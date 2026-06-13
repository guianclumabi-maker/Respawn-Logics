<?php

class EmployeeRelationsController
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
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             $action = $input['action'] ?? $action;
        }

        switch ($action) {
            case 'dashboard':
                $this->getDashboard();
                break;
            case 'add':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->addCase($input);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid method']);
                }
                break;
            case 'update_stage':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->updateStage($input);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid method']);
                }
                break;
            case 'update_rating':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->updateRating($input);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid method']);
                }
                break;
            case 'delete':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->deleteCase($input);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid method']);
                }
                break;
            default:
                if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($action)) {
                    $this->getAllCases();
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
                }
                break;
        }
    }

    private function getDashboard()
    {
        try {
            $total = (int)$this->pdo->query("SELECT COUNT(*) FROM `employee_relations` WHERE `tenant_id` = '{$this->tenantId}'")->fetchColumn();
            $interviews = (int)$this->pdo->query("SELECT COUNT(*) FROM `employee_relations` WHERE `tenant_id` = '{$this->tenantId}' AND `stage` IN ('Review', 'Investigation')")->fetchColumn();
            $offers = (int)$this->pdo->query("SELECT COUNT(*) FROM `employee_relations` WHERE `tenant_id` = '{$this->tenantId}' AND `stage` = 'Resolution Pending'")->fetchColumn();
            $hired = (int)$this->pdo->query("SELECT COUNT(*) FROM `employee_relations` WHERE `tenant_id` = '{$this->tenantId}' AND `stage` = 'Resolved'")->fetchColumn();
            
            $screened = (int)$this->pdo->query("SELECT COUNT(*) FROM `employee_relations` WHERE `tenant_id` = '{$this->tenantId}' AND `stage` NOT IN ('Reported', 'Resolved')")->fetchColumn();
            $f2f = (int)$this->pdo->query("SELECT COUNT(*) FROM `employee_relations` WHERE `tenant_id` = '{$this->tenantId}' AND `stage` = 'Investigation'")->fetchColumn();
            
            $stmt_act = $this->pdo->query("SELECT `name`, `stage`, `applied` FROM `employee_relations` WHERE `tenant_id` = '{$this->tenantId}' ORDER BY `id` DESC LIMIT 4");
            $activities = $stmt_act->fetchAll();
            
            // Format activities
            foreach ($activities as &$act) {
                $act['time'] = 'Recent action';
                $act['action'] = 'filed issue:';
                $act['type'] = 'apply';
                $act['role'] = 'HR Cases';
                
                $st = $act['stage'];
                if ($st === 'Resolved') {
                    $act['action'] = 'resolved case:';
                    $act['type'] = 'offer';
                } else if ($st === 'Resolution Pending') {
                    $act['action'] = 'pending resolution for';
                    $act['type'] = 'offer';
                } else if ($st !== 'Reported') {
                    $act['action'] = 'advanced case to';
                    $act['type'] = 'advance';
                    $act['role'] = $st;
                }
            }
            
            echo json_encode([
                'success' => true,
                'total_cases' => $total,
                'interviews_scheduled' => $interviews, 
                'offers_extended' => $offers,
                'resolved_count' => $hired,
                'screened_count' => $screened,
                'f2f_count' => $f2f,
                'activities' => $activities
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    private function getAllCases()
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `employee_relations` WHERE `tenant_id` = ? ORDER BY `id` DESC");
            $stmt->execute([$this->tenantId]);
            $cases = $stmt->fetchAll();
            
            // Convert comma-separated tags to array
            foreach ($cases as &$c) {
                $c['id'] = (int)$c['id'];
                $c['rating'] = (int)$c['rating'];
                $c['tags'] = !empty($c['tags']) ? explode(',', $c['tags']) : [];
                $c['applied'] = date('j F, Y', strtotime($c['applied']));
            }
            
            echo json_encode(['success' => true, 'cases' => $cases]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    private function addCase($input)
    {
        try {
            $name = trim($input['name'] ?? '');
            $stage = trim($input['stage'] ?? 'Reported');
            $rating = (int)($input['rating'] ?? 0);
            $tags = isset($input['tags']) ? implode(',', $input['tags']) : '';
            $applied = date('Y-m-d'); // default to today
            
            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Case details/name is required']);
                return;
            }
            
            $stmt = $this->pdo->prepare("INSERT INTO `employee_relations` (`tenant_id`, `name`, `stage`, `applied`, `rating`, `tags`) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$this->tenantId, $name, $stage, $applied, $rating, $tags]);
            $newId = (int)$this->pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'case' => [
                    'id' => $newId,
                    'name' => $name,
                    'stage' => $stage,
                    'applied' => date('j F, Y', strtotime($applied)),
                    'rating' => $rating,
                    'tags' => !empty($tags) ? explode(',', $tags) : []
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    private function updateStage($input)
    {
        try {
            $id = (int)($input['id'] ?? 0);
            $stage = trim($input['stage'] ?? '');
            
            if (!$id || empty($stage)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing ID or stage']);
                return;
            }
            
            $stmt = $this->pdo->prepare("UPDATE `employee_relations` SET `stage` = ? WHERE `id` = ? AND `tenant_id` = ?");
            $stmt->execute([$stage, $id, $this->tenantId]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    private function updateRating($input)
    {
        try {
            $id = (int)($input['id'] ?? 0);
            $rating = (int)($input['rating'] ?? 0);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing case ID']);
                return;
            }
            
            $stmt = $this->pdo->prepare("UPDATE `employee_relations` SET `rating` = ? WHERE `id` = ? AND `tenant_id` = ?");
            $stmt->execute([$rating, $id, $this->tenantId]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    private function deleteCase($input)
    {
        try {
            $id = (int)($input['id'] ?? 0);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing case ID']);
                return;
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM `employee_relations` WHERE `id` = ? AND `tenant_id` = ?");
            $stmt->execute([$id, $this->tenantId]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }
}
