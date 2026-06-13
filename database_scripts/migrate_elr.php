<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

try {
    echo "Starting ELR (Phase 7) schema migration...\n";

    // 1. Add is_confidential to service_ticket_types
    try {
        $pdo->exec("ALTER TABLE `service_ticket_types` ADD COLUMN `is_confidential` TINYINT(1) NOT NULL DEFAULT 0");
        echo "- Added is_confidential column to service_ticket_types.\n";
    } catch (PDOException $e) {
        // Ignore if column already exists
        echo "- is_confidential column already exists or error: " . $e->getMessage() . "\n";
    }

    // 2. Seed Employee Relations Team
    $stmt = $pdo->prepare("SELECT id FROM `service_teams` WHERE `name` = 'Employee Relations'");
    $stmt->execute();
    $erTeamId = $stmt->fetchColumn();

    if (!$erTeamId) {
        $pdo->exec("INSERT INTO `service_teams` (`name`, `description`) VALUES ('Employee Relations', 'Handles disciplinary actions, PIPs, and grievances.')");
        $erTeamId = $pdo->lastInsertId();
        echo "- Seeded Employee Relations team.\n";
    }

    // 3. Seed Confidential Ticket Types
    $types = ['Disciplinary Action', 'Performance Improvement Plan (PIP)', 'Grievance / Incident Report'];
    foreach ($types as $type) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `service_ticket_types` WHERE `name` = ?");
        $stmt->execute([$type]);
        if ($stmt->fetchColumn() == 0) {
            $insert = $pdo->prepare("INSERT INTO `service_ticket_types` (`name`, `default_team_id`, `is_confidential`) VALUES (?, ?, 1)");
            $insert->execute([$type, $erTeamId]);
        }
    }
    echo "- Seeded Confidential Ticket Types.\n";

    echo "ELR Migration completed successfully!\n";

} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
