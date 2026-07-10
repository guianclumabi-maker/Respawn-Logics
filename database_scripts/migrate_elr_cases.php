<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

/**
 * Core ELR case tables. These were queried by ELRController but never created by any
 * migration — so they existed only on dev machines and were missing in production,
 * causing "Database error" on My HR Cases and the ELR Admin Console. Idempotent.
 */
try {
    echo "Starting ELR core case tables migration...\n";

    // 1. Create elr_case_types
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_case_types` (
        `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id` VARCHAR(50) NOT NULL DEFAULT 'default_tenant',
        `name` VARCHAR(255) NOT NULL,
        `is_confidential` TINYINT(1) DEFAULT 0,
        `default_task_template` LONGTEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_ect_tenant` (`tenant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    try {
        $pdo->exec("ALTER TABLE `elr_case_types` ADD COLUMN `is_confidential` TINYINT(1) DEFAULT 0");
    } catch (PDOException $e) {
        // Ignore if column already exists
    }
    echo "- Table `elr_case_types` verified.\n";

    // 2. Create elr_cases
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_cases` (
        `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id` VARCHAR(50) NOT NULL DEFAULT 'default_tenant',
        `case_number` VARCHAR(50) NOT NULL UNIQUE,
        `employee_id` VARCHAR(100) NULL,
        `department` VARCHAR(255) NULL,
        `case_type_id` BIGINT NULL,
        `severity` VARCHAR(50) DEFAULT 'Low',
        `status` VARCHAR(50) DEFAULT 'Open',
        `created_by` VARCHAR(100) NULL,
        `description` TEXT NULL,
        `reported_by_employee_id` VARCHAR(100) NULL,
        `anonymous_report` TINYINT(1) DEFAULT 0,
        `is_confidential` TINYINT(1) DEFAULT 0,
        `investigator_id` VARCHAR(100) NULL,
        `restricted_access_roles` JSON NULL,
        `target_resolution_date` DATE NULL,
        `sla_status` VARCHAR(50) DEFAULT 'On Track',
        `resolution` TEXT NULL,
        `date_opened` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `date_closed` DATETIME NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_ec_tenant` (`tenant_id`),
        INDEX `idx_ec_emp` (`tenant_id`, `employee_id`),
        INDEX `idx_ec_status` (`status`),
        INDEX `idx_ec_case_type` (`case_type_id`),
        CONSTRAINT `fk_elr_cases_type` FOREIGN KEY (`case_type_id`) REFERENCES `elr_case_types` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Table `elr_cases` verified.\n";

    // 3. Create elr_case_timeline
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_case_timeline` (
        `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id` VARCHAR(50) NOT NULL DEFAULT '1',
        `case_id` BIGINT NOT NULL,
        `event_type` VARCHAR(100) NULL,
        `description` TEXT NULL,
        `actor` VARCHAR(150) NULL,
        `old_value` TEXT NULL,
        `new_value` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_ectl_case` (`case_id`),
        INDEX `idx_ectl_tenant` (`tenant_id`),
        CONSTRAINT `fk_elr_case_timeline_case` FOREIGN KEY (`case_id`) REFERENCES `elr_cases` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Table `elr_case_timeline` verified.\n";

    // 4. Seed default case types if empty
    $count = $pdo->query("SELECT COUNT(*) FROM `elr_case_types` WHERE `tenant_id` = 'default_tenant'")->fetchColumn();
    if ($count == 0) {
        $defaultTypes = [
            ['default_tenant', 'Disciplinary Action', 1],
            ['default_tenant', 'PIP (Performance Improvement Plan)', 1],
            ['default_tenant', 'Grievance / Incident Report', 1],
            ['default_tenant', 'General HR Case', 0]
        ];
        $stmt = $pdo->prepare("INSERT INTO `elr_case_types` (`tenant_id`, `name`, `is_confidential`) VALUES (?, ?, ?)");
        foreach ($defaultTypes as $type) {
            $stmt->execute($type);
        }
        echo "- Seeded default case types.\n";
    }

    echo "ELR core case tables migration completed successfully!\n";
} catch (PDOException $e) {
    echo "Error (migrate_elr_cases): " . $e->getMessage() . "\n";
    throw $e;
}
