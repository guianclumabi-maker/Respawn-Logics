<?php
/**
 * API Front Controller
 * This script catches rewritten requests from legacy *_api.php calls
 * and routes them to the appropriate modern Controller.
 */

require_once __DIR__ . '/../bootstrap/app.php';

// Force JSON response
header('Content-Type: application/json');

$route = isset($_GET['route']) ? $_GET['route'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Middleware: Verify Authentication before ANY controller logic runs
if (!isLoggedIn() && $route !== 'auth' && $route !== 'onboarding') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Block all data/action routes while a forced password change is pending
if (isLoggedIn() && !empty($_SESSION['must_change_password']) && $route !== 'auth') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Password change required',
        'must_change_password' => true,
        'redirect' => url('/login.php?step=set_password')
    ]);
    exit;
}

if (empty($route)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No route specified']);
    exit;
}

if ($route === 'auth' && $action === 'csrf') {
    echo json_encode(['success' => true, 'csrf_token' => $_SESSION['csrf_token'] ?? '']);
    exit;
}

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    $requestCsrf = '';
    
    // Check all headers case-insensitively
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'x-csrf-token') {
                $requestCsrf = $value;
                break;
            }
        }
    }
    
    // Fallback to $_SERVER superglobal
    if (empty($requestCsrf) && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $requestCsrf = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

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
    'dashboard' => 'DashboardController',
    'auth' => 'AuthController',
    'core_hr' => 'CoreHRController',
    'ai_companion' => 'AICompanionController',
    'analytics' => 'AnalyticsController',
    'attendance' => 'AttendanceController',
    'benefits' => 'BenefitsController',
    'leaves' => 'LeavesController',
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
    'onboarding' => 'OnboardingController',
    'health' => 'HealthController',
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

try {
    if (file_exists($controllerFile)) {
        require_once $controllerFile;
        global $pdo;
        $controller = new $controllerName($pdo);
        $controller->handleRequest($action);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Controller file for route '{$route}' not found."]);
    }
} catch (PDOException $e) {
    error_log('[API FrontController] Database error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'An internal error occurred. Please try again.'
    ]);
} catch (Throwable $e) {
    error_log('[API FrontController] Internal server error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'An internal error occurred. Please try again.'
    ]);
}
