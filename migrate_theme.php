<?php
require_once __DIR__ . '/../bootstrap/app.php';

try {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `theme_preference` ENUM('light', 'dark', 'system') DEFAULT 'dark'");
    echo "Successfully added theme_preference to users table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column theme_preference already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
