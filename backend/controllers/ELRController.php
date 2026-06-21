<?php

class ELRController
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
        // Global view permission check for all ELR endpoints
        requirePermission('elr.view');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
             $action = $input['action'] ?? $action;
        }

        try {
            switch ($action) {
                case 'cases':
                    $this->getCases();
                    break;
                case 'case':
                    $this->getCase();
                    break;
                case 'case_types':
                    $this->getCaseTypes();
                    break;
                case 'analytics':
                    $this->getAnalytics();
                    break;
                case 'create_case':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->createCase($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;
                case 'update_case':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->updateCase($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
                    break;
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    private function addTimelineEvent($caseId, $eventType, $description, $actor = null, $oldValue = null, $newValue = null) {
        $stmt = $this->pdo->prepare("INSERT INTO `elr_case_timeline` (`case_id`, `event_type`, `description`, `actor`, `old_value`, `new_value`) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$caseId, $eventType, $description, $actor, $oldValue, $newValue]);
    }

    private function getCases() {
        $userRole = strtolower($this->currentUser['role'] ?? '');
        $userEmployeeId = $this->currentUser['employee_id'] ?? '';
        
        $sql = "SELECT c.*, t.name as case_type_name 
                FROM `elr_cases` c 
                LEFT JOIN `elr_case_types` t ON c.case_type_id = t.id 
                WHERE c.tenant_id = :tenant_id";
        $params = [':tenant_id' => $this->tenantId];
        
        // Confidentiality Filter
        if ($userRole !== 'admin' && $userRole !== 'manager') {
            $sql .= " AND (c.is_confidential = 0 OR c.investigator_id = :user_emp_id OR JSON_CONTAINS(c.restricted_access_roles, :user_role))";
            $params[':user_emp_id'] = $userEmployeeId;
            $params[':user_role'] = '"' . $userRole . '"';
        }
        
        $sql .= " ORDER BY c.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'cases' => $cases]);
    }

    private function getCase() {
        $userRole = strtolower($this->currentUser['role'] ?? '');
        $userEmployeeId = $this->currentUser['employee_id'] ?? '';
        $id = (int)($_GET['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Case ID required']);
            return;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT c.*, t.name as case_type_name 
            FROM `elr_cases` c 
            LEFT JOIN `elr_case_types` t ON c.case_type_id = t.id 
            WHERE c.id = ? AND c.tenant_id = ?
        ");
        $stmt->execute([$id, $this->tenantId]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$case) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Case not found or access denied']);
            return;
        }
        
        // Confidentiality Check
        if ($case['is_confidential']) {
            $allowed = ($userRole === 'admin' || $userRole === 'manager' || $case['investigator_id'] === $userEmployeeId);
            if (!$allowed && !empty($case['restricted_access_roles'])) {
                $roles = json_decode($case['restricted_access_roles'], true);
                if (is_array($roles) && in_array($userRole, $roles)) {
                    $allowed = true;
                }
            }
            if (!$allowed) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Confidential case. Access denied.']);
                return;
            }
        }
        
        // Fetch timeline
        $t_stmt = $this->pdo->prepare("SELECT * FROM `elr_case_timeline` WHERE case_id = ? ORDER BY created_at DESC");
        $t_stmt->execute([$id]);
        $timeline = $t_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'case' => $case,
            'timeline' => $timeline
        ]);
    }

    private function getCaseTypes() {
        $stmt = $this->pdo->prepare("SELECT * FROM `elr_case_types` WHERE tenant_id = ?");
        $stmt->execute([$this->tenantId]);
        echo json_encode(['success' => true, 'case_types' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function getAnalytics() {
        // Case Volume Trend (Last 6 Months)
        $trendSql = "
            SELECT DATE_FORMAT(created_at, '%b') as month, COUNT(*) as count 
            FROM elr_cases 
            WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b')
            ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
        ";
        $stmt = $this->pdo->prepare($trendSql);
        $stmt->execute([$this->tenantId]);
        $trendData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Channels / Case Types
        $typeSql = "
            SELECT t.name as source, COUNT(c.id) as applications
            FROM elr_cases c
            JOIN elr_case_types t ON c.case_type_id = t.id
            WHERE c.tenant_id = ?
            GROUP BY t.id
            ORDER BY applications DESC
        ";
        $stmt = $this->pdo->prepare($typeSql);
        $stmt->execute([$this->tenantId]);
        $channelData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate percentages
        $totalCases = array_sum(array_column($channelData, 'applications'));
        $colors = ['#3b82f6', '#8b5cf6', '#10b981', '#ec4899', '#f59e0b', '#06b6d4'];
        foreach ($channelData as $idx => &$c) {
            $c['percentage'] = $totalCases > 0 ? round(($c['applications'] / $totalCases) * 100) : 0;
            $c['color'] = $colors[$idx % count($colors)];
        }
        
        echo json_encode([
            'success' => true, 
            'trend' => $trendData,
            'channels' => $channelData
        ]);
    }

    private function createCase($input) {
        $userEmployeeId = $this->currentUser['employee_id'] ?? '';
        requirePermission('elr.investigate');

        // Generate Case Number (e.g. ELR-2026-0001)
        $year = date('Y');
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `elr_cases` WHERE tenant_id = ? AND YEAR(created_at) = ?");
        $countStmt->execute([$this->tenantId, $year]);
        $count = $countStmt->fetchColumn() + 1;
        $caseNumber = sprintf("ELR-%s-%04d", $year, $count);
        
        $empId = trim($input['employee_id'] ?? '');
        $dept = trim($input['department'] ?? '');
        $typeId = (int)($input['case_type_id'] ?? 0);
        $severity = trim($input['severity'] ?? 'Low');
        $desc = trim($input['description'] ?? '');
        
        $reportedBy = trim($input['reported_by_employee_id'] ?? '');
        $anonymous = !empty($input['anonymous_report']) ? 1 : 0;
        $isConfidential = !empty($input['is_confidential']) ? 1 : 0;
        
        if (!$typeId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Case Type is required']);
            return;
        }
        
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO `elr_cases` (
                    `tenant_id`, `case_number`, `employee_id`, `department`, `case_type_id`, 
                    `severity`, `status`, `created_by`, `description`, `reported_by_employee_id`, 
                    `anonymous_report`, `is_confidential`
                ) VALUES (?, ?, ?, ?, ?, ?, 'Open', ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->tenantId, $caseNumber, $empId, $dept, $typeId, 
                $severity, $userEmployeeId, $desc, $reportedBy, 
                $anonymous, $isConfidential
            ]);
            
            $newId = $this->pdo->lastInsertId();
            
            $this->addTimelineEvent($newId, 'Case Created', "Case $caseNumber was officially opened.", $userEmployeeId, null, 'Open');
            
            $this->pdo->commit();
            
            echo json_encode(['success' => true, 'case_id' => $newId, 'case_number' => $caseNumber]);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    private function updateCase($input) {
        $userEmployeeId = $this->currentUser['employee_id'] ?? '';
        requirePermission('elr.investigate');
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Case ID is required']);
            return;
        }

        try {
            // Fetch existing to compare for timeline
            $stmt = $this->pdo->prepare("SELECT * FROM `elr_cases` WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $this->tenantId]);
            $case = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$case) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Case not found']);
                return;
            }

            $updates = [];
            $params = [];
            $timelineEvents = [];

            if (isset($input['status']) && $input['status'] !== $case['status']) {
                $newStatus = $input['status'];
                $oldStatus = $case['status'];
                
                // Enforce legal state transitions
                $allowedTransitions = [
                    'Open' => ['Under Review'],
                    'Under Review' => ['Investigating', 'Closed'],
                    'Investigating' => ['Pending Approval', 'Resolved', 'Closed'],
                    'Pending Approval' => ['Resolved', 'Investigating'],
                    'Resolved' => ['Closed'],
                    'Closed' => [] // Terminal
                ];
                
                if (!isset($allowedTransitions[$oldStatus]) || !in_array($newStatus, $allowedTransitions[$oldStatus])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => "Illegal status transition from $oldStatus to $newStatus"]);
                    return;
                }
                
                if ($newStatus === 'Closed') {
                    requirePermission('elr.close');
                }
                
                $updates[] = "`status` = ?";
                $params[] = $newStatus;
                
                // Map the overarching event type for the timeline
                $eventType = 'Status Changed';
                if ($newStatus === 'Closed') {
                    $eventType = 'Case Closed';
                }
                
                $timelineEvents[] = [$eventType, "Status changed to $newStatus", $userEmployeeId, $oldStatus, $newStatus];
                
                if ($newStatus === 'Closed') {
                    $updates[] = "`date_closed` = NOW()";
                }
            }

            if (isset($input['investigator_id']) && $input['investigator_id'] !== $case['investigator_id']) {
                $updates[] = "`investigator_id` = ?";
                $params[] = $input['investigator_id'];
                $timelineEvents[] = ['Investigator Assigned', "Investigator assigned", $userEmployeeId, $case['investigator_id'], $input['investigator_id']];
            }

            if (isset($input['severity']) && $input['severity'] !== $case['severity']) {
                $updates[] = "`severity` = ?";
                $params[] = $input['severity'];
                $timelineEvents[] = ['Severity Changed', "Severity changed to {$input['severity']}", $userEmployeeId, $case['severity'], $input['severity']];
            }

            if (empty($updates)) {
                echo json_encode(['success' => true, 'message' => 'No changes detected']);
                return;
            }

            $params[] = $id;
            $params[] = $this->tenantId;

            $this->pdo->beginTransaction();
            
            $sql = "UPDATE `elr_cases` SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?";
            $upStmt = $this->pdo->prepare($sql);
            $upStmt->execute($params);

            foreach ($timelineEvents as $event) {
                $this->addTimelineEvent($id, $event[0], $event[1], $event[2], $event[3], $event[4]);
            }

            $this->pdo->commit();
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }
}
