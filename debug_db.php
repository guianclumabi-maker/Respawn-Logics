<?php
// Check what DB the server-side bootstrap actually uses
putenv('APP_ENV=testing');
putenv('DB_NAME=employee_system_test');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3306');
putenv('DB_USER=root');
putenv('DB_PASS=');
$_ENV = array_merge($_ENV, [
    'APP_ENV' => 'testing',
    'DB_NAME' => 'employee_system_test',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_USER' => 'root',
    'DB_PASS' => '',
]);

require __DIR__ . '/bootstrap/app.php';
global $pdo;

$db = $pdo->query("SELECT DATABASE()")->fetchColumn();
echo "DB: $db\n";

// Look for our seeded test users
$res = $pdo->query("SELECT email, role FROM users WHERE email LIKE '%test.com' OR email LIKE '%tenantA%' OR email LIKE '%tenantB%' OR email LIKE '%elradmin%' LIMIT 20");
echo "Users in DB:\n";
foreach ($res->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "  {$row['email']} ({$row['role']})\n";
}
