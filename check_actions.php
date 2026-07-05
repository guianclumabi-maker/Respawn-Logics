<?php
$token = file_exists('git_token.txt') ? trim(file_get_contents('git_token.txt')) : getenv('GITHUB_TOKEN');
if (!$token) die("No token\n");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/guianclumabi-maker/Respawn-Logics/actions/runs?per_page=5');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: token " . $token,
    "Accept: application/vnd.github.v3+json"
]);
$res = json_decode(curl_exec($ch), true);
curl_close($ch);

if (isset($res['workflow_runs'])) {
    foreach ($res['workflow_runs'] as $run) {
        echo "Run #{$run['run_number']}: {$run['name']} - {$run['event']} on {$run['head_branch']} - status: {$run['status']} (conclusion: " . ($run['conclusion'] ?? 'none') . ")\n";
        if (isset($run['triggering_actor']['login'])) {
            echo "  Triggered by: {$run['triggering_actor']['login']}\n";
        }
    }
} else {
    print_r($res);
}
