<?php
require_once __DIR__ . '/../utils/Storage.php';

class ESMSupportController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser();
        $this->tenantId = $this->currentUser['tenant_id'] ?? null;
    }

    private function isESMAgent() {
        return $this->tenantId !== null && hasRole(['Admin', 'HR', 'Super_Admin']);
    }

    public function handleRequest($action)
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve tenant context']);
            return;
        }
        if ($this->tenantId === null) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Must be a tenant user to access ESM']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $input = $_POST;
            if (empty($input) && $_SERVER['REQUEST_METHOD'] === 'POST') {
                 $input = json_decode(file_get_contents('php://input'), true) ?? [];
            }
        }

        try {
            switch ($action) {
                case 'employee_create':
                    $this->employeeCreate($input);
                    break;
                case 'employee_list':
                    $this->employeeList();
                    break;
                case 'agent_list':
                    $this->agentList();
                    break;
                case 'ticket_details':
                    $this->getDetails();
                    break;
                case 'download_attachment':
                    $this->downloadAttachment();
                    break;
                case 'add_comment':
                    $this->addComment($input);
                    break;
                case 'update_status':
                    $this->updateStatus($input);
                    break;
                case 'update_ticket':
                    $this->updateTicket($input);
                    break;
                case 'add_ticket_tag':
                    $this->addTicketTag($input);
                    break;
                case 'remove_ticket_tag':
                    $this->removeTicketTag($input);
                    break;
                case 'export_report':
                    $this->exportReport();
                    break;
                case 'bulk_action':
                    $this->bulkAction($input);
                    break;
                case 'canned_responses':
                    $this->cannedResponses();
                    break;
                case 'upload_attachment':
                    $this->uploadAttachment();
                    break;
                case 'submit_csat':
                    $this->submitCSAT($input);
                    break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown action']);
                    break;
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    private function employeeCreate($input) {
        $subject = trim($input['subject'] ?? '');
        $description = trim($input['description'] ?? '');
        $priority = $input['priority'] ?? 'Medium';

        if (empty($subject) || empty($description)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Subject and description required']);
            return;
        }

        $priorityMap = ['Critical' => 4, 'High' => 12, 'Medium' => 24, 'Low' => 48];
        $hours = $priorityMap[$priority] ?? 24;

        $stmt = $this->pdo->prepare("INSERT INTO `esm_tickets` (`tenant_id`, `created_by`, `subject`, `description`, `priority`, `sla_breach_at`) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))");
        $stmt->execute([$this->tenantId, $this->currentUser['id'], $subject, $description, $priority, $hours]);
        
        $ticketId = $this->pdo->lastInsertId();
        
        $this->pdo->prepare("INSERT INTO `esm_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)")
             ->execute([$ticketId, $this->currentUser['id'], "Ticket opened by employee."]);

        echo json_encode(['success' => true, 'ticket_id' => $ticketId]);
    }

    private function submitCSAT($input) {
        $id = (int)($input['ticket_id'] ?? 0);
        $score = (int)($input['score'] ?? 0);
        $comment = trim($input['comment'] ?? '');

        if ($score < 1 || $score > 5) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid score']); return;
        }

        // Verify ownership (only the employee creator can submit CSAT)
        $stmt = $this->pdo->prepare("SELECT id, status, csat_score FROM `esm_tickets` WHERE `id` = ? AND `created_by` = ? AND `tenant_id` = ?");
        $stmt->execute([$id, $this->currentUser['id'], $this->tenantId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            http_response_code(404); echo json_encode(['success' => false, 'error' => 'Ticket not found or you are not the creator']); return;
        }

        if (!in_array($ticket['status'], ['Resolved', 'Closed'])) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Ticket must be resolved or closed to submit feedback']); return;
        }

        if ($ticket['csat_score']) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Feedback already submitted']); return;
        }

        $this->pdo->prepare("UPDATE `esm_tickets` SET `csat_score` = ?, `csat_comment` = ? WHERE `id` = ?")
             ->execute([$score, $comment, $id]);

        // Auto-comment
        $uName = ($this->currentUser['first_name'] || $this->currentUser['last_name']) ? trim($this->currentUser['first_name'] . ' ' . $this->currentUser['last_name']) : 'Employee';
        $sysComment = "[SYSTEM] $uName submitted a CSAT rating of $score/5 stars.";
        $this->pdo->prepare("INSERT INTO `esm_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)")
             ->execute([$id, $this->currentUser['id'], $sysComment]);

        echo json_encode(['success' => true]);
    }

    private function employeeList() {
        $stmt = $this->pdo->prepare("SELECT * FROM `esm_tickets` WHERE `tenant_id` = ? AND `created_by` = ? ORDER BY `updated_at` DESC");
        $stmt->execute([$this->tenantId, $this->currentUser['id']]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    private function agentList() {
        if (!$this->isESMAgent()) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return;
        }

        $where = ["et.tenant_id = ?"];
        $params = [$this->tenantId];

        if (!empty($_GET['status'])) { $where[] = "et.status = ?"; $params[] = $_GET['status']; }
        if (!empty($_GET['priority'])) { $where[] = "et.priority = ?"; $params[] = $_GET['priority']; }
        if (!empty($_GET['search'])) {
            $where[] = "(et.subject LIKE ? OR et.id = ?)";
            $params[] = "%" . $_GET['search'] . "%";
            $params[] = $_GET['search'];
        }
        if (!empty($_GET['tab'])) {
            if ($_GET['tab'] === 'pending') { $where[] = "et.status NOT IN ('Resolved', 'Closed')"; }
            else if ($_GET['tab'] === 'finished') { $where[] = "et.status IN ('Resolved', 'Closed')"; }
        }

        $sql = "SELECT et.*, t.company_name, 
                       TIMESTAMPDIFF(HOUR, et.created_at, IFNULL(et.updated_at, NOW())) as aging_hours 
                FROM `esm_tickets` et 
                JOIN `tenants` t ON et.tenant_id = t.id";
        
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY et.updated_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filteredTickets = [];
        foreach ($tickets as &$t) {
            $aging = (int)$t['aging_hours'];
            if ($t['status'] === 'Closed' || $t['status'] === 'Resolved') {
                $t['sli_status'] = 'Healthy';
            } else {
                if ($aging < 24) $t['sli_status'] = 'Healthy';
                else if ($aging < 48) $t['sli_status'] = 'Warning';
                else $t['sli_status'] = 'Breached';
            }
            if (empty($_GET['sli_status']) || $t['sli_status'] === $_GET['sli_status']) {
                $filteredTickets[] = $t;
            }
        }

        echo json_encode(['success' => true, 'data' => $filteredTickets]);
    }

    private function getDetails() {
        $id = (int)($_GET['id'] ?? 0);
        
        if ($this->isESMAgent()) {
            $stmt = $this->pdo->prepare("SELECT et.*, t.company_name, u.full_name as creator_name FROM `esm_tickets` et JOIN `tenants` t ON et.tenant_id = t.id LEFT JOIN `users` u ON et.created_by = u.id WHERE et.id = ? AND et.tenant_id = ?");
            $stmt->execute([$id, $this->tenantId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT et.*, u.full_name as creator_name FROM `esm_tickets` et LEFT JOIN `users` u ON et.created_by = u.id WHERE et.id = ? AND et.tenant_id = ? AND et.created_by = ?");
            $stmt->execute([$id, $this->tenantId, $this->currentUser['id']]);
        }
        
        $ticket = $stmt->fetch();
        if (!$ticket) { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Not found']); return; }

        $cStmt = $this->pdo->prepare("SELECT etc.*, u.full_name, u.role FROM `esm_ticket_comments` etc LEFT JOIN `users` u ON etc.user_id = u.id WHERE etc.ticket_id = ? ORDER BY etc.created_at ASC");
        $cStmt->execute([$id]);
        $comments = $cStmt->fetchAll();

        // Filter out internal comments if not an agent
        if (!$this->isESMAgent()) {
            $comments = array_values(array_filter($comments, function($c) {
                return $c['comment_type'] !== 'Internal';
            }));
        }

        foreach ($comments as &$c) {
            if (!empty($c['attachments'])) {
                $atts = json_decode($c['attachments'], true);
                if (is_array($atts)) {
                    foreach ($atts as &$a) {
                        if (isset($a['url'])) {
                            $a['url'] = '../api/index.php?route=esm_support&action=download_attachment&ticket_id=' . $id . '&path=' . urlencode($a['url']);
                        }
                    }
                    $c['attachments'] = json_encode($atts);
                }
            }
        }

        echo json_encode(['success' => true, 'data' => ['ticket' => $ticket, 'comments' => $comments]]);
    }

    private function addComment($input) {
        $id = (int)($input['ticket_id'] ?? 0);
        $comment = trim($input['comment'] ?? '');
        $attachments = $input['attachments'] ?? null;
        if (empty($comment)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Comment empty']); return; }

        if ($this->isESMAgent()) {
            $stmt = $this->pdo->prepare("SELECT id FROM `esm_tickets` WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $this->tenantId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT id FROM `esm_tickets` WHERE id = ? AND tenant_id = ? AND created_by = ?");
            $stmt->execute([$id, $this->tenantId, $this->currentUser['id']]);
        }

        if (!$stmt->fetch()) { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Not found']); return; }

        $attachmentsJson = $attachments ? json_encode($attachments) : null;

        $this->pdo->prepare("INSERT INTO `esm_ticket_comments` (`ticket_id`, `user_id`, `comment`, `attachments`) VALUES (?, ?, ?, ?)")
             ->execute([$id, $this->currentUser['id'], $comment, $attachmentsJson]);
             
        $this->pdo->prepare("UPDATE `esm_tickets` SET `updated_at` = NOW() WHERE `id` = ?")->execute([$id]);

        echo json_encode(['success' => true]);
    }

    private function updateStatus($input) {
        if (!$this->isESMAgent()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
        $id = (int)($input['ticket_id'] ?? 0);
        $status = $input['status'] ?? '';
        $this->pdo->prepare("UPDATE `esm_tickets` SET `status` = ? WHERE `id` = ? AND `tenant_id` = ?")->execute([$status, $id, $this->tenantId]);
        echo json_encode(['success' => true]);
    }

    private function updateTicket($input) {
        if (!$this->isESMAgent()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
        $id = (int)($input['ticket_id'] ?? 0);
        
        $stmt = $this->pdo->prepare("SELECT * FROM `esm_tickets` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$id, $this->tenantId]);
        $oldTicket = $stmt->fetch();
        if (!$oldTicket) { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Ticket not found']); return; }

        $updates = []; $params = []; $logMessages = [];
        $statusMapRev = ['open'=>'Open', 'in_progress'=>'In Progress', 'waiting'=>'Waiting', 'resolved'=>'Resolved', 'closed'=>'Closed'];
        $priorityMapRev = ['low'=>'Low', 'medium'=>'Medium', 'high'=>'High', 'critical'=>'Critical'];

        if (isset($input['status'])) {
            $newStatus = $statusMapRev[$input['status']] ?? 'Open';
            if ($newStatus !== $oldTicket['status']) {
                $updates[] = "`status` = ?"; $params[] = $newStatus;
                $logMessages[] = "changed status to " . $newStatus;
            }
        }
        if (isset($input['priority'])) {
            $newPriority = $priorityMapRev[$input['priority']] ?? 'Medium';
            if ($newPriority !== $oldTicket['priority']) {
                $updates[] = "`priority` = ?"; $params[] = $newPriority;
                $logMessages[] = "changed priority to " . $newPriority;
            }
        }
        if (array_key_exists('assigned_to', $input)) {
            $newAssigneeId = $input['assigned_to'] ? (int)$input['assigned_to'] : null;
            if ($newAssigneeId !== $oldTicket['assigned_to']) {
                $updates[] = "`assigned_to` = ?"; $params[] = $newAssigneeId;
                if ($newAssigneeId) {
                    $uStmt = $this->pdo->prepare("SELECT first_name, last_name, email FROM `users` WHERE `id` = ?");
                    $uStmt->execute([$newAssigneeId]);
                    $aUser = $uStmt->fetch();
                    $name = ($aUser['first_name'] || $aUser['last_name']) ? trim($aUser['first_name'] . ' ' . $aUser['last_name']) : $aUser['email'];
                    $logMessages[] = "assigned the ticket to " . $name;
                } else {
                    $logMessages[] = "unassigned the ticket";
                }
            }
        }

        if (!empty($updates)) {
            $params[] = $id; $params[] = $this->tenantId;
            $this->pdo->prepare("UPDATE `esm_tickets` SET " . implode(", ", $updates) . ", `updated_at` = NOW() WHERE `id` = ? AND `tenant_id` = ?")->execute($params);

            $uName = ($this->currentUser['first_name'] || $this->currentUser['last_name']) ? trim($this->currentUser['first_name'] . ' ' . $this->currentUser['last_name']) : 'Agent';
            foreach ($logMessages as $log) {
                $sysComment = "[SYSTEM] " . $uName . " " . $log . ".";
                $this->pdo->prepare("INSERT INTO `esm_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)")
                     ->execute([$id, $this->currentUser['id'], $sysComment]);
            }
        }
        echo json_encode(['success' => true]);
    }

    private function addTicketTag($input) {
        if (!$this->isESMAgent()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
        $id = (int)($input['ticket_id'] ?? 0);
        $tag = trim($input['tag'] ?? '');
        if (empty($tag)) { http_response_code(400); return; }
        
        $chk = $this->pdo->prepare("SELECT id FROM esm_tickets WHERE id = ? AND tenant_id = ?");
        $chk->execute([$id, $this->tenantId]);
        if (!$chk->fetch()) { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Ticket not found']); return; }

        try {
            $this->pdo->prepare("INSERT INTO `esm_ticket_tags` (`ticket_id`, `tag`) VALUES (?, ?)")->execute([$id, $tag]);
            $uName = ($this->currentUser['first_name'] || $this->currentUser['last_name']) ? trim($this->currentUser['first_name'] . ' ' . $this->currentUser['last_name']) : 'Agent';
            $this->pdo->prepare("INSERT INTO `esm_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)")
                 ->execute([$id, $this->currentUser['id'], "[SYSTEM] " . $uName . " added tag '" . $tag . "'."]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) { echo json_encode(['success' => true]); }
    }

    private function removeTicketTag($input) {
        if (!$this->isESMAgent()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
        $id = (int)($input['ticket_id'] ?? 0);
        $tag = trim($input['tag'] ?? '');

        $chk = $this->pdo->prepare("SELECT id FROM esm_tickets WHERE id = ? AND tenant_id = ?");
        $chk->execute([$id, $this->tenantId]);
        if (!$chk->fetch()) { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Ticket not found']); return; }
        $stmt = $this->pdo->prepare("DELETE FROM `esm_ticket_tags` WHERE `ticket_id` = ? AND `tag` = ?");
        $stmt->execute([$id, $tag]);
        if ($stmt->rowCount() > 0) {
            $uName = ($this->currentUser['first_name'] || $this->currentUser['last_name']) ? trim($this->currentUser['first_name'] . ' ' . $this->currentUser['last_name']) : 'Agent';
            $this->pdo->prepare("INSERT INTO `esm_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)")
                 ->execute([$id, $this->currentUser['id'], "[SYSTEM] " . $uName . " removed tag '" . $tag . "'."]);
        }
        echo json_encode(['success' => true]);
    }

    private function exportReport() {
        if (!$this->isESMAgent()) { http_response_code(403); echo "Denied"; return; }
        // implementation omitted for brevity
        echo "Export CSV";
    }

    private function cannedResponses() {
        if (!$this->isESMAgent()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
        $stmt = $this->pdo->query("SELECT * FROM `esm_canned_responses` ORDER BY `title` ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function bulkAction($input) {
        if (!$this->isESMAgent()) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return; }
        $ticketIds = $input['ticket_ids'] ?? [];
        $action = $input['action'] ?? '';
        $value = $input['value'] ?? '';
        if (empty($ticketIds) || !is_array($ticketIds) || empty($action)) { http_response_code(400); return; }
        $idList = implode(',', array_map('intval', $ticketIds));
        $uName = ($this->currentUser['first_name'] || $this->currentUser['last_name']) ? trim($this->currentUser['first_name'] . ' ' . $this->currentUser['last_name']) : 'Agent';
        $sysComment = "";

        if ($action === 'resolve') {
            $this->pdo->prepare("UPDATE `esm_tickets` SET `status` = 'Resolved', `updated_at` = NOW() WHERE `id` IN ($idList) AND `tenant_id` = ?")->execute([$this->tenantId]);
            $sysComment = "[SYSTEM] $uName mass-resolved this ticket.";
        } else if ($action === 'close') {
            $this->pdo->prepare("UPDATE `esm_tickets` SET `status` = 'Closed', `updated_at` = NOW() WHERE `id` IN ($idList) AND `tenant_id` = ?")->execute([$this->tenantId]);
            $sysComment = "[SYSTEM] $uName mass-closed this ticket.";
        }
        if ($sysComment) {
            $stmt = $this->pdo->prepare("INSERT INTO `esm_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)");
            foreach ($ticketIds as $tid) { $stmt->execute([(int)$tid, $this->currentUser['id'], $sysComment]); }
        }
        echo json_encode(['success' => true]);
    }

    private function uploadAttachment() {
        if (!isset($_FILES['attachment'])) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'No file uploaded']); return; }
        $file = $_FILES['attachment'];

        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'File exceeds 5MB limit']); return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
        ];
        if (!array_key_exists($mime, $allowedMimes)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type']); return;
        }

        $storageBase = \App\Utils\Storage::resolveStorageBase(false, true);
        $storageDir = rtrim($storageBase, '/') . '/tenant_' . $this->tenantId . '/esm_tickets';
        if (!is_dir($storageDir)) { mkdir($storageDir, 0755, true); }
        
        $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mime];
        
        if (move_uploaded_file($file['tmp_name'], $storageDir . '/' . $filename)) {
            $url = 'tenant_' . $this->tenantId . '/esm_tickets/' . $filename;
            echo json_encode(['success' => true, 'url' => $url, 'name' => basename($file['name'])]);
        } else {
            http_response_code(500); echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
        }
    }

    private function downloadAttachment() {
        $ticketId = (int)($_GET['ticket_id'] ?? 0);
        $path = $_GET['path'] ?? '';
        if (!$ticketId || !$path || strpos($path, '..') !== false) {
            http_response_code(400); echo "Invalid request"; return;
        }

        if ($this->isESMAgent()) {
            $stmt = $this->pdo->prepare("SELECT id FROM `esm_tickets` WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$ticketId, $this->tenantId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT id FROM `esm_tickets` WHERE id = ? AND tenant_id = ? AND created_by = ?");
            $stmt->execute([$ticketId, $this->tenantId, $this->currentUser['id']]);
        }
        if (!$stmt->fetch()) {
            http_response_code(403); echo "Access denied to ticket"; return;
        }

        $storageBase = \App\Utils\Storage::resolveStorageBase(false, false);
        $dbPath = preg_replace('/^\/?uploads\//', '', $path);
        $fullPath = rtrim($storageBase, '/') . '/' . ltrim($dbPath, '/');

        if (!file_exists($fullPath)) {
            http_response_code(404); echo "File not found"; return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }
}
