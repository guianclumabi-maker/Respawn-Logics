<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');

echo "Adding resume columns to candidate_profiles...\n";

try {
    $pdo->exec("
        ALTER TABLE `candidate_profiles`
        ADD COLUMN `resume_file_path` VARCHAR(255) NULL,
        ADD COLUMN `resume_filename` VARCHAR(255) NULL,
        ADD COLUMN `resume_mime` VARCHAR(100) NULL,
        ADD COLUMN `resume_uploaded_at` DATETIME NULL
    ");
    echo "Columns added successfully.\n";
} catch (PDOException $e) {
    // Ignore duplicate column errors
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist, skipping.\n";
    } else {
        echo "Error adding columns: " . $e->getMessage() . "\n";
    }
}
