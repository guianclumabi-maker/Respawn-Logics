<?php
$file = __DIR__ . '/candidates_api.php';
if (!file_exists($file)) {
    echo "Candidates API ATS file not found. Skipping.\n";
    return;
}
$content = file_get_contents($file);

// Add tenant_id init
$content = preg_replace('/\$currentUser = getCurrentUser\(\);/', '$currentUser = getCurrentUser();'."\n".'$tenantId = $currentUser[\'tenant_id\'] ?? $_SESSION[\'tenant_id\'] ?? \'1\';', $content);

// Replace $where = []
$content = preg_replace('/\$where = \[\];/', '$where = ["tenant_id = \'$tenantId\'"];', $content);
$content = str_replace('$where = ["cp.`status` = \'Active\'"];', '$where = ["tenant_id = \'$tenantId\'", "cp.`status` = \'Active\'"];', $content);

// Replace INSERT statements
// INSERT INTO `table` (`col1`, `col2`) VALUES (?, ?)
// to INSERT INTO `table` (`tenant_id`, `col1`, `col2`) VALUES ('$tenantId', ?, ?)
$content = preg_replace('/INSERT( IGNORE)? INTO `([a-z_]+)` \(/', 'INSERT$1 INTO `$2` (`tenant_id`, ', $content);
$content = preg_replace('/VALUES \(/', 'VALUES (\'$tenantId\', ', $content);

file_put_contents($file, $content);
echo "Candidates API ATS queries updated.\n";
