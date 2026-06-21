<?php

class Logger {
    private static $logFile = __DIR__ . '/../logs/app.log';

    public static function log($level, $message, $context = []) {
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $tenantId = null;
        if (isset($_SESSION['tenant_id'])) {
            $tenantId = $_SESSION['tenant_id'];
        } elseif (isset($_SERVER['HTTP_X_TENANT_ID'])) {
            $tenantId = $_SERVER['HTTP_X_TENANT_ID'];
        }

        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

        $logEntry = [
            'timestamp' => date('c'),
            'level' => strtoupper($level),
            'message' => $message,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'context' => $context
        ];

        $json = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents(self::$logFile, $json, FILE_APPEND | LOCK_EX);
    }

    public static function info($message, $context = []) {
        self::log('info', $message, $context);
    }

    public static function error($message, $context = []) {
        self::log('error', $message, $context);
    }

    public static function warning($message, $context = []) {
        self::log('warning', $message, $context);
    }

    public static function debug($message, $context = []) {
        self::log('debug', $message, $context);
    }
}
