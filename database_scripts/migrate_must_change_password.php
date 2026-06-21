<?php
if (!defined('MIGRATION_SAFE')) define('MIGRATION_SAFE', true);
require_once __DIR__ . '/../bootstrap/app.php';

try {
    echo "Checking users.must_change_password column...\n";
    $colCheck = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'must_change_password'");
    if ($colCheck->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) DEFAULT 0;");
        echo "- Added must_change_password to users.\n";
    } else {
        echo "- users table already has the must_change_password column.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
