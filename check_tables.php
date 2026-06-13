<?php
$pages = glob(__DIR__ . '/pages/*.php');
$views = glob(__DIR__ . '/pages/views/*.php');
$all_files = array_merge($pages, $views);

$tables = [];
foreach ($all_files as $f) {
    $content = file_get_contents($f);
    preg_match_all('/(?:FROM|INTO|UPDATE)\s+[`]?([a-zA-Z0-9_]+)[`]?/i', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $t) {
            $t = strtolower($t);
            if (!in_array($t, ['where', 'set', 'select'])) {
                $tables[$t] = true;
            }
        }
    }
}
$found_tables = array_keys($tables);

$scripts = glob(__DIR__ . '/database_scripts/*.php');
$created = [];
foreach ($scripts as $f) {
    $content = file_get_contents($f);
    preg_match_all('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?[`]?([a-zA-Z0-9_]+)[`]?/i', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $t) {
            $created[strtolower($t)] = true;
        }
    }
}
$created_tables = array_keys($created);

$missing = array_diff($found_tables, $created_tables);
echo "Missing Tables:\n";
print_r($missing);
