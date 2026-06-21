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

        $stmt = $this->pdo->prepare("SELECT * FROM attendance WHERE employee_email = ? AND tenant_id = ? AND DATE(time_in) = ? ORDER BY id DESC LIMIT 1");
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
        $stmt = $this->pdo->prepare("SELECT * FROM attendance WHERE employee_email = ? AND tenant_id = ? ORDER BY time_in DESC LIMIT 30");
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

        $stmt = $this->pdo->prepare("
            SELECT a.*, u.full_name, u.department 
            FROM attendance a
            JOIN users u ON a.employee_email = u.email
            WHERE a.tenant_id = ? AND a.manager_approved = 0 AND a.time_out IS NOT NULL
            ORDER BY a.time_in DESC
        ");
        $stmt->execute([$this->tenantId]);
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

        $stmt = $this->pdo->prepare("UPDATE attendance SET manager_approved = 1 WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$recordId, $this->tenantId]);
        
        if (function_exists('logAction')) {
            logAction($this->currentUser['email'], 'Timesheet Approved', "Approved attendance record $recordId");
        }
        
        echo json_encode(['success' => true, 'message' => 'Timesheet approved.']);
    }
}
