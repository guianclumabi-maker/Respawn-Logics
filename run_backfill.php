<?php
// Temporary script to run the encryption backfill from the browser.
// Delete this file after running it once.
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Encryption Backfill Output</h1>";
echo "<pre style='background: #1e1e1e; color: #00ff00; padding: 20px; border-radius: 5px; font-family: monospace;'>";

// Run the script directly
require_once __DIR__ . '/database_scripts/backfill_encrypt.php';

echo "</pre>";
echo "<p style='color: red; font-weight: bold;'>Done! Please delete this file (run_backfill.php) from your codebase now.</p>";
