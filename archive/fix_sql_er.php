<?php
$file = __DIR__ . '/backend/controllers/EmployeeRelationsController.php';
$content = file_get_contents($file);

$patterns = [
    '/\$this->pdo->query\("SELECT COUNT\(\*\) FROM `employee_relations` WHERE `tenant_id` = \'\{\$this->tenantId\}\'(.*?)"\)->fetchColumn\(\)/' 
        => '$this->pdo->prepare("SELECT COUNT(*) FROM `employee_relations` WHERE `tenant_id` = ?$1"); $countStmt->execute([$this->tenantId]); $___result = $countStmt->fetchColumn()',
    '/\$this->pdo->query\("SELECT `name`, `stage`, `applied` FROM `employee_relations` WHERE `tenant_id` = \'\{\$this->tenantId\}\'(.*?)"\)/'
        => '$this->pdo->prepare("SELECT `name`, `stage`, `applied` FROM `employee_relations` WHERE `tenant_id` = ?$1"); $stmt_act->execute([$this->tenantId]); $___stmt_act = $stmt_act'
];

// Need a manual rewrite because assigning the prepare to a variable and executing it inline is tricky without helper functions.
