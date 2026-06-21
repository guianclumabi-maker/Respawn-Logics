<?php
$env = parse_ini_file(__DIR__ . '/../../.env');
$host = $env['DB_HOST'] ?? 'localhost';
$db   = $env['DB_NAME'] ?? 'employee_system';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';

// Check if Railway URL string is provided by the environment
if (isset($_SERVER['DATABASE_URL'])) {
    $dbOpts = parse_url($_SERVER['DATABASE_URL']);
    $host = $dbOpts['host'];
    $port = $dbOpts['port'] ?? 3306;
    $user = $dbOpts['user'];
    $pass = $dbOpts['pass'] ?? '';
    $db = ltrim($dbOpts['path'], '/');
}

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
if (isset($port)) {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
}

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to DB successfully.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `sss_contribution_brackets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `range_from` DECIMAL(10,2) NOT NULL,
            `range_to` DECIMAL(10,2) NULL,
            `msc` DECIMAL(10,2) NOT NULL,
            `ee_amount` DECIMAL(10,2) NOT NULL,
            `er_amount` DECIMAL(10,2) NOT NULL,
            `ec_amount` DECIMAL(10,2) NOT NULL,
            `wisp_ee` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `wisp_er` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `effective_from` DATE NOT NULL,
            `effective_to` DATE NULL
        );
    ");
    $pdo->exec("TRUNCATE TABLE `sss_contribution_brackets`");

    $sssInserts = [];
    $effective_from = '2025-01-01'; // 15% rate is effective 2025
    $sssInserts[] = "(-999999, 4249.99, 4000, 200, 400, 10, 0, 0, '$effective_from')";

    $msc = 4500;
    $range_from = 4250;

    while ($msc <= 35000) {
        $range_to = $msc == 35000 ? 9999999.99 : $range_from + 499.99;
        
        $regular_msc = min($msc, 20000);
        $wisp_msc = max(0, $msc - 20000);
        
        $ee = $regular_msc * 0.05;
        $er = $regular_msc * 0.10;
        $ec = $msc < 15000 ? 10 : 30;
        
        $wisp_ee = $wisp_msc * 0.05;
        $wisp_er = $wisp_msc * 0.10;
        
        $sssInserts[] = "($range_from, $range_to, $msc, $ee, $er, $ec, $wisp_ee, $wisp_er, '$effective_from')";
        
        $range_from += 500;
        $msc += 500;
    }

    $pdo->exec("INSERT INTO `sss_contribution_brackets` (`range_from`, `range_to`, `msc`, `ee_amount`, `er_amount`, `ec_amount`, `wisp_ee`, `wisp_er`, `effective_from`) VALUES " . implode(", ", $sssInserts));
    echo "SSS brackets seeded.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `philhealth_config` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `rate_total` DECIMAL(5,4) NOT NULL,
            `floor_salary` DECIMAL(10,2) NOT NULL,
            `ceiling_salary` DECIMAL(10,2) NOT NULL,
            `effective_from` DATE NOT NULL,
            `effective_to` DATE NULL
        );
    ");
    $pdo->exec("TRUNCATE TABLE `philhealth_config`");
    $pdo->exec("INSERT INTO `philhealth_config` (`rate_total`, `floor_salary`, `ceiling_salary`, `effective_from`) VALUES (0.05, 10000, 100000, '2024-01-01')");
    echo "PhilHealth config seeded.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `pagibig_config` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `low_threshold` DECIMAL(10,2) NOT NULL,
            `low_rate` DECIMAL(5,4) NOT NULL,
            `high_rate` DECIMAL(5,4) NOT NULL,
            `fund_salary_ceiling` DECIMAL(10,2) NOT NULL,
            `er_rate` DECIMAL(5,4) NOT NULL,
            `effective_from` DATE NOT NULL,
            `effective_to` DATE NULL
        );
    ");
    $pdo->exec("TRUNCATE TABLE `pagibig_config`");
    $pdo->exec("INSERT INTO `pagibig_config` (`low_threshold`, `low_rate`, `high_rate`, `fund_salary_ceiling`, `er_rate`, `effective_from`) VALUES (1500, 0.01, 0.02, 10000, 0.02, '2024-02-01')");
    echo "Pag-IBIG config seeded.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `bir_withholding_brackets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `pay_frequency` ENUM('Monthly', 'Semi-Monthly', 'Weekly', 'Daily') NOT NULL,
            `lower_limit` DECIMAL(10,2) NOT NULL,
            `base_tax` DECIMAL(10,2) NOT NULL,
            `rate_on_excess` DECIMAL(5,4) NOT NULL,
            `effective_from` DATE NOT NULL,
            `effective_to` DATE NULL
        );
    ");
    $pdo->exec("TRUNCATE TABLE `bir_withholding_brackets`");
    $pdo->exec("
        INSERT INTO `bir_withholding_brackets` (`pay_frequency`, `lower_limit`, `base_tax`, `rate_on_excess`, `effective_from`) VALUES
        ('Monthly', 0, 0, 0, '2023-01-01'),
        ('Monthly', 20833, 0, 0.15, '2023-01-01'),
        ('Monthly', 33333, 1875, 0.20, '2023-01-01'),
        ('Monthly', 66667, 8541.67, 0.25, '2023-01-01'),
        ('Monthly', 166667, 33541.67, 0.30, '2023-01-01'),
        ('Monthly', 666667, 183541.67, 0.35, '2023-01-01'),
        ('Semi-Monthly', 0, 0, 0, '2023-01-01'),
        ('Semi-Monthly', 10417, 0, 0.15, '2023-01-01'),
        ('Semi-Monthly', 16667, 937.50, 0.20, '2023-01-01'),
        ('Semi-Monthly', 33333, 4270.83, 0.25, '2023-01-01'),
        ('Semi-Monthly', 83333, 16770.83, 0.30, '2023-01-01'),
        ('Semi-Monthly', 333333, 91770.83, 0.35, '2023-01-01')
    ");
    echo "BIR withholding brackets seeded.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `de_minimis_ceilings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `item_name` VARCHAR(100) NOT NULL,
            `ceiling_amount` DECIMAL(10,2) NOT NULL,
            `frequency` ENUM('Monthly', 'Yearly', 'Semester', 'Days') NOT NULL,
            `effective_from` DATE NOT NULL,
            `effective_to` DATE NULL
        );
    ");
    $pdo->exec("TRUNCATE TABLE `de_minimis_ceilings`");
    $pdo->exec("
        INSERT INTO `de_minimis_ceilings` (`item_name`, `ceiling_amount`, `frequency`, `effective_from`) VALUES
        ('Rice Subsidy', 2500, 'Monthly', '2026-01-06'),
        ('Uniform & Clothing', 8000, 'Yearly', '2026-01-06'),
        ('Medical Cash Allowance', 2000, 'Semester', '2026-01-06'),
        ('Laundry', 400, 'Monthly', '2026-01-06'),
        ('Achievement Awards', 12000, 'Yearly', '2026-01-06'),
        ('Christmas/Anniversary Gifts', 6000, 'Yearly', '2026-01-06'),
        ('CBA & Productivity', 12000, 'Yearly', '2026-01-06'),
        ('Monetized Unused VL', 10, 'Days', '2026-01-06'),
        ('Actual Medical Assistance', 10000, 'Yearly', '2026-01-06')
    ");
    echo "De Minimis ceilings seeded.\n";

    // Add ER columns to payroll_run_employees if they don't exist
    $columns = ['sss_er', 'sss_ec', 'wisp_er', 'phic_er', 'hdmf_er', 'thirteenth_month_accrual'];
    foreach ($columns as $col) {
        try {
            $pdo->exec("ALTER TABLE `payroll_run_employees` ADD COLUMN `$col` DECIMAL(10,2) NOT NULL DEFAULT 0");
            echo "Added column $col.\n";
        } catch (PDOException $e) {
            // Column probably exists, ignore
        }
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage());
}
