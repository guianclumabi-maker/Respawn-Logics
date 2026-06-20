<?php

// 1. Load Environment Variables
$envFile = __DIR__ . '/../.env';
$localEnv = file_exists($envFile) ? parse_ini_file($envFile) : [];
$env = array_merge($localEnv, getenv(), $_ENV);

// PHP-FPM sometimes puts env vars in $_SERVER
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'APP_URL', 'APP_ENV', 'APP_DEBUG'] as $key) {
    if (isset($_SERVER[$key]) && !isset($env[$key])) {
        $env[$key] = $_SERVER[$key];
    }
}

// 2. Load Configuration
global $config;
$config = require __DIR__ . '/../config/config.php';

// 3. Configure and Start Session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', $config['session']['httponly'] ? 1 : 0);
    ini_set('session.cookie_secure', $config['session']['secure'] ? 1 : 0);
    ini_set('session.cookie_samesite', $config['session']['samesite']);
    ini_set('session.gc_maxlifetime', $config['session']['timeout']);
    
    session_start();
}

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 4. Global URL Helper
if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        global $config;
        $baseUrl = rtrim($config['app']['url'], '/');
        
        // Dynamically override base URL if running in a web context to prevent Railway env misconfigurations
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $protocol = "https://";
            }
            $baseUrl = $protocol . $_SERVER['HTTP_HOST'];
            
            // If running on localhost inside XAMPP, append the project folder
            if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
                $baseUrl .= '/respawn-logics';
            }
        }
        
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

// 5. Load Database
require_once __DIR__ . '/../config/db.php';

// 6. Load Permissions & Auth Helpers
require_once __DIR__ . '/../services/PermissionService.php';

// 7. Load Component Helpers
require_once __DIR__ . '/../includes/logo.php';

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
            
            $stmt = $pdo->prepare("SELECT permission_version FROM tenants WHERE id = ?");
            $stmt->execute([$tenantIdInt]);
            $currentVersion = (int)$stmt->fetchColumn();
 
            if (!isset($_SESSION['permission_version']) || $_SESSION['permission_version'] !== $currentVersion || empty($_SESSION['permissions'])) {
                $_SESSION['permissions'] = PermissionService::userPermissions($pdo, (int)$_SESSION['user_id'], $tenantIdInt);
                $_SESSION['permission_version'] = $currentVersion;
            }
        }
    }
}

require_once __DIR__ . '/../helpers/permissions.php';
// Load CSRF helpers (csrf_token, csrf_field, csrf_verify)
require_once __DIR__ . '/../helpers/csrf.php';

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        $loggedIn = isset($_SESSION['user_email']);
        if ($loggedIn) {
            loadPermissions();
            loadTenantModules();
        }
        return $loggedIn;
    }
}

if (!function_exists('loadTenantModules')) {
    function loadTenantModules() {
        global $pdo;
        if (isset($_SESSION['tenant_id'])) {
            $tenantId = $_SESSION['tenant_id'];
            
            // Re-use permission_version to invalidate cache if needed, or simply cache once per login
            if (!isset($_SESSION['tenant_modules'])) {
                $stmt = $pdo->prepare("SELECT module_key, is_enabled FROM tenant_modules WHERE tenant_id = ?");
                $stmt->execute([$tenantId]);
                $_SESSION['tenant_modules'] = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $_SESSION['tenant_modules'][$row['module_key']] = (bool)$row['is_enabled'];
                }
            }
        }
    }
}

if (!function_exists('tenantModuleEnabled')) {
    function tenantModuleEnabled(string $module) {
        if (!isLoggedIn()) return false;
        // Default to true if not strictly defined, or false. Let's default to false if explicitly disabled.
        if (isset($_SESSION['tenant_modules'][$module])) {
            return $_SESSION['tenant_modules'][$module];
        }
        return true; // Fallback for backwards compatibility
    }
}

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

if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        global $pdo;
        if (!isLoggedIn()) return null;
        
        static $cachedUser = null;
        if ($cachedUser !== null) {
            return $cachedUser;
        }
        
        $tenantId = $_SESSION['tenant_id'] ?? null;
        try {
            if ($tenantId === null || $tenantId === '') {
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ? AND (`tenant_id` IS NULL OR `tenant_id` = '')");
                $stmt->execute([$_SESSION['user_email']]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ? AND `tenant_id` = ?");
                $stmt->execute([$_SESSION['user_email'], $tenantId]);
            }
            $cachedUser = $stmt->fetch();
            return $cachedUser;
        } catch (PDOException $e) {
            return null;
        }
    }
}

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

if (!function_exists('sendNotification')) {
    function sendNotification($pdo, $tenant_id, $user_email, $title, $message, $type = 'info', $link = null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO notifications (tenant_id, user_email, title, message, type, link) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $user_email, $title, $message, $type, $link]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
