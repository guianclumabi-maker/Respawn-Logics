<?php
require_once __DIR__ . '/bootstrap/app.php';

try {
    $email = 'admin@respawnlogics.com';
    $password = 'password123';
    $tenantId = '1';

    // Check if users table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT NULL,
        `full_name` varchar(255) NOT NULL,
        `email` varchar(255) NOT NULL,
        `password_hash` varchar(255) NOT NULL,
        `role` varchar(50) DEFAULT 'Employee',
        `employment_status` varchar(50) DEFAULT 'Active',
        `work_location` varchar(150) DEFAULT NULL,
        `department` varchar(100) DEFAULT NULL,
        `immediate_supervisor` varchar(100) DEFAULT NULL,
        `job_title` varchar(150) DEFAULT NULL,
        `base_salary` decimal(12,2) DEFAULT NULL,
        `profile_image` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        // Update the password just in case
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ?, role = 'Super_Admin', tenant_id = ? WHERE email = ?");
        $updateStmt->execute([$hash, $tenantId, $email]);
        echo "Admin account already existed, updated password and role to Super_Admin.";
    } else {
        // Insert new user
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $insertStmt = $pdo->prepare("INSERT INTO users (tenant_id, full_name, email, password_hash, role) VALUES (?, 'System Admin', ?, ?, 'Super_Admin')");
        $insertStmt->execute([$tenantId, $email, $hash]);
        echo "Successfully created admin account: $email / $password";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
