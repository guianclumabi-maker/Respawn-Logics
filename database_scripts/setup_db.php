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
    $pdo->exec('CREATE TABLE IF NOT EXISTS `attendance` (
        `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id` VARCHAR(50) NOT NULL,
        `employee_email` VARCHAR(150) NOT NULL,
        `time_in` DATETIME DEFAULT NULL,
        `time_out` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
    
    $pdo->exec('CREATE TABLE IF NOT EXISTS `employee_tasks` (
        `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id` VARCHAR(50) NOT NULL,
        `employee_email` VARCHAR(150) NOT NULL,
        `task_name` VARCHAR(255) NOT NULL,
        `task_description` TEXT,
        `is_completed` TINYINT(1) DEFAULT 0,
        `completed_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
    
    $pdo->exec('CREATE TABLE IF NOT EXISTS `leave_requests` (
        `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id` VARCHAR(50) NOT NULL,
        `employee_email` VARCHAR(150) NOT NULL,
        `leave_type` VARCHAR(50) NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `status` VARCHAR(50) DEFAULT "Pending",
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

    echo "Schema updated successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
