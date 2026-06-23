<?php

define('MIGRATION_SAFE', true);

// 1. Force testing environment
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_NAME'] = 'employee_system_test';
$_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? '127.0.0.1';
$_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? '3306';
$_ENV['DB_USER'] = $_ENV['DB_USER'] ?? 'root';
$_ENV['DB_PASS'] = $_ENV['DB_PASS'] ?? '';

// echo "Setting up Test Database: " . $_ENV['DB_NAME'] . "...\n";

// 2. Drop and recreate test database
try {
    $dsnNoDb = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $_ENV['DB_HOST'], $_ENV['DB_PORT']);
    $pdoSetup = new PDO($dsnNoDb, $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $pdoSetup->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdoSetup->exec("DROP DATABASE IF EXISTS `{$_ENV['DB_NAME']}`");
    $pdoSetup->exec("CREATE DATABASE `{$_ENV['DB_NAME']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    die("Failed to recreate test database: " . $e->getMessage() . "\n");
}

// 3. Include main app bootstrap which connects to the test database
require_once __DIR__ . '/../bootstrap/app.php';

// Ensure global $pdo exists
global $pdo;
if (!$pdo) {
    if (isset($pdoError)) {
        die("Failed to establish PDO connection to test database: " . $pdoError->getMessage() . "\n");
    }
    die("Failed to establish PDO connection to test database.\n");
}

// 4. Run all migrations
echo "Running schema migrations...\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$migrationScripts = [
    'migrate_tenants.php',
    'seed_admin.php',
    'setup_db.php',
    'iam_seed.php',
    'migrate_core_hr.php',
    'migrate_core_hr_columns.php',
    'migrate_ats_tables.php',
    'migrate_ats_queries.php',
    'migrate_benefits.php',
    'migrate_compensation.php',
    'migrate_esm.php',
    'migrate_elr.php',
    'migrate_elr_knowledge.php',
    'migrate_expenses.php',
    'migrate_global_cache.php',
    'migrate_knowledge_base.php',
    'migrate_onboarding.php',
    'migrate_payroll.php',
    'migrate_performance.php',
    'migrate_score_breakdown.php',
    'migrate_scoring_columns.php',
    'migrate_security.php',
    'migrate_support_access.php'
];

foreach ($migrationScripts as $script) {
    $path = __DIR__ . '/../database_scripts/' . $script;
    if (file_exists($path)) {
        // Output buffering to hide successful migration echoes
        ob_start();
        require $path;
        ob_end_clean();
    }
}

// Additional specific backend migrations
$backendMigrations = [
    'migrate_resume_columns.php',
    'migrate_payroll_config.php',
    'migrate_schema_updates.php',
    'migrate_statutory_rates.php'
];
foreach ($backendMigrations as $script) {
    $path = __DIR__ . '/../backend/migrations/' . $script;
    if (file_exists($path)) {
        ob_start();
        require $path;
        ob_end_clean();
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "Test database setup complete.\n\n";


// --- Fixture Helpers ---
class FixtureHelper {
    public static function createTenant($pdo, $name = 'Test Tenant') {
        $stmt = $pdo->prepare("INSERT INTO tenants (name) VALUES (?)");
        $stmt->execute([$name]);
        return (int)$pdo->lastInsertId();
    }

    public static function createUser($pdo, $tenantId, $email, $role = 'Employee') {
        $stmt = $pdo->prepare("INSERT INTO users (tenant_id, first_name, last_name, email, password_hash, role) VALUES (?, 'Test', 'User', ?, ?, ?)");
        $stmt->execute([$tenantId, $email, password_hash('password123', PASSWORD_DEFAULT), $role]);
        $userId = (int)$pdo->lastInsertId();
        
        // Ensure default permissions sync for tests
        require_once __DIR__ . '/../services/PermissionService.php';
        PermissionService::userPermissions($pdo, $userId, $tenantId);
        
        return $userId;
    }

    public static function createJob($pdo, $tenantId, $title = 'Software Engineer') {
        $stmt = $pdo->prepare("INSERT INTO jobs (tenant_id, title, status) VALUES (?, ?, 'Open')");
        $stmt->execute([$tenantId, $title]);
        return (int)$pdo->lastInsertId();
    }

    public static function createCandidate($pdo, $tenantId, $name = 'Jane Doe', $email = 'jane@example.com') {
        $stmt = $pdo->prepare("INSERT INTO candidate_profiles (tenant_id, name, email) VALUES (?, ?, ?)");
        $stmt->execute([$tenantId, $name, $email]);
        return (int)$pdo->lastInsertId();
    }

    public static function createApplication($pdo, $tenantId, $candidateId, $jobId, $stage = 'Applied') {
        $stmt = $pdo->prepare("INSERT INTO candidate_applications (tenant_id, candidate_id, job_id, stage) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tenantId, $candidateId, $jobId, $stage]);
        return (int)$pdo->lastInsertId();
    }
}
