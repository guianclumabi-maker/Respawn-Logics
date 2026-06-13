<?php

class PayrollService
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calculates the SSS statutory deduction based on base salary.
     */
    private function calculateSSS($baseSalary)
    {
        $sss = $baseSalary * 0.045;
        return $sss > 1350 ? 1350 : $sss;
    }

    /**
     * Calculates the PhilHealth statutory deduction.
     */
    private function calculatePhilHealth($baseSalary)
    {
        return $baseSalary * 0.025;
    }

    /**
     * Calculates the Pag-IBIG statutory deduction.
     */
    private function calculatePagIbig()
    {
        return 100.00;
    }

    /**
     * Calculates Withholding Tax based on taxable income.
     */
    private function calculateTax($taxableIncome)
    {
        return $taxableIncome > 0 ? $taxableIncome * 0.10 : 0;
    }

    /**
     * Generates a new payroll run.
     */
    public function generateRun($tenantId, $scheduleId, $start, $end, $payDate, $createdById)
    {
        try {
            $this->pdo->beginTransaction();

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

            // Extract IDs for Eager Loading
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

            // Prepare Insert Statements to avoid recompiling in the loop
            $reStmt = $this->pdo->prepare("INSERT INTO `payroll_run_employees` (`payroll_run_id`, `employee_id`, `gross_pay`, `total_deductions`, `net_pay`) VALUES (?, ?, ?, ?, ?)");
            $earningStmt = $this->pdo->prepare("INSERT INTO `payroll_earnings` (`payroll_run_id`, `employee_id`, `earning_type`, `amount`) VALUES (?, ?, ?, ?)");
            $deductStmt = $this->pdo->prepare("INSERT INTO `payroll_deductions` (`payroll_run_id`, `employee_id`, `deduction_type`, `amount`) VALUES (?, ?, ?, ?)");
            $markExpStmt = $this->pdo->prepare("UPDATE `expense_claims` SET `status` = 'Reimbursed' WHERE `id` = ?");

            $warnings = [];

            // 5. Process Each Employee (Now in-memory, O(1) DB calls per loop instead of O(N))
            foreach ($employees as $emp) {
                $empId = $emp['id'];
                $base = floatval($emp['base_salary']);
                
                $warnings[] = "Employee #{$empId} has unchecked timesheets. Assumed perfect attendance for now.";
                
                $empExpenses = $expensesByEmployee[$empId] ?? [];
                $empBenefits = $benefitsByEmployee[$empId] ?? [];

                $totalExpenses = 0;
                foreach ($empExpenses as $exp) {
                    $totalExpenses += floatval($exp['amount']);
                }
                
                $sssDeduction = $this->calculateSSS($base);
                $phicDeduction = $this->calculatePhilHealth($base);
                $hdmfDeduction = $this->calculatePagIbig();
                
                $totalHmoDeduction = 0;
                $totalAllowances = 0;
                
                foreach ($empBenefits as $ben) {
                    if ($ben['type'] === 'HMO') {
                        $totalHmoDeduction += (floatval($ben['employee_cost']) * intval($ben['dependent_count']));
                    } else if ($ben['type'] === 'De Minimis' || $ben['type'] === 'Perk') {
                        $totalAllowances += floatval($ben['company_cost']);
                    }
                }
                
                $gross = $base + $totalExpenses + $totalAllowances;
                $taxableIncome = $base - ($sssDeduction + $phicDeduction + $hdmfDeduction);
                $tax = $this->calculateTax($taxableIncome);
                
                $totalDeductions = $tax + $sssDeduction + $phicDeduction + $hdmfDeduction + $totalHmoDeduction;
                $net = $gross - $totalDeductions;

                $reStmt->execute([$runId, $empId, $gross, $totalDeductions, $net]);
                $earningStmt->execute([$runId, $empId, 'Basic Salary', $base]);

                foreach ($empBenefits as $ben) {
                    if ($ben['type'] === 'De Minimis' || $ben['type'] === 'Perk') {
                        $earningStmt->execute([$runId, $empId, 'Allowance: ' . $ben['name'], $ben['company_cost']]);
                    }
                }

                foreach ($empExpenses as $exp) {
                    $catName = $exp['category_name'] ?: 'Expense';
                    $earningStmt->execute([$runId, $empId, 'Reimbursement: ' . $catName, $exp['amount']]);
                    $markExpStmt->execute([$exp['id']]);
                }

                $deductStmt->execute([$runId, $empId, 'Withholding Tax', $tax]);
                $deductStmt->execute([$runId, $empId, 'SSS Contribution', $sssDeduction]);
                $deductStmt->execute([$runId, $empId, 'PhilHealth Contribution', $phicDeduction]);
                $deductStmt->execute([$runId, $empId, 'Pag-IBIG Contribution', $hdmfDeduction]);
                
                foreach ($empBenefits as $ben) {
                    if ($ben['type'] === 'HMO' && intval($ben['dependent_count']) > 0) {
                        $hmoCost = floatval($ben['employee_cost']) * intval($ben['dependent_count']);
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
