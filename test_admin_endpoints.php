<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once 'bootstrap/app.php';
$_SESSION['user_id'] = 1;
$_SESSION['user_email'] = 'admin@respawnlogics.com';
$_SESSION['tenant_id'] = '1';
$_SESSION['permissions'] = [
    'analytics.view', 'users.manage', 'shifts.manage', 'ats.view',
    'payroll.manage', 'compensation.manage', 'performance.manage',
    'expenses.manage', 'benefits.manage', 'elr.view', 'users.view',
    'settings.manage'
];

global $pdo;
$pdo->exec("UPDATE users SET role='Super_Admin' WHERE id=1");

$routes = [
    ['route' => 'analytics', 'action' => 'dashboard'],
    ['route' => 'core_hr', 'action' => 'directory'],
    ['route' => 'shifts', 'action' => 'list_shifts'],
    ['route' => 'payroll_engine', 'action' => 'runs'],
    ['route' => 'compensation', 'action' => 'list_bands'],
    ['route' => 'performance', 'action' => 'list_reviews'],
    ['route' => 'expenses', 'action' => 'list'],
    ['route' => 'benefits', 'action' => 'list_plans'],
    ['route' => 'iam', 'action' => 'users'],
    ['route' => 'iam', 'action' => 'org_units'],
    ['route' => 'iam', 'action' => 'roles'],
    ['route' => 'iam', 'action' => 'settings'],
];

foreach ($routes as $r) {
    echo "--- Testing {$r['route']} -> {$r['action']} ---\n";
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['route'] = $r['route'];
    $_GET['action'] = $r['action'];
    ob_start();
    
    // We must isolate the required files, because api/index.php calls exit;
    // We can just call the controllers directly instead.
    
    $controllers = [
        'dashboard' => 'DashboardController',
        'auth' => 'AuthController',
        'core_hr' => 'CoreHRController',
        'analytics' => 'AnalyticsController',
        'benefits' => 'BenefitsController',
        'iam' => 'IAMController',
        'payroll_engine' => 'PayrollController',
        'performance' => 'PerformanceController',
        'shifts' => 'ShiftController',
        'expenses' => 'ExpensesController',
        'compensation' => 'CompensationController'
    ];
    
    if (isset($controllers[$r['route']])) {
        $controllerName = $controllers[$r['route']];
        $controllerFile = __DIR__ . "/backend/controllers/{$controllerName}.php";
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            $c = new $controllerName($pdo);
            try {
                $c->handleRequest($r['action']);
            } catch (Exception $e) {
                echo "Exception: " . $e->getMessage() . "\n";
            }
        } else {
            echo "Controller file not found: $controllerFile\n";
        }
    }
    
    $out = ob_get_clean();
    $code = http_response_code();
    echo "HTTP CODE: $code\n";
    echo substr($out, 0, 100) . "...\n\n";
}
