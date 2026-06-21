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

    private function getRemaining90kExemption($empId, $payDate) {
        $year = date('Y', strtotime($payDate));
        $stmt = $this->pdo->prepare("
            SELECT SUM(pe.amount) 
            FROM payroll_earnings pe
            JOIN payroll_runs pr ON pe.payroll_run_id = pr.id
            WHERE pe.employee_id = ? 
            AND pe.earning_type = 'Non-Taxable Other Benefits'
            AND YEAR(pr.pay_date) = ?
        ");
        $stmt->execute([$empId, $year]);
        $used = floatval($stmt->fetchColumn());
        $cap = $this->statutoryParams['thirteenth_month_exemption_cap'] ?? 90000;
        return max(0, $cap - $used);
    }

    public function generateRun($tenantId, $scheduleId, $start, $end, $payDate, $createdById)
    {
        try {
            $this->pdo->beginTransaction();

            // Fetch schedule frequency
            $stmt = $this->pdo->prepare("SELECT frequency FROM payroll_schedules WHERE id = ?");
            $stmt->execute([$scheduleId]);
            $schedule = $stmt->fetch();
            $frequency = $schedule ? $schedule['frequency'] : 'Monthly';

            // Load global configs and tenant configs based on pay date
            $this->loadConfigs($payDate, $tenantId, $frequency);
            
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
            $stmt = $this->pdo->prepare("INSERT INTO `payroll_runs` (`tenant_id`, `payroll_schedule_id`, `payroll_period_start`, `payroll_period_end`, `pay_date`, `status`, `created_by`) VALUES (?, ?, ?, ?, ?, 'Draft', ?)");
            $stmt->execute([$tenantId, $scheduleId, $start, $end, $payDate, $createdById]);
            $runId = $this->pdo->lastInsertId();

            // 2. Fetch all eligible employees
            $empStmt = $this->pdo->prepare("SELECT `id`, `base_salary`, `employment_status` FROM `users` WHERE `tenant_id` = ? AND `payroll_schedule_id` = ? AND `employment_status` = 'Active'");
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
                // Multiply base by prorate factor if schedule is semi-monthly, because `base_salary` in DB is Monthly
                $cutoffBase = floatval($emp['base_salary']) * $prorateFactor;
                
                $warnings[] = "Employee #{$empId} has unchecked timesheets. Assumed perfect attendance for now.";
                
                $remaining90k = $this->getRemaining90kExemption($empId, $payDate);

                $empExpenses = $expensesByEmployee[$empId] ?? [];
                $empBenefits = $benefitsByEmployee[$empId] ?? [];

                $totalExpenses = 0;
                foreach ($empExpenses as $exp) {
                    $totalExpenses += floatval($exp['amount']);
                }
                
                $sss = $this->calculateSSS(floatval($emp['base_salary']), $statutoryMultiplier);
                $phic = $this->calculatePhilHealth(floatval($emp['base_salary']), $statutoryMultiplier);
                $hdmf = $this->calculatePagIbig(floatval($emp['base_salary']), $statutoryMultiplier);
                
                $totalHmoDeduction = 0;
                $totalAllowances = 0;
                $otherBenefitsThisRun = 0;
                
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
                
                $earningStmt->execute([$runId, $empId, 'Basic Salary', $cutoffBase]);

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
                
                $exemptOtherBenefits = min($otherBenefitsThisRun, $remaining90k);
                $taxableOtherBenefits = $otherBenefitsThisRun - $exemptOtherBenefits;

                if ($exemptOtherBenefits > 0) {
                    $earningStmt->execute([$runId, $empId, 'Non-Taxable Other Benefits', $exemptOtherBenefits]);
                }
                if ($taxableOtherBenefits > 0) {
                    $earningStmt->execute([$runId, $empId, 'Taxable Other Benefits', $taxableOtherBenefits]);
                }
                
                foreach ($empExpenses as $exp) {
                    $catName = $exp['category_name'] ?: 'Expense';
                    $earningStmt->execute([$runId, $empId, 'Reimbursement: ' . $catName, $exp['amount']]);
                    $markExpStmt->execute([$exp['id']]);
                }

                $gross = $cutoffBase + $totalExpenses + $totalAllowances + $customEarnings;
                
                // Taxable income is Cutoff Base + Taxable Allowances - Cutoff Statutory
                $taxableIncome = ($cutoffBase + $taxableOtherBenefits + $customTaxableEarnings) - ($sss['ee'] + $phic['ee'] + $hdmf['ee']);
                $tax = $this->calculateTax($taxableIncome, $frequency);
                
                $isMwe = isset($emp['is_mwe']) ? (bool)$emp['is_mwe'] : (floatval($emp['base_salary']) <= 15000);
                if (intval($this->tenantSettings['mwe_auto_exempt'] ?? 1) === 1 && $isMwe) {
                    $tax = 0;
                }
                
                $thirteenthAccrual = $cutoffBase / 12;

                $totalDeductions = $tax + $sss['ee'] + $phic['ee'] + $hdmf['ee'] + $totalHmoDeduction + $customDeductions;
                $net = $gross - $totalDeductions;

                $reStmt->execute([$runId, $empId, $gross, $totalDeductions, $net, $sss['er'], $sss['ec'], $sss['wisp_er'], $phic['er'], $hdmf['er'], $thirteenthAccrual]);

                if ($tax > 0) $deductStmt->execute([$runId, $empId, 'Withholding Tax', $tax]);
                if ($sss['ee'] > 0) $deductStmt->execute([$runId, $empId, 'SSS Contribution', $sss['ee']]);
                if ($phic['ee'] > 0) $deductStmt->execute([$runId, $empId, 'PhilHealth Contribution', $phic['ee']]);
                if ($hdmf['ee'] > 0) $deductStmt->execute([$runId, $empId, 'Pag-IBIG Contribution', $hdmf['ee']]);
                
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
