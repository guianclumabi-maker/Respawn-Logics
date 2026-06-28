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
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id']) ? $this->currentUser['tenant_id'] : ($_SESSION['tenant_id'] ?? null);
    }

    public function handleRequest($action)
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve tenant context']);
            return;
        }
        // Allow any authenticated user to update their theme
        if ($action === 'update_theme' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            try {
                $theme = $data['theme'] ?? 'light';
                if (!in_array($theme, ['light', 'dark', 'system'])) {
                    $theme = 'light';
                }
                $stmt = $this->pdo->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
                $stmt->execute([$theme, $this->currentUser['id']]);
                $_SESSION['theme_preference'] = $theme;
                echo json_encode(['success' => true, 'theme' => $theme]);
            } catch (Exception $e) {
                http_response_code(500);
                error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
            }
            return;
        }

        // All other IAM endpoints require the Admin role
        if (!hasPermission('users.manage')) {
            // Exception for grant_support_access which can be triggered by Super Admins/Tenant Admins
            if ($action === 'grant_support_access' && hasPermission('settings.manage')) {
                // allowed
            } elseif ($action === 'change_tier' && hasPermission('settings.manage')) {
                // allowed
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                return;
            }
        }

        if ($action === 'grant_support_access' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Grant access for 24 hours
                $stmt = $this->pdo->prepare("UPDATE tenants SET support_access_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?");
                $stmt->execute([$this->tenantId]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                http_response_code(500);
                error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
            }
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

                    // Fetch roles and their scopes for each user
                    foreach ($users as &$user) {
                        $roleStmt = $this->pdo->prepare("
                            SELECT r.id, r.name, ur.scope, ur.org_unit_id 
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
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
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
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
                }
                return;
            }

            if ($action === 'templates') {
                try {
                    $stmt = $this->pdo->query("SELECT id, name, description FROM role_templates");
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                } catch (Exception $e) {
                    http_response_code(500);
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
                }
                return;
            }

            if ($action === 'permissions') {
                try {
                    $stmt = $this->pdo->query("SELECT id, permission_key, description FROM permissions");
                    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                } catch (Exception $e) {
                    http_response_code(500);
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
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
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
                }
                return;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($action === 'org_units') {
                $stmt = $this->pdo->prepare("SELECT * FROM org_units WHERE tenant_id = ?");
                $stmt->execute([$this->tenantId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                return;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);

            if ($action === 'change_tier') {
                if (!hasPermission('settings.manage')) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'settings.manage permission required']);
                    return;
                }
                
                $newMode = $data['setup_mode'] ?? null;
                require_once __DIR__ . '/../services/RoleSeederService.php';
                $config = require __DIR__ . '/../../config/rbac_tiers.php';
                
                if (!$newMode || !isset($config['tiers'][$newMode])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid tier setup mode']);
                    return;
                }

                try {
                    $this->pdo->beginTransaction();
                    
                    // Additive seeding
                    $rolesToSeed = $config['tiers'][$newMode]['roles'];
                    $roleDefinitions = $config['roles'];
                    
                    // Get existing roles
                    $stmtExisting = $this->pdo->prepare("SELECT name FROM roles WHERE tenant_id = ?");
                    $stmtExisting->execute([$this->tenantId]);
                    $existingRoles = $stmtExisting->fetchAll(PDO::FETCH_COLUMN);

                    // Get all permission IDs
                    $stmtPerms = $this->pdo->query("SELECT id, permission_key FROM permissions");
                    $allPerms = [];
                    $allPermIds = [];
                    while ($row = $stmtPerms->fetch(PDO::FETCH_ASSOC)) {
                        $allPerms[$row['permission_key']] = $row['id'];
                        $allPermIds[] = $row['id'];
                    }

                    $stmtAddRole = $this->pdo->prepare("INSERT INTO roles (tenant_id, name, description, is_system_role) VALUES (?, ?, ?, 1)");
                    $stmtLinkPerm = $this->pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");

                    foreach ($rolesToSeed as $roleName) {
                        if (in_array($roleName, $existingRoles)) continue;
                        
                        $def = $roleDefinitions[$roleName];
                        $stmtAddRole->execute([$this->tenantId, $roleName, $def['desc']]);
                        $roleId = $this->pdo->lastInsertId();

                        $permsToGrant = [];
                        if (in_array('*', $def['perms'])) {
                            $permsToGrant = $allPermIds;
                        } else {
                            foreach ($def['perms'] as $k) {
                                if (isset($allPerms[$k])) {
                                    $permsToGrant[] = $allPerms[$k];
                                }
                            }
                        }

                        foreach ($permsToGrant as $pid) {
                            $stmtLinkPerm->execute([$roleId, $pid]);
                        }
                    }
                    
                    // Update tenant setup_mode
                    $stmtUpdateTenant = $this->pdo->prepare("UPDATE tenants SET setup_mode = ?, permission_version = permission_version + 1 WHERE id = ?");
                    $stmtUpdateTenant->execute([$newMode, $this->tenantId]);
                    
                    $this->pdo->commit();
                    
                    echo json_encode(['success' => true, 'tier_config' => $config['tiers'][$newMode]]);
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    http_response_code(500);
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);

                }
                return;
            }

            if ($action === 'assign_role') {
                $user_id = $data['user_id'] ?? null;
                $role_id = $data['role_id'] ?? null;
                $scope = $data['scope'] ?? null;
                $org_unit_id = $data['org_unit_id'] ?? null;

                if (!$user_id || !$role_id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
                    return;
                }

                try {
                    $this->applyRoleAssignment($user_id, $role_id, $scope, $org_unit_id);
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    http_response_code(400); // Bad request for invalid scope/org unit
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);

                }
                return;
            }

            if ($action === 'save_org_unit') {
                $stmtTier = $this->pdo->prepare("SELECT setup_mode FROM tenants WHERE id = ?");
                $stmtTier->execute([$this->tenantId]);
                $setupMode = $stmtTier->fetchColumn() ?: 'Solo';
                require_once __DIR__ . '/../services/RoleSeederService.php';
                $tierConfig = RoleSeederService::getTierConfig($setupMode);
                if (!$tierConfig['org_units']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => "Org units aren't available on your current plan"]);
                    return;
                }

                $id = $data['id'] ?? 0;
                $name = $data['name'] ?? '';
                $parentId = !empty($data['parent_id']) ? $data['parent_id'] : null;
                
                if ($parentId) {
                    $stmtCheckParent = $this->pdo->prepare("SELECT id FROM org_units WHERE id = ? AND tenant_id = ?");
                    $stmtCheckParent->execute([$parentId, $this->tenantId]);
                    if (!$stmtCheckParent->fetch()) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => "Invalid parent organization unit"]);
                        return;
                    }
                }
                
                if ($id > 0) {
                    $stmt = $this->pdo->prepare("UPDATE org_units SET name = ?, parent_id = ? WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$name, $parentId, $id, $this->tenantId]);
                } else {
                    $stmt = $this->pdo->prepare("INSERT INTO org_units (tenant_id, name, parent_id) VALUES (?, ?, ?)");
                    $stmt->execute([$this->tenantId, $name, $parentId]);
                }
                echo json_encode(['success' => true]);
                return;
            }

            if ($action === 'assign_org_unit') {
                $stmtTier = $this->pdo->prepare("SELECT setup_mode FROM tenants WHERE id = ?");
                $stmtTier->execute([$this->tenantId]);
                $setupMode = $stmtTier->fetchColumn() ?: 'Solo';
                require_once __DIR__ . '/../services/RoleSeederService.php';
                $tierConfig = RoleSeederService::getTierConfig($setupMode);
                if (!$tierConfig['org_units']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => "Org units aren't available on your current plan"]);
                    return;
                }

                $userId = $data['user_id'] ?? 0;
                $orgUnitId = !empty($data['org_unit_id']) ? $data['org_unit_id'] : null;
                
                if ($orgUnitId) {
                    $chk = $this->pdo->prepare("SELECT id FROM org_units WHERE id = ? AND tenant_id = ?");
                    $chk->execute([$orgUnitId, $this->tenantId]);
                    if (!$chk->fetchColumn()) {
                        http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid org unit']); return;
                    }
                }
                $stmt = $this->pdo->prepare("UPDATE users SET org_unit_id = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$orgUnitId, $userId, $this->tenantId]);
                
                // Audit Log
                $emailStmt = $this->pdo->prepare("SELECT email FROM users WHERE id = ?");
                $emailStmt->execute([$userId]);
                $uEmail = $emailStmt->fetchColumn();
                logAction($uEmail, 'Org Unit Changed', "Assigned to org_unit_id {$orgUnitId}");
                
                echo json_encode(['success' => true]);
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
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
                }
                return;
            }

            if ($action === 'invite_user') {
                $full_name = trim($data['full_name'] ?? '');
                $email = trim($data['email'] ?? '');
                $role_id = $data['role_id'] ?? null;
                $scope = $data['scope'] ?? null;
                $org_unit_id = $data['org_unit_id'] ?? null;
                
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

                    // Temporary password
                    $tempPassword = bin2hex(random_bytes(6));
                    $password_hash = password_hash($tempPassword, PASSWORD_DEFAULT);

                    $this->pdo->beginTransaction();

                    $this->pdo->beginTransaction();

                    $stmt = $this->pdo->prepare("
                        INSERT INTO users (tenant_id, full_name, email, password_hash, employment_status, must_change_password, created_at) 
                        VALUES (?, ?, ?, ?, 'Active', 1, NOW())
                    ");
                    $stmt->execute([$this->tenantId, $full_name, $email, $password_hash]);
                    $newUserId = $this->pdo->lastInsertId();
                    
                    if ($role_id) {
                        $this->applyRoleAssignment($newUserId, $role_id, $scope, $org_unit_id);
                    }
                    
                    $this->pdo->commit();
                    
                    try {
                        require_once __DIR__ . '/../services/Mailer.php';
                        Mailer::send(
                            $email,
                            $full_name,
                            "You've been invited to Respawn Logics",
                            "<p>Hi {$full_name},</p><p>Your temporary password is: <b>{$tempPassword}</b></p><p>Please log in and change your password.</p>"
                        );
                    } catch (\Throwable $mailEx) {
                        error_log("Invite email failed: " . $mailEx->getMessage());
                    }

                    
                    logAction($this->currentUser['email'], 'User Invited', "Invited user {$email}");

                    echo json_encode(['success' => true, 'temp_password' => $tempPassword]);
                } catch (Exception $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    http_response_code(400);
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);

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
                    error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
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

    private function applyRoleAssignment($userId, $roleId, $scope, $orgUnitId)
    {
        // Ensure both user and role belong to tenant
        $checkStmt = $this->pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE id = ? AND tenant_id = ?) as u_count,
                (SELECT COUNT(*) FROM roles WHERE id = ? AND tenant_id = ?) as r_count
        ");
        $checkStmt->execute([$userId, $this->tenantId, $roleId, $this->tenantId]);
        $res = $checkStmt->fetch();
        if ($res['u_count'] == 0 || $res['r_count'] == 0) {
            throw new Exception("Invalid user or role for this tenant.");
        }

        $stmtTier = $this->pdo->prepare("SELECT setup_mode FROM tenants WHERE id = ?");
        $stmtTier->execute([$this->tenantId]);
        $setupMode = $stmtTier->fetchColumn() ?: 'Solo';
        require_once __DIR__ . '/../services/RoleSeederService.php';
        $tierConfig = RoleSeederService::getTierConfig($setupMode);

        if (empty($scope)) {
            $scope = $tierConfig['default_scope'];
        }

        $validScopes = ['self', 'team', 'department', 'branch', 'tenant'];
        if (!in_array($scope, $validScopes)) {
            throw new Exception("Invalid scope.");
        }

        if (in_array($scope, ['department', 'branch'])) {
            if (empty($orgUnitId)) {
                throw new Exception("org_unit_id is required for department or branch scope.");
            }
            $stmtCheckOrg = $this->pdo->prepare("SELECT id FROM org_units WHERE id = ? AND tenant_id = ?");
            $stmtCheckOrg->execute([$orgUnitId, $this->tenantId]);
            if (!$stmtCheckOrg->fetch()) {
                throw new Exception("Invalid org unit for this tenant.");
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO user_roles (user_id, role_id, scope, org_unit_id) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE scope = VALUES(scope), org_unit_id = VALUES(org_unit_id)
        ");
        $stmt->execute([$userId, $roleId, $scope, !empty($orgUnitId) ? $orgUnitId : null]);

        // Increment permission_version to invalidate cache for this tenant
        $this->pdo->prepare("UPDATE tenants SET permission_version = permission_version + 1 WHERE id = ?")->execute([$this->tenantId]);
        
        // Audit Log
        $emailStmt = $this->pdo->prepare("SELECT email FROM users WHERE id = ?");
        $emailStmt->execute([$userId]);
        $uEmail = $emailStmt->fetchColumn();
        logAction($uEmail, 'Role Assigned/Updated', "Role ID {$roleId} assigned with scope {$scope}");
    }
}

