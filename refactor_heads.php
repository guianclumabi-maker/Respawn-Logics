<?php
$pagesDir = __DIR__ . '/pages';
$viewsDir = __DIR__ . '/pages/views';

$files = array_merge(
    glob($pagesDir . '/*.php'),
    glob($viewsDir . '/*.php')
);

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Check if the file has a head block
    if (preg_match('/<head>(.*?)<\/head>/is', $content, $headMatch)) {
        $headContent = $headMatch[1];
        
        // Extract title
        $title = 'Respawn Logics';
        if (preg_match('/<title>(.*?)<\/title>/is', $headContent, $titleMatch)) {
            $title = trim($titleMatch[1]);
        }
        
        // Extract style blocks
        $styleBlocks = '';
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $headContent, $styleMatches)) {
            foreach ($styleMatches[0] as $styleBlock) {
                $styleBlocks .= "\n    " . trim($styleBlock) . "\n";
            }
        }
        
        // Generate new head inclusion
        $relativeIncludesPath = basename(dirname($file)) === 'views' ? '/../../includes/head.php' : '/../includes/head.php';
        
        $newHead = "<?php \$page_title = '" . addslashes($title) . "'; ?>\n";
        $newHead .= "<?php include __DIR__ . '" . $relativeIncludesPath . "'; ?>\n";
        
        if (!empty($styleBlocks)) {
            $newHead .= $styleBlocks . "\n";
        }
        
        // Replace everything from <!DOCTYPE html> to </head>
        // Wait, DOCTYPE html is not inside <head>. Let's just replace the <head>...</head> block.
        // Wait! We also want to replace <!DOCTYPE html><html lang="en"><head>...
        
        // To be safe, we will just replace the <head>...</head> with the new code
        // BUT head.php outputs <!DOCTYPE html><html lang="en"><head>...</head> !
        // So we need to remove the existing DOCTYPE and <html> opening tag if possible.
        
        $pattern = '/<!DOCTYPE html>\s*<html[^>]*>\s*<head>.*?<\/head>/is';
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $newHead, $content);
            file_put_contents($file, $content);
            echo "Refactored: " . basename($file) . "\n";
        } else {
            echo "Could not match DOCTYPE pattern in: " . basename($file) . "\n";
        }
    } else {
        echo "No head found in: " . basename($file) . "\n";
    }
}
?>
