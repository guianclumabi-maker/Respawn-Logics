<?php
require_once __DIR__ . '/bootstrap/app.php';
header('Content-Type: application/json');

if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 0;
}
$_SESSION['test_counter']++;

echo json_encode([
    'session_id' => session_id(),
    'hostname' => gethostname(),
    'session_save_path' => session_save_path(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'headers' => getallheaders()
]);
