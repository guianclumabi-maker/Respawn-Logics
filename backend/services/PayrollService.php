<?php

class PayrollService
{
    private $pdo;
    
    private $sssBrackets = [];
    private $phicConfig = [];
    private $hdmfConfig = [];
    private $birBrackets = [];
    private $deMinimisConfig = [];
    private $statutoryParams = [];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    private $tenantSettings = [];
    private $payComponents = [];

    private function loadConfigs($payDate, $tenantId, $frequency) {
        $stmt = $this->pdo->prepare("SELECT * FROM sss_contribution_brackets WHERE effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?) ORDER BY range_from ASC");
        $stmt->execute([$payDate, $payDate]);
        $this->sssBrackets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("SELECT * FROM philhealth_config WHERE effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?) LIMIT 1");
        $stmt->execute([$payDate, $payDate]);
        $this->phicConfig = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("SELECT * FROM pagibig_config WHERE effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?) LIMIT 1");
        $stmt->execute([$payDate, $payDate]);
        $this->hdmfConfig = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare("SELECT * FROM bir_withholding_brackets WHERE effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?) ORDER BY pay_frequency, lower_limit DESC");
        $stmt->execute([$payDate, $payDate]);
        $birRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->birBrackets = [];
        foreach ($birRows as $row) {
            $this->birBrackets[$row['pay_frequency']][] = $row;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM de_minimis_ceilings WHERE effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)");
        $stmt->execute([$payDate, $payDate]);
        $dmRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->deMinimisConfig = [];
        foreach ($dmRows as $row) {
            $this->deMinimisConfig[$row['item_name']] = $row;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM statutory_parameters WHERE effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)");
        $stmt->execute([$payDate, $payDate]);
        $paramRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->statutoryParams = [];
        foreach ($paramRows as $row) {
            $this->statutoryParams[$row['param_key']] = floatval($row['param_value']);
        }

        if (empty($this->sssBrackets)) throw new Exception("No statutory SSS rate configured for $payDate");
        if (empty($this->phicConfig)) throw new Exception("No statutory PhilHealth rate configured for $payDate");
        if (empty($this->hdmfConfig)) throw new Exception("No statutory Pag-IBIG rate configured for $payDate");
        if (empty($this->birBrackets[$frequency])) throw new Exception("No statutory BIR withholding rate configured for $frequency on $payDate");
        if (empty($this->deMinimisConfig)) throw new Exception("No statutory De Minimis rates configured for $payDate");
        if (empty($this->statutoryParams['thirteenth_month_exemption_cap'])) throw new Exception("No statutory parameters configured for $payDate");

        $settings = [
            'proration_method' => 'split_even',
            'mwe_auto_exempt' => 1,
        ];
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM tenant_payroll_settings WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $settings = $row;
            }
        } catch (Exception $e) {
            // Table might not exist yet, fallback to defaults
        }
        $this->tenantSettings = $settings;

        $this->payComponents = [];
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM pay_components WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order ASC");
            $stmt->execute([$tenantId]);
            $this->payComponents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Table might not exist yet, fallback to empty array
        }
    }

    private function calculateSSS($baseSalary, $prorateFactor) {
        if (empty($this->sssBrackets)) return ['ee' => 0, 'er' => 0, 'ec' => 0, 'wisp_er' => 0];

        foreach ($this->sssBrackets as $b) {
            if ($baseSalary >= floatval($b['range_from']) && ($b['range_to'] === null || $baseSalary <= floatval($b['range_to']))) {
                return [
                    'ee' => (floatval($b['ee_amount']) + floatval($b['wisp_ee'])) * $prorateFactor,
                    'er' => (floatval($b['er_amount']) + floatval($b['wisp_er'])) * $prorateFactor,
                    'ec' => floatval($b['ec_amount']) * $prorateFactor,
                    'wisp_er' => floatval($b['wisp_er']) * $prorateFactor
                ];
            }
        }
        return ['ee' => 0, 'er' => 0, 'ec' => 0, 'wisp_er' => 0];
    }

    private function calculatePhilHealth($baseSalary, $prorateFactor) {
        if (empty($this->phicConfig)) return ['ee' => 0, 'er' => 0];

        $floor = floatval($this->phicConfig['floor_salary']);
        $ceiling = floatval($this->phicConfig['ceiling_salary']);
        $rate = floatval($this->phicConfig['rate_total']);

        $msc = min(max($baseSalary, $floor), $ceiling);
        $total = $msc * $rate;
        $half = $total / 2;

        return [
            'ee' => $half * $prorateFactor,
            'er' => $half * $prorateFactor
        ];
    }

    private function calculatePagIbig($baseSalary, $prorateFactor) {
        if (empty($this->hdmfConfig)) return ['ee' => 0, 'er' => 0];

        $ceiling = floatval($this->hdmfConfig['fund_salary_ceiling']);
        $msc = min($baseSalary, $ceiling);

        $eeRate = ($baseSalary <= floatval($this->hdmfConfig['low_threshold'])) 
            ? floatval($this->hdmfConfig['low_rate']) 
            : floatval($this->hdmfConfig['high_rate']);
        $erRate = floatval($this->hdmfConfig['er_rate']);

        return [
            'ee' => ($msc * $eeRate) * $prorateFactor,
            'er' => ($msc * $erRate) * $prorateFactor
        ];
    }

    private function calculateTax($taxableIncome, $frequency) {
        $brackets = $this->birBrackets[$frequency] ?? ($this->birBrackets['Monthly'] ?? []);
        foreach ($brackets as $b) {
            if ($taxableIncome >= floatval($b['lower_limit'])) {
                $excess = $taxableIncome - floatval($b['lower_limit']);
                return floatval($b['base_tax']) + ($excess * floatval($b['rate_on_excess']));
            }
        }
        return 0;
    }

    // ── Pay-basis strategy ────────────────────────────────────────────────────
    /**
     * Resolve the tenant's pay basis from tenant_payroll_settings.default_pay_basis.
     * The schema enum stores 'monthly'|'daily'|'hourly'; 'monthly' and 'monthly_fixed'
     * are BOTH normalized to 'monthly_fixed' so a seeded value can never crash a run.
     * Unknown values throw (fail-loud) rather than silently paying on the wrong basis.
     */
    private function resolvePayBasis(): string {
        $basis = $this->tenantSettings['default_pay_basis'] ?? 'monthly_fixed';
        if ($basis === null || $basis === '') return 'monthly_fixed';
        if ($basis === 'monthly') return 'monthly_fixed';
        if (!in_array($basis, ['monthly_fixed', 'daily', 'hourly'], true)) {
            throw new Exception("Unknown pay basis '{$basis}'. Set tenant default_pay_basis to monthly, daily, or hourly.");
        }
        return $basis;
    }

    /**
     * Derive daily/hourly rates for an employee under the tenant's pay basis.
     *  - monthly_fixed: base_salary is MONTHLY. daily = (base*12)/working_days_per_year,
     *    hourly = daily/hours_per_day. (Identical math to the original implementation.)
     *  - daily: base_salary is a DAILY rate. hourly = base/hours_per_day.
     *  - hourly: base_salary IS the hourly rate.
     * CPA VALIDATION NEEDED: the 313-day / 8-hour divisors are defaults from
     * statutory_parameters; employers using 365/261/26-day factors (policy/CBA) must
     * configure them. For monthly_fixed staff, "basic pay = worked hours × hourly" is a
     * timesheet-driven PROXY for the monthly salary — confirm it matches how a full
     * month is actually paid.
     */
    private function calculateHourlyRates(array $emp): array {
        $basis = $this->resolvePayBasis();
        $workingDays = floatval($this->statutoryParams['working_days_per_year'] ?? 313.00);
        $hoursPerDay = floatval($this->statutoryParams['hours_per_day'] ?? 8.00);
        if ($workingDays <= 0 || $hoursPerDay <= 0) {
            throw new Exception("Invalid statutory divisors (working_days_per_year={$workingDays}, hours_per_day={$hoursPerDay}). Fix statutory_parameters before running payroll.");
        }
        $base = floatval($emp['base_salary']);
        switch ($basis) {
            case 'monthly_fixed':
                $daily = ($base * 12) / $workingDays;
                return ['daily' => $daily, 'hourly' => $daily / $hoursPerDay, 'basis' => $basis];
            case 'daily':
                return ['daily' => $base, 'hourly' => $base / $hoursPerDay, 'basis' => $basis];
            case 'hourly':
                return ['daily' => $base * $hoursPerDay, 'hourly' => $base, 'basis' => $basis];
        }
        throw new Exception("Unhandled pay basis '{$basis}'."); // unreachable; defensive
    }

    // ── Premium-pay matrix ────────────────────────────────────────────────────
    /**
     * Pure premium-pay computation from approved timesheet hour buckets.
     * Faithful extraction of the original inline math — multipliers and results are
     * byte-identical for the same inputs; extracted so each line is auditable.
     *
     * CPA VALIDATION NEEDED (documented simplifications of the timesheet schema):
     *  1. rest_day_hours and special_day_hours share one multiplier (default 1.30).
     *     A rest day that IS ALSO a special day should be 1.50 — the schema cannot
     *     express the overlap, so it cannot be computed here.
     *  2. overtime_hours uses the ordinary-OT multiplier (1.25) even if the OT fell on
     *     a rest day/holiday (should compound, e.g. 1.30×1.30). Not representable.
     *  3. night_diff_hours earns a flat +10% of base hourly and does NOT compound with
     *     OT/holiday premiums for the same hour. Not representable.
     * These under-pay in edge cases rather than over-pay; fixing them requires
     * overlap columns in `timesheets` (e.g. rest_special_hours, ot_rest_hours).
     */
    private function calculatePremiumPay(array $hours, float $hourly): array {
        $p = $this->statutoryParams;
        return [
            'regular_pay'         => floatval($hours['reg'] ?? 0)  * $hourly,
            'overtime_pay'        => floatval($hours['ot'] ?? 0)   * $hourly * ($p['ordinary_ot_multiplier'] ?? 1.25),
            'rest_day_pay'        => floatval($hours['rest'] ?? 0) * $hourly * ($p['rest_day_or_special_multiplier'] ?? 1.30),
            'special_holiday_pay' => floatval($hours['spec'] ?? 0) * $hourly * ($p['rest_day_or_special_multiplier'] ?? 1.30),
            'regular_holiday_pay' => floatval($hours['hol'] ?? 0)  * $hourly * ($p['regular_holiday_multiplier'] ?? 2.00),
            'night_diff_pay'      => floatval($hours['nd'] ?? 0)   * $hourly * ($p['night_diff_multiplier'] ?? 0.10),
        ];
    }

    // ── Monthly pay mode ──────────────────────────────────────────────────────
    /**
     * How monthly_fixed employees are paid per cutoff:
     *  - 'fixed_salary' (DEFAULT — standard PH practice): basic = base_salary ×
     *    cutoff factor MINUS absence deductions (absent scheduled workday × daily
     *    rate). A 22-workday June pays the full monthly salary, not a pro-rated
     *    hours total. Under the 313-day divisor convention (365 − 52 rest days),
     *    the monthly salary already includes regular AND special holidays, so:
     *      · unworked holidays: no deduction (and no extra pay)
     *      · WORKED regular holiday pays the +100% EXCESS on top (200% total)
     *      · WORKED special day pays the +30% EXCESS on top (130% total)
     *      · OT/rest-day/night-diff are fully on top (not part of the salary)
     *  - 'hours_proxy' (legacy): basic = approved hours × hourly. Underpays short
     *    months; kept only for tenants that explicitly pay per approved hour.
     * CPA VALIDATION NEEDED: the excess-only holiday premiums above assume the
     * 313 divisor. A tenant using 261 (workdays only) must pay full 200%/130% and
     * deduct unworked special days — revisit if working_days_per_year != 313.
     */
    private function resolveMonthlyPayMode(): string {
        $mode = $this->tenantSettings['monthly_pay_mode'] ?? 'fixed_salary';
        if ($mode === null || $mode === '') return 'fixed_salary';
        if (!in_array($mode, ['fixed_salary', 'hours_proxy'], true)) {
            throw new Exception("Unknown monthly_pay_mode '{$mode}'. Use fixed_salary or hours_proxy.");
        }
        return $mode;
    }

    /**
     * Scheduled workdays in [start,end]: Mon–Fri, excluding tenant calendar holidays
     * (both types — under the 313 divisor they are salary-included, so an unworked
     * holiday is neither payable extra nor deductible as absence).
     * NOTE: assumes a Mon–Fri workweek (same default as timesheet auto-draft).
     */
    private function scheduledWorkdays(string $start, string $end, string $tenantId): array {
        $hStmt = $this->pdo->prepare("SELECT holiday_date FROM holiday_calendar WHERE tenant_id = ? AND holiday_date BETWEEN ? AND ?");
        $hStmt->execute([$tenantId, $start, $end]);
        $holidays = array_flip($hStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $days = [];
        for ($t = strtotime($start); $t <= strtotime($end); $t += 86400) {
            $d = date('Y-m-d', $t);
            if ((int)date('N', $t) >= 6) continue;
            if (isset($holidays[$d])) continue;
            $days[$d] = true;
        }
        return $days;
    }

    // ── Statutory contribution basis ──────────────────────────────────────────
    /**
     * Resolve the salary base used for SSS/PhilHealth/Pag-IBIG.
     *  - 'monthly_base' (default; original behavior): the employee's monthly base_salary.
     *  - 'actual_period_equivalent': actual period earnings scaled to a monthly
     *    equivalent (period pay ÷ statutoryMultiplier) — for daily/hourly/no-work-no-pay
     *    staff whose real compensable pay differs from a fixed monthly figure.
     * Unknown strategies throw. When a daily/hourly pay basis is combined with
     * 'monthly_base', a WARNING is attached to the run (visible, not silent).
     * CPA VALIDATION NEEDED: correct MSC basis for partial periods / daily-paid staff.
     */
    private function resolveStatutoryBasis(): string {
        $s = $this->tenantSettings['statutory_basis'] ?? 'monthly_base';
        if ($s === null || $s === '') return 'monthly_base';
        if (!in_array($s, ['monthly_base', 'actual_period_equivalent'], true)) {
            throw new Exception("Unknown statutory_basis '{$s}'. Use monthly_base or actual_period_equivalent.");
        }
        return $s;
    }

    private function statutorySalaryBase(array $emp, float $periodWorkPay, float $statutoryMultiplier): float {
        if ($this->resolveStatutoryBasis() === 'actual_period_equivalent') {
            if ($statutoryMultiplier <= 0) return 0.0; // cutoff carries no statutory deduction
            return $periodWorkPay / $statutoryMultiplier; // scale period pay to monthly equivalent
        }
        return floatval($emp['base_salary']);
    }

    private function getDeMinimisExemption($item_name, $amount, $frequency) {
        if (!isset($this->deMinimisConfig[$item_name])) {
            return ['exempt' => 0, 'excess' => $amount];
        }
        $config = $this->deMinimisConfig[$item_name];
        $ceiling = floatval($config['ceiling_amount']);
        $limit = 0;
        
        if ($config['frequency'] === 'Monthly') {
            $limit = ($frequency === 'Semi-Monthly') ? $ceiling / 2 : $ceiling;
        } else if ($config['frequency'] === 'Yearly') {
            $limit = ($frequency === 'Semi-Monthly') ? ($ceiling / 24) : ($ceiling / 12);
        } else if ($config['frequency'] === 'Semester') {
            $limit = ($frequency === 'Semi-Monthly') ? ($ceiling / 12) : ($ceiling / 6);
        } else if ($config['frequency'] === 'Days') {
            // Unused VL monetized - custom handling. Usually we pass the day rate * 10
            // Since we pass amount, if we just want to limit to 10 days, the user would need to provide the employee's daily rate
            // For now, treat as fully taxable or require specific logic for "Days".
            return ['exempt' => 0, 'excess' => $amount];
        }
        
        $exempt = min($amount, $limit);
        $excess = $amount - $exempt;
        return ['exempt' => $exempt, 'excess' => $excess];
    }

    /**
     * Remaining ₱90,000 exemption bucket (NIRC Sec. 32(B)(7)(e)) for 13th-month pay
     * and "other benefits" in the calendar year.
     *
     * HARDENED: de-minimis benefits WITHIN their own statutory ceilings are exempt
     * under a SEPARATE provision (RR 11-2018) and must NOT consume this ₱90k bucket —
     * so 'De Minimis (Exempt): %' rows are intentionally EXCLUDED below. De-minimis
     * EXCESS over its ceiling is already routed into "other benefits"
     * ($otherBenefitsThisRun in generateRun), which correctly consumes this bucket and
     * becomes taxable once the bucket is exhausted.
     * (Previous behavior also subtracted exempt de minimis here, over-consuming the
     * cap and over-withholding tax.)
     */
    private function getRemaining90kExemption($empId, $payDate, $tenantId) {
        $year = date('Y', strtotime($payDate));
        $stmt = $this->pdo->prepare("
            SELECT SUM(pe.amount)
            FROM payroll_earnings pe
            JOIN payroll_runs pr ON pe.payroll_run_id = pr.id
            WHERE pe.employee_id = ?
            AND pr.tenant_id = ?
            AND (pe.earning_type = 'Non-Taxable Other Benefits' OR pe.earning_type = '13th Month Pay (Non-Taxable)')
            AND YEAR(pr.pay_date) = ?
        ");
        $stmt->execute([$empId, $tenantId, $year]);
        $used = floatval($stmt->fetchColumn());
        $cap = $this->statutoryParams['thirteenth_month_exemption_cap'] ?? 90000;
        return max(0, $cap - $used);
    }

    /**
     * Sum the basic-pay 13th-month accrual across the calendar year's Regular runs for an employee.
     * Equals (total basic salary earned in the year) / 12 — the legally-correct 13th-month base
     * (PD 851), tenant-scoped, excluding the 13th-month run itself.
     */
    private function getThirteenthMonthAccrued($empId, $payDate, $tenantId) {
        $year = date('Y', strtotime($payDate));
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(pre.thirteenth_month_accrual), 0)
            FROM payroll_run_employees pre
            JOIN payroll_runs pr ON pre.payroll_run_id = pr.id
            WHERE pre.employee_id = ?
            AND pr.tenant_id = ?
            AND pr.run_type = 'Regular'
            AND YEAR(pr.pay_date) = ?
        ");
        $stmt->execute([$empId, $tenantId, $year]);
        return floatval($stmt->fetchColumn());
    }

    public function generateRun($tenantId, $scheduleId, $start, $end, $payDate, $createdById, $runType = 'Regular')
    {
        try {
            $this->pdo->beginTransaction();

            // Fetch schedule frequency (tenant-scoped — never run payroll against another
            // tenant's schedule, and never silently default the frequency, which would apply
            // the wrong statutory proration and BIR bracket).
            $stmt = $this->pdo->prepare("SELECT frequency FROM payroll_schedules WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$scheduleId, $tenantId]);
            $schedule = $stmt->fetch();
            if (!$schedule) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => 'Payroll schedule not found for this tenant.'];
            }
            $frequency = $schedule['frequency'];

            // Load global configs and tenant configs based on pay date
            $this->loadConfigs($payDate, $tenantId, $frequency);
            if (!empty($this->tenantSettings['tax_annualization'])) {
                throw new Exception('Tax annualization is enabled but not implemented in PayrollService. Disable it or implement annualized BIR withholding before running payroll.');
            }
            
            $prorateFactor = ($frequency === 'Semi-Monthly') ? 0.5 : 1.0;
            
            // Determine statutory multiplier based on proration method
            $isFirstCutoff = date('j', strtotime($end)) <= 15;
            $prorationMethod = $this->tenantSettings['proration_method'] ?? 'split_even';
            $statutoryMultiplier = 1.0;
            if ($frequency === 'Semi-Monthly') {
                if ($prorationMethod === 'split_even') {
                    $statutoryMultiplier = 0.5;
                } else if ($prorationMethod === 'full_first_cutoff') {
                    $statutoryMultiplier = $isFirstCutoff ? 1.0 : 0.0;
                } else if ($prorationMethod === 'full_second_cutoff') {
                    $statutoryMultiplier = !$isFirstCutoff ? 1.0 : 0.0;
                }
            }

            // 1. Create the Run Record
            $stmt = $this->pdo->prepare("INSERT INTO `payroll_runs` (`tenant_id`, `payroll_schedule_id`, `payroll_period_start`, `payroll_period_end`, `pay_date`, `status`, `created_by`, `run_type`) VALUES (?, ?, ?, ?, ?, 'Draft', ?, ?)");
            $stmt->execute([$tenantId, $scheduleId, $start, $end, $payDate, $createdById, $runType]);
            $runId = $this->pdo->lastInsertId();

            // 2. Fetch all eligible employees
            $empStmt = $this->pdo->prepare("SELECT `id`, `base_salary`, `employment_status`, `is_mwe` FROM `users` WHERE `tenant_id` = ? AND `payroll_schedule_id` = ? AND `employment_status` = 'Active'");
            $empStmt->execute([$tenantId, $scheduleId]);
            $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($employees)) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => 'No active employees found for this schedule.'];
            }

            $employeeIds = array_column($employees, 'id');
            $inQuery = implode(',', array_fill(0, count($employeeIds), '?'));

            // 3. Eager Load Expenses
            $expenseParams = array_merge([$tenantId], $employeeIds);
            $expStmt = $this->pdo->prepare("SELECT ec.`id`, ec.`employee_id`, ec.`amount`, c.`name` as category_name FROM `expense_claims` ec LEFT JOIN `expense_categories` c ON ec.category_id = c.id WHERE ec.`tenant_id` = ? AND ec.`status` = 'Finance Approved' AND ec.`employee_id` IN ($inQuery)");
            $expStmt->execute($expenseParams);
            $rawExpenses = $expStmt->fetchAll(PDO::FETCH_ASSOC);

            $expensesByEmployee = [];
            foreach ($rawExpenses as $exp) {
                $expensesByEmployee[$exp['employee_id']][] = $exp;
            }

            // 4. Eager Load Benefits
            $benefitParams = array_merge([$tenantId], $employeeIds);
            $benStmt = $this->pdo->prepare("
                SELECT eb.employee_id, eb.dependent_count, bp.name, bp.type, bp.employee_cost, bp.company_cost 
                FROM `employee_benefits` eb
                JOIN `benefit_plans` bp ON eb.plan_id = bp.id
                WHERE eb.tenant_id = ? AND eb.status = 'Enrolled' AND eb.employee_id IN ($inQuery)
            ");
            $benStmt->execute($benefitParams);
            $rawBenefits = $benStmt->fetchAll(PDO::FETCH_ASSOC);

            $benefitsByEmployee = [];
            foreach ($rawBenefits as $ben) {
                $benefitsByEmployee[$ben['employee_id']][] = $ben;
            }

            $reStmt = $this->pdo->prepare("INSERT INTO `payroll_run_employees` (`payroll_run_id`, `employee_id`, `gross_pay`, `total_deductions`, `net_pay`, `sss_er`, `sss_ec`, `wisp_er`, `phic_er`, `hdmf_er`, `thirteenth_month_accrual`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $earningStmt = $this->pdo->prepare("INSERT INTO `payroll_earnings` (`payroll_run_id`, `employee_id`, `earning_type`, `amount`) VALUES (?, ?, ?, ?)");
            $deductStmt = $this->pdo->prepare("INSERT INTO `payroll_deductions` (`payroll_run_id`, `employee_id`, `deduction_type`, `amount`) VALUES (?, ?, ?, ?)");
            $markExpStmt = $this->pdo->prepare("UPDATE `expense_claims` SET `status` = 'Reimbursed' WHERE `id` = ?");

            $warnings = [];

            // 5. Process Each Employee
            foreach ($employees as $emp) {
                $empId = $emp['id'];
                // Timesheet-driven gross pay, on the tenant's configured pay basis
                // (monthly_fixed default preserves the original math exactly; daily/hourly
                // supported; unknown basis throws — see calculateHourlyRates).
                $rates = $this->calculateHourlyRates($emp);
                $hourly = $rates['hourly'];

                $tsStmt = $this->pdo->prepare("
                    SELECT 
                        SUM(regular_hours) as reg, 
                        SUM(overtime_hours) as ot, 
                        SUM(rest_day_hours) as rest, 
                        SUM(special_day_hours) as spec, 
                        SUM(regular_holiday_hours) as hol, 
                        SUM(night_diff_hours) as nd 
                    FROM timesheets 
                    WHERE tenant_id = ? AND employee_id = ? 
                    AND timesheet_date >= ? AND timesheet_date <= ? 
                    AND status = 'Approved'
                ");
                $tsStmt->execute([$tenantId, $empId, $start, $end]);
                $tsData = $tsStmt->fetch(PDO::FETCH_ASSOC);

                $cutoffBase = 0;
                $grossReg = 0;
                $grossOt = 0;
                $grossRest = 0;
                $grossSpec = 0;
                $grossHol = 0;
                $grossNd = 0;

                if ($tsData && $tsData['reg'] !== null) {
                    $premium = $this->calculatePremiumPay($tsData, $hourly);
                    $grossOt   = $premium['overtime_pay'];
                    $grossRest = $premium['rest_day_pay'];
                    $grossSpec = $premium['special_holiday_pay'];
                    $grossHol  = $premium['regular_holiday_pay'];
                    $grossNd   = $premium['night_diff_pay'];

                    if ($rates['basis'] === 'monthly_fixed' && $this->resolveMonthlyPayMode() === 'fixed_salary') {
                        // FIXED-SALARY MODE (default; see resolveMonthlyPayMode docblock).
                        // Basic = fixed cutoff salary − absences. Holiday premiums become
                        // EXCESS-only because the 313-divisor salary already contains the
                        // first 100% of holidays.
                        $scheduled = $this->scheduledWorkdays($start, $end, $tenantId);
                        $pdStmt = $this->pdo->prepare("SELECT DISTINCT timesheet_date FROM timesheets
                            WHERE tenant_id = ? AND employee_id = ? AND timesheet_date >= ? AND timesheet_date <= ? AND status = 'Approved'");
                        $pdStmt->execute([$tenantId, $empId, $start, $end]);
                        $presentScheduled = 0;
                        foreach ($pdStmt->fetchAll(PDO::FETCH_COLUMN) as $pDate) {
                            if (isset($scheduled[$pDate])) $presentScheduled++;
                        }
                        $absentDays = max(0, count($scheduled) - $presentScheduled);
                        $fixedBasic = floatval($emp['base_salary']) * $prorateFactor;
                        $absenceDeduction = $absentDays * $rates['daily'];
                        $grossReg = max(0, $fixedBasic - $absenceDeduction);
                        if ($absentDays > 0) {
                            $warnings[] = "Employee #{$empId}: {$absentDays} absent scheduled workday(s) deducted at daily rate (" . number_format($absenceDeduction, 2) . ").";
                        }
                        // Excess-only holiday premiums (see docblock): worked regular
                        // holiday +100%, worked special day +30%, on top of the salary.
                        $grossHol  = floatval($tsData['hol'])  * $hourly * ((($this->statutoryParams['regular_holiday_multiplier'] ?? 2.00)) - 1.0);
                        $grossSpec = floatval($tsData['spec']) * $hourly * ((($this->statutoryParams['rest_day_or_special_multiplier'] ?? 1.30)) - 1.0);
                    } else {
                        // hours_proxy (legacy) or daily/hourly bases: pay per approved hour.
                        $grossReg = $premium['regular_pay'];
                    }

                    $cutoffBase = $grossReg + $grossOt + $grossRest + $grossSpec + $grossHol + $grossNd;
                } else if ($runType === 'Regular') {
                    throw new Exception("Employee #{$empId} has no approved timesheets for the payroll period. Approve timesheets before running payroll.");
                }
                $remaining90k = $this->getRemaining90kExemption($empId, $payDate, $tenantId);

                $empExpenses = $expensesByEmployee[$empId] ?? [];
                $empBenefits = $benefitsByEmployee[$empId] ?? [];

                $totalExpenses = 0;
                foreach ($empExpenses as $exp) {
                    $totalExpenses += floatval($exp['amount']);
                }
                
                // Statutory contributions use the configured basis strategy:
                // monthly_base (default, original behavior) or actual_period_equivalent.
                // Unknown strategy throws inside resolveStatutoryBasis().
                $statBase = $this->statutorySalaryBase($emp, $cutoffBase, $statutoryMultiplier);
                if ($rates['basis'] !== 'monthly_fixed' && $this->resolveStatutoryBasis() === 'monthly_base') {
                    // Not silent: daily/hourly staff on monthly_base MSC is usually wrong
                    // (no-work-no-pay ≠ fixed monthly salary). Surface it on every run.
                    $warnings[] = "Employee #{$empId}: pay basis '{$rates['basis']}' with statutory_basis 'monthly_base' — SSS/PhilHealth/Pag-IBIG use the fixed monthly base_salary, not actual period pay. Set statutory_basis='actual_period_equivalent' if this tenant pays no-work-no-pay.";
                }
                $sss = $this->calculateSSS($statBase, $statutoryMultiplier);
                $phic = $this->calculatePhilHealth($statBase, $statutoryMultiplier);
                $hdmf = $this->calculatePagIbig($statBase, $statutoryMultiplier);
                
                $totalHmoDeduction = 0;
                $totalAllowances = 0;
                $otherBenefitsThisRun = 0;
                
                $is13thMonth = ($runType === '13th Month');
                $thirteenthPayout = 0;

                if ($is13thMonth) {
                    $sss = ['ee' => 0, 'er' => 0, 'ec' => 0, 'wisp_er' => 0];
                    $phic = ['ee' => 0, 'er' => 0];
                    $hdmf = ['ee' => 0, 'er' => 0];
                    // 13th-month pay (PD 851) = total BASIC salary earned in the calendar year / 12.
                    // We sum the per-run basic accrual (grossReg/12) across the year's Regular runs,
                    // which equals (annual basic earned) / 12 — correct for mid-year hires, raises and
                    // unpaid absences, unlike paying one month's *current* base salary.
                    // NOTE: "basic salary" here excludes OT, holiday premium, night diff and allowances
                    // per the standard interpretation; company policy/CBA integration must be CPA-confirmed.
                    $thirteenthPayout = round($this->getThirteenthMonthAccrued($empId, $payDate, $tenantId), 2);
                    // A 13th-month run pays ONLY the 13th month — zero all work-pay components so
                    // no basic/OT/holiday/night-diff line items leak in (keeps the payslip reconciled).
                    $grossReg = $grossOt = $grossRest = $grossSpec = $grossHol = $grossNd = 0;
                    $cutoffBase = 0; // No regular basic salary this run
                }
                
                $customEarnings = 0;
                $customTaxableEarnings = 0;
                $customDeductions = 0;

                foreach ($this->payComponents as $comp) {
                    $amount = 0;
                    if ($comp['calc_type'] === 'fixed') {
                        $amount = floatval($comp['value']) * $prorateFactor;
                    } else if ($comp['calc_type'] === 'percent_of_base') {
                        $amount = (floatval($emp['base_salary']) * (floatval($comp['value']) / 100)) * $prorateFactor;
                    } else if ($comp['calc_type'] === 'loan_amortization') {
                        // A loan amortization IS a fixed per-period deduction; `value` is the
                        // amortization amount. LIMITATION (documented, not silent): remaining-balance
                        // tracking / auto-stop at zero is NOT implemented — payroll ops must
                        // deactivate the component when the loan is fully paid.
                        if ($comp['value'] === null || floatval($comp['value']) <= 0) {
                            throw new Exception("Pay component '{$comp['name']}' (loan_amortization) has no amortization amount set. Set its value or deactivate it.");
                        }
                        $amount = floatval($comp['value']) * $prorateFactor;
                        $warnings[] = "Component '{$comp['name']}': loan amortization deducted as a fixed amount; remaining-balance tracking is not implemented — deactivate the component once the loan is settled.";
                    } else {
                        // 'statutory', 'attendance_derived', 'formula' have no engine implementation.
                        // Previously they were SILENTLY skipped => wrong net pay. Fail loud instead.
                        throw new Exception("Pay component '{$comp['name']}' uses calc_type '{$comp['calc_type']}', which is not implemented in the payroll engine. Deactivate it or change it to 'fixed'/'percent_of_base'.");
                    }

                    if ($amount > 0) {
                        if ($comp['kind'] === 'earning') {
                            $customEarnings += $amount;
                            if (intval($comp['taxable']) === 1) {
                                $customTaxableEarnings += $amount;
                            }
                            $earningStmt->execute([$runId, $empId, $comp['name'], $amount]);
                        } else if ($comp['kind'] === 'deduction') {
                            $customDeductions += $amount;
                            $deductStmt->execute([$runId, $empId, $comp['name'], $amount]);
                        }
                    }
                }
                
                $basicLabel = ($rates['basis'] === 'monthly_fixed' && $this->resolveMonthlyPayMode() === 'fixed_salary')
                    ? 'Basic Pay (Monthly)' : 'Basic Pay (Hours)';
                if ($grossReg > 0) $earningStmt->execute([$runId, $empId, $basicLabel, round($grossReg, 2)]);
                if ($grossOt > 0) $earningStmt->execute([$runId, $empId, 'Overtime Pay', round($grossOt, 2)]);
                if ($grossRest > 0) $earningStmt->execute([$runId, $empId, 'Rest Day Pay', round($grossRest, 2)]);
                if ($grossSpec > 0) $earningStmt->execute([$runId, $empId, 'Special Holiday Pay', round($grossSpec, 2)]);
                if ($grossHol > 0) $earningStmt->execute([$runId, $empId, 'Regular Holiday Pay', round($grossHol, 2)]);
                if ($grossNd > 0) $earningStmt->execute([$runId, $empId, 'Night Differential Premium', round($grossNd, 2)]);

                foreach ($empBenefits as $ben) {
                    if ($ben['type'] === 'HMO') {
                        // HMO deductions happen every cutoff if semi-monthly
                        $totalHmoDeduction += (floatval($ben['employee_cost']) * intval($ben['dependent_count'])) * $prorateFactor;
                    } else if ($ben['type'] === 'De Minimis') {
                        $cutoffAllowance = floatval($ben['company_cost']) * $prorateFactor;
                        $res = $this->getDeMinimisExemption($ben['name'], $cutoffAllowance, $frequency);
                        
                        $totalAllowances += $cutoffAllowance;
                        if ($res['exempt'] > 0) {
                            $earningStmt->execute([$runId, $empId, 'De Minimis (Exempt): ' . $ben['name'], $res['exempt']]);
                        }
                        if ($res['excess'] > 0) {
                            $otherBenefitsThisRun += $res['excess'];
                        }
                    } else if ($ben['type'] === 'Perk') {
                        $cutoffAllowance = floatval($ben['company_cost']) * $prorateFactor;
                        $totalAllowances += $cutoffAllowance;
                        $otherBenefitsThisRun += $cutoffAllowance;
                    }
                }
                
                $exempt13thMonth = 0;
                $taxable13thMonth = 0;
                if ($thirteenthPayout > 0) {
                    $exempt13thMonth = min($thirteenthPayout, $remaining90k);
                    $taxable13thMonth = $thirteenthPayout - $exempt13thMonth;
                    $remaining90k -= $exempt13thMonth;

                    if ($exempt13thMonth > 0) {
                        $earningStmt->execute([$runId, $empId, '13th Month Pay (Non-Taxable)', round($exempt13thMonth, 2)]);
                    }
                    if ($taxable13thMonth > 0) {
                        $earningStmt->execute([$runId, $empId, '13th Month Pay (Taxable)', round($taxable13thMonth, 2)]);
                    }
                }

                $exemptOtherBenefits = min($otherBenefitsThisRun, $remaining90k);
                $taxableOtherBenefits = $otherBenefitsThisRun - $exemptOtherBenefits;

                if ($exemptOtherBenefits > 0) {
                    $earningStmt->execute([$runId, $empId, 'Non-Taxable Other Benefits', round($exemptOtherBenefits, 2)]);
                }
                if ($taxableOtherBenefits > 0) {
                    $earningStmt->execute([$runId, $empId, 'Taxable Other Benefits', round($taxableOtherBenefits, 2)]);
                }
                $taxableOtherBenefits += $taxable13thMonth;
                
                foreach ($empExpenses as $exp) {
                    $catName = $exp['category_name'] ?: 'Expense';
                    $earningStmt->execute([$runId, $empId, 'Reimbursement: ' . $catName, $exp['amount']]);
                    $markExpStmt->execute([$exp['id']]);
                }

                $gross = round($cutoffBase + $totalExpenses + $totalAllowances + $customEarnings + $thirteenthPayout, 2);
                
                $isMwe = (intval($emp['is_mwe']) === 1);
                
                if ($isMwe) {
                    $taxableIncome = ($taxableOtherBenefits + $customTaxableEarnings);
                } else {
                    $taxableIncome = ($cutoffBase + $taxableOtherBenefits + $customTaxableEarnings) - ($sss['ee'] + $phic['ee'] + $hdmf['ee']);
                }
                
                $tax = round($this->calculateTax($taxableIncome, $frequency), 2);
                
                // Accrue 13th-month on BASIC pay only (regular-hours pay), excluding OT, holiday
                // premium, night differential and allowances — per PD 851's "basic salary".
                // The 13th-month run itself does not accrue.
                $thirteenthAccrual = $is13thMonth ? 0.00 : round($grossReg / 12, 2);

                $totalDeductions = round($tax + $sss['ee'] + $phic['ee'] + $hdmf['ee'] + $totalHmoDeduction + $customDeductions, 2);
                $net = round($gross - $totalDeductions, 2);

                $reStmt->execute([$runId, $empId, $gross, $totalDeductions, $net, round($sss['er'], 2), round($sss['ec'], 2), round($sss['wisp_er'], 2), round($phic['er'], 2), round($hdmf['er'], 2), $thirteenthAccrual]);

                if ($tax > 0) $deductStmt->execute([$runId, $empId, 'Withholding Tax', $tax]);
                if ($sss['ee'] > 0) $deductStmt->execute([$runId, $empId, 'SSS Contribution', round($sss['ee'], 2)]);
                if ($phic['ee'] > 0) $deductStmt->execute([$runId, $empId, 'PhilHealth Contribution', round($phic['ee'], 2)]);
                if ($hdmf['ee'] > 0) $deductStmt->execute([$runId, $empId, 'Pag-IBIG Contribution', round($hdmf['ee'], 2)]);
                
                foreach ($empBenefits as $ben) {
                    if ($ben['type'] === 'HMO' && intval($ben['dependent_count']) > 0) {
                        $hmoCost = (floatval($ben['employee_cost']) * intval($ben['dependent_count'])) * $prorateFactor;
                        $deductStmt->execute([$runId, $empId, 'HMO Dependents: ' . $ben['name'], $hmoCost]);
                    }
                }
            }

            $this->pdo->commit();
            return ['success' => true, 'run_id' => $runId, 'warnings' => $warnings];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
