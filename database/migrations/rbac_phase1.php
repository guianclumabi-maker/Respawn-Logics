<?php
require_once __DIR__ . '/../../config/db.php';

try {
    $pdo->beginTransaction();

    // 1. Add setup_mode to tenants
    try {
        $pdo->exec("ALTER TABLE tenants ADD COLUMN setup_mode ENUM('Solo', 'Small', 'Mid', 'Enterprise') DEFAULT 'Solo' AFTER status");
        echo "Added setup_mode to tenants.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) throw $e;
        echo "setup_mode already exists in tenants.\n";
    }

    // 2. Add scope and org_unit_id to user_roles
    try {
        $pdo->exec("ALTER TABLE user_roles ADD COLUMN scope ENUM('self', 'team', 'department', 'branch', 'tenant') DEFAULT 'tenant' AFTER role_id");
        $pdo->exec("ALTER TABLE user_roles ADD COLUMN org_unit_id INT NULL AFTER scope");
        echo "Added scope and org_unit_id to user_roles.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) throw $e;
        echo "scope and org_unit_id already exist in user_roles.\n";
    }

    // 3. New granular permissions
    $newPermissions = [
        ['group' => 'Compensation', 'key' => 'payroll.run', 'desc' => 'Generate and process payroll data'],
        ['group' => 'Compensation', 'key' => 'payroll.approve', 'desc' => 'Finalize and disburse payroll'],
        ['group' => 'Compensation', 'key' => 'benefits.view', 'desc' => 'View employee benefits'],
        ['group' => 'Compensation', 'key' => 'benefits.approve', 'desc' => 'Approve benefit enrollments'],
        ['group' => 'Compensation', 'key' => 'compensation.view', 'desc' => 'View sensitive compensation data'],
        ['group' => 'Compensation', 'key' => 'compensation.approve', 'desc' => 'Approve compensation changes']
    ];

    $stmtGroup = $pdo->prepare("SELECT id FROM permission_groups WHERE name = ?");
    $stmtPerm = $pdo->prepare("INSERT IGNORE INTO permissions (permission_group_id, permission_key, description) VALUES (?, ?, ?)");

    foreach ($newPermissions as $p) {
        $stmtGroup->execute([$p['group']]);
        $groupId = $stmtGroup->fetchColumn();
        if ($groupId) {
            $stmtPerm->execute([$groupId, $p['key'], $p['desc']]);
        }
    }
    echo "Added granular permissions.\n";

    // 4. Ensure System Roles exist for all tenants
    $systemRoles = [
        ['name' => 'Account Owner', 'desc' => 'Ultimate tenant owner. Un-removable full access.'],
        ['name' => 'Payroll Approver', 'desc' => 'Can review and finalize payroll (Separation of Duties).']
    ];

    $stmtGetTenants = $pdo->query("SELECT id FROM tenants");
    $tenants = $stmtGetTenants->fetchAll(PDO::FETCH_COLUMN);

    $stmtAddRole = $pdo->prepare("INSERT IGNORE INTO roles (tenant_id, name, description, is_system_role) VALUES (?, ?, ?, 1)");
    $stmtGetRole = $pdo->prepare("SELECT id FROM roles WHERE tenant_id = ? AND name = ?");
    
    // Permission lookup for roles
    $stmtGetPerms = $pdo->prepare("SELECT id, permission_key FROM permissions");
    $stmtGetPerms->execute();
    $allPerms = [];
    while ($row = $stmtGetPerms->fetch(PDO::FETCH_ASSOC)) {
        $allPerms[$row['permission_key']] = $row['id'];
    }

    $stmtLinkRolePerm = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");

    foreach ($tenants as $tenantId) {
        foreach ($systemRoles as $sr) {
            $stmtAddRole->execute([$tenantId, $sr['name'], $sr['desc']]);
            
            // If it's Payroll Approver, attach payroll.approve, payroll.view
            if ($sr['name'] === 'Payroll Approver') {
                $stmtGetRole->execute([$tenantId, 'Payroll Approver']);
                $roleId = $stmtGetRole->fetchColumn();
                
                if ($roleId) {
                    $keysToGrant = ['payroll.view', 'payroll.approve'];
                    foreach ($keysToGrant as $k) {
                        if (isset($allPerms[$k])) {
                            $stmtLinkRolePerm->execute([$roleId, $allPerms[$k]]);
                        }
                    }
                }
            }
        }
    }
    echo "Ensured System Roles (Account Owner, Payroll Approver) exist.\n";

    // 5. Migration Strategy: Grant run + approve to legacy roles that had payroll.manage
    $stmtLegacyRoles = $pdo->prepare("
        SELECT rp.role_id 
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        WHERE p.permission_key = 'payroll.manage'
    ");
    $stmtLegacyRoles->execute();
    $legacyRoles = $stmtLegacyRoles->fetchAll(PDO::FETCH_COLUMN);

    $runPermId = $allPerms['payroll.run'] ?? null;
    $approvePermId = $allPerms['payroll.approve'] ?? null;
    $viewPermId = $allPerms['payroll.view'] ?? null;

    if ($runPermId && $approvePermId && $viewPermId) {
        foreach ($legacyRoles as $roleId) {
            $stmtLinkRolePerm->execute([$roleId, $runPermId]);
            $stmtLinkRolePerm->execute([$roleId, $approvePermId]);
            $stmtLinkRolePerm->execute([$roleId, $viewPermId]);
        }
        echo "Migrated legacy payroll.manage roles to run+approve (Continuity Bridge).\n";
    }

    // 6. Assign "Account Owner" to existing Admin users (or the first admin of the tenant)
    $stmtFindAdmins = $pdo->query("SELECT id, tenant_id FROM users WHERE role = 'Super_Admin' OR role = 'admin' ORDER BY id ASC");
    $admins = $stmtFindAdmins->fetchAll(PDO::FETCH_ASSOC);

    $stmtAssignUserRole = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id, scope) VALUES (?, ?, 'tenant')");

    $ownersAssigned = [];
    foreach ($admins as $admin) {
        $tId = $admin['tenant_id'];
        if (!isset($ownersAssigned[$tId])) {
            $stmtGetRole->execute([$tId, 'Account Owner']);
            $ownerRoleId = $stmtGetRole->fetchColumn();
            if ($ownerRoleId) {
                $stmtAssignUserRole->execute([$admin['id'], $ownerRoleId]);
                $ownersAssigned[$tId] = true;
            }
        }
    }
    echo "Assigned Account Owner role to initial tenant creators.\n";

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    echo "Phase 1 Migrations completed successfully.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
}
