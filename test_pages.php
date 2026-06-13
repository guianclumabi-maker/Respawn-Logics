<?php
$_SERVER['REQUEST_URI'] = '/';
session_start();
$_SESSION['user_email'] = 'admin@respawnlogics.com';
$_SESSION['user_id'] = 1;
$_SESSION['tenant_id'] = 1;
require_once __DIR__ . '/bootstrap/app.php';
$pages = glob(__DIR__ . '/pages/*.php');
foreach ($pages as $p) {
    if (strpos($p, 'dashboard.php') !== false) continue;
    ob_start();
    try {
        include $p;
    } catch (Throwable $e) {
        file_put_contents('php://stderr', "\nCRASH in $p: " . $e->getMessage() . "\n");
    }
    ob_end_clean();
}
echo "Done.\n";
