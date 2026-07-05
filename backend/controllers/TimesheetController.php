<?php

/**
 * Timesheets — manual entry + approval of the per-day hours that payroll reads to compute gross pay.
 * This is the compliant "human checkpoint": a manager enters/approves each employee's hours, giving
 * an auditable record. Payroll's PayrollService only consumes rows with status = 'Approved'.
 * Tenant-scoped; writes/approvals require a manage-level permission.
 */
class TimesheetController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    private $hourCols = ['regular_hours', 'overtime_hours', 'rest_day_hours', 'special_day_hours', 'regular_holiday_hours', 'night_diff_hours'];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser() ?: null;
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id'])
            ? $this->currentUser['tenant_id']
            : ($_SESSION['tenant_id'] ?? null);
    }

    private function canView()
    {
        return hasPermission('attendance.view') || hasPermission('attendance.manage')
            || hasPermission('payroll.view') || hasPermission('payroll.manage') || !empty($_SESSION['is_super']);
    }

    private function canManage()
    {
        return hasPermission('attendance.manage') || hasPermission('payroll.manage') || !empty($_SESSION['is_super']);
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
                case 'list':
                    $this->listTimesheets();
                    break;
                case 'save':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->save($input); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
                    break;
                case 'approve':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->setStatus($input, 'Approved'); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
                    break;
                case 'reject':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->setStatus($input, 'Rejected'); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
                    break;
                case 'delete':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->delete($input); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
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

    /** List timesheets for a date range, optionally filtered by employee/status. */
    private function listTimesheets()
    {
        if (!$this->canView()) { $this->deny(); }

        $start  = $_GET['start_date'] ?? date('Y-m-01');
        $end    = $_GET['end_date'] ?? date('Y-m-t');
        $empId  = trim($_GET['employee_id'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $sql = "SELECT t.*, u.`full_name`, u.`department`
                FROM `timesheets` t
                LEFT JOIN `users` u ON t.`employee_id` = u.`id` AND u.`tenant_id` = t.`tenant_id`
                WHERE t.`tenant_id` = ? AND t.`timesheet_date` BETWEEN ? AND ?";
        $params = [$this->tenantId, $start, $end];
        if ($empId !== '')  { $sql .= " AND t.`employee_id` = ?"; $params[] = (int)$empId; }
        if ($status !== '') { $sql .= " AND t.`status` = ?"; $params[] = $status; }
        $sql .= " ORDER BY t.`timesheet_date` ASC, u.`full_name` ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'timesheets' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    /** Create or update a single day's timesheet entry (upsert on tenant+employee+date). */
    private function save($input)
    {
        if (!$this->canManage()) { $this->deny(); }

        $empId = (int)($input['employee_id'] ?? 0);
        $date  = trim($input['timesheet_date'] ?? '');
        if (!$empId || $date === '' || strtotime($date) === false) {
            echo json_encode(['success' => false, 'error' => 'employee_id and a valid timesheet_date are required.']);
            return;
        }

        // Validate the employee belongs to this tenant.
        $chk = $this->pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `id` = ? AND `tenant_id` = ?");
        $chk->execute([$empId, $this->tenantId]);
        if (!$chk->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Employee not found in this tenant.']);
            return;
        }

        // Sanitize hour values (non-negative floats).
        $hours = [];
        foreach ($this->hourCols as $col) {
            $hours[$col] = max(0, (float)($input[$col] ?? 0));
        }
        // Editing a row sends it back to Pending unless an approver explicitly approves.
        $status = in_array($input['status'] ?? 'Pending', ['Pending', 'Approved', 'Rejected'], true) ? $input['status'] : 'Pending';

        $stmt = $this->pdo->prepare(
            "INSERT INTO `timesheets`
                (`tenant_id`, `employee_id`, `timesheet_date`, `regular_hours`, `overtime_hours`,
                 `rest_day_hours`, `special_day_hours`, `regular_holiday_hours`, `night_diff_hours`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                `regular_hours` = VALUES(`regular_hours`), `overtime_hours` = VALUES(`overtime_hours`),
                `rest_day_hours` = VALUES(`rest_day_hours`), `special_day_hours` = VALUES(`special_day_hours`),
                `regular_holiday_hours` = VALUES(`regular_holiday_hours`), `night_diff_hours` = VALUES(`night_diff_hours`),
                `status` = VALUES(`status`)"
        );
        $stmt->execute([
            $this->tenantId, $empId, $date,
            $hours['regular_hours'], $hours['overtime_hours'], $hours['rest_day_hours'],
            $hours['special_day_hours'], $hours['regular_holiday_hours'], $hours['night_diff_hours'],
            $status
        ]);
        echo json_encode(['success' => true, 'message' => 'Timesheet saved.']);
    }

    /**
     * Approve or reject timesheets. Accepts either a list of ids, or an employee_id + date range.
     * The approval is the compliance checkpoint before payroll can consume the hours.
     */
    private function setStatus($input, $status)
    {
        if (!$this->canManage()) { $this->deny(); }

        $ids = (isset($input['ids']) && is_array($input['ids'])) ? array_map('intval', $input['ids']) : [];

        if (!empty($ids)) {
            $place = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("UPDATE `timesheets` SET `status` = ? WHERE `tenant_id` = ? AND `id` IN ($place)");
            $stmt->execute(array_merge([$status, $this->tenantId], $ids));
            echo json_encode(['success' => true, 'updated' => $stmt->rowCount(), 'message' => "Timesheets {$status}."]);
            return;
        }

        $empId = (int)($input['employee_id'] ?? 0);
        $start = trim($input['start_date'] ?? '');
        $end   = trim($input['end_date'] ?? '');
        if ($empId && $start !== '' && $end !== '') {
            $stmt = $this->pdo->prepare(
                "UPDATE `timesheets` SET `status` = ?
                 WHERE `tenant_id` = ? AND `employee_id` = ? AND `timesheet_date` BETWEEN ? AND ?"
            );
            $stmt->execute([$status, $this->tenantId, $empId, $start, $end]);
            echo json_encode(['success' => true, 'updated' => $stmt->rowCount(), 'message' => "Timesheets {$status}."]);
            return;
        }

        echo json_encode(['success' => false, 'error' => 'Provide ids[] or employee_id + start_date + end_date.']);
    }

    private function delete($input)
    {
        if (!$this->canManage()) { $this->deny(); }
        $id = (int)($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Timesheet id required']); return; }
        $stmt = $this->pdo->prepare("DELETE FROM `timesheets` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$id, $this->tenantId]);
        echo json_encode(['success' => true, 'message' => 'Timesheet deleted.']);
    }
}
