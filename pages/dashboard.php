<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
$isVendor = ($user && (empty($user['tenant_id']) || $user['tenant_id'] == '1'));

if ($isVendor) {
    require_once __DIR__ . '/views/vendor_dashboard.php';
} else {
    require_once __DIR__ . '/views/client_dashboard.php';
}
