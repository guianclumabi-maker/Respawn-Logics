<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
header('Content-Type: text/plain');
require_once __DIR__ . '/../bootstrap/app.php';

echo "Starting All Migrations...\n";
echo "====================================\n";

// Disable foreign key checks to prevent lock/constraint errors during table creation
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

$coreScripts = require __DIR__ . '/schema_migrations.php';
$scripts = array_merge($coreScripts, [
    'setup_db.php',
    'iam_seed.php',
    'setup_platform_tickets.php'
]);

$extraMigrations = [
    '../database/migrations/rbac_phase1.php',
    '../database/migrations/rbac_phase2.php',
    '../backend/migrations/migrate_statutory_rates.php',
];

require_once __DIR__ . '/Migrator.php';
$migrator = new Migrator($pdo);

echo "Running migrations with tracking...\n";
try {
    $ranScripts = $migrator->run($scripts, __DIR__);
    foreach ($ranScripts as $s) {
        echo "[MIGRATED] $s\n";
    }
    
    $ranExtra = $migrator->run($extraMigrations, __DIR__);
    foreach ($ranExtra as $s) {
        echo "[MIGRATED] " . basename($s) . "\n";
    }
} catch (Throwable $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "All migrations finished!\n";
