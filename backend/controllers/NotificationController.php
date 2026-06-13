<?php

class NotificationController
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
            case 'fetch_unread':
                $this->fetchUnread();
                break;
            case 'mark_read':
                $this->markRead();
                break;
            case 'mark_all_read':
                $this->markAllRead();
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
                break;
        }
    }

    private function fetchUnread()
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM notifications 
            WHERE tenant_id = ? AND user_email = ? AND is_read = FALSE 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$this->tenantId, $this->currentUser['email']]);
        $notifications = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $notifications]);
    }

    private function markRead()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = $input['id'] ?? 0;

        if ($id > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = TRUE 
                WHERE id = ? AND tenant_id = ? AND user_email = ?
            ");
            $stmt->execute([$id, $this->tenantId, $this->currentUser['email']]);
        }

        echo json_encode(['success' => true]);
    }

    private function markAllRead()
    {
        $stmt = $this->pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE tenant_id = ? AND user_email = ?
        ");
        $stmt->execute([$this->tenantId, $this->currentUser['email']]);

        echo json_encode(['success' => true]);
    }
}
