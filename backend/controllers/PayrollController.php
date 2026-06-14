<?php

class PayrollController
{
    private $pdo;
    private $currentUser;
    private $tenantId;
    private $payrollService;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser() ?: null;
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id']) ? $this->currentUser['tenant_id'] : ($_SESSION['tenant_id'] ?? '1');
        
        // Load the new Service Layer
        require_once __DIR__ . '/../services/PayrollService.php';
        $this->payrollService = new PayrollService($pdo);
    }

    private function canManagePayroll()
    {
        return hasPermission('payroll.manage');
    }

    public function handleRequest($action)
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        switch ($action) {
            case 'schedules':
                $stmt = $this->pdo->prepare("SELECT * FROM `payroll_schedules` WHERE `tenant_id` = ?");
                $stmt->execute([$this->tenantId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                break;

            case 'create_schedule':
                if (!$this->canManagePayroll()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                $name = $input['name'] ?? '';
                $freq = $input['frequency'] ?? 'Monthly';
                
                $stmt = $this->pdo->prepare("INSERT INTO `payroll_schedules` (`tenant_id`, `name`, `frequency`) VALUES (?, ?, ?)");
                $stmt->execute([$this->tenantId, $name, $freq]);
                echo json_encode(['success' => true]);
                break;

            case 'assign_schedule':
                if (!$this->canManagePayroll()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                $userId = $input['user_id'] ?? 0;
                $schedId = $input['schedule_id'] ?? 0;
                
                $stmt = $this->pdo->prepare("UPDATE `users` SET `payroll_schedule_id` = ? WHERE `id` = ? AND `tenant_id` = ?");
                $stmt->execute([$schedId, $userId, $this->tenantId]);
                echo json_encode(['success' => true]);
                break;

            case 'runs':
                $stmt = $this->pdo->prepare("SELECT pr.*, ps.name as schedule_name FROM `payroll_runs` pr LEFT JOIN `payroll_schedules` ps ON pr.payroll_schedule_id = ps.id WHERE pr.tenant_id = ? ORDER BY pr.id DESC");
                $stmt->execute([$this->tenantId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                break;

            case 'generate_run':
                if (!$this->canManagePayroll()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                
                $scheduleId = $input['schedule_id'] ?? 0;
                $start = $input['start_date'] ?? '';
                $end = $input['end_date'] ?? '';
                $payDate = $input['pay_date'] ?? '';

                if (!$scheduleId || !$start || !$end || !$payDate) {
                    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
                    return;
                }

                // Delegate to the heavily optimized Service Layer
                $result = $this->payrollService->generateRun(
                    $this->tenantId, 
                    $scheduleId, 
                    $start, 
                    $end, 
                    $payDate, 
                    $this->currentUser['id']
                );

                echo json_encode($result);
                break;

            case 'run_details':
                if (!$this->canManagePayroll()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                $runId = intval($_GET['id'] ?? 0);

                $runStmt = $this->pdo->prepare("SELECT * FROM `payroll_runs` WHERE `id` = ? AND `tenant_id` = ?");
                $runStmt->execute([$runId, $this->tenantId]);
                $run = $runStmt->fetch();

                if (!$run) {
                    echo json_encode(['success' => false, 'error' => 'Payroll run not found or access denied']);
                    return;
                }

                $empStmt = $this->pdo->prepare("
                    SELECT pre.*, u.full_name, u.employee_number, u.department 
                    FROM `payroll_run_employees` pre
                    JOIN `users` u ON pre.employee_id = u.id
                    WHERE pre.payroll_run_id = ?
                ");
                $empStmt->execute([$runId]);
                $employees = $empStmt->fetchAll();

                $detStmt = $this->pdo->prepare("
                    SELECT 'Earning' as r_type, earning_type as description, amount, employee_id FROM `payroll_earnings` WHERE payroll_run_id = ?
                    UNION ALL
                    SELECT 'Deduction' as r_type, deduction_type as description, amount, employee_id FROM `payroll_deductions` WHERE payroll_run_id = ?
                ");
                $detStmt->execute([$runId, $runId]);
                $details = $detStmt->fetchAll();

                echo json_encode(['success' => true, 'data' => ['run' => $run, 'employees' => $employees, 'details' => $details]]);
                break;

            case 'update_run_status':
                if (!$this->canManagePayroll()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                $runId = $input['run_id'] ?? 0;
                $status = $input['status'] ?? ''; 

                // Verify run ownership first to prevent unauthorized cross-tenant operations
                $verifyStmt = $this->pdo->prepare("SELECT id FROM `payroll_runs` WHERE `id` = ? AND `tenant_id` = ?");
                $verifyStmt->execute([$runId, $this->tenantId]);
                if (!$verifyStmt->fetch()) {
                    echo json_encode(['success' => false, 'error' => 'Payroll run not found or access denied']);
                    return;
                }

                if ($status === 'Processed') {
                    $empStmt = $this->pdo->prepare("SELECT employee_id FROM payroll_run_employees WHERE payroll_run_id = ?");
                    $empStmt->execute([$runId]);
                    $emps = $empStmt->fetchAll();

                    foreach ($emps as $e) {
                        $pdfPath = "uploads/tenant_{$this->tenantId}/payslips/run_{$runId}_emp_{$e['employee_id']}.pdf";
                        
                        try {
                            $psStmt = $this->pdo->prepare("INSERT INTO `payroll_payslips` (`tenant_id`, `payroll_run_id`, `employee_id`, `pdf_path`) VALUES (?, ?, ?, ?)");
                            $psStmt->execute([$this->tenantId, $runId, $e['employee_id'], $pdfPath]);
                        } catch(Exception $ex) { /* ignore duplicate */ }
                    }
                }

                $stmt = $this->pdo->prepare("UPDATE `payroll_runs` SET `status` = ? WHERE `id` = ? AND `tenant_id` = ?");
                $stmt->execute([$status, $runId, $this->tenantId]);
                echo json_encode(['success' => true]);
                break;

            case 'my_payslips':
                $stmt = $this->pdo->prepare("
                    SELECT pp.*, pr.payroll_period_start, pr.payroll_period_end, pr.pay_date 
                    FROM `payroll_payslips` pp
                    JOIN `payroll_runs` pr ON pp.payroll_run_id = pr.id
                    WHERE pp.employee_id = ? AND pp.tenant_id = ?
                    ORDER BY pr.pay_date DESC
                ");
                $stmt->execute([$this->currentUser['id'], $this->tenantId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                break;

            case 'dashboard_kpis':
                $empStmt = $this->pdo->prepare("SELECT COUNT(*) as cnt, SUM(base_salary) as total_base FROM `users` WHERE `tenant_id` = ? AND `employment_status` = 'Active'");
                $empStmt->execute([$this->tenantId]);
                $empData = $empStmt->fetch();
                
                $runStmt = $this->pdo->prepare("SELECT * FROM `payroll_runs` WHERE `tenant_id` = ? AND `status` IN ('Draft', 'Processing', 'Approved') ORDER BY id DESC LIMIT 1");
                $runStmt->execute([$this->tenantId]);
                $activeRun = $runStmt->fetch();

                $processed = 0;
                if ($activeRun) {
                    $pStmt = $this->pdo->prepare("SELECT COUNT(*) as cnt FROM `payroll_run_employees` WHERE `payroll_run_id` = ?");
                    $pStmt->execute([$activeRun['id']]);
                    $processed = $pStmt->fetchColumn();
                }
                
                echo json_encode(['success' => true, 'data' => [
                    'themePreference' => $_SESSION['theme_preference'] ?? 'dark',
                    'activeRunName' => $activeRun ? 'PR-' . date('Y') . '-' . str_pad($activeRun['id'], 4, '0', STR_PAD_LEFT) : null,
                    'activeRunTotalEmployees' => intval($empData['cnt']),
                    'activeRunProcessed' => intval($processed),
                    'nextDate' => $activeRun ? $activeRun['pay_date'] : date('Y-m-t'),
                    'estimatedCost' => floatval($empData['total_base']),
                    'costIncrease' => 0,
                    'criticalExceptions' => 0,
                    'readiness' => 'Ready'
                ]]);
                break;

            case 'chart_data':
                echo json_encode(['success' => true, 'data' => [
                    ['name' => 'Jan', 'cost' => 1250000],
                    ['name' => 'Feb', 'cost' => 1280000],
                    ['name' => 'Mar', 'cost' => 1310000],
                    ['name' => 'Apr', 'cost' => 1315000],
                    ['name' => 'May', 'cost' => 1340000],
                    ['name' => 'Jun', 'cost' => 1380000]
                ]]);
                break;

            case 'exceptions_list':
                echo json_encode(['success' => true, 'data' => []]);
                break;

            case 'comp_history':
                echo json_encode(['success' => true, 'data' => [
                    'employeeName' => $this->currentUser['full_name'],
                    'employeeId' => $this->currentUser['employee_number'] ?: 'EMP-' . $this->currentUser['id'],
                    'currentBase' => floatval($this->currentUser['base_salary'] ?? 0),
                    'history' => [
                        ['id' => 1, 'base' => floatval($this->currentUser['base_salary'] ?? 0), 'type' => 'Monthly', 'status' => 'Active', 'effective' => 'Jan 1, 2024', 'author' => 'System']
                    ],
                    'audits' => []
                ]]);
                break;

            case 'settings':
                echo json_encode(['success' => true, 'data' => [
                    'extraction' => ['attendance' => true, 'leave' => true, 'recurring' => true],
                    'processing' => ['mode' => 'manual', 'statutory' => true]
                ]]);
                break;

            case 'gov_reports':
                echo json_encode(['success' => true, 'data' => []]);
                break;

            case 'payslips_admin':
                $stmt = $this->pdo->prepare("
                    SELECT pp.id, pp.employee_id, u.full_name as empName, 
                           CONCAT(pr.payroll_period_start, ' to ', pr.payroll_period_end) as period,
                           pre.net_pay as net, 'Published' as status
                    FROM `payroll_payslips` pp
                    JOIN `payroll_runs` pr ON pp.payroll_run_id = pr.id
                    JOIN `users` u ON pp.employee_id = u.id
                    JOIN `payroll_run_employees` pre ON (pre.payroll_run_id = pp.payroll_run_id AND pre.employee_id = pp.employee_id)
                    WHERE pp.tenant_id = ?
                    ORDER BY pp.id DESC
                ");
                $stmt->execute([$this->tenantId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;

            case 'payslip_details':
                $id = intval($_GET['id'] ?? 0);
                $stmt = $this->pdo->prepare("
                    SELECT pp.id, pp.employee_id, u.full_name as empName, u.employee_number as empId, pp.payroll_run_id,
                           CONCAT(pr.payroll_period_start, ' to ', pr.payroll_period_end) as period,
                           pre.net_pay as netPay, pre.gross_pay as gross, pre.total_deductions as totalDeductions
                    FROM `payroll_payslips` pp
                    JOIN `payroll_runs` pr ON pp.payroll_run_id = pr.id
                    JOIN `users` u ON pp.employee_id = u.id
                    JOIN `payroll_run_employees` pre ON (pre.payroll_run_id = pp.payroll_run_id AND pre.employee_id = pp.employee_id)
                    WHERE pp.id = ? AND pp.tenant_id = ?
                ");
                $stmt->execute([$id, $this->tenantId]);
                $ps = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($ps) {
                    $eStmt = $this->pdo->prepare("SELECT earning_type as label, amount FROM `payroll_earnings` WHERE payroll_run_id = ? AND employee_id = ?");
                    $eStmt->execute([$ps['payroll_run_id'], $ps['employee_id']]);
                    $ps['earnings'] = $eStmt->fetchAll(PDO::FETCH_ASSOC);

                    $dStmt = $this->pdo->prepare("SELECT deduction_type as label, amount FROM `payroll_deductions` WHERE payroll_run_id = ? AND employee_id = ?");
                    $dStmt->execute([$ps['payroll_run_id'], $ps['employee_id']]);
                    $ps['deductions'] = $dStmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode(['success' => true, 'data' => $ps]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Not found']);
                }
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
                break;
        }
    }
}
