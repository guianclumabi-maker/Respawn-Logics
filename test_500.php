<?php
session_start();
$_SESSION['user_email'] = 'Test1@test1.com';
$_SESSION['tenant_id'] = 'tenant_cbfd25887f';
$_SESSION['user_name'] = 'Test 1';
require_once 'bootstrap/app.php';
require_once 'backend/controllers/CandidatesController.php';
global $pdo;
$controller = new CandidatesController($pdo);
$controller->handleRequest('current_user');
