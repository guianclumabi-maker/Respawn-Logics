<?php
require_once __DIR__ . '/bootstrap/app.php';

// Clear session variables and destroy
$_SESSION = [];
session_destroy();

header('Location: ' . url('/login.php'));
exit;
