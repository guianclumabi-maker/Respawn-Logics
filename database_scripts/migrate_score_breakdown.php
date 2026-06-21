<?php
require_once __DIR__ . '/../db.php';

try {
    $pdo->exec("ALTER TABLE `candidate_applications` ADD COLUMN `score_breakdown` JSON DEFAULT NULL AFTER `ai_match_score`");
    echo "Added score_breakdown column to candidate_applications.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
