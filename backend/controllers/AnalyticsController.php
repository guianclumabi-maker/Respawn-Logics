<?php

class AnalyticsController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser() ?: null;
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id']) ? $this->currentUser['tenant_id'] : ($_SESSION['tenant_id'] ?? '1');
    }

    public function handleRequest($action)
    {
        if (!hasPermission('analytics.view')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access Denied: Executive clearance required.']);
            return;
        }

        switch ($action) {
            case 'headcount_by_dept':
                $this->headcountByDept();
                break;
            case 'payroll_trend':
                $this->payrollTrend();
                break;
            case 'talent_density':
                $this->talentDensity();
                break;
            case 'attrition_risks':
                $this->attritionRisks();
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
                break;
        }
    }

    private function headcountByDept()
    {
        $stmt = $this->pdo->prepare("
            SELECT IFNULL(department, 'Unassigned') as label, COUNT(id) as count 
            FROM `users` 
            WHERE `tenant_id` = ? AND `employment_status` = 'Active' 
            GROUP BY department
            ORDER BY count DESC
        ");
        $stmt->execute([$this->tenantId]);
        $results = $stmt->fetchAll();
        
        $labels = [];
        $data = [];
        foreach ($results as $row) {
            $labels[] = $row['label'];
            $data[] = (int)$row['count'];
        }
        
        echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
    }

    private function payrollTrend()
    {
        $stmt = $this->pdo->prepare("
            SELECT pr.payroll_period_end as label, SUM(pre.gross_pay) as total_gross 
            FROM `payroll_runs` pr
            JOIN `payroll_run_employees` pre ON pr.id = pre.payroll_run_id
            WHERE pr.tenant_id = ? AND pr.status IN ('Processed', 'Locked')
            GROUP BY pr.id, pr.payroll_period_end
            ORDER BY pr.payroll_period_end ASC
            LIMIT 12
        ");
        $stmt->execute([$this->tenantId]);
        $results = $stmt->fetchAll();
        
        $labels = [];
        $data = [];
        foreach ($results as $row) {
            $labels[] = $row['label'];
            $data[] = (float)$row['total_gross'];
        }
        
        echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
    }

    private function talentDensity()
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                CONCAT(nine_box_performance, '-', nine_box_potential) as box_key,
                COUNT(*) as count
            FROM `performance_reviews`
            WHERE `tenant_id` = ? AND `status` = 'Finalized'
            GROUP BY nine_box_performance, nine_box_potential
        ");
        $stmt->execute([$this->tenantId]);
        $results = $stmt->fetchAll();
        
        $mapping = [
            '3-3' => 'Future Leader', '3-2' => 'Growth Employee', '3-1' => 'Enigma',
            '2-3' => 'High Impact',   '2-2' => 'Core Employee',   '2-1' => 'Dilemma',
            '1-3' => 'Trusted Pro',   '1-2' => 'Solid Pro',       '1-1' => 'Risk'
        ];
        
        $boxCounts = array_fill_keys(array_values($mapping), 0);
        
        foreach ($results as $row) {
            $boxName = $mapping[$row['box_key']] ?? 'Unknown';
            if(isset($boxCounts[$boxName])) {
                $boxCounts[$boxName] += (int)$row['count'];
            }
        }
        
        $labels = array_keys($boxCounts);
        $data = array_values($boxCounts);
        
        echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
    }

    private function attritionRisks()
    {
        // Allowed for Admin, HR, Manager, CEO, Head
        if (!hasPermission('analytics.view') && !hasPermission('intelligence.view')) {
            echo json_encode(['success' => false, 'error' => 'Denied']); return;
        }

        // Base query to pull active users
        // If not Admin/HR/CEO, filter to only users where immediate_supervisor = current user or department_manager = current user
        $sql = "SELECT id, email, full_name, role, department, job_title, hire_date, immediate_supervisor, base_salary, profile_image FROM `users` WHERE `tenant_id` = ? AND `employment_status` = 'Active'";
        $params = [$this->tenantId];

        if (!hasPermission('analytics.view')) {
            $sql .= " AND (`immediate_supervisor` = ? OR `department_manager` = ?)";
            $params[] = $this->currentUser['email'];
            $params[] = $this->currentUser['email'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll();

        $risks = [];
        
        // Fetch global average salary per department to use as a benchmark
        $avgSalaries = [];
        $stmtAvg = $this->pdo->prepare("SELECT department, AVG(base_salary) as avg_sal FROM `users` WHERE `tenant_id` = ? AND `employment_status` = 'Active' GROUP BY department");
        $stmtAvg->execute([$this->tenantId]);
        foreach ($stmtAvg->fetchAll() as $row) {
            $avgSalaries[$row['department']] = $row['avg_sal'];
        }

        foreach ($employees as $emp) {
            $risk_score = 10; // Baseline 10%
            $factors = [];

            // Factor 1: Tenure / Stagnation (Flight risk increases around the 2-year and 4-year marks)
            $hire_date = new DateTime($emp['hire_date']);
            $now = new DateTime();
            $years_tenure = $now->diff($hire_date)->y;
            
            if ($years_tenure >= 2 && $years_tenure < 3) {
                $risk_score += 20;
                $factors[] = "Approaching 2-year tenure mark (high turnover window).";
            } else if ($years_tenure >= 4) {
                $risk_score += 15;
                $factors[] = "High tenure without recent known promotion data.";
            }

            // Factor 2: Compensation
            $dept_avg = $avgSalaries[$emp['department']] ?? 0;
            if ($dept_avg > 0 && $emp['base_salary'] > 0) {
                if ($emp['base_salary'] < ($dept_avg * 0.85)) {
                    $risk_score += 35;
                    $factors[] = "Salary is >15% below department average.";
                } else if ($emp['base_salary'] < ($dept_avg * 0.95)) {
                    $risk_score += 15;
                    $factors[] = "Salary is slightly below department average.";
                }
            }

            // Factor 3: Missing Supervisor
            if (empty($emp['immediate_supervisor'])) {
                $risk_score += 25;
                $factors[] = "No direct supervisor assigned (leadership vacuum).";
            }

            // Cap at 95%
            if ($risk_score > 95) $risk_score = 95;
            
            // For managers, we don't want to flood the board if everyone is low risk. 
            // We'll return everyone but sort by risk.
            $risks[] = [
                'user_id' => $emp['id'],
                'full_name' => $emp['full_name'],
                'department' => $emp['department'],
                'job_title' => $emp['job_title'],
                'profile_image' => $emp['profile_image'],
                'risk_score' => $risk_score,
                'factors' => $factors,
                'zone' => $risk_score >= 70 ? 'Red' : ($risk_score >= 40 ? 'Orange' : 'Green')
            ];
        }

        // Sort descending by risk score
        usort($risks, function($a, $b) {
            return $b['risk_score'] <=> $a['risk_score'];
        });

        // Top 10 highest risks
        $risks = array_slice($risks, 0, 10);

        echo json_encode(['success' => true, 'data' => $risks]);
    }
}
