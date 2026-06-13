<?php
require_once __DIR__ . '/bootstrap/app.php';

try {
    // 1. Give Employee role the "attendance.view" permission
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE permission_key = 'attendance.view'");
    $stmt->execute();
    $attViewId = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Employee'");
    $stmt->execute();
    $empRoleId = $stmt->fetchColumn();

    if ($attViewId && $empRoleId) {
        $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)")->execute([$empRoleId, $attViewId]);
    }

    // 2. Assign default roles to the test users
    $users = [
        'admin@test.com' => ['Admin', 'Employee'],
        'recruiter@test.com' => ['Recruiter', 'Employee'],
        'investigator@test.com' => ['Investigator', 'Employee']
    ];

    foreach ($users as $email => $roleNames) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $userId = $stmt->fetchColumn();

        if ($userId) {
            foreach ($roleNames as $rName) {
                $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
                $stmt->execute([$rName]);
                $rId = $stmt->fetchColumn();

                if ($rId) {
                    $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$userId, $rId]);
                }
            }
        }
    }

    // 3. Invalidate permission cache for tenant 1
    $pdo->prepare("UPDATE tenants SET permission_version = permission_version + 1 WHERE id = 1")->execute();

    echo "Fix applied.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
