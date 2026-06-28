<?php
/**
 * Deploy-time migration runner.
 * Invoked by Railway's preDeployCommand (see railway.json).
 * Runs the comprehensive, idempotent migration set in migrate_all.php
 * so the production schema always matches the code on every deploy.
 */
define('MIGRATION_SAFE', true);
require __DIR__ . '/migrate_all.php';
