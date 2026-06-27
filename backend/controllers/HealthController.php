<?php
class HealthController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function handleRequest($action, $input = null) {
        if (!hasPermission('settings.manage') && !in_array('Super_Admin', $_SESSION['roles'] ?? [])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Denied']);
            return;
        }

        if ($action === 'check') {
            $this->runChecks();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    }

    private function runChecks() {
        $checks = [];

        // 1. Resume storage
        $resumePath = $_ENV['RESUME_STORAGE_PATH'] ?? '';
        $resumePass = !empty($resumePath) && file_exists($resumePath) && is_writable($resumePath);
        $checks[] = [
            'name' => 'Resume Storage',
            'status' => $resumePass ? 'pass' : 'fail',
            'detail' => $resumePass ? "Writable at $resumePath" : "Not configured, missing, or unwritable"
        ];

        // 2. File storage
        $filePath = $_ENV['FILE_STORAGE_PATH'] ?? '';
        $filePass = !empty($filePath) && file_exists($filePath) && is_writable($filePath);
        $checks[] = [
            'name' => 'File Storage',
            'status' => $filePass ? 'pass' : 'fail',
            'detail' => $filePass ? "Writable at $filePath" : "Not configured, missing, or unwritable"
        ];

        // 3. Email config
        $resendSet = !empty($_ENV['RESEND_API_KEY']);
        $mailFrom = $_ENV['MAIL_FROM'] ?? '';
        $emailPass = $resendSet && !empty($mailFrom);
        $checks[] = [
            'name' => 'Email Configuration',
            'status' => $emailPass ? 'pass' : 'fail',
            'detail' => $emailPass ? "RESEND_API_KEY is set. MAIL_FROM: $mailFrom" : "Missing API Key or MAIL_FROM"
        ];

        // 4. PDF Parser
        $pdfPass = class_exists('\Smalot\PdfParser\Parser');
        $checks[] = [
            'name' => 'PDF Parser',
            'status' => $pdfPass ? 'pass' : 'fail',
            'detail' => $pdfPass ? "Smalot\PdfParser\Parser is loaded" : "Class not found"
        ];

        // 5. Autoloader
        $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
        $autoPass = file_exists($autoloadPath);
        $checks[] = [
            'name' => 'Composer Autoloader',
            'status' => $autoPass ? 'pass' : 'fail',
            'detail' => $autoPass ? "Exists" : "Not found at vendor/autoload.php"
        ];

        // 6. Permissions catalog seeded
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM permissions");
            $count = $stmt->fetchColumn();
            $permPass = $count > 0;
            $checks[] = [
                'name' => 'Permissions Catalog',
                'status' => $permPass ? 'pass' : 'fail',
                'detail' => $permPass ? "$count permissions seeded" : "Empty table"
            ];
        } catch (Exception $e) {
            $checks[] = [
                'name' => 'Permissions Catalog',
                'status' => 'fail',
                'detail' => 'Database error: ' . $e->getMessage()
            ];
        }

        // 7. RBAC schema
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'org_units'");
            $hasOrg = $stmt->rowCount() > 0;
            $stmt2 = $this->pdo->query("SHOW COLUMNS FROM user_roles LIKE 'scope'");
            $hasScope = $stmt2->rowCount() > 0;
            $rbacPass = $hasOrg && $hasScope;
            $checks[] = [
                'name' => 'RBAC Schema',
                'status' => $rbacPass ? 'pass' : 'fail',
                'detail' => $rbacPass ? "org_units and user_roles.scope exist" : "Missing org_units or scope column"
            ];
        } catch (Exception $e) {
            $checks[] = [
                'name' => 'RBAC Schema',
                'status' => 'fail',
                'detail' => 'Database error'
            ];
        }

        // 8. ATS indexes
        try {
            $stmt = $this->pdo->query("SELECT COUNT(1) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'candidate_applications' AND index_name = 'idx_ca_tenant_candidate_job'");
            $hasIdx = $stmt->fetchColumn() > 0;
            $checks[] = [
                'name' => 'ATS Indexes',
                'status' => $hasIdx ? 'pass' : 'fail',
                'detail' => $hasIdx ? "idx_ca_tenant_candidate_job exists" : "Missing M2 ATS indexes"
            ];
        } catch (Exception $e) {
            $checks[] = [
                'name' => 'ATS Indexes',
                'status' => 'fail',
                'detail' => 'Database error'
            ];
        }

        // 9. DB connectivity
        try {
            $stmt = $this->pdo->query("SELECT 1");
            $dbPass = $stmt && $stmt->fetchColumn() == 1;
            $checks[] = [
                'name' => 'DB Connectivity',
                'status' => $dbPass ? 'pass' : 'fail',
                'detail' => $dbPass ? "SELECT 1 succeeded" : "Failed"
            ];
        } catch (Exception $e) {
            $checks[] = [
                'name' => 'DB Connectivity',
                'status' => 'fail',
                'detail' => 'Connection error: ' . $e->getMessage()
            ];
        }

        echo json_encode(['success' => true, 'checks' => $checks]);
    }
}
