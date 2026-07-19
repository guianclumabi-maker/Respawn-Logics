<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Locks the payroll-hardening behaviors introduced for production readiness:
 *  - pay-basis strategies (daily / hourly) derive the correct basic pay
 *  - exempt de minimis does NOT consume the ₱90k 13th-month/other-benefits bucket
 *  - statutory_basis='actual_period_equivalent' contributes on actual period pay
 *  - daily/hourly + monthly_base statutory basis surfaces a visible warning
 *  - unimplemented pay-component calc types fail LOUD (no silent wrong net pay)
 *  - loan_amortization deducts its fixed amount and warns about balance tracking
 *  - tax annualization remains fail-loud until implemented
 * Direct PayrollService tests (no HTTP server). Each case builds its own tenant.
 */
class PayrollHardeningTest extends TestCase
{
    protected static $pdo;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../../backend/services/PayrollService.php';
        global $pdo;
        self::$pdo = $pdo;
    }

    /** Build tenant + monthly schedule + employee with approved 8h timesheets. */
    private function makeCase(string $name, float $baseSalary, int $days, array $settings = []): array
    {
        $pdo = self::$pdo;
        $tenantId = \FixtureHelper::createTenant($pdo, $name);

        if (!empty($settings)) {
            $cols = array_merge(['tenant_id'], array_keys($settings));
            $marks = implode(',', array_fill(0, count($cols), '?'));
            $pdo->prepare("INSERT INTO tenant_payroll_settings (`" . implode('`,`', $cols) . "`) VALUES ($marks)")
                ->execute(array_merge([$tenantId], array_values($settings)));
        }

        $pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")
            ->execute([$tenantId]);
        $schedId = (int) $pdo->lastInsertId();

        $empId = \FixtureHelper::createUser($pdo, $tenantId, uniqid('h') . '@hardening.test', 'Employee');
        $pdo->prepare("UPDATE users SET base_salary = ?, is_mwe = 0, payroll_schedule_id = ? WHERE id = ?")
            ->execute([$baseSalary, $schedId, $empId]);

        $ins = $pdo->prepare(
            "INSERT INTO timesheets (tenant_id, employee_id, timesheet_date, regular_hours, overtime_hours,
             rest_day_hours, special_day_hours, regular_holiday_hours, night_diff_hours, status)
             VALUES (?, ?, ?, 8, 0, 0, 0, 0, 0, 'Approved')"
        );
        for ($d = 1; $d <= $days; $d++) {
            $ins->execute([$tenantId, $empId, sprintf('2026-06-%02d', $d)]);
        }
        return [$tenantId, $schedId, $empId];
    }

    private function runPayroll(array $case): array
    {
        [$tenantId, $schedId, $empId] = $case;
        $svc = new \PayrollService(self::$pdo);
        return $svc->generateRun($tenantId, $schedId, '2026-06-01', '2026-06-30', '2026-06-30', $empId, 'Regular');
    }

    private function earn(int $runId, int $empId, string $type): float
    {
        $s = self::$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payroll_earnings WHERE payroll_run_id = ? AND employee_id = ? AND earning_type = ?");
        $s->execute([$runId, $empId, $type]);
        return (float) $s->fetchColumn();
    }

    private function deduct(int $runId, int $empId, string $type): float
    {
        $s = self::$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payroll_deductions WHERE payroll_run_id = ? AND employee_id = ? AND deduction_type LIKE ?");
        $s->execute([$runId, $empId, $type]);
        return (float) $s->fetchColumn();
    }

    // ── Pay basis ─────────────────────────────────────────────────────────────

    public function testDailyPayBasisPaysDailyRate(): void
    {
        // base_salary = 1,000/day; 10 approved days x 8h; hourly = 1000/8 = 125.
        $case = $this->runPayroll($this->makeCase('HardDaily', 1000, 10, ['default_pay_basis' => 'daily']));
        $this->assertTrue($case['success'] ?? false, 'daily-basis run should succeed: ' . ($case['error'] ?? ''));
        [$tenantId] = [null]; // readability only
        $basic = $this->earn((int) $case['run_id'], $this->lastEmp(), 'Basic Pay (Hours)');
        $this->assertEqualsWithDelta(10000.00, $basic, 0.01, '10 days x 1,000/day must pay 10,000 basic');
    }

    public function testHourlyPayBasisPaysHourlyRate(): void
    {
        // base_salary = 150/hour; 5 days x 8h = 40h -> 6,000 basic.
        $case = $this->runPayroll($this->makeCase('HardHourly', 150, 5, ['default_pay_basis' => 'hourly']));
        $this->assertTrue($case['success'] ?? false, 'hourly-basis run should succeed: ' . ($case['error'] ?? ''));
        $basic = $this->earn((int) $case['run_id'], $this->lastEmp(), 'Basic Pay (Hours)');
        $this->assertEqualsWithDelta(6000.00, $basic, 0.01, '40h x 150/h must pay 6,000 basic');
    }

    // ── ₱90k bucket vs de minimis ────────────────────────────────────────────

    public function testExemptDeMinimisDoesNotConsume90kBucket(): void
    {
        $pdo = self::$pdo;
        $ids = $this->makeCase('Hard90k', 30000, 22);
        [$tenantId, $schedId, $empId] = $ids;

        // Rice Subsidy 2,000/month is inside its 2,500 ceiling -> fully exempt de minimis.
        $pdo->prepare("INSERT INTO benefit_plans (tenant_id, name, type, employee_cost, company_cost) VALUES (?, 'Rice Subsidy', 'De Minimis', 0, 2000)")
            ->execute([$tenantId]);
        $planId = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO employee_benefits (tenant_id, employee_id, plan_id, status, dependent_count) VALUES (?, ?, ?, 'Enrolled', 0)")
            ->execute([$tenantId, $empId, $planId]);

        $svc = new \PayrollService($pdo);
        $r1 = $svc->generateRun($tenantId, $schedId, '2026-06-01', '2026-06-30', '2026-06-30', $empId, 'Regular');
        $this->assertTrue($r1['success'] ?? false, 'regular run should succeed: ' . ($r1['error'] ?? ''));
        $exemptDm = $this->earn((int) $r1['run_id'], $empId, 'De Minimis (Exempt): Rice Subsidy');
        $this->assertEqualsWithDelta(2000.00, $exemptDm, 0.01, 'Rice subsidy within ceiling must be fully exempt');

        // 13th-month run: the payout must be FULLY non-taxable — the 2,000 exempt de
        // minimis above must NOT have consumed any of the ₱90k bucket.
        $r2 = $svc->generateRun($tenantId, $schedId, '2026-12-01', '2026-12-31', '2026-12-31', $empId, '13th Month');
        $this->assertTrue($r2['success'] ?? false, '13th-month run should succeed: ' . ($r2['error'] ?? ''));
        $nonTax = $this->earn((int) $r2['run_id'], $empId, '13th Month Pay (Non-Taxable)');
        $tax    = $this->earn((int) $r2['run_id'], $empId, '13th Month Pay (Taxable)');
        $this->assertGreaterThan(0, $nonTax, '13th month should have a non-taxable payout');
        $this->assertEqualsWithDelta(0.00, $tax, 0.01, 'No taxable 13th-month portion: exempt de minimis must not consume the 90k cap');
    }

    // ── Statutory basis strategy ──────────────────────────────────────────────

    public function testActualPeriodEquivalentContributesOnActualPay(): void
    {
        // Daily-paid 1,000/day, 10 days -> 10,000 actual pay; monthly multiplier 1.0
        // -> statutory base 10,000: SSS MSC 10,000 => EE 500; PhilHealth EE = 250.
        $ids = $this->makeCase('HardStatActual', 1000, 10, [
            'default_pay_basis' => 'daily',
            'statutory_basis'   => 'actual_period_equivalent',
        ]);
        $r = $this->runPayroll($ids);
        $this->assertTrue($r['success'] ?? false, 'run should succeed: ' . ($r['error'] ?? ''));
        [, , $empId] = $ids;
        $this->assertEqualsWithDelta(500.00, $this->deduct((int) $r['run_id'], $empId, 'SSS%'), 0.01, 'SSS on actual 10,000 MSC');
        $this->assertEqualsWithDelta(250.00, $this->deduct((int) $r['run_id'], $empId, 'PhilHealth%'), 0.01, 'PhilHealth on actual 10,000');
    }

    public function testDailyBasisOnMonthlyBaseWarnsVisibly(): void
    {
        $r = $this->runPayroll($this->makeCase('HardStatWarn', 1000, 10, ['default_pay_basis' => 'daily']));
        $this->assertTrue($r['success'] ?? false);
        $joined = implode(' | ', $r['warnings'] ?? []);
        $this->assertStringContainsString('statutory_basis', $joined, 'daily basis + monthly_base MSC must warn, not stay silent');
    }

    // ── Fail-loud configs ─────────────────────────────────────────────────────

    public function testUnimplementedComponentTypeFailsLoud(): void
    {
        $ids = $this->makeCase('HardCompFail', 30000, 22);
        [$tenantId] = $ids;
        self::$pdo->prepare("INSERT INTO pay_components (tenant_id, code, name, kind, calc_type, value, taxable, is_active) VALUES (?, 'FRM1', 'Formula Comp', 'earning', 'formula', NULL, 1, 1)")
            ->execute([$tenantId]);
        $r = $this->runPayroll($ids);
        $this->assertFalse($r['success'] ?? true, 'formula components are unimplemented and must fail the run');
        $this->assertStringContainsString('Formula Comp', $r['error'] ?? '', 'error must name the offending component');
    }

    public function testLoanAmortizationDeductsFixedAmountWithWarning(): void
    {
        $ids = $this->makeCase('HardLoan', 30000, 22);
        [$tenantId, , $empId] = $ids;
        self::$pdo->prepare("INSERT INTO pay_components (tenant_id, code, name, kind, calc_type, value, taxable, is_active) VALUES (?, 'LN1', 'SSS Salary Loan', 'deduction', 'loan_amortization', 500, 0, 1)")
            ->execute([$tenantId]);
        $r = $this->runPayroll($ids);
        $this->assertTrue($r['success'] ?? false, 'loan run should succeed: ' . ($r['error'] ?? ''));
        $this->assertEqualsWithDelta(500.00, $this->deduct((int) $r['run_id'], $empId, 'SSS Salary Loan'), 0.01);
        $this->assertStringContainsString('loan', strtolower(implode(' ', $r['warnings'] ?? [])), 'must warn that balance tracking is not implemented');
    }

    public function testAnnualizationStillFailsLoud(): void
    {
        $r = $this->runPayroll($this->makeCase('HardAnnual', 30000, 22, ['tax_annualization' => 1]));
        $this->assertFalse($r['success'] ?? true, 'annualization is unimplemented and must fail the run');
        $this->assertStringContainsString('annualization', strtolower($r['error'] ?? ''));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /** Most recently created employee id (cases create exactly one each). */
    private function lastEmp(): int
    {
        return (int) self::$pdo->query("SELECT id FROM users WHERE email LIKE '%@hardening.test' ORDER BY id DESC LIMIT 1")->fetchColumn();
    }
}
