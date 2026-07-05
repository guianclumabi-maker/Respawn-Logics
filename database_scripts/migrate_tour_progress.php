<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

/**
 * User tour progress — records which guided walkthroughs a user has completed so a
 * first-run tour shows only once (per user, across devices) unless replayed manually.
 * Tenant + user scoped, idempotent.
 */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_tour_progress` (
        `id`           BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`    VARCHAR(50) NOT NULL,
        `user_id`      INT NOT NULL,                 -- users.id
        `tour_name`    VARCHAR(100) NOT NULL,        -- e.g. 'payroll'
        `completed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_tour` (`tenant_id`, `user_id`, `tour_name`),
        INDEX `idx_tour_user` (`tenant_id`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "User tour progress table verified.\n";
} catch (PDOException $e) {
    echo "Error (migrate_tour_progress): " . $e->getMessage() . "\n";
}
