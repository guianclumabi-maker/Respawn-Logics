<?php

class SurveyController
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

    public function handleRequest($action)
    {
        if (!$this->currentUser) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        try {
            switch ($action) {
                case 'create_survey':
                    $this->createSurvey();
                    break;
                case 'launch_survey':
                    $this->launchSurvey();
                    break;
                case 'fetch_my_surveys':
                    $this->fetchMySurveys();
                    break;
                case 'fetch_admin_surveys':
                    $this->fetchAdminSurveys();
                    break;
                case 'fetch_survey':
                    $this->fetchSurvey();
                    break;
                case 'submit_survey':
                    $this->submitSurvey();
                    break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown action']);
                    break;
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    private function createSurvey()
    {
        if (!hasPermission('surveys.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $title = $input['title'] ?? 'Company Pulse Survey';
        $description = $input['description'] ?? '';
        $customQuestions = $input['questions'] ?? [];

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO surveys (tenant_id, title, description, status, created_by) VALUES (?, ?, ?, 'Draft', ?)");
            $stmt->execute([$this->tenantId, $title, $description, $this->currentUser['email']]);
            $surveyId = $this->pdo->lastInsertId();

            // Auto-inject eNPS question
            $qStmt = $this->pdo->prepare("INSERT INTO survey_questions (survey_id, question_text, question_type) VALUES (?, ?, 'eNPS')");
            $qStmt->execute([$surveyId, 'On a scale of 0-10, how likely are you to recommend us as a place to work?']);

            // Insert custom questions
            foreach ($customQuestions as $q) {
                $qText = trim($q['text'] ?? '');
                $qType = $q['type'] ?? 'Text';
                if (!empty($qText)) {
                    $qStmt->execute([$surveyId, $qText, $qType]);
                }
            }

            $this->pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to create survey']);
        }
    }

    private function launchSurvey()
    {
        if (!hasPermission('surveys.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $surveyId = $input['id'] ?? 0;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE surveys SET status = 'Active' WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$surveyId, $this->tenantId]);

            // Fetch active employees
            $empStmt = $this->pdo->prepare("SELECT email FROM users WHERE tenant_id = ? AND employment_status = 'Active'");
            $empStmt->execute([$this->tenantId]);
            $employees = $empStmt->fetchAll();

            $partStmt = $this->pdo->prepare("INSERT IGNORE INTO survey_participants (tenant_id, survey_id, user_email, has_completed) VALUES (?, ?, ?, FALSE)");
            
            foreach ($employees as $emp) {
                $partStmt->execute([$this->tenantId, $surveyId, $emp['email']]);
                
                // Blast Notification
                sendNotification(
                    $this->pdo, 
                    $this->tenantId, 
                    $emp['email'], 
                    "New Engagement Survey", 
                    "HR has launched a new Pulse Survey. Your feedback is completely anonymous.", 
                    "warning", 
                    "/pages/surveys.php"
                );
            }

            $this->pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to launch survey']);
        }
    }

    private function fetchMySurveys()
    {
        $stmt = $this->pdo->prepare("
            SELECT s.id, s.title, s.description, s.created_at, p.has_completed 
            FROM survey_participants p
            JOIN surveys s ON p.survey_id = s.id
            WHERE p.tenant_id = ? AND p.user_email = ? AND s.status = 'Active'
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$this->tenantId, $this->currentUser['email']]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    private function fetchAdminSurveys()
    {
        if (!hasPermission('surveys.manage')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM surveys WHERE tenant_id = ? ORDER BY created_at DESC");
        $stmt->execute([$this->tenantId]);
        $surveys = $stmt->fetchAll();

        $data = [];
        foreach ($surveys as $s) {
            // Get completion rate
            $partStmt = $this->pdo->prepare("SELECT COUNT(*) as total, SUM(has_completed) as completed FROM survey_participants WHERE survey_id = ?");
            $partStmt->execute([$s['id']]);
            $parts = $partStmt->fetch();
            $total = (int)($parts['total'] ?? 0);
            $completed = (int)($parts['completed'] ?? 0);
            
            $rate = $total > 0 ? round(($completed / $total) * 100) : 0;

            // Calculate eNPS
            // Promoters (9-10), Passives (7-8), Detractors (0-6)
            $enpsStmt = $this->pdo->prepare("
                SELECT response_value 
                FROM survey_responses r 
                JOIN survey_questions q ON r.question_id = q.id 
                WHERE r.survey_id = ? AND q.question_type = 'eNPS'
            ");
            $enpsStmt->execute([$s['id']]);
            $enpsResponses = $enpsStmt->fetchAll();

            $totalNps = count($enpsResponses);
            $promoters = 0;
            $detractors = 0;
            $enpsScore = null;

            if ($totalNps > 0) {
                foreach ($enpsResponses as $r) {
                    $val = (int)$r['response_value'];
                    if ($val >= 9) $promoters++;
                    else if ($val <= 6) $detractors++;
                }
                $enpsScore = round((($promoters / $totalNps) * 100) - (($detractors / $totalNps) * 100));
            }

            $data[] = [
                'id' => $s['id'],
                'title' => $s['title'],
                'status' => $s['status'],
                'created_at' => $s['created_at'],
                'completion_rate' => $rate,
                'enps' => $enpsScore,
                'responses' => $completed
            ];
        }

        echo json_encode(['success' => true, 'data' => $data]);
    }

    private function fetchSurvey()
    {
        $id = $_GET['id'] ?? 0;
        
        $stmt = $this->pdo->prepare("SELECT * FROM surveys WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $this->tenantId]);
        $survey = $stmt->fetch();

        if (!$survey) {
            echo json_encode(['success' => false, 'error' => 'Survey not found']);
            return;
        }

        $qStmt = $this->pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ?");
        $qStmt->execute([$id]);
        $questions = $qStmt->fetchAll();

        echo json_encode(['success' => true, 'data' => ['survey' => $survey, 'questions' => $questions]]);
    }

    private function submitSurvey()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $surveyId = $input['survey_id'] ?? 0;
        $answers = $input['answers'] ?? []; // [{question_id, value}]

        // Check if already completed
        $checkStmt = $this->pdo->prepare("SELECT has_completed FROM survey_participants WHERE survey_id = ? AND user_email = ?");
        $checkStmt->execute([$surveyId, $this->currentUser['email']]);
        $part = $checkStmt->fetch();

        if (!$part || $part['has_completed']) {
            echo json_encode(['success' => false, 'error' => 'Survey already completed or invalid access.']);
            return;
        }

        $this->pdo->beginTransaction();
        try {
            // Save answers completely anonymously (NO user_id stored)
            $ansStmt = $this->pdo->prepare("INSERT INTO survey_responses (survey_id, question_id, response_value) VALUES (?, ?, ?)");
            foreach ($answers as $a) {
                $ansStmt->execute([$surveyId, $a['question_id'], $a['value']]);
            }

            // Mark participant as completed
            $updateStmt = $this->pdo->prepare("UPDATE survey_participants SET has_completed = TRUE WHERE survey_id = ? AND user_email = ?");
            $updateStmt->execute([$surveyId, $this->currentUser['email']]);

            $this->pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to save responses']);
        }
    }
}
