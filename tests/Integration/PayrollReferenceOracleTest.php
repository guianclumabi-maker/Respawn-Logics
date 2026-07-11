<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class PayrollReferenceOracleTest extends TestCase
{
    protected static $pdo;
    protected static $tenantId;
    protected static $schedId;

    // --- REFERENCE TABLE (Fill from sweldongpinoy.com or CPA) ---
    // Every reference value is null by default, which makes the test skip automatically.
    // The moment you fill in values for a salary, it runs the assertions.
    private static $references = [
        18000 => [
            'sss' => null,
            'philhealth' => null,
            'pagibig' => null,
            'tax' => null,
            'net' => null,
        ],
        30000 => [
            'sss' => null,
            'philhealth' => null,
            'pagibig' => null,
            'tax' => null,
            'net' => null,
        ],
        50000 => [
            'sss' => null,
            'philhealth' => null,
            'pagibig' => null,
            'tax' => null,
            'net' => null,
        ],
        90000 => [
            'sss' => null,
            'philhealth' => null,
            'pagibig' => null,
            'tax' => null,
            'net' => null,
        ],
    ];

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../../backend/services/PayrollService.php';
        global $pdo;
        self::$pdo = $pdo;

        self::$tenantId = \FixtureHelper::createTenant($pdo, 'Oracle Test Tenant');

        // Monthly schedule + assignment.
        $pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")
            ->execute([self::$tenantId]);
        self::$schedId = (int) $pdo->lastInsertId();
    }

    public function testSalary18k(): void
    {
        $this->runOracleCase(18000);
    }

    public function testSalary30k(): void
    {
        $this->runOracleCase(30000);
    }

    public function testSalary50k(): void
    {
        $this->runOracleCase(50000);
    }

    public function testSalary90k(): void
    {
        $this->runOracleCase(90000);
    }

    private function runOracleCase(int $salary): void
    {
        $ref = self::$references[$salary];
        if (
            $ref['sss'] === null &&
            $ref['philhealth'] === null &&
            $ref['pagibig'] === null &&
            $ref['tax'] === null &&
            $ref['net'] === null
        ) {
            $this->markTestSkipped("Fill {$salary} reference values from sweldongpinoy.com to activate this test.");
            return;
        }

        // Create a new employee for this salary case
        $email = "oracle.emp.{$salary}@test.com";
        $empId = \FixtureHelper::createUser(self::$pdo, self::$tenantId, $email, 'Employee');
        self::$pdo->prepare("UPDATE users SET base_salary = ?, is_mwe = 0, payroll_schedule_id = ?, employee_id = ? WHERE id = ?")
            ->execute([$salary, self::$schedId, 'EMP-' . $salary, $empId]);

        // Approved timesheets: 22 working days x 8 regular hours
        $ins = self::$pdo->prepare(
            "INSERT INTO timesheets
                (tenant_id, employee_id, timesheet_date, regular_hours, overtime_hours, rest_day_hours,
                 special_day_hours, regular_holiday_hours, night_diff_hours, status)
             VALUES (?, ?, ?, 8, 0, 0, 0, 0, 0, 'Approved')"
        );
        for ($d = 1; $d <= 22; $d++) {
            $ins->execute([self::$tenantId, $empId, sprintf('2026-06-%02d', $d)]);
        }

        $svc = new \PayrollService(self::$pdo);
        $res = $svc->generateRun(
            self::$tenantId, self::$schedId,
            '2026-06-01', '2026-06-30', '2026-06-30',
            $empId, 'Regular'
        );

        $this->assertTrue($res['success'] ?? false, "Payroll run for {$salary} should succeed");
        $runId = (int)$res['run_id'];

        // Retrieve the generated payroll totals
        $stmt = self::$pdo->prepare(
            "SELECT gross_pay, total_deductions, net_pay
             FROM payroll_run_employees WHERE payroll_run_id = ? AND employee_id = ?"
        );
        $stmt->execute([$runId, $empId]);
        $totals = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotEmpty($totals, 'payroll_run_employees row should exist');

        // Retrieve SSS, PhilHealth, Pag-IBIG, and withholding tax deductions
        $dedStmt = self::$pdo->prepare(
            "SELECT deduction_type, amount FROM payroll_deductions
             WHERE payroll_run_id = ? AND employee_id = ?"
        );
        $dedStmt->execute([$runId, $empId]);
        $deductions = $dedStmt->fetchAll(\PDO::FETCH_ASSOC);

        $sss = 0.0;
        $phil = 0.0;
        $pag = 0.0;
        $tax = 0.0;

        foreach ($deductions as $d) {
            $type = strtolower($d['deduction_type']);
            if (strpos($type, 'sss') !== false) {
                $sss += (float)$d['amount'];
            } elseif (strpos($type, 'philhealth') !== false || strpos($type, 'phil') !== false) {
                $phil += (float)$d['amount'];
            } elseif (strpos($type, 'pagibig') !== false || strpos($type, 'pag') !== false || strpos($type, 'ibig') !== false || strpos($type, 'hdmf') !== false) {
                $pag += (float)$d['amount'];
            } elseif (strpos($type, 'withholding') !== false || strpos($type, 'tax') !== false) {
                $tax += (float)$d['amount'];
            }
        }

        $actualNet = (float)$totals['net_pay'];

        // Assert SSS
        if ($ref['sss'] !== null) {
            $this->assertEqualsWithDelta((float)$ref['sss'], $sss, 0.01, "SSS deduction for {$salary} does not match reference");
        }
        // Assert PhilHealth
        if ($ref['philhealth'] !== null) {
            $this->assertEqualsWithDelta((float)$ref['philhealth'], $phil, 0.01, "PhilHealth deduction for {$salary} does not match reference");
        }
        // Assert Pag-IBIG
        if ($ref['pagibig'] !== null) {
            $this->assertEqualsWithDelta((float)$ref['pagibig'], $pag, 0.01, "Pag-IBIG deduction for {$salary} does not match reference");
        }
        // Assert Tax
        if ($ref['tax'] !== null) {
            $this->assertEqualsWithDelta((float)$ref['tax'], $tax, 0.01, "Tax withholding for {$salary} does not match reference");
        }
        // Assert Net Pay
        if ($ref['net'] !== null) {
            $this->assertEqualsWithDelta((float)$ref['net'], $actualNet, 0.01, "Net pay for {$salary} does not match reference");
        }
    }
}
