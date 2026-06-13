<?php
require_once __DIR__ . '/bootstrap/app.php';

try {
    echo "Starting Enterprise Payroll schema migration...\n";

    // 1. Alter users table to include payroll_schedule_id
    $alterUsers = "ALTER TABLE `users` ADD COLUMN `payroll_schedule_id` INT DEFAULT NULL AFTER `base_salary`;";
    $colCheck = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'payroll_schedule_id'");
    if ($colCheck->rowCount() == 0) {
        $pdo->exec($alterUsers);
        echo "- Added payroll_schedule_id to users.\n";
    }

    // 2. payroll_schedules
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payroll_schedules` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `frequency` VARCHAR(50) NOT NULL, -- Weekly, Bi-Weekly, Semi-Monthly, Monthly
        `config_json` TEXT, -- Stores anchor dates, cutoff rules, etc.
        `holiday_rule` VARCHAR(50) DEFAULT 'Previous Business Day',
        `weekend_rule` VARCHAR(50) DEFAULT 'Previous Business Day',
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created payroll_schedules table.\n";

    // 3. employee_compensation (Salary history)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `employee_compensation` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_id` INT NOT NULL,
        `effective_date` DATE NOT NULL,
        `salary_type` VARCHAR(50) NOT NULL DEFAULT 'Salary', -- Salary, Hourly, Daily
        `base_salary` DECIMAL(12,2) NOT NULL,
        `currency` VARCHAR(10) DEFAULT 'USD',
        `pay_frequency` VARCHAR(50) DEFAULT 'Monthly',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_emp_comp` (`employee_id`, `effective_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created employee_compensation table.\n";

    // 4. payroll_runs
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payroll_runs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `payroll_schedule_id` INT NOT NULL,
        `payroll_period_start` DATE NOT NULL,
        `payroll_period_end` DATE NOT NULL,
        `pay_date` DATE NOT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Draft', -- Draft, Review, Approved, Processed, Locked, Cancelled
        `created_by` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created payroll_runs table.\n";

    // 5. payroll_run_employees
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payroll_run_employees` (
        `payroll_run_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `gross_pay` DECIMAL(12,2) DEFAULT 0.00,
        `total_deductions` DECIMAL(12,2) DEFAULT 0.00,
        `net_pay` DECIMAL(12,2) DEFAULT 0.00,
        PRIMARY KEY (`payroll_run_id`, `employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created payroll_run_employees table.\n";

    // 6. payroll_earnings
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payroll_earnings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `payroll_run_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `earning_type` VARCHAR(100) NOT NULL,
        `amount` DECIMAL(12,2) NOT NULL,
        `notes` TEXT,
        INDEX `idx_earn_run_emp` (`payroll_run_id`, `employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created payroll_earnings table.\n";

    // 7. payroll_deductions
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payroll_deductions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `payroll_run_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `deduction_type` VARCHAR(100) NOT NULL,
        `amount` DECIMAL(12,2) NOT NULL,
        `notes` TEXT,
        INDEX `idx_ded_run_emp` (`payroll_run_id`, `employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created payroll_deductions table.\n";

    // 8. payroll_payslips
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payroll_payslips` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `payroll_run_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `pdf_path` VARCHAR(255) NOT NULL,
        `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_payslip` (`payroll_run_id`, `employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created payroll_payslips table.\n";

    echo "Payroll Migration completed successfully!\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
