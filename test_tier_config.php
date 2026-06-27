<?php
require 'bootstrap/app.php';
require_once 'backend/services/RoleSeederService.php';

// First check getTierConfig behavior
echo "Testing getTierConfig():\n";
$mid = RoleSeederService::getTierConfig('Mid');
echo "Mid tier -> default_scope: " . $mid['default_scope'] . ", org_units: " . ($mid['org_units'] ? 'true' : 'false') . "\n";

$garbage = RoleSeederService::getTierConfig('garbage');
echo "garbage tier -> default_scope: " . $garbage['default_scope'] . ", roles count: " . count($garbage['roles']) . "\n";

echo "\nTesting Seed Simulations:\n";
$modes = ['Solo', 'Small', 'Mid', 'Enterprise'];
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');

foreach ($modes as $mode) {
    $pdo->beginTransaction();
    try {
        $tenantId = 'test_tenant_' . rand(1000, 9999);
        $ownerId = 1;
        RoleSeederService::seedTenantRoles($pdo, $tenantId, $mode, $ownerId);

        $stmt = $pdo->prepare("SELECT name FROM roles WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmtUser = $pdo->prepare("SELECT r.name, ur.scope FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ? AND r.tenant_id = ?");
        $stmtUser->execute([$ownerId, $tenantId]);
        $userRole = $stmtUser->fetch(PDO::FETCH_ASSOC);

        echo "Mode: $mode -> Roles Seeded: " . count($roles) . " (" . implode(', ', $roles) . ")\n";
        echo "Account Owner scope assigned: " . $userRole['scope'] . "\n";
    } catch (Exception $e) {
        echo "Error in $mode: " . $e->getMessage() . "\n";
    }
    $pdo->rollBack();
}
