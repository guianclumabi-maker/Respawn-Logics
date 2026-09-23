<?php

// Parse DATABASE_URL / MYSQL_URL / MYSQL_PRIVATE_URL if provided (Railway / cloud standard format)
$dbUrl = $env['DATABASE_URL'] ?? $env['MYSQL_URL'] ?? $env['MYSQL_PRIVATE_URL'] ?? null;
$parsedUrl = [];
if (!empty($dbUrl)) {
    $parsed = parse_url($dbUrl);
    if ($parsed) {
        if (!empty($parsed['host'])) $parsedUrl['host'] = $parsed['host'];
        if (!empty($parsed['port'])) $parsedUrl['port'] = (int)$parsed['port'];
        if (!empty($parsed['user'])) $parsedUrl['user'] = urldecode($parsed['user']);
        if (isset($parsed['pass']))  $parsedUrl['pass'] = urldecode($parsed['pass']);
        if (!empty($parsed['path'])) $parsedUrl['name'] = ltrim($parsed['path'], '/');
    }
}

// In Railway or remote deployments, prioritize cloud database host (MYSQLHOST or DATABASE_URL)
// over any local fallback.
$dbHost = $parsedUrl['host'] ?? $env['MYSQLHOST'] ?? $env['MYSQL_HOST'] ?? $env['DB_HOST'] ?? 'localhost';
if (($dbHost === 'localhost' || $dbHost === '127.0.0.1') && !empty($env['MYSQLHOST'])) {
    $dbHost = $env['MYSQLHOST'];
} elseif (($dbHost === 'localhost' || $dbHost === '127.0.0.1') && !empty($parsedUrl['host'])) {
    $dbHost = $parsedUrl['host'];
}

$dbPort = $env['DB_PORT'] ?? $env['MYSQLPORT'] ?? $env['MYSQL_PORT'] ?? $parsedUrl['port'] ?? 3306;
$dbName = $env['DB_NAME'] ?? $env['DB_DATABASE'] ?? $env['MYSQLDATABASE'] ?? $env['MYSQL_DATABASE'] ?? $parsedUrl['name'] ?? 'employee_system';
$dbUser = $env['DB_USER'] ?? $env['MYSQLUSER'] ?? $env['MYSQL_USER'] ?? $parsedUrl['user'] ?? 'root';
$dbPass = $env['DB_PASS'] ?? $env['DB_PASSWORD'] ?? $env['MYSQLPASSWORD'] ?? $env['MYSQL_PASSWORD'] ?? $parsedUrl['pass'] ?? '';

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
        'host' => $dbHost,
        'port' => (int)$dbPort,
        'name' => $dbName,
        'user' => $dbUser,
        'pass' => $dbPass
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
