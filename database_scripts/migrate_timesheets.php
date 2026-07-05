<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

/**
 * Timesheets — the per-day approved-hours records the payroll engine reads to compute gross pay.
 * This table was referenced by PayrollService/PayrollController but never created by any migration,
 * which means timesheet-driven gross pay could not work. Schema derived from actual query usage.
 * Idempotent + tenant-scoped.
 */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `timesheets` (
        `id`                    BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`             VARCHAR(50) NOT NULL,
        `employee_id`           INT NOT NULL,                 -- users.id
        `timesheet_date`        DATE NOT NULL,
        `regular_hours`         DECIMAL(6,2) DEFAULT 0.00,
        `overtime_hours`        DECIMAL(6,2) DEFAULT 0.00,
        `rest_day_hours`        DECIMAL(6,2) DEFAULT 0.00,
        `special_day_hours`     DECIMAL(6,2) DEFAULT 0.00,
        `regular_holiday_hours` DECIMAL(6,2) DEFAULT 0.00,
        `night_diff_hours`      DECIMAL(6,2) DEFAULT 0.00,
        `status`                VARCHAR(30) DEFAULT 'Pending', -- Pending / Approved / Rejected
        `created_at`            DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at`            DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_ts_lookup` (`tenant_id`, `employee_id`, `timesheet_date`),
        INDEX `idx_ts_status` (`tenant_id`, `status`),
        UNIQUE KEY `uq_ts_day` (`tenant_id`, `employee_id`, `timesheet_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Timesheets table verified.\n";
} catch (PDOException $e) {
    echo "Error (migrate_timesheets): " . $e->getMessage() . "\n";
}
