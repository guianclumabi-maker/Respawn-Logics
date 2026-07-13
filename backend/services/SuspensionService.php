<?php
/**
 * SuspensionService — shared logic for suspending / reinstating an employee.
 * Called by BOTH the standalone HR action (CoreHRController) and the ELR disciplinary
 * flow (ELR case outcome). Sets users.employment_status ('Active' <-> 'Suspended') and
 * writes a full record to employee_suspensions. Tenant-scoped, transactional, audited.
 */
class SuspensionService
{
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }

    /**
     * Suspend an employee.
     * $opts: reason(string), end_date('Y-m-d'|null), source('Standalone'|'ELR'),
     *        elr_case_id(int|null), actor_id(int|null)
     * @return array{success:bool, error?:string, suspension_id?:int}
     */
    public function suspend($tenantId, int $employeeId, array $opts = []): array
    {
        $reason    = trim((string)($opts['reason'] ?? ''));
        $endDate   = $opts['end_date'] ?? null;
        $source    = (($opts['source'] ?? 'Standalone') === 'ELR') ? 'ELR' : 'Standalone';
        $elrCaseId = $opts['elr_case_id'] ?? null;
        $actorId   = $opts['actor_id'] ?? null;

        // IDOR guard: the employee must belong to this tenant.
        $stmt = $this->pdo->prepare("SELECT id, full_name, employment_status FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$employeeId, $tenantId]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$emp) return ['success' => false, 'error' => 'Employee not found in this workspace.'];
        if ($emp['employment_status'] === 'Suspended') {
            return ['success' => false, 'error' => 'Employee is already suspended.'];
        }

        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare("UPDATE users SET employment_status = 'Suspended' WHERE id = ? AND tenant_id = ?")
                      ->execute([$employeeId, $tenantId]);
            $ins = $this->pdo->prepare(
                "INSERT INTO employee_suspensions
                    (tenant_id, employee_id, status, source, elr_case_id, reason, start_date, end_date, suspended_by)
                 VALUES (?, ?, 'Active', ?, ?, ?, CURDATE(), ?, ?)"
            );
            $ins->execute([$tenantId, $employeeId, $source, $elrCaseId, ($reason !== '' ? $reason : null), $endDate, $actorId]);
            $suspensionId = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[SuspensionService::suspend] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Could not suspend employee.'];
        }

        if (function_exists('logAudit')) {
            logAudit('Employee Suspended',
                "Suspended {$emp['full_name']} (#{$employeeId}) via {$source}" . ($reason !== '' ? " — {$reason}" : ''),
                null, $tenantId);
        }

        $result = ['success' => true, 'suspension_id' => $suspensionId];
        // PH labor-law guard: a suspension/off-detail beyond 6 months without recall
        // risks constructive (illegal) dismissal (Labor Code Art. 301). Warn, don't block.
        if ($endDate && $endDate > date('Y-m-d', strtotime('+6 months'))) {
            $result['warning'] = 'This suspension ends more than 6 months from today. Under Philippine labor law '
                . '(Labor Code Art. 301), a suspension or off-detail beyond 6 months without recall may be deemed '
                . 'constructive (illegal) dismissal — review the end date before finalizing.';
        }
        return $result;
    }

    /**
     * Reinstate an employee: employment_status -> 'Active' and close the open suspension record.
     * @return array{success:bool, error?:string}
     */
    public function reinstate($tenantId, int $employeeId, array $opts = []): array
    {
        $actorId = $opts['actor_id'] ?? null;

        $stmt = $this->pdo->prepare("SELECT id, full_name, employment_status FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$employeeId, $tenantId]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$emp) return ['success' => false, 'error' => 'Employee not found in this workspace.'];
        if ($emp['employment_status'] !== 'Suspended') {
            return ['success' => false, 'error' => 'Employee is not currently suspended.'];
        }

        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare("UPDATE users SET employment_status = 'Active' WHERE id = ? AND tenant_id = ?")
                      ->execute([$employeeId, $tenantId]);
            $this->pdo->prepare(
                "UPDATE employee_suspensions SET status = 'Lifted', reinstated_at = NOW(), reinstated_by = ?
                 WHERE tenant_id = ? AND employee_id = ? AND status = 'Active'"
            )->execute([$actorId, $tenantId, $employeeId]);
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[SuspensionService::reinstate] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Could not reinstate employee.'];
        }

        if (function_exists('logAudit')) {
            logAudit('Employee Reinstated', "Reinstated {$emp['full_name']} (#{$employeeId})", null, $tenantId);
        }
        return ['success' => true];
    }

    /** True if the employee is currently suspended. */
    public function isSuspended($tenantId, int $employeeId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE id = ? AND tenant_id = ? AND employment_status = 'Suspended'");
        $stmt->execute([$employeeId, $tenantId]);
        return (bool)$stmt->fetchColumn();
    }

    /** Full suspension history for one employee (most recent first). */
    public function history($tenantId, int $employeeId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM employee_suspensions WHERE tenant_id = ? AND employee_id = ? ORDER BY id DESC");
        $stmt->execute([$tenantId, $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Active suspensions whose planned end_date has already passed — HR should reinstate or
     * extend so nobody is left suspended past their own end date. Includes days_overdue.
     */
    public function overdue($tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, u.full_name, u.email, DATEDIFF(CURDATE(), s.end_date) AS days_overdue
             FROM employee_suspensions s
             JOIN users u ON u.id = s.employee_id AND u.tenant_id = s.tenant_id
             WHERE s.tenant_id = ? AND s.status = 'Active'
               AND s.end_date IS NOT NULL AND s.end_date < CURDATE()
             ORDER BY s.end_date ASC"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
