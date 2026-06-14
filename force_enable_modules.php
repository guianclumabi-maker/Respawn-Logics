<?php
require 'bootstrap/app.php';

$availableModules = [
    'core_hr', 'time_attendance', 'leave_management', 'esm', 
    'analytics', 'payroll', 'performance', 'expenses', 
    'recruitment', 'benefits', 'surveys', 'announcements', 'elr', 'knowledge'
];

$stmt = $pdo->query("SELECT id FROM tenants");
$tenants = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tenants as $tId) {
    foreach ($availableModules as $mod) {
        $pdo->prepare("INSERT INTO tenant_modules (tenant_id, module_key, is_enabled) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_enabled = 1")->execute([$tId, $mod]);
    }
}
echo "All modules enabled for all tenants.";
