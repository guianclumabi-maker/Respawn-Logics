<?php
define('MIGRATION_SAFE', true);
$_ENV['DB_HOST'] = 'reseau.proxy.rlwy.net';
$_ENV['DB_PORT'] = '19932';
$_ENV['DB_NAME'] = 'railway';
$_ENV['DB_USER'] = 'root';
$_ENV['DB_PASS'] = 'CMLUchTlMGhDxVnKwWpnQcmzAOqqnsKg';

// Inject environment variables directly so bootstrap picks them up
putenv("DB_HOST=reseau.proxy.rlwy.net");
putenv("DB_PORT=19932");
putenv("DB_NAME=railway");
putenv("DB_USER=root");
putenv("DB_PASS=CMLUchTlMGhDxVnKwWpnQcmzAOqqnsKg");

require_once __DIR__ . '/database_scripts/migrate_all.php';
