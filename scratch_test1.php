<?php
require 'bootstrap/app.php';
$stmt = $pdo->query("SELECT id, full_name, role, job_title FROM users WHERE email='test1@test.com' OR full_name LIKE '%test1%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
