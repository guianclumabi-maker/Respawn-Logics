<?php

/**
 * ELR Admin Console — pipeline module backend ("ATS for cases").
 *
 * Phase 1 implemented here: document templates (the "Jobs" equivalent) — rich-text
 * documents with {{merge_fields}}, tenant-scoped and gated to ELR staff.
 * Later phases (pipelines, stages, the kanban board, stage-transition document
 * generation, AWOL auto-scan) hang off the same controller/route.
 */
class ELRPipelineController
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
        // All ELR endpoints require at least ELR view.
        requirePermission('elr.view');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $input['action'] ?? $action;
        }

        try {
            switch ($action) {
                case 'templates':
                    $this->listTemplates();
                    break;
                case 'template':
                    $this->getTemplate();
                    break;
                case 'save_template':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->saveTemplate($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;
                case 'delete_template':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->deleteTemplate($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;

                // ── Phase 2: pipelines + stages ──
                case 'pipelines':
                    $this->listPipelines();
                    break;
                case 'pipeline':
                    $this->getPipeline();
                    break;
                case 'save_pipeline':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->savePipeline($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;
                case 'delete_pipeline':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->deletePipeline($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;
                case 'save_stage':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->saveStage($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;
                case 'delete_stage':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->deleteStage($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;

                // ── Phase 3: the board (cards = employees moving through stages) ──
                case 'board':
                    $this->getBoard();
                    break;
                case 'card':
                    $this->getCard();
                    break;
                case 'add_card':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->addCard($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;
                case 'move_card':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->moveCard($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;

                // ── Daily incident report (digest of everything filed in a window) ──
                case 'daily_report':
                    $this->getDailyReport();
                    break;

                // ── Phase 4: auto-population (AWOL detection) ──
                case 'auto_rules':
                    $this->getAutoRules();
                    break;
                case 'save_auto_rule':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->saveAutoRule($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;
                case 'delete_auto_rule':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->deleteAutoRule($input);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;
                case 'run_scan':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $this->requireManage();
                        echo json_encode($this->scanCurrentTenant());
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Invalid method']);
                    }
                    break;

                // ── Phase 5: serve/acknowledge documents, hearings, approvals ──
                case 'serve_document':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->markDocument($input, 'served'); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
                    break;
                case 'acknowledge_document':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->markDocument($input, 'acknowledged'); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
                    break;
                case 'hearings':
                    $this->listHearings();
                    break;
                case 'save_hearing':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->saveHearing($input); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
                    break;
                case 'approvals':
                    $this->listApprovals();
                    break;
                case 'request_approval':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->requestApproval($input); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
                    break;
                case 'decide_approval':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->decideApproval($input); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
                    break;
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            error_log('[' . __CLASS__ . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'An internal error occurred. Please try again.']);
        }
    }

    /** Writes to the ELR admin corpus require investigate-level access (or platform admin). */
    private function requireManage()
    {
        if (!hasPermission('elr.investigate') && empty($_SESSION['is_super'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You do not have permission to manage ELR templates.']);
            exit;
        }
    }

    /** Extract distinct {{merge_field}} keys from a template body. */
    private function extractMergeFields($body)
    {
        $fields = [];
        if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', (string)$body, $m)) {
            $fields = array_values(array_unique($m[1]));
        }
        return $fields;
    }

    private function listTemplates()
    {
        $stmt = $this->pdo->prepare(
            "SELECT `id`, `name`, `doc_type`, `description`, `merge_fields`, `is_active`, `created_by`, `created_at`, `updated_at`
             FROM `elr_document_templates`
             WHERE `tenant_id` = ?
             ORDER BY `updated_at` DESC"
        );
        $stmt->execute([$this->tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['merge_fields'] = $r['merge_fields'] ? json_decode($r['merge_fields'], true) : [];
        }
        echo json_encode(['success' => true, 'templates' => $rows]);
    }

    private function getTemplate()
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Template ID required']);
            return;
        }
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `elr_document_templates` WHERE `id` = ? AND `tenant_id` = ?"
        );
        $stmt->execute([$id, $this->tenantId]);
        $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tpl) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Template not found']);
            return;
        }
        $tpl['merge_fields'] = $tpl['merge_fields'] ? json_decode($tpl['merge_fields'], true) : [];
        echo json_encode(['success' => true, 'template' => $tpl]);
    }

    private function saveTemplate($input)
    {
        $this->requireManage();

        $id      = (int)($input['id'] ?? 0);
        $name    = trim($input['name'] ?? '');
        $docType = trim($input['doc_type'] ?? '');
        $desc    = trim($input['description'] ?? '');
        $body    = (string)($input['body'] ?? '');
        $active  = isset($input['is_active']) ? (int)!empty($input['is_active']) : 1;

        if ($name === '' || $docType === '' || trim($body) === '') {
            echo json_encode(['success' => false, 'error' => 'Name, document type, and body are required.']);
            return;
        }

        $mergeFields = json_encode($this->extractMergeFields($body));
        $actor = is_array($this->currentUser) ? ($this->currentUser['full_name'] ?? 'Admin') : 'Admin';

        if ($id > 0) {
            // Update — tenant-scoped so no cross-tenant edits.
            $stmt = $this->pdo->prepare(
                "UPDATE `elr_document_templates`
                 SET `name` = ?, `doc_type` = ?, `description` = ?, `body` = ?, `merge_fields` = ?, `is_active` = ?
                 WHERE `id` = ? AND `tenant_id` = ?"
            );
            $stmt->execute([$name, $docType, $desc, $body, $mergeFields, $active, $id, $this->tenantId]);
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Template updated.']);
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO `elr_document_templates`
                (`tenant_id`, `name`, `doc_type`, `description`, `body`, `merge_fields`, `is_active`, `created_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$this->tenantId, $name, $docType, $desc, $body, $mergeFields, $active, $actor]);
        echo json_encode(['success' => true, 'id' => (int)$this->pdo->lastInsertId(), 'message' => 'Template created.']);
    }

    private function deleteTemplate($input)
    {
        $this->requireManage();
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Template ID required']);
            return;
        }
        $stmt = $this->pdo->prepare("DELETE FROM `elr_document_templates` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$id, $this->tenantId]);
        echo json_encode(['success' => true, 'message' => 'Template deleted.']);
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase 2 — Pipelines + Stages
    //  A pipeline is a case workflow; stages are its kanban columns.
    //  Each stage may map to a document template (fired on entry in Phase 3).
    // ─────────────────────────────────────────────────────────────

    /** List pipelines with their stage counts. */
    private function listPipelines()
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.`id`, p.`name`, p.`description`, p.`is_active`, p.`created_by`, p.`created_at`, p.`updated_at`,
                    (SELECT COUNT(*) FROM `elr_pipeline_stages` s WHERE s.`pipeline_id` = p.`id`) AS stage_count,
                    (SELECT COUNT(*) FROM `elr_case_cards` c WHERE c.`pipeline_id` = p.`id` AND c.`status` = 'Active') AS active_cases
             FROM `elr_pipelines` p
             WHERE p.`tenant_id` = ?
             ORDER BY p.`created_at` DESC"
        );
        $stmt->execute([$this->tenantId]);
        echo json_encode(['success' => true, 'pipelines' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    /** Get one pipeline plus its ordered stages (each with the mapped template name, if any). */
    private function getPipeline()
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Pipeline ID required']);
            return;
        }
        $pStmt = $this->pdo->prepare("SELECT * FROM `elr_pipelines` WHERE `id` = ? AND `tenant_id` = ?");
        $pStmt->execute([$id, $this->tenantId]);
        $pipeline = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$pipeline) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Pipeline not found']);
            return;
        }
        $sStmt = $this->pdo->prepare(
            "SELECT s.`id`, s.`name`, s.`stage_order`, s.`template_id`, s.`sla_days`, s.`is_terminal`,
                    t.`name` AS template_name, t.`doc_type` AS template_doc_type
             FROM `elr_pipeline_stages` s
             LEFT JOIN `elr_document_templates` t ON s.`template_id` = t.`id`
             WHERE s.`pipeline_id` = ? AND s.`tenant_id` = ?
             ORDER BY s.`stage_order` ASC, s.`id` ASC"
        );
        $sStmt->execute([$id, $this->tenantId]);
        $pipeline['stages'] = $sStmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'pipeline' => $pipeline]);
    }

    private function savePipeline($input)
    {
        $this->requireManage();
        $id     = (int)($input['id'] ?? 0);
        $name   = trim($input['name'] ?? '');
        $desc   = trim($input['description'] ?? '');
        $active = isset($input['is_active']) ? (int)!empty($input['is_active']) : 1;

        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Pipeline name is required.']);
            return;
        }
        $actor = is_array($this->currentUser) ? ($this->currentUser['full_name'] ?? 'Admin') : 'Admin';

        if ($id > 0) {
            $stmt = $this->pdo->prepare(
                "UPDATE `elr_pipelines` SET `name` = ?, `description` = ?, `is_active` = ?
                 WHERE `id` = ? AND `tenant_id` = ?"
            );
            $stmt->execute([$name, $desc, $active, $id, $this->tenantId]);
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Pipeline updated.']);
            return;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO `elr_pipelines` (`tenant_id`, `name`, `description`, `is_active`, `created_by`)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$this->tenantId, $name, $desc, $active, $actor]);
        echo json_encode(['success' => true, 'id' => (int)$this->pdo->lastInsertId(), 'message' => 'Pipeline created.']);
    }

    private function deletePipeline($input)
    {
        $this->requireManage();
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Pipeline ID required']);
            return;
        }
        // Remove the pipeline and its stages (tenant-scoped). Case cards are left intact but detached.
        $this->pdo->prepare("DELETE FROM `elr_pipeline_stages` WHERE `pipeline_id` = ? AND `tenant_id` = ?")
                  ->execute([$id, $this->tenantId]);
        $this->pdo->prepare("DELETE FROM `elr_pipelines` WHERE `id` = ? AND `tenant_id` = ?")
                  ->execute([$id, $this->tenantId]);
        echo json_encode(['success' => true, 'message' => 'Pipeline deleted.']);
    }

    /** Create or update a stage within a pipeline. template_id/sla_days are optional. */
    private function saveStage($input)
    {
        $this->requireManage();
        $id         = (int)($input['id'] ?? 0);
        $pipelineId = (int)($input['pipeline_id'] ?? 0);
        $name       = trim($input['name'] ?? '');
        $order      = (int)($input['stage_order'] ?? 0);
        $templateId = !empty($input['template_id']) ? (int)$input['template_id'] : null;
        $slaDays    = isset($input['sla_days']) && $input['sla_days'] !== '' ? (int)$input['sla_days'] : null;
        $isTerminal = (int)!empty($input['is_terminal']);

        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Stage name is required.']);
            return;
        }

        // Validate the pipeline belongs to this tenant.
        $chk = $this->pdo->prepare("SELECT COUNT(*) FROM `elr_pipelines` WHERE `id` = ? AND `tenant_id` = ?");
        $chk->execute([$pipelineId, $this->tenantId]);
        if (!$chk->fetchColumn()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid pipeline.']);
            return;
        }
        // If a template is set, validate it belongs to this tenant too.
        if ($templateId !== null) {
            $tchk = $this->pdo->prepare("SELECT COUNT(*) FROM `elr_document_templates` WHERE `id` = ? AND `tenant_id` = ?");
            $tchk->execute([$templateId, $this->tenantId]);
            if (!$tchk->fetchColumn()) {
                echo json_encode(['success' => false, 'error' => 'Invalid template for this tenant.']);
                return;
            }
        }

        if ($id > 0) {
            $stmt = $this->pdo->prepare(
                "UPDATE `elr_pipeline_stages`
                 SET `name` = ?, `stage_order` = ?, `template_id` = ?, `sla_days` = ?, `is_terminal` = ?
                 WHERE `id` = ? AND `tenant_id` = ?"
            );
            $stmt->execute([$name, $order, $templateId, $slaDays, $isTerminal, $id, $this->tenantId]);
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Stage updated.']);
            return;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO `elr_pipeline_stages` (`tenant_id`, `pipeline_id`, `name`, `stage_order`, `template_id`, `sla_days`, `is_terminal`)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$this->tenantId, $pipelineId, $name, $order, $templateId, $slaDays, $isTerminal]);
        echo json_encode(['success' => true, 'id' => (int)$this->pdo->lastInsertId(), 'message' => 'Stage added.']);
    }

    private function deleteStage($input)
    {
        $this->requireManage();
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Stage ID required']);
            return;
        }
        $stmt = $this->pdo->prepare("DELETE FROM `elr_pipeline_stages` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$id, $this->tenantId]);
        echo json_encode(['success' => true, 'message' => 'Stage deleted.']);
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase 3 — The board + document generation
    //  Cards are employees moving through stages. Entering a stage that
    //  has a mapped template auto-generates the merged document, and every
    //  move is logged as a due-process transition (the compliance trail).
    // ─────────────────────────────────────────────────────────────

    /** Return a pipeline's stages plus its active case cards (with employee display fields). */
    private function getBoard()
    {
        $pipelineId = (int)($_GET['pipeline_id'] ?? 0);
        if (!$pipelineId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'pipeline_id required']);
            return;
        }
        // Validate ownership.
        $chk = $this->pdo->prepare("SELECT `name` FROM `elr_pipelines` WHERE `id` = ? AND `tenant_id` = ?");
        $chk->execute([$pipelineId, $this->tenantId]);
        $pipelineName = $chk->fetchColumn();
        if ($pipelineName === false) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Pipeline not found']);
            return;
        }

        $sStmt = $this->pdo->prepare(
            "SELECT s.`id`, s.`name`, s.`stage_order`, s.`template_id`, s.`sla_days`, s.`is_terminal`,
                    t.`name` AS template_name, t.`doc_type` AS template_doc_type
             FROM `elr_pipeline_stages` s
             LEFT JOIN `elr_document_templates` t ON s.`template_id` = t.`id`
             WHERE s.`pipeline_id` = ? AND s.`tenant_id` = ?
             ORDER BY s.`stage_order` ASC, s.`id` ASC"
        );
        $sStmt->execute([$pipelineId, $this->tenantId]);
        $stages = $sStmt->fetchAll(PDO::FETCH_ASSOC);

        $cStmt = $this->pdo->prepare(
            "SELECT c.`id`, c.`employee_id`, c.`current_stage_id`, c.`status`, c.`entered_via`, c.`created_at`, c.`updated_at`,
                    u.`full_name`, u.`department`, u.`job_title`,
                    (SELECT COUNT(*) FROM `elr_generated_documents` g WHERE g.`case_card_id` = c.`id`) AS doc_count
             FROM `elr_case_cards` c
             LEFT JOIN `users` u ON c.`employee_id` = u.`employee_id` AND u.`tenant_id` = c.`tenant_id`
             WHERE c.`pipeline_id` = ? AND c.`tenant_id` = ? AND c.`status` <> 'Closed'
             ORDER BY c.`updated_at` DESC"
        );
        $cStmt->execute([$pipelineId, $this->tenantId]);
        $cards = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'       => true,
            'pipeline_id'   => $pipelineId,
            'pipeline_name' => $pipelineName,
            'stages'        => $stages,
            'cards'         => $cards,
        ]);
    }

    /** One card: employee info + generated documents + full transition history. */
    private function getCard()
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Card ID required']);
            return;
        }
        $cStmt = $this->pdo->prepare(
            "SELECT c.*, u.`full_name`, u.`department`, u.`job_title`, u.`email`
             FROM `elr_case_cards` c
             LEFT JOIN `users` u ON c.`employee_id` = u.`employee_id` AND u.`tenant_id` = c.`tenant_id`
             WHERE c.`id` = ? AND c.`tenant_id` = ?"
        );
        $cStmt->execute([$id, $this->tenantId]);
        $card = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$card) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Card not found']);
            return;
        }

        $dStmt = $this->pdo->prepare(
            "SELECT `id`, `template_id`, `stage_id`, `doc_type`, `title`, `content`, `generated_by`, `generated_at`, `served_at`, `acknowledged_at`
             FROM `elr_generated_documents` WHERE `case_card_id` = ? AND `tenant_id` = ? ORDER BY `generated_at` DESC"
        );
        $dStmt->execute([$id, $this->tenantId]);

        $tStmt = $this->pdo->prepare(
            "SELECT tr.`from_stage_id`, tr.`to_stage_id`, tr.`actor`, tr.`note`, tr.`transitioned_at`,
                    fs.`name` AS from_stage_name, ts.`name` AS to_stage_name
             FROM `elr_stage_transitions` tr
             LEFT JOIN `elr_pipeline_stages` fs ON tr.`from_stage_id` = fs.`id`
             LEFT JOIN `elr_pipeline_stages` ts ON tr.`to_stage_id` = ts.`id`
             WHERE tr.`case_card_id` = ? AND tr.`tenant_id` = ? ORDER BY tr.`transitioned_at` ASC"
        );
        $tStmt->execute([$id, $this->tenantId]);

        echo json_encode([
            'success'     => true,
            'card'        => $card,
            'documents'   => $dStmt->fetchAll(PDO::FETCH_ASSOC),
            'transitions' => $tStmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    /** Manually add an employee to a pipeline. Starts at the given stage or the first stage. */
    private function addCard($input)
    {
        $this->requireManage();
        $pipelineId = (int)($input['pipeline_id'] ?? 0);
        $employeeId = trim($input['employee_id'] ?? '');
        $enteredVia = ($input['entered_via'] ?? 'manual') === 'auto' ? 'auto' : 'manual';
        $extra      = is_array($input['fields'] ?? null) ? $input['fields'] : [];

        if (!$pipelineId || $employeeId === '') {
            echo json_encode(['success' => false, 'error' => 'pipeline_id and employee_id are required.']);
            return;
        }
        // Validate pipeline + employee belong to this tenant.
        $pchk = $this->pdo->prepare("SELECT COUNT(*) FROM `elr_pipelines` WHERE `id` = ? AND `tenant_id` = ?");
        $pchk->execute([$pipelineId, $this->tenantId]);
        if (!$pchk->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Invalid pipeline.']);
            return;
        }
        $echk = $this->pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `employee_id` = ? AND `tenant_id` = ?");
        $echk->execute([$employeeId, $this->tenantId]);
        if (!$echk->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Employee not found in this tenant.']);
            return;
        }

        $stageId = (int)($input['stage_id'] ?? 0) ?: null;
        $actor = is_array($this->currentUser) ? ($this->currentUser['full_name'] ?? 'Admin') : 'Admin';
        $result = $this->createCardInternal($pipelineId, $employeeId, $enteredVia, $stageId, $extra, $actor);
        echo json_encode(['success' => true, 'card_id' => $result['card_id'], 'generated_document' => $result['generated_document']]);
    }

    /**
     * Shared card creation, used by manual add_card AND the automated AWOL scan (non-echoing).
     * Defaults to the pipeline's first stage; logs entry and fires the entry stage's document.
     */
    private function createCardInternal($pipelineId, $employeeId, $enteredVia, $entryStageId, array $extra, $actor)
    {
        if (empty($entryStageId)) {
            $fs = $this->pdo->prepare(
                "SELECT `id` FROM `elr_pipeline_stages` WHERE `pipeline_id` = ? AND `tenant_id` = ?
                 ORDER BY `stage_order` ASC, `id` ASC LIMIT 1"
            );
            $fs->execute([$pipelineId, $this->tenantId]);
            $entryStageId = (int)$fs->fetchColumn() ?: null;
        }
        $ins = $this->pdo->prepare(
            "INSERT INTO `elr_case_cards` (`tenant_id`, `pipeline_id`, `employee_id`, `current_stage_id`, `status`, `entered_via`, `created_by`)
             VALUES (?, ?, ?, ?, 'Active', ?, ?)"
        );
        $ins->execute([$this->tenantId, $pipelineId, $employeeId, $entryStageId, $enteredVia, $actor]);
        $cardId = (int)$this->pdo->lastInsertId();
        $this->logTransition($cardId, null, $entryStageId, $actor, $enteredVia === 'auto' ? 'Auto-added (AWOL scan)' : 'Added to pipeline');
        $doc = $entryStageId ? $this->generateDocumentForStage($cardId, $employeeId, $entryStageId, $actor, $extra) : null;
        return ['card_id' => $cardId, 'generated_document' => $doc];
    }

    /**
     * Move a card to a new stage. This is the core mechanic:
     * logs the due-process transition and auto-generates the target stage's document.
     */
    private function moveCard($input)
    {
        $this->requireManage();
        $cardId    = (int)($input['card_id'] ?? 0);
        $toStageId = (int)($input['to_stage_id'] ?? 0);
        $extra     = is_array($input['fields'] ?? null) ? $input['fields'] : [];
        if (!$cardId || !$toStageId) {
            echo json_encode(['success' => false, 'error' => 'card_id and to_stage_id are required.']);
            return;
        }

        // Load the card (tenant-scoped).
        $cStmt = $this->pdo->prepare("SELECT * FROM `elr_case_cards` WHERE `id` = ? AND `tenant_id` = ?");
        $cStmt->execute([$cardId, $this->tenantId]);
        $card = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$card) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Card not found']);
            return;
        }

        // Validate target stage is in the same pipeline + tenant; also read is_terminal.
        $sStmt = $this->pdo->prepare(
            "SELECT `is_terminal` FROM `elr_pipeline_stages` WHERE `id` = ? AND `pipeline_id` = ? AND `tenant_id` = ?"
        );
        $sStmt->execute([$toStageId, $card['pipeline_id'], $this->tenantId]);
        $isTerminal = $sStmt->fetchColumn();
        if ($isTerminal === false) {
            echo json_encode(['success' => false, 'error' => 'Target stage does not belong to this pipeline.']);
            return;
        }

        $fromStageId = $card['current_stage_id'] !== null ? (int)$card['current_stage_id'] : null;
        if ($fromStageId === $toStageId) {
            echo json_encode(['success' => true, 'message' => 'No change (already in that stage).']);
            return;
        }

        $actor = is_array($this->currentUser) ? ($this->currentUser['full_name'] ?? 'Admin') : 'Admin';
        $newStatus = (int)$isTerminal === 1 ? 'Resolved' : 'Active';

        $upd = $this->pdo->prepare(
            "UPDATE `elr_case_cards` SET `current_stage_id` = ?, `status` = ? WHERE `id` = ? AND `tenant_id` = ?"
        );
        $upd->execute([$toStageId, $newStatus, $cardId, $this->tenantId]);

        $this->logTransition($cardId, $fromStageId, $toStageId, $actor, $input['note'] ?? null);
        $doc = $this->generateDocumentForStage($cardId, $card['employee_id'], $toStageId, $actor, $extra);

        echo json_encode([
            'success'            => true,
            'message'            => 'Card moved.',
            'status'             => $newStatus,
            'generated_document' => $doc,
        ]);
    }

    /** Insert a stage-transition audit row (the twin-notice / due-process trail). */
    private function logTransition($cardId, $fromStageId, $toStageId, $actor, $note = null)
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `elr_stage_transitions` (`tenant_id`, `case_card_id`, `from_stage_id`, `to_stage_id`, `actor`, `note`)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$this->tenantId, $cardId, $fromStageId, $toStageId, $actor, $note]);
    }

    /**
     * If the stage has a mapped template, merge the employee's data into it and store the
     * rendered document. Returns the generated doc row (or null if the stage has no template).
     */
    private function generateDocumentForStage($cardId, $employeeId, $stageId, $actor, array $extra = [])
    {
        // Fetch stage + its template.
        $sStmt = $this->pdo->prepare(
            "SELECT s.`template_id`, t.`name` AS template_name, t.`doc_type`, t.`body`
             FROM `elr_pipeline_stages` s
             LEFT JOIN `elr_document_templates` t ON s.`template_id` = t.`id`
             WHERE s.`id` = ? AND s.`tenant_id` = ?"
        );
        $sStmt->execute([$stageId, $this->tenantId]);
        $stage = $sStmt->fetch(PDO::FETCH_ASSOC);
        if (!$stage || empty($stage['template_id']) || $stage['body'] === null) {
            return null; // stage has no document to fire
        }

        // Build the merge data from the employee record + context + caller-supplied extras.
        $eStmt = $this->pdo->prepare(
            "SELECT `full_name`, `employee_id`, `department`, `job_title`, `email`
             FROM `users` WHERE `employee_id` = ? AND `tenant_id` = ?"
        );
        $eStmt->execute([$employeeId, $this->tenantId]);
        $emp = $eStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $data = array_merge([
            'employee_name' => $emp['full_name'] ?? '',
            'employee_id'   => $emp['employee_id'] ?? $employeeId,
            'department'    => $emp['department'] ?? '',
            'job_title'     => $emp['job_title'] ?? '',
            'email'         => $emp['email'] ?? '',
            'date'          => date('F j, Y'),
        ], $extra); // caller extras (e.g. awol_start_date, deadline_days) override/extend

        $rendered = $this->renderTemplate($stage['body'], $data);

        $ins = $this->pdo->prepare(
            "INSERT INTO `elr_generated_documents`
                (`tenant_id`, `case_card_id`, `template_id`, `stage_id`, `doc_type`, `title`, `content`, `generated_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $title = ($stage['doc_type'] ?: 'Document') . ' — ' . ($emp['full_name'] ?? $employeeId);
        $ins->execute([
            $this->tenantId, $cardId, (int)$stage['template_id'], $stageId,
            $stage['doc_type'], $title, $rendered, $actor
        ]);

        return [
            'id'       => (int)$this->pdo->lastInsertId(),
            'title'    => $title,
            'doc_type' => $stage['doc_type'],
            'content'  => $rendered,
        ];
    }

    /** Replace {{placeholders}} with data; unknown fields become a fill-in blank so nothing leaks. */
    private function renderTemplate($body, array $data)
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($data) {
            $key = $m[1];
            return array_key_exists($key, $data) && $data[$key] !== '' ? $data[$key] : '________';
        }, (string)$body);
    }

    // ─────────────────────────────────────────────────────────────
    //  Daily Incident Report
    //  An incident-type-agnostic digest: every case filed in the window
    //  (auto AND manual, any pipeline), with filters + summary counts.
    //  Powers both the on-demand view and the scheduled 24h digest.
    // ─────────────────────────────────────────────────────────────
    private function getDailyReport()
    {
        // Window: defaults to today. Filterable to any range.
        $start = $_GET['start_date'] ?? date('Y-m-d');
        $end   = $_GET['end_date'] ?? $start;
        $s = strtotime($start);
        $e = strtotime($end);
        if ($s === false || $e === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid date range.']);
            return;
        }
        if ($e < $s) { $t = $s; $s = $e; $e = $t; }
        $startD = date('Y-m-d', $s);
        $endD   = date('Y-m-d', $e);

        // Filters (all optional) — keep the digest useful.
        $filters = [
            'pipeline_id' => (int)($_GET['pipeline_id'] ?? 0),
            'department'  => trim($_GET['department'] ?? ''),
            'status'      => trim($_GET['status'] ?? ''),   // Active / Resolved / Closed
            'source'      => trim($_GET['source'] ?? ''),   // auto / manual
            'search'      => trim($_GET['search'] ?? ''),
        ];
        echo json_encode($this->dailyReportData($startD, $endD, $filters));
    }

    /**
     * Build the daily incident report (summary + cards) for a window. Shared by the HTTP
     * endpoint and the scheduled digest emailer. Returns the array (does not echo).
     */
    public function dailyReportData($startD, $endD, array $filters = [])
    {
        $pipelineId = (int)($filters['pipeline_id'] ?? 0);
        $dept       = trim($filters['department'] ?? '');
        $status     = trim($filters['status'] ?? '');
        $source     = trim($filters['source'] ?? '');
        $search     = trim($filters['search'] ?? '');

        $sql = "SELECT c.`id`, c.`employee_id`, c.`pipeline_id`, c.`current_stage_id`, c.`status`, c.`entered_via`, c.`created_at`,
                       u.`full_name`, u.`department`,
                       p.`name` AS pipeline_name,
                       st.`name` AS stage_name,
                       (SELECT COUNT(*) FROM `elr_generated_documents` g WHERE g.`case_card_id` = c.`id`) AS doc_count
                FROM `elr_case_cards` c
                LEFT JOIN `users` u ON c.`employee_id` = u.`employee_id` AND u.`tenant_id` = c.`tenant_id`
                LEFT JOIN `elr_pipelines` p ON c.`pipeline_id` = p.`id`
                LEFT JOIN `elr_pipeline_stages` st ON c.`current_stage_id` = st.`id`
                WHERE c.`tenant_id` = ? AND DATE(c.`created_at`) BETWEEN ? AND ?";
        $params = [$this->tenantId, $startD, $endD];

        if ($pipelineId)                          { $sql .= " AND c.`pipeline_id` = ?"; $params[] = $pipelineId; }
        if ($dept !== '')                         { $sql .= " AND u.`department` = ?";  $params[] = $dept; }
        if ($status !== '')                       { $sql .= " AND c.`status` = ?";      $params[] = $status; }
        if ($source === 'auto' || $source === 'manual') { $sql .= " AND c.`entered_via` = ?"; $params[] = $source; }
        if ($search !== '')                       { $sql .= " AND (u.`full_name` LIKE ? OR c.`employee_id` LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

        $sql .= " ORDER BY c.`created_at` DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = ['total' => count($cards), 'auto' => 0, 'manual' => 0, 'by_pipeline' => [], 'by_department' => []];
        foreach ($cards as $c) {
            ($c['entered_via'] === 'auto') ? $summary['auto']++ : $summary['manual']++;
            $pn = $c['pipeline_name'] ?: 'Unassigned';
            $dn = $c['department'] ?: 'Unassigned';
            $summary['by_pipeline'][$pn]   = ($summary['by_pipeline'][$pn] ?? 0) + 1;
            $summary['by_department'][$dn] = ($summary['by_department'][$dn] ?? 0) + 1;
        }

        return [
            'success' => true,
            'window'  => ['from' => $startD, 'to' => $endD],
            'summary' => $summary,
            'cards'   => $cards,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase 4 — Auto-population (AWOL detection from attendance)
    //  A tenant configures the rule (threshold + which pipeline/stage to drop
    //  AWOL employees into). The scan reads attendance the same way the
    //  Attendance Report does, and auto-adds employees who are absent on every
    //  one of the last N working days (no punch, no approved leave).
    // ─────────────────────────────────────────────────────────────

    /**
     * The detector library (system-provided). Detection needs a measurable data signal,
     * so companies can't invent detectors — but they compose rules from these and point
     * each at one of their own pipelines. Add new detectors here as more signals are supported.
     */
    private function detectorLibrary()
    {
        return [
            [
                'key'    => 'awol',
                'label'  => 'AWOL — consecutive absences',
                'desc'   => 'Flags employees absent on every one of the last N working days (no punch, no approved leave).',
                'params' => [
                    ['key' => 'consecutive_days', 'label' => 'Consecutive absent working days', 'type' => 'number', 'default' => 3],
                ],
            ],
            [
                'key'    => 'tardiness',
                'label'  => 'Tardiness — repeated lates',
                'desc'   => 'Flags employees with N or more late arrivals within a rolling window.',
                'params' => [
                    ['key' => 'late_count',  'label' => 'Number of lates', 'type' => 'number', 'default' => 3],
                    ['key' => 'window_days', 'label' => 'Within days',      'type' => 'number', 'default' => 30],
                ],
            ],
        ];
    }

    /** List this tenant's auto-rules + the available detector library (for the UI). */
    private function getAutoRules()
    {
        $stmt = $this->pdo->prepare(
            "SELECT `id`, `rule_type`, `name`, `enabled`, `params`, `target_pipeline_id`, `target_stage_id`, `updated_at`
             FROM `elr_auto_rules` WHERE `tenant_id` = ? ORDER BY `created_at` ASC"
        );
        $stmt->execute([$this->tenantId]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rules as &$r) {
            $r['params'] = $r['params'] ? json_decode($r['params'], true) : [];
        }
        echo json_encode(['success' => true, 'rules' => $rules, 'detectors' => $this->detectorLibrary()]);
    }

    /** Create or update an auto-rule (a detector pointed at a pipeline). */
    private function saveAutoRule($input)
    {
        $this->requireManage();
        $id         = (int)($input['id'] ?? 0);
        $ruleType   = trim($input['rule_type'] ?? '');
        $name       = trim($input['name'] ?? '');
        $enabled    = (int)!empty($input['enabled']);
        $params     = is_array($input['params'] ?? null) ? $input['params'] : [];
        $pipelineId = !empty($input['target_pipeline_id']) ? (int)$input['target_pipeline_id'] : null;
        $stageId    = !empty($input['target_stage_id']) ? (int)$input['target_stage_id'] : null;

        $validTypes = array_column($this->detectorLibrary(), 'key');
        if (!in_array($ruleType, $validTypes, true)) {
            echo json_encode(['success' => false, 'error' => 'Unknown detector type.']);
            return;
        }
        if ($enabled && $pipelineId) {
            $chk = $this->pdo->prepare("SELECT COUNT(*) FROM `elr_pipelines` WHERE `id` = ? AND `tenant_id` = ?");
            $chk->execute([$pipelineId, $this->tenantId]);
            if (!$chk->fetchColumn()) {
                echo json_encode(['success' => false, 'error' => 'Invalid target pipeline.']);
                return;
            }
        }
        $paramsJson = json_encode($params);

        if ($id > 0) {
            $stmt = $this->pdo->prepare(
                "UPDATE `elr_auto_rules`
                 SET `rule_type` = ?, `name` = ?, `enabled` = ?, `params` = ?, `target_pipeline_id` = ?, `target_stage_id` = ?
                 WHERE `id` = ? AND `tenant_id` = ?"
            );
            $stmt->execute([$ruleType, $name, $enabled, $paramsJson, $pipelineId, $stageId, $id, $this->tenantId]);
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Rule updated.']);
            return;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO `elr_auto_rules` (`tenant_id`, `rule_type`, `name`, `enabled`, `params`, `target_pipeline_id`, `target_stage_id`)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$this->tenantId, $ruleType, $name, $enabled, $paramsJson, $pipelineId, $stageId]);
        echo json_encode(['success' => true, 'id' => (int)$this->pdo->lastInsertId(), 'message' => 'Rule created.']);
    }

    private function deleteAutoRule($input)
    {
        $this->requireManage();
        $id = (int)($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Rule ID required']); return; }
        $this->pdo->prepare("DELETE FROM `elr_auto_rules` WHERE `id` = ? AND `tenant_id` = ?")
                  ->execute([$id, $this->tenantId]);
        echo json_encode(['success' => true, 'message' => 'Rule deleted.']);
    }

    /**
     * Run ALL enabled auto-rules for the CURRENT tenant. Public so the CLI/scheduler can call it
     * after setting the tenant context. Returns per-rule summaries (does not echo).
     */
    public function scanCurrentTenant()
    {
        $rStmt = $this->pdo->prepare(
            "SELECT * FROM `elr_auto_rules` WHERE `tenant_id` = ? AND `enabled` = 1 AND `target_pipeline_id` IS NOT NULL"
        );
        $rStmt->execute([$this->tenantId]);
        $rules = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rules) {
            return ['success' => true, 'skipped' => true, 'reason' => 'No enabled auto-rules.', 'results' => []];
        }

        // Active employees (shared across all detectors for this run).
        $eStmt = $this->pdo->prepare(
            "SELECT `employee_id`, `full_name`, `email` FROM `users`
             WHERE `tenant_id` = ? AND `employment_status` = 'Active' AND `email` IS NOT NULL AND `email` <> ''"
        );
        $eStmt->execute([$this->tenantId]);
        $employees = $eStmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rules as $rule) {
            $params         = $rule['params'] ? json_decode($rule['params'], true) : [];
            $targetPipeline = (int)$rule['target_pipeline_id'];
            $targetStage    = !empty($rule['target_stage_id']) ? (int)$rule['target_stage_id'] : null;

            switch ($rule['rule_type']) {
                case 'awol':      $matches = $this->detectAwol($params, $employees); break;
                case 'tardiness': $matches = $this->detectTardiness($params, $employees); break;
                default:          $matches = []; break;
            }

            $actor = 'System (' . $rule['rule_type'] . ' rule)';
            $added = [];
            foreach ($matches as $m) {
                // Skip if the employee already has an active card in the target pipeline.
                $dup = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM `elr_case_cards`
                     WHERE `tenant_id` = ? AND `pipeline_id` = ? AND `employee_id` = ? AND `status` = 'Active'"
                );
                $dup->execute([$this->tenantId, $targetPipeline, $m['employee_id']]);
                if ($dup->fetchColumn() > 0) { continue; }

                $this->createCardInternal($targetPipeline, $m['employee_id'], 'auto', $targetStage, $m['extra'] ?? [], $actor);
                $added[] = ['employee_id' => $m['employee_id'], 'full_name' => $m['full_name']];
            }

            $results[] = [
                'rule_id'     => (int)$rule['id'],
                'rule_type'   => $rule['rule_type'],
                'name'        => $rule['name'],
                'detected'    => count($matches),
                'cards_added' => count($added),
                'added'       => $added,
            ];
        }

        return ['success' => true, 'rules_run' => count($rules), 'employees' => count($employees), 'results' => $results];
    }

    /** Detector: AWOL — absent on every one of the last N working days (no punch, no approved leave). */
    private function detectAwol(array $params, array $employees)
    {
        $n = max(1, (int)($params['consecutive_days'] ?? 3));

        $workingDays = [];
        $cursor = strtotime('yesterday');
        while (count($workingDays) < $n) {
            if ((int)date('N', $cursor) < 6) { $workingDays[] = date('Y-m-d', $cursor); }
            $cursor -= 86400;
        }
        $earliest = end($workingDays);
        $latest   = $workingDays[0];

        $present = [];
        $aStmt = $this->pdo->prepare(
            "SELECT LOWER(`employee_email`) AS mail, DATE(`time_in`) AS d
             FROM `attendance` WHERE `tenant_id` = ? AND DATE(`time_in`) BETWEEN ? AND ?"
        );
        $aStmt->execute([$this->tenantId, $earliest, $latest]);
        while ($r = $aStmt->fetch(PDO::FETCH_ASSOC)) { $present[$r['mail']][$r['d']] = true; }

        $onLeave = [];
        $lStmt = $this->pdo->prepare(
            "SELECT LOWER(`employee_email`) AS mail, `start_date`, `end_date`
             FROM `leave_requests` WHERE `tenant_id` = ? AND `status` = 'Approved'
             AND `start_date` <= ? AND `end_date` >= ?"
        );
        $lStmt->execute([$this->tenantId, $latest, $earliest]);
        while ($lr = $lStmt->fetch(PDO::FETCH_ASSOC)) {
            $ls = strtotime($lr['start_date']); $le = strtotime($lr['end_date']);
            for ($d = $ls; $d <= $le; $d += 86400) { $onLeave[$lr['mail']][date('Y-m-d', $d)] = true; }
        }

        $matches = [];
        foreach ($employees as $emp) {
            $mail = strtolower((string)$emp['email']);
            $absentAll = true;
            foreach ($workingDays as $day) {
                if (isset($present[$mail][$day]) || isset($onLeave[$mail][$day])) { $absentAll = false; break; }
            }
            if ($absentAll) {
                $matches[] = [
                    'employee_id' => $emp['employee_id'],
                    'full_name'   => $emp['full_name'],
                    'extra'       => ['awol_start_date' => $earliest, 'awol_days' => (string)$n],
                ];
            }
        }
        return $matches;
    }

    /** Detector: Tardiness — N or more late arrivals within a rolling window of days. */
    private function detectTardiness(array $params, array $employees)
    {
        $lateCount  = max(1, (int)($params['late_count'] ?? 3));
        $windowDays = max(1, (int)($params['window_days'] ?? 30));
        $since = date('Y-m-d', strtotime("-{$windowDays} days"));

        $counts = [];
        $aStmt = $this->pdo->prepare(
            "SELECT LOWER(`employee_email`) AS mail, COUNT(*) AS cnt
             FROM `attendance`
             WHERE `tenant_id` = ? AND `status` LIKE '%late%' AND DATE(`time_in`) >= ?
             GROUP BY LOWER(`employee_email`)"
        );
        $aStmt->execute([$this->tenantId, $since]);
        while ($r = $aStmt->fetch(PDO::FETCH_ASSOC)) { $counts[$r['mail']] = (int)$r['cnt']; }

        $matches = [];
        foreach ($employees as $emp) {
            $c = $counts[strtolower((string)$emp['email'])] ?? 0;
            if ($c >= $lateCount) {
                $matches[] = [
                    'employee_id' => $emp['employee_id'],
                    'full_name'   => $emp['full_name'],
                    'extra'       => ['late_count' => (string)$c, 'window_days' => (string)$windowDays],
                ];
            }
        }
        return $matches;
    }

    // ─────────────────────────────────────────────────────────────
    //  Phase 5 — Serve/acknowledge documents, hearings, approvals
    //  Completes the due-process trail: issued -> served -> acknowledged,
    //  plus the hearing ("opportunity to be heard") and a sign-off gate.
    // ─────────────────────────────────────────────────────────────

    /** Stamp a generated document as served or acknowledged. */
    private function markDocument($input, $mode)
    {
        $this->requireManage();
        $id = (int)($input['id'] ?? ($input['document_id'] ?? 0));
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Document ID required']);
            return;
        }
        $col = $mode === 'acknowledged' ? 'acknowledged_at' : 'served_at'; // fixed set, safe to interpolate
        $stmt = $this->pdo->prepare("UPDATE `elr_generated_documents` SET `$col` = NOW() WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$id, $this->tenantId]);
        echo json_encode(['success' => true, 'message' => ucfirst($mode) . '.']);
    }

    /** List hearings for a case. */
    private function listHearings()
    {
        $cardId = (int)($_GET['case_card_id'] ?? 0);
        if (!$cardId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'case_card_id required']);
            return;
        }
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `elr_hearings` WHERE `case_card_id` = ? AND `tenant_id` = ? ORDER BY `scheduled_at` DESC, `id` DESC"
        );
        $stmt->execute([$cardId, $this->tenantId]);
        echo json_encode(['success' => true, 'hearings' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    /** Create or update a hearing / conference. */
    private function saveHearing($input)
    {
        $this->requireManage();
        $id       = (int)($input['id'] ?? 0);
        $cardId   = (int)($input['case_card_id'] ?? 0);
        $sched    = trim($input['scheduled_at'] ?? '') ?: null;   // 'YYYY-MM-DD HH:MM:SS'
        $location = trim($input['location'] ?? '');
        $notes    = trim($input['notes'] ?? '');
        $outcome  = trim($input['outcome'] ?? '') ?: null;
        $status   = in_array($input['status'] ?? 'Scheduled', ['Scheduled', 'Held', 'Cancelled'], true) ? $input['status'] : 'Scheduled';
        $actor    = is_array($this->currentUser) ? ($this->currentUser['full_name'] ?? 'Admin') : 'Admin';

        if ($id > 0) {
            $stmt = $this->pdo->prepare(
                "UPDATE `elr_hearings` SET `scheduled_at` = ?, `location` = ?, `notes` = ?, `outcome` = ?, `status` = ?
                 WHERE `id` = ? AND `tenant_id` = ?"
            );
            $stmt->execute([$sched, $location, $notes, $outcome, $status, $id, $this->tenantId]);
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Hearing updated.']);
            return;
        }
        // Validate the case belongs to this tenant before creating.
        $chk = $this->pdo->prepare("SELECT COUNT(*) FROM `elr_case_cards` WHERE `id` = ? AND `tenant_id` = ?");
        $chk->execute([$cardId, $this->tenantId]);
        if (!$chk->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Invalid case.']);
            return;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO `elr_hearings` (`tenant_id`, `case_card_id`, `scheduled_at`, `location`, `notes`, `outcome`, `status`, `created_by`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$this->tenantId, $cardId, $sched, $location, $notes, $outcome, $status, $actor]);
        echo json_encode(['success' => true, 'id' => (int)$this->pdo->lastInsertId(), 'message' => 'Hearing scheduled.']);
    }

    /** List approvals for a case, or the tenant's pending-approvals queue if no case id given. */
    private function listApprovals()
    {
        $cardId = (int)($_GET['case_card_id'] ?? 0);
        if ($cardId) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM `elr_approvals` WHERE `case_card_id` = ? AND `tenant_id` = ? ORDER BY `requested_at` DESC"
            );
            $stmt->execute([$cardId, $this->tenantId]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT a.*, u.`full_name` AS employee_name
                 FROM `elr_approvals` a
                 LEFT JOIN `elr_case_cards` c ON a.`case_card_id` = c.`id`
                 LEFT JOIN `users` u ON c.`employee_id` = u.`employee_id` AND u.`tenant_id` = a.`tenant_id`
                 WHERE a.`tenant_id` = ? AND a.`status` = 'Pending'
                 ORDER BY a.`requested_at` ASC"
            );
            $stmt->execute([$this->tenantId]);
        }
        echo json_encode(['success' => true, 'approvals' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    /** Request sign-off on a decisive action (e.g. issuing a NOD). */
    private function requestApproval($input)
    {
        $this->requireManage();
        $cardId  = (int)($input['case_card_id'] ?? 0);
        $subject = trim($input['subject'] ?? '');
        $stageId = !empty($input['stage_id']) ? (int)$input['stage_id'] : null;
        if (!$cardId || $subject === '') {
            echo json_encode(['success' => false, 'error' => 'case_card_id and subject are required']);
            return;
        }
        $chk = $this->pdo->prepare("SELECT COUNT(*) FROM `elr_case_cards` WHERE `id` = ? AND `tenant_id` = ?");
        $chk->execute([$cardId, $this->tenantId]);
        if (!$chk->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Invalid case.']);
            return;
        }
        $actor = is_array($this->currentUser) ? ($this->currentUser['full_name'] ?? 'Admin') : 'Admin';
        $stmt = $this->pdo->prepare(
            "INSERT INTO `elr_approvals` (`tenant_id`, `case_card_id`, `stage_id`, `subject`, `requested_by`, `status`)
             VALUES (?, ?, ?, ?, ?, 'Pending')"
        );
        $stmt->execute([$this->tenantId, $cardId, $stageId, $subject, $actor]);
        echo json_encode(['success' => true, 'id' => (int)$this->pdo->lastInsertId(), 'message' => 'Approval requested.']);
    }

    /** Approve or reject a pending approval. */
    private function decideApproval($input)
    {
        $this->requireManage();
        $id       = (int)($input['id'] ?? 0);
        $decision = in_array($input['status'] ?? '', ['Approved', 'Rejected'], true) ? $input['status'] : '';
        $note     = trim($input['decision_note'] ?? '') ?: null;
        if (!$id || $decision === '') {
            echo json_encode(['success' => false, 'error' => 'id and a valid status (Approved/Rejected) are required']);
            return;
        }
        $approver = is_array($this->currentUser) ? ($this->currentUser['full_name'] ?? 'Admin') : 'Admin';
        $stmt = $this->pdo->prepare(
            "UPDATE `elr_approvals` SET `status` = ?, `approver` = ?, `decision_note` = ?, `decided_at` = NOW()
             WHERE `id` = ? AND `tenant_id` = ? AND `status` = 'Pending'"
        );
        $stmt->execute([$decision, $approver, $note, $id, $this->tenantId]);
        echo json_encode(['success' => true, 'message' => 'Approval ' . strtolower($decision) . '.']);
    }
}
