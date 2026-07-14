<?php
require_once __DIR__ . '/bootstrap/app.php';

$tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchAll(PDO::FETCH_COLUMN);

echo "Tables missing tenant_id:\n";
foreach ($tables as $table) {
    $hasTenant = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'tenant_id'")->fetchColumn();
    if (!$hasTenant) {
        echo "- $table\n";
    }
}

echo "\nTables with nullable tenant_id:\n";
foreach ($tables as $table) {
    $nullable = $pdo->query("SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'tenant_id'")->fetchColumn();
    if ($nullable === 'YES') {
        echo "- $table\n";
    }
}

echo "\nChecking composite indexes containing tenant_id:\n";
foreach ($tables as $table) {
    $hasTenant = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = 'tenant_id'")->fetchColumn();
    if ($hasTenant) {
        $indexes = $pdo->query("SHOW INDEXES FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $hasIndex = false;
        foreach ($indexes as $idx) {
            if ($idx['Column_name'] === 'tenant_id') {
                $hasIndex = true;
                break;
            }
        }
        if (!$hasIndex) {
            echo "- $table is missing index on tenant_id\n";
        }
    }
}
