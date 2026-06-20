<?php

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
                        $_SESSION['theme_preference']  = $user['theme_preference'] ?? 'light';

                        echo json_encode([
                            'success' => true,
                            'user' => [
                                'id' => $user['id'],
                                'name' => $user['full_name'],
                                'email' => $user['email'],
                                'tenant_id' => $user['tenant_id'],
                                'theme' => $user['theme_preference']
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
            if ($action === 'me') {
                if (isLoggedIn()) {
                    echo json_encode([
                        'success' => true,
                        'user' => [
                            'id' => $_SESSION['user_id'],
                            'name' => $_SESSION['user_name'],
                            'email' => $_SESSION['user_email'],
                            'tenant_id' => $_SESSION['tenant_id'],
                            'theme' => $_SESSION['theme_preference'] ?? 'light',
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
}
