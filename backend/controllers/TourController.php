<?php

/**
 * Guided-tour progress — tracks which first-run walkthroughs a user has completed so each
 * tour shows only once (unless replayed). Tenant + user scoped; every row is discriminated
 * by tenant_id AND user_id so one tenant/user can never see or affect another's progress.
 *
 * Routes (route=tours):
 *   GET  &action=status            -> { success, completed: ['payroll', ...] }
 *   GET  &action=status&tour=NAME  -> { success, completed: bool }
 *   POST &action=complete  {tour_name}  -> marks a tour done (idempotent upsert)
 *   POST &action=reset     {tour_name}  -> clears it so the tour replays
 */
class TourController
{
    private $pdo;
    private $currentUser;
    private $tenantId;
    private $userId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser() ?: null;
        $this->tenantId = is_array($this->currentUser) && isset($this->currentUser['tenant_id'])
            ? $this->currentUser['tenant_id']
            : ($_SESSION['tenant_id'] ?? null);
        $this->userId = is_array($this->currentUser) && isset($this->currentUser['id'])
            ? (int)$this->currentUser['id']
            : (int)($_SESSION['user_id'] ?? 0);
    }

    public function handleRequest($action)
    {
        // A logged-in user is required; tour progress is inherently per-user.
        if ($this->tenantId === null || $this->tenantId === '' || $this->userId <= 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve user context']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            switch ($action) {
                case 'status':
                    $this->status();
                    break;
                case 'complete':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->complete($input); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
                    break;
                case 'reset':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') { $this->reset($input); }
                    else { echo json_encode(['success' => false, 'error' => 'Invalid method']); }
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

    /** Return completed tours for this user. With ?tour=NAME, return a single boolean. */
    private function status()
    {
        $one = trim($_GET['tour'] ?? '');
        if ($one !== '') {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM `user_tour_progress` WHERE `tenant_id` = ? AND `user_id` = ? AND `tour_name` = ? LIMIT 1"
            );
            $stmt->execute([$this->tenantId, $this->userId, $one]);
            echo json_encode(['success' => true, 'completed' => (bool)$stmt->fetchColumn()]);
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT `tour_name` FROM `user_tour_progress` WHERE `tenant_id` = ? AND `user_id` = ?"
        );
        $stmt->execute([$this->tenantId, $this->userId]);
        echo json_encode(['success' => true, 'completed' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
    }

    private function complete($input)
    {
        $tour = trim($input['tour_name'] ?? '');
        if ($tour === '') {
            echo json_encode(['success' => false, 'error' => 'tour_name is required.']);
            return;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO `user_tour_progress` (`tenant_id`, `user_id`, `tour_name`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `completed_at` = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$this->tenantId, $this->userId, $tour]);
        echo json_encode(['success' => true, 'message' => 'Tour marked complete.']);
    }

    private function reset($input)
    {
        $tour = trim($input['tour_name'] ?? '');
        if ($tour === '') {
            echo json_encode(['success' => false, 'error' => 'tour_name is required.']);
            return;
        }
        $stmt = $this->pdo->prepare(
            "DELETE FROM `user_tour_progress` WHERE `tenant_id` = ? AND `user_id` = ? AND `tour_name` = ?"
        );
        $stmt->execute([$this->tenantId, $this->userId, $tour]);
        echo json_encode(['success' => true, 'message' => 'Tour reset.']);
    }
}
