<?php

class OnboardingController {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function handleRequest($action) {
        switch ($action) {
            case 'import':
                $this->import();
                break;
            case 'update_roles':
                $this->updateRoles();
                break;
            default:
                $this->jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
        }
    }

    private function jsonResponse($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    public function import() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
        }

        // 1. Permission Verification
        if (!hasPermission('users.create') && empty($_SESSION['is_super'])) {
            $this->jsonResponse(['error' => 'Denied'], 403);
        }


        try {
            $tenantId = isset($_SESSION['tenant_id']) ? trim((string)$_SESSION['tenant_id']) : '';
            if (empty($tenantId)) {
                throw new Exception('Tenant ID is missing. Invalid or expired onboarding session.');
            }

            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No file uploaded or upload error occurred.');
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
            $stmtBatch = $this->pdo->prepare("INSERT INTO import_batches (tenant_id, filename, setup_mode, created_at) VALUES (?, ?, ?, NOW())");
            $stmtBatch->execute([$tenantId, $fileName, $setupMode]);
            $batchId = $this->pdo->lastInsertId();

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
                throw new Exception('Failed to open uploaded CSV file.');
            }

            if (empty($rows)) {
                throw new Exception('The uploaded CSV file is empty.');
            }

            $validationWarnings = [];
            $processedCount = 0;
            $skippedCount = 0;
            $validEmployees = [];
            
            // 3. Validation
            foreach ($rows as $row) {
                if (empty($row['employee_id'])) {
                    $validationWarnings[] = "Row {$row['line']}: Missing Employee ID. Skipping row.";
                    $this->pdo->prepare("INSERT INTO import_batch_errors (batch_id, row_num, error_message) VALUES (?, ?, ?)")
                        ->execute([$batchId, $row['line'], "Missing Employee ID"]);
                    $skippedCount++;
                    continue;
                }
                
                if (empty($row['first_name'])) {
                    $validationWarnings[] = "Row {$row['line']}: Missing First Name. Skipping row.";
                    $this->pdo->prepare("INSERT INTO import_batch_errors (batch_id, row_num, error_message) VALUES (?, ?, ?)")
                        ->execute([$batchId, $row['line'], "Missing First Name"]);
                    $skippedCount++;
                    continue;
                }
                
                $email = filter_var($row['email'], FILTER_VALIDATE_EMAIL);
                if (!$email) {
                    $validationWarnings[] = "Row {$row['line']}: Invalid or missing email. Skipping row.";
                    $this->pdo->prepare("INSERT INTO import_batch_errors (batch_id, row_num, error_message) VALUES (?, ?, ?)")
                        ->execute([$batchId, $row['line'], "Invalid or missing email"]);
                    $skippedCount++;
                    continue;
                }
                
                $row['email'] = $email;
                $validEmployees[] = $row;
            }

            if (empty($validEmployees)) {
                throw new Exception('No valid rows could be imported from the CSV after validation.');
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
                $stmtLook = $this->pdo->prepare("SELECT employee_id, work_email, first_name, last_name, manager_id FROM `users` WHERE tenant_id = ?");
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
                        throw new Exception("Circular reporting loop detected involving: " . implode(" -> ", $visited));
                    }
                    $visited[] = $curr;
                    $curr = $managerMap[$curr];
                }
            }

            // 4. Ingestion
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("INSERT INTO `users` 
                (`tenant_id`, `employee_id`, `first_name`, `last_name`, `work_email`, `department`, `job_title`, `manager_supervisor_id`, `hire_date`, `full_name`, `email`, `role`, `immediate_supervisor`, `department_manager`, `organization_unit_1`, `organization_unit_2`, `organization_unit_3`, `organization_unit_4`, `password_hash`, `profile_image`, `manager_id`) 
                VALUES 
                (:tenant_id, :employee_id, :first_name, :last_name, :email, :department, :job_title, :manager_supervisor_id, :hire_date, :full_name, :email2, :role, :immediate_supervisor, :department_manager, :ou1, :ou2, :ou3, :ou4, '', '', NULL)
                ON DUPLICATE KEY UPDATE
                `first_name` = VALUES(`first_name`),
                `last_name` = VALUES(`last_name`),
                `work_email` = VALUES(`work_email`),
                `department` = VALUES(`department`),
                `job_title` = VALUES(`job_title`),
                `manager_supervisor_id` = VALUES(`manager_supervisor_id`),
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
                `profile_image` = VALUES(`profile_image`),
                `manager_id` = NULL");

            $stmtToken = $this->pdo->prepare("INSERT INTO user_activation_tokens (user_id, token, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())");

            $accounts = [];

            foreach ($validEmployees as $emp) {
                $employeeId = $emp['employee_id'];
                $managerId = $emp['manager_id'];
                
                if (!empty($managerId) && !isset($emailsMap[$managerId])) {
                    $managerId = null;
                }

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
                $role = !empty($emp['system_role']) ? strtolower($emp['system_role']) : 'employee';

                $stmt->execute([
                    ':tenant_id' => $tenantId,
                    ':employee_id' => $employeeId,
                    ':first_name' => $emp['first_name'],
                    ':last_name' => $emp['last_name'],
                    ':email' => $emp['email'],
                    ':department' => $emp['department'],
                    ':job_title' => $emp['job_title'],
                    ':manager_supervisor_id' => !empty($managerId) ? $managerId : null,
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
                
                $stmtGet = $this->pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND employee_id = ?");
                $stmtGet->execute([$tenantId, $employeeId]);
                $uid = $stmtGet->fetchColumn();

                $activationToken = bin2hex(random_bytes(16));
                $stmtToken->execute([$uid, $activationToken]);

                $baseUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $baseUrl .= "://" . $_SERVER['HTTP_HOST'];
                $basePath = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) ? '/respawn-logics' : '';
                $activationLink = $baseUrl . $basePath . "/login.php?activation_token=" . $activationToken;

                $accounts[] = [
                    'employee_id' => $employeeId,
                    'full_name' => $fullName,
                    'email' => $emp['email'],
                    'role' => $role,
                    'activation_link' => $activationLink
                ];
                
                $processedCount++;
            }

            $this->pdo->prepare("UPDATE import_batches SET total_rows = ?, success_rows = ?, failed_rows = ? WHERE id = ?")
                ->execute([count($rows), $processedCount, $skippedCount, $batchId]);

            $this->pdo->commit();

            // 5. Post-commit: resolve manager_id integers using string manager_supervisor_id
            try {
                $this->pdo->prepare("
                    UPDATE users u
                    JOIN users mgr ON u.manager_supervisor_id = mgr.employee_id AND u.tenant_id = mgr.tenant_id
                    SET u.manager_id = mgr.id
                    WHERE u.tenant_id = ?
                ")->execute([$tenantId]);
            } catch (PDOException $ex) {
                error_log('[OnboardingController] Failed resolving manager_ids: ' . $ex->getMessage());
            }

            $suggestedAdmins = array_filter($accounts, function($a) {
                $title = $a['job_title'] ?? '';
                return in_array($a['role'], ['admin', 'hr', 'hr manager']) || stripos($title, 'HR') !== false || stripos($title, 'Human Resources') !== false;
            });

            if (empty($suggestedAdmins)) {
                $suggestedAdmins = array_filter($accounts, function($a) use ($managerMap) {
                    return empty($managerMap[$a['employee_id']]);
                });
            }

            // Removed $_SESSION['logged_in'] = true; and $_SESSION['role'] = 'admin'; - do not grant sessions on import

            $this->jsonResponse([
                'success' => true,
                'batch_id' => $batchId,
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'warnings' => $validationWarnings,
                'suggested_admins' => array_values($suggestedAdmins),
                'activation_csv_ready' => true,
                'accounts' => $accounts
            ]);

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function updateRoles() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['roles'])) {
            $this->jsonResponse(['error' => 'Invalid or missing roles mapping payload.'], 400);
        }

        // 1. Permission Verification
        if (!hasPermission('users.create') && empty($_SESSION['is_super'])) {
            $this->jsonResponse(['error' => 'Denied'], 403);
        }

        $tenantId = $_SESSION['tenant_id'] ?? null;
        if (empty($tenantId)) {
            $this->jsonResponse(['error' => 'No tenant context'], 403);
        }
        $allowedRoles = ['admin', 'hr', 'hr manager', 'recruiter', 'employee', 'manager', 'supervisor'];

        try {
            $this->pdo->beginTransaction();

            $stmtUserUpdate = $this->pdo->prepare("UPDATE users SET role = ? WHERE employee_id = ? AND tenant_id = ?");
            $stmtFindUser = $this->pdo->prepare("SELECT id FROM users WHERE employee_id = ? AND tenant_id = ?");
            $stmtFindRole = $this->pdo->prepare("SELECT id FROM roles WHERE LOWER(name) = ?");
            $stmtClearRoles = $this->pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
            $stmtInsertRole = $this->pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");

            foreach ($input['roles'] as $employeeId => $newRole) {
                $cleanRole = strtolower(trim($newRole));
                if (!in_array($cleanRole, $allowedRoles)) {
                    throw new Exception("Invalid role requested: " . htmlspecialchars($cleanRole));
                }

                $stmtUserUpdate->execute([$cleanRole, $employeeId, $tenantId]);
                $stmtFindUser->execute([$employeeId, $tenantId]);
                $userId = $stmtFindUser->fetchColumn();

                if ($userId) {
                    $stmtFindRole->execute([$cleanRole]);
                    $roleId = $stmtFindRole->fetchColumn();

                    if ($roleId) {
                        $stmtClearRoles->execute([$userId]);
                        $stmtInsertRole->execute([$userId, $roleId]);

                        $stmtFindRole->execute(['employee']);
                        $empRoleId = $stmtFindRole->fetchColumn();
                        if ($empRoleId && $empRoleId != $roleId) {
                            $stmtInsertRole->execute([$userId, $empRoleId]);
                        }
                    }
                }
            }

            $this->pdo->commit();
            $this->jsonResponse(['success' => true]);
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->jsonResponse(['success' => false, 'error' => 'An internal error occurred. Please try again.'], 500);
        }
    }
}
