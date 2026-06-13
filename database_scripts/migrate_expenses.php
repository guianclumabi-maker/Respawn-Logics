<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

try {
    echo "Starting Expense & Claims Management schema migration...\n";

    // 1. expense_categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS `expense_categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `description` TEXT,
        `requires_receipt` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created expense_categories table.\n";

    // Insert default categories
    $catCheck = $pdo->query("SELECT COUNT(*) FROM `expense_categories`")->fetchColumn();
    if ($catCheck == 0) {
        $pdo->exec("INSERT INTO `expense_categories` (`name`, `description`, `requires_receipt`) VALUES 
            ('Travel & Flights', 'Airfare, train tickets, and travel expenses.', 1),
            ('Accommodation', 'Hotels and lodging.', 1),
            ('Meals & Entertainment', 'Client dinners and business meals.', 1),
            ('Office Supplies', 'Desk equipment, stationery.', 1),
            ('Software Subscriptions', 'SaaS tools paid via personal card.', 1),
            ('Mileage', 'Personal vehicle usage for business.', 0)
        ");
        echo "- Seeded default expense categories.\n";
    }

    // 2. expense_claims
    $pdo->exec("CREATE TABLE IF NOT EXISTS `expense_claims` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_id` INT NOT NULL,
        `category_id` INT NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `currency` VARCHAR(10) DEFAULT 'USD',
        `expense_date` DATE NOT NULL,
        `description` TEXT,
        `receipt_path` VARCHAR(255) DEFAULT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Pending Manager', -- Pending Manager, Pending Finance, Rejected, Reimbursed
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created expense_claims table.\n";

    // 3. expense_approvals (Audit Log)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `expense_approvals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `claim_id` INT NOT NULL,
        `approver_id` INT NOT NULL,
        `action` VARCHAR(50) NOT NULL, -- Approved by Manager, Approved by Finance, Rejected
        `comments` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created expense_approvals table.\n";

    echo "Expense Migration completed successfully!\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
