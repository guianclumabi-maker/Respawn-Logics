<?php
require_once __DIR__ . '/bootstrap/app.php';
header('Content-Type: application/json');

try {
    $tablesStmt = $pdo->query("SHOW TABLES");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    $columns = [];
    $tokens = [];
    if (in_array('users', $tables)) {
        $colsStmt = $pdo->query("SHOW COLUMNS FROM users");
        $columns = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (in_array('user_activation_tokens', $tables)) {
        $tokensStmt = $pdo->query("SELECT * FROM user_activation_tokens ORDER BY id DESC LIMIT 10");
        $tokens = $tokensStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'hostname' => gethostname(),
        'tables' => $tables,
        'users_columns' => $columns,
        'activation_tokens' => $tokens,
        'cookies' => $_COOKIE,
        'session' => $_SESSION
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
