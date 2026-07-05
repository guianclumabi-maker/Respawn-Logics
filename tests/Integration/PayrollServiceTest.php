<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Money-math guard for the payroll engine. This is a DIRECT test of PayrollService
 * (no HTTP server needed) — it seeds an employee + monthly schedule + approved timesheets,
 * runs payroll, and asserts the payslip RECONCILES:
 *   - net_pay == gross_pay - total_deductions
 *   - sum(earning line items)   == gross_pay
 *   - sum(deduction line items) == total_deductions
 * These invariants must hold on any correct payslip. They currently catch a rounding drift
 * (line items rounded individually while totals round the sum) — once the rounding is made
 * consistent, they lock the money math against regressions forever.
 */
class PayrollServiceTest extends TestCase
{
    protected static $pdo;
    protected static $tenantId;
    protected static $empId;
    protected static $schedId;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../../backend/services/PayrollService.php';
        global $pdo;
        self::$pdo = $pdo;

        self::$tenantId = \FixtureHelper::createTenant($pdo, 'Payroll Tenant');

        // Employee with a known monthly base salary, not a minimum-wage earner.
        self::$empId = \FixtureHelper::createUser($pdo, self::$tenantId, 'payroll.emp@test.com', 'Employee');
        $pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([self::$empId]);

        // Monthly schedule + assignment.
        $pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")
            ->execute([self::$tenantId]);
        self::$schedId = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([self::$schedId, self::$empId]);

        // Approved timesheets: 22 working days x 8 regular hours in the period.
        $ins = $pdo->prepare(
            "INSERT INTO timesheets
                (tenant_id, employee_id, timesheet_date, regular_hours, overtime_hours, rest_day_hours,
                 special_day_hours, regular_holiday_hours, night_diff_hours, status)
             VALUES (?, ?, ?, 8, 0, 0, 0, 0, 0, 'Approved')"
        );
        // 2026 dates so every seeded statutory config is in effect (de-minimis starts 2026-01-06).
        for ($d = 1; $d <= 22; $d++) {
            $ins->execute([self::$tenantId, self::$empId, sprintf('2026-06-%02d', $d)]);
        }
    }

    public function testGenerateRunSucceeds(): array
    {
        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun(
            self::$tenantId, self::$schedId,
            '2026-06-01', '2026-06-30', '2026-06-30',
            self::$empId, 'Regular'
        );
        $this->assertTrue($res['success'] ?? false, 'generateRun should succeed: ' . json_encode($res));
        $this->assertArrayHasKey('run_id', $res);
        return $res;
    }

    /**
     * @depends testGenerateRunSucceeds
     */
    public function testPayslipReconciles(array $res): void
    {
        $runId = (int) $res['run_id'];

        $stmt = self::$pdo->prepare(
            "SELECT gross_pay, total_deductions, net_pay
             FROM payroll_run_employees WHERE payroll_run_id = ? AND employee_id = ?"
        );
        $stmt->execute([$runId, self::$empId]);
        $rec = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotEmpty($rec, 'a payroll_run_employees row should exist');

        $gross  = round((float) $rec['gross_pay'], 2);
        $deduct = round((float) $rec['total_deductions'], 2);
        $net    = round((float) $rec['net_pay'], 2);

        // Invariant 1: net = gross - deductions.
        $this->assertEqualsWithDelta($gross - $deduct, $net, 0.001, 'net_pay must equal gross - deductions');

        // Invariant 2: deduction line items reconcile to total_deductions.
        $d = self::$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payroll_deductions WHERE payroll_run_id = ? AND employee_id = ?");
        $d->execute([$runId, self::$empId]);
        $sumDed = round((float) $d->fetchColumn(), 2);
        $this->assertEqualsWithDelta($deduct, $sumDed, 0.001, 'deduction line items must sum to total_deductions');

        // Invariant 3: earning line items reconcile to gross_pay.
        $e = self::$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payroll_earnings WHERE payroll_run_id = ? AND employee_id = ?");
        $e->execute([$runId, self::$empId]);
        $sumEarn = round((float) $e->fetchColumn(), 2);
        $this->assertEqualsWithDelta($gross, $sumEarn, 0.001, 'earning line items must sum to gross_pay');
    }

    public function testForeignScheduleIsRejected(): void
    {
        // A schedule id from another tenant must not run payroll for this tenant.
        $otherTenant = \FixtureHelper::createTenant(self::$pdo, 'Other Payroll Tenant');
        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Other', 'Monthly')")
            ->execute([$otherTenant]);
        $foreignSchedId = (int) self::$pdo->lastInsertId();

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun(self::$tenantId, $foreignSchedId, '2025-06-01', '2025-06-30', '2025-06-30', self::$empId, 'Regular');
        $this->assertFalse($res['success'] ?? true, 'running payroll against another tenant\'s schedule must fail');
    }
}
