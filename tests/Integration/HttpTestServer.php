<?php

namespace Tests\Integration;

trait HttpTestServer {
    protected static $serverPid;
    protected static $serverHost = '127.0.0.1:8888';

    public static function startServer() {
        $host = self::$serverHost;
        $docroot = realpath(__DIR__ . '/../../');

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows async execution using wmic or start
            $command = "start /b php -S $host -t \"$docroot\" > NUL 2>&1";
            pclose(popen($command, "r"));
            
            // On Windows, finding the PID of `php -S` can be hard, we just hope it stops or we kill it by name later
            // Since this is a test environment, we can do a generic kill
            self::$serverPid = 'windows';
        } else {
            // Linux/Mac
            $command = "php -S $host -t \"$docroot\" > /dev/null 2>&1 & echo $!";
            self::$serverPid = exec($command);
        }

        // Wait a bit for the server to start
        sleep(1);
    }

    public static function stopServer() {
        if (self::$serverPid) {
            if (self::$serverPid === 'windows') {
                exec('taskkill /F /IM php.exe > NUL 2>&1');
            } else {
                exec('kill ' . self::$serverPid . ' > /dev/null 2>&1');
            }
        }
    }
}
