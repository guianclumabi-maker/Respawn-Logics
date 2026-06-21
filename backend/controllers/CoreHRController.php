<?php

class CoreHRController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        // Since we already ran isLoggedIn() in api/index.php, getCurrentUser() will work.
        $this->currentUser = getCurrentUser() ?: null;
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id']) ? $this->currentUser['tenant_id'] : ($_SESSION['tenant_id'] ?? null);
    }

    public function handleRequest($action)
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve tenant context']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            switch ($action) {
                case 'master_record':
                    $this->masterRecord();
                    break;
                case 'directory':
                    $this->directory();
                    break;
                case 'update_master_record':
                    if (!hasPermission('employees.manage')) { http_response_code(403); echo json_encode(['success'=>false, 'error'=>'Denied']); return; }
                $this->updateMasterRecord($input);
                    break;
                case 'custom_fields_def':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hasPermission('settings.manage')) { http_response_code(403); echo json_encode(['success'=>false, 'error'=>'Denied']); return; }
                    $this->customFieldsDef($input);
                    break;
                case 'upload_document':
                    if (!hasPermission('settings.manage')) { http_response_code(403); echo json_encode(['success'=>false, 'error'=>'Denied']); return; }
                    $this->uploadDocument();
                    break;
                case 'save_tenant_modules':
                    if (!hasPermission('settings.manage')) { http_response_code(403); echo json_encode(['success'=>false, 'error'=>'Denied']); return; }
                $this->saveTenantModules($input);
                    break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown action']);
                    break;
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    private function logEmploymentHistory($userId, $changeType, $jobTitle, $dept, $managerId, $salary, $notes, $recordedBy)
    {
        $stmt = $this->pdo->prepare("INSERT INTO `employment_history` (`tenant_id`, `user_id`, `change_type`, `job_title`, `department`, `manager_id`, `base_salary`, `effective_date`, `notes`, `recorded_by`) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)");
        $stmt->execute([$this->tenantId, $userId, $changeType, $jobTitle, $dept, $managerId, $salary, $notes, $recordedBy]);
    }

    private function directory()
    {
        if (!hasPermission('employees.view')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Denied']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT id, full_name, email, employment_status, department, job_title, created_at FROM users WHERE tenant_id = ? ORDER BY created_at DESC");
        $stmt->execute([$this->tenantId]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $employees]);
    }

    private function masterRecord()
    {
        $userId = intval($_GET['user_id'] ?? 0);
        
        // Fetch user basic
        $stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$userId, $this->tenantId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }

        // Mask salary if requester is not HR/Admin and not themselves
        $isHR = hasPermission('users.manage') || hasPermission('employees.manage');
        if (!$isHR && $this->currentUser['id'] !== $user['id']) {
            $user['base_salary'] = null;
        }

        // Fetch history
        $histStmt = $this->pdo->prepare("SELECT * FROM `employment_history` WHERE `user_id` = ? AND `tenant_id` = ? ORDER BY `effective_date` DESC, `id` DESC");
        $histStmt->execute([$userId, $this->tenantId]);
        $history = $histStmt->fetchAll();

        // Mask salary in history as well
        if (!$isHR && $this->currentUser['id'] !== $user['id']) {
            foreach ($history as &$h) {
                $h['base_salary'] = null;
            }
        }

        // Fetch custom fields values
        $cfStmt = $this->pdo->prepare("
            SELECT cf.id, cf.field_name, cf.field_type, cf.field_options, cfv.field_value 
            FROM `custom_fields` cf 
            LEFT JOIN `custom_field_values` cfv ON cfv.field_id = cf.id AND cfv.user_id = ? 
            WHERE cf.tenant_id = ?
        ");
        $cfStmt->execute([$userId, $this->tenantId]);
        $customFields = $cfStmt->fetchAll();

        // Fetch documents
        $docStmt = $this->pdo->prepare("SELECT `id`, `document_type`, `file_name`, `uploaded_by`, `uploaded_at` FROM `employee_documents` WHERE `user_id` = ? AND `tenant_id` = ? ORDER BY `uploaded_at` DESC");
        $docStmt->execute([$userId, $this->tenantId]);
        $documents = $docStmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => [
                'profile' => $user,
                'history' => $history,
                'custom_fields' => $customFields,
                'documents' => $documents
            ]
        ]);
    }

    private function updateMasterRecord($input)
    {
        if (!hasPermission('users.manage') && !hasPermission('employees.manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Denied']);
            return;
        }

        $userId = intval($input['user_id'] ?? 0);
        $changeType = $input['change_type'] ?? 'Profile Update';
        $notes = $input['notes'] ?? '';

        // Current record
        $stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$userId, $this->tenantId]);
        $oldUser = $stmt->fetch();

        if (!$oldUser) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            // Prepare update
            $fields = [];
            $params = [];
            $updatable = ['full_name', 'employment_status', 'department', 'work_location', 'job_title', 'base_salary'];
            
            $changedCoreFields = false;

            foreach ($updatable as $f) {
                if (isset($input[$f])) {
                    $fields[] = "`$f` = ?";
                    $params[] = $input[$f];
                    
                    if (in_array($f, ['job_title', 'department', 'base_salary', 'manager_id']) && $oldUser[$f] != $input[$f]) {
                        $changedCoreFields = true;
                    }
                }
            }

            if (!empty($fields)) {
                $params[] = $userId;
                $params[] = $this->tenantId;
                $this->pdo->prepare("UPDATE `users` SET " . implode(', ', $fields) . " WHERE `id` = ? AND `tenant_id` = ?")->execute($params);
            }

            // Log history if it's a structural change
            if ($changedCoreFields || $changeType !== 'Profile Update') {
                $newTitle = $input['job_title'] ?? $oldUser['job_title'];
                $newDept = $input['department'] ?? $oldUser['department'];
                $newMgr = $input['manager_id'] ?? ($oldUser['manager_id'] ?? null);
                $newSal = $input['base_salary'] ?? $oldUser['base_salary'];

                $this->logEmploymentHistory($userId, $changeType, $newTitle, $newDept, $newMgr, $newSal, $notes, $this->currentUser['full_name']);
            }

            // Handle custom fields
            if (isset($input['custom_fields']) && is_array($input['custom_fields'])) {
                $cfStmt = $this->pdo->prepare("INSERT INTO `custom_field_values` (`tenant_id`, `user_id`, `field_id`, `field_value`) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE `field_value` = ?");
                foreach ($input['custom_fields'] as $fieldId => $val) {
                    $cfStmt->execute([$this->tenantId, $userId, $fieldId, $val, $val]);
                }
            }

            $this->pdo->commit();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }

    private function customFieldsDef($input)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $stmt = $this->pdo->prepare("SELECT * FROM `custom_fields` WHERE `tenant_id` = ?");
            $stmt->execute([$this->tenantId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!hasPermission('settings.manage')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Denied']);
                return;
            }
            $name = $input['field_name'] ?? '';
            $type = $input['field_type'] ?? 'text';
            $options = $input['field_options'] ?? '';

            try {
                $stmt = $this->pdo->prepare("INSERT INTO `custom_fields` (`tenant_id`, `field_name`, `field_type`, `field_options`) VALUES (?, ?, ?, ?)");
                $stmt->execute([$this->tenantId, $name, $type, $options]);
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Field exists or DB error']);
            }
        }
    }

    private function uploadDocument()
    {
        if (!hasPermission('settings.manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Denied']);
            return;
        }

        $userId = intval($_POST['user_id'] ?? 0);
        $docType = $_POST['document_type'] ?? 'General';
        
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/tenant_' . $this->tenantId . '/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileInfo = pathinfo($_FILES['document']['name']);
            $ext = strtolower($fileInfo['extension']);
            $allowed = ['pdf', 'doc', 'docx', 'jpg', 'png'];
            
            if (in_array($ext, $allowed)) {
                $filename = 'doc_' . $userId . '_' . time() . '.' . $ext;
                $destPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['document']['tmp_name'], $destPath)) {
                    $stmt = $this->pdo->prepare("INSERT INTO `employee_documents` (`tenant_id`, `user_id`, `document_type`, `file_name`, `file_path`, `uploaded_by`) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$this->tenantId, $userId, $docType, $fileInfo['basename'], 'uploads/tenant_'.$this->tenantId.'/documents/'.$filename, $this->currentUser['full_name']]);
                    
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid file extension']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        }
    }

    private function saveTenantModules($input)
    {
        if (!hasPermission('settings.manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Denied']);
            return;
        }

        $modules = $input['modules'] ?? [];
        if (empty($modules)) {
            echo json_encode(['success' => false, 'error' => 'No modules provided']);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("INSERT INTO `tenant_modules` (`tenant_id`, `module_key`, `is_enabled`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `is_enabled` = VALUES(`is_enabled`)");
            
            foreach ($modules as $key => $isEnabled) {
                $stmt->execute([$this->tenantId, $key, $isEnabled ? 1 : 0]);
            }

            $this->pdo->commit();
            
            // Invalidate cache in session
            unset($_SESSION['tenant_modules']);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }
}
