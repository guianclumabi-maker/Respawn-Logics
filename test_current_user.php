<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['route'] = 'auth';
$_GET['action'] = 'current_user';
session_start();
$_SESSION['user_id'] = 39;
require 'api.php';
