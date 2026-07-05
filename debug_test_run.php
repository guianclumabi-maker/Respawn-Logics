<?php
require 'tests/Integration/HttpTestServer.php';
class Dummy {
    use Tests\Integration\HttpTestServer;
    public static function run() {
        self::startServer();
        self::fetchCsrf();
        var_dump(self::$cookies);
        var_dump(self::$csrfToken);
        
        $r = self::loginAs('admin@tenantB.com');
        print_r($r);
        self::stopServer();
    }
}
Dummy::run();
