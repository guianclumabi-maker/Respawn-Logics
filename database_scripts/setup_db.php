<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS user_activation_tokens (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        user_id BIGINT NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        INDEX(token)
    );');
    $pdo->exec('CREATE TABLE IF NOT EXISTS import_batches (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        tenant_id VARCHAR(50) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        setup_mode VARCHAR(50) NOT NULL,
        total_rows INT NOT NULL DEFAULT 0,
        success_rows INT NOT NULL DEFAULT 0,
        failed_rows INT NOT NULL DEFAULT 0,
        created_by BIGINT NULL,
        created_at DATETIME NOT NULL
    );');
    $pdo->exec('CREATE TABLE IF NOT EXISTS import_batch_errors (
        id BIGINT PRIMARY KEY AUTO_INCREMENT,
        batch_id BIGINT NOT NULL,
        row_num INT NOT NULL,
        error_message TEXT NOT NULL
    );');
    $cols = ['organization_unit_1', 'organization_unit_2', 'organization_unit_3', 'organization_unit_4'];
    foreach ($cols as $col) {
        $check = $pdo->query("SHOW COLUMNS FROM `users` LIKE '$col'");
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `$col` VARCHAR(150) DEFAULT NULL;");
        }
    }
    echo "Schema updated successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
