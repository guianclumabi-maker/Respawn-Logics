<?php

class PlatformSupportController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser() ?: null;
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id']) ? $this->currentUser['tenant_id'] : null;
    }

    private function isVendorStaff() {
        return $this->tenantId === null && hasRole(['Platform_Admin', 'Support_Agent', 'Implementation_Specialist']);
    }

    private function isSuperAdmin() {
        return hasRole('Super_Admin');
    }

    public function handleRequest($action)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $input = $_POST;
            if (empty($input) && $_SERVER['REQUEST_METHOD'] === 'POST') {
                 $input = json_decode(file_get_contents('php://input'), true) ?? [];
            }
        }

        switch ($action) {
            case 'tenant_create':
                $this->tenantCreate($input);
                break;
            case 'tenant_list':
                $this->tenantList();
                break;
            case 'vendor_list':
                $this->vendorList();
                break;
            case 'ticket_details':
                $this->getDetails();
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
            case 'submit_feedback':
                $this->submitFeedback($input);
                break;
            case 'submit_csat':
                $this->submitCSAT($input);
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
                break;
        }
    }

    private function tenantCreate($input) {
        if ($this->tenantId === null) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Only Tenant Users can submit platform tickets']);
            return;
        }

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

        $stmt = $this->pdo->prepare("INSERT INTO `platform_tickets` (`tenant_id`, `created_by`, `subject`, `description`, `priority`, `sla_breach_at`) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))");
        $stmt->execute([$this->tenantId, $this->currentUser['id'], $subject, $description, $priority, $hours]);
        
        $ticketId = $this->pdo->lastInsertId();
        
        // Auto-comment
        $this->pdo->prepare("INSERT INTO `platform_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)")
             ->execute([$ticketId, $this->currentUser['id'], "Ticket opened by Tenant Admin."]);

        echo json_encode(['success' => true, 'ticket_id' => $ticketId]);
    }

    private function submitFeedback($input) {
        $feedback = trim($input['feedback'] ?? '');
        if (empty($feedback)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Feedback cannot be empty']);
            return;
        }

        $subject = "User Feedback";
        $priority = "Low";
        $hours = 48; // Low priority SLA

        // Create ticket
        $stmt = $this->pdo->prepare("INSERT INTO `platform_tickets` (`tenant_id`, `created_by`, `subject`, `description`, `priority`, `sla_breach_at`) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))");
        $stmt->execute([$this->tenantId, $this->currentUser['id'], $subject, $feedback, $priority, $hours]);
        $ticketId = $this->pdo->lastInsertId();

        // Add "Feedback" tag
        try {
            $this->pdo->prepare("INSERT INTO `platform_ticket_tags` (`ticket_id`, `tag`) VALUES (?, ?)")->execute([$ticketId, "Feedback"]);
        } catch (PDOException $e) {}

        // Add initial comment
        $this->pdo->prepare("INSERT INTO `platform_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)")
             ->execute([$ticketId, $this->currentUser['id'], "Feedback submitted via global feedback button."]);

        echo json_encode(['success' => true]);
    }

    private function submitCSAT($input) {
        $id = (int)($input['ticket_id'] ?? 0);
        $score = (int)($input['score'] ?? 0);
        $comment = trim($input['comment'] ?? '');

        if ($score < 1 || $score > 5) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid score']); return;
        }

        // Verify ownership (only creator or tenant admin can submit CSAT)
        if ($this->tenantId === null) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Only clients can submit CSAT']); return;
        }

        $stmt = $this->pdo->prepare("SELECT id, status, csat_score FROM `platform_tickets` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$id, $this->tenantId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            http_response_code(404); echo json_encode(['success' => false, 'error' => 'Not found']); return;
        }

        if (!in_array($ticket['status'], ['Resolved', 'Closed'])) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Ticket must be resolved or closed to submit feedback']); return;
        }

        if ($ticket['csat_score']) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Feedback already submitted']); return;
        }

        $this->pdo->prepare("UPDATE `platform_tickets` SET `csat_score` = ?, `csat_comment` = ? WHERE `id` = ?")
             ->execute([$score, $comment, $id]);

        // Auto-comment
        $uName = ($this->currentUser['first_name'] || $this->currentUser['last_name']) ? trim($this->currentUser['first_name'] . ' ' . $this->currentUser['last_name']) : 'User';
        $sysComment = "[SYSTEM] $uName submitted a CSAT rating of $score/5 stars.";
        $this->pdo->prepare("INSERT INTO `platform_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)")
             ->execute([$id, $this->currentUser['id'], $sysComment]);

        echo json_encode(['success' => true]);
    }

    private function tenantList() {
        if ($this->tenantId === null) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM `platform_tickets` WHERE `tenant_id` = ? ORDER BY `updated_at` DESC");
        $stmt->execute([$this->tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    private function vendorList() {
        if (!$this->isVendorStaff()) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return;
        }

        $where = [];
        $params = [];

        if (!empty($_GET['status'])) {
            $where[] = "pt.status = ?";
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['priority'])) {
            $where[] = "pt.priority = ?";
            $params[] = $_GET['priority'];
        }
        if (!empty($_GET['tenant_id'])) {
            $where[] = "pt.tenant_id = ?";
            $params[] = $_GET['tenant_id'];
        }
        if (!empty($_GET['start_date'])) {
            $where[] = "DATE(pt.created_at) >= ?";
            $params[] = $_GET['start_date'];
        }
        if (!empty($_GET['end_date'])) {
            $where[] = "DATE(pt.created_at) <= ?";
            $params[] = $_GET['end_date'];
        }
        if (!empty($_GET['search'])) {
            $where[] = "(pt.subject LIKE ? OR pt.id = ?)";
            $params[] = "%" . $_GET['search'] . "%";
            $params[] = $_GET['search'];
        }
        if (!empty($_GET['tab'])) {
            if ($_GET['tab'] === 'pending') {
                $where[] = "pt.status NOT IN ('Resolved', 'Closed')";
            } else if ($_GET['tab'] === 'finished') {
                $where[] = "pt.status IN ('Resolved', 'Closed')";
            }
        }

        $sql = "SELECT pt.*, t.company_name, 
                       TIMESTAMPDIFF(HOUR, pt.created_at, IFNULL(pt.updated_at, NOW())) as aging_hours 
                FROM `platform_tickets` pt 
                JOIN `tenants` t ON pt.tenant_id = t.id";
        
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY pt.updated_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filteredTickets = [];
        foreach ($tickets as &$t) {
            $aging = (int)$t['aging_hours'];
            if ($t['status'] === 'Closed' || $t['status'] === 'Resolved') {
                $t['sli_status'] = 'Healthy'; // Freeze SLI for closed tickets
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
        
        if ($this->isVendorStaff()) {
            $stmt = $this->pdo->prepare("SELECT pt.*, t.company_name, u.full_name as creator_name FROM `platform_tickets` pt JOIN `tenants` t ON pt.tenant_id = t.id LEFT JOIN `users` u ON pt.created_by = u.id WHERE pt.id = ?");
            $stmt->execute([$id]);
        } else if ($this->tenantId !== null) {
            $stmt = $this->pdo->prepare("SELECT pt.*, u.full_name as creator_name FROM `platform_tickets` pt LEFT JOIN `users` u ON pt.created_by = u.id WHERE pt.id = ? AND pt.tenant_id = ?");
            $stmt->execute([$id, $this->tenantId]);
        } else {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return;
        }
        
        $ticket = $stmt->fetch();
        if (!$ticket) { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Not found']); return; }

        $cStmt = $this->pdo->prepare("SELECT ptc.*, u.full_name, u.role FROM `platform_ticket_comments` ptc LEFT JOIN `users` u ON ptc.user_id = u.id WHERE ptc.ticket_id = ? ORDER BY ptc.created_at ASC");
        $cStmt->execute([$id]);
        $comments = $cStmt->fetchAll();

        echo json_encode(['success' => true, 'data' => ['ticket' => $ticket, 'comments' => $comments]]);
    }

    private function addComment($input) {
        $id = (int)($input['ticket_id'] ?? 0);
        $comment = trim($input['comment'] ?? '');
        $attachments = $input['attachments'] ?? null;
        if (empty($comment)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Comment empty']); return; }

        // Verify access
        if ($this->isVendorStaff()) {
            $stmt = $this->pdo->prepare("SELECT id FROM `platform_tickets` WHERE id = ?");
            $stmt->execute([$id]);
        } else if ($this->tenantId !== null) {
            $stmt = $this->pdo->prepare("SELECT id FROM `platform_tickets` WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $this->tenantId]);
        } else {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return;
        }

        if (!$stmt->fetch()) { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Not found']); return; }

        $attachmentsJson = $attachments ? json_encode($attachments) : null;

        $this->pdo->prepare("INSERT INTO `platform_ticket_comments` (`ticket_id`, `user_id`, `comment`, `attachments`) VALUES (?, ?, ?, ?)")
             ->execute([$id, $this->currentUser['id'], $comment, $attachmentsJson]);
             
        $this->pdo->prepare("UPDATE `platform_tickets` SET `updated_at` = NOW() WHERE `id` = ?")->execute([$id]);

        echo json_encode(['success' => true]);
    }

    private function updateStatus($input) {
        if (!$this->isVendorStaff()) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Only vendor staff can update status']); return;
        }
        $id = (int)($input['ticket_id'] ?? 0);
        $status = $input['status'] ?? '';
        
        $this->pdo->prepare("UPDATE `platform_tickets` SET `status` = ? WHERE `id` = ?")->execute([$status, $id]);
        echo json_encode(['success' => true]);
    }

    private function updateTicket($input) {
        if (!$this->isVendorStaff()) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Only vendor staff can update tickets']); return;
        }
        $id = (int)($input['ticket_id'] ?? 0);
        
        // Fetch original to detect changes
        $stmt = $this->pdo->prepare("SELECT * FROM `platform_tickets` WHERE `id` = ?");
        $stmt->execute([$id]);
        $oldTicket = $stmt->fetch();
        
        if (!$oldTicket) {
            http_response_code(404); echo json_encode(['success' => false, 'error' => 'Ticket not found']); return;
        }

        $updates = [];
        $params = [];
        $logMessages = [];

        // Map React statuses back to DB ENUMs
        $statusMapRev = ['open'=>'Open', 'in_progress'=>'In Progress', 'waiting'=>'Waiting', 'resolved'=>'Resolved', 'closed'=>'Closed'];
        $priorityMapRev = ['low'=>'Low', 'medium'=>'Medium', 'high'=>'High', 'critical'=>'Critical'];

        if (isset($input['status'])) {
            $newStatus = $statusMapRev[$input['status']] ?? 'Open';
            if ($newStatus !== $oldTicket['status']) {
                $updates[] = "`status` = ?";
                $params[] = $newStatus;
                $logMessages[] = "changed status to " . $newStatus;
            }
        }

        if (isset($input['priority'])) {
            $newPriority = $priorityMapRev[$input['priority']] ?? 'Medium';
            if ($newPriority !== $oldTicket['priority']) {
                $updates[] = "`priority` = ?";
                $params[] = $newPriority;
                $logMessages[] = "changed priority to " . $newPriority;
            }
        }

        if (array_key_exists('assigned_to', $input)) {
            $newAssigneeId = $input['assigned_to'] ? (int)$input['assigned_to'] : null;
            if ($newAssigneeId !== $oldTicket['assigned_to']) {
                $updates[] = "`assigned_to` = ?";
                $params[] = $newAssigneeId;
                
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
            $params[] = $id;
            $this->pdo->prepare("UPDATE `platform_tickets` SET " . implode(", ", $updates) . ", `updated_at` = NOW() WHERE `id` = ?")->execute($params);

            // Create System Audit Log Comments
            $uName = ($this->currentUser['first_name'] || $this->currentUser['last_name']) ? trim($this->currentUser['first_name'] . ' ' . $this->currentUser['last_name']) : 'Agent';
            foreach ($logMessages as $log) {
                // Prepend [SYSTEM] so the UI can detect it
                $sysComment = "[SYSTEM] " . $uName . " " . $log . ".";
                $this->pdo->prepare("INSERT INTO `platform_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)")
                     ->execute([$id, $this->currentUser['id'], $sysComment]);
            }
        }

        echo json_encode(['success' => true]);
    }

    private function addTicketTag($input) {
        if (!$this->isVendorStaff()) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Only vendor staff can add tags']); return;
        }
        $id = (int)($input['ticket_id'] ?? 0);
        $tag = trim($input['tag'] ?? '');
        
        if (empty($tag)) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Tag cannot be empty']); return;
        }

        try {
            $this->pdo->prepare("INSERT INTO `platform_ticket_tags` (`ticket_id`, `tag`) VALUES (?, ?)")->execute([$id, $tag]);
            
            // Log it
            $uName = ($this->currentUser['first_name'] || $this->currentUser['last_name']) ? trim($this->currentUser['first_name'] . ' ' . $this->currentUser['last_name']) : 'Agent';
            $sysComment = "[SYSTEM] " . $uName . " added tag '" . $tag . "'.";
            $this->pdo->prepare("INSERT INTO `platform_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)")
                 ->execute([$id, $this->currentUser['id'], $sysComment]);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            // Might be duplicate
            echo json_encode(['success' => true]); // Ignore duplicates silently
        }
    }

    private function removeTicketTag($input) {
        if (!$this->isVendorStaff()) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Only vendor staff can remove tags']); return;
        }
        $id = (int)($input['ticket_id'] ?? 0);
        $tag = trim($input['tag'] ?? '');

        $stmt = $this->pdo->prepare("DELETE FROM `platform_ticket_tags` WHERE `ticket_id` = ? AND `tag` = ?");
        $stmt->execute([$id, $tag]);

        if ($stmt->rowCount() > 0) {
            $uName = ($this->currentUser['first_name'] || $this->currentUser['last_name']) ? trim($this->currentUser['first_name'] . ' ' . $this->currentUser['last_name']) : 'Agent';
            $sysComment = "[SYSTEM] " . $uName . " removed tag '" . $tag . "'.";
            $this->pdo->prepare("INSERT INTO `platform_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)")
                 ->execute([$id, $this->currentUser['id'], $sysComment]);
        }

        echo json_encode(['success' => true]);
    }

    private function exportReport() {
        if (!$this->isVendorStaff()) {
            http_response_code(403); echo "Denied"; return;
        }

        $where = [];
        $params = [];

        if (!empty($_GET['status'])) { $where[] = "pt.status = ?"; $params[] = $_GET['status']; }
        if (!empty($_GET['priority'])) { $where[] = "pt.priority = ?"; $params[] = $_GET['priority']; }
        if (!empty($_GET['tenant_id'])) { $where[] = "pt.tenant_id = ?"; $params[] = $_GET['tenant_id']; }
        
        if (!empty($_GET['start_date'])) { $where[] = "DATE(pt.created_at) >= ?"; $params[] = $_GET['start_date']; }
        if (!empty($_GET['end_date'])) { $where[] = "DATE(pt.created_at) <= ?"; $params[] = $_GET['end_date']; }
        
        if (!empty($_GET['search'])) {
            $where[] = "(pt.subject LIKE ? OR pt.id = ?)";
            $params[] = "%" . $_GET['search'] . "%";
            $params[] = $_GET['search'];
        }
        if (!empty($_GET['tab'])) {
            if ($_GET['tab'] === 'pending') { $where[] = "pt.status NOT IN ('Resolved', 'Closed')"; }
            else if ($_GET['tab'] === 'finished') { $where[] = "pt.status IN ('Resolved', 'Closed')"; }
        }

        $sql = "SELECT pt.id, t.company_name, pt.subject, pt.status, pt.priority, pt.created_at, pt.updated_at,
                       TIMESTAMPDIFF(HOUR, pt.created_at, IFNULL(pt.updated_at, NOW())) as aging_hours 
                FROM `platform_tickets` pt 
                JOIN `tenants` t ON pt.tenant_id = t.id";
        
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY pt.updated_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="Vendor_Support_Report_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Ticket ID', 'Company', 'Subject', 'Status', 'Priority', 'Aging (Hours)', 'SLI Status', 'Created At', 'Last Updated']);

        foreach ($tickets as $t) {
            $aging = (int)$t['aging_hours'];
            $sli = 'Healthy';
            if ($t['status'] !== 'Closed' && $t['status'] !== 'Resolved') {
                if ($aging >= 48) $sli = 'Breached';
                else if ($aging >= 24) $sli = 'Warning';
            }
            
            if (!empty($_GET['sli_status']) && $sli !== $_GET['sli_status']) {
                continue;
            }

            fputcsv($output, [
                $t['id'], 
                $t['company_name'], 
                $t['subject'], 
                $t['status'], 
                $t['priority'], 
                $aging, 
                $sli, 
                $t['created_at'], 
                $t['updated_at']
            ]);
        }
        fclose($output);
    }

    private function cannedResponses() {
        if (!$this->isVendorStaff()) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return;
        }
        $stmt = $this->pdo->query("SELECT * FROM `platform_canned_responses` ORDER BY `title` ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function bulkAction($input) {
        if (!$this->isVendorStaff()) {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Denied']); return;
        }

        $ticketIds = $input['ticket_ids'] ?? [];
        $action = $input['action'] ?? '';
        $value = $input['value'] ?? '';

        if (empty($ticketIds) || !is_array($ticketIds) || empty($action)) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid parameters']); return;
        }

        $idList = implode(',', array_map('intval', $ticketIds));
        $uName = ($this->currentUser['first_name'] || $this->currentUser['last_name']) ? trim($this->currentUser['first_name'] . ' ' . $this->currentUser['last_name']) : 'Agent';
        $sysComment = "";

        if ($action === 'resolve') {
            $this->pdo->exec("UPDATE `platform_tickets` SET `status` = 'Resolved', `updated_at` = NOW() WHERE `id` IN ($idList)");
            $sysComment = "[SYSTEM] $uName mass-resolved this ticket.";
        } else if ($action === 'close') {
            $this->pdo->exec("UPDATE `platform_tickets` SET `status` = 'Closed', `updated_at` = NOW() WHERE `id` IN ($idList)");
            $sysComment = "[SYSTEM] $uName mass-closed this ticket.";
        } else if ($action === 'assign') {
            $assigneeId = (int)$value;
            if ($assigneeId) {
                $uStmt = $this->pdo->prepare("SELECT first_name, last_name, email FROM `users` WHERE `id` = ?");
                $uStmt->execute([$assigneeId]);
                $aUser = $uStmt->fetch();
                $name = ($aUser['first_name'] || $aUser['last_name']) ? trim($aUser['first_name'] . ' ' . $aUser['last_name']) : $aUser['email'];
                $this->pdo->exec("UPDATE `platform_tickets` SET `assigned_to` = $assigneeId, `updated_at` = NOW() WHERE `id` IN ($idList)");
                $sysComment = "[SYSTEM] $uName mass-assigned this ticket to $name.";
            } else {
                $this->pdo->exec("UPDATE `platform_tickets` SET `assigned_to` = NULL, `updated_at` = NOW() WHERE `id` IN ($idList)");
                $sysComment = "[SYSTEM] $uName mass-unassigned this ticket.";
            }
        }

        if ($sysComment) {
            $stmt = $this->pdo->prepare("INSERT INTO `platform_ticket_comments` (`ticket_id`, `user_id`, `comment`) VALUES (?, ?, ?)");
            foreach ($ticketIds as $tid) {
                $stmt->execute([(int)$tid, $this->currentUser['id'], $sysComment]);
            }
        }

        echo json_encode(['success' => true]);
    }

    private function uploadAttachment() {
        if (!isset($_FILES['attachment'])) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'No file uploaded']); return;
        }

        $file = $_FILES['attachment'];
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'txt', 'csv'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid file type']); return;
        }

        $uploadDir = __DIR__ . '/../../uploads/tickets/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = uniqid('tkt_') . '.' . $ext;
        $dest = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            echo json_encode(['success' => true, 'url' => '/uploads/tickets/' . $filename, 'name' => basename($file['name'])]);
        } else {
            http_response_code(500); echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
        }
    }
}
