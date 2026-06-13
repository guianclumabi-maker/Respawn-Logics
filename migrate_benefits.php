<?php
require_once __DIR__ . '/bootstrap/app.php';

try {
    echo "Starting Benefits & Statutory schema migration...\n";

    // 1. benefit_plans
    $pdo->exec("CREATE TABLE IF NOT EXISTS `benefit_plans` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `provider` VARCHAR(150),
        `description` TEXT,
        `employee_cost` DECIMAL(10,2) DEFAULT 0.00, -- Cost per dependent usually
        `company_cost` DECIMAL(10,2) DEFAULT 0.00, -- Cost for principal
        `type` VARCHAR(50) NOT NULL DEFAULT 'HMO', -- HMO, De Minimis, Allowance
        `status` VARCHAR(50) NOT NULL DEFAULT 'Active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created benefit_plans table.\n";

    // Seed default plans
    $planCheck = $pdo->query("SELECT COUNT(*) FROM `benefit_plans`")->fetchColumn();
    if ($planCheck == 0) {
        $pdo->exec("INSERT INTO `benefit_plans` (`name`, `provider`, `description`, `employee_cost`, `company_cost`, `type`) VALUES 
            ('Maxicare Gold HMO', 'Maxicare', 'Principal covered 100%. Dependents cost 1500/mo.', 1500.00, 2000.00, 'HMO'),
            ('Rice Subsidy', 'Internal', 'Non-taxable De Minimis allowance.', 0.00, 2000.00, 'De Minimis')
        ");
        echo "- Seeded default benefit plans.\n";
    }

    // 2. employee_benefits
    $pdo->exec("CREATE TABLE IF NOT EXISTS `employee_benefits` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_id` INT NOT NULL,
        `plan_id` INT NOT NULL,
        `dependent_count` INT DEFAULT 0,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Enrolled',
        `enrollment_date` DATE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_emp_plan` (`employee_id`, `plan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created employee_benefits table.\n";

    // 3. employee_statutory
    $pdo->exec("CREATE TABLE IF NOT EXISTS `employee_statutory` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `employee_id` INT NOT NULL,
        `sss_number` VARCHAR(50) DEFAULT NULL,
        `philhealth_number` VARCHAR(50) DEFAULT NULL,
        `pagibig_number` VARCHAR(50) DEFAULT NULL,
        `tin_number` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_emp_stat` (`employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created employee_statutory table.\n";

    echo "Benefits Migration completed successfully!\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
