<?php
// Emergency: clear all stale php_sessions that shouldn't be logged in
require_once __DIR__ . '/bootstrap/app.php';
header('Content-Type: application/json');

// Only allow this from a secret key
$secret = $_GET['key'] ?? '';
if ($secret !== 'purge_stale_2024') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$count = $pdo->exec("DELETE FROM php_sessions WHERE 1");
echo json_encode(['success' => true, 'deleted' => $count, 'message' => 'All sessions purged']);
