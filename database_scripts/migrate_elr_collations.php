<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

try {
    echo "Starting ELR collation mismatch migration...\n";

    $tables = [
        'elr_case_types',
        'elr_cases',
        'elr_case_timeline',
        'elr_document_templates',
        'elr_pipelines',
        'elr_pipeline_stages',
        'elr_case_cards',
        'elr_generated_documents',
        'elr_stage_transitions',
        'elr_awol_config',
        'elr_auto_rules',
        'labor_references',
        'elr_precedents',
        'elr_hearings',
        'elr_approvals'
    ];

    // Disable foreign key checks to allow altering columns involved in foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    foreach ($tables as $table) {
        try {
            $pdo->exec("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "- Converted table `{$table}` to utf8mb4_unicode_ci.\n";
        } catch (PDOException $e) {
            echo "- Table `{$table}` could not be converted (may not exist yet): " . $e->getMessage() . "\n";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "ELR collation migration completed successfully!\n";

} catch (Exception $e) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    throw $e;
}
