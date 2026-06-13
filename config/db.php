<?php
// Database configurations for Employee System
global $config;
$dbConfig = $config['database'] ?? [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'employee_system',
    'user' => 'root',
    'pass' => ''
];

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['name']
    );
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Attempt local database creation if it's missing
    if ($e->getCode() == 1049 && $dbConfig['host'] === 'localhost') {
        try {
            $dsnNoDb = sprintf(
                'mysql:host=%s;port=%s;charset=utf8mb4',
                $dbConfig['host'],
                $dbConfig['port']
            );
            $pdo = new PDO($dsnNoDb, $dbConfig['user'], $dbConfig['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbConfig['name']}`");
            
            // Reconnect
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $ex) {
            die("Database connection failed: " . $ex->getMessage());
        }
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}

// 1. Auto-create 'users' table
try {
    $usersSql = "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT NULL,
        `employee_id` VARCHAR(50) DEFAULT NULL,
        `employee_number` VARCHAR(50) DEFAULT NULL,
        `manager_supervisor_id` VARCHAR(50) DEFAULT NULL,
        `first_name` VARCHAR(100) DEFAULT NULL,
        `last_name` VARCHAR(100) DEFAULT NULL,
        `full_name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(150) NOT NULL,
        `work_email` VARCHAR(150) DEFAULT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `role` VARCHAR(50) NOT NULL DEFAULT 'employee',
        `employment_status` VARCHAR(50) DEFAULT 'Active',
        `department` VARCHAR(100) DEFAULT NULL,
        `work_location` VARCHAR(150) DEFAULT NULL,
        `immediate_supervisor` VARCHAR(150) DEFAULT NULL,
        `department_manager` VARCHAR(150) DEFAULT NULL,
        `manager_id` VARCHAR(50) DEFAULT NULL,
        `tier` DECIMAL(3,1) NOT NULL DEFAULT 1.0,
        `job_title` VARCHAR(150) DEFAULT NULL,
        `base_salary` DECIMAL(12,2) DEFAULT NULL,
        `payroll_schedule_id` INT DEFAULT NULL,
        `hire_date` DATE DEFAULT NULL,
        `profile_image` VARCHAR(255) DEFAULT '',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_tenant_employee` (`tenant_id`, `employee_id`),
        UNIQUE KEY `unique_tenant_email` (`tenant_id`, `work_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($usersSql);
} catch (PDOException $e) {
    die("Users table creation failed: " . $e->getMessage());
}

// 2. Auto-create 'audit_logs' table
try {
    $auditSql = "CREATE TABLE IF NOT EXISTS `audit_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `user_email` VARCHAR(150) NOT NULL,
        `action` VARCHAR(100) NOT NULL,
        `details` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($auditSql);
} catch (PDOException $e) {
    die("Audit logs table creation failed: " . $e->getMessage());
}

// 3. Auto-create 'attendance' table
try {
    $attendanceSql = "CREATE TABLE IF NOT EXISTS `attendance` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_email` VARCHAR(150) NOT NULL,
        `time_in` DATETIME NOT NULL,
        `time_out` DATETIME DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT 'On Time',
        `shift_id` INT NULL,
        `manager_approved` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($attendanceSql);
} catch (PDOException $e) {
    die("Attendance table creation failed: " . $e->getMessage());
}

// 3.1. Auto-create 'shifts' table
try {
    $shiftsSql = "CREATE TABLE IF NOT EXISTS `shifts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` INT NULL,
        `name` VARCHAR(100) NOT NULL,
        `start_time` TIME NOT NULL,
        `end_time` TIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($shiftsSql);
} catch (PDOException $e) {
    die("Shifts table creation failed: " . $e->getMessage());
}

// 3.2. Auto-create 'employee_shifts' table
try {
    $employeeShiftsSql = "CREATE TABLE IF NOT EXISTS `employee_shifts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `user_id` INT NOT NULL,
        `shift_id` INT NOT NULL,
        `effective_date` DATE NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `user_shift` (`user_id`, `shift_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($employeeShiftsSql);
} catch (PDOException $e) {
    die("Employee shifts table creation failed: " . $e->getMessage());
}

// 4. Auto-create 'leave_requests' table
try {
    $leavesSql = "CREATE TABLE IF NOT EXISTS `leave_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_email` VARCHAR(150) NOT NULL,
        `leave_type` VARCHAR(100) NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `reason` TEXT DEFAULT NULL,
        `status` VARCHAR(50) DEFAULT 'Pending',
        `tl_decision` VARCHAR(50) DEFAULT 'Pending',
        `tl_decided_by` VARCHAR(150) DEFAULT NULL,
        `tl_decision_date` DATETIME DEFAULT NULL,
        `tl_comments` TEXT DEFAULT NULL,
        `manager_decision` VARCHAR(50) DEFAULT 'Pending',
        `manager_decided_by` VARCHAR(150) DEFAULT NULL,
        `manager_decision_date` DATETIME DEFAULT NULL,
        `manager_comments` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($leavesSql);
} catch (PDOException $e) {
    die("Leave requests table creation failed: " . $e->getMessage());
}

// 5. Auto-create 'employee_tasks' table
try {
    $tasksSql = "CREATE TABLE IF NOT EXISTS `employee_tasks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_email` VARCHAR(150) NOT NULL,
        `task_name` VARCHAR(255) NOT NULL,
        `task_description` TEXT DEFAULT NULL,
        `is_completed` TINYINT NOT NULL DEFAULT 0,
        `completed_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($tasksSql);
} catch (PDOException $e) {
    die("Tasks table creation failed: " . $e->getMessage());
}

// 6. Auto-create 'employee_relations' table
try {
    $relationsSql = "CREATE TABLE IF NOT EXISTS `employee_relations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(255) NOT NULL,
        `stage` VARCHAR(100) NOT NULL DEFAULT 'Reported',
        `applied` DATE NOT NULL,
        `rating` INT NOT NULL DEFAULT 0,
        `tags` VARCHAR(255) DEFAULT '',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($relationsSql);
    
    // Seed if empty
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `employee_relations`")->fetchColumn();
    if ($count === 0) {
        $seeds = [
            ['John Doe - Overtime Dispute', 'Investigation', date('Y-m-d', strtotime('-10 days')), 4, 'Pay,Dispute'],
            ['Jane Smith - Department Transfer Request', 'Reported', date('Y-m-d', strtotime('-2 days')), 2, 'Transfer,Career'],
            ['Bob Johnson - Workplace Policy Inquiry', 'Resolved', date('Y-m-d', strtotime('-15 days')), 1, 'Policy,Inquiry'],
            ['Alice Williams - Performance Feedback Appeal', 'Investigation', date('Y-m-d', strtotime('-5 days')), 3, 'Feedback,Review'],
            ['Charlie Brown - System Access Issues', 'Resolution Pending', date('Y-m-d', strtotime('-1 day')), 3, 'IT,Access']
        ];
        
        $stmtSeed = $pdo->prepare("INSERT INTO `employee_relations` (`name`, `stage`, `applied`, `rating`, `tags`) VALUES (?, ?, ?, ?, ?)");
        foreach ($seeds as $s) {
            $stmtSeed->execute($s);
        }
    }
} catch (PDOException $e) {
    die("Employee relations table creation failed: " . $e->getMessage());
}

// 7. Auto-create 'employment_history' table
try {
    $historySql = "CREATE TABLE IF NOT EXISTS `employment_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `user_id` INT NOT NULL,
        `change_type` VARCHAR(100) NOT NULL,
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
} catch (PDOException $e) {
    die("Employment history table creation failed: " . $e->getMessage());
}

// 8. Auto-create 'employee_documents' table
try {
    $docSql = "CREATE TABLE IF NOT EXISTS `employee_documents` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `user_id` INT NOT NULL,
        `document_type` VARCHAR(100) NOT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `uploaded_by` VARCHAR(150) NOT NULL,
        `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($docSql);
} catch (PDOException $e) {
    die("Employee documents table creation failed: " . $e->getMessage());
}

// 9. Auto-create 'custom_fields' and 'custom_field_values'
try {
    $cfDefSql = "CREATE TABLE IF NOT EXISTS `custom_fields` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `field_name` VARCHAR(100) NOT NULL,
        `field_type` VARCHAR(50) NOT NULL DEFAULT 'text',
        `field_options` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_tenant_field` (`tenant_id`, `field_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($cfDefSql);

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
} catch (PDOException $e) {
    die("Custom fields tables creation failed: " . $e->getMessage());
}

// 10. Auto-create Payroll tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payroll_schedules` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `frequency` VARCHAR(50) NOT NULL,
        `config_json` TEXT,
        `holiday_rule` VARCHAR(50) DEFAULT 'Previous Business Day',
        `weekend_rule` VARCHAR(50) DEFAULT 'Previous Business Day',
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `employee_compensation` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_id` INT NOT NULL,
        `effective_date` DATE NOT NULL,
        `salary_type` VARCHAR(50) NOT NULL DEFAULT 'Salary',
        `base_salary` DECIMAL(12,2) NOT NULL,
        `currency` VARCHAR(10) DEFAULT 'USD',
        `pay_frequency` VARCHAR(50) DEFAULT 'Monthly',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_emp_comp` (`employee_id`, `effective_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `payroll_runs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `payroll_schedule_id` INT NOT NULL,
        `payroll_period_start` DATE NOT NULL,
        `payroll_period_end` DATE NOT NULL,
        `pay_date` DATE NOT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Draft',
        `created_by` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `payroll_run_employees` (
        `payroll_run_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `gross_pay` DECIMAL(12,2) DEFAULT 0.00,
        `total_deductions` DECIMAL(12,2) DEFAULT 0.00,
        `net_pay` DECIMAL(12,2) DEFAULT 0.00,
        PRIMARY KEY (`payroll_run_id`, `employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `payroll_earnings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `payroll_run_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `earning_type` VARCHAR(100) NOT NULL,
        `amount` DECIMAL(12,2) NOT NULL,
        `notes` TEXT,
        INDEX `idx_earn_run_emp` (`payroll_run_id`, `employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `payroll_deductions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `payroll_run_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `deduction_type` VARCHAR(100) NOT NULL,
        `amount` DECIMAL(12,2) NOT NULL,
        `notes` TEXT,
        INDEX `idx_ded_run_emp` (`payroll_run_id`, `employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `payroll_payslips` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `payroll_run_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `pdf_path` VARCHAR(255) NOT NULL,
        `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_payslip` (`payroll_run_id`, `employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

} catch (PDOException $e) {
    die("Payroll tables creation failed: " . $e->getMessage());
}

// 11. Auto-create Performance tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `performance_cycles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Draft',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `performance_reviews` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `cycle_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `reviewer_id` INT NOT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Pending Self-Evaluation',
        `self_comments` TEXT,
        `manager_comments` TEXT,
        `overall_score_1_to_5` DECIMAL(3,1) DEFAULT NULL,
        `nine_box_performance` INT DEFAULT NULL,
        `nine_box_potential` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_review` (`cycle_id`, `employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `performance_goals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_id` INT NOT NULL,
        `cycle_id` INT DEFAULT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `weight` INT DEFAULT 0,
        `completion_percentage` INT DEFAULT 0,
        `status` VARCHAR(50) DEFAULT 'On Track',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `performance_competencies` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `review_competency_scores` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `review_id` INT NOT NULL,
        `competency_id` INT NOT NULL,
        `score` INT NOT NULL,
        `comments` TEXT,
        UNIQUE KEY `unique_comp_score` (`review_id`, `competency_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `performance_pips` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_id` INT NOT NULL,
        `manager_id` INT NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `reason` TEXT NOT NULL,
        `expected_outcomes` TEXT NOT NULL,
        `status` VARCHAR(50) DEFAULT 'Active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

} catch (PDOException $e) {
    die("Performance tables creation failed: " . $e->getMessage());
}

// 12. Auto-create Expense tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `expense_categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `description` TEXT,
        `requires_receipt` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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
        `status` VARCHAR(50) NOT NULL DEFAULT 'Pending Manager',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `expense_approvals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `claim_id` INT NOT NULL,
        `approver_id` INT NOT NULL,
        `action` VARCHAR(50) NOT NULL,
        `comments` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

} catch (PDOException $e) {
    die("Expense tables creation failed: " . $e->getMessage());
}

// 13. Auto-create Benefits & Statutory tables (Phase 5 - PH Edition)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `benefit_plans` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `provider` VARCHAR(150),
        `description` TEXT,
        `employee_cost` DECIMAL(10,2) DEFAULT 0.00,
        `company_cost` DECIMAL(10,2) DEFAULT 0.00,
        `type` VARCHAR(50) NOT NULL DEFAULT 'HMO',
        `status` VARCHAR(50) NOT NULL DEFAULT 'Active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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

} catch (PDOException $e) {
    die("Benefits tables creation failed: " . $e->getMessage());
}

// 14. Auto-create ESM (Employee Service Management) tables (Phase 6)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `service_teams` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `service_ticket_types` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `default_team_id` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `service_tickets` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `ticket_number` VARCHAR(50) NOT NULL UNIQUE,
        `ticket_type_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `assigned_team_id` INT DEFAULT NULL,
        `assigned_to_user_id` INT DEFAULT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Open',
        `priority` VARCHAR(50) NOT NULL DEFAULT 'Medium',
        `sla_due_at` DATETIME DEFAULT NULL,
        `first_response_at` DATETIME DEFAULT NULL,
        `resolved_at` DATETIME DEFAULT NULL,
        `created_by` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `ticket_comments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `ticket_id` INT NOT NULL,
        `user_id` INT DEFAULT NULL,
        `comment_type` VARCHAR(20) NOT NULL DEFAULT 'Public',
        `comment` TEXT NOT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    die("ESM tables creation failed: " . $e->getMessage());
}
