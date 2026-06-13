<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/PermissionService.php';

/**
 * Ensures the session has loaded the user's permissions and checks for cache invalidation.
 */
if (!function_exists('loadPermissions')) {
    function loadPermissions() {
        global $pdo;
        if (isset($_SESSION['user_id'])) {
            $tenantId = $_SESSION['tenant_id'] ?? null;
            
            if ($tenantId === null || $tenantId === '') {
                if (!isset($_SESSION['permissions'])) {
                    $_SESSION['permissions'] = PermissionService::userPermissions($pdo, (int)$_SESSION['user_id'], 0);
                    $_SESSION['permission_version'] = 1;
                }
                return;
            }
            
            $tenantIdInt = (int)$tenantId;
            
            // Fetch current permission version for this tenant
            $stmt = $pdo->prepare("SELECT permission_version FROM tenants WHERE id = ?");
            $stmt->execute([$tenantIdInt]);
            $currentVersion = (int)$stmt->fetchColumn();

            // If session version is missing or outdated, reload permissions
            if (!isset($_SESSION['permission_version']) || $_SESSION['permission_version'] !== $currentVersion || !isset($_SESSION['permissions'])) {
                $_SESSION['permissions'] = PermissionService::userPermissions($pdo, (int)$_SESSION['user_id'], $tenantIdInt);
                $_SESSION['permission_version'] = $currentVersion;
            }
        }
    }
}

// Load helpers after core definitions
require_once __DIR__ . '/../helpers/permissions.php';

/**
 * Checks if the user is authenticated.
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        $loggedIn = isset($_SESSION['user_email']);
        if ($loggedIn) {
            loadPermissions();
        }
        return $loggedIn;
    }
}

/**
 * Enforces authentication and checks password reset constraints.
 */
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            header('Location: ' . url('/login.php'));
            exit;
        }
        
        // If session is active but database record is missing, destroy session and redirect
        $user = getCurrentUser();
        if ($user === false) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            header('Location: ' . url('/login.php'));
            exit;
        }
        
        // If the user's password must be changed, redirect them to the change password form.
        if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] === true) {
            $current_script = basename($_SERVER['SCRIPT_NAME']);
            if ($current_script !== 'login.php') {
                header('Location: ' . url('/login.php'));
                exit;
            }
        }
    }
}

/**
 * Returns the current logged in user details.
 */
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        global $pdo;
        if (!isLoggedIn()) return null;
        
        $tenantId = $_SESSION['tenant_id'] ?? null;
        try {
            if ($tenantId === null || $tenantId === '') {
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ? AND (`tenant_id` IS NULL OR `tenant_id` = '')");
                $stmt->execute([$_SESSION['user_email']]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ? AND `tenant_id` = ?");
                $stmt->execute([$_SESSION['user_email'], $tenantId]);
            }
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
}

/**
 * Verifies if the current user has specific access roles.
 */
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        if (!isLoggedIn()) return false;
        $user = getCurrentUser();
        if (!$user) return false;
        
        if (is_array($roles)) {
            return in_array($user['role'], $roles);
        }
        return $user['role'] === $roles;
    }
}


