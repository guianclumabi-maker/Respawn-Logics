<?php
require_once __DIR__ . '/../../bootstrap/app.php';

try {
    global $pdo;

    $pdo->beginTransaction();

    echo "Creating tenant_payroll_settings table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tenant_payroll_settings` (
            `tenant_id` INT NOT NULL,
            `default_pay_frequency` ENUM('Monthly', 'Semi-Monthly', 'Weekly', 'Daily') DEFAULT 'Semi-Monthly',
            `proration_method` ENUM('split_even', 'full_first_cutoff', 'full_second_cutoff') DEFAULT 'split_even',
            `default_pay_basis` ENUM('monthly', 'daily', 'hourly') DEFAULT 'monthly',
            `tax_annualization` TINYINT(1) DEFAULT 0,
            `mwe_auto_exempt` TINYINT(1) DEFAULT 1,
            `rounding_mode` ENUM('half_up', 'half_even') DEFAULT 'half_up',
            `approval_levels` INT DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`tenant_id`),
            FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo "Creating pay_components table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `pay_components` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tenant_id` INT NOT NULL,
            `code` VARCHAR(50) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `kind` ENUM('earning', 'deduction') NOT NULL,
            `calc_type` ENUM('fixed', 'percent_of_base', 'statutory', 'loan_amortization', 'attendance_derived', 'formula') NOT NULL,
            `value` DECIMAL(12,2) NULL,
            `taxable` TINYINT(1) DEFAULT 1,
            `statutory_key` VARCHAR(50) NULL,
            `formula` TEXT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `sort_order` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_tenant_code` (`tenant_id`, `code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo "Seeding default tenant_payroll_settings for existing tenants...\n";
    $stmt = $pdo->query("SELECT id FROM tenants");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO `tenant_payroll_settings` 
        (`tenant_id`, `default_pay_frequency`, `proration_method`, `default_pay_basis`, `mwe_auto_exempt`) 
        VALUES (?, 'Semi-Monthly', 'split_even', 'monthly', 1)
    ");

    foreach ($tenants as $tenant) {
        $insertStmt->execute([$tenant['id']]);
    }

    $pdo->commit();
    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
}
