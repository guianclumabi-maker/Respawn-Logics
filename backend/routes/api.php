<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AtsCandidateController;

// Migrated Routes
Route::any('/ats', function(Request $request) {
    $action = $request->query('action');
    
    if ($request->method() === 'GET' && $action === 'jobs') {
        return app()->call([AtsCandidateController::class, 'jobs']);
    }
    
    // Proxy everything else to the old API
    $baseUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $baseUrl .= "://" . $_SERVER['HTTP_HOST'];
    $basePath = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) ? '/respawn-logics' : '';
    $oldUrl = $baseUrl . $basePath . "/candidates_api.php?" . http_build_query($request->query());
    
    // Forward Cookies for Session Auth
    $cookies = [];
    foreach ($_COOKIE as $name => $value) {
        $cookies[] = "$name=$value";
    }
    $cookieHeader = !empty($cookies) ? "Cookie: " . implode("; ", $cookies) . "\r\n" : "";
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n" . $cookieHeader,
            'method'  => $request->method(),
            'content' => $request->getContent(),
            'ignore_errors' => true
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($oldUrl, false, $context);
    
    return response($result)->header('Content-Type', 'application/json');
});