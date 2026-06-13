<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/bootstrap/app.php';

echo "Starting All Migrations...\n";
echo "====================================\n";

// Disable foreign key checks to prevent lock/constraint errors during table creation
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

$scripts = [
    'migrate_tenants.php',
    'migrate_core_hr.php',
    'migrate_ats_queries.php',
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
    'setup_db.php',
    'iam_seed.php',
    'setup_platform_tickets.php',
    'upgrade_cache_learning.php'
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

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "All migrations finished!\n";
