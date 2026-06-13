<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
$isVendor = ($user && $user['tenant_id'] === null);

if ($isVendor) {
    require_once __DIR__ . '/views/vendor_dashboard.php';
} else {
    require_once __DIR__ . '/views/client_dashboard.php';
}
