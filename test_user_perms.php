<?php
require 'bootstrap/app.php';
$u = $pdo->query("SELECT id, full_name, email, role, tenant_id FROM users WHERE full_name='test1' OR email='test1@test1.com'")->fetch();
print_r($u);
$t = $pdo->query("SELECT * FROM tenants WHERE id={$u['tenant_id']}")->fetch();
print_r($t);
$r = $pdo->query("SELECT * FROM roles WHERE name='{$u['role']}' AND tenant_id={$u['tenant_id']}")->fetch();
print_r($r);
