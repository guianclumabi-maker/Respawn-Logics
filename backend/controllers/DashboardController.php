<?php

class DashboardController {
    public function handleRequest($action) {
        $user = getCurrentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        switch ($action) {
            case 'get_stats':
                $this->getStats($user);
                break;
            case 'toggle_task':
                $this->toggleTask($user);
                break;
            case 'add_task':
                $this->addTask($user);
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    }

    private function getStats($user) {
        global $pdo;
        $email = $user['email'];
        $tenantId = $user['tenant_id'];
        $today = date('Y-m-d');

        $clocked_in_today = false;
        $clock_time = '--:--';
        $total_hours = 0;
        $pending_leaves = 0;
        $active_tasks_count = 0;
        $todo_list = [];

        try {
            // Check attendance for today
            $stmt = $pdo->prepare("SELECT * FROM `attendance` WHERE `employee_email` = ? AND `tenant_id` = ? AND DATE(`time_in`) = ?");
            $stmt->execute([$email, $tenantId, $today]);
            $att = $stmt->fetch();
            if ($att) {
                $clocked_in_today = true;
                $clock_time = date('h:i A', strtotime($att['time_in']));
                if (!empty($att['time_out'])) {
                    $clocked_in_today = false; // Clocked out
                }
            }

            // Weekly Working Hours count
            $stmt = $pdo->prepare("SELECT * FROM `attendance` WHERE `employee_email` = ? AND `tenant_id` = ? AND `time_in` >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute([$email, $tenantId]);
            $week_logs = $stmt->fetchAll();
            foreach ($week_logs as $log) {
                if (!empty($log['time_in']) && !empty($log['time_out'])) {
                    $diff = strtotime($log['time_out']) - strtotime($log['time_in']);
                    $total_hours += round($diff / 3600, 2);
                }
            }

            // Pending Leaves Count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `leave_requests` WHERE `employee_email` = ? AND `tenant_id` = ? AND `status` = 'Pending'");
            $stmt->execute([$email, $tenantId]);
            $pending_leaves = (int)$stmt->fetchColumn();

            // Active Tasks Count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `employee_tasks` WHERE `employee_email` = ? AND `tenant_id` = ? AND `is_completed` = 0");
            $stmt->execute([$email, $tenantId]);
            $active_tasks_count = (int)$stmt->fetchColumn();

            // Fetch todo list
            $stmt = $pdo->prepare("SELECT id, task_name, task_description, is_completed FROM `employee_tasks` WHERE `employee_email` = ? AND `tenant_id` = ? ORDER BY `id` DESC LIMIT 10");
            $stmt->execute([$email, $tenantId]);
            $todo_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            // Ignore DB errors and return 0s
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'clocked_in_today' => $clocked_in_today,
                'clock_time' => $clock_time,
                'total_hours' => $total_hours,
                'pending_leaves' => $pending_leaves,
                'active_tasks_count' => $active_tasks_count,
                'todo_list' => $todo_list
            ]
        ]);
    }

    private function toggleTask($user) {
        global $pdo;
        $email = $user['email'];
        $tenantId = $user['tenant_id'];

        $input = json_decode(file_get_contents('php://input'), true);
        $task_id = intval($input['task_id'] ?? 0);

        if (!$task_id) {
            echo json_encode(['success' => false, 'error' => 'Missing task ID']);
            return;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM `employee_tasks` WHERE `id` = ? AND `employee_email` = ? AND `tenant_id` = ?");
            $stmt->execute([$task_id, $email, $tenantId]);
            $task = $stmt->fetch();

            if ($task) {
                $new_val = $task['is_completed'] ? 0 : 1;
                $comp_at = $new_val ? date('Y-m-d H:i:s') : null;

                $stmt = $pdo->prepare("UPDATE `employee_tasks` SET `is_completed` = ?, `completed_at` = ? WHERE `id` = ? AND `tenant_id` = ?");
                $stmt->execute([$new_val, $comp_at, $task_id, $tenantId]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Task not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    private function addTask($user) {
        global $pdo;
        $email = $user['email'];
        $tenantId = $user['tenant_id'];

        $input = json_decode(file_get_contents('php://input'), true);
        $task_name = trim($input['task_name'] ?? '');
        $task_desc = trim($input['task_description'] ?? '');

        if (empty($task_name)) {
            echo json_encode(['success' => false, 'error' => 'Task name is required']);
            return;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO `employee_tasks` (`tenant_id`, `employee_email`, `task_name`, `task_description`, `is_completed`) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$tenantId, $email, $task_name, $task_desc]);
            
            $newId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'task' => [
                'id' => $newId,
                'task_name' => $task_name,
                'task_description' => $task_desc,
                'is_completed' => 0
            ]]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }
}
