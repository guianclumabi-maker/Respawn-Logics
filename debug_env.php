<?php
// Test that proc_open with explicit env actually passes DB_NAME to child
$env = array_merge(getenv(), [
    'APP_ENV' => 'testing',
    'DB_NAME' => 'employee_system_test',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_USER' => 'root',
    'DB_PASS' => '',
]);

// Spawn a child that prints getenv('DB_NAME')
$cmd = 'php -r "echo getenv(\'DB_NAME\');"';
$desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open($cmd, $desc, $pipes, null, $env, ['bypass_shell' => true]);
if (is_resource($proc)) {
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    proc_close($proc);
    echo "Child DB_NAME = '$out'\n";
    if ($err) echo "Stderr: $err\n";
} else {
    echo "proc_open failed\n";
}
