<?php
$dir = 'C:\xampp\htdocs\respawn-logics\backend\controllers';
foreach (glob($dir.'/*.php') as $f) {
    if (basename($f) == 'AuthController.php' || basename($f) == 'SaaSStaffController.php') continue;
    $lines = file($f);
    foreach ($lines as $i => $line) {
        if (preg_match('/(SELECT|UPDATE|DELETE|INSERT\s+INTO).+(FROM|UPDATE|INTO)\s+`?[a-zA-Z0-9_]+`?/i', $line) && !preg_match('/tenant_id/i', $line) && !preg_match('/JOIN/i', $line)) {
            echo basename($f) . ':' . ($i+1) . ' ' . trim($line) . PHP_EOL;
        }
    }
}
