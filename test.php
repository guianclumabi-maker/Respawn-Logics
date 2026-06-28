<?php
require 'bootstrap/app.php';

global $pdo, $env;

try {
    if ($pdo) {
        $pdo->query("SELECT 1");
        echo "DB Connectivity: pass\n";
    } else {
        echo "DB Connectivity: fail\n";
    }
} catch (Exception $e) {
    echo "DB Connectivity: fail - " . $e->getMessage() . "\n";
}

if (!empty($env['RESEND_API_KEY'])) {
    echo "Email Configuration: pass\n";
} else {
    echo "Email Configuration: fail\n";
}

if (!empty($env['GEMINI_API_KEY'])) {
    echo "Gemini Configuration: pass\n";
} else {
    echo "Gemini Configuration: fail\n";
}
