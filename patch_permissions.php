<?php
require 'bootstrap/app.php';

$requiredPermissions = [
    // Existing ones
    'users.view', 'users.manage', 'settings.manage', 'roles.manage',
    'core_hr.view', 'core_hr.manage',
    'attendance.view', 'attendance.manage',
    'leave.view', 'leave.manage',
    'esm.view', 'esm.manage',
    'analytics.view', 'analytics.manage',
    
    // Missing ones for new modules
    'payroll.manage', 'payroll.view',
    'performance.manage', 'performance.view',
    'expenses.manage', 'expenses.view',
    'recruitment.manage', 'recruitment.view',
    'benefits.manage', 'benefits.view',
    'surveys.manage', 'surveys.view',
    'announcements.manage', 'announcements.view',
    
    // ELR and Platform Support
    'elr.view', 'elr.manage',
    'platform.manage', 'audit.view', 'knowledge.manage', 'knowledge.view', 'compensation.manage'
];

foreach ($requiredPermissions as $perm) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO permissions (permission_key, description) VALUES (?, ?)");
        // Just title case the key for the name
        $name = ucwords(str_replace(['.', '_'], ' ', $perm));
        $stmt->execute([$perm, "Allows $name"]);
    } catch (Exception $e) {
        echo "Error on $perm: " . $e->getMessage() . "\n";
    }
}

echo "Permissions patched successfully.\n";
