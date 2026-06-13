<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../config/db.php';

$sql = "
CREATE TABLE IF NOT EXISTS `platform_tickets` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `created_by` int(11) NOT NULL,
    `subject` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `status` enum('Open','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Open',
    `priority` enum('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
    `assigned_to` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `tenant_id` (`tenant_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `platform_ticket_comments` (
    `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
    `ticket_id` BIGINT NOT NULL,
    `user_id` BIGINT NOT NULL,
    `comment` TEXT NOT NULL,
    `is_internal` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `platform_ticket_tags` (
    `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
    `ticket_id` BIGINT NOT NULL,
    `tag` VARCHAR(100) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($sql);
    
    // Add CSAT columns if they don't exist
    try { $pdo->exec("ALTER TABLE `platform_tickets` ADD COLUMN `csat_score` INT DEFAULT NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE `platform_tickets` ADD COLUMN `csat_comment` TEXT DEFAULT NULL"); } catch (PDOException $e) {}
    
    echo "Platform ticketing tables created successfully.\n";
} catch (Exception $e) {
    echo "[ERROR] Failed running setup_platform_tickets.php: " . $e->getMessage() . "\n";
}
