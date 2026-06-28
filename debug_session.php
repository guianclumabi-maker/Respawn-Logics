<?php
require_once __DIR__ . '/bootstrap/app.php';
header('Content-Type: application/json');
echo json_encode([
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'headers' => getallheaders()
]);
