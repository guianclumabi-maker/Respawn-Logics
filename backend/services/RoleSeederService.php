<?php

class RoleSeederService
{
    /**
     * Get the resolved tier configuration
     */
    public static function getTierConfig(string $mode): array
    {
        $config = require __DIR__ . '/../../config/rbac_tiers.php';
        if (!isset($config['tiers'][$mode])) {
            $mode = 'Solo'; // Default fallback
        }
        return $config['tiers'][$mode];
    }

    public static function seedTenantRoles(PDO $pdo, string $tenantId, string $setupMode, int $ownerUserId)
    {
        $startedTransaction = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            // Load configuration
            $config = require __DIR__ . '/../../config/rbac_tiers.php';
            $roleDefinitions = $config['roles'];
            
            $tierConfig = self::getTierConfig($setupMode);
            $rolesToSeed = $tierConfig['roles'];

            // Get all permission IDs
            $stmtPerms = $pdo->query("SELECT id, permission_key FROM permissions");
            $allPerms = [];
            $allPermIds = [];
            while ($row = $stmtPerms->fetch(PDO::FETCH_ASSOC)) {
                $allPerms[$row['permission_key']] = $row['id'];
                $allPermIds[] = $row['id'];
            }

            $stmtAddRole = $pdo->prepare("INSERT INTO roles (tenant_id, name, description, is_system_role) VALUES (?, ?, ?, 1)");
            $stmtLinkPerm = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");

            $createdRoleIds = [];

            foreach ($rolesToSeed as $roleName) {
                if (!isset($roleDefinitions[$roleName])) continue;
                $def = $roleDefinitions[$roleName];
                $stmtAddRole->execute([$tenantId, $roleName, $def['desc']]);
                $roleId = $pdo->lastInsertId();
                $createdRoleIds[$roleName] = $roleId;

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

            // Assign Account Owner role to the creator user
            if (isset($createdRoleIds['Account Owner'])) {
                $stmtAssignUser = $pdo->prepare("INSERT INTO user_roles (user_id, role_id, scope) VALUES (?, ?, 'tenant')");
                $stmtAssignUser->execute([$ownerUserId, $createdRoleIds['Account Owner']]);
            }

            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (Exception $e) {
            if ($startedTransaction) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
