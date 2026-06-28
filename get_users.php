<?php
require 'backend/core/Database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query('SELECT name, email, role FROM users LIMIT 10');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
