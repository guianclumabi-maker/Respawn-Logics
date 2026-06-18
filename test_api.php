<?php
require 'bootstrap/app.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['route'] = 'candidates';
$_GET['action'] = 'analytics';
// mock logged in
$_SESSION['user_id'] = 1;
$_SESSION['tenant_id'] = 1;

require 'api/index.php';