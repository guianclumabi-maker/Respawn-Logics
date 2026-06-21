<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
header('Content-Type: text/plain');

try {
    echo "Adding candidate_profiles privacy columns...\n";

    $columns = [
        'consent_given' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'consent_at' => 'DATETIME NULL',
        'consent_source' => 'VARCHAR(100) NULL',
        'data_retention_until' => 'DATE NULL',
        'is_anonymized' => 'TINYINT(1) NOT NULL DEFAULT 0'
    ];

    foreach ($columns as $col => $def) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'candidate_profiles' AND COLUMN_NAME = ?");
        $stmt->execute([$col]);
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("ALTER TABLE `candidate_profiles` ADD COLUMN `$col` $def;");
        }
    }

    // Backfill existing
    $pdo->exec("UPDATE `candidate_profiles` SET `consent_given` = 1, `consent_at` = `created_at`, `data_retention_until` = DATE_ADD(`created_at`, INTERVAL 24 MONTH) WHERE `consent_given` = 0;");
    
    echo "Successfully migrated privacy columns.\n";
} catch (PDOException $e) {
    echo "Error migrating candidate_profiles: " . $e->getMessage() . "\n";
    throw $e;
}
