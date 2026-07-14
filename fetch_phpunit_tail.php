<?php
$descriptorspec = [0 => ["pipe","r"], 1 => ["pipe","w"], 2 => ["pipe","w"]];
$process = proc_open('git credential fill', $descriptorspec, $pipes);
fwrite($pipes[0], "protocol=https\nhost=github.com\n\n");
fclose($pipes[0]);
$output = stream_get_contents($pipes[1]);
fclose($pipes[1]); fclose($pipes[2]);
proc_close($process);
if (!preg_match('/password=(.+)/', $output, $m)) { die("Token not found\n"); }
$token = trim($m[1]);
$repo = 'guianclumabi-maker/Respawn-Logics';
$headers = ['Authorization: token '.$token,'User-Agent: PHP-Script','Accept: application/vnd.github.v3+json'];

$ch = curl_init("https://api.github.com/repos/$repo/actions/runs?branch=ci/pipeline&per_page=2");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
$runs = json_decode(curl_exec($ch), true);
curl_close($ch);

foreach ($runs['workflow_runs'] ?? [] as $run) {
    $ch = curl_init("https://api.github.com/repos/$repo/actions/runs/{$run['id']}/jobs");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
    $jobs = json_decode(curl_exec($ch), true);
    curl_close($ch);

    foreach ($jobs['jobs'] ?? [] as $job) {
        if ($job['conclusion'] !== 'failure') continue;
        $ch = curl_init("https://api.github.com/repos/$repo/actions/jobs/{$job['id']}/logs");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => true]);
        $logs = curl_exec($ch);
        curl_close($ch);

        $lines = explode("\n", $logs);
        // Print LAST 80 lines of the PHPUnit step (after the migrations)
        $phpunitLines = [];
        $inPhpUnit = false;
        foreach ($lines as $line) {
            $plain = preg_replace('/\x1B\[[0-9;]*m|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z /', '', $line);
            if (str_contains($plain, 'Run phpunit') || str_contains($plain, '##[group]Run phpunit')) {
                $inPhpUnit = true;
            }
            if ($inPhpUnit) {
                $phpunitLines[] = $plain;
            }
        }
        // Print last 80 of those lines
        echo implode("\n", array_slice($phpunitLines, -80));
    }
    if (!empty($jobs['jobs'])) break;
}
