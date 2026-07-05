<?php
putenv('APP_ENV=testing');
putenv('DB_NAME=employee_system_test');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3306');
putenv('DB_USER=root');
putenv('DB_PASS=');
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_NAME'] = 'employee_system_test';
$_ENV['DB_HOST'] = '127.0.0.1';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASS'] = '';

require __DIR__ . '/bootstrap/app.php';
global $pdo;

echo "Session status after bootstrap: " . session_status() . " (2=active)\n";
echo "CSRF in SESSION: " . ($_SESSION['csrf_token'] ?? '(empty)') . "\n";
echo "Session ID: " . session_id() . "\n";

// Check if php_sessions table exists
try {
    $res = $pdo->query("SELECT COUNT(*) FROM php_sessions");
    echo "php_sessions table exists, rows: " . $res->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "php_sessions table error: " . $e->getMessage() . "\n";
}

// Check what DB the pdo is on
$res = $pdo->query("SELECT DATABASE()");
echo "Active DB: " . $res->fetchColumn() . "\n";
