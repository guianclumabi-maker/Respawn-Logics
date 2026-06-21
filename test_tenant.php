<?php
require_once __DIR__ . '/bootstrap/app.php';
$user = getCurrentUser();
echo "User: \n";
var_export($user);
echo "\nSession tenant: " . ($_SESSION['tenant_id'] ?? 'NULL') . "\n";
