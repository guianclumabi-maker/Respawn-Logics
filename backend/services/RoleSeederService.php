<?php

class RoleSeederService
{
    public static function seedTenantRoles(PDO $pdo, string $tenantId, string $setupMode, int $ownerUserId)
    {
        $pdo->beginTransaction();

        try {
            // Define all possible base roles
            $roleDefinitions = [
                'Account Owner' => ['desc' => 'Ultimate tenant owner. Full access.', 'perms' => ['*']],
                'Admin' => ['desc' => 'Administrative access to system settings.', 'perms' => ['users.manage', 'settings.manage', 'audit.view', 'employees.view', 'employees.edit']],
                'Manager' => ['desc' => 'Can manage their department/team.', 'perms' => ['employees.view_team', 'leave.approve_team', 'performance.manage_team']],
                'Employee' => ['desc' => 'Base access for standard employees.', 'perms' => ['employees.view_self', 'leave.view', 'leave.request', 'attendance.view']],
                'HR Manager' => ['desc' => 'Can manage HR records, ELR, and ATS.', 'perms' => ['employees.manage', 'leave.manage', 'elr.view', 'elr.investigate', 'ats.view', 'ats.edit']],
                'Recruiter' => ['desc' => 'Focused on ATS pipeline.', 'perms' => ['ats.view', 'ats.create_job', 'ats.edit_job']],
                'Payroll Manager' => ['desc' => 'Can process payroll runs.', 'perms' => ['payroll.view', 'payroll.run', 'benefits.manage']],
                'Payroll Approver' => ['desc' => 'Can approve and finalize payroll.', 'perms' => ['payroll.view', 'payroll.approve', 'benefits.approve']]
            ];

            // Determine which roles to seed based on tier
            $rolesToSeed = ['Account Owner']; // Solo gets only this
            
            if (in_array($setupMode, ['Small', 'Mid', 'Enterprise'])) {
                $rolesToSeed = array_merge($rolesToSeed, ['Admin', 'Manager', 'Employee']);
            }
            if (in_array($setupMode, ['Mid', 'Enterprise'])) {
                $rolesToSeed = array_merge($rolesToSeed, ['HR Manager', 'Recruiter', 'Payroll Manager', 'Payroll Approver']);
            }

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

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
