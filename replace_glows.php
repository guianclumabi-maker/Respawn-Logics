<?php
$pagesDir = __DIR__ . '/pages';
$viewsDir = __DIR__ . '/pages/views';

$files = array_merge(
    glob($pagesDir . '/*.php'),
    glob($viewsDir . '/*.php')
);

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    $modified = false;
    if (strpos($content, 'glow-purple') !== false) {
        $content = str_replace('glow-purple', 'glow-green', $content);
        $modified = true;
    }
    if (strpos($content, 'glow-pink') !== false) {
        $content = str_replace('glow-pink', 'glow-cyan', $content);
        $modified = true;
    }
    
    if ($modified) {
        file_put_contents($file, $content);
        echo "Updated glows in: " . basename($file) . "\n";
    }
}
?>
