<?php

return [
    'app' => [
        'name' => $env['APP_NAME'] ?? 'Respawn Logic',
        'env' => $env['APP_ENV'] ?? 'production',
        'debug' => filter_var($env['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'url' => isset($env['RAILWAY_PUBLIC_DOMAIN']) 
                    ? 'https://' . rtrim($env['RAILWAY_PUBLIC_DOMAIN'], '/') 
                    : rtrim($env['APP_URL'] ?? 'http://localhost/respawn-logics', '/')
    ],
    'database' => [
        'host' => $env['DB_HOST'] ?? 'localhost',
        'port' => $env['DB_PORT'] ?? 3306,
        'name' => $env['DB_NAME'] ?? 'employee_system',
        'user' => $env['DB_USER'] ?? 'root',
        'pass' => $env['DB_PASS'] ?? ''
    ],
    'session' => [
        'timeout' => (int)($env['SESSION_TIMEOUT'] ?? 3600),
        'secure' => filter_var($env['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'samesite' => $env['SESSION_SAMESITE'] ?? 'Lax',
        'httponly' => filter_var($env['SESSION_HTTPONLY'] ?? true, FILTER_VALIDATE_BOOLEAN)
    ],
    'cors' => [
        'allowed_origins' => $env['ALLOWED_ORIGINS'] ?? ''
    ]
];
