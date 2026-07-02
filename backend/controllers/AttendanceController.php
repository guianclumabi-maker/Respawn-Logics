<?php

class AttendanceController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser() ?: null;
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id']) ? $this->currentUser['tenant_id'] : ($_SESSION['tenant_id'] ?? null);
    }

    public function handleRequest($action)
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve tenant context']);
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                switch ($action) {
                    case 'status':
                        $this->status();
                        break;
                    case 'timesheet':
                        $this->timesheet();
                        break;
                    case 'pending_approvals':
                        $this->pendingApprovals();
                        break;
                    case 'shifts':
                        $this->shifts();
                        break;
                    case 'report':
                        $this->report();
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Invalid action']);
                        break;
                }
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                switch ($action) {
                    case 'clock_in':
                        $this->clockIn();
                        break;
                    case 'clock_out':
                        $this->clockOut();
                        break;
                    case 'approve_timesheet':
                        if (!hasPermission('attendance.manage')) { http_response_code(403); echo json_encode(['success'=>false, 'error'=>'Denied']); return; }
                $this->approveTimesheet($data);
                        break;
                    case 'import_punches':
                        if (!hasPermission('attendance.manage') && empty($_SESSION['is_super'])) { http_response_code(403); echo json_encode(['success'=>false, 'error'=>'Denied']); return; }
                        $this->importPunches();
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Invalid action']);
                        break;
                }
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    /**
     * Bulk-import attendance punches from a CSV (e.g. a biometric-device export).
     * Matches employees by `employee_id` (biometric ID) or email within the tenant.
     * Accepts columns: employee_id | email, time_in, time_out (optional), date (optional).
     */
    private function importPunches()
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error occurred.']);
            return;
        }

        $handle = fopen($_FILES['file']['tmp_name'], 'r');
        if ($handle === false) {
            echo json_encode(['success' => false, 'error' => 'Could not read the uploaded file.']);
            return;
        }

        $headers = fgetcsv($handle, 0, ',');
        if (!$headers) {
            fclose($handle);
            echo json_encode(['success' => false, 'error' => 'The CSV file appears to be empty.']);
            return;
        }

        // Map normalized header name -> column index
        $idx = [];
        foreach ($headers as $i => $h) {
            $key = strtolower(trim(str_replace([' ', '_', '-'], '', (string)$h)));
            if ($key !== '') $idx[$key] = $i;
        }
        $get = function ($row, array $names) use ($idx) {
            foreach ($names as $n) {
                if (isset($idx[$n], $row[$idx[$n]])) return trim((string)$row[$idx[$n]]);
            }
            return '';
        };

        // Pre-load this tenant's employees: employee_id -> email, and a valid-email set.
        $byEmpId = [];
        $validEmails = [];
        $ustmt = $this->pdo->prepare("SELECT `employee_id`, `email`, `work_email` FROM `users` WHERE `tenant_id` = ?");
        $ustmt->execute([$this->tenantId]);
        while ($u = $ustmt->fetch(PDO::FETCH_ASSOC)) {
            $mail = $u['email'] ?: ($u['work_email'] ?? '');
            if ($mail === '') continue;
            if (!empty($u['employee_id'])) $byEmpId[strtolower((string)$u['employee_id'])] = $mail;
            $validEmails[strtolower($mail)] = true;
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO `attendance` (`tenant_id`, `employee_email`, `time_in`, `time_out`, `status`) VALUES (?, ?, ?, ?, 'Present')"
        );

        $processed = 0;
        $skipped = 0;
        $warnings = [];
        $line = 1;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $line++;
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue;

            $empId   = $get($row, ['employeeid', 'biometricid', 'empid', 'employeenumber']);
            $email   = strtolower($get($row, ['email', 'employeeemail', 'workemail']));
            $timeIn  = $get($row, ['timein', 'clockin', 'checkin', 'in']);
            $timeOut = $get($row, ['timeout', 'clockout', 'checkout', 'out']);
            $date    = $get($row, ['date', 'workdate']);

            // Resolve to a real tenant employee email
            $resolved = '';
            if ($email !== '' && isset($validEmails[$email])) {
                $resolved = $email;
            } elseif ($empId !== '' && isset($byEmpId[strtolower($empId)])) {
                $resolved = $byEmpId[strtolower($empId)];
            }
            if ($resolved === '') {
                $skipped++;
                if (count($warnings) < 50) $warnings[] = "Row {$line}: no matching employee (" . ($empId ?: $email ?: 'blank') . ").";
                continue;
            }

            $ti = $this->normalizeDateTime($timeIn, $date);
            $to = $this->normalizeDateTime($timeOut, $date);
            if ($ti === null && $to === null) {
                $skipped++;
                if (count($warnings) < 50) $warnings[] = "Row {$line}: no valid time_in/time_out value.";
                continue;
            }

            $insert->execute([$this->tenantId, $resolved, $ti, $to]);
            $processed++;
        }
        fclose($handle);

        echo json_encode([
            'success'   => true,
            'processed' => $processed,
            'skipped'   => $skipped,
            'warnings'  => $warnings
        ]);
    }

    /** Parse a time value (optionally combined with a separate date column) into 'Y-m-d H:i:s', or null. */
    private function normalizeDateTime($time, $date = '')
    {
        $time = trim((string)$time);
        if ($time === '') return null;
        $date = trim((string)$date);
        $candidates = [];
        if ($date !== '') $candidates[] = $date . ' ' . $time;
        $candidates[] = $time;
        foreach ($candidates as $c) {
            $ts = strtotime($c);
            if ($ts !== false) return date('Y-m-d H:i:s', $ts);
        }
        return null;
    }

    /**
     * Consolidated attendance report: per employee, per day in the range, fuses
     * attendance (time in/out), approved leaves, and derives absences.
     * Filters: start_date, end_date, department, search (name). Range capped at 31 days.
     */
    private function report()
    {
        if (!hasPermission('attendance.view') && !hasPermission('attendance.manage') && empty($_SESSION['is_super'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Denied']);
            return;
        }

        $start  = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 days'));
        $end    = $_GET['end_date'] ?? date('Y-m-d');
        $dept   = trim($_GET['department'] ?? '');
        $search = trim($_GET['search'] ?? '');

        $s = strtotime($start);
        $e = strtotime($end);
        if ($s === false || $e === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid date range.']);
            return;
        }
        if ($e < $s) { $t = $s; $s = $e; $e = $t; }
        if (($e - $s) / 86400 > 31) { $e = $s + 31 * 86400; } // cap the window
        $start = date('Y-m-d', $s);
        $end   = date('Y-m-d', $e);

        // Employees (with optional department / name filters)
        $sql = "SELECT `id`, `employee_id`, `full_name`, `email`, `department` FROM `users` WHERE `tenant_id` = ?";
        $params = [$this->tenantId];
        if ($dept !== '')   { $sql .= " AND `department` = ?";   $params[] = $dept; }
        if ($search !== '') { $sql .= " AND `full_name` LIKE ?"; $params[] = '%' . $search . '%'; }
        $sql .= " ORDER BY `full_name` ASC";
        $ustmt = $this->pdo->prepare($sql);
        $ustmt->execute($params);
        $employees = $ustmt->fetchAll(PDO::FETCH_ASSOC);

        // Attendance in range: email -> date -> record
        $att = [];
        $astmt = $this->pdo->prepare(
            "SELECT `employee_email`, DATE(`time_in`) AS d, `time_in`, `time_out`, `status`
             FROM `attendance` WHERE `tenant_id` = ? AND DATE(`time_in`) BETWEEN ? AND ?"
        );
        $astmt->execute([$this->tenantId, $start, $end]);
        while ($r = $astmt->fetch(PDO::FETCH_ASSOC)) {
            $att[strtolower($r['employee_email'])][$r['d']] = $r;
        }

        // Approved leaves overlapping the range: email -> date -> leave_type
        $leaveByDay = [];
        $lstmt = $this->pdo->prepare(
            "SELECT `employee_email`, `leave_type`, `start_date`, `end_date`
             FROM `leave_requests` WHERE `tenant_id` = ? AND `status` = 'Approved'
             AND `start_date` <= ? AND `end_date` >= ?"
        );
        $lstmt->execute([$this->tenantId, $end, $start]);
        while ($lr = $lstmt->fetch(PDO::FETCH_ASSOC)) {
            $ls = max(strtotime($lr['start_date']), $s);
            $le = min(strtotime($lr['end_date']), $e);
            for ($d = $ls; $d <= $le; $d += 86400) {
                $leaveByDay[strtolower($lr['employee_email'])][date('Y-m-d', $d)] = $lr['leave_type'];
            }
        }

        // Build the per-employee-per-day grid
        $rows = [];
        $summary = ['present' => 0, 'late' => 0, 'on_leave' => 0, 'absent' => 0, 'rest_day' => 0];
        foreach ($employees as $emp) {
            $mail = strtolower((string)$emp['email']);
            for ($d = $s; $d <= $e; $d += 86400) {
                $date   = date('Y-m-d', $d);
                $dow    = (int)date('N', $d); // 1=Mon .. 7=Sun
                $status = '';
                $timeIn = null;
                $timeOut = null;
                $detail = null;

                if (isset($att[$mail][$date])) {
                    $rec     = $att[$mail][$date];
                    $timeIn  = $rec['time_in'] ? date('H:i', strtotime($rec['time_in'])) : null;
                    $timeOut = $rec['time_out'] ? date('H:i', strtotime($rec['time_out'])) : null;
                    $isLate  = stripos((string)($rec['status'] ?? ''), 'late') !== false;
                    $status  = $isLate ? 'Late' : 'Present';
                    $isLate ? $summary['late']++ : $summary['present']++;
                } elseif (isset($leaveByDay[$mail][$date])) {
                    $status = 'On Leave';
                    $detail = $leaveByDay[$mail][$date];
                    $summary['on_leave']++;
                } elseif ($dow >= 6) { // Sat/Sun treated as rest day (default; refine with shifts later)
                    $status = 'Rest Day';
                    $summary['rest_day']++;
                } else {
                    $status = 'Absent';
                    $summary['absent']++;
                }

                $rows[] = [
                    'employee_id' => $emp['employee_id'],
                    'name'        => $emp['full_name'],
                    'department'  => $emp['department'],
                    'date'        => $date,
                    'status'      => $status,
                    'time_in'     => $timeIn,
                    'time_out'    => $timeOut,
                    'detail'      => $detail,
                ];
            }
        }

        // Distinct departments for the filter dropdown
        $dstmt = $this->pdo->prepare(
            "SELECT DISTINCT `department` FROM `users` WHERE `tenant_id` = ? AND `department` IS NOT NULL AND `department` <> '' ORDER BY `department`"
        );
        $dstmt->execute([$this->tenantId]);

        echo json_encode([
            'success'     => true,
            'start_date'  => $start,
            'end_date'    => $end,
            'departments' => $dstmt->fetchAll(PDO::FETCH_COLUMN),
            'summary'     => $summary,
            'rows'        => $rows,
        ]);
    }

    private function calculateStatus($userId, $timeInStr)
    {
        $nowTime = date('H:i:s', strtotime($timeInStr));
        $todayDate = date('Y-m-d', strtotime($timeInStr));

        $stmt = $this->pdo->prepare("
            SELECT s.start_time, s.id as shift_id
            FROM employee_shifts es
            JOIN shifts s ON es.shift_id = s.id
            WHERE es.user_id = ? AND s.tenant_id = ? AND es.effective_date <= ?
            ORDER BY es.effective_date DESC LIMIT 1
        ");
        $stmt->execute([$userId, $this->tenantId, $todayDate]);
        $shift = $stmt->fetch();

        if ($shift) {
            $lateThreshold = date('H:i:s', strtotime($shift['start_time'] . ' + 10 minutes'));
            return [
                'status' => ($nowTime > $lateThreshold) ? 'Late' : 'On Time',
                'shift_id' => $shift['shift_id']
            ];
        }

        return [
            'status' => ($nowTime > '09:10:00') ? 'Late' : 'On Time',
            'shift_id' => null
        ];
    }

    private function status()
    {
        $today = date('Y-m-d');
        $email = $this->currentUser['email'];

        $stmt = $this->pdo->prepare("SELECT `id`, `employee_email`, `time_in`, `time_out`, `status`, `tenant_id` FROM attendance WHERE employee_email = ? AND tenant_id = ? AND DATE(time_in) = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email, $this->tenantId, $today]);
        $current_log = $stmt->fetch();

        if ($current_log) {
            if (empty($current_log['time_out'])) {
                echo json_encode(['success' => true, 'data' => ['state' => 'in', 'log' => $current_log]]);
                return;
            } else {
                echo json_encode(['success' => true, 'data' => ['state' => 'completed', 'log' => $current_log]]);
                return;
            }
        }
        
        echo json_encode(['success' => true, 'data' => ['state' => 'out', 'log' => null]]);
    }

    private function timesheet()
    {
        $email = $this->currentUser['email'];
        $stmt = $this->pdo->prepare("SELECT `id`, `employee_email`, `time_in`, `time_out`, `status`, `tenant_id` FROM attendance WHERE employee_email = ? AND tenant_id = ? ORDER BY time_in DESC LIMIT 30");
        $stmt->execute([$email, $this->tenantId]);
        $logs = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $logs]);
    }

    private function pendingApprovals()
    {
        if (!hasPermission('attendance.manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            return;
        }

        require_once __DIR__ . '/../services/ScopeResolver.php';
        $scopeClause = ScopeResolver::getScopeWhereClause($this->pdo, $this->currentUser, 'u');

        $stmt = $this->pdo->prepare("
            SELECT a.*, u.full_name, u.department 
            FROM attendance a
            JOIN users u ON a.employee_email = u.email AND a.tenant_id = u.tenant_id
            WHERE a.tenant_id = :tenant_id AND a.manager_approved = 0 AND a.time_out IS NOT NULL
            $scopeClause
            ORDER BY a.time_in DESC
        ");
        $stmt->execute([':tenant_id' => $this->tenantId]);
        $pending = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $pending]);
    }

    private function shifts()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shifts WHERE tenant_id = ?");
        $stmt->execute([$this->tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    private function clockIn()
    {
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $email = $this->currentUser['email'];
        
        $checkStmt = $this->pdo->prepare("SELECT id, time_out FROM attendance WHERE employee_email = ? AND tenant_id = ? AND DATE(time_in) = ? ORDER BY id DESC LIMIT 1");
        $checkStmt->execute([$email, $this->tenantId, $today]);
        $existing = $checkStmt->fetch();
        
        if ($existing && empty($existing['time_out'])) {
            echo json_encode(['success' => false, 'error' => 'Already clocked in.']);
            return;
        }

        $shiftDetails = $this->calculateStatus($this->currentUser['id'], $now);
        
        $stmt = $this->pdo->prepare("INSERT INTO attendance (tenant_id, employee_email, time_in, status, shift_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $email, $now, $shiftDetails['status'], $shiftDetails['shift_id']]);
        
        if (function_exists('logAction')) {
            logAction($email, 'Clock In', "Clocked in at " . date('h:i A', strtotime($now)));
        }
        
        echo json_encode(['success' => true, 'message' => 'Clocked in successfully.']);
    }

    private function clockOut()
    {
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $email = $this->currentUser['email'];
        
        $stmt = $this->pdo->prepare("SELECT id FROM attendance WHERE employee_email = ? AND tenant_id = ? AND DATE(time_in) = ? AND time_out IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email, $this->tenantId, $today]);
        $current_log = $stmt->fetch();
        
        if (!$current_log) {
            echo json_encode(['success' => false, 'error' => 'Not currently clocked in.']);
            return;
        }
        
        $updateStmt = $this->pdo->prepare("UPDATE attendance SET time_out = ? WHERE id = ? AND tenant_id = ?");
        $updateStmt->execute([$now, $current_log['id'], $this->tenantId]);
        
        if (function_exists('logAction')) {
            logAction($email, 'Clock Out', "Clocked out at " . date('h:i A', strtotime($now)));
        }
        
        echo json_encode(['success' => true, 'message' => 'Clocked out successfully.']);
    }

    private function approveTimesheet($data)
    {
        if (!hasPermission('attendance.manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            return;
        }

        $recordId = $data['record_id'] ?? null;
        if (!$recordId) {
            echo json_encode(['success' => false, 'error' => 'Missing record ID']);
            return;
        }

        // Fetch the target user ID to verify scope access
        $checkStmt = $this->pdo->prepare("
            SELECT u.id as target_user_id 
            FROM attendance a 
            JOIN users u ON a.employee_email = u.email AND a.tenant_id = u.tenant_id 
            WHERE a.id = ? AND a.tenant_id = ?
        ");
        $checkStmt->execute([$recordId, $this->tenantId]);
        $target = $checkStmt->fetch();

        if (!$target) {
            echo json_encode(['success' => false, 'error' => 'Record not found']);
            return;
        }

        require_once __DIR__ . '/../services/ScopeResolver.php';
        if (!ScopeResolver::hasScopedAccess($this->pdo, $this->currentUser, (int)$target['target_user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $stmt = $this->pdo->prepare("UPDATE attendance SET manager_approved = 1 WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$recordId, $this->tenantId]);
        
        if (function_exists('logAction')) {
            logAction($this->currentUser['email'], 'Timesheet Approved', "Approved attendance record $recordId");
        }
        
        echo json_encode(['success' => true, 'message' => 'Timesheet approved.']);
    }
}
