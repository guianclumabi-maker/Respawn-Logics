<?php
if (!defined('MIGRATION_SAFE')) define('MIGRATION_SAFE', true);
require_once __DIR__ . '/../bootstrap/app.php';

try {
    echo "Starting ATS tables migration...\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS `jobs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT '1',
        `title` varchar(255) NOT NULL,
        `department` varchar(100) DEFAULT NULL,
        `location` varchar(150) DEFAULT NULL,
        `employment_type` varchar(50) DEFAULT 'Full-Time',
        `salary_min` decimal(12,2) DEFAULT NULL,
        `salary_max` decimal(12,2) DEFAULT NULL,
        `description` text,
        `requirements` text,
        `status` varchar(50) DEFAULT 'Open',
        `priority` varchar(50) DEFAULT 'Normal',
        `hiring_manager` varchar(150) DEFAULT NULL,
        `assigned_recruiter` varchar(150) DEFAULT NULL,
        `approval_status` varchar(50) DEFAULT 'Approved',
        `approved_by` varchar(150) DEFAULT NULL,
        `approved_at` datetime DEFAULT NULL,
        `closed_at` datetime DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created jobs table.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS `candidate_profiles` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT '1',
        `name` varchar(255) NOT NULL,
        `email` varchar(255) DEFAULT NULL,
        `phone` varchar(50) DEFAULT NULL,
        `location` varchar(150) DEFAULT NULL,
        `skills` text,
        `experience_years` int(11) DEFAULT 0,
        `resume_text` longtext,
        `salary_expectation` decimal(12,2) DEFAULT NULL,
        `source` varchar(100) DEFAULT 'Direct',
        `tags` text,
        `assigned_recruiter` varchar(150) DEFAULT NULL,
        `assigned_hiring_manager` varchar(150) DEFAULT NULL,
        `status` varchar(50) DEFAULT 'Active',
        `ai_summary` text,
        `last_activity_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created candidate_profiles table.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS `candidate_applications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT '1',
        `candidate_id` int(11) NOT NULL,
        `job_id` int(11) NOT NULL,
        `stage` varchar(100) DEFAULT 'Applied',
        `source` varchar(100) DEFAULT 'Direct',
        `assigned_recruiter` varchar(150) DEFAULT NULL,
        `rating` int(11) DEFAULT 0,
        `ai_match_score` int(11) DEFAULT NULL,
        `stage_entered_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `hired_at` datetime DEFAULT NULL,
        `rejected_at` datetime DEFAULT NULL,
        `rejection_reason` text,
        `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created candidate_applications table.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS `interviews` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT '1',
        `application_id` int(11) NOT NULL,
        `candidate_id` int(11) NOT NULL,
        `job_id` int(11) NOT NULL,
        `interview_type` varchar(100) DEFAULT 'General',
        `scheduled_at` datetime NOT NULL,
        `duration_minutes` int(11) DEFAULT 60,
        `location` varchar(255) DEFAULT NULL,
        `meeting_link` varchar(255) DEFAULT NULL,
        `interviewer_name` varchar(150) DEFAULT NULL,
        `status` varchar(50) DEFAULT 'Scheduled',
        `notes` text,
        `score` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created interviews table.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS `scorecards` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT '1',
        `interview_id` int(11) NOT NULL,
        `evaluator_name` varchar(150) DEFAULT NULL,
        `technical_score` int(11) DEFAULT 0,
        `communication_score` int(11) DEFAULT 0,
        `culture_score` int(11) DEFAULT 0,
        `overall_score` int(11) DEFAULT 0,
        `recommendation` varchar(50) DEFAULT 'Maybe',
        `strengths` text,
        `concerns` text,
        `notes` text,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created scorecards table.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS `candidate_notes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT '1',
        `candidate_id` int(11) NOT NULL,
        `application_id` int(11) DEFAULT NULL,
        `author_name` varchar(150) DEFAULT 'System',
        `content` text,
        `note_type` varchar(50) DEFAULT 'Comment',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created candidate_notes table.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS `talent_pools` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT '1',
        `name` varchar(150) NOT NULL,
        `description` text,
        `created_by` varchar(150) DEFAULT 'System',
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created talent_pools table.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS `pool_members` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT '1',
        `pool_id` int(11) NOT NULL,
        `candidate_id` int(11) NOT NULL,
        `added_by` varchar(150) DEFAULT 'System',
        `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_pool_candidate` (`pool_id`, `candidate_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created pool_members table.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS `approvals` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT '1',
        `type` varchar(50) NOT NULL,
        `reference_id` int(11) NOT NULL,
        `requested_by` varchar(150) DEFAULT NULL,
        `approver_name` varchar(150) DEFAULT NULL,
        `notes` text,
        `status` varchar(50) DEFAULT 'Pending',
        `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `resolved_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created approvals table.\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS `activities` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT '1',
        `candidate_id` int(11) DEFAULT NULL,
        `job_id` int(11) DEFAULT NULL,
        `application_id` int(11) DEFAULT NULL,
        `action` varchar(100) NOT NULL,
        `description` text,
        `actor_name` varchar(150) DEFAULT 'System',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "- Created activities table.\n";

    echo "ATS migration completed successfully!\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
