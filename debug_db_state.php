<?php
require_once __DIR__ . '/tests/Integration/HttpTestServer.php';
require_once __DIR__ . '/tests/bootstrap.php';

class TestRunner {
    use \Tests\Integration\HttpTestServer;

    public static function run() {
        global ;
        self::startServer();
        
        // Seed users
        $tenantA = \FixtureHelper::createTenant($pdo, 'Tenant A');
        \FixtureHelper::createUser($pdo, $tenantA, 'emp@tenantA.com', 'Employee');
        
        // We will make a direct curl call to a test endpoint that returns the DB name and user count
        $ch = curl_init("http://127.0.0.1:8888/api/index.php?route=test_db_debug");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        echo "Server DB State: $res
";
        
        self::stopServer();
    }
}
TestRunner::run();