<?php
// Temporary endpoint to run database migrations on Railway

define('MIGRATION_SAFE', true);

// Add a simple security layer so bots don't hit this randomly
if (!isset($_GET['token']) || $_GET['token'] !== 'respawn123') {
    http_response_code(403);
    die('Forbidden. Please provide the correct token.');
}

header('Content-Type: text/plain');
echo "Starting web-triggered migration...\n";
echo "====================================\n\n";

try {
    require_once __DIR__ . '/database_scripts/migrate_all.php';
} catch (Throwable $e) {
    echo "\n\nFATAL ERROR during migration: " . $e->getMessage();
}

echo "\n\nMigration execution completed.";
