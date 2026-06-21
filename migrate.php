<?php
echo "Running Schema Updates...\n";
require_once __DIR__ . '/backend/migrations/migrate_schema_updates.php';

echo "\nRunning Statutory Rates Update...\n";
require_once __DIR__ . '/backend/migrations/migrate_statutory_rates.php';
