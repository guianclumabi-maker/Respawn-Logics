<?php
require 'bootstrap/app.php';
$stmt = $pdo->query('SHOW COLUMNS FROM leave_requests');
echo implode(', ', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field'));
