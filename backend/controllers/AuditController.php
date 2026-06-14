<?php

class AuditController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser() ?: null;
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id']) ? $this->currentUser['tenant_id'] : ($_SESSION['tenant_id'] ?? '1');
    }

    public function handleRequest($action)
    {
        if (!$this->currentUser || !hasPermission('audit.view')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            return;
        }

        switch ($action) {
            case 'fetch_logs':
                $this->fetchLogs();
                break;
            case 'fetch_actions':
                $this->fetchActions();
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
                break;
        }
    }

    private function fetchLogs()
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = ($page - 1) * $limit;

        $search = $_GET['search'] ?? '';
        $actionFilter = $_GET['action_filter'] ?? '';

        $sql = "
            SELECT a.id, a.user_email, a.action, a.details, a.created_at, u.full_name, u.job_title, u.profile_image 
            FROM audit_logs a
            LEFT JOIN users u ON a.user_email = u.email
            WHERE a.tenant_id = :tenant_id
        ";
        
        $countSql = "SELECT COUNT(*) as total FROM audit_logs a WHERE a.tenant_id = :tenant_id";
        
        $params = [':tenant_id' => $this->tenantId];

        if (!empty($search)) {
            $sql .= " AND (a.user_email LIKE :search OR a.details LIKE :search OR u.full_name LIKE :search)";
            $countSql .= " AND (a.user_email LIKE :search OR a.details LIKE :search OR u.full_name LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if (!empty($actionFilter)) {
            $sql .= " AND a.action = :action";
            $countSql .= " AND a.action = :action";
            $params[':action'] = $actionFilter;
        }

        $sql .= " ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset";

        // Count Total
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $totalRows = $countStmt->fetch()['total'];

        // Fetch Data
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();

        echo json_encode([
            'success' => true, 
            'data' => $logs, 
            'meta' => [
                'total' => $totalRows,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalRows / $limit)
            ]
        ]);
    }

    private function fetchActions()
    {
        // Get unique action types for the dropdown filter
        $stmt = $this->pdo->prepare("SELECT DISTINCT action FROM audit_logs WHERE tenant_id = ? ORDER BY action ASC");
        $stmt->execute([$this->tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
    }
}
