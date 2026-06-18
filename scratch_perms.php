<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
$perms = [];
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $content = file_get_contents($file);
        if (preg_match_all("/hasPermission\('([^']+)'\)/", $content, $matches)) {
            foreach ($matches[1] as $m) $perms[$m] = true;
        }
    }
}
$defined = require 'config/permissions.php';
$flat_defined = [];
foreach ($defined as $group => $list) {
    foreach ($list as $p) $flat_defined[$p] = true;
}

echo "DEFINED IN CONFIG:\n";
print_r(array_keys($flat_defined));

echo "\nUSED IN CODE:\n";
print_r(array_keys($perms));

echo "\nUSED BUT NOT DEFINED:\n";
$missing = array_diff(array_keys($perms), array_keys($flat_defined));
print_r(array_values($missing));
