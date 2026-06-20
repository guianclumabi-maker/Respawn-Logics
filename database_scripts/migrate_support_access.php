<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN support_access_expires_at DATETIME NULL");
    echo "Successfully added support_access_expires_at to tenants table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column support_access_expires_at already exists.\n";
    } else {
        die("Error migrating tenants: " . $e->getMessage() . "\n");
    }
}
