<?php
require_once __DIR__ . '/../utils/Storage.php';

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
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id']) ? $this->currentUser['tenant_id'] : ($_SESSION['tenant_id'] ?? null);
        
        // Load the new Service Layer
        require_once __DIR__ . '/../services/PayrollService.php';
        $this->payrollService = new PayrollService($pdo);
    }

    private function canViewPayroll()
    {
        return hasPermission('payroll.view') || hasPermission('payroll.manage');
    }

    private function canRunPayroll()
    {
        return hasPermission('payroll.run') || hasPermission('payroll.manage');
    }

    private function canApprovePayroll()
    {
        return hasPermission('payroll.approve') || hasPermission('payroll.manage');
    }

    public function handleRequest($action)
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve tenant context']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            switch ($action) {
                case 'schedules':
                    if (!$this->canViewPayroll()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $stmt = $this->pdo->prepare("SELECT * FROM `payroll_schedules` WHERE `tenant_id` = ?");
                    $stmt->execute([$this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'create_schedule':
                    if (!$this->canRunPayroll()) { http_response_code(403); echo json_encode(['success'=>false, 'error'=>'Denied']); return; }
                    $name = $input['name'] ?? '';
                    $freq = $input['frequency'] ?? 'Monthly';
                    
                    $stmt = $this->pdo->prepare("INSERT INTO `payroll_schedules` (`tenant_id`, `name`, `frequency`) VALUES (?, ?, ?)");
                    $stmt->execute([$this->tenantId, $name, $freq]);
                    echo json_encode(['success' => true]);
                    break;

                case 'assign_schedule':
                    if (!$this->canRunPayroll()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $userId = $input['user_id'] ?? 0;
                    $schedId = $input['schedule_id'] ?? 0;
                    
                    $stmt = $this->pdo->prepare("UPDATE `users` SET `payroll_schedule_id` = ? WHERE `id` = ? AND `tenant_id` = ?");
                    $stmt->execute([$schedId, $userId, $this->tenantId]);
                    echo json_encode(['success' => true]);
                    break;

                case 'runs':
                    if (!$this->canViewPayroll()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $stmt = $this->pdo->prepare("SELECT pr.*, ps.name as schedule_name FROM `payroll_runs` pr LEFT JOIN `payroll_schedules` ps ON pr.payroll_schedule_id = ps.id WHERE pr.tenant_id = ? ORDER BY pr.id DESC");
                    $stmt->execute([$this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'generate_run':
                    if (!$this->canRunPayroll()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    
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
                    if (!$this->canViewPayroll()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
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
                    $runId = $input['run_id'] ?? 0;
                    $status = $input['status'] ?? ''; 

                    if (!in_array($status, ['Draft', 'Processing', 'Approved', 'Processed', 'Locked', 'Rejected'])) {
                        http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid status provided']); return;
                    }

                    if (in_array($status, ['Approved', 'Processed'])) {
                        if (!$this->canApprovePayroll()) {
                            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied: Payroll Approver role required']); return;
                        }
                    } else {
                        if (!$this->canRunPayroll()) {
                            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied: Payroll Run access required']); return;
                        }
                    } 

                    // Verify run ownership first to prevent unauthorized cross-tenant operations
                    $verifyStmt = $this->pdo->prepare("SELECT id FROM `payroll_runs` WHERE `id` = ? AND `tenant_id` = ?");
                    $verifyStmt->execute([$runId, $this->tenantId]);
                    if (!$verifyStmt->fetch()) {
                        echo json_encode(['success' => false, 'error' => 'Payroll run not found or access denied']);
                        return;
                    }

                    if ($status === 'Processed') {
                        require_once __DIR__ . '/../utils/Storage.php';
                        require_once __DIR__ . '/../utils/PayslipGenerator.php';
                        
                        // Fetch Run details for period and pay date
                        $runStmt = $this->pdo->prepare("SELECT payroll_period_start, payroll_period_end, pay_date FROM payroll_runs WHERE id = ?");
                        $runStmt->execute([$runId]);
                        $runDetails = $runStmt->fetch();
                        $periodStr = $runDetails['payroll_period_start'] . ' to ' . $runDetails['payroll_period_end'];
                        $payDateStr = $runDetails['pay_date'];

                        $empStmt = $this->pdo->prepare("
                            SELECT pre.employee_id, u.full_name, u.employee_number 
                            FROM payroll_run_employees pre 
                            JOIN users u ON pre.employee_id = u.id 
                            WHERE pre.payroll_run_id = ?
                        ");
                        $empStmt->execute([$runId]);
                        $emps = $empStmt->fetchAll();

                        foreach ($emps as $e) {
                            $empId = $e['employee_id'];
                            
                            // Fetch earnings
                            $earnStmt = $this->pdo->prepare("SELECT earning_type as description, amount FROM payroll_earnings WHERE payroll_run_id = ? AND employee_id = ?");
                            $earnStmt->execute([$runId, $empId]);
                            $earnings = $earnStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Fetch deductions
                            $dedStmt = $this->pdo->prepare("SELECT deduction_type as description, amount FROM payroll_deductions WHERE payroll_run_id = ? AND employee_id = ?");
                            $dedStmt->execute([$runId, $empId]);
                            $deductions = $dedStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            $netPayStmt = $this->pdo->prepare("SELECT net_pay FROM payroll_run_employees WHERE payroll_run_id = ? AND employee_id = ?");
                            $netPayStmt->execute([$runId, $empId]);
                            $netPayRow = $netPayStmt->fetch();
                            $netPay = $netPayRow ? floatval($netPayRow['net_pay']) : 0;

                            $pdfPath = "tenant_{$this->tenantId}/payslips/run_{$runId}_emp_{$empId}.pdf";
                            $fullPath = Storage::resolveStorageBase() . '/' . $pdfPath;
                            
                            $pdfData = [
                                'employeeName' => $e['full_name'],
                                'employeeId'   => $e['employee_number'] ?: 'EMP-' . $empId,
                                'period'       => $periodStr,
                                'payDate'      => $payDateStr,
                                'earnings'     => $earnings,
                                'deductions'   => $deductions,
                                'netPay'       => $netPay
                            ];
                            
                            try {
                                PayslipGenerator::generate($pdfData, $fullPath);
                                
                                $psStmt = $this->pdo->prepare("INSERT INTO `payroll_payslips` (`tenant_id`, `payroll_run_id`, `employee_id`, `pdf_path`) VALUES (?, ?, ?, ?)");
                                $psStmt->execute([$this->tenantId, $runId, $empId, $pdfPath]);
                            } catch(Exception $ex) { 
                                error_log("Payslip generation failed: " . $ex->getMessage());
                            }
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
                    $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($payslips as &$ps) {
                        $ps['download_url'] = '../api/index.php?route=payroll&action=download_payslip&id=' . $ps['id'];
                    }
                    echo json_encode(['success' => true, 'data' => $payslips]);
                    break;

                case 'dashboard_kpis':
                    if (!$this->canManagePayroll()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
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

                    $prevStmt = $this->pdo->prepare("SELECT SUM(pre.gross_pay) as prev_total FROM payroll_runs pr JOIN payroll_run_employees pre ON pre.payroll_run_id = pr.id WHERE pr.tenant_id = ? AND pr.status IN ('Processed', 'Locked') ORDER BY pr.pay_date DESC LIMIT 1");
                    $prevStmt->execute([$this->tenantId]);
                    $prevTotal = (float)$prevStmt->fetchColumn();
                    $currentCost = floatval($empData['total_base']);
                    $costIncrease = 0;
                    if ($prevTotal > 0) {
                        $costIncrease = round((($currentCost - $prevTotal) / $prevTotal) * 100, 1);
                    }

                    $exStmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND employment_status = 'Active' AND (base_salary IS NULL OR base_salary <= 0)");
                    $exStmt->execute([$this->tenantId]);
                    $criticalExceptions = (int)$exStmt->fetchColumn();

                    $readiness = ($activeRun && $criticalExceptions === 0) ? 'Ready' : 'Needs Attention';
                    
                    echo json_encode(['success' => true, 'data' => [
                        'themePreference' => $_SESSION['theme_preference'] ?? 'dark',
                        'activeRunName' => $activeRun ? 'PR-' . date('Y') . '-' . str_pad($activeRun['id'], 4, '0', STR_PAD_LEFT) : null,
                        'activeRunTotalEmployees' => intval($empData['cnt']),
                        'activeRunProcessed' => intval($processed),
                        'nextDate' => $activeRun ? $activeRun['pay_date'] : date('Y-m-t'),
                        'estimatedCost' => $currentCost,
                        'costIncrease' => $costIncrease,
                        'criticalExceptions' => $criticalExceptions,
                        'readiness' => $readiness
                    ]]);
                    break;

                case 'chart_data':
                    if (!$this->canManagePayroll()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $stmt = $this->pdo->prepare("
                        SELECT DATE_FORMAT(pr.pay_date,'%b') AS name, SUM(pre.gross_pay) AS cost
                        FROM payroll_runs pr 
                        JOIN payroll_run_employees pre ON pre.payroll_run_id = pr.id
                        WHERE pr.tenant_id = ? AND pr.status IN ('Processed','Locked')
                        GROUP BY YEAR(pr.pay_date), MONTH(pr.pay_date) 
                        ORDER BY YEAR(pr.pay_date) ASC, MONTH(pr.pay_date) ASC 
                        LIMIT 6
                    ");
                    $stmt->execute([$this->tenantId]);
                    $chartData = [];
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $chartData[] = [
                            'name' => $row['name'],
                            'cost' => floatval($row['cost'])
                        ];
                    }
                    echo json_encode(['success' => true, 'data' => $chartData]);
                    break;

                case 'exceptions_list':
                    if (!$this->canManagePayroll()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $exceptions = [];
                    $exStmt = $this->pdo->prepare("SELECT full_name FROM users WHERE tenant_id = ? AND employment_status = 'Active' AND (base_salary IS NULL OR base_salary <= 0)");
                    $exStmt->execute([$this->tenantId]);
                    while ($row = $exStmt->fetch(PDO::FETCH_ASSOC)) {
                        $exceptions[] = [
                            'employee' => $row['full_name'],
                            'issue' => 'Missing Base Salary',
                            'severity' => 'Critical'
                        ];
                    }

                    $activeRunStmt = $this->pdo->prepare("SELECT * FROM payroll_runs WHERE tenant_id = ? AND status IN ('Draft', 'Processing') ORDER BY id DESC LIMIT 1");
                    $activeRunStmt->execute([$this->tenantId]);
                    $activeRun = $activeRunStmt->fetch();

                    if ($activeRun) {
                        $tsStmt = $this->pdo->prepare("
                            SELECT u.full_name 
                            FROM users u 
                            WHERE u.tenant_id = ? AND u.employment_status = 'Active' 
                            AND NOT EXISTS (
                                SELECT 1 FROM timesheets t 
                                WHERE t.tenant_id = u.tenant_id AND t.employee_id = u.id 
                                AND t.timesheet_date >= ? AND t.timesheet_date <= ? 
                                AND t.status = 'Approved'
                            )
                        ");
                        $tsStmt->execute([$this->tenantId, $activeRun['payroll_period_start'], $activeRun['payroll_period_end']]);
                        while ($row = $tsStmt->fetch(PDO::FETCH_ASSOC)) {
                            $exceptions[] = [
                                'employee' => $row['full_name'],
                                'issue' => 'Unapproved / Missing Timesheets',
                                'severity' => 'High'
                            ];
                        }
                    }

                    echo json_encode(['success' => true, 'data' => $exceptions]);
                    break;

                case 'comp_history':
                    $history = [];
                    $stmt = $this->pdo->prepare("SELECT id, base_salary as base, pay_frequency as type, 'Active' as status, DATE_FORMAT(effective_date, '%b %e, %Y') as effective, 'System' as author FROM employee_compensation WHERE tenant_id = ? AND employee_id = ? ORDER BY effective_date DESC");
                    $stmt->execute([$this->tenantId, $this->currentUser['id']]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $row['base'] = floatval($row['base']);
                        $history[] = $row;
                    }

                    if (empty($history)) {
                        $history[] = [
                            'id' => 'current',
                            'base' => floatval($this->currentUser['base_salary'] ?? 0),
                            'type' => 'Monthly',
                            'status' => 'Active',
                            'effective' => 'Current',
                            'author' => 'System'
                        ];
                    }

                    echo json_encode(['success' => true, 'data' => [
                        'employeeName' => $this->currentUser['full_name'],
                        'employeeId' => $this->currentUser['employee_number'] ?: 'EMP-' . $this->currentUser['id'],
                        'currentBase' => floatval($this->currentUser['base_salary'] ?? 0),
                        'history' => $history,
                        'audits' => []
                    ]]);
                    break;

                case 'settings':
                    $settings = [
                        'default_pay_frequency' => 'Semi-Monthly',
                        'proration_method' => 'split_even',
                        'default_pay_basis' => 'monthly',
                        'tax_annualization' => 0,
                        'mwe_auto_exempt' => 1,
                        'rounding_mode' => 'half_up',
                        'approval_levels' => 1
                    ];
                    try {
                        $stmt = $this->pdo->prepare("SELECT * FROM `tenant_payroll_settings` WHERE `tenant_id` = ?");
                        $stmt->execute([$this->tenantId]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            $settings = $row;
                        }
                    } catch (Exception $e) {
                        // Fallback
                    }
                    echo json_encode(['success' => true, 'data' => $settings]);
                    break;

                case 'save_settings':
                    if (!$this->canManagePayroll()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $stmt = $this->pdo->prepare("
                        INSERT INTO `tenant_payroll_settings` 
                        (`tenant_id`, `default_pay_frequency`, `proration_method`, `default_pay_basis`, `tax_annualization`, `mwe_auto_exempt`, `rounding_mode`, `approval_levels`)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        `default_pay_frequency`=VALUES(`default_pay_frequency`),
                        `proration_method`=VALUES(`proration_method`),
                        `default_pay_basis`=VALUES(`default_pay_basis`),
                        `tax_annualization`=VALUES(`tax_annualization`),
                        `mwe_auto_exempt`=VALUES(`mwe_auto_exempt`),
                        `rounding_mode`=VALUES(`rounding_mode`),
                        `approval_levels`=VALUES(`approval_levels`)
                    ");
                    $stmt->execute([
                        $this->tenantId,
                        $input['default_pay_frequency'] ?? 'Semi-Monthly',
                        $input['proration_method'] ?? 'split_even',
                        $input['default_pay_basis'] ?? 'monthly',
                        (int)($input['tax_annualization'] ?? 0),
                        (int)($input['mwe_auto_exempt'] ?? 1),
                        $input['rounding_mode'] ?? 'half_up',
                        (int)($input['approval_levels'] ?? 1)
                    ]);
                    echo json_encode(['success' => true]);
                    break;

                case 'components_list':
                    try {
                        $stmt = $this->pdo->prepare("SELECT * FROM `pay_components` WHERE `tenant_id` = ? ORDER BY `sort_order` ASC, `id` ASC");
                        $stmt->execute([$this->tenantId]);
                        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                    } catch (Exception $e) {
                        echo json_encode(['success' => true, 'data' => []]);
                    }
                    break;

                case 'component_save':
                    if (!$this->canManagePayroll()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $id = intval($input['id'] ?? 0);
                    $code = $input['code'] ?? '';
                    $name = $input['name'] ?? '';
                    $kind = $input['kind'] ?? 'earning';
                    $calc_type = $input['calc_type'] ?? 'fixed';
                    $value = isset($input['value']) && $input['value'] !== '' ? floatval($input['value']) : null;
                    $taxable = isset($input['taxable']) ? (int)$input['taxable'] : 1;
                    $statutory_key = $input['statutory_key'] ?? null;
                    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

                    if ($id > 0) {
                        $stmt = $this->pdo->prepare("UPDATE `pay_components` SET `code`=?, `name`=?, `kind`=?, `calc_type`=?, `value`=?, `taxable`=?, `statutory_key`=?, `is_active`=? WHERE `id`=? AND `tenant_id`=?");
                        $stmt->execute([$code, $name, $kind, $calc_type, $value, $taxable, $statutory_key, $is_active, $id, $this->tenantId]);
                    } else {
                        $stmt = $this->pdo->prepare("INSERT INTO `pay_components` (`tenant_id`, `code`, `name`, `kind`, `calc_type`, `value`, `taxable`, `statutory_key`, `is_active`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$this->tenantId, $code, $name, $kind, $calc_type, $value, $taxable, $statutory_key, $is_active]);
                    }
                    echo json_encode(['success' => true]);
                    break;

                case 'component_delete':
                    if (!$this->canManagePayroll()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $id = intval($input['id'] ?? 0);
                    $stmt = $this->pdo->prepare("DELETE FROM `pay_components` WHERE `id` = ? AND `tenant_id` = ?");
                    $stmt->execute([$id, $this->tenantId]);
                    echo json_encode(['success' => true]);
                    break;

                case 'gov_reports':
                    echo json_encode(['success' => true, 'data' => []]);
                    break;

                case 'payslips_admin':
                    if (!$this->canManagePayroll()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
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
                    $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($payslips as &$ps) {
                        $ps['download_url'] = '../api/index.php?route=payroll&action=download_payslip&id=' . $ps['id'];
                    }
                    echo json_encode(['success' => true, 'data' => $payslips]);
                    break;

                case 'payslip_details':
                    if (!$this->canManagePayroll()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
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
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    private function downloadPayslip() {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo "Missing ID"; return; }

        $stmt = $this->pdo->prepare("SELECT `employee_id`, `pdf_path` FROM `payroll_payslips` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$id, $this->tenantId]);
        $ps = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ps) { http_response_code(404); echo "Payslip not found"; return; }

        $isPayrollManager = $this->canViewPayroll();
        if (!$isPayrollManager && $this->currentUser['id'] !== $ps['employee_id']) {
            http_response_code(403);
            echo "Access denied";
            return;
        }

        $storageBase = \App\Utils\Storage::resolveStorageBase(false, false);
        $dbPath = preg_replace('/^uploads\//', '', $ps['pdf_path']);
        $fullPath = rtrim($storageBase, '/') . '/' . ltrim($dbPath, '/');

        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo "Payslip PDF not generated yet.";
            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="Payslip_' . $id . '.pdf"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }
}
