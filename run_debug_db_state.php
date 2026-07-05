<?php
require_once __DIR__ . '/tests/Integration/HttpTestServer.php';
require_once __DIR__ . '/tests/bootstrap.php';

class TestRunner {
    use \Tests\Integration\HttpTestServer;

    public static function run() {
        global $pdo;
        echo "Parent process DB: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
        
        self::startServer();
        
        $tenantA = \FixtureHelper::createTenant($pdo, 'Tenant A');
        \FixtureHelper::createUser($pdo, $tenantA, 'emp@tenantA.com', 'Employee');
        
        echo "Parent process users after insert: " . json_encode($pdo->query('SELECT id, email FROM users')->fetchAll(PDO::FETCH_ASSOC)) . "\n";
        
        $ch = curl_init("http://127.0.0.1:8888/api/index.php?route=test_db_debug");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        echo "Child process DB State: $res\n";
        
        self::stopServer();
    }
}
TestRunner::run();
