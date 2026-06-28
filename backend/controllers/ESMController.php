<?php
require_once __DIR__ . '/../utils/Storage.php';

class ESMController
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

    private function isAgent() {
        return hasPermission('esm.manage');
    }

    private function logSystemComment($ticketId, $message) {
        $stmt = $this->pdo->prepare("INSERT INTO `ticket_comments` (`ticket_id`, `comment_type`, `comment`) VALUES (?, 'System', ?)");
        $stmt->execute([$ticketId, $message]);
    }

    public function handleRequest($action)
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve tenant context']);
            return;
        }
        if (!$this->currentUser || !isset($this->currentUser['id'])) {
            echo json_encode(['success' => false, 'error' => 'User not authenticated or missing ID']);
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
                case 'ticket_types':
                    $stmt = $this->pdo->prepare("SELECT * FROM `service_ticket_types` WHERE `tenant_id` = ? AND `is_confidential` = 0");
                    $stmt->execute([$this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'elr_ticket_types':
                    $stmt = $this->pdo->prepare("SELECT * FROM `service_ticket_types` WHERE `tenant_id` = ? AND (`is_confidential` = 1 OR `name` LIKE '%HR%')");
                    $stmt->execute([$this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'all_ticket_types':
                    if (!$this->isAgent()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $stmt = $this->pdo->prepare("SELECT * FROM `service_ticket_types` WHERE `tenant_id` = ?");
                    $stmt->execute([$this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'teams':
                    $stmt = $this->pdo->prepare("SELECT * FROM `service_teams` WHERE `tenant_id` = ?");
                    $stmt->execute([$this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'my_tickets':
                    $stmt = $this->pdo->prepare("
                        SELECT st.*, tt.name as type_name, tm.name as team_name
                        FROM `service_tickets` st
                        JOIN `service_ticket_types` tt ON st.ticket_type_id = tt.id
                        LEFT JOIN `service_teams` tm ON st.assigned_team_id = tm.id
                        WHERE st.employee_id = ? AND st.tenant_id = ? AND tt.is_confidential = 0
                        ORDER BY st.created_at DESC
                    ");
                    $stmt->execute([$this->currentUser['id'], $this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'my_elr_cases':
                    $stmt = $this->pdo->prepare("
                        SELECT st.*, tt.name as type_name, tm.name as team_name
                        FROM `service_tickets` st
                        JOIN `service_ticket_types` tt ON st.ticket_type_id = tt.id
                        LEFT JOIN `service_teams` tm ON st.assigned_team_id = tm.id
                        WHERE st.employee_id = ? AND st.tenant_id = ? AND (tt.is_confidential = 1 OR tm.name = 'Employee Relations' OR tt.name LIKE '%HR%')
                        ORDER BY st.created_at DESC
                    ");
                    $stmt->execute([$this->currentUser['id'], $this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'create_ticket':
                    $this->createTicket($input);
                    break;

                case 'ticket_details':
                    $this->getTicketDetails();
                    break;

                case 'add_comment':
                    $this->addComment($input);
                    break;
                case 'download_attachment':
                    $this->downloadAttachment();
                    break;
                case 'agent_queue':
                    if (!$this->isAgent()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
                    $stmt = $this->pdo->prepare("
                        SELECT st.*, tt.name as type_name, tm.name as team_name, u.full_name as employee_name
                        FROM `service_tickets` st
                        JOIN `service_ticket_types` tt ON st.ticket_type_id = tt.id
                        JOIN `users` u ON st.employee_id = u.id
                        WHERE st.tenant_id = ?
                        ORDER BY st.id DESC
                    ");
                    $stmt->execute([$this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                    break;

                case 'update_ticket':
                    $this->updateTicket($input);
                    break;

                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown action']);
                    break;
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    private function createTicket($input)
    {
        $typeId = intval($input['ticket_type_id'] ?? 0);
        $subject = $input['subject'] ?? '';
        $desc = $input['description'] ?? '';
        $priority = $input['priority'] ?? 'Medium';

        if (!$typeId || !$subject) { echo json_encode(['success' => false, 'error' => 'Missing fields']); return; }

        try {
            $this->pdo->beginTransaction();

            $tStmt = $this->pdo->prepare("SELECT default_team_id FROM service_ticket_types WHERE id = ?");
            $tStmt->execute([$typeId]);
            $defaultTeam = $tStmt->fetchColumn();

            $rand = strtoupper(substr(md5(uniqid()), 0, 5));
            $ticketNo = "ESM-" . date('ym') . "-" . $rand;

            $slaDue = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $targetEmployeeId = $this->currentUser['id'];
            if ($this->isAgent() && !empty($input['employee_id'])) {
                $targetEmployeeId = intval($input['employee_id']);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO `service_tickets` 
                (`tenant_id`, `ticket_number`, `ticket_type_id`, `employee_id`, `assigned_team_id`, `subject`, `description`, `priority`, `sla_due_at`, `created_by`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$this->tenantId, $ticketNo, $typeId, $targetEmployeeId, $defaultTeam, $subject, $desc, $priority, $slaDue, $this->currentUser['id']]);
            $ticketId = $this->pdo->lastInsertId();

            if ($targetEmployeeId === $this->currentUser['id']) {
                $this->logSystemComment($ticketId, "Ticket created by Employee.");
            } else {
                $this->logSystemComment($ticketId, "Case opened by HR/Admin.");
            }
            
            $this->pdo->commit();
            echo json_encode(['success' => true, 'ticket_id' => $ticketId]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function getTicketDetails()
    {
        $ticketId = intval($_GET['id'] ?? 0);
        
        $stmt = $this->pdo->prepare("
            SELECT st.*, tt.name as type_name, tt.is_confidential, tm.name as team_name, u.full_name as employee_name, a.full_name as agent_name
            FROM `service_tickets` st
            JOIN `service_ticket_types` tt ON st.ticket_type_id = tt.id
            JOIN `users` u ON st.employee_id = u.id
            LEFT JOIN `service_teams` tm ON st.assigned_team_id = tm.id
            LEFT JOIN `users` a ON st.assigned_to_user_id = a.id
            WHERE st.id = ? AND st.tenant_id = ?
        ");
        $stmt->execute([$ticketId, $this->tenantId]);
        $ticket = $stmt->fetch();

        if (!$ticket) { echo json_encode(['success' => false, 'error' => 'Not found']); return; }

        if (!$this->isAgent() && $ticket['is_confidential'] == 1 && $ticket['employee_id'] !== $this->currentUser['id']) {
            echo json_encode(['success' => false, 'error' => 'Denied - Confidential Case']); return;
        }
        if ($ticket['employee_id'] !== $this->currentUser['id'] && !$this->isAgent()) {
            echo json_encode(['success' => false, 'error' => 'Denied']); return;
        }

        $cStmt = $this->pdo->prepare("
            SELECT tc.*, u.full_name as author_name 
            FROM `ticket_comments` tc
            LEFT JOIN `users` u ON tc.user_id = u.id
            WHERE tc.ticket_id = ?
            ORDER BY tc.created_at ASC
        ");
        $cStmt->execute([$ticketId]);
        $allComments = $cStmt->fetchAll();

        $comments = [];
        foreach ($allComments as $c) {
            if ($c['comment_type'] === 'Internal' && !$this->isAgent()) {
                continue; 
            }
            if ($c['attachment_url']) {
                $c['attachment_url'] = '../api/index.php?route=esm&action=download_attachment&id=' . $c['id'];
            }
            $comments[] = $c;
        }

        echo json_encode(['success' => true, 'data' => ['ticket' => $ticket, 'comments' => $comments]]);
    }

    private function addComment($input)
    {
        $ticketId = intval($input['ticket_id'] ?? 0);
        $comment = $input['comment'] ?? '';
        $type = $input['comment_type'] ?? 'Public'; 

        $tStmt = $this->pdo->prepare("SELECT employee_id, status FROM service_tickets WHERE id = ? AND tenant_id = ?");
        $tStmt->execute([$ticketId, $this->tenantId]);
        $t = $tStmt->fetch();
        if (!$t || ($t['employee_id'] !== $this->currentUser['id'] && !$this->isAgent())) {
            echo json_encode(['success' => false, 'error' => 'Denied']); return;
        }

        if (!$this->isAgent()) $type = 'Public';

        $attachmentUrl = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
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
            $storageDir = rtrim($storageBase, '/') . '/tenant_' . $this->tenantId . '/tickets';
            if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

            $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mime];
            $dest = $storageDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $attachmentUrl = 'tenant_' . $this->tenantId . '/tickets/' . $filename;
            }
        }

        $stmt = $this->pdo->prepare("INSERT INTO `ticket_comments` (`ticket_id`, `user_id`, `comment_type`, `comment`, `created_by`, `attachment_url`) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ticketId, $this->currentUser['id'], $type, $comment, $this->currentUser['id'], $attachmentUrl]);

        if ($this->isAgent() && $type === 'Public') {
            $this->pdo->prepare("UPDATE `service_tickets` SET `first_response_at` = CURRENT_TIMESTAMP WHERE `id` = ? AND `first_response_at` IS NULL")->execute([$ticketId]);
        }

        echo json_encode(['success' => true]);
    }

    private function updateTicket($input)
    {
        if (!$this->isAgent()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
        $ticketId = intval($input['ticket_id'] ?? 0);
        $status = $input['status'] ?? null;
        $teamId = $input['assigned_team_id'] ?? null;
        $assignedTo = $input['assigned_to_user_id'] ?? null;

        $updates = [];
        $params = [];

        if ($status !== null) {
            $updates[] = "`status` = ?"; $params[] = $status;
            if ($status === 'Resolved' || $status === 'Closed') {
                $updates[] = "`resolved_at` = CURRENT_TIMESTAMP";
            }
        }
        if ($teamId !== null) { $updates[] = "`assigned_team_id` = ?"; $params[] = $teamId ?: null; }
        if ($assignedTo !== null) { $updates[] = "`assigned_to_user_id` = ?"; $params[] = $assignedTo ?: null; }

        if (count($updates) > 0) {
            $params[] = $ticketId;
            $params[] = $this->tenantId;
            $sql = "UPDATE `service_tickets` SET " . implode(", ", $updates) . " WHERE id = ? AND tenant_id = ?";
            $this->pdo->prepare($sql)->execute($params);
            $this->logSystemComment($ticketId, "Ticket attributes updated by Agent.");
        }

        echo json_encode(['success' => true]);
    }

    private function downloadAttachment()
    {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo "Missing ID"; return; }

        $stmt = $this->pdo->prepare("
            SELECT tc.attachment_url, st.employee_id, st.is_confidential 
            FROM `ticket_comments` tc
            JOIN `service_tickets` st ON tc.ticket_id = st.id
            WHERE tc.id = ? AND st.tenant_id = ?
        ");
        $stmt->execute([$id, $this->tenantId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment || empty($comment['attachment_url'])) {
            http_response_code(404); echo "Attachment not found"; return;
        }

        if (!$this->isAgent() && $comment['is_confidential'] == 1 && $comment['employee_id'] !== $this->currentUser['id']) {
            http_response_code(403); echo "Denied - Confidential Case"; return;
        }
        if ($comment['employee_id'] !== $this->currentUser['id'] && !$this->isAgent()) {
            http_response_code(403); echo "Access denied"; return;
        }

        $storageBase = \App\Utils\Storage::resolveStorageBase(false, false);
        $dbPath = preg_replace('/^\/?uploads\/tickets\//', '', $comment['attachment_url']);
        $fullPath = rtrim($storageBase, '/') . '/' . ltrim($dbPath, '/');

        if (!file_exists($fullPath)) {
            http_response_code(404); echo "File missing from storage"; return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="attachment_' . $id . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }
}
