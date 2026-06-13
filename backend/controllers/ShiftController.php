<?php

class ShiftController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser();
        $this->tenantId = $this->currentUser['tenant_id'] ?? $_SESSION['tenant_id'] ?? '1';
    }

    public function handleRequest($action)
    {
        // Require logged-in user
        if (!$this->currentUser) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        switch ($action) {
            case 'fetch_shift_types':
                $this->fetchShiftTypes();
                break;
            case 'create_shift_type':
                $this->createShiftType();
                break;
            case 'fetch_roster':
                $this->fetchRoster();
                break;
            case 'publish_roster':
                $this->publishRoster();
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
                break;
        }
    }

    private function fetchShiftTypes()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shifts WHERE tenant_id = ? ORDER BY start_time ASC");
        $stmt->execute([$this->tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    private function createShiftType()
    {
        if (!hasPermission('shifts.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? '';
        $start = $input['start_time'] ?? '';
        $end = $input['end_time'] ?? '';

        if (!$name || !$start || !$end) {
            echo json_encode(['success' => false, 'error' => 'Missing fields']);
            return;
        }

        $stmt = $this->pdo->prepare("INSERT INTO shifts (tenant_id, name, start_time, end_time) VALUES (?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $name, $start, $end]);
        echo json_encode(['success' => true]);
    }

    private function fetchRoster()
    {
        $start_date = $_GET['start_date'] ?? date('Y-m-d');
        $end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('+6 days'));

        // Get employees
        $sql = "SELECT id, email, full_name, department, job_title, profile_image FROM users WHERE tenant_id = ? AND employment_status = 'Active'";
        $params = [$this->tenantId];

        // Restrict visibility for Managers
        if (!hasPermission('shifts.manage')) {
            $sql .= " AND (immediate_supervisor = ? OR department_manager = ?)";
            $params[] = $this->currentUser['email'];
            $params[] = $this->currentUser['email'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll();

        // Get their shifts within the date range
        $roster = [];
        if (count($employees) > 0) {
            $userIds = array_column($employees, 'id');
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            
            $shiftSql = "SELECT es.*, s.name, s.start_time, s.end_time 
                         FROM employee_shifts es 
                         JOIN shifts s ON es.shift_id = s.id 
                         WHERE es.tenant_id = ? 
                         AND es.effective_date BETWEEN ? AND ? 
                         AND es.user_id IN ($placeholders)";
            
            $shiftParams = array_merge([$this->tenantId, $start_date, $end_date], $userIds);
            $shiftStmt = $this->pdo->prepare($shiftSql);
            $shiftStmt->execute($shiftParams);
            $assignedShifts = $shiftStmt->fetchAll();

            // Map shifts to users and dates
            $shiftMap = [];
            foreach ($assignedShifts as $s) {
                $shiftMap[$s['user_id']][$s['effective_date']] = [
                    'shift_id' => $s['shift_id'],
                    'name' => $s['name'],
                    'start' => $s['start_time'],
                    'end' => $s['end_time']
                ];
            }

            foreach ($employees as $emp) {
                $roster[] = [
                    'user_id' => $emp['id'],
                    'full_name' => $emp['full_name'],
                    'department' => $emp['department'],
                    'job_title' => $emp['job_title'],
                    'profile_image' => $emp['profile_image'],
                    'shifts' => $shiftMap[$emp['id']] ?? []
                ];
            }
        }

        echo json_encode(['success' => true, 'data' => $roster]);
    }

    private function publishRoster()
    {
        if (!hasPermission('shifts.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $assignments = $input['assignments'] ?? [];
        $notifiedUsers = [];

        $this->pdo->beginTransaction();
        try {
            foreach ($assignments as $a) {
                $userId = $a['user_id'];
                $date = $a['date'];
                $shiftId = $a['shift_id']; // If 0 or null, we delete the shift

                // Delete existing shift for this date
                $delStmt = $this->pdo->prepare("DELETE FROM employee_shifts WHERE tenant_id = ? AND user_id = ? AND effective_date = ?");
                $delStmt->execute([$this->tenantId, $userId, $date]);

                if ($shiftId > 0) {
                    $insStmt = $this->pdo->prepare("INSERT INTO employee_shifts (tenant_id, user_id, shift_id, effective_date) VALUES (?, ?, ?, ?)");
                    $insStmt->execute([$this->tenantId, $userId, $shiftId, $date]);
                    $notifiedUsers[$userId] = true;
                }
            }
            $this->pdo->commit();

            // Send Notifications
            if (!empty($notifiedUsers)) {
                // Get emails for notified users
                $userIds = array_keys($notifiedUsers);
                $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
                $stmt = $this->pdo->prepare("SELECT email, full_name FROM users WHERE id IN ($placeholders)");
                $stmt->execute($userIds);
                $users = $stmt->fetchAll();

                foreach ($users as $u) {
                    sendNotification(
                        $this->pdo, 
                        $this->tenantId, 
                        $u['email'], 
                        "Schedule Updated", 
                        "Your manager has published a new shift schedule for you.", 
                        "info", 
                        "/pages/profile.php"
                    );
                }
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to save roster']);
        }
    }
}
