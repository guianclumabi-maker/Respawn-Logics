<?php

class PerformanceController
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

    private function canManagePerformance()
    {
        return hasPermission('performance.manage') || hasPermission('performance.manage_team');
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
                case 'cycles':
                    $stmt = $this->pdo->prepare("SELECT * FROM `performance_cycles` WHERE `tenant_id` = ? ORDER BY `start_date` DESC");
                    $stmt->execute([$this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'create_cycle':
                    if (!hasPermission('performance.manage')) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $name = $input['name'] ?? '';
                    $start = $input['start_date'] ?? '';
                    $end = $input['end_date'] ?? '';
                    
                    $stmt = $this->pdo->prepare("INSERT INTO `performance_cycles` (`tenant_id`, `name`, `start_date`, `end_date`, `status`) VALUES (?, ?, ?, ?, 'Active')");
                    $stmt->execute([$this->tenantId, $name, $start, $end]);
                    echo json_encode(['success' => true]);
                    break;

                case 'initialize_reviews':
                    if (!hasPermission('performance.manage')) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $cycleId = $input['cycle_id'] ?? 0;
                    
                    $chk = $this->pdo->prepare("SELECT id FROM performance_cycles WHERE id = ? AND tenant_id = ?");
                    $chk->execute([$cycleId, $this->tenantId]);
                    if (!$chk->fetch()) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Cycle not found']);
                        return;
                    }
                    
                    $empStmt = $this->pdo->prepare("SELECT id, manager_id FROM `users` WHERE `tenant_id` = ? AND `employment_status` = 'Active' AND `manager_id` IS NOT NULL");
                    $empStmt->execute([$this->tenantId]);
                    $employees = $empStmt->fetchAll();

                    foreach ($employees as $emp) {
                        try {
                            $stmt = $this->pdo->prepare("INSERT INTO `performance_reviews` (`tenant_id`, `cycle_id`, `employee_id`, `reviewer_id`) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$this->tenantId, $cycleId, $emp['id'], $emp['manager_id']]);
                        } catch (Exception $e) { /* Ignore duplicates */ }
                    }
                    echo json_encode(['success' => true]);
                    break;

                case 'my_goals':
                    $stmt = $this->pdo->prepare("SELECT * FROM `performance_goals` WHERE `employee_id` = ? AND `tenant_id` = ? ORDER BY `id` DESC");
                    $stmt->execute([$this->currentUser['id'], $this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'save_goal':
                    $title = $input['title'] ?? '';
                    $desc = $input['description'] ?? '';
                    $weight = intval($input['weight'] ?? 0);
                    $completion = intval($input['completion_percentage'] ?? 0);
                    $status = $input['status'] ?? 'On Track';
                    $id = $input['id'] ?? 0;

                    if ($id > 0) {
                        $stmt = $this->pdo->prepare("UPDATE `performance_goals` SET `title`=?, `description`=?, `weight`=?, `completion_percentage`=?, `status`=? WHERE `id`=? AND `employee_id`=? AND `tenant_id`=?");
                        $stmt->execute([$title, $desc, $weight, $completion, $status, $id, $this->currentUser['id'], $this->tenantId]);
                    } else {
                        $stmt = $this->pdo->prepare("INSERT INTO `performance_goals` (`tenant_id`, `employee_id`, `title`, `description`, `weight`, `completion_percentage`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$this->tenantId, $this->currentUser['id'], $title, $desc, $weight, $completion, $status]);
                    }
                    echo json_encode(['success' => true]);
                    break;

                case 'my_reviews': 
                    $stmt = $this->pdo->prepare("
                        SELECT pr.*, pc.name as cycle_name, u.full_name as manager_name 
                        FROM `performance_reviews` pr
                        JOIN `performance_cycles` pc ON pr.cycle_id = pc.id
                        LEFT JOIN `users` u ON pr.reviewer_id = u.id
                        WHERE pr.employee_id = ? AND pr.tenant_id = ?
                    ");
                    $stmt->execute([$this->currentUser['id'], $this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'team_reviews': 
                    if (!$this->canManagePerformance()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    
                    require_once __DIR__ . '/../services/ScopeResolver.php';
                    $scopeClause = ScopeResolver::getScopeWhereClause($this->pdo, $this->currentUser, 'u');
                    
                    $stmt = $this->pdo->prepare("
                        SELECT pr.*, pc.name as cycle_name, u.full_name as employee_name, u.job_title 
                        FROM `performance_reviews` pr
                        JOIN `performance_cycles` pc ON pr.cycle_id = pc.id
                        JOIN `users` u ON pr.employee_id = u.id AND pr.tenant_id = u.tenant_id
                        WHERE pr.tenant_id = :tenant_id
                        $scopeClause
                    ");
                    $stmt->execute([':tenant_id' => $this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'submit_self_eval':
                    $reviewId = $input['review_id'] ?? 0;
                    $comments = $input['self_comments'] ?? '';
                    
                    $stmt = $this->pdo->prepare("UPDATE `performance_reviews` SET `self_comments` = ?, `status` = 'Pending Manager' WHERE `id` = ? AND `employee_id` = ? AND `tenant_id` = ? AND `status` = 'Pending Self-Evaluation'");
                    $stmt->execute([$comments, $reviewId, $this->currentUser['id'], $this->tenantId]);
                    echo json_encode(['success' => true]);
                    break;

                case 'submit_manager_eval':
                    if (!$this->canManagePerformance()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    
                    $reviewId = $input['review_id'] ?? 0;

                    $checkStmt = $this->pdo->prepare("SELECT employee_id FROM performance_reviews WHERE id = ? AND tenant_id = ?");
                    $checkStmt->execute([$reviewId, $this->tenantId]);
                    $targetEmpId = $checkStmt->fetchColumn();

                    require_once __DIR__ . '/../services/ScopeResolver.php';
                    if (!$targetEmpId || !ScopeResolver::hasScopedAccess($this->pdo, $this->currentUser, (int)$targetEmpId)) {
                        echo json_encode(['success' => false, 'error' => 'Unauthorized']); return;
                    }

                    $comments = $input['manager_comments'] ?? '';
                    $score = floatval($input['overall_score_1_to_5'] ?? 0);
                    $perf = intval($input['nine_box_performance'] ?? 0);
                    $pot = intval($input['nine_box_potential'] ?? 0);

                    $stmt = $this->pdo->prepare("UPDATE `performance_reviews` SET `manager_comments` = ?, `overall_score_1_to_5` = ?, `nine_box_performance` = ?, `nine_box_potential` = ?, `status` = 'Finalized', `reviewer_id` = ? WHERE `id` = ? AND `tenant_id` = ?");
                    $stmt->execute([$comments, $score, $perf, $pot, $this->currentUser['id'], $reviewId, $this->tenantId]);
                    
                    echo json_encode(['success' => true]);
                    break;

                case 'nine_box_data':
                    if (!hasPermission('performance.manage')) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $cycleId = intval($_GET['cycle_id'] ?? 0);
                    
                    $chk = $this->pdo->prepare("SELECT id FROM performance_cycles WHERE id = ? AND tenant_id = ?");
                    $chk->execute([$cycleId, $this->tenantId]);
                    if (!$chk->fetch()) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Cycle not found']);
                        return;
                    }
                    
                    $stmt = $this->pdo->prepare("
                        SELECT pr.id, pr.employee_id, u.full_name, u.profile_image, pr.nine_box_performance as perf, pr.nine_box_potential as pot, pr.overall_score_1_to_5
                        FROM `performance_reviews` pr
                        JOIN `users` u ON pr.employee_id = u.id
                        WHERE pr.cycle_id = ? AND pr.tenant_id = ? AND pr.status = 'Finalized'
                    ");
                    $stmt->execute([$cycleId, $this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown action']);
                    break;
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }
}
