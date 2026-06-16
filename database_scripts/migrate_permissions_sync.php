<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

echo "Syncing RBAC Permissions...\n";

$permissionDict = require __DIR__ . '/../config/permissions.php';

// Ensure permission groups exist and insert new permissions
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
    'HR Manager' => [
        'employees.view', 'employees.edit', 'leave.approve', 'attendance.manage', 
        'users.manage', 'payroll.manage', 'benefits.manage', 'expenses.manage', 
        'performance.manage', 'shifts.manage', 'surveys.manage', 'announcements.manage', 
        'analytics.view'
    ],
    'Manager' => ['employees.view_team', 'leave.approve_team', 'performance.manage_team'],
    'Employee' => ['employees.view_self', 'leave.request'],
    'Recruiter' => ['ats.view', 'ats.edit', 'ats.create_job', 'ats.edit_job'],
    'Investigator' => ['elr.view', 'elr.investigate']
];

// Fetch all permission keys for Admin
$allPermsStmt = $pdo->query("SELECT permission_key FROM permissions");
while ($row = $allPermsStmt->fetch()) {
    $roleDefs['Admin'][] = $row['permission_key'];
}

// Sync new permissions for existing roles across all tenants
$tenants = $pdo->query("SELECT DISTINCT tenant_id FROM users")->fetchAll();
foreach ($tenants as $t) {
    $tenantId = $t['tenant_id'];

    foreach ($roleDefs as $roleName => $rolePermKeys) {
        // Ensure role exists (in case it got deleted or new tenant)
        $stmt = $pdo->prepare("INSERT IGNORE INTO roles (tenant_id, name, is_system_role) VALUES (?, ?, 1)");
        $stmt->execute([$tenantId, $roleName]);

        $stmt = $pdo->prepare("SELECT id FROM roles WHERE tenant_id = ? AND name = ?");
        $stmt->execute([$tenantId, $roleName]);
        $roleId = $stmt->fetchColumn();

        if ($roleId) {
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
}

// Bump permission version to force cache invalidation for all users
try {
    $pdo->exec("UPDATE tenants SET permission_version = permission_version + 1");
} catch (PDOException $e) {
    // Column might not exist on all setups
}

echo "RBAC Permissions Synced Successfully.\n";
