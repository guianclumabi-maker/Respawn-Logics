<?php
// Retrieve database credentials from environment variables
global $config;
$dbConfig = $config['database'] ?? [
    'host' => 'localhost',
    'port' => 3306,
    'user' => 'root',
    'pass' => ''
];
$dbname = 'respawn_logics';

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbname
    );
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // If database doesn't exist locally, attempt to connect to MySQL without db name to create it
    if ($e->getCode() == 1049 && $dbConfig['host'] === 'localhost') {
        try {
            $dsnNoDb = sprintf(
                'mysql:host=%s;port=%s;charset=utf8mb4',
                $dbConfig['host'],
                $dbConfig['port']
            );
            $pdo = new PDO($dsnNoDb, $dbConfig['user'], $dbConfig['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");
        } catch (PDOException $ex) {
            die("Database connection failed: " . $ex->getMessage());
        }
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}

// ============================================================
// AUTO-CREATE TABLES — Talent Intelligence Platform Schema
// ============================================================

try {
    // Legacy: organization_canvas table (from org-chart module)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `organization_canvas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `org_name` VARCHAR(255) NOT NULL,
        `org_domain` VARCHAR(255) NOT NULL UNIQUE,
        `org_industry` VARCHAR(100),
        `org_size` VARCHAR(50),
        `nodes` LONGTEXT,
        `connections` LONGTEXT,
        `column_names` TEXT,
        `column_types` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ─── 1. CANDIDATE PROFILES (CRM Entity — independent of jobs) ───
    $pdo->exec("CREATE TABLE IF NOT EXISTS `candidate_profiles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `location` VARCHAR(255) DEFAULT NULL,
        `skills` TEXT DEFAULT NULL,
        `experience_years` INT DEFAULT 0,
        `resume_text` TEXT DEFAULT NULL,
        `salary_expectation` DECIMAL(12,2) DEFAULT NULL,
        `source` VARCHAR(100) DEFAULT 'Direct',
        `tags` TEXT DEFAULT NULL,
        `assigned_recruiter` VARCHAR(255) DEFAULT NULL,
        `assigned_hiring_manager` VARCHAR(255) DEFAULT NULL,
        `ai_summary` TEXT DEFAULT NULL,
        `status` ENUM('Active','Archived','Blacklisted') DEFAULT 'Active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `last_activity_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ─── 2. JOBS ───
    $pdo->exec("CREATE TABLE IF NOT EXISTS `jobs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `department` VARCHAR(100) DEFAULT NULL,
        `location` VARCHAR(255) DEFAULT NULL,
        `employment_type` ENUM('Full-Time','Part-Time','Contract','Intern') DEFAULT 'Full-Time',
        `salary_min` DECIMAL(12,2) DEFAULT NULL,
        `salary_max` DECIMAL(12,2) DEFAULT NULL,
        `description` TEXT,
        `requirements` TEXT,
        `status` ENUM('Draft','Pending Approval','Open','Paused','Closed') DEFAULT 'Draft',
        `priority` ENUM('Normal','Urgent','Critical') DEFAULT 'Normal',
        `hiring_manager` VARCHAR(255) DEFAULT NULL,
        `assigned_recruiter` VARCHAR(255) DEFAULT NULL,
        `approval_status` ENUM('None','Pending','Approved','Rejected') DEFAULT 'None',
        `approved_by` VARCHAR(255) DEFAULT NULL,
        `approved_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `closed_at` DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ─── 3. CANDIDATE APPLICATIONS (Many-to-Many: Candidates ↔ Jobs) ───
    $pdo->exec("CREATE TABLE IF NOT EXISTS `candidate_applications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `candidate_id` INT NOT NULL,
        `job_id` INT NOT NULL,
        `stage` VARCHAR(50) NOT NULL DEFAULT 'Applied',
        `rating` INT DEFAULT 0,
        `ai_match_score` INT DEFAULT NULL,
        `source` VARCHAR(100) DEFAULT 'Direct',
        `assigned_recruiter` VARCHAR(255) DEFAULT NULL,
        `stage_entered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `hired_at` DATETIME DEFAULT NULL,
        `rejected_at` DATETIME DEFAULT NULL,
        `rejection_reason` VARCHAR(255) DEFAULT NULL,
        `last_activity_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`candidate_id`) REFERENCES `candidate_profiles`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_application` (`candidate_id`, `job_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ─── 4. INTERVIEWS ───
    $pdo->exec("CREATE TABLE IF NOT EXISTS `interviews` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `application_id` INT NOT NULL,
        `candidate_id` INT NOT NULL,
        `job_id` INT NOT NULL,
        `interview_type` VARCHAR(100) DEFAULT 'General',
        `scheduled_at` DATETIME NOT NULL,
        `duration_minutes` INT DEFAULT 60,
        `location` VARCHAR(255) DEFAULT NULL,
        `meeting_link` VARCHAR(500) DEFAULT NULL,
        `interviewer_name` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('Scheduled','Completed','Cancelled','No-Show') DEFAULT 'Scheduled',
        `notes` TEXT,
        `score` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`application_id`) REFERENCES `candidate_applications`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`candidate_id`) REFERENCES `candidate_profiles`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ─── 5. SCORECARDS ───
    $pdo->exec("CREATE TABLE IF NOT EXISTS `scorecards` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `interview_id` INT NOT NULL,
        `evaluator_name` VARCHAR(255),
        `technical_score` INT DEFAULT 0,
        `communication_score` INT DEFAULT 0,
        `culture_score` INT DEFAULT 0,
        `overall_score` INT DEFAULT 0,
        `recommendation` ENUM('Strong Yes','Yes','Maybe','No','Strong No') DEFAULT 'Maybe',
        `strengths` TEXT,
        `concerns` TEXT,
        `notes` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`interview_id`) REFERENCES `interviews`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ─── 6. ACTIVITIES (Audit Log) ───
    $pdo->exec("CREATE TABLE IF NOT EXISTS `activities` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `candidate_id` INT DEFAULT NULL,
        `job_id` INT DEFAULT NULL,
        `application_id` INT DEFAULT NULL,
        `action` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `actor_name` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`candidate_id`) REFERENCES `candidate_profiles`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ─── 7. CANDIDATE NOTES (Collaboration) ───
    $pdo->exec("CREATE TABLE IF NOT EXISTS `candidate_notes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `candidate_id` INT NOT NULL,
        `application_id` INT DEFAULT NULL,
        `author_name` VARCHAR(255),
        `content` TEXT NOT NULL,
        `note_type` ENUM('Comment','Feedback','Internal') DEFAULT 'Comment',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`candidate_id`) REFERENCES `candidate_profiles`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ─── 8. TALENT POOLS ───
    $pdo->exec("CREATE TABLE IF NOT EXISTS `talent_pools` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `created_by` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ─── 9. POOL MEMBERS (Candidates ↔ Talent Pools) ───
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pool_members` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `pool_id` INT NOT NULL,
        `candidate_id` INT NOT NULL,
        `added_by` VARCHAR(255),
        `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_pool_member` (`pool_id`, `candidate_id`),
        FOREIGN KEY (`pool_id`) REFERENCES `talent_pools`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`candidate_id`) REFERENCES `candidate_profiles`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ─── 10. APPROVALS (Job/Offer/Requisition workflows) ───
    $pdo->exec("CREATE TABLE IF NOT EXISTS `approvals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tenant_id` VARCHAR(50) DEFAULT '1',
        `type` ENUM('Job','Offer','Requisition') NOT NULL,
        `reference_id` INT NOT NULL,
        `requested_by` VARCHAR(255),
        `approver_name` VARCHAR(255),
        `status` ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        `notes` TEXT,
        `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `resolved_at` DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // ─── DATA MIGRATION: Legacy candidates → candidate_profiles ───
    // If old `candidates` table exists and candidate_profiles is empty, migrate data
    $oldTableExists = $pdo->query("SHOW TABLES LIKE 'candidates'")->rowCount() > 0;
    if ($oldTableExists) {
        $newCount = (int)$pdo->query("SELECT COUNT(*) FROM `candidate_profiles`")->fetchColumn();
        if ($newCount === 0) {
            $oldCount = (int)$pdo->query("SELECT COUNT(*) FROM `candidates`")->fetchColumn();
            if ($oldCount > 0) {
                $pdo->exec("INSERT INTO `candidate_profiles` (`name`, `tags`, `source`, `created_at`)
                    SELECT `name`, `tags`, 'Direct', `created_at` FROM `candidates`");
            }
        }
    }

} catch (PDOException $e) {
    die("Table creation failed: " . $e->getMessage());
}
