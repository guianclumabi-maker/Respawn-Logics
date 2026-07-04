<?php
// Database configurations for Employee System
global $config, $pdo;
$dbConfig = $config['database'] ?? [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'employee_system',
    'user' => 'root',
    'pass' => ''
];

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['name']
    );
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    // Persistent connections are pooled per-process and reused across requests inside the
    // long-running `php -S` integration-test server. A pooled handle can outlive DB
    // drops/recreates and keep serving requests against a stale connection. In the testing
    // environment we therefore want a FRESH connection per request; keep persistence
    // everywhere else for performance.
    $isTesting = (getenv('APP_ENV') === 'testing')
        || (substr((string)$dbConfig['name'], -5) === '_test');
    if (!$isTesting) {
        $pdoOptions[PDO::ATTR_PERSISTENT] = true;
    }
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $pdoOptions);
    // Force MySQL session to UTC to align with PHP's timezone
    $pdo->exec("SET time_zone = '+00:00';");
} catch (PDOException $e) {
    // Attempt local database creation if it's missing
    if ($e->getCode() == 1049 && $dbConfig['host'] === 'localhost') {
        try {
            $dsnNoDb = sprintf(
                'mysql:host=%s;port=%s;charset=utf8mb4',
                $dbConfig['host'],
                $dbConfig['port']
            );
            $pdo = new PDO($dsnNoDb, $dbConfig['user'], $dbConfig['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbConfig['name']}`");
            
            // Reconnect
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $ex) {
            die("Database connection failed: " . $ex->getMessage());
        }
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}
