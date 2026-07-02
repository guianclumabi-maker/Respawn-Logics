<?php

/**
 * CSV export endpoints (downloadable files). All GET, tenant-scoped, permission-gated.
 * Streams a UTF-8 CSV (with BOM for Excel) as a file attachment.
 */
class ExportController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser() ?: null;
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id'])
            ? $this->currentUser['tenant_id']
            : ($_SESSION['tenant_id'] ?? null);
    }

    public function handleRequest($action)
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve tenant context']);
            return;
        }

        try {
            switch ($action) {
                case 'employees':
                    $this->exportEmployees();
                    break;
                case 'attendance':
                    $this->exportAttendance();
                    break;
                case 'payroll':
                    $this->exportPayroll();
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid export.']);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
        }
    }

    private function deny()
    {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Denied']);
        exit;
    }

    /** Streams rows as a CSV file attachment (overrides the JSON content-type set by the front controller). */
    private function stream($filename, array $columnHeaders, PDOStatement $stmt)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly
        fputcsv($out, $columnHeaders);
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    private function exportEmployees()
    {
        if (!hasPermission('employees.view') && !hasPermission('users.view') && empty($_SESSION['is_super'])) $this->deny();
        $stmt = $this->pdo->prepare(
            "SELECT `employee_id`, `full_name`, `email`, `work_email`, `department`, `job_title`, `employment_status`, `hire_date`
             FROM `users` WHERE `tenant_id` = ? ORDER BY `full_name` ASC"
        );
        $stmt->execute([$this->tenantId]);
        $this->stream(
            'employees_' . date('Ymd') . '.csv',
            ['Employee ID', 'Full Name', 'Email', 'Work Email', 'Department', 'Job Title', 'Status', 'Hire Date'],
            $stmt
        );
    }

    private function exportAttendance()
    {
        if (!hasPermission('attendance.view') && !hasPermission('attendance.manage') && empty($_SESSION['is_super'])) $this->deny();
        $start = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end   = $_GET['end_date'] ?? date('Y-m-d');
        $stmt = $this->pdo->prepare(
            "SELECT u.`employee_id`, u.`full_name`, u.`department`, a.`time_in`, a.`time_out`, a.`status`
             FROM `attendance` a
             LEFT JOIN `users` u ON a.`employee_email` = u.`email` AND u.`tenant_id` = a.`tenant_id`
             WHERE a.`tenant_id` = ? AND DATE(a.`time_in`) BETWEEN ? AND ?
             ORDER BY a.`time_in` DESC"
        );
        $stmt->execute([$this->tenantId, $start, $end]);
        $this->stream(
            'attendance_' . $start . '_to_' . $end . '.csv',
            ['Employee ID', 'Name', 'Department', 'Time In', 'Time Out', 'Status'],
            $stmt
        );
    }

    private function exportPayroll()
    {
        if (!hasPermission('payroll.view') && !hasPermission('payroll.manage') && empty($_SESSION['is_super'])) $this->deny();
        $runId = (int)($_GET['run_id'] ?? 0);
        $sql = "SELECT u.`employee_id`, u.`full_name`, pr.`payroll_period_start`, pr.`payroll_period_end`, pr.`pay_date`,
                       pre.`gross_pay`, pre.`total_deductions`, pre.`net_pay`
                FROM `payroll_run_employees` pre
                JOIN `payroll_runs` pr ON pre.`payroll_run_id` = pr.`id`
                LEFT JOIN `users` u ON pre.`employee_id` = u.`id`
                WHERE pr.`tenant_id` = ?";
        $params = [$this->tenantId];
        if ($runId > 0) { $sql .= " AND pre.`payroll_run_id` = ?"; $params[] = $runId; }
        $sql .= " ORDER BY pr.`pay_date` DESC, u.`full_name` ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $this->stream(
            'payroll_' . date('Ymd') . '.csv',
            ['Employee ID', 'Name', 'Period Start', 'Period End', 'Pay Date', 'Gross Pay', 'Deductions', 'Net Pay'],
            $stmt
        );
    }
}
