<?php
require_once __DIR__ . '/bootstrap/app.php';

try {
    // 1. Compensation Bands
    $pdo->exec("CREATE TABLE IF NOT EXISTS `compensation_bands` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `job_title` VARCHAR(150) NOT NULL,
        `min_salary` DECIMAL(12,2) NOT NULL,
        `mid_salary` DECIMAL(12,2) NOT NULL,
        `max_salary` DECIMAL(12,2) NOT NULL,
        `currency` VARCHAR(10) DEFAULT 'PHP',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Employee Equity (Stock Options)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `employee_equity` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `employee_name` VARCHAR(255) NOT NULL,
        `grant_type` ENUM('ESOP', 'RSU', 'Phantom') DEFAULT 'ESOP',
        `total_shares` INT NOT NULL,
        `vested_shares` INT DEFAULT 0,
        `vesting_schedule` VARCHAR(100) DEFAULT '4-Year (1-Year Cliff)',
        `grant_date` DATE NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Compensation & Equity tables created successfully.\n";

    // --- SEED DATA ---
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `compensation_bands`")->fetchColumn();
    if ($count === 0) {
        // Seed Salary Bands
        $bands = [
            ['Junior Developer', 30000, 45000, 60000, 'PHP'],
            ['Senior Developer', 80000, 120000, 160000, 'PHP'],
            ['Product Manager', 90000, 130000, 180000, 'PHP'],
            ['Customer Success', 25000, 35000, 50000, 'PHP']
        ];
        $stmtBands = $pdo->prepare("INSERT INTO `compensation_bands` (`job_title`, `min_salary`, `mid_salary`, `max_salary`, `currency`) VALUES (?, ?, ?, ?, ?)");
        foreach ($bands as $b) {
            $stmtBands->execute($b);
        }

        // Seed Employee Equity
        $equity = [
            ['Alex Chen', 'ESOP', 10000, 2500, '4-Year (1-Year Cliff)', date('Y-m-d', strtotime('-1 year'))],
            ['Sarah Connor', 'RSU', 5000, 0, '4-Year (1-Year Cliff)', date('Y-m-d', strtotime('-3 months'))],
            ['John Smith', 'Phantom', 2000, 2000, 'Immediate', date('Y-m-d', strtotime('-2 years'))]
        ];
        $stmtEq = $pdo->prepare("INSERT INTO `employee_equity` (`employee_name`, `grant_type`, `total_shares`, `vested_shares`, `vesting_schedule`, `grant_date`) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($equity as $e) {
            $stmtEq->execute($e);
        }
        
        echo "Seeded Compensation Bands and Equity tracking.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
