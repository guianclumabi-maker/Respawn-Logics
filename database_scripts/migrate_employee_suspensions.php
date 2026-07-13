<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

/**
 * Employee suspension / reinstatement.
 * `users.employment_status` already carries the current state ('Active' | 'Suspended')
 * and every module already filters WHERE employment_status = 'Active', so a suspended
 * employee is automatically excluded from payroll, analytics, announcements, etc.
 * This table stores the full suspension RECORD/history (reason, dates, who, ELR link)
 * for due-process and audit — one row per suspension event. Idempotent.
 */
try {
    echo "Starting employee_suspensions migration...\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS `employee_suspensions` (
        `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id` VARCHAR(50) NOT NULL,
        `employee_id` BIGINT NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'Active',      -- 'Active' (in effect) | 'Lifted'
        `source` VARCHAR(20) NOT NULL DEFAULT 'Standalone',  -- 'Standalone' | 'ELR'
        `elr_case_id` BIGINT DEFAULT NULL,
        `reason` TEXT DEFAULT NULL,
        `start_date` DATE DEFAULT NULL,
        `end_date` DATE DEFAULT NULL,                        -- optional planned reinstatement date
        `suspended_by` BIGINT DEFAULT NULL,
        `reinstated_at` DATETIME DEFAULT NULL,
        `reinstated_by` BIGINT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_es_tenant_emp` (`tenant_id`, `employee_id`),
        INDEX `idx_es_tenant_status` (`tenant_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Defensive: make sure employment_status can hold 'Suspended' (already VARCHAR(50), so a no-op on most installs).
    try {
        $pdo->exec("ALTER TABLE `users` MODIFY `employment_status` VARCHAR(50) NOT NULL DEFAULT 'Active'");
    } catch (PDOException $e) { /* column already fine */ }

    echo "employee_suspensions migration complete.\n";
} catch (PDOException $e) {
    echo "employee_suspensions migration failed: " . $e->getMessage() . "\n";
    error_log('[migrate_employee_suspensions] ' . $e->getMessage());
}
