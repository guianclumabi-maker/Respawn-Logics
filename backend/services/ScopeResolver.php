<?php

class ScopeResolver
{
    public const ALL_USERS = 'ALL_USERS';

    /**
     * Determine the broadest scope for a user across all their roles.
     * Order: tenant > branch > department > team > self
     */
    private static function getUserBroadestScope(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare("SELECT scope, org_unit_id FROM user_roles WHERE user_id = ?");
        $stmt->execute([$userId]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $bestScope = 'self';
        $bestOrgUnitId = null;

        $rank = [
            'tenant' => 5,
            'branch' => 4,
            'department' => 3,
            'team' => 2,
            'self' => 1
        ];

        foreach ($roles as $r) {
            $s = $r['scope'] ?? 'self';
            if ($rank[$s] > $rank[$bestScope]) {
                $bestScope = $s;
                $bestOrgUnitId = $r['org_unit_id'];
            }
        }

        return ['scope' => $bestScope, 'org_unit_id' => $bestOrgUnitId];
    }

    /**
     * Returns ScopeResolver::ALL_USERS for tenant scope,
     * otherwise returns an array of integer user IDs.
     */
    public static function getAccessibleUserIds(PDO $pdo, array $currentUser): array|string
    {
        $userId = (int)$currentUser['id'];
        $tenantId = $currentUser['tenant_id'];
        
        $scopeInfo = self::getUserBroadestScope($pdo, $userId);
        $scope = $scopeInfo['scope'];
        $roleOrgUnitId = $scopeInfo['org_unit_id']; // This might be null

        // Fallback to the user's own org_unit_id if the role didn't specify one
        if (!$roleOrgUnitId && isset($currentUser['org_unit_id'])) {
            $roleOrgUnitId = $currentUser['org_unit_id'];
        }

        if ($scope === 'tenant') {
            return self::ALL_USERS;
        }

        $accessibleIds = [];

        if ($scope === 'department' || $scope === 'branch') {
            if (!$roleOrgUnitId) {
                // Fail-closed: if scoped to department but no unit is assigned, they see only themselves
                return [$userId];
            }

            // Use MySQL 8 CTE to find all descendant org units
            $cte = "
                WITH RECURSIVE org_tree AS (
                    SELECT id FROM org_units WHERE id = :root_id AND tenant_id = :tenant_id
                    UNION ALL
                    SELECT ou.id FROM org_units ou
                    INNER JOIN org_tree ot ON ou.parent_id = ot.id
                )
                SELECT id FROM org_tree;
            ";
            $stmtCte = $pdo->prepare($cte);
            $stmtCte->execute([':root_id' => $roleOrgUnitId, ':tenant_id' => $tenantId]);
            $unitIds = $stmtCte->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($unitIds)) {
                $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
                $params = array_map('intval', $unitIds);
                $params[] = $tenantId;
                $stmtUsers = $pdo->prepare("SELECT id FROM users WHERE org_unit_id IN ($placeholders) AND tenant_id = ?");
                $stmtUsers->execute($params);
                $deptUserIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);
                foreach ($deptUserIds as $id) {
                    $accessibleIds[] = (int)$id;
                }
            }
        }

        // Team scope: always included if scope >= team. 
        // A department manager also manages their direct reports even if outside org_unit technically (safeguard).
        if (in_array($scope, ['team', 'department', 'branch'])) {
            $userEmail = strtolower(trim($currentUser['email'] ?? ''));
            
            $sql = "SELECT id FROM users WHERE tenant_id = :tenant_id AND (manager_id = :user_id";
            $params = [':tenant_id' => $tenantId, ':user_id' => $userId];

            if ($userEmail !== '') {
                $sql .= " OR LOWER(immediate_supervisor) = :email OR LOWER(department_manager) = :email";
                $params[':email'] = $userEmail;
            }
            $sql .= ")";

            $stmtTeam = $pdo->prepare($sql);
            $stmtTeam->execute($params);
            $teamIds = $stmtTeam->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($teamIds as $id) {
                $accessibleIds[] = (int)$id;
            }
        }

        // Always include self
        $accessibleIds[] = $userId;

        return array_values(array_unique(array_map('intval', $accessibleIds)));
    }

    /**
     * Returns the SQL WHERE clause for the resolved scope.
     */
    public static function getScopeWhereClause(PDO $pdo, array $currentUser, string $tableAlias = 'u'): string
    {
        $ids = self::getAccessibleUserIds($pdo, $currentUser);

        if ($ids === self::ALL_USERS) {
            return " AND 1=1 ";
        }

        if (empty($ids)) {
            // Fail-closed: if array is somehow empty, force false
            return " AND 1=0 ";
        }

        $idList = implode(',', $ids);
        return " AND {$tableAlias}.id IN ({$idList}) ";
    }

    /**
     * Boolean check for 1:1 actions.
     */
    public static function hasScopedAccess(PDO $pdo, array $currentUser, int $targetUserId): bool
    {
        $ids = self::getAccessibleUserIds($pdo, $currentUser);
        
        if ($ids === self::ALL_USERS) {
            return true;
        }

        return in_array($targetUserId, $ids, true);
    }
}
