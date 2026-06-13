<?php
require_once __DIR__ . '/bootstrap/app.php';

try {
    // Add columns for RLHF (Reinforcement Learning from Human Feedback)
    $pdo->exec("ALTER TABLE `global_intelligence_cache` 
        ADD COLUMN `confidence_score` INT DEFAULT 50 AFTER `status`,
        ADD COLUMN `upvotes` INT DEFAULT 0 AFTER `confidence_score`,
        ADD COLUMN `downvotes` INT DEFAULT 0 AFTER `upvotes`
    ");

    echo "Successfully upgraded Global Cache with Deep Learning architecture.\n";

} catch (PDOException $e) {
    // Ignore error if columns already exist
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Deep learning columns already exist.\n";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
