<?php
header('Content-Type: application/json');

require_once __DIR__ . '/bootstrap/app.php';
global $config;

// Define CORS
$allowed_origins = array_map('trim', explode(',', $config['cors']['allowed_origins']));

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Enforce Auth
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'current_user') {
        global $pdo;
        $user = getCurrentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        // Fetch User Roles Names
        $stmt = $pdo->prepare("
            SELECT r.name 
            FROM roles r
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Generate CSRF if missing
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        echo json_encode([
            'success' => true,
            'csrf_token' => $_SESSION['csrf_token'],
            'user' => [
                'id' => $user['id'],
                'name' => $user['full_name'],
                'profile_image' => $user['profile_image'] ?? null,
                'job_title' => $user['job_title'] ?? null,
                'roles' => $roles,
                'permissions' => $_SESSION['permissions'] ?? [],
                'must_change_password' => !empty($_SESSION['must_change_password'])
            ]
        ]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
