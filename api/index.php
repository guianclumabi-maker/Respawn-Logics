<?php
/**
 * API Front Controller
 * This script catches rewritten requests from legacy *_api.php calls
 * and routes them to the appropriate modern Controller.
 */

require_once __DIR__ . '/../bootstrap/app.php';

// Force JSON response
header('Content-Type: application/json');

// Middleware: Verify Authentication before ANY controller logic runs
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$route = $_GET['route'] ?? '';
$action = $_GET['action'] ?? '';

if (empty($route)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No route specified']);
    exit;
}

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    $headers = getallheaders();
    $requestCsrf = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    
    // Also check JSON body if not in headers
    if (empty($requestCsrf)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $requestCsrf = $input['csrf_token'] ?? '';
    }

    if (empty($requestCsrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $requestCsrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF token mismatch']);
        exit;
    }
}

// Rate Limiting (100 requests per 60 seconds per user)
$now = time();
if (!isset($_SESSION['rate_limit'])) {
    $_SESSION['rate_limit'] = ['count' => 0, 'start' => $now];
}

if ($now - $_SESSION['rate_limit']['start'] > 60) {
    // Reset window
    $_SESSION['rate_limit'] = ['count' => 1, 'start' => $now];
} else {
    $_SESSION['rate_limit']['count']++;
    if ($_SESSION['rate_limit']['count'] > 100) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too Many Requests']);
        exit;
    }
}

// Map routes to Controller classes
$controllers = [
    'core_hr' => 'CoreHRController',
    'ai_companion' => 'AICompanionController',
    'analytics' => 'AnalyticsController',
    'attendance' => 'AttendanceController',
    'benefits' => 'BenefitsController',
    'candidates' => 'CandidatesController',
    'elr' => 'ELRController',
    'employee_relations' => 'EmployeeRelationsController',
    'esm' => 'ESMController',
    'expenses' => 'ExpensesController',
    'iam' => 'IAMController',
    'payroll_engine' => 'PayrollController',
    'performance' => 'PerformanceController',
    'platform_support' => 'PlatformSupportController',
    'esm_support' => 'ESMSupportController',
    'saas_staff' => 'SaaSStaffController',
    'notifications' => 'NotificationController',
    'shifts' => 'ShiftController',
    'announcements' => 'AnnouncementsController',
    'surveys' => 'SurveyController',
    'audit' => 'AuditController',
];

if (!array_key_exists($route, $controllers)) {
    // Fallback: If route isn't mapped yet, return 404. 
    // Wait, if it isn't mapped, the user will hit 404 because the file was deleted.
    // That's correct. We only delete files we have migrated.
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => "API route '{$route}' not found or not yet migrated."]);
    exit;
}

$controllerName = $controllers[$route];
$controllerFile = __DIR__ . "/../backend/controllers/{$controllerName}.php";

if (!file_exists($controllerFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Controller file is missing']);
    exit;
}

require_once $controllerFile;

// Instantiate the controller, passing global dependencies (Dependency Injection)
global $pdo;
$controller = new $controllerName($pdo);

// Call the handle method which will process the $action
$controller->handleRequest($action);
