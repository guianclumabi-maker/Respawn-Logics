<?php

class LeavesController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser() ?: null;
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id']) ? $this->currentUser['tenant_id'] : ($_SESSION['tenant_id'] ?? '1');
    }

    private function getBusinessDays($start_date, $end_date) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $end->modify('+1 day'); // Include end date
        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($start, $interval, $end);
        $days = 0;
        foreach ($period as $dt) {
            if ($dt->format('N') < 6) {
                $days++;
            }
        }
        return $days;
    }

    public function handleRequest($action)
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = $this->currentUser['email'] ?? '';

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                switch ($action) {
                    case 'balances':
                        $stmt = $this->pdo->prepare("SELECT leave_type, total_allowance, used_balance FROM `leave_balances` WHERE `employee_email` = ? AND `tenant_id` = ?");
                        $stmt->execute([$email, $this->tenantId]);
                        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                        break;

                    case 'my_requests':
                        $stmt = $this->pdo->prepare("SELECT * FROM `leave_requests` WHERE `employee_email` = ? AND `tenant_id` = ? ORDER BY `created_at` DESC");
                        $stmt->execute([$email, $this->tenantId]);
                        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                        break;

                    case 'pending_approvals':
                        // Fetch requests where the current user is either the supervisor or manager and it needs action
                        $stmt = $this->pdo->prepare("
                            SELECT lr.*, u.full_name, u.department, u.immediate_supervisor, u.department_manager
                            FROM `leave_requests` lr 
                            JOIN `users` u ON lr.employee_email = u.email AND lr.tenant_id = u.tenant_id 
                            WHERE lr.tenant_id = ? 
                            AND lr.status = 'Pending'
                            AND (
                                (u.immediate_supervisor = ? AND lr.tl_decision = 'Pending') 
                                OR 
                                (u.department_manager = ? AND lr.tl_decision = 'Approved' AND lr.manager_decision = 'Pending')
                            )
                            ORDER BY lr.created_at DESC
                        ");
                        $stmt->execute([$this->tenantId, $email, $email]);
                        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                        break;

                    default:
                        echo json_encode(['success' => false, 'error' => 'Unknown action']);
                        break;
                }
            } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                switch ($action) {
                    case 'apply':
                        $this->applyLeave($input, $email);
                        break;
                    case 'approve_reject':
                        $this->approveRejectLeave($input, $email);
                        break;
                    default:
                        echo json_encode(['success' => false, 'error' => 'Unknown action']);
                        break;
                }
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    private function applyLeave($input, $email)
    {
        $leave_type = trim($input['leave_type'] ?? '');
        $start_date = trim($input['start_date'] ?? '');
        $end_date = trim($input['end_date'] ?? '');
        $reason = trim($input['reason'] ?? '');
        
        if (empty($leave_type) || empty($start_date) || empty($end_date)) {
            echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
            return;
        } 
        
        if (strtotime($start_date) > strtotime($end_date)) {
            echo json_encode(['success' => false, 'error' => 'Start date cannot be after end date.']);
            return;
        }

        $requested_days = $this->getBusinessDays($start_date, $end_date);
        
        // Verify balance
        $bal_stmt = $this->pdo->prepare("SELECT leave_type, total_allowance, used_balance FROM `leave_balances` WHERE `employee_email` = ? AND `tenant_id` = ?");
        $bal_stmt->execute([$email, $this->tenantId]);
        $leave_balances = $bal_stmt->fetchAll(PDO::FETCH_ASSOC);

        $available_balance = 0;
        foreach ($leave_balances as $lb) {
            if ($lb['leave_type'] === $leave_type) {
                $available_balance = $lb['total_allowance'] - $lb['used_balance'];
                break;
            }
        }
        
        if ($requested_days > $available_balance) {
            echo json_encode(['success' => false, 'error' => "Insufficient balance. You requested {$requested_days} day(s), but only have {$available_balance} day(s) available for {$leave_type}."]);
            return;
        }

        $supervisor_email = trim($this->currentUser['immediate_supervisor'] ?? '');
        $manager_email    = trim($this->currentUser['department_manager'] ?? '');
        
        $tl_decision_default = empty($supervisor_email) ? 'Approved' : 'Pending';
        $mgr_decision_default = empty($manager_email) ? 'Approved' : 'Pending';
        
        $initial_status = (empty($supervisor_email) && empty($manager_email)) ? 'Approved' : 'Pending';
        
        $stmt = $this->pdo->prepare("INSERT INTO `leave_requests` (`tenant_id`, `employee_email`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `tl_decision`, `manager_decision`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $email, $leave_type, $start_date, $end_date, $reason, $initial_status, $tl_decision_default, $mgr_decision_default]);
        
        // Auto deduct if auto approved
        if ($initial_status === 'Approved') {
            $deduct_stmt = $this->pdo->prepare("UPDATE `leave_balances` SET `used_balance` = `used_balance` + ? WHERE `employee_email` = ? AND `leave_type` = ? AND `tenant_id` = ?");
            $deduct_stmt->execute([$requested_days, $email, $leave_type, $this->tenantId]);
        }

        echo json_encode(['success' => true]);
    }

    private function approveRejectLeave($input, $email)
    {
        $request_id = intval($input['request_id'] ?? 0);
        $decision = $input['decision'] ?? ''; // 'Approved' or 'Rejected'
        $comments = trim($input['comments'] ?? '');
        
        if ($request_id <= 0 || !in_array($decision, ['Approved', 'Rejected'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid decision inputs.']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT lr.*, u.immediate_supervisor, u.department_manager FROM `leave_requests` lr JOIN `users` u ON lr.employee_email = u.email AND lr.tenant_id = u.tenant_id WHERE lr.id = ? AND lr.tenant_id = ?");
        $stmt->execute([$request_id, $this->tenantId]);
        $req = $stmt->fetch();
        
        if (!$req) {
            echo json_encode(['success' => false, 'error' => 'Request not found.']);
            return;
        }

        $isSupervisor = strtolower($req['immediate_supervisor']) === strtolower($email);
        $isManager = strtolower($req['department_manager']) === strtolower($email);

        if (!$isSupervisor && !$isManager) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            return;
        }

        if ($isSupervisor && $req['tl_decision'] === 'Pending') {
            $status = ($decision === 'Rejected') ? 'Rejected' : 'Pending';
            
            // If the employee has NO manager, and TL approves, auto-approve the whole request
            $emp_manager = trim($req['department_manager'] ?? '');
            if ($decision === 'Approved' && empty($emp_manager)) {
                $status = 'Approved';
                $req_days = $this->getBusinessDays($req['start_date'], $req['end_date']);
                $deduct_stmt = $this->pdo->prepare("UPDATE `leave_balances` SET `used_balance` = `used_balance` + ? WHERE `employee_email` = ? AND `leave_type` = ? AND `tenant_id` = ?");
                $deduct_stmt->execute([$req_days, $req['employee_email'], $req['leave_type'], $this->tenantId]);
            }
            
            $manager_dec = (empty($emp_manager) && $decision === 'Approved') ? 'Approved' : $req['manager_decision'];
            
            $stmt_update = $this->pdo->prepare("UPDATE `leave_requests` SET `tl_decision` = ?, `tl_decided_by` = ?, `tl_decision_date` = NOW(), `tl_comments` = ?, `status` = ?, `manager_decision` = ? WHERE `id` = ? AND `tenant_id` = ?");
            $stmt_update->execute([$decision, $email, $comments, $status, $manager_dec, $request_id, $this->tenantId]);
            
            echo json_encode(['success' => true]);
            return;
        }

        if ($isManager && $req['tl_decision'] === 'Approved' && $req['manager_decision'] === 'Pending') {
            $status = ($decision === 'Approved') ? 'Approved' : 'Rejected';
            
            $stmt_update = $this->pdo->prepare("UPDATE `leave_requests` SET `manager_decision` = ?, `manager_decided_by` = ?, `manager_decision_date` = NOW(), `manager_comments` = ?, `status` = ? WHERE `id` = ? AND `tenant_id` = ?");
            $stmt_update->execute([$decision, $email, $comments, $status, $request_id, $this->tenantId]);
            
            if ($status === 'Approved') {
                $req_days = $this->getBusinessDays($req['start_date'], $req['end_date']);
                $deduct_stmt = $this->pdo->prepare("UPDATE `leave_balances` SET `used_balance` = `used_balance` + ? WHERE `employee_email` = ? AND `leave_type` = ? AND `tenant_id` = ?");
                $deduct_stmt->execute([$req_days, $req['employee_email'], $req['leave_type'], $this->tenantId]);
            }
            echo json_encode(['success' => true]);
            return;
        }

        echo json_encode(['success' => false, 'error' => 'Action cannot be performed at this time.']);
    }
}
