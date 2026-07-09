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

                        $fullUser = [
                            'id' => $user['id'],
                            'name' => $user['full_name'],
                            'profile_image' => $user['profile_image'] ?? null,
                            'job_title' => $user['job_title'] ?? null,
                            'roles' => $roles,
                            'role' => $user['role'] ?? null,
                            'permissions' => $_SESSION['permissions'] ?? [],
                            'is_super' => !empty($_SESSION['is_super']),
                            'must_change_password' => !empty($user['must_change_password']),
                            'tier_config' => $tierConfig,
                            'theme' => $user['theme_preference'] ?? null,
                            'email' => $user['email'],
                            'tenant_id' => $user['tenant_id']
                        ];

                        echo json_encode(['success' => true, 'user' => $fullUser]);
                    } else {
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
