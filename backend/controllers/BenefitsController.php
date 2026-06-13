<?php

class BenefitsController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser();
        $this->tenantId = $this->currentUser['tenant_id'] ?? $_SESSION['tenant_id'] ?? '1';
    }

    private function isFinanceOrHR()
    {
        return hasPermission('benefits.manage');
    }

    public function handleRequest($action)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $input = $_POST;
        }

        switch ($action) {
            case 'plans':
                $this->plans();
                break;
            case 'my_benefits':
                $this->myBenefits();
                break;
            case 'enroll':
                $this->enroll($input);
                break;
            case 'my_statutory':
                $this->myStatutory();
                break;
            case 'update_statutory':
                $this->updateStatutory($input);
                break;
            case 'hr_plans':
                $this->hrPlans();
                break;
            case 'hr_create_plan':
                $this->hrCreatePlan($input);
                break;
            case 'hr_enrollments':
                $this->hrEnrollments();
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
                break;
        }
    }

    private function plans()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `benefit_plans` WHERE `tenant_id` = ? AND `status` = 'Active'");
        $stmt->execute([$this->tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    private function myBenefits()
    {
        $stmt = $this->pdo->prepare("
            SELECT eb.*, bp.name as plan_name, bp.type as plan_type, bp.employee_cost, bp.provider
            FROM `employee_benefits` eb
            JOIN `benefit_plans` bp ON eb.plan_id = bp.id
            WHERE eb.employee_id = ? AND eb.tenant_id = ?
        ");
        $stmt->execute([$this->currentUser['id'], $this->tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    private function enroll($input)
    {
        $planId = intval($input['plan_id'] ?? 0);
        $depCount = intval($input['dependent_count'] ?? 0);
        $status = $input['status'] ?? 'Enrolled';
        
        if (!$planId) {
            echo json_encode(['success' => false, 'error' => 'Invalid plan']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO `employee_benefits` (`tenant_id`, `employee_id`, `plan_id`, `dependent_count`, `status`, `enrollment_date`)
                VALUES (?, ?, ?, ?, ?, CURDATE())
                ON DUPLICATE KEY UPDATE `dependent_count` = VALUES(`dependent_count`), `status` = VALUES(`status`), `updated_at` = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$this->tenantId, $this->currentUser['id'], $planId, $depCount, $status]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function myStatutory()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `employee_statutory` WHERE `employee_id` = ?");
        $stmt->execute([$this->currentUser['id']]);
        $data = $stmt->fetch();
        if (!$data) {
            $data = ['sss_number'=>'', 'philhealth_number'=>'', 'pagibig_number'=>'', 'tin_number'=>''];
        }
        echo json_encode(['success' => true, 'data' => $data]);
    }

    private function updateStatutory($input)
    {
        $sss = $input['sss_number'] ?? '';
        $phic = $input['philhealth_number'] ?? '';
        $hdmf = $input['pagibig_number'] ?? '';
        $tin = $input['tin_number'] ?? '';

        $stmt = $this->pdo->prepare("
            INSERT INTO `employee_statutory` (`employee_id`, `sss_number`, `philhealth_number`, `pagibig_number`, `tin_number`)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                `sss_number` = VALUES(`sss_number`),
                `philhealth_number` = VALUES(`philhealth_number`),
                `pagibig_number` = VALUES(`pagibig_number`),
                `tin_number` = VALUES(`tin_number`)
        ");
        $stmt->execute([$this->currentUser['id'], $sss, $phic, $hdmf, $tin]);
        echo json_encode(['success' => true]);
    }

    private function hrPlans()
    {
        if (!$this->isFinanceOrHR()) {
            echo json_encode(['success' => false, 'error' => 'Denied']);
            return;
        }
        $stmt = $this->pdo->prepare("
            SELECT bp.*, 
            (SELECT COUNT(*) FROM employee_benefits eb WHERE eb.plan_id = bp.id AND eb.status = 'Enrolled') as enrolled_count
            FROM `benefit_plans` bp 
            WHERE bp.tenant_id = ?
        ");
        $stmt->execute([$this->tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    private function hrCreatePlan($input)
    {
        if (!$this->isFinanceOrHR()) {
            echo json_encode(['success' => false, 'error' => 'Denied']);
            return;
        }
        $name = $input['name'] ?? '';
        $provider = $input['provider'] ?? '';
        $type = $input['type'] ?? 'HMO';
        $empCost = floatval($input['employee_cost'] ?? 0);
        $compCost = floatval($input['company_cost'] ?? 0);
        $desc = $input['description'] ?? '';

        $stmt = $this->pdo->prepare("INSERT INTO `benefit_plans` (`tenant_id`, `name`, `provider`, `type`, `employee_cost`, `company_cost`, `description`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $name, $provider, $type, $empCost, $compCost, $desc]);
        echo json_encode(['success' => true]);
    }

    private function hrEnrollments()
    {
        if (!$this->isFinanceOrHR()) {
            echo json_encode(['success' => false, 'error' => 'Denied']);
            return;
        }
        $stmt = $this->pdo->prepare("
            SELECT eb.*, bp.name as plan_name, bp.type, bp.employee_cost, u.full_name, u.employee_number
            FROM `employee_benefits` eb
            JOIN `benefit_plans` bp ON eb.plan_id = bp.id
            JOIN `users` u ON eb.employee_id = u.id
            WHERE eb.tenant_id = ? AND eb.status = 'Enrolled'
            ORDER BY bp.name ASC, u.full_name ASC
        ");
        $stmt->execute([$this->tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }
}
