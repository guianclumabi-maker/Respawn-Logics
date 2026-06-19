<?php
function refactor_file($path) {
    $content = file_get_contents($path);
    $orig = $content;

    // Fix $_POST and $_GET
    $content = preg_replace_callback('/(isset\(\s*|empty\(\s*)?\$_(POST|GET)\[[\'"]([^\'"]+)[\'"]\](\s*\?\?)?/', function($m) {
        if (!empty($m[1]) || !empty($m[4])) return $m[0];
        return "\$_" . $m[2] . "['" . $m[3] . "'] ?? null";
    }, $content);

    // Fix syntax errors (just rudimentary check)
    // Add try/catch roughly around methods using pdo but lacking try
    // We will do a simple line-by-line replacement for execute() if not in try
    // Actually, just wrapping the whole method body is hard in regex.
    // Instead, I will write the updated content back.
    if ($content !== $orig) {
        file_put_contents($path, $content);
        echo "Updated $path\n";
    }
}

$files = glob(__DIR__ . '/backend/controllers/*.php');
$files = array_merge($files, glob(__DIR__ . '/api/*.php'));
foreach ($files as $f) { refactor_file($f); }
echo "Finished fixing POST/GET.\n";
?>