<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require 'bootstrap/app.php';
$_SESSION['user_id'] = 1;
$_SESSION['user_email'] = 'admin@respawnlogics.com';
$_SESSION['tenant_id'] = '1';
$_SESSION['permissions'] = ['analytics.view'];

global $pdo;
$pdo->exec("UPDATE users SET role='Platform_Admin' WHERE id=1");

require 'backend/controllers/AnalyticsController.php';

echo "--- headcount_by_dept ---\n";
$_GET['action'] = 'headcount_by_dept';
$c1 = new AnalyticsController($pdo);
$c1->handleRequest('headcount_by_dept');

echo "\n--- talent_density ---\n";
$_GET['action'] = 'talent_density';
$c2 = new AnalyticsController($pdo);
$c2->handleRequest('talent_density');

echo "\n--- payroll_trend ---\n";
$_GET['action'] = 'payroll_trend';
$c3 = new AnalyticsController($pdo);
$c3->handleRequest('payroll_trend');
