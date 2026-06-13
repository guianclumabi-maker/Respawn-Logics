<?php
require_once __DIR__ . '/bootstrap/app.php';

try {
    echo "Starting Core HR schema migration...\n";

    // 1. Alter users table
    $alterUsers = "
        ALTER TABLE `users`
        ADD COLUMN `employment_status` VARCHAR(50) DEFAULT 'Active' AFTER `role`,
        ADD COLUMN `work_location` VARCHAR(150) DEFAULT NULL AFTER `department`,
        ADD COLUMN `base_salary` DECIMAL(12,2) DEFAULT NULL AFTER `job_title`;
    ";
    
    // Check if column exists first to avoid errors
    $colCheck = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'employment_status'");
    if ($colCheck->rowCount() == 0) {
        $pdo->exec($alterUsers);
        echo "- Added employment_status, work_location, base_salary to users.\n";
    } else {
        echo "- users table already has the new columns.\n";
    }

    // 2. Create employment_history
    $historySql = "CREATE TABLE IF NOT EXISTS `employment_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `user_id` INT NOT NULL,
        `change_type` VARCHAR(100) NOT NULL, -- e.g., 'Promotion', 'Salary Review', 'Manager Change'
        `job_title` VARCHAR(150) DEFAULT NULL,
        `department` VARCHAR(100) DEFAULT NULL,
        `manager_id` VARCHAR(50) DEFAULT NULL,
        `base_salary` DECIMAL(12,2) DEFAULT NULL,
        `effective_date` DATE NOT NULL,
        `notes` TEXT,
        `recorded_by` VARCHAR(150) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_history` (`user_id`, `effective_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($historySql);
    echo "- Created employment_history table.\n";

    // 3. Create employee_documents
    $docSql = "CREATE TABLE IF NOT EXISTS `employee_documents` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `user_id` INT NOT NULL,
        `document_type` VARCHAR(100) NOT NULL, -- e.g., 'Contract', 'ID', 'Certification'
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `uploaded_by` VARCHAR(150) NOT NULL,
        `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($docSql);
    echo "- Created employee_documents table.\n";

    // 4. Create custom fields definitions
    $cfDefSql = "CREATE TABLE IF NOT EXISTS `custom_fields` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `field_name` VARCHAR(100) NOT NULL,
        `field_type` VARCHAR(50) NOT NULL DEFAULT 'text', -- text, date, select, number
        `field_options` TEXT NULL, -- comma separated if select
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_tenant_field` (`tenant_id`, `field_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($cfDefSql);
    echo "- Created custom_fields definition table.\n";

    // 5. Create custom field values
    $cfValSql = "CREATE TABLE IF NOT EXISTS `custom_field_values` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `user_id` INT NOT NULL,
        `field_id` INT NOT NULL,
        `field_value` TEXT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user_field` (`user_id`, `field_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($cfValSql);
    echo "- Created custom_field_values table.\n";

    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
