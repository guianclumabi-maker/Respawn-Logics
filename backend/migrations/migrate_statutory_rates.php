<?php
if (!isset($pdo)) {
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
}

function insertVersionedRecord($pdo, $table, $columns, $rows, $naturalKeys = []) {
    if (empty($rows)) return;

    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $effectiveFrom = $row['effective_from'];

            // 1. Build where clause for natural keys + effective_from (idempotency check)
            $whereClauses = ["`effective_from` = ?"];
            $whereVals = [$effectiveFrom];
            foreach ($naturalKeys as $key) {
                if (array_key_exists($key, $row)) {
                    $whereClauses[] = "`$key` = ?";
                    $whereVals[] = $row[$key];
                } else {
                    $whereClauses[] = "`$key` IS NULL";
                }
            }
            $whereSql = implode(' AND ', $whereClauses);

            $stmt = $pdo->prepare("SELECT 1 FROM `$table` WHERE $whereSql LIMIT 1");
            $stmt->execute($whereVals);
            if ($stmt->fetch()) {
                // Already exists, skip this row
                continue;
            }

            // 2. Find currently active record matching natural keys
            $activeClauses = ["`effective_to` IS NULL"];
            $activeVals = [];
            foreach ($naturalKeys as $key) {
                if (array_key_exists($key, $row)) {
                    $activeClauses[] = "`$key` = ?";
                    $activeVals[] = $row[$key];
                } else {
                    $activeClauses[] = "`$key` IS NULL";
                }
            }
            $activeSql = implode(' AND ', $activeClauses);

            $stmt = $pdo->prepare("SELECT `effective_from`, `id` FROM `$table` WHERE $activeSql LIMIT 1");
            $stmt->execute($activeVals);
            $active = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($active) {
                $activeFrom = $active['effective_from'];
                if (strtotime($effectiveFrom) <= strtotime($activeFrom)) {
                    echo "Warning: New version $effectiveFrom is older or equal to active version $activeFrom for $table. Skipping.\n";
                    continue;
                }

                // Close active record
                $closeStmt = $pdo->prepare("UPDATE `$table` SET `effective_to` = DATE_SUB(?, INTERVAL 1 DAY) WHERE `id` = ?");
                $closeStmt->execute([$effectiveFrom, $active['id']]);
            }

            // 3. Insert new row
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $insertStmt = $pdo->prepare("INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)");
            $vals = [];
            foreach ($columns as $col) {
                $vals[] = $row[$col] ?? null;
            }
            $insertStmt->execute($vals);

            try {
                $auditStmt = $pdo->prepare("INSERT INTO `audit_logs` (`tenant_id`, `user_email`, `action`, `details`, `created_at`) VALUES (NULL, 'system', 'statutory_rate_version_added', ?, NOW())");
                $auditStmt->execute([json_encode(['table' => $table, 'effective_from' => $effectiveFrom, 'key' => array_intersect_key($row, array_flip($naturalKeys))])]);
            } catch (Exception $e) {
                try {
                    $auditStmt = $pdo->prepare("INSERT INTO `audit_logs` (`user_email`, `action`, `details`, `created_at`) VALUES ('system', 'statutory_rate_version_added', ?, NOW())");
                    $auditStmt->execute([json_encode(['table' => $table, 'effective_from' => $effectiveFrom, 'key' => array_intersect_key($row, array_flip($naturalKeys))])]);
                } catch (Exception $e2) {
                    // Ignore audit failure
                }
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

try {
    if (!isset($pdo)) {
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected to DB successfully.\n";
    } else {
        echo "Using existing DB connection.\n";
    }

    // 1. Statutory Parameters (New Table)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `statutory_parameters` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `param_key` VARCHAR(100) NOT NULL,
            `param_value` DECIMAL(12,2) NOT NULL,
            `effective_from` DATE NOT NULL,
            `effective_to` DATE NULL
        );
    ");

    $paramRows = [
        ['param_key' => 'thirteenth_month_exemption_cap', 'param_value' => 90000, 'effective_from' => '2023-01-01'],
        ['param_key' => 'sss_wisp_msc_threshold', 'param_value' => 20000, 'effective_from' => '2023-01-01'],
        ['param_key' => 'sss_ec_threshold', 'param_value' => 15000, 'effective_from' => '2023-01-01'],
        ['param_key' => 'sss_ee_rate', 'param_value' => 0.05, 'effective_from' => '2025-01-01'],
        ['param_key' => 'sss_er_rate', 'param_value' => 0.10, 'effective_from' => '2025-01-01']
    ];
    // Since paramRows has multiple different keys sharing the same effective_from, we can insert them as one set
    insertVersionedRecord($pdo, 'statutory_parameters', ['param_key', 'param_value', 'effective_from'], $paramRows, ['param_key']);

    // Seed working-day divisors into statutory_parameters so the payroll engine
    // reads them from the database rather than falling back to hard-coded constants.
    // CPA VALIDATION NEEDED: confirm working_days_per_year (313 is the PH standard) and
    // hours_per_day (8 is the standard) match the employer's CBA / policy.
    $divisorRows = [
        ['param_key' => 'working_days_per_year', 'param_value' => 313, 'effective_from' => '2023-01-01'],
        ['param_key' => 'hours_per_day',         'param_value' => 8,   'effective_from' => '2023-01-01'],
        // Multipliers (CPA: confirm each rate against DOLE Labor Advisory / company policy)
        ['param_key' => 'ordinary_ot_multiplier',            'param_value' => 1.25, 'effective_from' => '2023-01-01'],
        ['param_key' => 'rest_day_or_special_multiplier',    'param_value' => 1.30, 'effective_from' => '2023-01-01'],
        ['param_key' => 'regular_holiday_multiplier',        'param_value' => 2.00, 'effective_from' => '2023-01-01'],
        ['param_key' => 'night_diff_multiplier',             'param_value' => 0.10, 'effective_from' => '2023-01-01'],
    ];
    insertVersionedRecord($pdo, 'statutory_parameters', ['param_key', 'param_value', 'effective_from'], $divisorRows, ['param_key']);


    // 2. SSS Brackets
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

    // Guard against re-inserting an incorrect SSS seed:
    // The original migration accidentally seeded MSC starting at 4000 (too low).
    // We only delete those rows if the minimum MSC present for 2025-01-01 is < 5000,
    // meaning the bad seed is still present. This makes the cleanup idempotent.
    $badSeedStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM `sss_contribution_brackets` WHERE `effective_from` = '2025-01-01' AND `msc` < 5000"
    );
    $badSeedStmt->execute();
    if ((int)$badSeedStmt->fetchColumn() > 0) {
        $pdo->exec("DELETE FROM `sss_contribution_brackets` WHERE `effective_from` = '2025-01-01'");
    }

    $sssRows = [];
    $effective_from = '2025-01-01';
    $sssRows[] = ['range_from' => -999999, 'range_to' => 5249.99, 'msc' => 5000, 'ee_amount' => 250, 'er_amount' => 500, 'ec_amount' => 10, 'wisp_ee' => 0, 'wisp_er' => 0, 'effective_from' => $effective_from];

    $msc = 5500;
    $range_from = 5250;
    while ($msc <= 35000) {
        $range_to = $msc == 35000 ? 9999999.99 : $range_from + 499.99;
        $regular_msc = min($msc, 20000);
        $wisp_msc = max(0, $msc - 20000);
        
        $ee = $regular_msc * 0.05;
        $er = $regular_msc * 0.10;
        $ec = $msc < 15000 ? 10 : 30;
        
        $wisp_ee = $wisp_msc * 0.05;
        $wisp_er = $wisp_msc * 0.10;
        
        $sssRows[] = ['range_from' => $range_from, 'range_to' => $range_to, 'msc' => $msc, 'ee_amount' => $ee, 'er_amount' => $er, 'ec_amount' => $ec, 'wisp_ee' => $wisp_ee, 'wisp_er' => $wisp_er, 'effective_from' => $effective_from];
        $range_from += 500;
        $msc += 500;
    }
    insertVersionedRecord($pdo, 'sss_contribution_brackets', ['range_from', 'range_to', 'msc', 'ee_amount', 'er_amount', 'ec_amount', 'wisp_ee', 'wisp_er', 'effective_from'], $sssRows, ['range_from', 'range_to']);


    // 3. PhilHealth
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
    insertVersionedRecord($pdo, 'philhealth_config', ['rate_total', 'floor_salary', 'ceiling_salary', 'effective_from'], [
        ['rate_total' => 0.05, 'floor_salary' => 10000, 'ceiling_salary' => 100000, 'effective_from' => '2024-01-01']
    ], []);

    // 4. Pag-IBIG
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
    insertVersionedRecord($pdo, 'pagibig_config', ['low_threshold', 'low_rate', 'high_rate', 'fund_salary_ceiling', 'er_rate', 'effective_from'], [
        ['low_threshold' => 1500, 'low_rate' => 0.01, 'high_rate' => 0.02, 'fund_salary_ceiling' => 10000, 'er_rate' => 0.02, 'effective_from' => '2024-02-01']
    ], []);

    // 5. BIR Withholding
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
    // Correct existing BIR centavos for Monthly/Semi-Monthly
    $pdo->exec("UPDATE `bir_withholding_brackets` SET `base_tax` = 8541.80 WHERE `pay_frequency` = 'Monthly' AND `lower_limit` = 66667 AND `effective_from` = '2023-01-01'");
    $pdo->exec("UPDATE `bir_withholding_brackets` SET `base_tax` = 33541.80 WHERE `pay_frequency` = 'Monthly' AND `lower_limit` = 166667 AND `effective_from` = '2023-01-01'");
    $pdo->exec("UPDATE `bir_withholding_brackets` SET `base_tax` = 183541.80 WHERE `pay_frequency` = 'Monthly' AND `lower_limit` = 666667 AND `effective_from` = '2023-01-01'");
    $pdo->exec("UPDATE `bir_withholding_brackets` SET `base_tax` = 4270.70 WHERE `pay_frequency` = 'Semi-Monthly' AND `lower_limit` = 33333 AND `effective_from` = '2023-01-01'");
    $pdo->exec("UPDATE `bir_withholding_brackets` SET `base_tax` = 16770.70 WHERE `pay_frequency` = 'Semi-Monthly' AND `lower_limit` = 83333 AND `effective_from` = '2023-01-01'");
    $pdo->exec("UPDATE `bir_withholding_brackets` SET `base_tax` = 91770.70 WHERE `pay_frequency` = 'Semi-Monthly' AND `lower_limit` = 333333 AND `effective_from` = '2023-01-01'");

    $birRowsMonthly = [
        ['pay_frequency' => 'Monthly', 'lower_limit' => 0, 'base_tax' => 0, 'rate_on_excess' => 0, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Monthly', 'lower_limit' => 20833, 'base_tax' => 0, 'rate_on_excess' => 0.15, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Monthly', 'lower_limit' => 33333, 'base_tax' => 1875, 'rate_on_excess' => 0.20, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Monthly', 'lower_limit' => 66667, 'base_tax' => 8541.80, 'rate_on_excess' => 0.25, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Monthly', 'lower_limit' => 166667, 'base_tax' => 33541.80, 'rate_on_excess' => 0.30, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Monthly', 'lower_limit' => 666667, 'base_tax' => 183541.80, 'rate_on_excess' => 0.35, 'effective_from' => '2023-01-01']
    ];
    insertVersionedRecord($pdo, 'bir_withholding_brackets', ['pay_frequency', 'lower_limit', 'base_tax', 'rate_on_excess', 'effective_from'], $birRowsMonthly, ['pay_frequency', 'lower_limit']);

    $birRowsSemi = [
        ['pay_frequency' => 'Semi-Monthly', 'lower_limit' => 0, 'base_tax' => 0, 'rate_on_excess' => 0, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Semi-Monthly', 'lower_limit' => 10417, 'base_tax' => 0, 'rate_on_excess' => 0.15, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Semi-Monthly', 'lower_limit' => 16667, 'base_tax' => 937.50, 'rate_on_excess' => 0.20, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Semi-Monthly', 'lower_limit' => 33333, 'base_tax' => 4270.70, 'rate_on_excess' => 0.25, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Semi-Monthly', 'lower_limit' => 83333, 'base_tax' => 16770.70, 'rate_on_excess' => 0.30, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Semi-Monthly', 'lower_limit' => 333333, 'base_tax' => 91770.70, 'rate_on_excess' => 0.35, 'effective_from' => '2023-01-01']
    ];
    insertVersionedRecord($pdo, 'bir_withholding_brackets', ['pay_frequency', 'lower_limit', 'base_tax', 'rate_on_excess', 'effective_from'], $birRowsSemi, ['pay_frequency', 'lower_limit']);

    $birRowsWeekly = [
        ['pay_frequency' => 'Weekly', 'lower_limit' => 0, 'base_tax' => 0, 'rate_on_excess' => 0, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Weekly', 'lower_limit' => 4808, 'base_tax' => 0, 'rate_on_excess' => 0.15, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Weekly', 'lower_limit' => 7692, 'base_tax' => 432.60, 'rate_on_excess' => 0.20, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Weekly', 'lower_limit' => 15385, 'base_tax' => 1971.20, 'rate_on_excess' => 0.25, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Weekly', 'lower_limit' => 38462, 'base_tax' => 7740.45, 'rate_on_excess' => 0.30, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Weekly', 'lower_limit' => 153846, 'base_tax' => 42355.65, 'rate_on_excess' => 0.35, 'effective_from' => '2023-01-01']
    ];
    insertVersionedRecord($pdo, 'bir_withholding_brackets', ['pay_frequency', 'lower_limit', 'base_tax', 'rate_on_excess', 'effective_from'], $birRowsWeekly, ['pay_frequency', 'lower_limit']);

    $birRowsDaily = [
        ['pay_frequency' => 'Daily', 'lower_limit' => 0, 'base_tax' => 0, 'rate_on_excess' => 0, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Daily', 'lower_limit' => 685, 'base_tax' => 0, 'rate_on_excess' => 0.15, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Daily', 'lower_limit' => 1096, 'base_tax' => 61.65, 'rate_on_excess' => 0.20, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Daily', 'lower_limit' => 2192, 'base_tax' => 280.85, 'rate_on_excess' => 0.25, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Daily', 'lower_limit' => 5479, 'base_tax' => 1102.60, 'rate_on_excess' => 0.30, 'effective_from' => '2023-01-01'],
        ['pay_frequency' => 'Daily', 'lower_limit' => 21918, 'base_tax' => 6034.30, 'rate_on_excess' => 0.35, 'effective_from' => '2023-01-01']
    ];
    insertVersionedRecord($pdo, 'bir_withholding_brackets', ['pay_frequency', 'lower_limit', 'base_tax', 'rate_on_excess', 'effective_from'], $birRowsDaily, ['pay_frequency', 'lower_limit']);

    // 6. De Minimis
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
    $dmRows = [
        ['item_name' => 'Rice Subsidy', 'ceiling_amount' => 2500, 'frequency' => 'Monthly', 'effective_from' => '2026-01-06'],
        ['item_name' => 'Uniform & Clothing', 'ceiling_amount' => 8000, 'frequency' => 'Yearly', 'effective_from' => '2026-01-06'],
        ['item_name' => 'Medical Cash Allowance', 'ceiling_amount' => 2000, 'frequency' => 'Semester', 'effective_from' => '2026-01-06'],
        ['item_name' => 'Laundry', 'ceiling_amount' => 400, 'frequency' => 'Monthly', 'effective_from' => '2026-01-06'],
        ['item_name' => 'Achievement Awards', 'ceiling_amount' => 12000, 'frequency' => 'Yearly', 'effective_from' => '2026-01-06'],
        ['item_name' => 'Christmas/Anniversary Gifts', 'ceiling_amount' => 6000, 'frequency' => 'Yearly', 'effective_from' => '2026-01-06'],
        ['item_name' => 'CBA & Productivity', 'ceiling_amount' => 12000, 'frequency' => 'Yearly', 'effective_from' => '2026-01-06'],
        ['item_name' => 'Monetized Unused VL', 'ceiling_amount' => 10, 'frequency' => 'Days', 'effective_from' => '2026-01-06'],
        ['item_name' => 'Actual Medical Assistance', 'ceiling_amount' => 10000, 'frequency' => 'Yearly', 'effective_from' => '2026-01-06']
    ];
    insertVersionedRecord($pdo, 'de_minimis_ceilings', ['item_name', 'ceiling_amount', 'frequency', 'effective_from'], $dmRows, ['item_name']);

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
