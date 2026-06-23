<?php
require_once __DIR__ . '/../../config/db.php';

try {
    $pdo->beginTransaction();

    // 1. Create org_units
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `org_units` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tenant_id` VARCHAR(50) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `parent_id` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_tenant_parent` (`tenant_id`, `parent_id`),
            CONSTRAINT `fk_org_unit_parent` FOREIGN KEY (`parent_id`) REFERENCES `org_units` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "Ensured org_units table exists.\n";

    // 2. Add org_unit_id to users
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN org_unit_id INT NULL AFTER department");
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_user_org_unit FOREIGN KEY (org_unit_id) REFERENCES org_units(id) ON DELETE SET NULL");
        echo "Added org_unit_id to users.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) throw $e;
        echo "org_unit_id already exists in users.\n";
    }

    // 3. Clean up manager_id before converting to INT
    // Some manager_id might be empty string '' instead of NULL.
    $pdo->exec("UPDATE users SET manager_id = NULL WHERE manager_id = ''");
    
    // 4. Convert manager_id to INT safely
    $pdo->exec("ALTER TABLE users MODIFY manager_id INT NULL");
    echo "Converted manager_id to INT.\n";

    // 5. Check if user_roles has scope/org_unit_id (we did this in M1, but safety first)
    try {
        $pdo->exec("ALTER TABLE user_roles ADD COLUMN scope ENUM('self', 'team', 'department', 'branch', 'tenant') DEFAULT 'tenant' AFTER role_id");
        $pdo->exec("ALTER TABLE user_roles ADD COLUMN org_unit_id INT NULL AFTER scope");
        $pdo->exec("ALTER TABLE user_roles ADD CONSTRAINT fk_user_roles_org_unit FOREIGN KEY (org_unit_id) REFERENCES org_units(id) ON DELETE CASCADE");
        echo "Added scope and org_unit_id to user_roles.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) throw $e;
        echo "scope and org_unit_id already exist in user_roles.\n";
        
        // Ensure the FK exists if we created the columns in M1 before org_units existed
        try {
            $pdo->exec("ALTER TABLE user_roles ADD CONSTRAINT fk_user_roles_org_unit FOREIGN KEY (org_unit_id) REFERENCES org_units(id) ON DELETE CASCADE");
        } catch (PDOException $e2) {
            // Ignore duplicate FK errors
        }
    }

    $pdo->commit();
    echo "Phase 2 Migrations completed successfully.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
}
