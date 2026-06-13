<?php

class ExpensesController
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $input = $_POST;
            if (empty($input) && $_SERVER['REQUEST_METHOD'] === 'POST') {
                 $input = json_decode(file_get_contents('php://input'), true) ?? [];
            }
        }

        switch ($action) {
            case 'categories':
                $this->getCategories();
                break;

            case 'my_claims':
                $this->getMyClaims();
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
                $this->approveClaim($input);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
                break;
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
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
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
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
            $uploadDir = __DIR__ . '/../../uploads/receipts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $fileName = 'receipt_' . uniqid() . '.' . $ext;
            $dest = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $dest)) {
                $receiptPath = '/uploads/receipts/' . $fileName;
            }
        }

        $stmt = $this->pdo->prepare("INSERT INTO `expense_claims` (`tenant_id`, `employee_id`, `category_id`, `amount`, `expense_date`, `description`, `receipt_path`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $this->currentUser['id'], $categoryId, $amount, $date, $desc, $receiptPath]);
        echo json_encode(['success' => true]);
    }

    private function getManagerPending()
    {
        if (!$this->isManager() && !$this->isFinanceOrHR()) { echo json_encode(['success' => false, 'error' => 'Denied']); return; }
        // Fetch claims for employees whose manager is the current user
        $stmt = $this->pdo->prepare("
            SELECT ec.*, cat.name as category_name, u.full_name as employee_name
            FROM `expense_claims` ec
            JOIN `expense_categories` cat ON ec.category_id = cat.id
            JOIN `users` u ON ec.employee_id = u.id
            WHERE u.manager_id = ? AND ec.tenant_id = ? AND ec.status = 'Pending Manager'
            ORDER BY ec.id ASC
        ");
        $stmt->execute([$this->currentUser['id'], $this->tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
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
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
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
}
