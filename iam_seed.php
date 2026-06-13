<?php
require_once __DIR__ . '/bootstrap/app.php';
$permissionDict = require __DIR__ . '/config/permissions.php';

try {
    // 0. Create and Update tenants table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tenants (
            id INT PRIMARY KEY,
            name VARCHAR(100),
            permission_version INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
    $pdo->exec("
        INSERT IGNORE INTO tenants (id, name)
        SELECT DISTINCT tenant_id, CONCAT('Tenant ', tenant_id) FROM users WHERE tenant_id IS NOT NULL
    ");

    // 1. Create permission_groups table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS permission_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // We may need to alter permissions if it exists (from my previous run)
    $pdo->exec("
        ALTER TABLE permissions 
        ADD COLUMN IF NOT EXISTS permission_group_id INT NULL AFTER id,
        DROP COLUMN IF EXISTS module_name;
    ");

    $pdo->exec("
        ALTER TABLE permissions 
        ADD CONSTRAINT fk_permission_group 
        FOREIGN KEY (permission_group_id) REFERENCES permission_groups(id) ON DELETE CASCADE;
    ");

    // The rest of the tables (roles, role_permissions, user_roles) are correct from earlier, 
    // but just ensure they exist.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            is_system_role TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_role_name (tenant_id, name),
            INDEX idx_tenant (tenant_id)
        ) ENGINE=InnoDB;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS role_permissions (
            role_id INT NOT NULL,
            permission_id INT NOT NULL,
            PRIMARY KEY(role_id, permission_id),
            FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_roles (
            user_id INT NOT NULL,
            role_id INT NOT NULL,
            PRIMARY KEY(user_id, role_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // Add Audit Logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs_v2 (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            user_id INT NOT NULL,
            action VARCHAR(100),
            entity_type VARCHAR(100),
            entity_id INT,
            old_values JSON,
            new_values JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // Seed Permission Groups & Permissions
    foreach ($permissionDict as $groupName => $perms) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO permission_groups (name) VALUES (?)");
        $stmt->execute([$groupName]);
        
        $stmt = $pdo->prepare("SELECT id FROM permission_groups WHERE name = ?");
        $stmt->execute([$groupName]);
        $groupId = $stmt->fetchColumn();

        foreach ($perms as $permKey) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO permissions (permission_group_id, permission_key, description) VALUES (?, ?, ?)");
            $stmt->execute([$groupId, $permKey, $permKey]);
        }
    }

    // Role definitions
    $roleDefs = [
        'Admin' => [], // Will dynamically fetch all below
        'HR Manager' => ['employees.view', 'employees.edit', 'leave.approve', 'attendance.manage'],
        'Manager' => ['employees.view_team', 'leave.approve_team'],
        'Employee' => ['employees.view_self', 'leave.request'],
        'Recruiter' => ['ats.view', 'ats.edit', 'ats.create_job', 'ats.edit_job'],
        'Investigator' => ['elr.view', 'elr.investigate']
    ];

    // Fetch all permission keys for Admin
    $allPermsStmt = $pdo->query("SELECT permission_key FROM permissions");
    while ($row = $allPermsStmt->fetch()) {
        $roleDefs['Admin'][] = $row['permission_key'];
    }

    // Seed Roles & Mappings for each Tenant
    $tenants = $pdo->query("SELECT DISTINCT tenant_id FROM users")->fetchAll();
    foreach ($tenants as $t) {
        $tenantId = $t['tenant_id'];

        foreach ($roleDefs as $roleName => $rolePermKeys) {
            // Insert Role
            $stmt = $pdo->prepare("INSERT IGNORE INTO roles (tenant_id, name, is_system_role) VALUES (?, ?, 1)");
            $stmt->execute([$tenantId, $roleName]);

            // Get Role ID
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE tenant_id = ? AND name = ?");
            $stmt->execute([$tenantId, $roleName]);
            $roleId = $stmt->fetchColumn();

            // Link Permissions
            foreach ($rolePermKeys as $permKey) {
                $stmt = $pdo->prepare("SELECT id FROM permissions WHERE permission_key = ?");
                $stmt->execute([$permKey]);
                $permId = $stmt->fetchColumn();

                if ($permId) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                    $stmt->execute([$roleId, $permId]);
                }
            }
        }
    }

    // Migrate Users based on their old string role
    $roleMap = [
        'admin' => 'Admin',
        'manager' => 'Manager',
        'supervisor' => 'Manager',
        'employee' => 'Employee'
    ];

    $users = $pdo->query("SELECT id, tenant_id, role FROM users")->fetchAll();
    foreach ($users as $u) {
        $uId = $u['id'];
        $uTenantId = $u['tenant_id'];
        $oldRole = strtolower(trim($u['role']));
        
        $newRoleName = $roleMap[$oldRole] ?? 'Employee';
        if ($oldRole === 'admin') $newRoleName = 'Admin';
        
        // Ensure every user is at least an 'Employee'
        $rolesToAssign = array_unique([$newRoleName, 'Employee']);
        
        foreach ($rolesToAssign as $rn) {
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE tenant_id = ? AND name = ?");
            $stmt->execute([$uTenantId, $rn]);
            $roleId = $stmt->fetchColumn();

            if ($roleId) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
                $stmt->execute([$uId, $roleId]);
            }
        }
    }

    echo "IAM v1 Seeding & Migration Complete!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
