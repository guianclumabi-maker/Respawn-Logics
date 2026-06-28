<?php

class SaaSStaffController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser();
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id']) ? $this->currentUser['tenant_id'] : ($_SESSION['tenant_id'] ?? null);
    }

    public function handleRequest($action)
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve tenant context']);
            return;
        }
        // Only Platform_Admin can manage internal staff
        if (!hasRole('Platform_Admin')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($action === 'list') {
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT id, full_name, email, role, employment_status 
                        FROM users 
                        WHERE (tenant_id IS NULL OR tenant_id = '') 
                        AND role IN ('Platform_Admin', 'Support_Agent', 'Implementation_Specialist')
                    ");
                    $stmt->execute();
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                } catch (Exception $e) {
                    http_response_code(500);
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
                }
                return;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);

            if ($action === 'create') {
                $first_name = trim($data['first_name'] ?? '');
                $last_name = trim($data['last_name'] ?? '');
                $email = trim($data['email'] ?? '');
                $password = $data['password'] ?? '';
                $role = $data['role'] ?? 'Support_Agent';
                
                if (!$first_name || !$last_name || !$email || !$password) {
                    echo json_encode(['success' => false, 'error' => 'All fields are required.']);
                    return;
                }
                
                $valid_roles = ['Platform_Admin', 'Support_Agent', 'Implementation_Specialist'];
                if (!in_array($role, $valid_roles)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid internal role specified.']);
                    return;
                }

                try {
                    // Check if email exists globally
                    $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        echo json_encode(['success' => false, 'error' => 'Email already exists.']);
                        return;
                    }

                    $full_name = $first_name . ' ' . $last_name;
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $insertStmt = $this->pdo->prepare("
                        INSERT INTO users (
                            tenant_id, first_name, last_name, full_name, email, password_hash, role, employment_status, tier
                        ) VALUES (NULL, ?, ?, ?, ?, ?, ?, 'Active', 1.0)
                    ");
                    
                    $insertStmt->execute([
                        $first_name, $last_name, $full_name, $email, $hash, $role
                    ]);

                    echo json_encode(['success' => true, 'message' => 'Internal staff created successfully.']);
                } catch (Exception $e) {
                    http_response_code(500);
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
                }
                return;
            }
            
            if ($action === 'delete') {
                $user_id = $data['user_id'] ?? null;
                
                if (!$user_id) {
                    echo json_encode(['success' => false, 'error' => 'User ID is required.']);
                    return;
                }
                
                // Prevent deleting yourself
                if ($user_id == $this->currentUser['id']) {
                    echo json_encode(['success' => false, 'error' => 'You cannot delete your own active session.']);
                    return;
                }
                
                // Failsafe: Prevent deleting the Master Admin
                if ($user_id == 901) {
                    echo json_encode(['success' => false, 'error' => 'Permission Denied: The Master Admin account cannot be deleted or modified.']);
                    return;
                }

                try {
                    // Ensure the target user is actually internal (tenant_id IS NULL)
                    $checkStmt = $this->pdo->prepare("SELECT tenant_id FROM users WHERE id = ?");
                    $checkStmt->execute([$user_id]);
                    $target = $checkStmt->fetch();
                    
                    if (!$target || $target['tenant_id'] !== null) {
                        echo json_encode(['success' => false, 'error' => 'Cannot delete external sandbox users from this panel.']);
                        return;
                    }
                    
                    $delStmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
                    $delStmt->execute([$user_id]);
                    
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    http_response_code(500);
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
                }
                return;
            }
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}
