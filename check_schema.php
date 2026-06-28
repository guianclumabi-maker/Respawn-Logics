<?php
require 'bootstrap/app.php';
$stmt = $pdo->query("SHOW COLUMNS FROM `tenants`");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
