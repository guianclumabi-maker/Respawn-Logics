<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

try {
    echo "Starting ESM schema migration...\n";

    // 1. service_teams
    $pdo->exec("CREATE TABLE IF NOT EXISTS `service_teams` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created service_teams table.\n";

    // Seed default teams
    $teamCheck = $pdo->query("SELECT COUNT(*) FROM `service_teams`")->fetchColumn();
    if ($teamCheck == 0) {
        $pdo->exec("INSERT INTO `service_teams` (`name`) VALUES ('HR'), ('Payroll'), ('IT'), ('Facilities'), ('Benefits')");
        echo "- Seeded default service_teams.\n";
    }

    // 2. service_ticket_types
    $pdo->exec("CREATE TABLE IF NOT EXISTS `service_ticket_types` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `default_team_id` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created service_ticket_types table.\n";

    // Seed default ticket types (Link to Teams for auto-routing)
    $typeCheck = $pdo->query("SELECT COUNT(*) FROM `service_ticket_types`")->fetchColumn();
    if ($typeCheck == 0) {
        $pdo->exec("
            INSERT INTO `service_ticket_types` (`name`, `default_team_id`) VALUES 
            ('HR Request', (SELECT id FROM service_teams WHERE name='HR')),
            ('Payroll Inquiry', (SELECT id FROM service_teams WHERE name='Payroll')),
            ('Leave Issue', (SELECT id FROM service_teams WHERE name='HR')),
            ('Benefits Question', (SELECT id FROM service_teams WHERE name='Benefits')),
            ('IT Request', (SELECT id FROM service_teams WHERE name='IT')),
            ('Facilities Request', (SELECT id FROM service_teams WHERE name='Facilities')),
            ('Other', NULL)
        ");
        echo "- Seeded default service_ticket_types.\n";
    }

    // 3. service_tickets
    $pdo->exec("CREATE TABLE IF NOT EXISTS `service_tickets` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `ticket_number` VARCHAR(50) NOT NULL UNIQUE,
        `ticket_type_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `assigned_team_id` INT DEFAULT NULL,
        `assigned_to_user_id` INT DEFAULT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Open', -- Open, In Progress, Resolved, Closed
        `priority` VARCHAR(50) NOT NULL DEFAULT 'Medium', -- Low, Medium, High, Urgent
        `sla_due_at` DATETIME DEFAULT NULL,
        `first_response_at` DATETIME DEFAULT NULL,
        `resolved_at` DATETIME DEFAULT NULL,
        `created_by` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created service_tickets table.\n";

    // 4. ticket_comments
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ticket_comments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `ticket_id` INT NOT NULL,
        `user_id` INT DEFAULT NULL, -- NULL if system generated
        `comment_type` VARCHAR(20) NOT NULL DEFAULT 'Public', -- Public, Internal, System
        `comment` TEXT NOT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created ticket_comments table.\n";

    echo "ESM Migration completed successfully!\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
