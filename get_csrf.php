<?php
require_once __DIR__ . '/bootstrap/app.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Generate CSRF token if one does not exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Flag that an onboarding session is active
$_SESSION['onboarding_active'] = true;

// Optionally bind to a tenant ID (mocked as 1 for now)
if (empty($_SESSION['tenant_id'])) {
    $_SESSION['tenant_id'] = 1;
}

echo json_encode([
    'success' => true,
    'csrf_token' => $_SESSION['csrf_token']
]);
