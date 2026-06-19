<?php
if (!defined('MIGRATION_SAFE')) define('MIGRATION_SAFE', true);
require_once __DIR__ . '/../bootstrap/app.php';

try {
    echo "Starting Core HR additional columns migration...\n";

    $alterUsers = "
        ALTER TABLE `users`
        ADD COLUMN `employee_id` VARCHAR(100) DEFAULT NULL,
        ADD COLUMN `employee_number` VARCHAR(100) DEFAULT NULL,
        ADD COLUMN `work_email` VARCHAR(255) DEFAULT NULL,
        ADD COLUMN `manager_id` INT DEFAULT NULL,
        ADD COLUMN `hire_date` DATE DEFAULT NULL,
        ADD COLUMN `phone` VARCHAR(50) DEFAULT NULL,
        ADD COLUMN `emergency_name` VARCHAR(150) DEFAULT NULL,
        ADD COLUMN `emergency_phone` VARCHAR(50) DEFAULT NULL;
    ";
    
    $colCheck = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'employee_id'");
    if ($colCheck->rowCount() == 0) {
        $pdo->exec($alterUsers);
        echo "- Added employee_id, employee_number, work_email, manager_id, hire_date, phone, emergency_name, emergency_phone to users.\n";
    } else {
        echo "- users table already has the new columns.\n";
    }

    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
