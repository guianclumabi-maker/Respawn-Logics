<?php
header('Content-Type: application/json');

class ValidationException extends Exception {}

// Helper for XSS sanitization
function sanitizeInput($str) {
    if ($str === null) return null;
    if (is_array($str)) {
        return array_map('sanitizeInput', $str);
    }
    return htmlspecialchars(strip_tags(trim((string)$str)), ENT_QUOTES, 'UTF-8');
}

// 1. Read database configuration
require_once __DIR__ . '/bootstrap/app.php';

$input = json_decode(file_get_contents('php://input'), true);

try {
    if (!$input || empty($input['currentUser'])) {
        throw new ValidationException('Invalid payload data: missing currentUser details.');
    }

    $currentUserInput = $input['currentUser'];
    $nodes = $input['nodes'] ?: [];
    $connections = $input['connections'] ?: [];
    $columnTypes = $input['columnTypes'] ?: [];
    
    // Validate current user fields
    $currentFirst = sanitizeInput($currentUserInput['firstName'] ?? '');
    $currentLast = sanitizeInput($currentUserInput['lastName'] ?? '');
    $currentEmail = filter_var($currentUserInput['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $currentPass = $currentUserInput['password'] ?? ''; // Keep raw password for insert, will BCRYPT hash it
    $currentPosId = sanitizeInput($currentUserInput['positionNodeId'] ?? '');

    if (empty($currentFirst) || empty($currentLast) || !$currentEmail || empty($currentPass) || empty($currentPosId)) {
        file_put_contents('debug_payload.log', print_r([
            'first' => $currentFirst,
            'last' => $currentLast,
            'email' => $currentEmail,
            'pass' => $currentPass,
            'posId' => $currentPosId,
            'rawEmail' => $currentUserInput['email'] ?? 'NOT_SET'
        ], true) . "\nRaw Input: " . print_r($currentUserInput, true));
        throw new ValidationException('Invalid or incomplete current user registration inputs.');
    }

    // Validate and sanitize organization domain
    $orgDomain = sanitizeInput($input['organization']['domain'] ?? '');
    if (empty($orgDomain) || !preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $orgDomain)) {
        throw new ValidationException('Invalid company domain format. Use e.g. respawn.io');
    }
    
    $orgName = sanitizeInput($input['organization']['name'] ?? 'My Company');

    // Reusable helper to find a node's type
    function getNodeType($node, $columnTypes) {
        $col = $node['column'];
        if (isset($columnTypes[$col])) {
            return $columnTypes[$col];
        }
        if ($col >= 5) return 'team';
        if ($col == 4) return 'position';
        if ($col == 1) return 'company';
        if ($col == 2) return 'division';
        return 'hub';
    }

    // 1. Map all nodes by their IDs & sanitize internal details
    $nodesMap = [];
    foreach ($nodes as $key => $node) {
        if (empty($node['id'])) continue;
        
        // Sanitize names and attributes
        $nodes[$key]['name'] = sanitizeInput($node['name'] ?? 'Unnamed');
        if (isset($node['properties'])) {
            foreach ($node['properties'] as $propKey => $propVal) {
                $nodes[$key]['properties'][$propKey] = sanitizeInput($propVal);
            }
        }
        if (isset($node['subitems'])) {
            $nodes[$key]['subitems'] = array_map('sanitizeInput', $node['subitems']);
        }
        if (isset($node['teammembers'])) {
            $nodes[$key]['teammembers'] = array_map('sanitizeInput', $node['teammembers']);
        }
        
        $nodesMap[$node['id']] = $nodes[$key];
    }

    // 2. Map connections (to -> from)
    $parentConnectionMap = [];
    $childConnectionsMap = [];
    foreach ($connections as $conn) {
        if (empty($conn['from']) || empty($conn['to'])) continue;
        $parentConnectionMap[$conn['to']] = $conn['from'];
        if (!isset($childConnectionsMap[$conn['from']])) {
            $childConnectionsMap[$conn['from']] = [];
        }
        $childConnectionsMap[$conn['from']][] = $conn['to'];
    }

    // Helper to trace back the hierarchy path of a node with Loop/Cycle detection
    function tracePath($nodeId, $nodesMap, $parentConnectionMap) {
        $path = [];
        $visited = [];
        $currentId = $nodeId;
        while ($currentId && isset($nodesMap[$currentId])) {
            if (in_array($currentId, $visited)) {
                $names = array_map(function($id) use ($nodesMap) {
                    return $nodesMap[$id]['name'] ?? $id;
                }, $visited);
                throw new ValidationException("Circular dependency loop detected in chart connection path: " . implode(" -> ", $names) . " -> " . ($nodesMap[$currentId]['name'] ?? $currentId));
            }
            $visited[] = $currentId;
            $path[] = $nodesMap[$currentId];
            $currentId = isset($parentConnectionMap[$currentId]) ? $parentConnectionMap[$currentId] : null;
        }
        return array_reverse($path); // Company -> Division -> Hub -> Position
    }

    // Helper to find the department (Division) name of a node
    function resolveDepartment($nodeId, $nodesMap, $parentConnectionMap, $columnTypes) {
        $path = tracePath($nodeId, $nodesMap, $parentConnectionMap);
        foreach ($path as $n) {
            if (getNodeType($n, $columnTypes) === 'division') {
                return $n['name'];
            }
        }
        return 'Operations'; // Default fallback
    }

    // Helper to resolve the supervisor email of a position node with Cycle detection
    function resolveSupervisorEmail($nodeId, $nodesMap, $parentConnectionMap, $emailsMap) {
        $currentId = $nodeId;
        $visited = [];
        while (isset($parentConnectionMap[$currentId])) {
            if (in_array($currentId, $visited)) {
                throw new ValidationException("Circular dependency loop detected while resolving immediate supervisor chain.");
            }
            $visited[] = $currentId;
            $parentId = $parentConnectionMap[$currentId];
            if (isset($nodesMap[$parentId])) {
                if (isset($emailsMap[$parentId])) {
                    return $emailsMap[$parentId];
                }
            }
            $currentId = $parentId;
        }
        return null; // Top-level
    }

    // Helper to resolve the department manager of a node with Cycle detection
    function resolveDepartmentManager($nodeId, $nodesMap, $parentConnectionMap, $emailsMap, $columnTypes) {
        $path = tracePath($nodeId, $nodesMap, $parentConnectionMap);
        foreach (array_reverse($path) as $pathNode) {
            if (getNodeType($pathNode, $columnTypes) === 'position' && isset($emailsMap[$pathNode['id']])) {
                // Approximate tier of pathNode
                $pTier = 1.0;
                if (preg_match('/\((\d+(\.\d+)?)\)/', $pathNode['name'], $pMatches)) {
                    $pTier = floatval($pMatches[1]);
                } else {
                    $pClear = isset($pathNode['properties']['clearance']) ? $pathNode['properties']['clearance'] : 'Associate';
                    if ($pClear === 'Executive') $pTier = 4.0;
                    else if ($pClear === 'Manager') $pTier = 3.0;
                }
                if ($pTier >= 3.0) {
                    return $emailsMap[$pathNode['id']];
                }
            }
        }
        return null;
    }

    // 3. Pre-generate emails and validate duplicate Employee Numbers
    $emailsMap = [];
    $usedEmployeeNumbers = [];
    
    foreach ($nodes as $node) {
        $type = getNodeType($node, $columnTypes);
        if ($type !== 'team') {
            // Verify Employee Number is unique
            $employeeNumber = isset($node['properties']['employee_number']) ? trim($node['properties']['employee_number']) : '';
            if ($employeeNumber !== '') {
                if (isset($usedEmployeeNumbers[$employeeNumber])) {
                    throw new ValidationException("Duplicate Employee Number '$employeeNumber' detected for position card '{$node['name']}'. Each position must have a unique Employee Number.");
                }
                $usedEmployeeNumbers[$employeeNumber] = true;
            }
            
            if ($node['id'] === $currentPosId) {
                $emailsMap[$node['id']] = $currentEmail;
            } else {
                // Generate slug email
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' ', '.', $node['name'])));
                if (empty($slug)) $slug = 'user.' . $node['id'];
                $emailsMap[$node['id']] = $slug . '@' . $orgDomain;
            }
        }
    }

    // Ensure the current user's mapped position actually exists in the nodes array
    if (!isset($nodesMap[$currentPosId])) {
        throw new ValidationException("The selected position node '{$currentPosId}' does not exist on the canvas.");
    }

    // 4. Schema Mutation (ALTER TABLE) - RUN BEFORE ACTIVE TRANSACTION (implicit commit safety)
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'employee_number'");
        if (!$checkCol->fetch()) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `employee_number` VARCHAR(50) DEFAULT NULL AFTER `id`;");
        }
        $checkCol2 = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'manager_supervisor_id'");
        if (!$checkCol2->fetch()) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `manager_supervisor_id` VARCHAR(50) DEFAULT NULL AFTER `employee_number`;");
        }
    } catch (PDOException $e) {
        // Schema changes might fail on strict production configurations; do not block ingest if column exists
    }

    // 5. Begin Database Transaction
    $pdo->beginTransaction();
    
    // Clear existing tables using transaction-safe DELETE
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DELETE FROM `users`;");
    $pdo->exec("DELETE FROM `audit_logs`;");
    $pdo->exec("DELETE FROM `attendance`;");
    $pdo->exec("DELETE FROM `leave_requests`;");
    $pdo->exec("DELETE FROM `employee_tasks`;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    $stmt = $pdo->prepare("INSERT INTO `users` 
        (`full_name`, `email`, `role`, `department`, `immediate_supervisor`, `department_manager`, `tier`, `job_title`, `password_hash`, `employee_number`, `manager_supervisor_id`, `profile_image`) 
        VALUES (:full_name, :email, :role, :department, :immediate_supervisor, :department_manager, :tier, :job_title, :password_hash, :employee_number, :manager_supervisor_id, :profile_image)");
        
    $insertedAccounts = [];
    $insertedEmails = [];

    // Loop through position nodes and insert users
    foreach ($nodes as $node) {
        $type = getNodeType($node, $columnTypes);
        if ($type === 'team') continue;
        
        $email = $emailsMap[$node['id']];
        if (isset($insertedEmails[strtolower($email)])) continue;
        $insertedEmails[strtolower($email)] = true;
        
        $isCurrentUser = ($node['id'] === $currentPosId);
        
        $fullName = $isCurrentUser ? ($currentFirst . ' ' . $currentLast) : $node['name'];
        $rawPassword = $isCurrentUser ? $currentPass : 'password123';
        $passwordHash = password_hash($rawPassword, PASSWORD_BCRYPT);
        
        // Parse tier from name e.g. "Senior Associate (3.5)" -> 3.5
        $tierVal = 1.0;
        if (preg_match('/\((\d+(\.\d+)?)\)/', $node['name'], $matches)) {
            $tierVal = floatval($matches[1]);
        } else {
            // Mapping clearance to default tiers
            $clearance = isset($node['properties']['clearance']) ? $node['properties']['clearance'] : 'Associate';
            if ($clearance === 'Executive') $tierVal = 4.0;
            else if ($clearance === 'Manager') $tierVal = 3.0;
            else if ($clearance === 'Associate') $tierVal = 1.0;
            else $tierVal = 1.0;
        }
        
        // Determine role column for system access
        $role = 'employee';
        if ($tierVal >= 4.0) $role = 'admin';
        else if ($tierVal >= 3.0) $role = 'manager';
        else if ($tierVal >= 2.0) $role = 'supervisor';
        
        $department = resolveDepartment($node['id'], $nodesMap, $parentConnectionMap, $columnTypes);
        $supervisor = resolveSupervisorEmail($node['id'], $nodesMap, $parentConnectionMap, $emailsMap);
        $deptManager = resolveDepartmentManager($node['id'], $nodesMap, $parentConnectionMap, $emailsMap, $columnTypes);
        
        // Retrieve optional properties
        $employeeNumber = isset($node['properties']['employee_number']) ? $node['properties']['employee_number'] : null;
        $managerSupervisorId = isset($node['properties']['manager_supervisor_id']) ? $node['properties']['manager_supervisor_id'] : null;
        $jobTitle = (!empty($node['properties']['job_position'])) ? $node['properties']['job_position'] : $node['name'];
        
        // Fallback null safety values for non-nullable DB columns
        $supervisorVal = $supervisor ?: '';
        $deptManagerVal = $deptManager ?: '';
        
        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':role' => $role,
            ':department' => $department,
            ':immediate_supervisor' => $supervisorVal,
            ':department_manager' => $deptManagerVal,
            ':tier' => $tierVal,
            ':job_title' => $jobTitle,
            ':password_hash' => $passwordHash,
            ':employee_number' => $employeeNumber,
            ':manager_supervisor_id' => $managerSupervisorId,
            ':profile_image' => ''
        ]);
        
        $insertedAccounts[] = [
            'full_name' => $fullName,
            'email' => $email,
            'password' => $rawPassword,
            'role' => $role,
            'department' => $department,
            'immediate_supervisor' => $supervisorVal,
            'job_title' => $jobTitle
        ];
    }

    // Loop through team nodes and insert their team members
    foreach ($nodes as $node) {
        $type = getNodeType($node, $columnTypes);
        if ($type !== 'team') continue;
        
        if (empty($node['teammembers'])) continue;
        
        // Resolve supervisor email for this team node
        $supervisor = resolveSupervisorEmail($node['id'], $nodesMap, $parentConnectionMap, $emailsMap);
        $supervisorVal = $supervisor ?: '';
        
        // Resolve department manager by tracing path of the team node
        $deptManager = resolveDepartmentManager($node['id'], $nodesMap, $parentConnectionMap, $emailsMap, $columnTypes);
        $deptManagerVal = $deptManager ?: '';
        
        foreach ($node['teammembers'] as $memberObj) {
            $memberName = is_array($memberObj) ? ($memberObj['name'] ?? 'Unnamed') : $memberObj;
            $memberEmpId = (is_array($memberObj) && !empty($memberObj['emp_id'])) ? $memberObj['emp_id'] : null;
            $memberJob = (is_array($memberObj) && !empty($memberObj['job'])) ? $memberObj['job'] : 'Team Member';
            $memberManagerSup = (is_array($memberObj) && !empty($memberObj['manager_supervisor_id'])) ? $memberObj['manager_supervisor_id'] : null;

            // Avoid duplicates if member is same as current user
            if (strtolower($memberName) === strtolower($currentFirst . ' ' . $currentLast)) {
                continue;
            }
            
            $mSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' ', '.', $memberName)));
            $mEmail = $mSlug . '@' . $orgDomain;
            
            if (isset($insertedEmails[strtolower($mEmail)])) continue;
            $insertedEmails[strtolower($mEmail)] = true;
            
            $stmt->execute([
                ':full_name' => $memberName,
                ':email' => $mEmail,
                ':role' => 'employee',
                ':department' => $node['name'],
                ':immediate_supervisor' => $supervisorVal,
                ':department_manager' => $deptManagerVal,
                ':tier' => 1.0,
                ':job_title' => $memberJob,
                ':password_hash' => password_hash('password123', PASSWORD_BCRYPT),
                ':employee_number' => $memberEmpId,
                ':manager_supervisor_id' => $memberManagerSup,
                ':profile_image' => ''
            ]);
            
            $insertedAccounts[] = [
                'full_name' => $memberName,
                'email' => $mEmail,
                'password' => 'password123',
                'role' => 'employee',
                'department' => $node['name'],
                'immediate_supervisor' => $supervisorVal,
                'job_title' => 'Team Member'
            ];
        }
    }
    
    $pdo->commit();
    echo json_encode([
        'success' => true,
        'accounts' => $insertedAccounts
    ]);

} catch (ValidationException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database ingestion error: ' . $e->getMessage()
    ]);
}
