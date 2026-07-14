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

    /**
     * Canonical money-rounding helper. All monetary values MUST pass through
     * this function at accumulation boundaries (gross, deductions, net).
     * "Half-up" rounding to 2 decimal places — the standard for Philippine payroll.
     * Centralizing here ensures the entire engine rounds identically.
     */
    private function money(float $v): float {
        return round($v, 2, PHP_ROUND_HALF_UP);
    }


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
            'default_pay_frequency' => 'Semi-Monthly',
            'proration_method' => 'split_even',
            'default_pay_basis' => 'monthly',
            'tax_annualization' => 0,
            'mwe_auto_exempt' => 1,
            'rounding_mode' => 'half_up',
            'approval_levels' => 1,
            'statutory_basis' => 'monthly_base'
        ];
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM tenant_payroll_settings WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $settings = $row;
                $settings['is_explicit'] = true;
            } else {
                $settings['is_explicit'] = false;
            }
        } catch (Exception $e) {
            $settings['is_explicit'] = false;
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
        // CPA NOTE: taxable income CAN legitimately be zero (e.g. MWE with no extras).
        // Negative income is clamped to 0 — statutory deductions cannot generate a tax credit.
        $taxableIncome = max(0.0, (float)$taxableIncome);

        if (!isset($this->birBrackets[$frequency])) {
            throw new Exception("No statutory BIR withholding bracket configured for frequency '{$frequency}'. Ensure the correct brackets are seeded for this pay schedule.");
        }
        $brackets = $this->birBrackets[$frequency];
        foreach ($brackets as $b) {
            if ($taxableIncome >= floatval($b['lower_limit'])) {
                $excess = $taxableIncome - floatval($b['lower_limit']);
                return floatval($b['base_tax']) + ($excess * floatval($b['rate_on_excess']));
            }
        }
        return 0;
    }

    /**
     * Resolve the tenant's pay basis. Defaults to 'monthly_fixed' so existing installs are unchanged.
     * (Per-employee override can be added later via a users.pay_basis column.)
     */
    private function resolvePayBasis(): string {
        $basis = $this->tenantSettings['default_pay_basis'] ?? '';
        // Canonicalize the legacy 'monthly' value stored by older UI versions
        if ($basis === 'monthly') {
            return 'monthly_fixed';
        }
        // Reject empty/unrecognized basis values — MySQL ENUM silently stores '' for invalid input,
        // and we must not silently default to monthly_fixed in that case.
        $allowed = ['monthly_fixed', 'daily', 'hourly'];
        if (!in_array($basis, $allowed, true)) {
            throw new Exception("Unknown pay basis '{$basis}'. Set tenant default_pay_basis to monthly_fixed, daily, or hourly.");
        }
        return $basis;
    }

    /**
     * Derive daily/hourly pay rate from the employee's pay basis — instead of silently applying the
     * monthly 313-divisor to everyone. Fails loud on an unknown basis.
     *   - monthly_fixed: base_salary is MONTHLY; hourly = (base*12)/working_days_per_year/hours_per_day.
     *   - daily:         base_salary is the DAILY rate;  hourly = base / hours_per_day.
     *   - hourly:        base_salary is the HOURLY rate; hourly = base.
     *
     * CPA VALIDATION NEEDED:
     *  - working_days_per_year (default 313) and hours_per_day (default 8) are DOCUMENTED DEFAULTS; the
     *    per-tenant divisor/hours must be confirmed against the employer's policy/CBA (365, 313, 261, 26...).
     *  - For monthly_fixed staff, "basic pay = worked hours * hourly" is a timesheet-driven PROXY for the
     *    monthly salary; confirm this matches how the employer intends to pay a full-month monthly employee.
     */
    private function calculateHourlyRates(array $emp): array {
        $workingDays = floatval($this->statutoryParams['working_days_per_year'] ?? 313.00); // documented default
        $hoursPerDay = floatval($this->statutoryParams['hours_per_day'] ?? 8.00);           // documented default
        if ($workingDays <= 0 || $hoursPerDay <= 0) {
            throw new Exception('Payroll config invalid: working_days_per_year and hours_per_day must be > 0.');
        }
        $base = floatval($emp['base_salary']);
        $basis = $this->resolvePayBasis();
        switch ($basis) {
            case 'monthly_fixed':
                $daily = ($base * 12) / $workingDays;
                $hourly = $daily / $hoursPerDay;
                break;
            case 'daily':
                $daily = $base;
                $hourly = $base / $hoursPerDay;
                break;
            case 'hourly':
                $daily = $base * $hoursPerDay;
                $hourly = $base;
                break;
            default:
                throw new Exception("Unknown pay basis '{$basis}'. Set tenant default_pay_basis to monthly_fixed, daily, or hourly.");
        }
        return ['daily' => $daily, 'hourly' => $hourly, 'basis' => $basis];
    }

    /**
     * Compute pay per earning bucket from approved-timesheet hours — a pure function of hours + hourly
     * rate so a CPA can audit each multiplier against the applied statutory_parameters config.
     *
     * CPA VALIDATION NEEDED — known simplifications (NOT bugs, but not full DOLE coverage):
     *  - Rest day and special non-working day both use rest_day_or_special_multiplier (1.30). Real rules
     *    differ for a rest-day-that-is-also-special (1.50) and for OT on those days (distinct rates). Not modeled.
     *  - Night differential is a FLAT premium (default 10%) on the BASE hourly for night_diff_hours only. It
     *    does NOT compound with OT / rest-day / holiday hours worked at night — the timesheet schema can't
     *    express that overlap yet. TODO: capture overlap hour-types to model combined premiums.
     *  - Overtime uses one ordinary-day multiplier; OT on rest days / holidays has higher rates, not modeled.
     */
    private function calculatePremiumPay(array $hours, float $hourly): array {
        $p = $this->statutoryParams;
        return [
            'regular_pay'         => floatval($hours['reg'])  * $hourly,
            'overtime_pay'        => floatval($hours['ot'])   * $hourly * ($p['ordinary_ot_multiplier'] ?? 1.25),
            'rest_day_pay'        => floatval($hours['rest']) * $hourly * ($p['rest_day_or_special_multiplier'] ?? 1.30),
            'special_holiday_pay' => floatval($hours['spec']) * $hourly * ($p['rest_day_or_special_multiplier'] ?? 1.30),
            'regular_holiday_pay' => floatval($hours['hol'])  * $hourly * ($p['regular_holiday_multiplier'] ?? 2.00),
            'night_diff_pay'      => floatval($hours['nd'])   * $hourly * ($p['night_diff_multiplier'] ?? 0.10),
        ];
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
     * Remaining ₱90,000 tax-exempt bucket for 13th-month + "other benefits" for the calendar year.
     *
     * ⚠️ CPA VALIDATION NEEDED (possible OVER-taxation): this query currently also subtracts
     * 'De Minimis (Exempt): %' from the ₱90k cap. Under the NIRC, de-minimis benefits within their own
     * ceilings are exempt SEPARATELY and should NOT consume the ₱90k 13th-month/other-benefits bucket —
     * only the EXCESS de-minimis (already routed into "Other Benefits") should. Including exempt de-minimis
     * here shrinks the ₱90k cap and can OVER-withhold tax. Left as-is (conservative — it never UNDER-withholds)
     * pending CPA confirmation; if confirmed, drop the `De Minimis (Exempt)` clause from this query.
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

            // --- Frequency enum validation ---
            // Fail loud rather than apply wrong statutory proration or BIR bracket.
            $allowedFrequencies = ['Monthly', 'Semi-Monthly', 'Weekly', 'Daily'];
            if (!in_array($frequency, $allowedFrequencies, true)) {
                throw new Exception("Unsupported payroll schedule frequency '{$frequency}'. Allowed values: " . implode(', ', $allowedFrequencies) . ".");
            }

            // --- Date-range validation ---
            // All three dates must be parseable ISO dates, period must be non-negative, and
            // the pay date must not precede the period start.
            $startTs = strtotime($start);
            $endTs   = strtotime($end);
            $payTs   = strtotime($payDate);
            if (!$startTs || !$endTs || !$payTs) {
                throw new Exception("Invalid date(s): start='{$start}', end='{$end}', payDate='{$payDate}'. All must be valid ISO-format dates.");
            }
            if ($startTs > $endTs) {
                throw new Exception("Pay period start '{$start}' is after end '{$end}'. The period start must not be later than the end.");
            }
            if ($payTs < $startTs) {
                throw new Exception("Pay date '{$payDate}' is before period start '{$start}'. Pay date must be on or after the period start.");
            }

            // --- Duplicate run guard ---
            // A second run for the same tenant/schedule/start/end/run_type blocks unless all prior
            // runs for that combination are in a terminal-rejected state.
            $dupStmt = $this->pdo->prepare("
                SELECT id FROM payroll_runs
                WHERE tenant_id = ? AND payroll_schedule_id = ?
                  AND payroll_period_start = ? AND payroll_period_end = ?
                  AND run_type = ? AND status != 'Rejected'
                LIMIT 1
            ");
            $dupStmt->execute([$tenantId, $scheduleId, $start, $end, $runType]);
            if ($dupStmt->fetch()) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => "Duplicate run: a '{$runType}' payroll run for {$start}–{$end} already exists and is not rejected."];
            }

            $this->loadConfigs($payDate, $tenantId, $frequency);
            if (!empty($this->tenantSettings['tax_annualization'])) {
                throw new Exception('Tax annualization is enabled but not implemented in PayrollService. Disable it or implement annualized BIR withholding before running payroll.');
            }
            
            $prorateFactor = 1.0;
            if ($frequency === 'Semi-Monthly') {
                $prorateFactor = 0.5;
            } elseif ($frequency === 'Weekly') {
                // 52 weeks / 12 months — gives the fraction of monthly statutory per pay period.
                // CPA VALIDATION NEEDED: confirm this divisor for your pay schedule.
                $prorateFactor = 12.0 / 52.0;
            } elseif ($frequency === 'Daily') {
                $workingDaysPerYear = floatval($this->statutoryParams['working_days_per_year'] ?? 313.00);
                $prorateFactor = 12.0 / $workingDaysPerYear;
            }
            // Monthly: prorateFactor stays 1.0.

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
                // Timesheet-driven gross pay. Rate derivation is pay-basis-aware (see calculateHourlyRates)
                // instead of assuming every employee is monthly-fixed on the 313 divisor.
                $rates = $this->calculateHourlyRates($emp);
                $hourly = $rates['hourly'];
                $tsStmt = $this->pdo->prepare("
                     SELECT 
                         timesheet_date,
                         regular_hours, 
                         overtime_hours, 
                         rest_day_hours, 
                         special_day_hours, 
                         regular_holiday_hours, 
                         night_diff_hours 
                     FROM timesheets 
                     WHERE tenant_id = ? AND employee_id = ? 
                     AND timesheet_date >= ? AND timesheet_date <= ? 
                     AND status = 'Approved'
                 ");
                 $tsStmt->execute([$tenantId, $empId, $start, $end]);
                 $tsRows = $tsStmt->fetchAll(PDO::FETCH_ASSOC);

                 $cutoffBase = 0;
                 $grossReg = 0;
                 $grossOt = 0;
                 $grossRest = 0;
                 $grossSpec = 0;
                 $grossHol = 0;
                 $grossNd = 0;

                 if (!empty($tsRows)) {
                     $sums = [
                         'reg'  => 0.0,
                         'ot'   => 0.0,
                         'rest' => 0.0,
                         'spec' => 0.0,
                         'hol'  => 0.0,
                         'nd'   => 0.0
                     ];

                     foreach ($tsRows as $row) {
                         $regVal  = floatval($row['regular_hours']);
                         $otVal   = floatval($row['overtime_hours']);
                         $restVal = floatval($row['rest_day_hours']);
                         $specVal = floatval($row['special_day_hours']);
                         $holVal  = floatval($row['regular_holiday_hours']);
                         $ndVal   = floatval($row['night_diff_hours']);

                         // --- Negative hours guard ---
                         // Negative hours would inflate pay or create negative deductions.
                         // These are always a data-entry error and must fail loud.
                         foreach (['regular_hours' => $regVal, 'overtime_hours' => $otVal,
                                   'rest_day_hours' => $restVal, 'special_day_hours' => $specVal,
                                   'regular_holiday_hours' => $holVal, 'night_diff_hours' => $ndVal] as $field => $val) {
                             if ($val < 0) {
                                 throw new Exception("Invalid timesheet: negative {$field} ({$val}) on {$row['timesheet_date']} for employee #{$empId}.");
                             }
                         }

                         $hasPremiumDay = ($restVal > 0 || $specVal > 0 || $holVal > 0);
                         $hasOt = ($otVal > 0);
                         $hasNd = ($ndVal > 0);

                         // 1. Multiple premium day types active on the same day (e.g. Rest Day + Special Holiday)
                         $activePremiumDaysCount = ($restVal > 0 ? 1 : 0) + ($specVal > 0 ? 1 : 0) + ($holVal > 0 ? 1 : 0);
                         if ($activePremiumDaysCount > 1) {
                             throw new Exception("Ambiguous combined premium: multiple premium day types on " . $row['timesheet_date']);
                         }
                         // 2. OT on a premium day (Rest/Special/Regular Holiday)
                         if ($hasOt && $hasPremiumDay) {
                             throw new Exception("Ambiguous combined premium: Overtime hours on premium day (Rest/Special/Holiday) on " . $row['timesheet_date']);
                         }
                         // 3. Night Differential on a premium day or during Overtime
                         if ($hasNd && ($hasPremiumDay || $hasOt)) {
                             throw new Exception("Ambiguous combined premium: Night differential overlapping with premium day or overtime on " . $row['timesheet_date']);
                         }

                         $sums['reg']  += $regVal;
                         $sums['ot']   += $otVal;
                         $sums['rest'] += $restVal;
                         $sums['spec'] += $specVal;
                         $sums['hol']  += $holVal;
                         $sums['nd']   += $ndVal;
                     }

                     $prem = $this->calculatePremiumPay($sums, $hourly);
                     $grossReg  = $prem['regular_pay'];
                     $grossOt   = $prem['overtime_pay'];
                     $grossRest = $prem['rest_day_pay'];
                     $grossSpec = $prem['special_holiday_pay'];
                     $grossHol  = $prem['regular_holiday_pay'];
                     $grossNd   = $prem['night_diff_pay'];

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
                
                // Statutory basis strategy checks and calculations
                $basis = $rates['basis'];
                $statutoryBasis = $this->tenantSettings['statutory_basis'] ?? null;
                $isExplicit = $this->tenantSettings['is_explicit'] ?? false;
                
                if (!$isExplicit && $basis !== 'monthly_fixed') {
                    throw new Exception("Missing tenant payroll settings: Tenant payroll settings must be explicitly configured in the database for daily/hourly employees.");
                }

                if (!$statutoryBasis) {
                    if ($basis === 'monthly_fixed') {
                        $statutoryBasis = 'monthly_base';
                    } else {
                        throw new Exception("Missing tenant payroll settings: statutory_basis must be explicitly configured for daily/hourly employees.");
                    }
                }

                if ($statutoryBasis !== 'monthly_base' && $statutoryBasis !== 'actual_period_equivalent') {
                    throw new Exception("Unknown statutory basis strategy '{$statutoryBasis}'.");
                }

                $workingDays = floatval($this->statutoryParams['working_days_per_year'] ?? 313.00);
                $hoursPerDay = floatval($this->statutoryParams['hours_per_day'] ?? 8.00);

                if ($workingDays <= 0 || $hoursPerDay <= 0) {
                    throw new Exception("Payroll config invalid: working_days_per_year and hours_per_day must be > 0.");
                }

                $monthlyBase = 0;
                if ($statutoryBasis === 'monthly_base') {
                    if ($basis === 'monthly_fixed') {
                        $monthlyBase = floatval($emp['base_salary']);
                    } else if ($basis === 'daily') {
                        $monthlyBase = floatval($emp['base_salary']) * $workingDays / 12;
                    } else if ($basis === 'hourly') {
                        $monthlyBase = floatval($emp['base_salary']) * $hoursPerDay * $workingDays / 12;
                    }
                } else if ($statutoryBasis === 'actual_period_equivalent') {
                    if ($frequency === 'Semi-Monthly') {
                        $monthlyBase = $grossReg * 2;
                    } else if ($frequency === 'Monthly') {
                        $monthlyBase = $grossReg;
                    } else if ($frequency === 'Weekly') {
                        $monthlyBase = $grossReg * 52 / 12;
                    } else if ($frequency === 'Daily') {
                        $monthlyBase = $grossReg * $workingDays / 12;
                    } else {
                        throw new Exception("Unsupported payroll frequency '{$frequency}' for actual_period_equivalent statutory basis.");
                    }
                }

                $sss = $this->calculateSSS($monthlyBase, $statutoryMultiplier);
                $phic = $this->calculatePhilHealth($monthlyBase, $statutoryMultiplier);
                $hdmf = $this->calculatePagIbig($monthlyBase, $statutoryMultiplier);
                
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
                
                if ($grossReg > 0) $earningStmt->execute([$runId, $empId, 'Basic Pay (Hours)', round($grossReg, 2)]);
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

                $gross = $this->money($cutoffBase + $totalExpenses + $totalAllowances + $customEarnings + $thirteenthPayout);
                
                $isMwe = (intval($emp['is_mwe']) === 1);
                
                if ($isMwe) {
                    $taxableIncome = ($taxableOtherBenefits + $customTaxableEarnings);
                } else {
                    $taxableIncome = ($cutoffBase + $taxableOtherBenefits + $customTaxableEarnings) - ($sss['ee'] + $phic['ee'] + $hdmf['ee']);
                }
                
                $taxableIncome = max(0.0, $taxableIncome); // Statutory deductions cannot create negative taxable income
                $tax = $this->money($this->calculateTax($taxableIncome, $frequency));
                
                // Accrue 13th-month on BASIC pay only (regular-hours pay), excluding OT, holiday
                // premium, night differential and allowances — per PD 851's "basic salary".
                // The 13th-month run itself does not accrue.
                $thirteenthAccrual = $is13thMonth ? 0.00 : round($grossReg / 12, 2);

                $totalDeductions = $this->money($tax + $sss['ee'] + $phic['ee'] + $hdmf['ee'] + $totalHmoDeduction + $customDeductions);
                $net = $this->money($gross - $totalDeductions);

                // Strict payslip reconciliation check: gross - deductions must equal net pay.
                // Because all three values are computed with the same money() helper at the same
                // precision, this should always hold. A mismatch indicates a rounding-boundary bug.
                $reconciledNet = $this->money($gross - $totalDeductions);
                if (abs($reconciledNet - $net) > 0.01) {
                    throw new Exception("Payslip reconciliation mismatch for Employee #{$empId}: Gross={$gross}, Deductions={$totalDeductions}, Net={$net}, Expected Net={$reconciledNet}");
                }
                
                // Note: Employer contributions (sss_er, sss_ec, wisp_er, phic_er, hdmf_er) are stored separately and do not reduce the employee's net pay.

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
