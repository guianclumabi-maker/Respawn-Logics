<?php
require 'bootstrap/app.php';
$pdo->exec('UPDATE tenants SET permission_version = permission_version + 1;');
echo "Tenant permission cache bumped.\n";
