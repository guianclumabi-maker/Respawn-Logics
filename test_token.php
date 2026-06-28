<?php
require_once __DIR__ . '/bootstrap/app.php';
header('Content-Type: application/json');

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    echo json_encode(['error' => 'No token provided']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT t.user_id, t.expires_at, t.used_at, u.email, u.full_name, u.tenant_id
         FROM user_activation_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.token = ? LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Token not found in DB']);
        exit;
    }

    $expiresTimestamp = strtotime($row['expires_at']);
    $currentTimestamp = time();
    $difference = $expiresTimestamp - $currentTimestamp;

    echo json_encode([
        'success' => true,
        'token' => $token,
        'expires_at' => $row['expires_at'],
        'expires_timestamp' => $expiresTimestamp,
        'current_timestamp' => $currentTimestamp,
        'difference_seconds' => $difference,
        'is_expired_by_php' => ($expiresTimestamp < $currentTimestamp),
        'used_at' => $row['used_at'],
        'user' => [
            'email' => $row['email'],
            'full_name' => $row['full_name'],
            'tenant_id' => $row['tenant_id']
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
