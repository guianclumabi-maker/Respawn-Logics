<?php
require 'bootstrap/app.php';
$stmt = $pdo->query("SELECT id, full_name, role, job_title FROM users LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
