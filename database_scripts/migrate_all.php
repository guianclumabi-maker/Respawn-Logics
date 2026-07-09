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

foreach ($scripts as $script) {
    $path = __DIR__ . '/' . $script;
    if (file_exists($path)) {
        echo "[RUNNING] $script\n";
        try {
            // Obfuscate duplicate bootstrap/app.php load if necessary
            // since require_once is used inside them, it is safe to include.
            include $path;
            echo "\n------------------------------------\n";
        } catch (Throwable $e) {
            echo "[ERROR] Failed running $script: " . $e->getMessage() . "\n";
            echo "------------------------------------\n";
        }
    } else {
        echo "[WARNING] Migration file not found: $script\n";
        echo "------------------------------------\n";
    }
}

$extraMigrations = [
    __DIR__ . '/../database/migrations/rbac_phase1.php',
    __DIR__ . '/../database/migrations/rbac_phase2.php',
    __DIR__ . '/../backend/migrations/migrate_statutory_rates.php',
];
foreach ($extraMigrations as $path) {
    if (file_exists($path)) {
        echo "[RUNNING] " . basename($path) . "\n";
        try { include $path; echo "\n------------------------------------\n"; }
        catch (Throwable $e) { echo "[ERROR] " . basename($path) . ": " . $e->getMessage() . "\n------------------------------------\n"; }
    } else {
        echo "[WARNING] not found: $path\n";
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "All migrations finished!\n";
