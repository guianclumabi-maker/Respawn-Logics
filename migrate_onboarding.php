<?php
require_once __DIR__ . '/bootstrap/app.php';

try {
    // 1. Workflow Templates
    $pdo->exec("CREATE TABLE IF NOT EXISTS `workflow_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `type` ENUM('Onboarding', 'Offboarding', 'Promotion') DEFAULT 'Onboarding',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Workflow Tasks (Template Items)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `workflow_tasks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `template_id` INT NOT NULL,
        `task_name` VARCHAR(255) NOT NULL,
        `department_owner` VARCHAR(100) DEFAULT 'HR',
        `days_offset` INT DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Employee Workflows (Assigned Instances)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `employee_workflows` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_name` VARCHAR(255) NOT NULL,
        `employee_role` VARCHAR(150) NOT NULL,
        `template_id` INT NOT NULL,
        `status` ENUM('In Progress', 'Completed', 'Stalled') DEFAULT 'In Progress',
        `completion_percentage` INT DEFAULT 0,
        `start_date` DATE NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Employee Workflow Tasks (Specific Task Progress)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `employee_workflow_tasks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `workflow_id` INT NOT NULL,
        `task_name` VARCHAR(255) NOT NULL,
        `department_owner` VARCHAR(100) DEFAULT 'HR',
        `is_completed` TINYINT(1) DEFAULT 0,
        `completed_at` DATETIME NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Onboarding & Offboarding tables created successfully.\n";

    // --- SEED DATA ---
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `workflow_templates`")->fetchColumn();
    if ($count === 0) {
        // Seed Template
        $pdo->exec("INSERT INTO `workflow_templates` (`name`, `type`) VALUES ('Standard Dev Onboarding', 'Onboarding')");
        $templateId = $pdo->lastInsertId();

        // Seed Tasks
        $tasks = [
            ['Issue Company Laptop (MacBook Pro)', 'IT'],
            ['Create Google Workspace Account', 'IT'],
            ['Sign NDA & Employment Contract', 'HR'],
            ['Complete Security Compliance Training', 'Compliance'],
            ['Schedule 1-on-1 with Department Head', 'Management']
        ];
        $stmtTask = $pdo->prepare("INSERT INTO `workflow_tasks` (`template_id`, `task_name`, `department_owner`) VALUES (?, ?, ?)");
        foreach ($tasks as $t) {
            $stmtTask->execute([$templateId, $t[0], $t[1]]);
        }

        // Seed Employee Workflows
        $workflows = [
            ['Alex Chen', 'Senior React Developer', 'In Progress', 40, date('Y-m-d')],
            ['Sarah Connor', 'QA Engineer', 'In Progress', 80, date('Y-m-d', strtotime('-3 days'))],
            ['John Smith', 'Backend Engineer', 'Completed', 100, date('Y-m-d', strtotime('-14 days'))]
        ];
        $stmtWf = $pdo->prepare("INSERT INTO `employee_workflows` (`employee_name`, `employee_role`, `template_id`, `status`, `completion_percentage`, `start_date`) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($workflows as $w) {
            $stmtWf->execute([$w[0], $w[1], $templateId, $w[2], $w[3], $w[4]]);
            $workflowId = $pdo->lastInsertId();

            // Assign tasks based on completion percentage
            $numCompleted = ($w[3] / 100) * count($tasks);
            $stmtEmpTask = $pdo->prepare("INSERT INTO `employee_workflow_tasks` (`workflow_id`, `task_name`, `department_owner`, `is_completed`, `completed_at`) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($tasks as $index => $t) {
                $isCompleted = ($index < $numCompleted) ? 1 : 0;
                $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;
                $stmtEmpTask->execute([$workflowId, $t[0], $t[1], $isCompleted, $completedAt]);
            }
        }
        
        echo "Seeded Onboarding templates and employee workflows.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
