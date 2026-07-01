<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

try {
    // Ensure the table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tenants` (
        `id` VARCHAR(50) PRIMARY KEY,
        `company_name` VARCHAR(255) NOT NULL,
        `contact_email` VARCHAR(150) NOT NULL,
        `subscription_tier` VARCHAR(50) DEFAULT 'Trial',
        `status` VARCHAR(50) DEFAULT 'Active',
        `foot_traffic_score` INT DEFAULT 0,
        `ai_api_calls` INT DEFAULT 0,
        `permission_version` INT DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Tenants table created successfully.\n";

    // Add settings columns idempotently (safe to re-run on every deploy)
    $tenantCols = [
        'setup_mode'                => "VARCHAR(20) DEFAULT 'Solo'",
        'logo'                      => "VARCHAR(255) DEFAULT NULL",
        'timezone'                  => "VARCHAR(64) DEFAULT 'Asia/Manila'",
        'locale'                    => "VARCHAR(20) DEFAULT 'en-PH'",
        'enforce_2fa'               => "TINYINT(1) NOT NULL DEFAULT 0",
        'notification_prefs'        => "TEXT DEFAULT NULL",
        'support_access_expires_at' => "DATETIME NULL DEFAULT NULL",
    ];
    foreach ($tenantCols as $col => $def) {
        $exists = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE table_schema = DATABASE() AND table_name = 'tenants' AND column_name = " . $pdo->quote($col)
        )->fetchColumn();
        if ($exists === 0) {
            $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `$col` $def");
            echo "Added tenants.$col\n";
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `tenant_modules` (
        `tenant_id` VARCHAR(50) NOT NULL,
        `module_key` VARCHAR(50) NOT NULL,
        `is_enabled` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`tenant_id`, `module_key`),
        FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Tenant Modules table created successfully.\n";

    // Seed mock tenants
    $stmt = $pdo->prepare("INSERT IGNORE INTO `tenants` (`id`, `company_name`, `contact_email`, `subscription_tier`, `status`, `foot_traffic_score`, `ai_api_calls`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $seedData = [
        ['1', 'Respawn Logic (Internal)', 'admin@respawnlogic.com', 'Enterprise', 'Active', 1542, 8500],
        ['TENANT_A', 'Acme Corporation', 'hr@acme.com', 'Pro', 'Active', 850, 3200],
        ['TENANT_B', 'Globex Industries', 'payroll@globex.com', 'Starter', 'Active', 120, 450],
        ['TENANT_C', 'Initech LLC', 'boss@initech.com', 'Pro', 'Past Due', 45, 120],
        ['TENANT_D', 'Soylent Corp', 'hr@soylent.com', 'Enterprise', 'Active', 3200, 15000]
    ];

    foreach ($seedData as $data) {
        $stmt->execute($data);
    }
    echo "Seeded mock clients.\n";

    // Elevate all admin users to Super_Admin for demo purposes
    $pdo->exec("UPDATE `users` SET `role` = 'Super_Admin' WHERE `role` = 'Admin'");
    echo "Elevated admins to Super_Admin.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
