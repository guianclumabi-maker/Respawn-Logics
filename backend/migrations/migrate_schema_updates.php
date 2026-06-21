<?php
$env = parse_ini_file(__DIR__ . '/../../.env');
$host = $env['DB_HOST'] ?? 'localhost';
$db   = $env['DB_NAME'] ?? 'employee_system';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';

if (isset($_SERVER['DATABASE_URL'])) {
    $dbOpts = parse_url($_SERVER['DATABASE_URL']);
    $host = $dbOpts['host'];
    $port = $dbOpts['port'] ?? 3306;
    $user = $dbOpts['user'];
    $pass = $dbOpts['pass'] ?? '';
    $db = ltrim($dbOpts['path'], '/');
}

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
if (isset($port)) {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
}

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to DB successfully.\n";

    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_mwe` TINYINT(1) NOT NULL DEFAULT 0");
        echo "Added is_mwe column to users.\n";
    } catch (PDOException $e) {
        echo "Column is_mwe might already exist.\n";
    }

    try {
        $pdo->exec("ALTER TABLE `payroll_runs` ADD COLUMN `run_type` ENUM('Regular', '13th Month', 'Final Pay') NOT NULL DEFAULT 'Regular'");
        echo "Added run_type column to payroll_runs.\n";
    } catch (PDOException $e) {
        echo "Column run_type might already exist.\n";
    }

    echo "Schema updates completed successfully!\n";

} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage());
}
