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
                        session_regenerate_id(true); // Prevent Session Fixation
                        
                        $_SESSION['user_id']           = $user['id'];
                        $_SESSION['user_email']        = $user['email'];
                        $_SESSION['user_name']         = $user['full_name'];
                        $_SESSION['tenant_id']         = $user['tenant_id'];
                        $_SESSION['theme_preference']  = $user['theme_preference'] ?? 'dark';
                        $_SESSION['must_change_password'] = !empty($user['must_change_password']);

                        echo json_encode([
                            'success' => true,
                            'user' => [
                                'id' => $user['id'],
                                'name' => $user['full_name'],
                                'email' => $user['email'],
                                'tenant_id' => $user['tenant_id'],
                                'theme' => $user['theme_preference'],
                                'must_change_password' => !empty($user['must_change_password'])
                            ]
                        ]);
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
                            'permissions' => $_SESSION['permissions'] ?? []
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
