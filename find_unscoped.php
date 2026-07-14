<?php
$dir = new RecursiveDirectoryIterator(__DIR__ . '/backend/controllers');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$suspicious = [];
foreach ($files as $file) {
    $path = $file[0];
    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        if (preg_match('/(?:SELECT|UPDATE|DELETE|INSERT).*(?:WHERE|AND)\s+.*?id\s*=\s*(?:\?|:[a-zA-Z0-9_]+)/i', $line)) {
            // Check if tenant_id is also in the line or surrounding lines
            $context = implode(" ", array_slice($lines, max(0, $i - 2), 5));
            if (stripos($context, 'tenant_id') === false) {
                $suspicious[] = basename($path) . ":" . ($i + 1) . " -> " . trim($line);
            }
        }
    }
}

echo "Suspicious queries missing tenant_id:\n";
foreach ($suspicious as $s) {
    echo $s . "\n";
}
