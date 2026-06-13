<?php

class PermissionService
{
    /**
     * Fetches a flat array of permission keys for a given user.
     *
     * @param PDO $pdo The database connection
     * @param int $userId The user's ID
     * @param string $tenantId The user's tenant ID
     * @return array Array of permission strings (e.g. ['users.view', 'ats.edit'])
     */
    public static function userPermissions(PDO $pdo, int $userId, string $tenantId): array
    {
        // Shortcut for global admins or tenant founders
        $stmtUser = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userRole = $stmtUser->fetchColumn();

        if ($userRole === 'Super_Admin' || $userRole === 'Platform_Admin') {
            $stmt = $pdo->prepare("SELECT permission_key FROM permissions");
            $stmt->execute();
            $permissions = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $permissions[] = $row['permission_key'];
            }
            return $permissions;
        }

        $stmt = $pdo->prepare("
            SELECT DISTINCT p.permission_key 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN roles r ON rp.role_id = r.id
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ? AND r.tenant_id = ?
        ");
        $stmt->execute([$userId, $tenantId]);
        
        $permissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[] = $row['permission_key'];
        }
        
        return $permissions;
    }
}
