<?php
header('Content-Type: application/json');

require_once __DIR__ . '/bootstrap/app.php';
global $config;

// Define CORS — only needed for cross-origin requests (Origin header present)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $allowed_origins = array_filter(array_map('trim', explode(',', $config['cors']['allowed_origins'])));
    if (in_array($origin, $allowed_origins) || empty($allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
    }
}
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

$action = $_GET['action'] ?? '';

// ── Public: exchange one-time login token (called by React right after registration) ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'exchange_token') {
    $token = trim($_GET['token'] ?? '');
    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token required']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT t.user_id, t.expires_at, t.used_at, u.email, u.full_name, u.tenant_id
         FROM user_activation_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.token = ? LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }
    if ($row['used_at'] !== null) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token already used']);
        exit;
    }
    if (strtotime($row['expires_at']) < time()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token expired']);
        exit;
    }

    // Mark token as used
    $pdo->prepare("UPDATE user_activation_tokens SET used_at = NOW() WHERE token = ?")
        ->execute([$token]);

    // Establish the session
    $_SESSION['user_id']    = $row['user_id'];
    $_SESSION['user_email'] = $row['email'];
    $_SESSION['user_name']  = $row['full_name'];
    $_SESSION['tenant_id']  = $row['tenant_id'];

    // Force the session to persist to the DB now, while the connection is alive,
    // so the very next request (current_user) can read it back.
    session_write_close();

    echo json_encode(['success' => true, 'message' => 'Session established']);
    exit;
}

// Enforce Auth
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

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

        require_once __DIR__ . '/backend/services/RoleSeederService.php';
        $stmtTier = $pdo->prepare("SELECT setup_mode FROM tenants WHERE id = ?");
        $stmtTier->execute([$user['tenant_id']]);
        $setupMode = $stmtTier->fetchColumn() ?: 'Solo';
        $tierConfig = RoleSeederService::getTierConfig($setupMode);

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
                'must_change_password' => !empty($_SESSION['must_change_password']),
                'tier_config' => $tierConfig
            ]
        ]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
