<?php
$file = __DIR__ . '/index.php';
$content = file_get_contents($file);

// Find DEMOS section
$startMarker = '<!-- DEMOS WORKFLOW SECTION -->';
$endMarker = '</section>' . "\n\n" . '<!-- SECTION 1: JOURNEY -->';

$startPos = strpos($content, $startMarker);
if ($startPos === false) {
    die("Start marker not found.\n");
}

// Find the end of the DEMOS section
$endPos = strpos($content, '<!-- SECTION 1: JOURNEY -->', $startPos);
if ($endPos === false) {
    die("End marker not found.\n");
}

$demosSection = substr($content, $startPos, $endPos - $startPos);

// Remove the DEMOS section from its original place
$content = str_replace($demosSection, '', $content);

// Find the target insertion point: AFTER OVERVIEW (4 PILLARS) section
$insertAfterMarker = '<!-- SECTION 4: WHY TEAMS CHOOSE RESPAWN -->';
$insertPos = strpos($content, $insertAfterMarker);

if ($insertPos === false) {
    die("Insertion marker not found.\n");
}

// Insert the DEMOS section right before SECTION 4
$newContent = substr_replace($content, $demosSection, $insertPos, 0);

file_put_contents($file, $newContent);
echo "Successfully moved DEMOS section.\n";
