<?php
require_once __DIR__ . '/bootstrap/app.php';
header('Content-Type: application/json');
if (!isLoggedIn()) {
    echo json_encode(['error' => 'not logged in']);
    exit;
}

echo json_encode([
    'email' => $_SESSION['user_email'],
    'user_id' => $_SESSION['user_id'],
    'tenant_id' => $_SESSION['tenant_id'],
    'permissions' => $_SESSION['permissions'] ?? 'NOT SET',
    'version' => $_SESSION['permission_version'] ?? 'NOT SET'
]);
