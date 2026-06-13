<?php
// C:\xampp\htdocs\respawn-logics\test_cookies.php
header('Content-Type: application/json');
echo json_encode(['cookies' => $_COOKIE, 'headers' => getallheaders()]);