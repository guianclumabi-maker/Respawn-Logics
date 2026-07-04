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
             FROM `elr_generated_documents` WHERE `case_card_id` = ? ORDER BY `generated_at` DESC"
        );
        $dStmt->execute([$id]);

        $tStmt = $this->pdo->prepare(
            "SELECT tr.`from_stage_id`, tr.`to_stage_id`, tr.`actor`, tr.`note`, tr.`transitioned_at`,
                    fs.`name` AS from_stage_name, ts.`name` AS to_stage_name
             FROM `elr_stage_transitions` tr
             LEFT JOIN `elr_pipeline_stages` fs ON tr.`from_stage_id` = fs.`id`
             LEFT JOIN `elr_pipeline_stages` ts ON tr.`to_stage_id` = ts.`id`
             WHERE tr.`case_card_id` = ? ORDER BY tr.`transitioned_at` ASC"
        );
        $tStmt->execute([$id]);

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

        // Entry stage = provided, else the first stage by order.
        $entryStageId = (int)($input['stage_id'] ?? 0);
        if (!$entryStageId) {
            $fs = $this->pdo->prepare(
                "SELECT `id` FROM `elr_pipeline_stages` WHERE `pipeline_id` = ? AND `tenant_id` = ?
                 ORDER BY `stage_order` ASC, `id` ASC LIMIT 1"
            );
            $fs->execute([$pipelineId, $this->tenantId]);
            $entryStageId = (int)$fs->fetchColumn();
        }

        $actor = is_array($this->currentUser) ? ($this->currentUser['full_name'] ?? 'Admin') : 'Admin';
        $ins = $this->pdo->prepare(
            "INSERT INTO `elr_case_cards` (`tenant_id`, `pipeline_id`, `employee_id`, `current_stage_id`, `status`, `entered_via`, `created_by`)
             VALUES (?, ?, ?, ?, 'Active', ?, ?)"
        );
        $ins->execute([$this->tenantId, $pipelineId, $employeeId, $entryStageId ?: null, $enteredVia, $actor]);
        $cardId = (int)$this->pdo->lastInsertId();

        // Log entry + fire the entry stage's document (if any).
        $this->logTransition($cardId, null, $entryStageId ?: null, $actor, 'Added to pipeline');
        $doc = $entryStageId ? $this->generateDocumentForStage($cardId, $employeeId, $entryStageId, $actor, $extra) : null;

        echo json_encode(['success' => true, 'card_id' => $cardId, 'generated_document' => $doc]);
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
}
