<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!class_exists('ValidationException')) {
    class ValidationException extends Exception {}
}

function sanitizeInput($str) {
    if ($str === null) return null;
    if (is_array($str)) {
        return array_map('sanitizeInput', $str);
    }
    return htmlspecialchars(strip_tags(trim((string)$str)), ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/bootstrap/app.php';

// 1. Session & CSRF Verification
if (empty($_SESSION['onboarding_active'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No active onboarding session.']);
    exit;
}

$clientCsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $clientCsrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit;
}

try {
    $tenantId = isset($_SESSION['tenant_id']) ? trim((string)$_SESSION['tenant_id']) : '';
    if (empty($tenantId)) {
        $tenantId = 'default_tenant';
        $_SESSION['tenant_id'] = $tenantId;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new ValidationException('Invalid request method. Only POST is supported.');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new ValidationException('No file uploaded or upload error occurred.');
    }

    $setupMode = $_POST['setup_mode'] ?? 'Quick';
    $rawMapping = $_POST['mapping'] ?? '{}';
    $mapping = json_decode($rawMapping, true);

    if (!is_array($mapping) || empty($mapping)) {
        // Fallback default mapping
        $mapping = [
            'employeeid' => 'employee_id',
            'firstname' => 'first_name',
            'lastname' => 'last_name',
            'workemail' => 'email',
            'department' => 'department',
            'jobtitle' => 'job_title',
            'managerid' => 'manager_id',
            'hiredate' => 'hire_date'
        ];
    }

    $fileTmpPath = $_FILES['file']['tmp_path'] ?? $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];

    // 1. Initialize Batch Audit
    $stmtBatch = $pdo->prepare("INSERT INTO import_batches (tenant_id, filename, setup_mode, created_at) VALUES (?, ?, ?, NOW())");
    $stmtBatch->execute([$tenantId, $fileName, $setupMode]);
    $batchId = $pdo->lastInsertId();

    // 2. Parse CSV
    $rows = [];
    if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ',');
        
        $headerIndices = [];
        foreach ($headers as $index => $header) {
            $normalized = trim(strtolower(str_replace([' ', '_', '(', ')'], '', $header)));
            $headerIndices[$normalized] = $index;
            $headerIndices[$header] = $index; // support raw string matching
        }

        $lineNum = 1;
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $lineNum++;
            
            $row = ['line' => $lineNum];
            
            // Map data using frontend mapping
            // Mapping format: {"CSV Header Name" : "system_field"}
            foreach ($mapping as $csvCol => $sysField) {
                $idx = $headerIndices[$csvCol] ?? $headerIndices[trim(strtolower(str_replace([' ', '_', '(', ')'], '', $csvCol)))] ?? -1;
                $row[$sysField] = ($idx !== -1 && isset($data[$idx])) ? trim($data[$idx]) : '';
            }

            // Defaults if not mapped
            $row['employee_id'] = $row['employee_id'] ?? '';
            $row['first_name'] = $row['first_name'] ?? '';
            $row['last_name'] = $row['last_name'] ?? '';
            $row['email'] = $row['email'] ?? '';
            $row['manager_id'] = $row['manager_id'] ?? '';
            $row['department'] = $row['department'] ?? '';
            $row['job_title'] = $row['job_title'] ?? '';
            $row['hire_date'] = $row['hire_date'] ?? '';
            $row['system_role'] = $row['system_role'] ?? '';
            $row['organization_unit_1'] = $row['organization_unit_1'] ?? null;
            $row['organization_unit_2'] = $row['organization_unit_2'] ?? null;
            $row['organization_unit_3'] = $row['organization_unit_3'] ?? null;
            $row['organization_unit_4'] = $row['organization_unit_4'] ?? null;

            if (empty($row['employee_id']) && empty($row['email']) && empty($row['first_name'])) continue;
            
            $rows[] = $row;
        }
        fclose($handle);
    } else {
        throw new ValidationException('Failed to open uploaded CSV file.');
    }

    if (empty($rows)) {
        throw new ValidationException('The uploaded CSV file is empty.');
    }

    $validationWarnings = [];
    $processedCount = 0;
    $skippedCount = 0;
    $validEmployees = [];
    $employeeIdsInBatch = [];
    
    // 3. Validation
    foreach ($rows as $row) {
        if (empty($row['employee_id'])) {
            $validationWarnings[] = "Row {$row['line']}: Missing Employee ID. Skipping row.";
            $pdo->prepare("INSERT INTO import_batch_errors (batch_id, row_num, error_message) VALUES (?, ?, ?)")
                ->execute([$batchId, $row['line'], "Missing Employee ID"]);
            $skippedCount++;
            continue;
        }
        
        if (empty($row['first_name'])) {
            $validationWarnings[] = "Row {$row['line']}: Missing First Name. Skipping row.";
            $pdo->prepare("INSERT INTO import_batch_errors (batch_id, row_num, error_message) VALUES (?, ?, ?)")
                ->execute([$batchId, $row['line'], "Missing First Name"]);
            $skippedCount++;
            continue;
        }
        
        $email = filter_var($row['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $validationWarnings[] = "Row {$row['line']}: Invalid or missing email. Skipping row.";
            $pdo->prepare("INSERT INTO import_batch_errors (batch_id, row_number, error_message) VALUES (?, ?, ?)")
                ->execute([$batchId, $row['line'], "Invalid or missing email"]);
            $skippedCount++;
            continue;
        }
        
        $row['email'] = $email;
        $validEmployees[] = $row;
        $employeeIdsInBatch[$row['employee_id']] = true;
    }

    if (empty($validEmployees)) {
        throw new ValidationException('No valid rows could be imported from the CSV after validation.');
    }

    $emailsMap = []; 
    $namesMap = [];
    $managerMap = [];
    
    foreach ($validEmployees as $emp) {
        $emailsMap[$emp['employee_id']] = $emp['email'];
        $namesMap[$emp['employee_id']] = $emp['first_name'] . ' ' . $emp['last_name'];
        $managerMap[$emp['employee_id']] = $emp['manager_id'];
    }

    try {
        $stmtLook = $pdo->prepare("SELECT employee_id, work_email, first_name, last_name, manager_id FROM `users` WHERE tenant_id = ?");
        $stmtLook->execute([$tenantId]);
        while ($dbUser = $stmtLook->fetch()) {
            if (!isset($emailsMap[$dbUser['employee_id']])) {
                $emailsMap[$dbUser['employee_id']] = $dbUser['work_email'];
                $managerMap[$dbUser['employee_id']] = $dbUser['manager_id'];
            }
        }
    } catch (PDOException $e) {}

    // Circular loop check
    foreach ($validEmployees as $emp) {
        $visited = [];
        $curr = $emp['employee_id'];
        while (!empty($managerMap[$curr])) {
            if (in_array($curr, $visited)) {
                throw new ValidationException("Circular reporting loop detected involving: " . implode(" -> ", $visited));
            }
            $visited[] = $curr;
            $curr = $managerMap[$curr];
        }
    }

    // 4. Ingestion
    $pdo->beginTransaction();

    $allManagerIds = [];
    foreach ($managerMap as $empId => $mgrId) {
        if (!empty($mgrId)) {
            $allManagerIds[$mgrId] = true;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO `users` 
        (`tenant_id`, `employee_id`, `first_name`, `last_name`, `work_email`, `department`, `job_title`, `manager_id`, `hire_date`, `full_name`, `email`, `role`, `immediate_supervisor`, `department_manager`, `organization_unit_1`, `organization_unit_2`, `organization_unit_3`, `organization_unit_4`, `password_hash`, `profile_image`) 
        VALUES 
        (:tenant_id, :employee_id, :first_name, :last_name, :email, :department, :job_title, :manager_id, :hire_date, :full_name, :email2, :role, :immediate_supervisor, :department_manager, :ou1, :ou2, :ou3, :ou4, '', '')
        ON DUPLICATE KEY UPDATE
        `first_name` = VALUES(`first_name`),
        `last_name` = VALUES(`last_name`),
        `work_email` = VALUES(`work_email`),
        `department` = VALUES(`department`),
        `job_title` = VALUES(`job_title`),
        `manager_id` = VALUES(`manager_id`),
        `hire_date` = VALUES(`hire_date`),
        `full_name` = VALUES(`full_name`),
        `email` = VALUES(`email`),
        `role` = VALUES(`role`),
        `immediate_supervisor` = VALUES(`immediate_supervisor`),
        `department_manager` = VALUES(`department_manager`),
        `organization_unit_1` = VALUES(`organization_unit_1`),
        `organization_unit_2` = VALUES(`organization_unit_2`),
        `organization_unit_3` = VALUES(`organization_unit_3`),
        `organization_unit_4` = VALUES(`organization_unit_4`),
        `profile_image` = VALUES(`profile_image`)");

    $stmtToken = $pdo->prepare("INSERT INTO user_activation_tokens (user_id, token, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())");

    $accounts = [];

    foreach ($validEmployees as $emp) {
        $employeeId = $emp['employee_id'];
        $managerId = $emp['manager_id'];
        
        if (!empty($managerId) && !isset($emailsMap[$managerId])) {
            $managerId = null;
        }

        // Keep hierarchy tracing for organization metadata (but don't use it for security role)
        $immediateSupervisorEmail = !empty($managerId) ? ($emailsMap[$managerId] ?? '') : '';
        
        $deptManagerEmail = '';
        $curr = $employeeId;
        $traceVisited = [];
        while (!empty($managerMap[$curr])) {
            if (in_array($curr, $traceVisited)) break;
            $traceVisited[] = $curr;
            $mId = $managerMap[$curr];
            $pMgr = $managerMap[$mId] ?? null;
            if (empty($pMgr) || empty($managerMap[$pMgr])) {
                $deptManagerEmail = $emailsMap[$mId] ?? '';
                break;
            }
            $curr = $mId;
        }

        $fullName = trim($emp['first_name'] . ' ' . $emp['last_name']);
        
        // Use explicitly mapped system_role, else default to 'employee'
        $role = !empty($emp['system_role']) ? strtolower($emp['system_role']) : 'employee';

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':employee_id' => $employeeId,
            ':first_name' => $emp['first_name'],
            ':last_name' => $emp['last_name'],
            ':email' => $emp['email'],
            ':department' => $emp['department'],
            ':job_title' => $emp['job_title'],
            ':manager_id' => $managerId,
            ':hire_date' => $emp['hire_date'] ?: null,
            ':full_name' => $fullName,
            ':email2' => $emp['email'],
            ':role' => $role,
            ':immediate_supervisor' => $immediateSupervisorEmail,
            ':department_manager' => $deptManagerEmail,
            ':ou1' => $emp['organization_unit_1'],
            ':ou2' => $emp['organization_unit_2'],
            ':ou3' => $emp['organization_unit_3'],
            ':ou4' => $emp['organization_unit_4']
        ]);
        
        // Grab the internal auto-increment ID to link to activation token
        // If ON DUPLICATE KEY UPDATE matched, lastInsertId might be weird, so we fetch it safely.
        $stmtGet = $pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND employee_id = ?");
        $stmtGet->execute([$tenantId, $employeeId]);
        $uid = $stmtGet->fetchColumn();

        $activationToken = bin2hex(random_bytes(16));
        $stmtToken->execute([$uid, $activationToken]);

        $baseUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $baseUrl .= "://" . $_SERVER['HTTP_HOST'];
        $activationLink = $baseUrl . "/respawn-logics/login.php?activation_token=" . $activationToken;

        $accounts[] = [
            'employee_id' => $employeeId,
            'full_name' => $fullName,
            'email' => $emp['email'],
            'role' => $role,
            'activation_link' => $activationLink
        ];
        
        $processedCount++;
    }

    $pdo->prepare("UPDATE import_batches SET total_rows = ?, success_rows = ?, failed_rows = ? WHERE id = ?")
        ->execute([count($rows), $processedCount, $skippedCount, $batchId]);

    $pdo->commit();

    // Auto-suggest Admins for the UI if roles weren't explicitly mapped
    $suggestedAdmins = array_filter($accounts, function($a) {
        $title = $a['job_title'] ?? '';
        return in_array($a['role'], ['admin', 'hr', 'hr manager']) || stripos($title, 'HR') !== false || stripos($title, 'Human Resources') !== false;
    });

    if (empty($suggestedAdmins)) {
        // Fallback: top of hierarchy
        $suggestedAdmins = array_filter($accounts, function($a) use ($managerMap) {
            return empty($managerMap[$a['employee_id']]);
        });
    }

    // Force an admin session for now to prevent lockout during dev
    $_SESSION['logged_in'] = true;
    $_SESSION['role'] = 'admin';
    $_SESSION['tenant_id'] = $tenantId;

    echo json_encode([
        'success' => true,
        'batch_id' => $batchId,
        'processed' => $processedCount,
        'skipped' => $skippedCount,
        'warnings' => $validationWarnings,
        'suggested_admins' => array_values($suggestedAdmins),
        'activation_csv_ready' => true,
        'accounts' => $accounts // Passed to frontend to generate downloadable CSV
    ]);

} catch (ValidationException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database ingestion error: ' . $e->getMessage()]);
}
