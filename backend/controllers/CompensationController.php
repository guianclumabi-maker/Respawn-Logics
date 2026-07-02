<?php

class CompensationController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser() ?: null;
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id'])
            ? $this->currentUser['tenant_id']
            : ($_SESSION['tenant_id'] ?? null);
    }

    public function handleRequest($action)
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve tenant context']);
            return;
        }

        // Read access requires compensation.view (writes are additionally gated below).
        if (!hasPermission('compensation.view') && empty($_SESSION['is_super'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Denied']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            switch ($action) {
                case 'bands':
                    $this->listBands();
                    break;
                case 'save_band':
                    $this->requireManage();
                    $this->saveBand($input);
                    break;
                case 'delete_band':
                    $this->requireManage();
                    $this->deleteBand($input);
                    break;
                case 'equity':
                    $this->listEquity();
                    break;
                case 'save_equity':
                    $this->requireManage();
                    $this->saveEquity($input);
                    break;
                case 'delete_equity':
                    $this->requireManage();
                    $this->deleteEquity($input);
                    break;
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
        }
    }

    /** Writes require compensation.manage (salary data is sensitive). */
    private function requireManage()
    {
        if (!hasPermission('compensation.manage') && empty($_SESSION['is_super'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Denied']);
            exit;
        }
    }

    // ── Salary Bands ────────────────────────────────────────────────────────
    private function listBands()
    {
        $stmt = $this->pdo->prepare(
            "SELECT `id`, `job_title`, `min_salary`, `mid_salary`, `max_salary`, `currency`
             FROM `compensation_bands` WHERE `tenant_id` = ? ORDER BY `job_title` ASC"
        );
        $stmt->execute([$this->tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function saveBand($input)
    {
        $id       = (int)($input['id'] ?? 0);
        $jobTitle = trim($input['job_title'] ?? '');
        $min      = (float)($input['min_salary'] ?? 0);
        $mid      = (float)($input['mid_salary'] ?? 0);
        $max      = (float)($input['max_salary'] ?? 0);
        $currency = trim($input['currency'] ?? 'PHP') ?: 'PHP';

        if ($jobTitle === '') {
            echo json_encode(['success' => false, 'error' => 'Job title is required.']);
            return;
        }

        if ($id > 0) {
            $stmt = $this->pdo->prepare(
                "UPDATE `compensation_bands`
                 SET `job_title` = ?, `min_salary` = ?, `mid_salary` = ?, `max_salary` = ?, `currency` = ?
                 WHERE `id` = ? AND `tenant_id` = ?"
            );
            $stmt->execute([$jobTitle, $min, $mid, $max, $currency, $id, $this->tenantId]);
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `compensation_bands` (`tenant_id`, `job_title`, `min_salary`, `mid_salary`, `max_salary`, `currency`)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$this->tenantId, $jobTitle, $min, $mid, $max, $currency]);
        }
        echo json_encode(['success' => true, 'message' => 'Salary band saved.']);
    }

    private function deleteBand($input)
    {
        $id = (int)($input['id'] ?? 0);
        $this->pdo->prepare("DELETE FROM `compensation_bands` WHERE `id` = ? AND `tenant_id` = ?")
            ->execute([$id, $this->tenantId]);
        echo json_encode(['success' => true, 'message' => 'Salary band deleted.']);
    }

    // ── Employee Equity ─────────────────────────────────────────────────────
    private function listEquity()
    {
        $stmt = $this->pdo->prepare(
            "SELECT `id`, `employee_name`, `grant_type`, `total_shares`, `vested_shares`, `vesting_schedule`, `grant_date`
             FROM `employee_equity` WHERE `tenant_id` = ? ORDER BY `grant_date` DESC"
        );
        $stmt->execute([$this->tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function saveEquity($input)
    {
        $id        = (int)($input['id'] ?? 0);
        $name      = trim($input['employee_name'] ?? '');
        $grantType = in_array($input['grant_type'] ?? 'ESOP', ['ESOP', 'RSU', 'Phantom'], true) ? $input['grant_type'] : 'ESOP';
        $total     = max(0, (int)($input['total_shares'] ?? 0));
        $vested    = max(0, (int)($input['vested_shares'] ?? 0));
        $schedule  = trim($input['vesting_schedule'] ?? '4-Year (1-Year Cliff)');
        $grantDate = !empty($input['grant_date']) ? $input['grant_date'] : date('Y-m-d');

        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Employee name is required.']);
            return;
        }
        if ($vested > $total) {
            echo json_encode(['success' => false, 'error' => 'Vested shares cannot exceed total shares.']);
            return;
        }

        if ($id > 0) {
            $stmt = $this->pdo->prepare(
                "UPDATE `employee_equity`
                 SET `employee_name` = ?, `grant_type` = ?, `total_shares` = ?, `vested_shares` = ?, `vesting_schedule` = ?, `grant_date` = ?
                 WHERE `id` = ? AND `tenant_id` = ?"
            );
            $stmt->execute([$name, $grantType, $total, $vested, $schedule, $grantDate, $id, $this->tenantId]);
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `employee_equity` (`tenant_id`, `employee_name`, `grant_type`, `total_shares`, `vested_shares`, `vesting_schedule`, `grant_date`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$this->tenantId, $name, $grantType, $total, $vested, $schedule, $grantDate]);
        }
        echo json_encode(['success' => true, 'message' => 'Equity grant saved.']);
    }

    private function deleteEquity($input)
    {
        $id = (int)($input['id'] ?? 0);
        $this->pdo->prepare("DELETE FROM `employee_equity` WHERE `id` = ? AND `tenant_id` = ?")
            ->execute([$id, $this->tenantId]);
        echo json_encode(['success' => true, 'message' => 'Equity grant deleted.']);
    }
}
