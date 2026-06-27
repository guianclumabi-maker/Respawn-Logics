<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
header('Content-Type: text/plain');
require_once __DIR__ . '/../bootstrap/app.php';

echo "Starting All Migrations...\n";
echo "====================================\n";

// Disable foreign key checks to prevent lock/constraint errors during table creation
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

$scripts = [
    'migrate_tenants.php',
    'migrate_core_hr.php',
    'migrate_ats_tables.php',
    'migrate_ats_queries.php',
    'migrate_ats_indexes.php',
    'migrate_benefits.php',
    'migrate_compensation.php',
    'migrate_elr.php',
    'migrate_elr_knowledge.php',
    'migrate_esm.php',
    'migrate_expenses.php',
    'migrate_global_cache.php',
    'migrate_knowledge_base.php',
    'migrate_onboarding.php',
    'migrate_payroll.php',
    'migrate_performance.php',
    'migrate_security.php',
    'setup_db.php',
    'iam_seed.php',
    'setup_platform_tickets.php',
    'migrate_notifications.php',
    'migrate_permissions_sync.php',
    'migrate_must_change_password.php',
    'migrate_candidate_privacy.php'
];

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
