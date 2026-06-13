<?php
$dir = __DIR__ . '/backend/controllers';
$files = glob($dir . '/*.php');
$issues = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    $inQuery = false;
    $queryLines = [];
    
    foreach ($lines as $i => $line) {
        $upperLine = strtoupper($line);
        if (strpos($upperLine, 'SELECT ') !== false || strpos($upperLine, 'UPDATE ') !== false || strpos($upperLine, 'DELETE ') !== false || strpos($upperLine, 'INSERT INTO') !== false) {
            $inQuery = true;
            $queryLines = [];
        }
        
        if ($inQuery) {
            $queryLines[] = $line;
            if (strpos($upperLine, '")') !== false || strpos($upperLine, "')") !== false || strpos($upperLine, '";') !== false || strpos($upperLine, "';") !== false) {
                $inQuery = false;
                $queryText = strtoupper(implode(" ", $queryLines));
                
                // If the query touches tables that don't need tenant_id, skip.
                if (strpos($queryText, 'FROM TENANTS') !== false || strpos($queryText, 'INTO TENANTS') !== false) continue;
                if (strpos($queryText, 'FROM PRECEDENTS') !== false) continue;
                
                if (strpos($queryText, 'TENANT_ID') === false && strpos($queryText, 'WHERE ID =') === false) {
                    $issues[] = basename($file) . ":" . ($i + 1) . " -> " . implode(" ", $queryLines);
                }
            }
        }
    }
}

if (empty($issues)) {
    echo "No tenant_id leaks found.\n";
} else {
    echo implode("\n", $issues);
}
