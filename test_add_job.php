<?php
require_once __DIR__ . '/bootstrap/app.php';
$_SERVER['REQUEST_METHOD'] = 'POST';

// We need a session, so we'll mock an admin user
$_SESSION['user_id'] = 1;
$_SESSION['tenant_id'] = 1;
// Give ats.create_job permission
$_SESSION['permissions'] = ['ats.create_job'];

$input = [
    'action' => 'add_job',
    'title' => 'Test Job',
    'department' => 'Engineering',
    'location' => 'Remote',
    'employment_type' => 'Full-Time',
    'priority' => 'Normal',
    'salary_min' => 80000,
    'salary_max' => 120000,
    'description' => 'Test description',
    'requirements' => [['text' => 'Req 1', 'type' => 'Mandatory']],
    'external_link' => 'https://example.com'
];

require_once __DIR__ . '/backend/controllers/CandidatesController.php';
$controller = new CandidatesController($pdo);
// the handleRequest will try to read php://input, let's override that in the controller or just call addJob directly
// But addJob is private! We can use Reflection or modify the file.
// Or just let it fail and see if it outputs JSON error?
// To mock php://input we can't easily do it.
// Let's just create a test that does what the controller does.

try {
    $stmt = $pdo->prepare("INSERT INTO `jobs` (`tenant_id`, `title`, `department`, `location`, `employment_type`, `salary_min`, `salary_max`, `description`, `requirements`, `status`, `priority`, `hiring_manager`, `assigned_recruiter`, `external_link`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        1, 
        'Test Job', 
        'Engineering', 
        'Remote', 
        'Full-Time', 
        80000, 
        120000, 
        'Desc', 
        json_encode([]), 
        'Open', 
        'Normal', 
        '', 
        '', 
        ''
    ]);
    echo "Success: " . $pdo->lastInsertId();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
