<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

try {
    $pdo->exec("ALTER TABLE `candidate_applications` ADD COLUMN `score_source` VARCHAR(50) DEFAULT NULL AFTER `ai_match_score`");
    $pdo->exec("ALTER TABLE `candidate_applications` ADD COLUMN `scored_at` DATETIME DEFAULT NULL AFTER `score_source`");
    echo "Added score_source and scored_at columns to candidate_applications.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
