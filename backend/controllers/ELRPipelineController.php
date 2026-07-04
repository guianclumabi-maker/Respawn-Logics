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
}
