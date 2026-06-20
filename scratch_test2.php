<?php
require 'bootstrap/app.php';
$stmt = $pdo->query("SELECT id, full_name, email, role, job_title FROM users ORDER BY id ASC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
