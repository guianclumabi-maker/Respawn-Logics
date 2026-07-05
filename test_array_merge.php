<?php
$_ENV['DB_NAME'] = 'test';
putenv('DB_NAME=test');
$localEnv = ['DB_NAME' => 'prod'];
$env = array_merge($localEnv, getenv(), $_ENV);
echo $env['DB_NAME'];
