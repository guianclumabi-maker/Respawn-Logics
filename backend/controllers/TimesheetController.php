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

                // ── Phase B: auto-draft from attendance + holiday calendar ──
                case 'generate_draft':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->generateDraft($input); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
                    break;
                case 'holidays':
                    $this->listHolidays();
                    break;
                case 'save_holiday':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->saveHoliday($input); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
                    break;
                case 'delete_holiday':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->deleteHoliday($input); }
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
        $status = 'Pending';
        if (isset($input['status']) && in_array($input['status'], ['Pending', 'Approved', 'Rejected'], true)) {
            $status = $input['status'];
        }

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

    // ─────────────────────────────────────────────────────────────
    //  Phase B — Auto-draft timesheets from attendance punches
    //  Computes per-day hour buckets from clock in/out, classified against the holiday
    //  calendar + weekend rule, and upserts them as PENDING drafts for a manager to review
    //  and approve. NEVER overwrites already-Approved rows.
    //
    //  POLICY DEFAULTS — confirm with your CPA / labor policy (overridable via request):
    //   - Unpaid meal break of 60 min deducted for shifts longer than 6 hours.
    //   - Hours beyond 8 on a regular day are Overtime.
    //   - Saturday/Sunday treated as rest days (shift schedules could refine this later).
    //   - Night differential = hours worked within 22:00–06:00 (additive premium).
    // ─────────────────────────────────────────────────────────────
    private function generateDraft($input)
    {
        if (!$this->canManage()) { $this->deny(); }

        $start = trim($input['start_date'] ?? '');
        $end   = trim($input['end_date'] ?? '');
        if ($start === '' || $end === '' || strtotime($start) === false || strtotime($end) === false) {
            echo json_encode(['success' => false, 'error' => 'Valid start_date and end_date are required.']);
            return;
        }

        $breakMinutes        = isset($input['break_minutes']) ? max(0, (int)$input['break_minutes']) : 60;
        $breakThresholdHours = 6;
        $regularDailyHours   = 8;

        // Holidays in range: date => type.
        $holidays = [];
        $hStmt = $this->pdo->prepare("SELECT `holiday_date`, `type` FROM `holiday_calendar` WHERE `tenant_id` = ? AND `holiday_date` BETWEEN ? AND ?");
        $hStmt->execute([$this->tenantId, $start, $end]);
        while ($h = $hStmt->fetch(PDO::FETCH_ASSOC)) { $holidays[$h['holiday_date']] = $h['type']; }

        // Attendance punches -> resolve to users.id (timesheets.employee_id).
        $sql = "SELECT u.`id` AS emp_id, a.`time_in`, a.`time_out`
                FROM `attendance` a
                JOIN `users` u ON LOWER(a.`employee_email`) = LOWER(u.`email`) AND u.`tenant_id` = a.`tenant_id`
                WHERE a.`tenant_id` = ? AND a.`time_in` IS NOT NULL AND a.`time_out` IS NOT NULL
                AND DATE(a.`time_in`) BETWEEN ? AND ?";
        $params = [$this->tenantId, $start, $end];
        if (!empty($input['employee_id'])) { $sql .= " AND u.`id` = ?"; $params[] = (int)$input['employee_id']; }
        $aStmt = $this->pdo->prepare($sql);
        $aStmt->execute($params);

        $upsert = $this->pdo->prepare(
            "INSERT INTO `timesheets`
                (`tenant_id`, `employee_id`, `timesheet_date`, `regular_hours`, `overtime_hours`,
                 `rest_day_hours`, `special_day_hours`, `regular_holiday_hours`, `night_diff_hours`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
             ON DUPLICATE KEY UPDATE
                `regular_hours` = VALUES(`regular_hours`), `overtime_hours` = VALUES(`overtime_hours`),
                `rest_day_hours` = VALUES(`rest_day_hours`), `special_day_hours` = VALUES(`special_day_hours`),
                `regular_holiday_hours` = VALUES(`regular_holiday_hours`), `night_diff_hours` = VALUES(`night_diff_hours`),
                `status` = 'Pending'"
        );
        $approvedChk = $this->pdo->prepare("SELECT `status` FROM `timesheets` WHERE `tenant_id` = ? AND `employee_id` = ? AND `timesheet_date` = ?");

        $drafted = 0; $skippedApproved = 0;
        while ($row = $aStmt->fetch(PDO::FETCH_ASSOC)) {
            $tin  = strtotime($row['time_in']);
            $tout = strtotime($row['time_out']);
            if ($tout <= $tin) { continue; } // ignore bad/incomplete punches
            $date = date('Y-m-d', $tin);

            // Never overwrite a manager-approved day.
            $approvedChk->execute([$this->tenantId, $row['emp_id'], $date]);
            if ($approvedChk->fetchColumn() === 'Approved') { $skippedApproved++; continue; }

            $rawHours = ($tout - $tin) / 3600.0;
            $worked = ($rawHours > $breakThresholdHours) ? max(0, $rawHours - ($breakMinutes / 60.0)) : $rawHours;
            $worked = round($worked, 2);
            $nd = round($this->nightDiffHours($tin, $tout), 2);

            $reg = $ot = $rest = $spec = $hol = 0.0;
            $type = $holidays[$date] ?? null;
            $dow = (int)date('N', $tin);
            if ($type === 'Regular Holiday') {
                $hol = $worked;
            } elseif ($type === 'Special Non-Working') {
                $spec = $worked;
            } elseif ($dow >= 6) {
                $rest = $worked;
            } else {
                $reg = min($worked, $regularDailyHours);
                $ot  = max(0, $worked - $regularDailyHours);
            }

            $upsert->execute([$this->tenantId, $row['emp_id'], $date, $reg, $ot, $rest, $spec, $hol, $nd]);
            $drafted++;
        }

        echo json_encode([
            'success'          => true,
            'drafted'          => $drafted,
            'skipped_approved' => $skippedApproved,
            'note'             => 'Draft timesheets created as Pending. Review and approve before payroll. Break/OT/rest-day rules are policy defaults — confirm with your policy/CPA.',
        ]);
    }

    /** Hours worked within the 22:00–06:00 night-differential window (15-min resolution). */
    private function nightDiffHours($tin, $tout)
    {
        $nd = 0.0;
        for ($cursor = $tin; $cursor < $tout; $cursor += 900) {
            $next = min($cursor + 900, $tout);
            $mid  = (int)date('G', (int)(($cursor + $next) / 2));
            if ($mid >= 22 || $mid < 6) { $nd += ($next - $cursor) / 3600.0; }
        }
        return $nd;
    }

    // ── Holiday calendar CRUD (feeds the auto-draft classification) ──

    private function listHolidays()
    {
        if (!$this->canView()) { $this->deny(); }
        $start = $_GET['start_date'] ?? date('Y-01-01');
        $end   = $_GET['end_date'] ?? date('Y-12-31');
        $stmt = $this->pdo->prepare("SELECT * FROM `holiday_calendar` WHERE `tenant_id` = ? AND `holiday_date` BETWEEN ? AND ? ORDER BY `holiday_date` ASC");
        $stmt->execute([$this->tenantId, $start, $end]);
        echo json_encode(['success' => true, 'holidays' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function saveHoliday($input)
    {
        if (!$this->canManage()) { $this->deny(); }
        $date = trim($input['holiday_date'] ?? '');
        $name = trim($input['name'] ?? '');
        $type = in_array($input['type'] ?? 'Regular Holiday', ['Regular Holiday', 'Special Non-Working'], true) ? $input['type'] : 'Regular Holiday';
        if ($date === '' || strtotime($date) === false || $name === '') {
            echo json_encode(['success' => false, 'error' => 'holiday_date and name are required.']);
            return;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO `holiday_calendar` (`tenant_id`, `holiday_date`, `name`, `type`) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `type` = VALUES(`type`)"
        );
        $stmt->execute([$this->tenantId, $date, $name, $type]);
        echo json_encode(['success' => true, 'message' => 'Holiday saved.']);
    }

    private function deleteHoliday($input)
    {
        if (!$this->canManage()) { $this->deny(); }
        $id = (int)($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Holiday id required']); return; }
        $stmt = $this->pdo->prepare("DELETE FROM `holiday_calendar` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$id, $this->tenantId]);
        echo json_encode(['success' => true, 'message' => 'Holiday deleted.']);
    }
}
