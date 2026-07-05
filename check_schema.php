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

// Check the three columns we patched
$res = $pdo->query("SHOW COLUMNS FROM users WHERE Field IN ('department','immediate_supervisor','profile_image')");
echo "Column|Null|Default\n";
while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . " | NULL=" . $r['Null'] . " | DEFAULT=" . var_export($r['Default'], true) . " | EXTRA=" . $r['Extra'] . "\n";
}

// Also check the full column list so we can find ALL NOT NULL / no-default columns
$res2 = $pdo->query("SHOW COLUMNS FROM users WHERE `Null`='NO' AND `Default` IS NULL AND Extra NOT LIKE '%auto_increment%'");
echo "\n--- ALL NOT NULL / no-default users columns (potential INSERT bombs) ---\n";
while ($r = $res2->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . "\n";
}
