<?php
$files = [
    'backend/controllers/CoreHRController.php',
    'backend/controllers/ELRController.php',
    'backend/controllers/EmployeeRelationsController.php',
    'backend/controllers/ESMController.php',
    'backend/controllers/IAMController.php',
    'backend/controllers/OnboardingController.php',
    'backend/controllers/SaaSStaffController.php'
];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    $newLines = [];
    $changed = false;
    
    foreach ($lines as $line) {
        if (strpos($line, "echo json_encode(['success' => false, 'error' => 'Database error: ' . \$e->getMessage()]);") !== false) {
            $newLines[] = str_replace(
                "echo json_encode(['success' => false, 'error' => 'Database error: ' . \$e->getMessage()]);",
                "error_log('[' . __CLASS__ . '] ' . \$e->getMessage() . ' in ' . \$e->getFile() . ':' . \$e->getLine());\n            http_response_code(500);\n            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);",
                $line
            );
            $changed = true;
        } elseif (strpos($line, "echo json_encode(['success' => false, 'error' => \$e->getMessage()]);") !== false) {
            $newLines[] = str_replace(
                "echo json_encode(['success' => false, 'error' => \$e->getMessage()]);",
                "error_log('[' . __CLASS__ . '] ' . \$e->getMessage() . ' in ' . \$e->getFile() . ':' . \$e->getLine());\n            http_response_code(500);\n            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);",
                $line
            );
            $changed = true;
        } elseif (strpos($line, "\$this->jsonResponse(['success' => false, 'error' => \$e->getMessage()], 400);") !== false) {
            $newLines[] = str_replace(
                "\$this->jsonResponse(['success' => false, 'error' => \$e->getMessage()], 400);",
                "error_log('[' . __CLASS__ . '] ' . \$e->getMessage() . ' in ' . \$e->getFile() . ':' . \$e->getLine());\n            \$this->jsonResponse(['success' => false, 'error' => 'An internal error occurred. Please try again.'], 400);",
                $line
            );
            $changed = true;
        } elseif (strpos($line, "\$this->jsonResponse(['error' => 'Database update error: ' . \$e->getMessage()], 500);") !== false) {
            $newLines[] = str_replace(
                "\$this->jsonResponse(['error' => 'Database update error: ' . \$e->getMessage()], 500);",
                "error_log('[' . __CLASS__ . '] ' . \$e->getMessage() . ' in ' . \$e->getFile() . ':' . \$e->getLine());\n            \$this->jsonResponse(['success' => false, 'error' => 'An internal error occurred. Please try again.'], 500);",
                $line
            );
            $changed = true;
        } else {
            $newLines[] = $line;
        }
    }
    
    if ($changed) {
        file_put_contents($file, implode("\n", $newLines));
        echo "Updated $file\n";
    }
}
