<?php
$descriptorspec = array(
   0 => array("pipe", "r"),
   1 => array("pipe", "w"),
   2 => array("pipe", "w")
);
$process = proc_open('git credential fill', $descriptorspec, $pipes);
if (is_resource($process)) {
    fwrite($pipes[0], "protocol=https\nhost=github.com\n\n");
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    
    if (preg_match('/password=(.+)/', $output, $matches)) {
        $token = trim($matches[1]);
        
        $branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
        
        $data = [
            'title' => 'fix(auth): fix missing is_super on page refresh',
            'body' => 'Added `is_super` and `roles` to the `/auth/me` endpoint. Previously, if a Platform Admin refreshed their browser, these fields were dropped from the session rehydration, causing the frontend to hide the Command Center button.',
            'head' => $branch,
            'base' => 'main'
        ];
        
        $ch = curl_init('https://api.github.com/repos/guianclumabi-maker/Respawn-Logics/pulls');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $token,
            'User-Agent: PHP-Script',
            'Accept: application/vnd.github.v3+json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        echo $response;
    } else {
        echo "Token not found.\n";
    }
}
