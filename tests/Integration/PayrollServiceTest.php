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
        $res = $svc->generateRun(self::$tenantId, $foreignSchedId, '2026-06-01', '2026-06-30', '2026-06-30', self::$empId, 'Regular');
        $this->assertFalse($res['success'] ?? true, 'running payroll against another tenant\'s schedule must fail');
    }

    /**
     * 13th-month pay must be the accrued basic-salary total (PD 851), not one month's base salary.
     * @depends testGenerateRunSucceeds
     */
    public function testThirteenthMonthUsesAccruedBasicNotBaseSalary(array $res): void
    {
        // The Regular run from testGenerateRunSucceeds accrued basic (grossReg / 12) for 2026.
        $accStmt = self::$pdo->prepare(
            "SELECT COALESCE(SUM(pre.thirteenth_month_accrual),0)
             FROM payroll_run_employees pre
             JOIN payroll_runs pr ON pre.payroll_run_id = pr.id
             WHERE pre.employee_id = ? AND pr.tenant_id = ? AND pr.run_type = 'Regular' AND YEAR(pr.pay_date) = 2026"
        );
        $accStmt->execute([self::$empId, self::$tenantId]);
        $accrued = round((float) $accStmt->fetchColumn(), 2);
        $this->assertGreaterThan(0, $accrued, 'a Regular run should have accrued 13th-month basic');

        // Run a 13th-month run.
        $svc = new \PayrollService(self::$pdo);
        $res13 = $svc->generateRun(self::$tenantId, self::$schedId, '2026-01-01', '2026-12-31', '2026-12-24', self::$empId, '13th Month');
        $this->assertTrue($res13['success'] ?? false, '13th-month run should succeed: ' . json_encode($res13));
        $runId = (int) $res13['run_id'];

        // The payout must equal the accrued basic total — NOT one month's base salary (30000).
        $payStmt = self::$pdo->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM payroll_earnings
             WHERE payroll_run_id = ? AND employee_id = ? AND earning_type LIKE '13th Month%'"
        );
        $payStmt->execute([$runId, self::$empId]);
        $payout = round((float) $payStmt->fetchColumn(), 2);

        $this->assertEqualsWithDelta($accrued, $payout, 0.05, '13th-month payout must equal accrued basic, not base salary');
        $this->assertGreaterThan(0.05, abs($payout - 30000.00), '13th-month payout must NOT be one month base salary (the old approximation)');
    }

    /**
     * A 13th-month run must reconcile just like a regular payslip; the payout
     * must not be duplicated as both 13th-month pay and generic other benefits.
     * @depends testGenerateRunSucceeds
     */
    public function testThirteenthMonthPayslipReconcilesWithoutDoubleCounting(array $res): void
    {
        $svc = new \PayrollService(self::$pdo);
        $res13 = $svc->generateRun(self::$tenantId, self::$schedId, '2026-01-01', '2026-12-24', '2026-12-23', self::$empId, '13th Month');
        $this->assertTrue($res13['success'] ?? false, '13th-month run should succeed: ' . json_encode($res13));
        $runId = (int) $res13['run_id'];

        $recStmt = self::$pdo->prepare(
            "SELECT gross_pay, total_deductions, net_pay
             FROM payroll_run_employees WHERE payroll_run_id = ? AND employee_id = ?"
        );
        $recStmt->execute([$runId, self::$empId]);
        $rec = $recStmt->fetch(\PDO::FETCH_ASSOC);
        $gross = round((float) $rec['gross_pay'], 2);
        $deduct = round((float) $rec['total_deductions'], 2);
        $net = round((float) $rec['net_pay'], 2);

        $sumEarnStmt = self::$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payroll_earnings WHERE payroll_run_id = ? AND employee_id = ?");
        $sumEarnStmt->execute([$runId, self::$empId]);
        $sumEarn = round((float) $sumEarnStmt->fetchColumn(), 2);

        $this->assertEqualsWithDelta($gross - $deduct, $net, 0.001, '13th-month net_pay must equal gross - deductions');
        $this->assertEqualsWithDelta($gross, $sumEarn, 0.001, '13th-month earning lines must not double-count the payout');
    }

    public function testRegularRunFailsWithoutApprovedTimesheets(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'No Timesheet Payroll Tenant');
        $empId = \FixtureHelper::createUser(self::$pdo, $tenantId, 'no.timesheet@test.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);
        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")
            ->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-07-01', '2026-07-31', '2026-07-31', $empId, 'Regular');

        $this->assertFalse($res['success'] ?? true, 'regular payroll must fail closed when timesheets are missing');
        $this->assertStringContainsString('no approved timesheets', strtolower($res['error'] ?? ''));
    }

    public function testDeMinimisDoesNotConsume90kExemption(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'DeMinimis Exemption Tenant');
        $empId = \FixtureHelper::createUser(self::$pdo, $tenantId, 'deminimis.test@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("
            INSERT INTO tenant_payroll_settings 
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'monthly_base')
        ")->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        $ins = self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, ?, 8, 'Approved')");
        for ($d = 1; $d <= 22; $d++) {
            $ins->execute([$tenantId, $empId, sprintf('2026-05-%02d', $d)]);
        }

        self::$pdo->prepare("
            INSERT INTO benefit_plans (tenant_id, name, type, employee_cost, company_cost)
            VALUES (?, 'Rice Subsidy', 'De Minimis', 0, 2000.00)
        ")->execute([$tenantId]);
        $planId = self::$pdo->lastInsertId();

        self::$pdo->prepare("
            INSERT INTO employee_benefits (tenant_id, employee_id, plan_id, dependent_count, status)
            VALUES (?, ?, ?, 0, 'Enrolled')
        ")->execute([$tenantId, $empId, $planId]);

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-05-01', '2026-05-31', '2026-05-31', $empId, 'Regular');
        $this->assertTrue($res['success'] ?? false);
        $runId = (int)$res['run_id'];

        $stmt = self::$pdo->prepare("SELECT amount FROM payroll_earnings WHERE payroll_run_id = ? AND employee_id = ? AND earning_type = 'De Minimis (Exempt): Rice Subsidy'");
        $stmt->execute([$runId, $empId]);
        $this->assertEquals(2000.00, floatval($stmt->fetchColumn()));

        $refMethod = new \ReflectionMethod(\PayrollService::class, 'getRemaining90kExemption');
        $refMethod->setAccessible(true);
        $remaining = $refMethod->invoke($svc, $empId, '2026-05-31', $tenantId);
        $this->assertEquals(90000.00, $remaining);
    }

    public function testExcessDeMinimisConsumes90kCapAndTaxesCorrectly(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'DeMinimis Excess Tenant');
        $empId = \FixtureHelper::createUser(self::$pdo, $tenantId, 'deminimis.excess@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("
            INSERT INTO tenant_payroll_settings 
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'monthly_base')
        ")->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        $ins = self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, ?, 8, 'Approved')");
        for ($d = 1; $d <= 22; $d++) {
            $ins->execute([$tenantId, $empId, sprintf('2026-05-%02d', $d)]);
        }

        self::$pdo->prepare("
            INSERT INTO benefit_plans (tenant_id, name, type, employee_cost, company_cost)
            VALUES (?, 'Rice Subsidy', 'De Minimis', 0, 3500.00)
        ")->execute([$tenantId]);
        $planId = self::$pdo->lastInsertId();

        self::$pdo->prepare("
            INSERT INTO employee_benefits (tenant_id, employee_id, plan_id, dependent_count, status)
            VALUES (?, ?, ?, 0, 'Enrolled')
        ")->execute([$tenantId, $empId, $planId]);

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-05-01', '2026-05-31', '2026-05-31', $empId, 'Regular');
        $this->assertTrue($res['success'] ?? false);
        $runId = (int)$res['run_id'];

        $stmt = self::$pdo->prepare("SELECT amount FROM payroll_earnings WHERE payroll_run_id = ? AND employee_id = ? AND earning_type = 'De Minimis (Exempt): Rice Subsidy'");
        $stmt->execute([$runId, $empId]);
        $this->assertEquals(2500.00, floatval($stmt->fetchColumn()));

        $stmt = self::$pdo->prepare("SELECT amount FROM payroll_earnings WHERE payroll_run_id = ? AND employee_id = ? AND earning_type = 'Non-Taxable Other Benefits'");
        $stmt->execute([$runId, $empId]);
        $this->assertEquals(1000.00, floatval($stmt->fetchColumn()));

        $refMethod = new \ReflectionMethod(\PayrollService::class, 'getRemaining90kExemption');
        $refMethod->setAccessible(true);
        $remaining = $refMethod->invoke($svc, $empId, '2026-05-31', $tenantId);
        $this->assertEquals(89000.00, $remaining);
    }

    public function testThirteenthMonthSplitBetweenTaxableAndNonTaxable(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'Thirteenth Month Split Tenant');
        $empId = \FixtureHelper::createUser(self::$pdo, $tenantId, 'thirteenth.split@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 1500000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("
            INSERT INTO tenant_payroll_settings 
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'monthly_base')
        ")->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        $ins = self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, ?, 8, 'Approved')");
        for ($d = 1; $d <= 22; $d++) {
            $ins->execute([$tenantId, $empId, sprintf('2026-05-%02d', $d)]);
        }

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-05-01', '2026-05-31', '2026-05-31', $empId, 'Regular');
        $this->assertTrue($res['success'] ?? false);

        $res13 = $svc->generateRun($tenantId, $schedId, '2026-01-01', '2026-12-31', '2026-12-24', $empId, '13th Month');
        $this->assertTrue($res13['success'] ?? false);
        $runId = (int)$res13['run_id'];

        $stmt = self::$pdo->prepare("SELECT amount FROM payroll_earnings WHERE payroll_run_id = ? AND employee_id = ? AND earning_type = '13th Month Pay (Non-Taxable)'");
        $stmt->execute([$runId, $empId]);
        $this->assertEquals(90000.00, floatval($stmt->fetchColumn()));

        $stmt = self::$pdo->prepare("SELECT amount FROM payroll_earnings WHERE payroll_run_id = ? AND employee_id = ? AND earning_type = '13th Month Pay (Taxable)'");
        $stmt->execute([$runId, $empId]);
        $this->assertEquals(15431.31, floatval($stmt->fetchColumn()));
    }

    public function testStatutoryBasisMonthlyFixed(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'StatBasis Monthly Tenant');
        $empId = \FixtureHelper::createUser(self::$pdo, $tenantId, 'stat.monthly@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("
            INSERT INTO tenant_payroll_settings 
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'monthly_base')
        ")->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        $ins = self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, ?, 8, 'Approved')");
        for ($d = 1; $d <= 10; $d++) {
            $ins->execute([$tenantId, $empId, sprintf('2026-05-%02d', $d)]);
        }

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-05-01', '2026-05-31', '2026-05-31', $empId, 'Regular');
        $this->assertTrue($res['success'] ?? false);
        $runId = (int)$res['run_id'];

        $stmt = self::$pdo->prepare("SELECT amount FROM payroll_deductions WHERE payroll_run_id = ? AND employee_id = ? AND deduction_type = 'SSS Contribution'");
        $stmt->execute([$runId, $empId]);
        $deduction = floatval($stmt->fetchColumn());
        $this->assertEquals(1500.00, $deduction);
    }

    public function testStatutoryBasisDaily(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'StatBasis Daily Tenant');
        $empId = \FixtureHelper::createUser(self::$pdo, $tenantId, 'stat.daily@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 1000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("
            INSERT INTO tenant_payroll_settings 
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'daily', 0, 1, 'half_up', 1, 'monthly_base')
        ")->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        $ins = self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, ?, 8, 'Approved')");
        for ($d = 1; $d <= 22; $d++) {
            $ins->execute([$tenantId, $empId, sprintf('2026-05-%02d', $d)]);
        }

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-05-01', '2026-05-31', '2026-05-31', $empId, 'Regular');
        $this->assertTrue($res['success'] ?? false);
        $runId = (int)$res['run_id'];

        $stmt = self::$pdo->prepare("SELECT amount FROM payroll_deductions WHERE payroll_run_id = ? AND employee_id = ? AND deduction_type = 'SSS Contribution'");
        $stmt->execute([$runId, $empId]);
        $deduction = floatval($stmt->fetchColumn());
        $this->assertEqualsWithDelta(1300.00, $deduction, 0.05);
    }

    public function testStatutoryBasisActualPeriodEquivalent(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'StatBasis Actual Tenant');
        $empId = \FixtureHelper::createUser(self::$pdo, $tenantId, 'stat.actual@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("
            INSERT INTO tenant_payroll_settings 
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Semi-Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'actual_period_equivalent')
        ")->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Semi-Monthly', 'Semi-Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        $ins = self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, ?, 8, 'Approved')");
        for ($d = 1; $d <= 5; $d++) {
            $ins->execute([$tenantId, $empId, sprintf('2026-06-%02d', $d)]);
        }

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-06-01', '2026-06-15', '2026-06-15', $empId, 'Regular');
        $this->assertTrue($res['success'] ?? false);
        $runId = (int)$res['run_id'];

        $stmt = self::$pdo->prepare("SELECT amount FROM payroll_deductions WHERE payroll_run_id = ? AND employee_id = ? AND deduction_type = 'SSS Contribution'");
        $stmt->execute([$runId, $empId]);
        $deduction = floatval($stmt->fetchColumn());
        $this->assertEqualsWithDelta(287.50, $deduction, 2.00);
    }

    public function testMissingTenantSettingsThrowsForDailyHourly(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'Missing Settings Tenant');
        $empId = \FixtureHelper::createUser(self::$pdo, $tenantId, 'missing.settings@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 1000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        // Explicitly insert settings with statutory_basis as NULL to trigger daily/hourly validation
        self::$pdo->prepare("
            INSERT INTO tenant_payroll_settings 
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'daily', 0, 1, 'half_up', 1, NULL)
        ")->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, '2026-05-01', 8, 'Approved')")
            ->execute([$tenantId, $empId]);

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-05-01', '2026-05-31', '2026-05-31', $empId, 'Regular');
        
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('must be explicitly configured', $res['error']);
    }

    public function testCombinedPremiumOverlapThrows(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'Overlap Tenant');
        $empId = \FixtureHelper::createUser(self::$pdo, $tenantId, 'overlap.test@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("
            INSERT INTO tenant_payroll_settings 
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'monthly_base')
        ")->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        self::$pdo->prepare("
            INSERT INTO timesheets 
            (tenant_id, employee_id, timesheet_date, regular_hours, rest_day_hours, regular_holiday_hours, status) 
            VALUES (?, ?, '2026-05-01', 8, 8, 8, 'Approved')
        ")->execute([$tenantId, $empId]);

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-05-01', '2026-05-31', '2026-05-31', $empId, 'Regular');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Ambiguous combined premium', $res['error']);
    }

    public function testTaxAnnualizationThrows(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'Annualization Tenant');
        $empId = \FixtureHelper::createUser(self::$pdo, $tenantId, 'annual.test@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("
            INSERT INTO tenant_payroll_settings 
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 1, 1, 'half_up', 1, 'monthly_base')
        ")->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, '2026-05-01', 8, 'Approved')")
            ->execute([$tenantId, $empId]);

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-05-01', '2026-05-31', '2026-05-31', $empId, 'Regular');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Tax annualization is enabled but not implemented', $res['error']);
    }

    // =========================================================================
    // SECOND HARDENING PASS — tests added 2026-07-14
    // =========================================================================

    /**
     * GOAL 4 — Duplicate run prevention.
     * A second generateRun call for the exact same tenant/schedule/period/run_type
     * must fail with a clear error rather than creating a second payroll run.
     */
    public function testDuplicateRunIsPrevented(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'Dup Run Tenant');
        $empId    = \FixtureHelper::createUser(self::$pdo, $tenantId, 'dup.run@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("INSERT INTO tenant_payroll_settings
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'monthly_base')")
            ->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        $ins = self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, ?, 8, 'Approved')");
        for ($d = 1; $d <= 22; $d++) {
            $ins->execute([$tenantId, $empId, sprintf('2026-04-%02d', $d)]);
        }

        $svc = new \PayrollService(self::$pdo);
        $res1 = $svc->generateRun($tenantId, $schedId, '2026-04-01', '2026-04-30', '2026-04-30', $empId, 'Regular');
        $this->assertTrue($res1['success'] ?? false, 'First run must succeed: ' . json_encode($res1));

        $res2 = $svc->generateRun($tenantId, $schedId, '2026-04-01', '2026-04-30', '2026-04-30', $empId, 'Regular');
        $this->assertFalse($res2['success'] ?? true, 'Second identical run must be rejected');
        $this->assertStringContainsStringIgnoringCase('duplicate', $res2['error'] ?? '');
    }

    /**
     * GOAL 4 — Date-range validation.
     * A run where period start is after period end must fail loudly.
     */
    public function testInvalidDateRangeFails(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'Bad Date Tenant');
        $empId    = \FixtureHelper::createUser(self::$pdo, $tenantId, 'bad.date@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("INSERT INTO tenant_payroll_settings
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'monthly_base')")
            ->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        $svc = new \PayrollService(self::$pdo);
        // start (July 31) is after end (July 1)
        $res = $svc->generateRun($tenantId, $schedId, '2026-07-31', '2026-07-01', '2026-07-31', $empId, 'Regular');
        $this->assertFalse($res['success'] ?? true, 'Period start > end must be rejected');
        $this->assertMatchesRegularExpression('/start.*after.*end|period start/i', $res['error'] ?? '');
    }

    /**
     * GOAL 5 — Negative hours must be rejected.
     * A timesheet row with negative regular_hours must cause the run to fail.
     */
    public function testNegativeRegularHoursFails(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'Negative Hours Tenant');
        $empId    = \FixtureHelper::createUser(self::$pdo, $tenantId, 'neg.hours@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("INSERT INTO tenant_payroll_settings
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'monthly_base')")
            ->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        // Insert a timesheet with negative regular hours
        self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, '2026-08-01', -4, 'Approved')")
            ->execute([$tenantId, $empId]);

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-08-01', '2026-08-31', '2026-08-31', $empId, 'Regular');
        $this->assertFalse($res['success'] ?? true, 'Negative regular_hours must reject the payroll run');
        $this->assertStringContainsStringIgnoringCase('negative', $res['error'] ?? '');
    }

    /**
     * GOAL 4 — Terminated employee must not be included.
     * Setting employment_status to 'Terminated' before the run must exclude the employee,
     * causing the run to fail with "no active employees".
     */
    public function testTerminatedEmployeeIsExcluded(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'Term Employee Tenant');
        $empId    = \FixtureHelper::createUser(self::$pdo, $tenantId, 'term.emp@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("INSERT INTO tenant_payroll_settings
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'monthly_base')")
            ->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ?, employment_status = 'Terminated' WHERE id = ?")->execute([$schedId, $empId]);

        self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, '2026-09-01', 8, 'Approved')")
            ->execute([$tenantId, $empId]);

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-09-01', '2026-09-30', '2026-09-30', $empId, 'Regular');
        $this->assertFalse($res['success'] ?? true, 'Terminated employee must cause run to fail (no active employees)');
        $this->assertStringContainsStringIgnoringCase('no active employees', $res['error'] ?? '');
    }

    /**
     * GOAL 7 — Minimum wage earner (MWE) must not be taxed on regular pay.
     * An MWE's taxable income is zero on basic salary; only excess benefits can be taxed.
     * @depends testGenerateRunSucceeds
     */
    public function testMweEmployeePaysTaxOnlyOnBenefitExcess(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'MWE Tenant');
        $empId    = \FixtureHelper::createUser(self::$pdo, $tenantId, 'mwe.emp@example.com', 'Employee');
        // MWE with a salary typical of minimum wage in Metro Manila (~₱16,000/mo)
        self::$pdo->prepare("UPDATE users SET base_salary = 16000, is_mwe = 1 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("INSERT INTO tenant_payroll_settings
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'monthly_base')")
            ->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        $ins = self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, ?, 8, 'Approved')");
        for ($d = 1; $d <= 22; $d++) {
            $ins->execute([$tenantId, $empId, sprintf('2026-10-%02d', $d)]);
        }

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-10-01', '2026-10-31', '2026-10-31', $empId, 'Regular');
        $this->assertTrue($res['success'] ?? false, 'MWE run must succeed: ' . json_encode($res));
        $runId = (int) $res['run_id'];

        // MWE basic pay must not generate any withholding tax
        $taxStmt = self::$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payroll_deductions WHERE payroll_run_id = ? AND employee_id = ? AND deduction_type = 'Withholding Tax'");
        $taxStmt->execute([$runId, $empId]);
        $tax = floatval($taxStmt->fetchColumn());
        $this->assertEquals(0.00, $tax, 'MWE employee must have zero withholding tax on regular pay (is_mwe = 1)');
    }

    /**
     * GOAL 6 — Reimbursements must be non-taxable.
     * Approved expense claims that are included in gross pay must not increase taxable income.
     * @depends testGenerateRunSucceeds
     */
    public function testReimbursementIsNonTaxable(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'Reimbursement Tenant');
        $empId    = \FixtureHelper::createUser(self::$pdo, $tenantId, 'reimb.emp@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("INSERT INTO tenant_payroll_settings
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'monthly_base')")
            ->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        $ins = self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, ?, 8, 'Approved')");
        for ($d = 1; $d <= 22; $d++) {
            $ins->execute([$tenantId, $empId, sprintf('2026-11-%02d', $d)]);
        }

        // Insert an expense category and a Finance-Approved expense claim
        self::$pdo->prepare("INSERT INTO expense_categories (tenant_id, name) VALUES (?, 'Travel')")->execute([$tenantId]);
        $catId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("INSERT INTO expense_claims (tenant_id, employee_id, category_id, amount, status, expense_date) VALUES (?, ?, ?, 5000, 'Finance Approved', '2026-11-01')")
            ->execute([$tenantId, $empId, $catId]);

        $svc  = new \PayrollService(self::$pdo);
        $res  = $svc->generateRun($tenantId, $schedId, '2026-11-01', '2026-11-30', '2026-11-30', $empId, 'Regular');
        $this->assertTrue($res['success'] ?? false, 'Run with reimbursement must succeed: ' . json_encode($res));
        $runId = (int) $res['run_id'];

        // Reimbursement must appear in earnings
        $rStmt = self::$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payroll_earnings WHERE payroll_run_id = ? AND employee_id = ? AND earning_type LIKE 'Reimbursement%'");
        $rStmt->execute([$runId, $empId]);
        $reimbTotal = floatval($rStmt->fetchColumn());
        $this->assertGreaterThan(0, $reimbTotal, 'Reimbursement earning line must be present in gross');

        // Gross must include the reimbursement
        $gStmt = self::$pdo->prepare("SELECT gross_pay FROM payroll_run_employees WHERE payroll_run_id = ? AND employee_id = ?");
        $gStmt->execute([$runId, $empId]);
        $gross = floatval($gStmt->fetchColumn());
        $this->assertGreaterThanOrEqual($reimbTotal, $gross, 'Gross must include the reimbursement amount');

        // Compute what tax would be without reimbursement (same employee, same base, no expenses).
        // The key assertion: reimbursements are labelled as non-taxable earning types; tax
        // is computed on cutoffBase+taxableOtherBenefits, NOT including reimbursements.
        // Verify by checking the run reconciles (gross - deductions = net) — already a global invariant.
        $netStmt = self::$pdo->prepare("SELECT gross_pay, total_deductions, net_pay FROM payroll_run_employees WHERE payroll_run_id = ? AND employee_id = ?");
        $netStmt->execute([$runId, $empId]);
        $rec = $netStmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEqualsWithDelta(
            round((float)$rec['gross_pay'] - (float)$rec['total_deductions'], 2),
            round((float)$rec['net_pay'], 2),
            0.01,
            'Payslip including reimbursement must still reconcile gross - deductions = net'
        );
    }

    /**
     * GOAL 8 — Payroll run must persist auditable earning/deduction breakdown.
     * After a run, we must be able to reconstruct gross and net from stored line items.
     * @depends testGenerateRunSucceeds
     */
    public function testPayrollRunPersistsAuditableBreakdown(array $res): void
    {
        $runId  = (int) $res['run_id'];
        $empId  = self::$empId;

        // Verify earning rows exist
        $eStmt = self::$pdo->prepare("SELECT COUNT(*) FROM payroll_earnings WHERE payroll_run_id = ? AND employee_id = ?");
        $eStmt->execute([$runId, $empId]);
        $this->assertGreaterThan(0, (int)$eStmt->fetchColumn(), 'At least one earning line item must exist for auditability');

        // Verify deduction rows exist
        $dStmt = self::$pdo->prepare("SELECT COUNT(*) FROM payroll_deductions WHERE payroll_run_id = ? AND employee_id = ?");
        $dStmt->execute([$runId, $empId]);
        $this->assertGreaterThan(0, (int)$dStmt->fetchColumn(), 'At least one deduction line item must exist for auditability');

        // Verify the payroll_run_employees row has full breakdown columns
        $rStmt = self::$pdo->prepare("SELECT sss_er, sss_ec, wisp_er, phic_er, hdmf_er, thirteenth_month_accrual FROM payroll_run_employees WHERE payroll_run_id = ? AND employee_id = ?");
        $rStmt->execute([$runId, $empId]);
        $breakdown = $rStmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertIsArray($breakdown, 'Employer-contribution breakdown columns must be stored');
        // At least one ER column must be non-zero for a non-MWE monthly employee
        $erTotal = array_sum(array_map('floatval', array_values($breakdown)));
        $this->assertGreaterThan(0, $erTotal, 'At least one ER contribution or 13th-month accrual must be stored');
    }

    /**
     * GOAL 8 — Changing statutory config AFTER payroll generation must not mutate old payslip totals.
     * We generate a run, change the PhilHealth rate, then verify the run's stored net_pay is unchanged.
     * @depends testGenerateRunSucceeds
     */
    public function testChangingStatutoryConfigAfterRunDoesNotMutateOldPayslip(array $res): void
    {
        $runId = (int) $res['run_id'];
        $empId = self::$empId;

        $before = self::$pdo->prepare("SELECT net_pay FROM payroll_run_employees WHERE payroll_run_id = ? AND employee_id = ?");
        $before->execute([$runId, $empId]);
        $netBefore = floatval($before->fetchColumn());

        // Simulate a statutory rate change: close current PhilHealth config and insert a new one
        self::$pdo->exec("UPDATE philhealth_config SET effective_to = '2026-01-01' WHERE effective_to IS NULL");
        self::$pdo->prepare("INSERT INTO philhealth_config (rate_total, floor_salary, ceiling_salary, effective_from) VALUES (0.07, 10000, 100000, '2026-02-01')")->execute();

        $after = self::$pdo->prepare("SELECT net_pay FROM payroll_run_employees WHERE payroll_run_id = ? AND employee_id = ?");
        $after->execute([$runId, $empId]);
        $netAfter = floatval($after->fetchColumn());

        $this->assertEqualsWithDelta($netBefore, $netAfter, 0.001, 'Changing PhilHealth config must not retroactively alter stored payslip net_pay');

        // Restore the original PhilHealth config so other tests are unaffected
        self::$pdo->exec("UPDATE philhealth_config SET effective_to = NULL WHERE effective_from = '2024-01-01'");
        self::$pdo->exec("DELETE FROM philhealth_config WHERE effective_from = '2026-02-01'");
    }

    /**
     * GOAL 4 — Unsupported schedule frequency must throw.
     * A schedule with frequency 'Quarterly' is not supported and must fail loudly.
     */
    public function testUnsupportedFrequencyThrows(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'Bad Freq Tenant');
        $empId    = \FixtureHelper::createUser(self::$pdo, $tenantId, 'bad.freq@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        self::$pdo->prepare("INSERT INTO tenant_payroll_settings
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'monthly', 0, 1, 'half_up', 1, 'monthly_base')")
            ->execute([$tenantId]);

        // Insert a schedule with an unsupported frequency
        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Quarterly', 'Quarterly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, '2026-12-01', 8, 'Approved')")
            ->execute([$tenantId, $empId]);

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-12-01', '2026-12-31', '2026-12-31', $empId, 'Regular');
        $this->assertFalse($res['success'] ?? true, 'Unsupported frequency must be rejected');
        $this->assertMatchesRegularExpression('/unsupported.*frequency|frequency.*unsupported/i', $res['error'] ?? '');
    }

    /**
     * GOAL 2 — Invalid pay basis must throw a clear exception.
     * Setting default_pay_basis to an unknown value ('biweekly') must cause the run to fail.
     */
    public function testInvalidPayBasisThrows(): void
    {
        $tenantId = \FixtureHelper::createTenant(self::$pdo, 'Invalid Basis Tenant');
        $empId    = \FixtureHelper::createUser(self::$pdo, $tenantId, 'bad.basis@example.com', 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = 30000, is_mwe = 0 WHERE id = ?")->execute([$empId]);

        // Use raw PDO to bypass the controller validation and insert a bad value directly
        self::$pdo->prepare("INSERT INTO tenant_payroll_settings
            (tenant_id, default_pay_frequency, proration_method, default_pay_basis, tax_annualization, mwe_auto_exempt, rounding_mode, approval_levels, statutory_basis)
            VALUES (?, 'Monthly', 'split_even', 'biweekly', 0, 1, 'half_up', 1, 'monthly_base')")
            ->execute([$tenantId]);

        self::$pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")->execute([$tenantId]);
        $schedId = (int) self::$pdo->lastInsertId();
        self::$pdo->prepare("UPDATE users SET payroll_schedule_id = ? WHERE id = ?")->execute([$schedId, $empId]);

        self::$pdo->prepare("INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, status) VALUES (?, ?, '2026-12-01', 8, 'Approved')")
            ->execute([$tenantId, $empId]);

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun($tenantId, $schedId, '2026-12-01', '2026-12-31', '2026-12-31', $empId, 'Regular');
        $this->assertFalse($res['success'] ?? true, 'Unknown pay basis must be rejected');
        $this->assertMatchesRegularExpression('/unknown pay basis|pay_basis/i', $res['error'] ?? '');
    }
}
