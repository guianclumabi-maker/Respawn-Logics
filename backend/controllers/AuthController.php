<?php
require_once __DIR__ . '/../utils/Storage.php';

class AuthController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function handleRequest($action)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);

            if ($action === 'login') {
                $email = trim($data['email'] ?? '');
                $password = $data['password'] ?? '';

                if (empty($email) || empty($password)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Email and password are required.']);
                    return;
                }

                // ── Brute-force throttle ──────────────────────────────────────
                // 5 failed attempts per email OR per IP within 15 minutes -> 429.
                // Response is generic (never confirms the account exists). Counter
                // clears on successful login. Self-healing table.
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                try {
                    $this->pdo->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
                        `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
                        `email` VARCHAR(150) NOT NULL,
                        `ip` VARCHAR(45) NOT NULL,
                        `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX `idx_la_email` (`email`, `attempted_at`),
                        INDEX `idx_la_ip` (`ip`, `attempted_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    $thr = $this->pdo->prepare("SELECT COUNT(*) FROM `login_attempts`
                        WHERE (`email` = ? OR `ip` = ?) AND `attempted_at` > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                    $thr->execute([$email, $ip]);
                    if ((int)$thr->fetchColumn() >= 5) {
                        http_response_code(429);
                        echo json_encode(['success' => false, 'error' => 'Too many login attempts. Please wait 15 minutes and try again.']);
                        return;
                    }
                } catch (\Throwable $thrEx) {
                    error_log('[Auth] throttle check failed: ' . $thrEx->getMessage()); // fail open, never lock out on infra errors
                }

                try {
                    $stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `email` = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                        // 1. Check for 2FA
                        if (!empty($user['totp_enabled'])) {
                            $_SESSION['2fa_pending_user_id'] = $user['id'];
                            $_SESSION['2fa_pending_user_email'] = $user['email'];
                            echo json_encode([
                                'success' => true,
                                'require_2fa' => true,
                                'redirect' => url('/login.php?step=2fa')
                            ]);
                            return;
                        }

                        // 1b. 2FA ENFORCEMENT — tenant requires 2FA but this user has not enrolled yet.
                        $enforce2fa = false;
                        try {
                            $tstmt = $this->pdo->prepare("SELECT `enforce_2fa` FROM `tenants` WHERE `id` = ? LIMIT 1");
                            $tstmt->execute([$user['tenant_id']]);
                            $enforce2fa = ((int)$tstmt->fetchColumn() === 1);
                        } catch (\Throwable $e) {
                            $enforce2fa = false; // fail OPEN: never lock users out on a DB/schema hiccup
                        }
                        if ($enforce2fa) {
                            // Partial state only — the user is NOT fully logged in until enrollment completes.
                            $_SESSION['2fa_setup_user_id'] = $user['id'];
                            unset($_SESSION['2fa_setup_secret']);
                            echo json_encode([
                                'success' => true,
                                'require_2fa_setup' => true,
                                'redirect' => url('/setup_2fa.php')
                            ]);
                            return;
                        }

                        // 2. Check for must_change_password
                        if (!empty($user['must_change_password'])) {
                            $_SESSION['user_id']           = $user['id'];
                            $_SESSION['user_email']        = $user['email'];
                            $_SESSION['user_name']         = $user['full_name'];
                            $_SESSION['tenant_id']         = $user['tenant_id'];
                            $_SESSION['theme_preference']  = $user['theme_preference'] ?? 'dark';
                            $_SESSION['must_change_password'] = true;
                            
                            echo json_encode([
                                'success' => true,
                                'must_change_password' => true,
                                'redirect' => url('/login.php?step=set_password')
                            ]);
                            return;
                        }

                        session_regenerate_id(true); // Prevent Session Fixation

                        $_SESSION['user_id']           = $user['id'];
                        $_SESSION['user_email']        = $user['email'];
                        $_SESSION['user_name']         = $user['full_name'];
                        $_SESSION['tenant_id']         = $user['tenant_id'];
                        $_SESSION['theme_preference']  = $user['theme_preference'] ?? 'dark';
                        $_SESSION['must_change_password'] = false;

                        // Hydrate RBAC into the session using the SAME canonical loader every
                        // other request uses (loadPermissions), so the login response is complete
                        // and cannot drift from GET current_user. Without this, the frontend gets a
                        // "lite" user with no role/permissions/is_super and hides admin UI until a
                        // full page reload re-hydrates the session.
                        if (function_exists('loadPermissions')) {
                            loadPermissions();
                        }

                        // Fetch User Roles Names
                        $stmtRoles = $this->pdo->prepare("
                            SELECT r.name
                            FROM roles r
                            JOIN user_roles ur ON r.id = ur.role_id
                            WHERE ur.user_id = ?
                        ");
                        $stmtRoles->execute([$user['id']]);
                        $roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

                        require_once __DIR__ . '/../services/RoleSeederService.php';
                        $stmtTier = $this->pdo->prepare("SELECT setup_mode FROM tenants WHERE id = ?");
                        $stmtTier->execute([$user['tenant_id']]);
                        $setupMode = $stmtTier->fetchColumn() ?: 'Solo';
                        $tierConfig = RoleSeederService::getTierConfig($setupMode);

                        $fullUser = array_merge(buildUserPayload($user, $roles), ['tier_config' => $tierConfig]);

                        // Successful login clears this identity's throttle counter.
                        try {
                            $this->pdo->prepare("DELETE FROM `login_attempts` WHERE `email` = ? OR `ip` = ?")->execute([$email, $ip]);
                        } catch (\Throwable $e) { /* non-fatal */ }

                        logAudit('Login', 'User signed in successfully.', $user['email'], $user['tenant_id']);
                        echo json_encode(['success' => true, 'user' => $fullUser]);
                    } else {
                        // Record the failure for the brute-force throttle.
                        try {
                            $this->pdo->prepare("INSERT INTO `login_attempts` (`email`, `ip`) VALUES (?, ?)")->execute([$email, $ip]);
                        } catch (\Throwable $e) { /* non-fatal */ }
                        http_response_code(401);
                        echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
                    }
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Database error.']);
                }
                return;
            }

            if ($action === 'logout') {
                logAudit('Logout', 'User signed out.'); // uses session actor/tenant, before destroy
                session_destroy();
                echo json_encode(['success' => true]);
                return;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($action === 'download_avatar') {
                $this->downloadAvatar();
                return;
            }

            if ($action === 'me') {
                if (isLoggedIn()) {
                    echo json_encode([
                        'success' => true,
                        'user' => [
                            'id' => $_SESSION['user_id'],
                            'name' => $_SESSION['user_name'],
                            'email' => $_SESSION['user_email'],
                            'tenant_id' => $_SESSION['tenant_id'],
                            'theme' => $_SESSION['theme_preference'] ?? 'dark',
                            'permissions' => $_SESSION['permissions'] ?? [],
                            'is_super' => !empty($_SESSION['is_super']),
                            'role' => getCurrentUser()['role'] ?? null,
                            'roles' => [getCurrentUser()['role'] ?? 'Employee'],
                        ]
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                }
                return;
            }
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid auth action']);
    }

    private function downloadAvatar() {
        if (!isLoggedIn()) { http_response_code(401); echo "Unauthorized"; return; }
        $file = $_GET['file'] ?? '';
        // Only allow basic filenames, no traversal
        if (!$file || !preg_match('/^[a-zA-Z0-9.\-_]+$/', $file)) {
            http_response_code(400); echo "Invalid file"; return;
        }

        $storageBase = \App\Utils\Storage::resolveStorageBase(false, false);
        
        // Legacy check: did we migrate it yet? If not, it might still be in uploads/
        $legacyPath = __DIR__ . '/../../uploads/' . $file;
        $securePath = rtrim($storageBase, '/') . '/avatars/' . $file;

        $fullPath = file_exists($securePath) ? $securePath : $legacyPath;

        if (!file_exists($fullPath)) {
            // Serve a default placeholder or 404
            http_response_code(404); echo "Avatar not found"; return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }
}
