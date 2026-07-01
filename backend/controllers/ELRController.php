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
                case 'copilot':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->copilot($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;
                case 'kb_list':
                    $this->kbList();
                    break;
                case 'kb_add':
                    $this->kbAdd($input);
                    break;
                case 'kb_approve':
                    $this->kbApprove($input);
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

    /**
     * ELR Copilot — Retrieval-Augmented Generation over the labor-law corpus.
     * Full-text searches the (global, provider-curated) labor_references + elr_precedents,
     * then asks Gemini to answer STRICTLY from those retrieved sources, with citations.
     */
    private function copilot($input)
    {
        $question = trim($input['question'] ?? '');
        if ($question === '') {
            echo json_encode(['success' => false, 'error' => 'A question is required.']);
            return;
        }

        $sources = [];
        $contextParts = [];

        // 1a. Retrieve DOLE/statutory references (only Approved entries are used for grounding)
        $stmtRef = $this->pdo->prepare(
            "SELECT `id`, `category`, `title`, `summary`, `source_type`, `official_url`
             FROM `labor_references`
             WHERE `status` = 'Approved' AND MATCH(`title`, `summary`) AGAINST (? IN NATURAL LANGUAGE MODE)
             LIMIT 4"
        );
        $stmtRef->execute([$question]);
        while ($r = $stmtRef->fetch(PDO::FETCH_ASSOC)) {
            $contextParts[] = "[DOLE / Statutory Reference] {$r['title']} ({$r['category']}): {$r['summary']}";
            $sources[] = ['type' => 'reference', 'title' => $r['title'], 'reference' => $r['source_type'], 'url' => $r['official_url']];
        }

        // 1b. Retrieve Supreme Court jurisprudence / internal precedents
        $stmtPre = $this->pdo->prepare(
            "SELECT `id`, `case_type`, `title`, `summary`, `key_principles`, `source_reference`, `risk_level`, `recommended_process`
             FROM `elr_precedents`
             WHERE MATCH(`case_type`, `title`, `summary`, `key_principles`) AGAINST (? IN NATURAL LANGUAGE MODE)
             LIMIT 4"
        );
        $stmtPre->execute([$question]);
        while ($p = $stmtPre->fetch(PDO::FETCH_ASSOC)) {
            $contextParts[] = "[Jurisprudence] {$p['title']} ({$p['case_type']}, Risk: {$p['risk_level']}). Key principles: {$p['key_principles']}. Recommended process: {$p['recommended_process']}. Source: {$p['source_reference']}";
            $sources[] = ['type' => 'precedent', 'title' => $p['title'], 'reference' => $p['source_reference'], 'risk_level' => $p['risk_level']];
        }

        $context = empty($contextParts)
            ? "NO MATCHING SOURCES FOUND IN THE KNOWLEDGE BASE."
            : implode("\n\n", $contextParts);

        // 2. Ask Gemini, grounded strictly on the retrieved sources
        $answer = $this->askGeminiGrounded($question, $context);

        echo json_encode([
            'success'  => true,
            'answer'   => $answer,
            'sources'  => $sources,
            'grounded' => !empty($contextParts)
        ]);
    }

    /**
     * Calls Gemini, instructing it to answer only from the supplied labor-law context.
     * Reuses the same integration approach as the AI Companion.
     */
    private function askGeminiGrounded($question, $context)
    {
        $apiKey = getenv('GEMINI_API_KEY');
        if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
            return "⚠️ The AI engine is not configured (GEMINI_API_KEY missing). The matching sources are listed below for manual review.";
        }

        $systemPrompt = "You are a Philippine Employee & Labor Relations legal assistant. "
            . "Answer the user's question STRICTLY based on the provided SOURCES (DOLE advisories and Supreme Court jurisprudence). "
            . "Cite the specific source titles you rely on. If the SOURCES do not cover the question, say so plainly and advise consulting a labor lawyer or DOLE — never invent legal rules or cite laws not in the sources. "
            . "Be practical and process-oriented (e.g., due-process steps). End with a brief note that this is guidance, not legal advice.";

        $fullPrompt = "SYSTEM: {$systemPrompt}\n\nSOURCES:\n{$context}\n\nQUESTION:\n{$question}";

        $data = [
            "contents" => [["parts" => [["text" => $fullPrompt]]]],
            "generationConfig" => ["temperature" => 0.2]
        ];

        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "x-goog-api-key: " . $apiKey]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $rd = json_decode($response, true);
            if (isset($rd['candidates'][0]['content']['parts'][0]['text'])) {
                return $rd['candidates'][0]['content']['parts'][0]['text'];
            }
        }
        error_log('[ELRController] Gemini call failed: HTTP ' . $httpCode);
        return "The AI engine could not generate a response right now. Please review the matching sources below, or try again shortly.";
    }

    /**
     * List the labor-law corpus (global, provider-curated). Readable by any ELR user.
     */
    private function kbList()
    {
        $refs = $this->pdo->query(
            "SELECT `id`, `category`, `title`, `summary`, `source_type`, `official_url`, `effective_date`, `status`, `created_at`
             FROM `labor_references` ORDER BY `created_at` DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $precs = $this->pdo->query(
            "SELECT `id`, `case_type`, `title`, `summary`, `key_principles`, `source_reference`, `risk_level`, `recommended_process`, `created_at`
             FROM `elr_precedents` ORDER BY `created_at` DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'labor_references' => $refs, 'precedents' => $precs]);
    }

    /**
     * Add a corpus entry. Corpus is global/shared across tenants (the law is the same for
     * everyone), so writes are restricted to platform admins (Super_Admin) to keep quality high.
     */
    private function kbAdd($input)
    {
        if (empty($_SESSION['is_super'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Only platform administrators can edit the labor-law corpus.']);
            return;
        }
        $type = $input['type'] ?? 'reference';
        if ($type === 'precedent') {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `elr_precedents` (`case_type`, `title`, `summary`, `key_principles`, `source_reference`, `risk_level`, `recommended_process`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                trim($input['case_type'] ?? ''),
                trim($input['title'] ?? ''),
                trim($input['summary'] ?? ''),
                trim($input['key_principles'] ?? ''),
                trim($input['source_reference'] ?? ''),
                in_array($input['risk_level'] ?? 'Medium', ['Low', 'Medium', 'High', 'Critical'], true) ? $input['risk_level'] : 'Medium',
                trim($input['recommended_process'] ?? '')
            ]);
        } else {
            // Labor references start as 'Pending' — only 'Approved' entries are used for RAG grounding.
            $stmt = $this->pdo->prepare(
                "INSERT INTO `labor_references` (`category`, `title`, `summary`, `source_type`, `official_url`, `effective_date`, `status`)
                 VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
            );
            $stmt->execute([
                trim($input['category'] ?? ''),
                trim($input['title'] ?? ''),
                trim($input['summary'] ?? ''),
                trim($input['source_type'] ?? 'Manual Entry'),
                trim($input['official_url'] ?? '') ?: null,
                !empty($input['effective_date']) ? $input['effective_date'] : null
            ]);
        }
        echo json_encode(['success' => true, 'message' => 'Corpus entry added.']);
    }

    /**
     * Approve / reject a labor reference so it becomes (in)eligible for RAG grounding. Platform admins only.
     */
    private function kbApprove($input)
    {
        if (empty($_SESSION['is_super'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Only platform administrators can review the corpus.']);
            return;
        }
        $id = (int)($input['id'] ?? 0);
        $status = in_array($input['status'] ?? 'Approved', ['Pending', 'Approved', 'Rejected'], true) ? $input['status'] : 'Approved';
        $reviewer = is_array($this->currentUser) ? ($this->currentUser['full_name'] ?? 'Admin') : 'Admin';
        $stmt = $this->pdo->prepare("UPDATE `labor_references` SET `status` = ?, `reviewed_by` = ? WHERE `id` = ?");
        $stmt->execute([$status, $reviewer, $id]);
        echo json_encode(['success' => true, 'message' => 'Corpus entry updated.']);
    }

    private function addTimelineEvent($caseId, $eventType, $description, $actor = null, $oldValue = null, $newValue = null) {
        $stmt = $this->pdo->prepare("INSERT INTO `elr_case_timeline` (`case_id`, `event_type`, `description`, `actor`, `old_value`, `new_value`) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$caseId, $eventType, $description, $actor, $oldValue, $newValue]);
    }

    private function getCases() {
        $userRole = strtolower($this->currentUser['role'] ?? '');
        $userEmployeeId = $this->currentUser['employee_id'] ?? '';
        
        require_once __DIR__ . '/../services/ScopeResolver.php';
        $scopeClause = ScopeResolver::getScopeWhereClause($this->pdo, $this->currentUser, 'u');

        $sql = "SELECT c.*, t.name as case_type_name 
                FROM `elr_cases` c 
                LEFT JOIN `elr_case_types` t ON c.case_type_id = t.id 
                LEFT JOIN `users` u ON c.employee_id = u.employee_id AND c.tenant_id = u.tenant_id
                WHERE c.tenant_id = :tenant_id $scopeClause";
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
        require_once __DIR__ . '/../services/ScopeResolver.php';
        $scopeClause = ScopeResolver::getScopeWhereClause($this->pdo, $this->currentUser, 'u');

        // Case Volume Trend (Last 6 Months)
        $trendSql = "
            SELECT DATE_FORMAT(c.created_at, '%b') as month, COUNT(*) as count 
            FROM elr_cases c
            LEFT JOIN `users` u ON c.employee_id = u.employee_id AND c.tenant_id = u.tenant_id
            WHERE c.tenant_id = ? AND c.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) $scopeClause
            GROUP BY DATE_FORMAT(c.created_at, '%Y-%m'), DATE_FORMAT(c.created_at, '%b')
            ORDER BY DATE_FORMAT(c.created_at, '%Y-%m') ASC
        ";
        $stmt = $this->pdo->prepare($trendSql);
        $stmt->execute([$this->tenantId]);
        $trendData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Channels / Case Types
        $typeSql = "
            SELECT t.name as source, COUNT(c.id) as applications
            FROM elr_cases c
            JOIN elr_case_types t ON c.case_type_id = t.id
            LEFT JOIN `users` u ON c.employee_id = u.employee_id AND c.tenant_id = u.tenant_id
            WHERE c.tenant_id = ? $scopeClause
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
            error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
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
            error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
        }
    }
}
