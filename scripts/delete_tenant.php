<?php
require __DIR__ . '/../bootstrap/app.php';

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the CLI.\n");
}

if ($argc < 2) {
    die("Usage: php delete_tenant.php <tenant_id>\n");
}

$tenantId = $argv[1];

// Validate tenant exists
$stmt = $pdo->prepare("SELECT id, company_name FROM tenants WHERE id = ?");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    die("Error: Tenant '$tenantId' not found.\n");
}

echo "Starting deletion for Tenant: {$tenant['company_name']} (ID: $tenantId)\n\n";

$summary = [];

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->beginTransaction();

    // 1. Identify all tables with 'tenant_id'
    $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME = 'tenant_id' AND TABLE_SCHEMA = DATABASE()");
    $tenantTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Identify tables that don't have tenant_id but have 'user_id'
    $stmt = $pdo->query("
        SELECT TABLE_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE COLUMN_NAME = 'user_id' 
          AND TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME NOT IN (
              SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME = 'tenant_id' AND TABLE_SCHEMA = DATABASE()
          )
    ");
    $userDependentTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 3. Identify tables with 'role_id' but no tenant_id
    $stmt = $pdo->query("
        SELECT TABLE_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE COLUMN_NAME = 'role_id' 
          AND TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME NOT IN (
              SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME = 'tenant_id' AND TABLE_SCHEMA = DATABASE()
          )
    ");
    $roleDependentTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Also survey_id, expense_claim_id, ticket_id etc.
    // For safety, we'll manually specify known dependent tables to avoid accidentally deleting global things
    $manualDependencies = [
        'role_permissions' => 'role_id IN (SELECT id FROM roles WHERE tenant_id = ?)',
        'user_roles' => 'user_id IN (SELECT id FROM users WHERE tenant_id = ?)',
        'user_activation_tokens' => 'user_id IN (SELECT id FROM users WHERE tenant_id = ?)',
        'totp_secrets' => 'user_id IN (SELECT id FROM users WHERE tenant_id = ?)',
        'employee_statutory' => 'user_id IN (SELECT id FROM users WHERE tenant_id = ?)',
        'payroll_run_employees' => 'user_id IN (SELECT id FROM users WHERE tenant_id = ?)',
        'payroll_earnings' => 'run_employee_id IN (SELECT id FROM payroll_run_employees WHERE user_id IN (SELECT id FROM users WHERE tenant_id = ?))',
        'payroll_deductions' => 'run_employee_id IN (SELECT id FROM payroll_run_employees WHERE user_id IN (SELECT id FROM users WHERE tenant_id = ?))',
        'employee_workflow_tasks' => 'user_id IN (SELECT id FROM users WHERE tenant_id = ?)',
        'expense_approvals' => 'claim_id IN (SELECT id FROM expense_claims WHERE tenant_id = ?)',
        'review_competency_scores' => 'review_id IN (SELECT id FROM performance_reviews WHERE tenant_id = ?)',
        'survey_questions' => 'survey_id IN (SELECT id FROM surveys WHERE tenant_id = ?)',
        'survey_responses' => 'survey_id IN (SELECT id FROM surveys WHERE tenant_id = ?)',
        'ticket_comments' => 'user_id IN (SELECT id FROM users WHERE tenant_id = ?)',
        'platform_ticket_comments' => 'user_id IN (SELECT id FROM users WHERE tenant_id = ?)',
        'esm_ticket_comments' => 'user_id IN (SELECT id FROM users WHERE tenant_id = ?)'
    ];

    // Delete manual dependencies
    foreach ($manualDependencies as $table => $whereClause) {
        // check if table exists
        $check = $pdo->prepare("SHOW TABLES LIKE ?");
        $check->execute([$table]);
        if ($check->fetch()) {
            $sql = "DELETE FROM `$table` WHERE $whereClause";
            $delStmt = $pdo->prepare($sql);
            $delStmt->execute([$tenantId]);
            $count = $delStmt->rowCount();
            if ($count > 0) {
                $summary[$table] = ($summary[$table] ?? 0) + $count;
            }
        }
    }

    // Delete dynamic user dependent tables not already covered
    foreach ($userDependentTables as $table) {
        if (!isset($manualDependencies[$table])) {
            $sql = "DELETE FROM `$table` WHERE user_id IN (SELECT id FROM users WHERE tenant_id = ?)";
            $delStmt = $pdo->prepare($sql);
            $delStmt->execute([$tenantId]);
            $count = $delStmt->rowCount();
            if ($count > 0) {
                $summary[$table] = ($summary[$table] ?? 0) + $count;
            }
        }
    }

    // Delete dynamic role dependent tables not already covered
    foreach ($roleDependentTables as $table) {
        if (!isset($manualDependencies[$table]) && $table !== 'role_templates' && $table !== 'role_template_permissions') {
            $sql = "DELETE FROM `$table` WHERE role_id IN (SELECT id FROM roles WHERE tenant_id = ?)";
            $delStmt = $pdo->prepare($sql);
            $delStmt->execute([$tenantId]);
            $count = $delStmt->rowCount();
            if ($count > 0) {
                $summary[$table] = ($summary[$table] ?? 0) + $count;
            }
        }
    }

    // Finally, delete all tenant_id tables EXCEPT tenants itself
    // We must do 'users', 'roles', 'org_units' etc first? Actually, with FK_CHECKS=0 order doesn't strictly matter for the DB,
    // but it's good to do users last in case of any triggers or just logical flow. Wait, FK_CHECKS=0 makes order irrelevant.
    
    foreach ($tenantTables as $table) {
        if ($table === 'tenants') continue;

        $sql = "DELETE FROM `$table` WHERE tenant_id = ?";
        $delStmt = $pdo->prepare($sql);
        $delStmt->execute([$tenantId]);
        $count = $delStmt->rowCount();
        if ($count > 0) {
            $summary[$table] = ($summary[$table] ?? 0) + $count;
        }
    }

    // Finally delete from tenants
    $stmt = $pdo->prepare("DELETE FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
    $summary['tenants'] = $stmt->rowCount();

    $pdo->commit();
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "Deletion successful!\n\nSummary of deleted rows:\n";
    foreach ($summary as $table => $count) {
        printf("- %-30s : %d rows\n", $table, $count);
    }
    if (empty($summary)) {
        echo "No rows were deleted.\n";
    }

} catch (Exception $e) {
    $pdo->rollBack();
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    die("Error during deletion: " . $e->getMessage() . "\n");
}
