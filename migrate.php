<?php
define('MIGRATION_SAFE', true);

echo "Running Schema Updates...\n";
require_once __DIR__ . '/backend/migrations/migrate_schema_updates.php';

echo "\nRunning Statutory Rates Update...\n";
require_once __DIR__ . '/backend/migrations/migrate_statutory_rates.php';

echo "\nRunning Permissions Sync...\n";
require_once __DIR__ . '/database_scripts/migrate_permissions_sync.php';
