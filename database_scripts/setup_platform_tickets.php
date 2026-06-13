<?php
require_once __DIR__ . '/config/db.php';

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
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `comment` text NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($sql);
    echo "Platform ticketing tables created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
