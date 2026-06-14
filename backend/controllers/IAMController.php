<?php

class IAMController
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
        // All IAM endpoints require the Admin role
        if (!hasPermission('users.manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($action === 'users') {
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT u.id, u.full_name, u.email, u.department, u.role as legacy_role
                        FROM users u
                        WHERE u.tenant_id = ?
                    ");
                    $stmt->execute([$this->tenantId]);
                    $users = $stmt->fetchAll();

                    // Fetch roles for each user
                    foreach ($users as &$user) {
                        $roleStmt = $this->pdo->prepare("
                            SELECT r.id, r.name 
                            FROM roles r
                            JOIN user_roles ur ON r.id = ur.role_id
                            WHERE ur.user_id = ?
                        ");
                        $roleStmt->execute([$user['id']]);
                        $user['roles'] = $roleStmt->fetchAll();
                    }

                    echo json_encode(['success' => true, 'data' => $users]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                return;
            }

            if ($action === 'update_theme') {
                try {
                    $input = json_decode(file_get_contents('php://input'), true) ?? [];
                    $theme = $input['theme'] ?? 'system';
                    if (!in_array($theme, ['light', 'dark', 'system'])) {
                        $theme = 'system';
                    }
                    
                    // Fail-safe table modification (ignores error if column exists)
                    try {
                        $this->pdo->exec("ALTER TABLE `users` ADD COLUMN `theme_preference` ENUM('light', 'dark', 'system') DEFAULT 'dark'");
                    } catch (Exception $e) {}

                    $stmt = $this->pdo->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
                    $stmt->execute([$theme, $this->currentUser['id']]);
                    
                    $_SESSION['theme_preference'] = $theme;
                    
                    echo json_encode(['success' => true, 'theme' => $theme]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                return;
            }

            if ($action === 'roles') {
                try {
                    $stmt = $this->pdo->prepare("SELECT id, name, description, is_system_role FROM roles WHERE tenant_id = ?");
                    $stmt->execute([$this->tenantId]);
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                return;
            }

            if ($action === 'templates') {
                try {
                    $stmt = $this->pdo->query("SELECT id, name, description FROM role_templates");
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                return;
            }

            if ($action === 'permissions') {
                try {
                    $stmt = $this->pdo->query("SELECT id, permission_key, description FROM permissions");
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                return;
            }

            if ($action === 'role_permissions') {
                $role_id = $_GET['role_id'] ?? null;
                if (!$role_id) {
                    echo json_encode(['success' => false, 'error' => 'Missing role ID']);
                    return;
                }
                try {
                    // Check if role belongs to tenant
                    $check = $this->pdo->prepare("SELECT id FROM roles WHERE id = ? AND tenant_id = ?");
                    $check->execute([$role_id, $this->tenantId]);
                    if (!$check->fetch()) {
                        echo json_encode(['success' => false, 'error' => 'Role not found']);
                        return;
                    }

                    $stmt = $this->pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
                    $stmt->execute([$role_id]);
                    $perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    echo json_encode(['success' => true, 'data' => $perms]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                return;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($action === 'assign_role') {
                $user_id = $data['user_id'] ?? null;
                $role_id = $data['role_id'] ?? null;

                if (!$user_id || !$role_id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
                    return;
                }

                try {
                    // Ensure both user and role belong to tenant
                    $checkStmt = $this->pdo->prepare("
                        SELECT 
                            (SELECT COUNT(*) FROM users WHERE id = ? AND tenant_id = ?) as u_count,
                            (SELECT COUNT(*) FROM roles WHERE id = ? AND tenant_id = ?) as r_count
                    ");
                    $checkStmt->execute([$user_id, $this->tenantId, $role_id, $this->tenantId]);
                    $res = $checkStmt->fetch();
                    if ($res['u_count'] == 0 || $res['r_count'] == 0) {
                        throw new Exception("Invalid user or role for this tenant.");
                    }

                    $stmt = $this->pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $role_id]);
                    
                    // Audit Log
                    $emailStmt = $this->pdo->prepare("SELECT email FROM users WHERE id = ?");
                    $emailStmt->execute([$user_id]);
                    $uEmail = $emailStmt->fetchColumn();
                    logAction($uEmail, 'Role Assigned', "Assigned role_id {$role_id}");

                    // Increment permission_version to invalidate cache for this tenant
                    $this->pdo->prepare("UPDATE tenants SET permission_version = permission_version + 1 WHERE id = ?")->execute([$this->tenantId]);

                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                return;
            }

            if ($action === 'remove_role') {
                $user_id = $data['user_id'] ?? null;
                $role_id = $data['role_id'] ?? null;

                if (!$user_id || !$role_id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
                    return;
                }

                try {
                    $stmt = $this->pdo->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ? AND 
                        (SELECT tenant_id FROM users WHERE id = ?) = ?");
                    $stmt->execute([$user_id, $role_id, $user_id, $this->tenantId]);
                    
                    // Audit Log
                    $emailStmt = $this->pdo->prepare("SELECT email FROM users WHERE id = ?");
                    $emailStmt->execute([$user_id]);
                    $uEmail = $emailStmt->fetchColumn();
                    logAction($uEmail, 'Role Removed', "Removed role_id {$role_id}");

                    // Increment permission_version
                    $this->pdo->prepare("UPDATE tenants SET permission_version = permission_version + 1 WHERE id = ?")->execute([$this->tenantId]);

                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                return;
            }

            if ($action === 'invite_user') {
                $full_name = trim($data['full_name'] ?? '');
                $email = trim($data['email'] ?? '');
                
                if (empty($full_name) || empty($email)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Name and email are required']);
                    return;
                }

                try {
                    // Check if email exists
                    $stmtCheck = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ?");
                    $stmtCheck->execute([$email, $this->tenantId]);
                    if ($stmtCheck->fetch()) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'A user with this email already exists in your tenant.']);
                        return;
                    }

                    // Default password hash (password123)
                    $password_hash = password_hash('password123', PASSWORD_DEFAULT);

                    $stmt = $this->pdo->prepare("
                        INSERT INTO users (tenant_id, full_name, email, password_hash, employment_status, created_at) 
                        VALUES (?, ?, ?, ?, 'Active', NOW())
                    ");
                    $stmt->execute([$this->tenantId, $full_name, $email, $password_hash]);
                    
                    logAction($this->currentUser['email'], 'User Invited', "Invited user {$email}");

                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                return;
            }

            if ($action === 'create_from_template') {
                $template_id = $data['template_id'] ?? null;
                $role_name = $data['role_name'] ?? null;
                
                if (!$template_id || !$role_name) {
                    echo json_encode(['success' => false, 'error' => 'Missing template ID or role name']);
                    return;
                }
                
                try {
                    $this->pdo->beginTransaction();
                    
                    // Create the new role
                    $stmt = $this->pdo->prepare("INSERT INTO roles (tenant_id, name, description, is_system_role) VALUES (?, ?, 'Created from template', 0)");
                    $stmt->execute([$this->tenantId, $role_name]);
                    $newRoleId = $this->pdo->lastInsertId();
                    
                    // Copy permissions
                    $permStmt = $this->pdo->prepare("
                        INSERT INTO role_permissions (role_id, permission_id)
                        SELECT ?, permission_id FROM role_template_permissions WHERE template_id = ?
                    ");
                    $permStmt->execute([$newRoleId, $template_id]);
                    
                    $this->pdo->commit();
                    
                    logAction($this->currentUser['email'], 'ROLE_CREATED', "Role $role_name created from template ID $template_id");
                    echo json_encode(['success' => true, 'role_id' => $newRoleId]);
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                return;
            }

            if ($action === 'save_role_permissions') {
                $role_id = $data['role_id'] ?? null;
                $permissions = $data['permissions'] ?? [];
                
                if (!$role_id) {
                    echo json_encode(['success' => false, 'error' => 'Missing role ID']);
                    return;
                }

                try {
                    $this->pdo->beginTransaction();

                    // Check if role belongs to tenant and is NOT a system role (cannot modify system roles)
                    $check = $this->pdo->prepare("SELECT id, is_system_role FROM roles WHERE id = ? AND tenant_id = ?");
                    $check->execute([$role_id, $this->tenantId]);
                    $role = $check->fetch();
                    if (!$role) {
                        throw new Exception("Role not found");
                    }
                    if ($role['is_system_role']) {
                        throw new Exception("Cannot modify system roles");
                    }

                    // Delete existing permissions for this role
                    $delStmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                    $delStmt->execute([$role_id]);

                    // Insert new permissions
                    if (!empty($permissions)) {
                        $insertStmt = $this->pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                        foreach ($permissions as $pid) {
                            $insertStmt->execute([$role_id, $pid]);
                        }
                    }

                    $this->pdo->commit();
                    logAction($this->currentUser['email'], 'ROLE_UPDATED', "Permissions updated for role ID $role_id");
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                return;
            }
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}
