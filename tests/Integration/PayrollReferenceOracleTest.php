<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class PayrollReferenceOracleTest extends TestCase
{
    protected static $pdo;
    protected static $tenantId;
    protected static $schedId;

    // --- REFERENCE TABLE (independent statutory values, verified 2026-07) ---
    // Sources (2026 employee shares, monthly-paid):
    //  SSS  (RA 11199, 2026): 15% of MSC, EE share 5%; MSC floor P5,000 / ceiling P35,000
    //       in P500 brackets; MSC above P20,000 goes to the MPF/WISP individual account
    //       (the engine posts regular EE + WISP EE as one "SSS Contribution" line).
    //       -> 18,000 salary => MSC 18,000 => EE 900.00
    //       -> 30,000 salary => MSC 30,000 => EE 1,500.00 (1,000 regular + 500 MPF)
    //       -> 50,000/90,000 => MSC capped 35,000 => EE 1,750.00 (1,000 regular + 750 MPF)
    //  PhilHealth (UHC/RA 11223, 2026): flat 5% of monthly basic, split equally
    //       (EE 2.5%); floor P10,000 / ceiling P100,000.
    //       -> 18k => 450.00 | 30k => 750.00 | 50k => 1,250.00 | 90k => 2,250.00
    //  Pag-IBIG (HDMF, 2026): EE 2% of Fund Salary capped at P10,000 => 200.00 for all
    //       salaries >= P10,000.
    //
    //  TAX (TRAIN monthly withholding table, RA 10963, 2023+ schedule) and NET assume
    //  the DEFAULT monthly_pay_mode='fixed_salary': full attendance on every scheduled
    //  workday of June 2026 pays exactly the monthly base salary as basic.
    //    taxable = salary − (SSS_EE + PhilHealth_EE + PagIBIG_EE)
    //    tax     = bracket base + rate × (taxable − bracket lower limit)
    //    net     = salary − (SSS + PhilHealth + PagIBIG + tax)
    //  Hand-computed against the official bracket boundaries (20,833 / 33,333 / 66,667):
    //    18k: taxable 16,450.00 → tax 0.00      → net 16,450.00
    //    30k: taxable 27,550.00 → tax 1,007.55  → net 26,542.45
    //    50k: taxable 46,800.00 → tax 4,568.40  → net 42,231.60
    //    90k: taxable 85,800.00 → tax 13,325.05 → net 72,474.95
    private static $references = [
        18000 => [
            'sss' => 900.00,
            'philhealth' => 450.00,
            'pagibig' => 200.00,
            'tax' => 0.00,
            'net' => 16450.00,
        ],
        30000 => [
            'sss' => 1500.00,
            'philhealth' => 750.00,
            'pagibig' => 200.00,
            'tax' => 1007.55,
            'net' => 26542.45,
        ],
        50000 => [
            'sss' => 1750.00,
            'philhealth' => 1250.00,
            'pagibig' => 200.00,
            'tax' => 4568.40,
            'net' => 42231.60,
        ],
        90000 => [
            'sss' => 1750.00,
            'philhealth' => 2250.00,
            'pagibig' => 200.00,
            'tax' => 13325.05,
            'net' => 72474.95,
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

        // Approved timesheets on EVERY scheduled workday (Mon–Fri) of June 2026 —
        // full attendance, so fixed_salary mode pays exactly the monthly base with
        // zero absence deductions. (June 2026 has 22 weekdays.)
        $ins = self::$pdo->prepare(
            "INSERT INTO timesheets
                (tenant_id, employee_id, timesheet_date, regular_hours, overtime_hours, rest_day_hours,
                 special_day_hours, regular_holiday_hours, night_diff_hours, status)
             VALUES (?, ?, ?, 8, 0, 0, 0, 0, 0, 'Approved')"
        );
        for ($d = 1; $d <= 30; $d++) {
            $date = sprintf('2026-06-%02d', $d);
            if ((int) date('N', strtotime($date)) >= 6) continue; // skip weekends
            $ins->execute([self::$tenantId, $empId, $date]);
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
