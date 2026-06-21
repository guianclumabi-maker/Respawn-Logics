<?php
/**
 * Idempotent migration script intended to be run during deployment or as a release command.
 * Wrapper around the main migrate_all.php script to ensure it can be safely executed from CLI.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

define('MIGRATION_SAFE', true);

// We need to resolve the path relative to this script
$migrateAllPath = __DIR__ . '/../../database_scripts/migrate_all.php';

if (!file_exists($migrateAllPath)) {
    die("Error: migrate_all.php not found at $migrateAllPath\n");
}

echo "Starting deployment migrations...\n";
require_once $migrateAllPath;
echo "Deployment migrations completed successfully.\n";
