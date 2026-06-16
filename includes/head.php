<?php
$page_title = $page_title ?? 'Respawn Logics';
$userTheme = $_SESSION['theme_preference'] ?? 'light';
if ($userTheme === 'system') $userTheme = 'light'; // Fallback to light if system
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($userTheme) ?>">
<head>
    <script>
        // Option to cache in localStorage for instant load before CSS parsing
        (function() {
            var sessionTheme = "<?= htmlspecialchars($userTheme) ?>";
            var localTheme = localStorage.getItem('theme');
            // If session says light (default) but local storage has dark, prioritize local storage as a fallback cache
            if (!<?= isset($_SESSION['theme_preference']) ? 'true' : 'false' ?> && localTheme) {
                document.documentElement.setAttribute('data-theme', localTheme);
            } else if (localTheme !== sessionTheme) {
                localStorage.setItem('theme', sessionTheme);
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <!-- Unified Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Unified FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Core Application CSS -->
    <link rel="stylesheet" href="<?= url('/assets/css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/app-nav.css') ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= url('/assets/favicon.svg') ?>">
</head>
