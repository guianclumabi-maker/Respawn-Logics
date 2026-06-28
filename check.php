<?php
require 'bootstrap/app.php';
$db = \App\Core\Database::getInstance()->getConnection();
$stmt = $db->prepare('SELECT id, role, permissions FROM users WHERE email=?');
$stmt->execute(['test1@test1.com']);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
