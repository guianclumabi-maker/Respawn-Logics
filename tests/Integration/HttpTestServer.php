<?php

namespace Tests\Integration;

trait HttpTestServer {
    protected static $serverProcessResource;
    protected static $serverHost = '127.0.0.1:8888';

    public static function startServer() {
        $host = self::$serverHost;
        $docroot = realpath(__DIR__ . '/../../');

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', 'NUL', 'w'], // stdout to NUL
            2 => ['file', __DIR__ . '/../../php_error.log', 'w'], // stderr to file
        ];
        
        // Pass the FULL test context explicitly. getenv() with no arguments is unreliable
        // on Windows for values PHPUnit set at runtime via putenv(), so every key the child
        // server needs must be spelled out here rather than assumed to be inherited.
        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing',              // lets config/db.php disable persistent PDO
            'DB_NAME' => 'employee_system_test',
            'DB_HOST' => getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1',
            'DB_PORT' => getenv('DB_PORT') !== false ? getenv('DB_PORT') : '3306',
            'DB_USER' => getenv('DB_USER') !== false ? getenv('DB_USER') : 'root',
            'DB_PASS' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
        ]);

        $phpBin = PHP_BINARY;
        self::$serverProcessResource = proc_open("\"$phpBin\" -S $host -t \"$docroot\"", $descriptors, $pipes, __DIR__ . '/../../', $env);

        // Wait a bit for the server to start
        sleep(1);
    }

    public static function stopServer() {
        if (self::$serverProcessResource) {
            proc_terminate(self::$serverProcessResource);
            proc_close(self::$serverProcessResource);
            self::$serverProcessResource = null;
        }
    }

    protected static $cookies = [];
    protected static $csrfToken = null;

    public static function http(string $method, string $path, array $data = [], array $headers = []): array
    {
        $url = "http://" . self::$serverHost . $path;
        $allHeaders = [];
        if (!empty($data) && $method !== 'GET') {
            $allHeaders[] = "Content-type: application/json";
        }
        if (!empty(self::$cookies)) {
            $cookieParts = [];
            foreach (self::$cookies as $k => $v) {
                $cookieParts[] = "$k=$v";
            }
            $allHeaders[] = "Cookie: " . implode('; ', $cookieParts);
        }
        if (self::$csrfToken) {
            $allHeaders[] = "X-CSRF-Token: " . self::$csrfToken;
        }
        foreach ($headers as $h) {
            $allHeaders[] = $h;
        }

        $options = [
            'http' => [
                'header'  => implode("\r\n", $allHeaders) . "\r\n",
                'method'  => $method,
                'ignore_errors' => true
            ]
        ];
        if (!empty($data) && $method !== 'GET') {
            $options['http']['content'] = json_encode($data);
        }

        $context  = stream_context_create($options);
        $body = @file_get_contents($url, false, $context);

        if (isset($http_response_header)) {
            foreach ($http_response_header as $hdr) {
                if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/', $hdr, $matches)) {
                    self::$cookies[$matches[1]] = $matches[2];
                }
            }
        }

        $code = 500;
        if (isset($http_response_header[0]) && preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }

        return [
            'code' => $code,
            'body' => $body,
            'json' => json_decode($body ?: '', true)
        ];
    }

    public static function fetchCsrf(): void
    {
        $r = self::http('GET', '/api/index.php?route=auth&action=csrf');
        if (isset($r['json']['csrf_token'])) {
            self::$csrfToken = $r['json']['csrf_token'];
        }
    }

    public static function loginAs(string $email, string $password = 'password123'): array
    {
        self::$cookies = [];
        self::$csrfToken = null;
        
        self::fetchCsrf();
        
        $r = self::http('POST', '/api/index.php?route=auth&action=login', [
            'email' => $email,
            'password' => $password
        ]);
        
        self::fetchCsrf();
        return $r;
    }

    public static function apiGet(string $path): array
    {
        return self::http('GET', $path);
    }

    public static function apiPost(string $path, array $data = []): array
    {
        return self::http('POST', $path, $data);
    }
}
