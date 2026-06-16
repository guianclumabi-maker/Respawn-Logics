<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');

echo "Migrating Notifications Table...\n";

// notifications
$pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
    `id`          BIGINT PRIMARY KEY AUTO_INCREMENT,
    `tenant_id`   VARCHAR(50)  NOT NULL,
    `user_email`  VARCHAR(150) NOT NULL,
    `title`       VARCHAR(255) NOT NULL,
    `body`        TEXT         NOT NULL,
    `type`        VARCHAR(50)  DEFAULT 'info',
    `link`        VARCHAR(255) DEFAULT NULL,
    `is_read`     TINYINT(1)   DEFAULT 0,
    `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notifications_user` (`tenant_id`, `user_email`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

echo "Notifications Table migrated successfully.\n";
