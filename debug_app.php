<?php
$_ENV['DB_NAME'] = 'employee_system_test';
putenv('DB_NAME=' . $_ENV['DB_NAME']);
require __DIR__ . '/bootstrap/app.php';
global $pdo, $config;
echo "PDO Database: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
echo "Config DB Name: " . $config['database']['name'] . "\n";
