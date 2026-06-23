<?php
require_once __DIR__ . '/../utils/Storage.php';

class ExpensesController
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

    private function isManager() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `manager_id` = ? AND `tenant_id` = ?");
        $stmt->execute([$this->currentUser['id'], $this->tenantId]);
        return $stmt->fetchColumn() > 0;
    }

    private function isFinanceOrHR() {
        return hasPermission('expenses.manage');
    }

    public function handleRequest($action)
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve tenant context']);
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
                case 'categories':
                    $this->getCategories();
                    break;

                case 'my_claims':
                    $this->getMyClaims();
                    break;

                case 'download_receipt':
                    $this->downloadReceipt();
                    break;

                case 'submit_claim':
                    $this->submitClaim($input);
                    break;

                case 'manager_pending':
                    $this->getManagerPending();
                    break;

                case 'finance_pending':
                    $this->getFinancePending();
                    break;

                case 'approve_claim':
                    if (!hasPermission('expenses.manage')) { http_response_code(403); echo json_encode(['success'=>false, 'error'=>'Denied']); return; }
                $this->approveClaim($input);
                    break;

                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown action']);
                    break;
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    private function getCategories()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `expense_categories` WHERE `tenant_id` = ?");
        $stmt->execute([$this->tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    private function getMyClaims()
    {
        $stmt = $this->pdo->prepare("
            SELECT ec.*, cat.name as category_name
            FROM `expense_claims` ec
            JOIN `expense_categories` cat ON ec.category_id = cat.id
            WHERE ec.employee_id = ? AND ec.tenant_id = ?
            ORDER BY ec.id DESC
        ");
        $stmt->execute([$this->currentUser['id'], $this->tenantId]);
        $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($claims as &$c) {
            if ($c['receipt_path']) {
                $c['receipt_url'] = '../api/index.php?route=expenses&action=download_receipt&id=' . $c['id'];
            }
        }
        echo json_encode(['success' => true, 'data' => $claims]);
    }

    private function submitClaim($input)
    {
        $categoryId = $input['category_id'] ?? 0;
        $amount = floatval($input['amount'] ?? 0);
        $date = $input['expense_date'] ?? date('Y-m-d');
        $desc = $input['description'] ?? '';
        
        if ($amount <= 0 || !$categoryId) {
            echo json_encode(['success' => false, 'error' => 'Invalid amount or category.']);
            return;
        }

        // File Upload Handling
        $receiptPath = null;
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['receipt'];

            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'File exceeds 5MB limit']);
                return;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png'
            ];

            if (!array_key_exists($mime, $allowedMimes)) {
                echo json_encode(['success' => false, 'error' => 'Invalid file type. Only PDF, JPG, PNG allowed.']);
                return;
            }

            $ext = $allowedMimes[$mime];

            $storageBase = \App\Utils\Storage::resolveStorageBase(false, true);
            $storageDir = rtrim($storageBase, '/') . '/tenant_' . $this->tenantId . '/receipts';
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }
            
            $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
            $dest = $storageDir . '/' . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $receiptPath = 'tenant_' . $this->tenantId . '/receipts/' . $fileName;
            }
        }

        $stmt = $this->pdo->prepare("INSERT INTO `expense_claims` (`tenant_id`, `employee_id`, `category_id`, `amount`, `expense_date`, `description`, `receipt_path`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $this->currentUser['id'], $categoryId, $amount, $date, $desc, $receiptPath]);
        echo json_encode(['success' => true]);
    }

    private function getManagerPending()
    {
        if (!$this->isManager() && !$this->isFinanceOrHR()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
        
        require_once __DIR__ . '/../services/ScopeResolver.php';
        $scopeClause = ScopeResolver::getScopeWhereClause($this->pdo, $this->currentUser, 'u');

        $stmt = $this->pdo->prepare("
            SELECT ec.*, cat.name as category_name, u.full_name as employee_name
            FROM `expense_claims` ec
            JOIN `expense_categories` cat ON ec.category_id = cat.id
            JOIN `users` u ON ec.employee_id = u.id AND ec.tenant_id = u.tenant_id
            WHERE ec.tenant_id = :tenant_id AND ec.status = 'Pending Manager'
            $scopeClause
            ORDER BY ec.id ASC
        ");
        $stmt->execute([':tenant_id' => $this->tenantId]);
        $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($claims as &$c) {
            if ($c['receipt_path']) {
                $c['receipt_url'] = '../api/index.php?route=expenses&action=download_receipt&id=' . $c['id'];
            }
        }
        echo json_encode(['success' => true, 'data' => $claims]);
    }

    private function getFinancePending()
    {
        if (!$this->isFinanceOrHR()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
        // Fetch claims that passed Manager approval and are waiting for Finance
        $stmt = $this->pdo->prepare("
            SELECT ec.*, cat.name as category_name, u.full_name as employee_name
            FROM `expense_claims` ec
            JOIN `expense_categories` cat ON ec.category_id = cat.id
            JOIN `users` u ON ec.employee_id = u.id
            WHERE ec.tenant_id = ? AND ec.status = 'Pending Finance'
            ORDER BY ec.id ASC
        ");
        $stmt->execute([$this->tenantId]);
        $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($claims as &$c) {
            if ($c['receipt_path']) {
                $c['receipt_url'] = '../api/index.php?route=expenses&action=download_receipt&id=' . $c['id'];
            }
        }
        echo json_encode(['success' => true, 'data' => $claims]);
    }

    private function approveClaim($input)
    {
        $claimId = $input['claim_id'] ?? 0;
        $decision = $input['decision'] ?? ''; // 'Approve' or 'Reject'
        $comments = $input['comments'] ?? '';
        
        // Find claim
        $stmt = $this->pdo->prepare("SELECT * FROM `expense_claims` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$claimId, $this->tenantId]);
        $claim = $stmt->fetch();
        if (!$claim) { echo json_encode(['success' => false, 'error' => 'Claim not found.']); return; }

        $newStatus = '';
        $auditAction = '';

        if ($claim['status'] === 'Pending Manager') {
            if (!$this->isManager() && !$this->isFinanceOrHR()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
            
            require_once __DIR__ . '/../services/ScopeResolver.php';
            if (!ScopeResolver::hasScopedAccess($this->pdo, $this->currentUser, (int)$claim['employee_id'])) {
                echo json_encode(['success' => false, 'error' => 'Unauthorized']); return;
            }

            if ($decision === 'Approve') {
                $newStatus = 'Pending Finance';
                $auditAction = 'Approved by Manager';
            } else {
                $newStatus = 'Rejected';
                $auditAction = 'Rejected by Manager';
            }
        } else if ($claim['status'] === 'Pending Finance') {
            if (!$this->isFinanceOrHR()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
            if ($decision === 'Approve') {
                $newStatus = 'Finance Approved'; // Ready for payroll sync
                $auditAction = 'Approved by Finance';
            } else {
                $newStatus = 'Rejected';
                $auditAction = 'Rejected by Finance';
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Claim not in pending state.']); return;
        }

        // Update Claim Status
        $ustmt = $this->pdo->prepare("UPDATE `expense_claims` SET `status` = ? WHERE `id` = ? AND `tenant_id` = ?");
        $ustmt->execute([$newStatus, $claimId, $this->tenantId]);

        // Insert Audit Log
        $astmt = $this->pdo->prepare("INSERT INTO `expense_approvals` (`claim_id`, `approver_id`, `action`, `comments`) VALUES (?, ?, ?, ?)");
        $astmt->execute([$claimId, $this->currentUser['id'], $auditAction, $comments]);

        echo json_encode(['success' => true]);
    }

    private function downloadReceipt()
    {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo "Missing ID"; return; }

        $stmt = $this->pdo->prepare("SELECT `employee_id`, `receipt_path` FROM `expense_claims` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$id, $this->tenantId]);
        $claim = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$claim || empty($claim['receipt_path'])) {
            http_response_code(404);
            echo "Receipt not found";
            return;
        }

        // Access check: User must be Finance, the Manager of the employee, or the owner
        $isOwner = ($this->currentUser['id'] === $claim['employee_id']);
        $isFinance = $this->isFinanceOrHR();
        $isManagerOf = false;

        if (!$isOwner && !$isFinance) {
            $mgrStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `id` = ? AND `manager_id` = ? AND `tenant_id` = ?");
            $mgrStmt->execute([$claim['employee_id'], $this->currentUser['id'], $this->tenantId]);
            $isManagerOf = ($mgrStmt->fetchColumn() > 0);
        }

        if (!$isOwner && !$isFinance && !$isManagerOf) {
            http_response_code(403);
            echo "Access denied";
            return;
        }

        $storageBase = \App\Utils\Storage::resolveStorageBase(false, false);
        $dbPath = preg_replace('/^\/?uploads\/receipts\//', '', $claim['receipt_path']);
        $fullPath = rtrim($storageBase, '/') . '/' . ltrim($dbPath, '/');

        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo "File missing from storage";
            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="receipt_' . $id . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }
}
