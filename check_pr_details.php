<?php
$token = file_exists('git_token.txt') ? trim(file_get_contents('git_token.txt')) : getenv('GITHUB_TOKEN');
if (!$token) die("No token\n");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/guianclumabi-maker/Respawn-Logics/pulls/92');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: token " . $token,
    "Accept: application/vnd.github.v3+json"
]);
$res = json_decode(curl_exec($ch), true);
curl_close($ch);

print_r([
    'title' => $res['title'],
    'state' => $res['state'],
    'mergeable' => $res['mergeable'] ?? 'null',
    'mergeable_state' => $res['mergeable_state'] ?? 'null',
    'draft' => $res['draft'] ?? 'null',
    'head' => $res['head']['ref'],
    'base' => $res['base']['ref']
]);
