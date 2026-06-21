<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
header('Content-Type: text/plain');

try {
    echo "Adding candidate_profiles privacy columns...\n";

    $pdo->exec("ALTER TABLE `candidate_profiles` ADD COLUMN IF NOT EXISTS `consent_given` TINYINT(1) NOT NULL DEFAULT 0;");
    $pdo->exec("ALTER TABLE `candidate_profiles` ADD COLUMN IF NOT EXISTS `consent_at` DATETIME NULL;");
    $pdo->exec("ALTER TABLE `candidate_profiles` ADD COLUMN IF NOT EXISTS `consent_source` VARCHAR(100) NULL;");
    $pdo->exec("ALTER TABLE `candidate_profiles` ADD COLUMN IF NOT EXISTS `data_retention_until` DATE NULL;");
    $pdo->exec("ALTER TABLE `candidate_profiles` ADD COLUMN IF NOT EXISTS `is_anonymized` TINYINT(1) NOT NULL DEFAULT 0;");

    // Backfill existing
    $pdo->exec("UPDATE `candidate_profiles` SET `consent_given` = 1, `consent_at` = `created_at`, `data_retention_until` = DATE_ADD(`created_at`, INTERVAL 24 MONTH) WHERE `consent_given` = 0;");
    
    echo "Successfully migrated privacy columns.\n";
} catch (PDOException $e) {
    echo "Error migrating candidate_profiles: " . $e->getMessage() . "\n";
    throw $e;
}
