<?php
if (($_GET['key'] ?? '') !== 'respawn99') {
    http_response_code(403);
    die('Forbidden');
}
define('MIGRATION_SAFE', true);
require_once __DIR__ . '/database_scripts/migrate_all.php';
