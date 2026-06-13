<?php
require_once __DIR__ . '/bootstrap/app.php';

try {
    echo "Starting Performance & Talent Management schema migration...\n";

    // 1. performance_cycles
    $pdo->exec("CREATE TABLE IF NOT EXISTS `performance_cycles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'Draft', -- Draft, Active, Calibrating, Closed
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created performance_cycles table.\n";

    // 2. performance_reviews
    $pdo->exec("CREATE TABLE IF NOT EXISTS `performance_reviews` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `cycle_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `reviewer_id` INT NOT NULL, -- The Manager
        `status` VARCHAR(50) NOT NULL DEFAULT 'Pending Self-Evaluation', -- Pending Self-Evaluation, Pending Manager, Calibrating, Finalized
        `self_comments` TEXT,
        `manager_comments` TEXT,
        `overall_score_1_to_5` DECIMAL(3,1) DEFAULT NULL,
        `nine_box_performance` INT DEFAULT NULL, -- 1=Low, 2=Moderate, 3=High
        `nine_box_potential` INT DEFAULT NULL, -- 1=Low, 2=Moderate, 3=High
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_review` (`cycle_id`, `employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created performance_reviews table.\n";

    // 3. performance_goals
    $pdo->exec("CREATE TABLE IF NOT EXISTS `performance_goals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_id` INT NOT NULL,
        `cycle_id` INT DEFAULT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `weight` INT DEFAULT 0, -- percentage
        `completion_percentage` INT DEFAULT 0,
        `status` VARCHAR(50) DEFAULT 'On Track', -- On Track, At Risk, Completed, Cancelled
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created performance_goals table.\n";

    // 4. performance_competencies (Global traits)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `performance_competencies` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(150) NOT NULL,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created performance_competencies table.\n";

    // Insert some default competencies if empty
    $compCheck = $pdo->query("SELECT COUNT(*) FROM `performance_competencies`")->fetchColumn();
    if ($compCheck == 0) {
        $pdo->exec("INSERT INTO `performance_competencies` (`name`, `description`) VALUES 
            ('Communication', 'Expresses ideas clearly and respectfully.'),
            ('Problem Solving', 'Identifies issues and implements effective solutions.'),
            ('Leadership', 'Takes initiative and inspires others.'),
            ('Technical Execution', 'Delivers high quality work within deadlines.')
        ");
        echo "- Seeded default competencies.\n";
    }

    // 5. review_competency_scores
    $pdo->exec("CREATE TABLE IF NOT EXISTS `review_competency_scores` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `review_id` INT NOT NULL,
        `competency_id` INT NOT NULL,
        `score` INT NOT NULL, -- 1 to 5
        `comments` TEXT,
        UNIQUE KEY `unique_comp_score` (`review_id`, `competency_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created review_competency_scores table.\n";

    // 6. performance_pips
    $pdo->exec("CREATE TABLE IF NOT EXISTS `performance_pips` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_id` INT NOT NULL,
        `manager_id` INT NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `reason` TEXT NOT NULL,
        `expected_outcomes` TEXT NOT NULL,
        `status` VARCHAR(50) DEFAULT 'Active', -- Active, Passed, Failed
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created performance_pips table.\n";

    echo "Performance Migration completed successfully!\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
