<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../../bootstrap/app.php';

echo "Enforcing tenant_id DB-level constraints...\n";

try {
    // Exclude the tenants table itself, and potentially any global tables (e.g., if there are global settings tables)
    // We dynamically find all tables with a tenant_id column.
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
    
    $tablesWithTenantId = [];
    foreach ($tables as $table) {
        if ($table === 'tenants') continue; // The tenants table is the parent
        
        $hasTenant = $pdo->query("
            SELECT COUNT(*) FROM information_schema.columns 
            WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'tenant_id'
        ")->fetchColumn();
        
        if ($hasTenant) {
            $tablesWithTenantId[] = $table;
        }
    }

    foreach ($tablesWithTenantId as $table) {
        // 1. Enforce NOT NULL on tenant_id
        $nullable = $pdo->query("
            SELECT IS_NULLABLE FROM information_schema.columns 
            WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'tenant_id'
        ")->fetchColumn();
        
        if ($nullable === 'YES') {
            // Check current column type
            $colType = $pdo->query("
                SELECT COLUMN_TYPE FROM information_schema.columns 
                WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'tenant_id'
            ")->fetchColumn();
            
            echo "Altering $table to make tenant_id NOT NULL...\n";
            $pdo->exec("ALTER TABLE `$table` MODIFY `tenant_id` $colType NOT NULL");
        }

        // 2. Ensure an index exists on tenant_id
        $indexes = $pdo->query("SHOW INDEXES FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $hasIndex = false;
        foreach ($indexes as $idx) {
            if ($idx['Column_name'] === 'tenant_id') {
                $hasIndex = true;
                break;
            }
        }
        
        if (!$hasIndex) {
            echo "Adding index on tenant_id to $table...\n";
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `idx_{$table}_tenant_id` (`tenant_id`)");
        }
    }
    
    echo "Tenant DB constraints enforced successfully.\n";

} catch (PDOException $e) {
    echo "Error enforcing tenant constraints: " . $e->getMessage() . "\n";
}
