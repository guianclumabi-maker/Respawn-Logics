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
        
        $data = [
            'title' => 'fix(ui): fix workspace dropdown infinite loop and header collapse layout',
            'body' => 'Fixes two bugs in the Sidebar: 1. The active section (e.g. Workspace) could not be collapsed because a React `useEffect` was caught in an infinite loop due to shifting array references on every render. 2. When the sidebar was minimized, the logo and collapse button were squished side-by-side; they now stack elegantly in a column layout.',
            'head' => 'fix/sidebar-layout-bugs',
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
