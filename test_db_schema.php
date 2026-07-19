<?php
require 'backend/config/database.php';

try {
    $stmt = $pdo->query("DESCRIBE tenants");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($result);
} catch (Exception $e) {
    echo $e->getMessage();
}
