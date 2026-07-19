<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
// CLI-safe header (see migrate_all.php).
if (php_sapi_name() !== 'cli' && !headers_sent()) header('Content-Type: text/plain');

/**
 * Attendance → users hard link.
 *
 * WHY: attendance rows were linked to employees ONLY by email string
 * (LOWER(a.employee_email) = LOWER(u.email)). If an employee's email is ever
 * edited, their punches silently detach → timesheet drafts miss their hours →
 * payroll underpays with no error. This adds a stable `user_id` FK-style column.
 * Named user_id (not employee_id) because users.employee_id is the human-readable
 * employee NUMBER, not the PK.
 *
 * Idempotent: information_schema checks; backfill only touches NULL rows.
 * The email column stays for legacy rows and display; new writes set both.
 */
try {
    global $pdo;

    $colChk = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'user_id'");
    if ((int)$colChk->fetchColumn() === 0) {
        echo "Adding attendance.user_id...\n";
        $pdo->exec("ALTER TABLE `attendance` ADD COLUMN `user_id` INT NULL AFTER `employee_email`");
    }

    $idxChk = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance' AND INDEX_NAME = 'idx_att_user'");
    if ((int)$idxChk->fetchColumn() === 0) {
        echo "Adding idx_att_user index...\n";
        $pdo->exec("ALTER TABLE `attendance` ADD INDEX `idx_att_user` (`tenant_id`, `user_id`)");
    }

    // Backfill: match legacy rows to users by email within the same tenant.
    echo "Backfilling user_id from email matches...\n";
    $pdo->exec("UPDATE `attendance` a
        JOIN `users` u ON u.`tenant_id` = a.`tenant_id` AND LOWER(u.`email`) = LOWER(a.`employee_email`)
        SET a.`user_id` = u.`id`
        WHERE a.`user_id` IS NULL");

    $orphans = $pdo->query("SELECT COUNT(*) FROM `attendance` WHERE `user_id` IS NULL")->fetchColumn();
    echo "Done. Unmatched legacy rows (no user with that email): {$orphans}\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    throw $e; // fail loud — deploy should not proceed on a broken migration
}
