<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

/**
 * Holiday calendar — per-tenant list of regular holidays and special non-working days.
 * Used by the timesheet auto-draft (Phase B) to classify worked hours into the correct
 * pay buckets (regular_holiday_hours vs special_day_hours vs normal). Tenant-scoped, idempotent.
 */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `holiday_calendar` (
        `id`           BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`    VARCHAR(50) NOT NULL,
        `holiday_date` DATE NOT NULL,
        `name`         VARCHAR(255) NOT NULL,
        `type`         VARCHAR(30) NOT NULL DEFAULT 'Regular Holiday', -- 'Regular Holiday' | 'Special Non-Working'
        `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_holiday` (`tenant_id`, `holiday_date`),
        INDEX `idx_hol_tenant` (`tenant_id`, `holiday_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Holiday calendar table verified.\n";
} catch (PDOException $e) {
    echo "Error (migrate_holidays): " . $e->getMessage() . "\n";
}
