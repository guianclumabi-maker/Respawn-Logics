<?php
require 'bootstrap/app.php';
$_SESSION['roles'] = ['Super_Admin'];
require 'backend/controllers/HealthController.php';
$c = new HealthController($pdo);
$c->handleRequest('check');
