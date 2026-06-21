<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/Mailer.php';
require_once __DIR__ . '/../utils/Storage.php';

class CandidatesController
{
    private const ALLOWED_STAGES = ['Applied', 'Review', 'Phone Screen', 'Interview', 'Offer', 'Hired', 'Rejected'];
    private const ALLOWED_JOB_STATUS = ['Open', 'Draft', 'Closed', 'On Hold'];
    private const ALLOWED_JOB_PRIORITY = ['Low', 'Normal', 'High', 'Urgent'];
    private const ALLOWED_EMP_TYPE = ['Full-Time', 'Part-Time', 'Contract', 'Internship'];

    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser() ?: null;
        $tenant = null;
        if (is_array($this->currentUser)
            && isset($this->currentUser['tenant_id'])
            && $this->currentUser['tenant_id'] !== ''
            && $this->currentUser['tenant_id'] !== null) {
            $tenant = $this->currentUser['tenant_id'];
        } elseif (isset($_SESSION['tenant_id'])
            && $_SESSION['tenant_id'] !== ''
            && $_SESSION['tenant_id'] !== null) {
            $tenant = $_SESSION['tenant_id'];
        }
        $this->tenantId = $tenant; // null when unresolved
    }

    public function handleRequest($action)
    {
        if ($this->tenantId === null || $this->tenantId === '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unable to resolve tenant context']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $input['action'] ?? $action;
        }

        if ($action !== 'current_user' && $action !== 'permissions') {
            requirePermission('ats.view');
        }

        // Action-specific permissions checked on employee_system database before USE respawn_logics
        if ($action === 'add_job' || $action === 'duplicate_job') {
            requirePermission('ats.create_job');
        } elseif ($action === 'update_job') {
            requirePermission('ats.edit_job');
        } elseif (
            $action === 'update_stage' || $action === 'bulk_advance' || $action === 'bulk_reject' || $action === 'bulk_delete' ||
            $action === 'add_candidate' || $action === 'update_candidate' || $action === 'add_application' ||
            $action === 'update_rating' || $action === 'add' || $action === 'add_interview' ||
            $action === 'update_interview' || $action === 'add_scorecard' || $action === 'add_note' ||
            $action === 'add_pool' || $action === 'update_pool' || $action === 'add_to_pool' ||
            $action === 'remove_from_pool' || $action === 'submit_approval' || $action === 'compute_ai_scores'
        ) {
            requirePermission('ats.edit');
        } elseif (
            $action === 'delete_candidate' || $action === 'delete_pool' || 
            $action === 'delete' || $action === 'resolve_approval'
        ) {
            requirePermission('ats.delete');
        } elseif ($action === 'upload_resume') {
            requirePermission('ats.edit');
        }

        try {
            switch ($action) {
                // GET
                case 'dashboard': $this->dashboard(); break;
                case 'jobs': $this->jobs(); break;
                case 'job': $this->job(); break;
                case 'candidates': $this->candidates(); break;
                case 'candidate': $this->candidate(); break;
                case 'interviews': $this->interviews(); break;
                case 'analytics': $this->analytics(); break;
                case 'talent_pools': $this->talentPools(); break;
                case 'pool': $this->pool(); break;
                case 'search': $this->search(); break;
                case 'ai_match': $this->aiMatch(); break;
                case 'ai_actions': $this->aiActions(); break;
                case 'activities': $this->activities(); break;
                case 'approvals': $this->approvals(); break;
                case 'permissions': $this->permissions(); break;
                case 'current_user': $this->currentUserAction(); break;
                case 'download_resume': $this->downloadResume(); break;

                // POST
                case 'add_job': $this->addJob($input); break;
                case 'update_job': $this->updateJob($input); break;
                case 'duplicate_job': $this->duplicateJob($input); break;
                case 'add_candidate': $this->addCandidate($input); break;
                case 'update_candidate': $this->updateCandidate($input); break;
                case 'add_application': $this->addApplication($input); break;
                case 'update_stage': $this->updateStage($input); break;
                case 'hire_candidate': $this->hireCandidate($input); break;
                case 'update_rating': $this->updateRating($input); break;
                case 'bulk_advance': $this->bulkAdvance($input); break;
                case 'bulk_reject': $this->bulkReject($input); break;
                case 'bulk_delete': $this->bulkDelete($input); break;
                case 'delete_candidate': $this->deleteCandidate($input); break;
                case 'add_interview': $this->addInterview($input); break;
                case 'update_interview': $this->updateInterview($input); break;
                case 'add_scorecard': $this->addScorecard($input); break;
                case 'add_note': $this->addNote($input); break;
                case 'add_pool': $this->addPool($input); break;
                case 'update_pool': $this->updatePool($input); break;
                case 'add_to_pool': $this->addToPool($input); break;
                case 'remove_from_pool': $this->removeFromPool($input); break;
                case 'delete_pool': $this->deletePool($input); break;
                case 'submit_approval': $this->submitApproval($input); break;
                case 'resolve_approval': $this->resolveApproval($input); break;
                case 'compute_ai_scores': $this->computeAiScores($input); break;
                case 'upload_resume': $this->uploadResume(); break;
                case 'add': $this->legacyAdd($input); break;
                case 'delete': $this->legacyDelete($input); break;

                default:
                    if ($action !== '') {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
                        exit;
                    } else {
                        $this->fallbackCandidates();
                    }
                    break;
            }
        } catch (\Exception $e) {
            error_log('CandidatesController error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'An error occurred. Please try again.']);
        }
    }

    // --- HELPERS ---
    private function logActivity($action, $description, $candidateId = null, $jobId = null, $applicationId = null, $actor = null) {
        $actor = $actor ?? ($this->currentUser['full_name'] ?? 'System');
        try {
            $stmt = $this->pdo->prepare("INSERT INTO `activities` (`tenant_id`, `candidate_id`, `job_id`, `application_id`, `action`, `description`, `actor_name`) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$this->tenantId, $candidateId, $jobId, $applicationId, $action, $description, $actor]);
        } catch (PDOException $e) {
            // Ignore missing activities table error for now to prevent crashes
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }

    private function updateMatchScore($appId, $candidateId, $jobId) {
        require_once __DIR__ . '/../services/Scoring/ScoringProvider.php';
        require_once __DIR__ . '/../services/Scoring/HeuristicScoringProvider.php';
        
        $candidate = $this->pdo->prepare("SELECT * FROM `candidate_profiles` WHERE `id` = ?");
        $candidate->execute([$candidateId]);
        $c = $candidate->fetch();
        
        $job = $this->pdo->prepare("SELECT * FROM `jobs` WHERE `id` = ?");
        $job->execute([$jobId]);
        $j = $job->fetch();
        
        $provider = new \Respawn\Services\Scoring\HeuristicScoringProvider();
        $match = $provider->score($c ?: [], $j ?: []);
        
        $stmt = $this->pdo->prepare("UPDATE `candidate_applications` SET `ai_match_score` = ?, `score_breakdown` = ?, `score_source` = ?, `scored_at` = NOW() WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$match['total'], json_encode($match['breakdown']), $match['source'], $appId, $this->tenantId]);
        
        return $match;
    }

    private function computePipelineHealth($jobId) {
        $stages = $this->pdo->prepare("SELECT `stage`, COUNT(*) as cnt FROM `candidate_applications` WHERE `job_id` = ? AND `rejected_at` IS NULL GROUP BY `stage`");
        $stages->execute([$jobId]);
        $counts = [];
        $total = 0;
        foreach ($stages->fetchAll() as $r) {
            $counts[$r['stage']] = (int)$r['cnt'];
            $total += (int)$r['cnt'];
        }
        
        $applied = $counts['Applied'] ?? 0;
        $review = $counts['Review'] ?? 0;
        $phoneScreen = $counts['Phone Screen'] ?? 0;
        $interview = $counts['Interview'] ?? 0;
        $offer = $counts['Offer'] ?? 0;
        $hired = $counts['Hired'] ?? 0;
        
        $score = 50;
        if ($total >= 5) $score += 10;
        if ($total >= 15) $score += 10;
        if ($interview >= 2) $score += 15;
        if ($offer >= 1) $score += 15;
        if ($hired >= 1) $score += 10;
        
        $stuckStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_applications` WHERE `job_id` = ? AND DATEDIFF(NOW(), `stage_entered_at`) >= 7 AND `stage` NOT IN ('Hired', 'Rejected') AND `rejected_at` IS NULL");
        $stuckStmt->execute([$jobId]);
        $stuck = (int)$stuckStmt->fetchColumn();
        if ($stuck > 0) $score -= min($stuck * 5, 25);
        
        $score = max(0, min(100, $score));
        
        if ($score >= 70) $status = 'Healthy';
        elseif ($score >= 40) $status = 'Needs Attention';
        else $status = 'Critical';
        
        $velocityStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_applications` WHERE `job_id` = ? AND `applied_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $velocityStmt->execute([$jobId]);
        $weeklyVelocity = (int)$velocityStmt->fetchColumn();
        
        return [
            'total' => $total, 'applied' => $applied, 'review' => $review, 'phone_screen' => $phoneScreen,
            'interview' => $interview, 'offer' => $offer, 'hired' => $hired, 'stuck' => $stuck,
            'score' => $score, 'status' => $status, 'velocity' => $weeklyVelocity
        ];
    }

    private function computeBatchPipelineHealth($jobIds) {
        if (empty($jobIds)) return [];
        $inClause = implode(',', array_fill(0, count($jobIds), '?'));
        
        $stagesStmt = $this->pdo->prepare("SELECT `job_id`,
            SUM(CASE WHEN `stage` = 'Applied' THEN 1 ELSE 0 END) as applied,
            SUM(CASE WHEN `stage` = 'Review' THEN 1 ELSE 0 END) as review,
            SUM(CASE WHEN `stage` = 'Phone Screen' THEN 1 ELSE 0 END) as phone_screen,
            SUM(CASE WHEN `stage` = 'Interview' THEN 1 ELSE 0 END) as interview,
            SUM(CASE WHEN `stage` = 'Offer' THEN 1 ELSE 0 END) as offer,
            SUM(CASE WHEN `stage` = 'Hired' THEN 1 ELSE 0 END) as hired,
            COUNT(*) as total
            FROM `candidate_applications` WHERE `job_id` IN ($inClause) AND `rejected_at` IS NULL GROUP BY `job_id`");
        $stagesStmt->execute($jobIds);
        $stageData = [];
        foreach ($stagesStmt->fetchAll() as $r) { $stageData[$r['job_id']] = $r; }
        
        $stuckStmt = $this->pdo->prepare("SELECT `job_id`, COUNT(*) as stuck
            FROM `candidate_applications` WHERE `job_id` IN ($inClause) AND DATEDIFF(NOW(), `stage_entered_at`) >= 7 AND `stage` NOT IN ('Hired', 'Rejected') AND `rejected_at` IS NULL GROUP BY `job_id`");
        $stuckStmt->execute($jobIds);
        $stuckData = [];
        foreach ($stuckStmt->fetchAll() as $r) { $stuckData[$r['job_id']] = (int)$r['stuck']; }
        
        $velocityStmt = $this->pdo->prepare("SELECT `job_id`, COUNT(*) as velocity
            FROM `candidate_applications` WHERE `job_id` IN ($inClause) AND `applied_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY `job_id`");
        $velocityStmt->execute($jobIds);
        $velocityData = [];
        foreach ($velocityStmt->fetchAll() as $r) { $velocityData[$r['job_id']] = (int)$r['velocity']; }
        
        $results = [];
        foreach ($jobIds as $jobId) {
            $jobId = (int)$jobId;
            $data = $stageData[$jobId] ?? ['total'=>0, 'applied'=>0, 'review'=>0, 'phone_screen'=>0, 'interview'=>0, 'offer'=>0, 'hired'=>0];
            $total = (int)$data['total'];
            $stuck = $stuckData[$jobId] ?? 0;
            $velocity = $velocityData[$jobId] ?? 0;
            $score = 50;
            if ($total >= 5) $score += 10;
            if ($total >= 15) $score += 10;
            if ((int)$data['interview'] >= 2) $score += 15;
            if ((int)$data['offer'] >= 1) $score += 15;
            if ((int)$data['hired'] >= 1) $score += 10;
            if ($stuck > 0) $score -= min($stuck * 5, 25);
            $score = max(0, min(100, $score));
            $status = $score >= 70 ? 'Healthy' : ($score >= 40 ? 'Needs Attention' : 'Critical');
            
            $results[$jobId] = ['total'=>$total, 'applied'=>(int)$data['applied'], 'review'=>(int)$data['review'], 'phone_screen'=>(int)$data['phone_screen'], 'interview'=>(int)$data['interview'], 'offer'=>(int)$data['offer'], 'hired'=>(int)$data['hired'], 'stuck'=>$stuck, 'score'=>$score, 'status'=>$status, 'velocity'=>$velocity];
        }
        return $results;
    }

    private function computeBatchLastActivity($jobIds) {
        if (empty($jobIds)) return [];
        $inClause = implode(',', array_fill(0, count($jobIds), '?'));
        $stmt = $this->pdo->prepare("SELECT `job_id`, MAX(`created_at`) as last_act FROM `activities` WHERE `job_id` IN ($inClause) GROUP BY `job_id`");
        $stmt->execute($jobIds);
        $results = [];
        foreach ($stmt->fetchAll() as $r) { $results[$r['job_id']] = $r['last_act']; }
        return $results;
    }

    // --- GET METHODS ---
    private function dashboard() {
        $stmtAwaiting = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_applications` WHERE `stage` = 'Applied' AND `rejected_at` IS NULL AND `tenant_id` = ?");
        $stmtAwaiting->execute([$this->tenantId]);
        $awaitingReview = (int)$stmtAwaiting->fetchColumn();

        $stmtInterviewsToday = $this->pdo->prepare("SELECT COUNT(*) FROM `interviews` WHERE DATE(`scheduled_at`) = CURDATE() AND `status` = 'Scheduled' AND `tenant_id` = ?");
        $stmtInterviewsToday->execute([$this->tenantId]);
        $interviewsToday = (int)$stmtInterviewsToday->fetchColumn();

        $stmtOffers = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_applications` WHERE `stage` = 'Offer' AND `hired_at` IS NULL AND `rejected_at` IS NULL AND `tenant_id` = ?");
        $stmtOffers->execute([$this->tenantId]);
        $pendingOffers = (int)$stmtOffers->fetchColumn();

        $stmtMissing = $this->pdo->prepare("SELECT COUNT(*) FROM `interviews` i LEFT JOIN `scorecards` s ON s.`interview_id` = i.`id` WHERE i.`status` = 'Completed' AND s.`id` IS NULL AND i.`tenant_id` = ?");
        $stmtMissing->execute([$this->tenantId]);
        $missingScorecards = (int)$stmtMissing->fetchColumn();

        $stmtApprovals = $this->pdo->prepare("SELECT COUNT(*) FROM `approvals` WHERE `status` = 'Pending' AND `tenant_id` = ?");
        $stmtApprovals->execute([$this->tenantId]);
        $pendingApprovals = (int)$stmtApprovals->fetchColumn();
        
        $slaAlerts = [];
        $stuckStmt = $this->pdo->prepare("SELECT ca.*, cp.`name` as candidate_name, j.`title` as job_title FROM `candidate_applications` ca JOIN `candidate_profiles` cp ON cp.`id` = ca.`candidate_id` JOIN `jobs` j ON j.`id` = ca.`job_id` WHERE DATEDIFF(NOW(), ca.`stage_entered_at`) >= 5 AND ca.`stage` NOT IN ('Hired') AND ca.`rejected_at` IS NULL AND ca.`tenant_id` = ? ORDER BY ca.`stage_entered_at` ASC LIMIT 10");
        $stuckStmt->execute([$this->tenantId]);
        foreach ($stuckStmt->fetchAll() as $s) {
            $days = (int)((time() - strtotime($s['stage_entered_at'])) / 86400);
            $slaAlerts[] = ['type' => 'stuck_candidate', 'severity' => $days >= 10 ? 'critical' : 'warning', 'message' => "{$s['candidate_name']} waiting in {$s['stage']} for {$days} days", 'job_title' => $s['job_title'], 'application_id' => (int)$s['id'], 'candidate_id' => (int)$s['candidate_id'], 'job_id' => (int)$s['job_id'], 'days' => $days];
        }
        
        $msStmt = $this->pdo->prepare("SELECT i.*, cp.`name` as candidate_name, j.`title` as job_title FROM `interviews` i LEFT JOIN `scorecards` s ON s.`interview_id` = i.`id` JOIN `candidate_profiles` cp ON cp.`id` = i.`candidate_id` JOIN `jobs` j ON j.`id` = i.`job_id` WHERE i.`status` = 'Completed' AND s.`id` IS NULL AND i.`tenant_id` = ? ORDER BY i.`scheduled_at` DESC LIMIT 10");
        $msStmt->execute([$this->tenantId]);
        foreach ($msStmt->fetchAll() as $ms) {
            $slaAlerts[] = ['type' => 'missing_scorecard', 'severity' => 'warning', 'message' => "Scorecard missing for {$ms['candidate_name']}'s {$ms['interview_type']} interview", 'job_title' => $ms['job_title'], 'interview_id' => (int)$ms['id'], 'candidate_id' => (int)$ms['candidate_id']];
        }
        
        $allOpenJobsStmt = $this->pdo->prepare("SELECT * FROM `jobs` WHERE `status` = 'Open' AND `tenant_id` = ? ORDER BY `created_at` DESC");
        $allOpenJobsStmt->execute([$this->tenantId]);
        $allOpenJobs = $allOpenJobsStmt->fetchAll();
        
        $openJobIds = array_column($allOpenJobs, 'id');
        $batchHealth = $this->computeBatchPipelineHealth($openJobIds);
        $batchLastAct = $this->computeBatchLastActivity($openJobIds);
        $jobsHealth = [];
        foreach ($allOpenJobs as $job) {
            $health = $batchHealth[$job['id']] ?? $this->computePipelineHealth($job['id']);
            $daysOpen = (int)((time() - strtotime($job['created_at'])) / 86400);
            $lastActivity = $batchLastAct[$job['id']] ?? null;
            $jobsHealth[] = ['id' => (int)$job['id'], 'title' => $job['title'], 'department' => $job['department'], 'location' => $job['location'], 'employment_type' => $job['employment_type'], 'priority' => $job['priority'], 'hiring_manager' => $job['hiring_manager'], 'assigned_recruiter' => $job['assigned_recruiter'], 'days_open' => $daysOpen, 'days_since_activity' => $lastActivity ? (int)((time() - strtotime($lastActivity)) / 86400) : $daysOpen, 'health' => $health];
            if ($health['status'] === 'Critical') {
                $slaAlerts[] = ['type' => 'critical_pipeline', 'severity' => 'critical', 'message' => "{$job['title']} has critical pipeline health (score: {$health['score']})", 'job_id' => (int)$job['id'], 'job_title' => $job['title']];
            }
        }
        
        $stmtAct = $this->pdo->prepare("SELECT a.*, cp.`name` as candidate_name, j.`title` as job_title FROM `activities` a LEFT JOIN `candidate_profiles` cp ON cp.`id` = a.`candidate_id` LEFT JOIN `jobs` j ON j.`id` = a.`job_id` WHERE a.`tenant_id` = ? ORDER BY a.`created_at` DESC LIMIT 10");
        $stmtAct->execute([$this->tenantId]);
        $activities = $stmtAct->fetchAll();
        foreach ($activities as &$act) { $act['id'] = (int)$act['id']; $act['time_ago'] = humanTimeAgo($act['created_at']); }
        
        $upcomingStmt = $this->pdo->prepare("SELECT i.*, cp.`name` as candidate_name, j.`title` as job_title FROM `interviews` i JOIN `candidate_profiles` cp ON cp.`id` = i.`candidate_id` JOIN `jobs` j ON j.`id` = i.`job_id` WHERE i.`scheduled_at` >= NOW() AND i.`status` = 'Scheduled' AND i.`tenant_id` = ? ORDER BY i.`scheduled_at` ASC LIMIT 5");
        $upcomingStmt->execute([$this->tenantId]);
        $upcoming = $upcomingStmt->fetchAll();
        foreach ($upcoming as &$u) { $u['id'] = (int)$u['id']; $u['formatted_date'] = date('M j, Y', strtotime($u['scheduled_at'])); $u['formatted_time'] = date('g:i A', strtotime($u['scheduled_at'])); }
        
        $stmtCandCount = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_profiles` WHERE `tenant_id` = ?");
        $stmtCandCount->execute([$this->tenantId]);
        $candCount = (int)$stmtCandCount->fetchColumn();

        $stmtJobsCount = $this->pdo->prepare("SELECT COUNT(*) FROM `jobs` WHERE `status` = 'Open' AND `tenant_id` = ?");
        $stmtJobsCount->execute([$this->tenantId]);
        $jobsCount = (int)$stmtJobsCount->fetchColumn();

        $stmtHiredCount = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_applications` WHERE `stage` = 'Hired' AND `tenant_id` = ?");
        $stmtHiredCount->execute([$this->tenantId]);
        $hiredCount = (int)$stmtHiredCount->fetchColumn();

        echo json_encode([
            'success' => true,
            'action_summary' => ['awaiting_review' => $awaitingReview, 'interviews_today' => $interviewsToday, 'pending_offers' => $pendingOffers, 'missing_scorecards' => $missingScorecards, 'pending_approvals' => $pendingApprovals],
            'sla_alerts' => $slaAlerts, 'jobs_health' => $jobsHealth, 'activities' => $activities, 'upcoming_interviews' => $upcoming,
            'totals' => [
                'candidates' => $candCount,
                'open_jobs' => $jobsCount,
                'hired' => $hiredCount
            ]
        ]);
        exit;
    }

    private function jobs() {
        $where = ["j.tenant_id = ?"]; $params = [$this->tenantId];
        $page = max(1, (int)($_GET['page'] ?? 1)); $limit = min(100, max(10, (int)($_GET['limit'] ?? 50))); $offset = ($page - 1) * $limit;
        if (!empty($_GET['status'])) { $where[] = "j.`status` = ?"; $params[] = $_GET['status']; }
        if (!empty($_GET['department'])) { $where[] = "j.`department` = ?"; $params[] = $_GET['department']; }
        if (!empty($_GET['priority'])) { $where[] = "j.`priority` = ?"; $params[] = $_GET['priority']; }
        if (!empty($_GET['search'])) { $where[] = "(j.`title` LIKE ? OR j.`department` LIKE ? OR j.`location` LIKE ?)"; $params = array_merge($params, ["%{$_GET['search']}%", "%{$_GET['search']}%", "%{$_GET['search']}%"]); }
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `jobs` j $whereClause");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();
        
        $stmt = $this->pdo->prepare("SELECT j.* FROM `jobs` j $whereClause ORDER BY j.`created_at` DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $jobs = $stmt->fetchAll();
        $fetchedJobIds = array_column($jobs, 'id');
        $batchHealth = $this->computeBatchPipelineHealth($fetchedJobIds);
        $batchLastAct = $this->computeBatchLastActivity($fetchedJobIds);
        foreach ($jobs as &$job) {
            $job['id'] = (int)$job['id'];
            $job['health'] = $batchHealth[$job['id']] ?? $this->computePipelineHealth($job['id']);
            $daysOpen = (int)((time() - strtotime($job['created_at'])) / 86400);
            $job['days_open'] = $daysOpen;
            $lastActivity = $batchLastAct[$job['id']] ?? null;
            $job['days_since_activity'] = $lastActivity ? (int)((time() - strtotime($lastActivity)) / 86400) : $daysOpen;
            $job['formatted_date'] = date('M j, Y', strtotime($job['created_at']));
        }
        $deptStmt = $this->pdo->prepare("SELECT DISTINCT `department` FROM `jobs` WHERE `tenant_id` = ? AND `department` IS NOT NULL AND `department` != '' ORDER BY `department`");
        $deptStmt->execute([$this->tenantId]);
        $departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'jobs' => $jobs, 'departments' => $departments, 'total' => $totalCount, 'page' => $page, 'limit' => $limit]);
        exit;
    }

    private function job() {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT * FROM `jobs` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$id, $this->tenantId]);
        $job = $stmt->fetch();
        if (!$job) { echo json_encode(['success' => false, 'error' => 'Job not found']); exit; }
        $job['id'] = (int)$job['id'];
        $job['health'] = $this->computePipelineHealth($job['id']);
        $job['days_open'] = (int)((time() - strtotime($job['created_at'])) / 86400);
        $appsStmt = $this->pdo->prepare("SELECT ca.*, cp.`name`, cp.`email`, cp.`phone`, cp.`location` as candidate_location, cp.`skills`, cp.`experience_years`, cp.`source` as candidate_source, cp.`assigned_recruiter` as recruiter, cp.`assigned_hiring_manager` as hiring_mgr FROM `candidate_applications` ca JOIN `candidate_profiles` cp ON cp.`id` = ca.`candidate_id` WHERE ca.`job_id` = ? AND ca.`tenant_id` = ? ORDER BY ca.`applied_at` DESC");
        $appsStmt->execute([$id, $this->tenantId]);
        $applications = $appsStmt->fetchAll();
        foreach ($applications as &$app) {
            $app['id'] = (int)$app['id']; $app['candidate_id'] = (int)$app['candidate_id']; $app['rating'] = (int)$app['rating'];
            $app['ai_match_score'] = $app['ai_match_score'] !== null ? (int)$app['ai_match_score'] : null;
            $app['score_breakdown'] = !empty($app['score_breakdown']) ? json_decode($app['score_breakdown'], true) : null;
            $app['days_in_stage'] = (int)((time() - strtotime($app['stage_entered_at'])) / 86400);
            $app['tags'] = !empty($app['skills']) ? array_map('trim', explode(',', $app['skills'])) : [];
            $app['formatted_applied'] = date('M j, Y', strtotime($app['applied_at']));
        }
        $job['applications'] = $applications;
        echo json_encode(['success' => true, 'job' => $job]);
        exit;
    }

    private function candidates() {
        $search = $_GET['search'] ?? ''; $status = $_GET['status'] ?? ''; $source = $_GET['source'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1)); $limit = min(100, max(10, (int)($_GET['limit'] ?? 50))); $offset = ($page - 1) * $limit;
        $where = ["cp.tenant_id = ?"]; $params = [$this->tenantId];
        if ($search) { $where[] = "(cp.`name` LIKE ? OR cp.`email` LIKE ? OR cp.`skills` LIKE ? OR cp.`location` LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]); }
        if ($status) { $where[] = "cp.`status` = ?"; $params[] = $status; }
        if ($source) { $where[] = "cp.`source` = ?"; $params[] = $source; }
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_profiles` cp $whereClause");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT cp.*, (SELECT COUNT(*) FROM `candidate_applications` WHERE `candidate_id` = cp.`id`) as application_count, (SELECT COUNT(*) FROM `pool_members` WHERE `candidate_id` = cp.`id`) as pool_count FROM `candidate_profiles` cp $whereClause ORDER BY cp.`last_activity_at` DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $candidates = $stmt->fetchAll();
        foreach ($candidates as &$c) {
            $c['id'] = (int)$c['id']; $c['experience_years'] = (int)$c['experience_years'];
            $c['application_count'] = (int)$c['application_count']; $c['pool_count'] = (int)$c['pool_count'];
            $c['tags'] = !empty($c['tags']) ? array_map('trim', explode(',', $c['tags'])) : [];
            $c['skills_array'] = !empty($c['skills']) ? array_map('trim', explode(',', $c['skills'])) : [];
        }
        echo json_encode(['success' => true, 'candidates' => $candidates, 'total' => $totalCount, 'page' => $page, 'limit' => $limit]);
        exit;
    }

    private function candidate() {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT * FROM `candidate_profiles` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$id, $this->tenantId]);
        $candidate = $stmt->fetch();
        if (!$candidate) { echo json_encode(['success' => false, 'error' => 'Candidate not found']); exit; }
        $candidate['id'] = (int)$candidate['id'];
        $candidate['tags'] = !empty($candidate['tags']) ? array_map('trim', explode(',', $candidate['tags'])) : [];
        $candidate['skills_array'] = !empty($candidate['skills']) ? array_map('trim', explode(',', $candidate['skills'])) : [];
        if (!empty($candidate['resume_filename'])) {
            $candidate['resume_download_url'] = '../api/index.php?route=candidates&action=download_resume&id=' . $candidate['id'];
        }
        $appsStmt = $this->pdo->prepare("SELECT ca.*, j.`title` as job_title, j.`department`, j.`location` as job_location FROM `candidate_applications` ca JOIN `jobs` j ON j.`id` = ca.`job_id` WHERE ca.`candidate_id` = ? AND ca.`tenant_id` = ? ORDER BY ca.`applied_at` DESC");
        $appsStmt->execute([$id, $this->tenantId]);
        $candidate['applications'] = $appsStmt->fetchAll();
        foreach ($candidate['applications'] as &$app) {
            $app['id'] = (int)$app['id']; $app['days_in_stage'] = (int)((time() - strtotime($app['stage_entered_at'])) / 86400);
            $app['ai_match_score'] = $app['ai_match_score'] !== null ? (int)$app['ai_match_score'] : null;
            $app['score_breakdown'] = !empty($app['score_breakdown']) ? json_decode($app['score_breakdown'], true) : null;
            $app['formatted_applied'] = date('M j, Y', strtotime($app['applied_at']));
        }
        $intStmt = $this->pdo->prepare("SELECT i.*, j.`title` as job_title FROM `interviews` i JOIN `jobs` j ON j.`id` = i.`job_id` WHERE i.`candidate_id` = ? AND i.`tenant_id` = ? ORDER BY i.`scheduled_at` DESC");
        $intStmt->execute([$id, $this->tenantId]);
        $candidate['interviews'] = $intStmt->fetchAll();
        foreach ($candidate['interviews'] as &$iv) {
            $iv['id'] = (int)$iv['id']; $iv['formatted_date'] = date('M j, Y', strtotime($iv['scheduled_at'])); $iv['formatted_time'] = date('g:i A', strtotime($iv['scheduled_at']));
            $scStmt = $this->pdo->prepare("SELECT * FROM `scorecards` WHERE `interview_id` = ? AND `tenant_id` = ?"); $scStmt->execute([$iv['id'], $this->tenantId]);
            $iv['scorecards'] = $scStmt->fetchAll();
        }
        $notesStmt = $this->pdo->prepare("SELECT * FROM `candidate_notes` WHERE `candidate_id` = ? AND `tenant_id` = ? ORDER BY `created_at` DESC"); $notesStmt->execute([$id, $this->tenantId]);
        $candidate['notes'] = $notesStmt->fetchAll();
        $poolsStmt = $this->pdo->prepare("SELECT tp.*, pm.`added_at`, pm.`added_by` FROM `pool_members` pm JOIN `talent_pools` tp ON tp.`id` = pm.`pool_id` WHERE pm.`candidate_id` = ? AND pm.`tenant_id` = ?"); $poolsStmt->execute([$id, $this->tenantId]);
        $candidate['pools'] = $poolsStmt->fetchAll();
        $actStmt = $this->pdo->prepare("SELECT * FROM `activities` WHERE `candidate_id` = ? AND `tenant_id` = ? ORDER BY `created_at` DESC LIMIT 20"); $actStmt->execute([$id, $this->tenantId]);
        $candidate['activity_log'] = $actStmt->fetchAll();
        foreach ($candidate['activity_log'] as &$a) { $a['time_ago'] = humanTimeAgo($a['created_at']); }
        echo json_encode(['success' => true, 'candidate' => $candidate]);
        exit;
    }

    private function interviews() {
        $where = ["i.tenant_id = ?"]; $params = [$this->tenantId];
        if (!empty($_GET['status'])) { $where[] = "i.`status` = ?"; $params[] = $_GET['status']; }
        if (!empty($_GET['job_id'])) { $where[] = "i.`job_id` = ?"; $params[] = (int)$_GET['job_id']; }
        if (!empty($_GET['date_from'])) { $where[] = "DATE(i.`scheduled_at`) >= ?"; $params[] = $_GET['date_from']; }
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->pdo->prepare("SELECT i.*, cp.`name` as candidate_name, cp.`email` as candidate_email, j.`title` as job_title, (SELECT COUNT(*) FROM `scorecards` WHERE `interview_id` = i.`id`) as scorecard_count FROM `interviews` i JOIN `candidate_profiles` cp ON cp.`id` = i.`candidate_id` JOIN `jobs` j ON j.`id` = i.`job_id` $whereClause ORDER BY i.`scheduled_at` ASC");
        $stmt->execute($params);
        $interviews = $stmt->fetchAll();
        foreach ($interviews as &$iv) {
            $iv['id'] = (int)$iv['id']; $iv['candidate_id'] = (int)$iv['candidate_id']; $iv['job_id'] = (int)$iv['job_id']; $iv['scorecard_count'] = (int)$iv['scorecard_count'];
            $iv['formatted_date'] = date('M j, Y', strtotime($iv['scheduled_at'])); $iv['formatted_time'] = date('g:i A', strtotime($iv['scheduled_at']));
            $iv['is_today'] = date('Y-m-d', strtotime($iv['scheduled_at'])) === date('Y-m-d'); $iv['is_past'] = strtotime($iv['scheduled_at']) < time();
        }
        echo json_encode(['success' => true, 'interviews' => $interviews]);
        exit;
    }

    private function analytics() {
        $stmtTimeToHire = $this->pdo->prepare("SELECT AVG(DATEDIFF(`hired_at`, `applied_at`)) FROM `candidate_applications` WHERE `hired_at` IS NOT NULL AND `tenant_id` = ?");
        $stmtTimeToHire->execute([$this->tenantId]);
        $avgTimeToHire = round((float)$stmtTimeToHire->fetchColumn(), 1) ?: 0;

        $stmtTimeToFill = $this->pdo->prepare("SELECT AVG(DATEDIFF(ca.`hired_at`, j.`created_at`)) FROM `candidate_applications` ca JOIN `jobs` j ON j.`id` = ca.`job_id` WHERE ca.`hired_at` IS NOT NULL AND ca.`tenant_id` = ?");
        $stmtTimeToFill->execute([$this->tenantId]);
        $avgTimeToFill = round((float)$stmtTimeToFill->fetchColumn(), 1) ?: 0;

        $stmtOffersTotal = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_applications` WHERE (`stage` IN ('Offer', 'Hired') OR (`rejected_at` IS NOT NULL AND `stage` = 'Offer')) AND `tenant_id` = ?");
        $stmtOffersTotal->execute([$this->tenantId]);
        $offersTotal = (int)$stmtOffersTotal->fetchColumn();

        $stmtOffersAccepted = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_applications` WHERE `stage` = 'Hired' AND `tenant_id` = ?");
        $stmtOffersAccepted->execute([$this->tenantId]);
        $offersAccepted = (int)$stmtOffersAccepted->fetchColumn();

        $offerAcceptanceRate = $offersTotal > 0 ? round(($offersAccepted / $offersTotal) * 100) : 0;

        $stmtSourcePerf = $this->pdo->prepare("SELECT cp.`source`, COUNT(*) as total, SUM(CASE WHEN ca.`stage` = 'Hired' THEN 1 ELSE 0 END) as hired FROM `candidate_profiles` cp LEFT JOIN `candidate_applications` ca ON ca.`candidate_id` = cp.`id` WHERE cp.`tenant_id` = ? GROUP BY cp.`source` ORDER BY total DESC");
        $stmtSourcePerf->execute([$this->tenantId]);
        $sourcePerformance = $stmtSourcePerf->fetchAll();
        foreach ($sourcePerformance as &$sp) { $sp['total'] = (int)$sp['total']; $sp['hired'] = (int)$sp['hired']; $sp['conversion_rate'] = $sp['total'] > 0 ? round(($sp['hired'] / $sp['total']) * 100) : 0; }

        $funnel = [];
        $stmtFunnel = $this->pdo->prepare("SELECT `stage`, COUNT(*) as cnt FROM `candidate_applications` WHERE `rejected_at` IS NULL AND `tenant_id` = ? GROUP BY `stage`");
        $stmtFunnel->execute([$this->tenantId]);
        foreach ($stmtFunnel->fetchAll() as $f) { $funnel[$f['stage']] = (int)$f['cnt']; }

        $stmtDurations = $this->pdo->prepare("SELECT `stage`, AVG(DATEDIFF(NOW(), `stage_entered_at`)) as avg_days, COUNT(*) as cnt FROM `candidate_applications` WHERE `rejected_at` IS NULL AND `stage` NOT IN ('Hired') AND `tenant_id` = ? GROUP BY `stage` ORDER BY avg_days DESC");
        $stmtDurations->execute([$this->tenantId]);
        $stageDurations = $stmtDurations->fetchAll();
        foreach ($stageDurations as &$sd) { $sd['avg_days'] = round((float)$sd['avg_days'], 1); $sd['cnt'] = (int)$sd['cnt']; }

        $stmtVolume = $this->pdo->prepare("SELECT DATE_FORMAT(`applied_at`, '%Y-%m') as month, COUNT(*) as cnt FROM `candidate_applications` WHERE `applied_at` >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND `tenant_id` = ? GROUP BY month ORDER BY month ASC");
        $stmtVolume->execute([$this->tenantId]);
        $applicationVolume = $stmtVolume->fetchAll();
        foreach ($applicationVolume as &$av) { $av['cnt'] = (int)$av['cnt']; $av['label'] = date('M', strtotime($av['month'] . '-01')); }

        $stmtJobMetrics = $this->pdo->prepare("SELECT j.`id`, j.`title`, j.`status`, j.`department`, COUNT(ca.`id`) as total_candidates, (SELECT COUNT(*) FROM `candidate_applications` WHERE `job_id` = j.`id` AND `stage` = 'Hired') as hired, DATEDIFF(NOW(), j.`created_at`) as days_open FROM `jobs` j LEFT JOIN `candidate_applications` ca ON ca.`job_id` = j.`id` WHERE j.`tenant_id` = ? GROUP BY j.`id` ORDER BY j.`created_at` DESC");
        $stmtJobMetrics->execute([$this->tenantId]);
        $jobMetrics = $stmtJobMetrics->fetchAll();
        foreach ($jobMetrics as &$jm) { $jm['id'] = (int)$jm['id']; $jm['total_candidates'] = (int)$jm['total_candidates']; $jm['hired'] = (int)$jm['hired']; $jm['days_open'] = (int)$jm['days_open']; $jm['conversion_rate'] = $jm['total_candidates'] > 0 ? round(($jm['hired'] / $jm['total_candidates']) * 100) : 0; }

        $stmtActivePipe = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_applications` WHERE `stage` NOT IN ('Hired') AND `rejected_at` IS NULL AND `tenant_id` = ?");
        $stmtActivePipe->execute([$this->tenantId]);
        $activePipeline = (int)$stmtActivePipe->fetchColumn();

        $stmtTotalCands = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_profiles` WHERE `tenant_id` = ?");
        $stmtTotalCands->execute([$this->tenantId]);
        $totalCandidatesCount = (int)$stmtTotalCands->fetchColumn();

        $stmtOpenJobs = $this->pdo->prepare("SELECT COUNT(*) FROM `jobs` WHERE `status` = 'Open' AND `tenant_id` = ?");
        $stmtOpenJobs->execute([$this->tenantId]);
        $openHeadcount = (int)$stmtOpenJobs->fetchColumn();

        $stmtFilledJobs = $this->pdo->prepare("SELECT COUNT(DISTINCT `job_id`) FROM `candidate_applications` WHERE `stage` = 'Hired' AND `tenant_id` = ?");
        $stmtFilledJobs->execute([$this->tenantId]);
        $filledHeadcount = (int)$stmtFilledJobs->fetchColumn();

        $stmtDeptVelocity = $this->pdo->prepare("SELECT j.`department`, COUNT(ca.`id`) as total, SUM(CASE WHEN ca.`stage` = 'Hired' THEN 1 ELSE 0 END) as hired, AVG(CASE WHEN ca.`hired_at` IS NOT NULL THEN DATEDIFF(ca.`hired_at`, ca.`applied_at`) END) as avg_time_to_hire FROM `jobs` j LEFT JOIN `candidate_applications` ca ON ca.`job_id` = j.`id` WHERE j.`department` IS NOT NULL AND j.`tenant_id` = ? GROUP BY j.`department` ORDER BY total DESC");
        $stmtDeptVelocity->execute([$this->tenantId]);
        $deptVelocity = $stmtDeptVelocity->fetchAll();

        echo json_encode([
            'success' => true,
            'headline' => ['avg_time_to_hire' => $avgTimeToHire, 'avg_time_to_fill' => $avgTimeToFill, 'offer_acceptance_rate' => $offerAcceptanceRate, 'top_source' => !empty($sourcePerformance) ? $sourcePerformance[0]['source'] : 'N/A', 'active_pipeline' => $activePipeline, 'total_candidates' => $totalCandidatesCount],
            'executive' => ['open_headcount' => $openHeadcount, 'filled_headcount' => $filledHeadcount, 'department_velocity' => $deptVelocity],
            'funnel' => $funnel, 'source_performance' => $sourcePerformance, 'stage_durations' => $stageDurations, 'application_volume' => $applicationVolume, 'job_metrics' => $jobMetrics
        ]);
        exit;
    }

    private function talentPools() {
        $stmt = $this->pdo->prepare("SELECT tp.*, (SELECT COUNT(*) FROM `pool_members` WHERE `pool_id` = tp.`id`) as member_count FROM `talent_pools` tp WHERE tp.`tenant_id` = ? ORDER BY tp.`updated_at` DESC");
        $stmt->execute([$this->tenantId]);
        $pools = $stmt->fetchAll();
        foreach ($pools as &$p) { $p['id'] = (int)$p['id']; $p['member_count'] = (int)$p['member_count']; $p['formatted_date'] = date('M j, Y', strtotime($p['created_at'])); }
        echo json_encode(['success' => true, 'pools' => $pools]);
        exit;
    }

    private function pool() {
        $id = (int)($_GET['id'] ?? 0);
        $poolData = $this->pdo->prepare("SELECT * FROM `talent_pools` WHERE `id` = ? AND `tenant_id` = ?"); $poolData->execute([$id, $this->tenantId]); $pool = $poolData->fetch();
        if (!$pool) { echo json_encode(['success' => false, 'error' => 'Pool not found']); exit; }
        $pool['id'] = (int)$pool['id'];
        $membersStmt = $this->pdo->prepare("SELECT cp.*, pm.`added_at`, pm.`added_by`, (SELECT COUNT(*) FROM `candidate_applications` WHERE `candidate_id` = cp.`id`) as application_count FROM `pool_members` pm JOIN `candidate_profiles` cp ON cp.`id` = pm.`candidate_id` WHERE pm.`pool_id` = ? AND pm.`tenant_id` = ? ORDER BY pm.`added_at` DESC"); $membersStmt->execute([$id, $this->tenantId]);
        $members = $membersStmt->fetchAll();
        foreach ($members as &$m) { $m['id'] = (int)$m['id']; $m['skills_array'] = !empty($m['skills']) ? array_map('trim', explode(',', $m['skills'])) : []; $m['application_count'] = (int)$m['application_count']; }
        $pool['members'] = $members;
        echo json_encode(['success' => true, 'pool' => $pool]);
        exit;
    }

    private function search() {
        $skills = $_GET['skills'] ?? ''; $minExp = (int)($_GET['min_experience'] ?? 0); $location = $_GET['location'] ?? ''; $source = $_GET['source'] ?? ''; $tags = $_GET['tags'] ?? ''; $hasInterviews = $_GET['has_interviews'] ?? ''; $poolId = (int)($_GET['pool_id'] ?? 0); $previousJobId = (int)($_GET['previous_job_id'] ?? 0);
        $where = ["cp.tenant_id = ?", "cp.`status` = 'Active'"]; $params = [$this->tenantId];
        if ($skills) { foreach (array_map('trim', explode(',', $skills)) as $skill) { $where[] = "cp.`skills` LIKE ?"; $params[] = "%$skill%"; } }
        if ($minExp > 0) { $where[] = "cp.`experience_years` >= ?"; $params[] = $minExp; }
        if ($location) { $where[] = "cp.`location` LIKE ?"; $params[] = "%$location%"; }
        if ($source) { $where[] = "cp.`source` = ?"; $params[] = $source; }
        if ($tags) { foreach (array_map('trim', explode(',', $tags)) as $tag) { $where[] = "cp.`tags` LIKE ?"; $params[] = "%$tag%"; } }
        if ($hasInterviews === '1') { $where[] = "EXISTS (SELECT 1 FROM `interviews` WHERE `candidate_id` = cp.`id`)"; }
        if ($poolId > 0) { $where[] = "EXISTS (SELECT 1 FROM `pool_members` WHERE `candidate_id` = cp.`id` AND `pool_id` = ?)"; $params[] = $poolId; }
        if ($previousJobId > 0) { $where[] = "EXISTS (SELECT 1 FROM `candidate_applications` WHERE `candidate_id` = cp.`id` AND `job_id` = ?)"; $params[] = $previousJobId; }
        $stmt = $this->pdo->prepare("SELECT cp.*, (SELECT COUNT(*) FROM `candidate_applications` WHERE `candidate_id` = cp.`id`) as application_count, (SELECT COUNT(*) FROM `pool_members` WHERE `candidate_id` = cp.`id`) as pool_count, (SELECT COUNT(*) FROM `interviews` WHERE `candidate_id` = cp.`id`) as interview_count, (SELECT MAX(`scheduled_at`) FROM `interviews` WHERE `candidate_id` = cp.`id`) as last_interview FROM `candidate_profiles` cp WHERE " . implode(' AND ', $where) . " ORDER BY cp.`experience_years` DESC, cp.`last_activity_at` DESC LIMIT 100");
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        foreach ($results as &$r) { $r['id'] = (int)$r['id']; $r['experience_years'] = (int)$r['experience_years']; $r['application_count'] = (int)$r['application_count']; $r['pool_count'] = (int)$r['pool_count']; $r['interview_count'] = (int)$r['interview_count']; $r['skills_array'] = !empty($r['skills']) ? array_map('trim', explode(',', $r['skills'])) : []; $r['tags'] = !empty($r['tags']) ? array_map('trim', explode(',', $r['tags'])) : []; }
        echo json_encode(['success' => true, 'results' => $results, 'total' => count($results)]);
        exit;
    }

    private function aiMatch() {
        require_once __DIR__ . '/../services/Scoring/ScoringProvider.php';
        require_once __DIR__ . '/../services/Scoring/HeuristicScoringProvider.php';

        $candidateId = (int)($_GET['candidate_id'] ?? 0);
        $jobId = (int)($_GET['job_id'] ?? 0);

        $cStmt = $this->pdo->prepare("SELECT * FROM `candidate_profiles` WHERE `id` = ? AND `tenant_id` = ?");
        $cStmt->execute([$candidateId, $this->tenantId]);
        $c = $cStmt->fetch();

        $jStmt = $this->pdo->prepare("SELECT * FROM `jobs` WHERE `id` = ? AND `tenant_id` = ?");
        $jStmt->execute([$jobId, $this->tenantId]);
        $j = $jStmt->fetch();

        $provider = new \Respawn\Services\Scoring\HeuristicScoringProvider();
        $match = $provider->score($c ?: [], $j ?: []);

        echo json_encode(['success' => true, 'match' => $match]);
        exit;
    }

    private function aiActions() {
        $recommendations = [];
        $stmtStuck = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_applications` WHERE `stage` IN ('Applied', 'Review') AND DATEDIFF(NOW(), `stage_entered_at`) >= 5 AND `rejected_at` IS NULL AND `tenant_id` = ?");
        $stmtStuck->execute([$this->tenantId]);
        $stuck = $stmtStuck->fetchColumn();
        if ($stuck > 0) $recommendations[] = ['type' => 'stuck', 'priority' => 'high', 'icon' => 'clock', 'message' => "$stuck candidate(s) awaiting review for 5+ days", 'action' => 'Review Now', 'action_view' => 'Pipeline'];
        
        $stmtMs = $this->pdo->prepare("SELECT COUNT(*) FROM `interviews` i LEFT JOIN `scorecards` s ON s.`interview_id` = i.`id` WHERE i.`status` = 'Completed' AND s.`id` IS NULL AND i.`tenant_id` = ?");
        $stmtMs->execute([$this->tenantId]);
        $ms = $stmtMs->fetchColumn();
        if ($ms > 0) $recommendations[] = ['type' => 'scorecard', 'priority' => 'high', 'icon' => 'clipboard', 'message' => "$ms completed interview(s) missing scorecards", 'action' => 'Add Scorecards', 'action_view' => 'Interviews'];
        
        $stmtOpenJobs = $this->pdo->prepare("SELECT * FROM `jobs` WHERE `status` = 'Open' AND `tenant_id` = ?");
        $stmtOpenJobs->execute([$this->tenantId]);
        $openJobs = $stmtOpenJobs->fetchAll();
        $batchHealth = $this->computeBatchPipelineHealth(array_column($openJobs, 'id'));
        foreach ($openJobs as $j) {
            $health = $batchHealth[$j['id']] ?? $this->computePipelineHealth($j['id']);
            if ($health['status'] === 'Critical') $recommendations[] = ['type' => 'pipeline', 'priority' => 'critical', 'icon' => 'alert-triangle', 'message' => "{$j['title']} has critical pipeline health", 'action' => 'View Pipeline', 'action_view' => 'Pipeline', 'job_id' => (int)$j['id']];
            if ($health['velocity'] > 0 && $health['hired'] === 0) {
                $projectedDays = round(max(1, 5 - $health['interview']) / max($health['velocity'], 0.1) * 7);
                if ($projectedDays > 0 && $projectedDays < 90) $recommendations[] = ['type' => 'prediction', 'priority' => 'info', 'icon' => 'trending-up', 'message' => "{$j['title']} projected to fill within {$projectedDays} days", 'action' => 'View Details', 'action_view' => 'Jobs', 'job_id' => (int)$j['id']];
            }
        }
        
        $stmtOffers = $this->pdo->prepare("SELECT COUNT(*) FROM `candidate_applications` WHERE `stage` = 'Offer' AND `hired_at` IS NULL AND `rejected_at` IS NULL AND DATEDIFF(NOW(), `stage_entered_at`) >= 3 AND `tenant_id` = ?");
        $stmtOffers->execute([$this->tenantId]);
        $pendingOffers = (int)$stmtOffers->fetchColumn();
        if ($pendingOffers > 0) $recommendations[] = ['type' => 'offer', 'priority' => 'high', 'icon' => 'mail', 'message' => "$pendingOffers offer(s) awaiting response for 3+ days", 'action' => 'View Offers', 'action_view' => 'Pipeline'];
        
        $stmtApprovals = $this->pdo->prepare("SELECT COUNT(*) FROM `approvals` WHERE `status` = 'Pending' AND `tenant_id` = ?");
        $stmtApprovals->execute([$this->tenantId]);
        $pendingApprovals = (int)$stmtApprovals->fetchColumn();
        if ($pendingApprovals > 0) $recommendations[] = ['type' => 'approval', 'priority' => 'medium', 'icon' => 'check-circle', 'message' => "$pendingApprovals approval(s) pending review", 'action' => 'Review Approvals', 'action_view' => 'Approvals'];
        
        $stmtTodayInt = $this->pdo->prepare("SELECT COUNT(*) FROM `interviews` WHERE DATE(`scheduled_at`) = CURDATE() AND `status` = 'Scheduled' AND `tenant_id` = ?");
        $stmtTodayInt->execute([$this->tenantId]);
        $todayInt = (int)$stmtTodayInt->fetchColumn();
        if ($todayInt > 0) $recommendations[] = ['type' => 'interview', 'priority' => 'info', 'icon' => 'calendar', 'message' => "$todayInt interview(s) scheduled for today", 'action' => 'View Schedule', 'action_view' => 'Interviews'];
        
        $prio = ['critical'=>0,'high'=>1,'medium'=>2,'info'=>3];
        usort($recommendations, function($a, $b) use ($prio) { return ($prio[$a['priority']] ?? 9) - ($prio[$b['priority']] ?? 9); });
        echo json_encode(['success' => true, 'recommendations' => $recommendations]);
        exit;
    }

    private function activities() {
        $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
        $stmt = $this->pdo->prepare("SELECT a.*, cp.`name` as candidate_name, j.`title` as job_title FROM `activities` a LEFT JOIN `candidate_profiles` cp ON cp.`id` = a.`candidate_id` LEFT JOIN `jobs` j ON j.`id` = a.`job_id` WHERE a.`tenant_id` = ? ORDER BY a.`created_at` DESC LIMIT ?");
        $stmt->execute([$this->tenantId, $limit]); $activities = $stmt->fetchAll();
        foreach ($activities as &$act) { $act['id'] = (int)$act['id']; $act['time_ago'] = humanTimeAgo($act['created_at']); }
        echo json_encode(['success' => true, 'activities' => $activities]);
        exit;
    }

    private function approvals() {
        $where = ["a.tenant_id = ?"]; $params = [$this->tenantId];
        if (!empty($_GET['status'])) { $where[] = "a.`status` = ?"; $params[] = $_GET['status']; }
        $stmt = $this->pdo->prepare("SELECT a.*, CASE WHEN a.`type` = 'Job' THEN (SELECT `title` FROM `jobs` WHERE `id` = a.`reference_id`) ELSE CONCAT(a.`type`, ' #', a.`reference_id`) END as reference_title FROM `approvals` a " . (!empty($where) ? 'WHERE ' . implode(' AND ', $where) : '') . " ORDER BY a.`requested_at` DESC");
        $stmt->execute($params); $approvals = $stmt->fetchAll();
        foreach ($approvals as &$ap) { $ap['id'] = (int)$ap['id']; $ap['reference_id'] = (int)$ap['reference_id']; $ap['formatted_date'] = date('M j, Y', strtotime($ap['requested_at'])); }
        echo json_encode(['success' => true, 'approvals' => $approvals]);
        exit;
    }

    private function permissions() {
        $role = $this->currentUser['role'] ?? 'Viewer';
        $isAdmin = in_array($role, ['Administrator', 'Admin', 'HR Manager']);
        $isRecruiter = in_array($role, ['Recruiter', 'Talent Acquisition']);
        echo json_encode(['success' => true, 'role' => $role, 'permissions' => ['create_jobs' => $isAdmin || $isRecruiter, 'edit_jobs' => $isAdmin || $isRecruiter, 'approve_jobs' => $isAdmin, 'add_candidates' => true, 'move_candidates' => true, 'delete_candidates' => $isAdmin, 'schedule_interviews' => true, 'submit_scorecards' => true, 'view_analytics' => $isAdmin || $isRecruiter, 'manage_pools' => $isAdmin || $isRecruiter, 'manage_permissions' => $isAdmin, 'view_candidates' => true, 'manage_approvals' => $isAdmin]]);
        exit;
    }

    private function currentUserAction() {
        if ($this->currentUser) { 
            $userPayload = $this->currentUser; 
            unset($userPayload['password_hash']); 
            echo json_encode(['success' => true, 'user' => $userPayload]); 
        } else { 
            // Fallback for development/beta if database lookup fails but session exists
            if (isset($_SESSION['user_name'])) {
                echo json_encode([
                    'success' => true, 
                    'user' => [
                        'full_name' => $_SESSION['user_name'], 
                        'role' => 'Employee', 
                        'department' => 'Operations'
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Not logged in']);
            }
        }
        exit;
    }

    private function fallbackCandidates() {
        $stmt = $this->pdo->prepare("SELECT * FROM `candidate_profiles` WHERE `tenant_id` = ? ORDER BY `id` DESC LIMIT 100");
        $stmt->execute([$this->tenantId]);
        $candidates = $stmt->fetchAll();
        foreach ($candidates as &$c) { $c['id'] = (int)$c['id']; $c['tags'] = !empty($c['tags']) ? array_map('trim', explode(',', $c['tags'])) : []; $c['skills_array'] = !empty($c['skills']) ? array_map('trim', explode(',', $c['skills'])) : []; }
        echo json_encode(['success' => true, 'candidates' => $candidates]);
        exit;
    }

    // --- POST METHODS ---
    private function addJob($input) {
        $title = mb_substr(trim($input['title'] ?? ''), 0, 255);
        if (empty($title)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Job title is required']); exit; }
        
        $status = $input['status'] ?? 'Open';
        if (!in_array($status, self::ALLOWED_JOB_STATUS, true)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid status']); exit; }

        $priority = $input['priority'] ?? 'Normal';
        if (!in_array($priority, self::ALLOWED_JOB_PRIORITY, true)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid priority']); exit; }

        $empType = $input['employment_type'] ?? 'Full-Time';
        if (!in_array($empType, self::ALLOWED_EMP_TYPE, true)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid employment type']); exit; }

        $salaryMin = isset($input['salary_min']) && $input['salary_min'] !== '' ? (float)$input['salary_min'] : null;
        $salaryMax = isset($input['salary_max']) && $input['salary_max'] !== '' ? (float)$input['salary_max'] : null;
        if (($salaryMin !== null && $salaryMin < 0) || ($salaryMax !== null && $salaryMax < 0)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Salary cannot be negative']); exit; }

        $stmt = $this->pdo->prepare("INSERT INTO `jobs` (`tenant_id`, `title`, `department`, `location`, `employment_type`, `salary_min`, `salary_max`, `description`, `requirements`, `status`, `priority`, `hiring_manager`, `assigned_recruiter`, `external_link`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $title, mb_substr(trim($input['department'] ?? ''), 0, 255), mb_substr(trim($input['location'] ?? ''), 0, 255), $empType, $salaryMin, $salaryMax, trim($input['description'] ?? ''), is_array($input['requirements'] ?? '') ? json_encode($input['requirements']) : trim($input['requirements'] ?? ''), $status, $priority, mb_substr(trim($input['hiring_manager'] ?? ''), 0, 150), mb_substr(trim($input['assigned_recruiter'] ?? ''), 0, 150), mb_substr(trim($input['external_link'] ?? ''), 0, 255)]);
        $jobId = (int)$this->pdo->lastInsertId();
        $this->logActivity('job_created', "Job '$title' was created", null, $jobId);
        
        $externalLink = trim($input['external_link'] ?? '');
        if (!empty($externalLink)) {
            $this->logActivity('job_published', "Job '$title' was published to external board: $externalLink", null, $jobId);
        }
        
        echo json_encode(['success' => true, 'job_id' => $jobId]);
        exit;
    }

    private function updateJob($input) {
        $id = (int)($input['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Job ID required']); exit; }
        
        if (isset($input['title'])) { $input['title'] = mb_substr(trim($input['title']), 0, 255); if (empty($input['title'])) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Job title is required']); exit; } }
        if (isset($input['status']) && !in_array($input['status'], self::ALLOWED_JOB_STATUS, true)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid status']); exit; }
        if (isset($input['priority']) && !in_array($input['priority'], self::ALLOWED_JOB_PRIORITY, true)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid priority']); exit; }
        if (isset($input['employment_type']) && !in_array($input['employment_type'], self::ALLOWED_EMP_TYPE, true)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid employment type']); exit; }
        if (isset($input['salary_min']) && $input['salary_min'] !== '' && (float)$input['salary_min'] < 0) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Salary cannot be negative']); exit; }
        if (isset($input['salary_max']) && $input['salary_max'] !== '' && (float)$input['salary_max'] < 0) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Salary cannot be negative']); exit; }

        $fields = []; $params = [];
        foreach (['title', 'department', 'location', 'employment_type', 'description', 'requirements', 'status', 'priority', 'hiring_manager', 'assigned_recruiter', 'external_link'] as $f) {
            if (isset($input[$f])) { 
                $fields[] = "`$f` = ?"; 
                $val = ($f === 'requirements' && is_array($input[$f])) ? json_encode($input[$f]) : $input[$f];
                if (in_array($f, ['department', 'location', 'external_link'])) $val = mb_substr(trim((string)$val), 0, 255);
                if (in_array($f, ['hiring_manager', 'assigned_recruiter'])) $val = mb_substr(trim((string)$val), 0, 150);
                $params[] = $val;
            }
        }
        if (isset($input['salary_min'])) { $fields[] = "`salary_min` = ?"; $params[] = $input['salary_min'] !== '' ? (float)$input['salary_min'] : null; }
        if (isset($input['salary_max'])) { $fields[] = "`salary_max` = ?"; $params[] = $input['salary_max'] !== '' ? (float)$input['salary_max'] : null; }
        if (isset($input['status']) && $input['status'] === 'Closed') { $fields[] = "`closed_at` = NOW()"; }
        if (empty($fields)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'No fields to update']); exit; }
        $params[] = $id;
        $params[] = $this->tenantId;
        $this->pdo->prepare("UPDATE `jobs` SET " . implode(', ', $fields) . " WHERE `id` = ? AND `tenant_id` = ?")->execute($params);
        $this->logActivity('job_updated', "Job was updated", null, $id);
        echo json_encode(['success' => true]);
        exit;
    }

    private function duplicateJob($input) {
        $id = (int)($input['id'] ?? 0);
        $orig = $this->pdo->prepare("SELECT * FROM `jobs` WHERE `id` = ? AND `tenant_id` = ?"); $orig->execute([$id, $this->tenantId]); $job = $orig->fetch();
        if (!$job) { echo json_encode(['success' => false, 'error' => 'Job not found']); exit; }
        $stmt = $this->pdo->prepare("INSERT INTO `jobs` (`tenant_id`, `title`, `department`, `location`, `employment_type`, `salary_min`, `salary_max`, `description`, `requirements`, `status`, `priority`, `hiring_manager`, `assigned_recruiter`, `external_link`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $job['title'] . ' (Copy)', $job['department'], $job['location'], $job['employment_type'], $job['salary_min'], $job['salary_max'], $job['description'], $job['requirements'], $job['priority'], $job['hiring_manager'], $job['assigned_recruiter'], $job['external_link']]);
        $newId = (int)$this->pdo->lastInsertId();
        $this->logActivity('job_duplicated', "Job duplicated from #{$id}", null, $newId);
        echo json_encode(['success' => true, 'job_id' => $newId]);
        exit;
    }

    private function addCandidate($input) {
        $name = mb_substr(trim($input['name'] ?? ''), 0, 255);
        if (empty($name)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Name is required']); exit; }
        
        $email = trim($input['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid email format']); exit; }

        $expYears = isset($input['experience_years']) && $input['experience_years'] !== '' ? (int)$input['experience_years'] : 0;
        if ($expYears < 0) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Experience years cannot be negative']); exit; }

        $salaryExpectation = isset($input['salary_expectation']) && $input['salary_expectation'] !== '' ? (float)$input['salary_expectation'] : null;
        if ($salaryExpectation !== null && $salaryExpectation < 0) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Salary expectation cannot be negative']); exit; }

        $stmt = $this->pdo->prepare("INSERT INTO `candidate_profiles` (`tenant_id`, `name`, `email`, `phone`, `location`, `skills`, `experience_years`, `resume_text`, `salary_expectation`, `source`, `tags`, `assigned_recruiter`, `assigned_hiring_manager`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $name, mb_substr($email, 0, 255), mb_substr(trim($input['phone'] ?? ''), 0, 50), mb_substr(trim($input['location'] ?? ''), 0, 150), is_array($input['skills'] ?? null) ? implode(', ', $input['skills']) : trim($input['skills'] ?? ''), $expYears, trim($input['resume_text'] ?? ''), $salaryExpectation, mb_substr($input['source'] ?? 'Direct', 0, 100), is_array($input['tags'] ?? null) ? implode(', ', $input['tags']) : trim($input['tags'] ?? ''), mb_substr(trim($input['assigned_recruiter'] ?? ''), 0, 150), mb_substr(trim($input['assigned_hiring_manager'] ?? ''), 0, 150)]);
        $candidateId = (int)$this->pdo->lastInsertId();
        $this->logActivity('candidate_created', "Candidate '$name' was added", $candidateId);
        if (!empty($input['job_id'])) {
            $jobId = (int)$input['job_id'];
            $appStmt = $this->pdo->prepare("INSERT INTO `candidate_applications` (`tenant_id`, `candidate_id`, `job_id`, `stage`, `source`, `assigned_recruiter`) VALUES (?, ?, ?, 'Applied', ?, ?)");
            $appStmt->execute([$this->tenantId, $candidateId, $jobId, $input['source'] ?? 'Direct', trim($input['assigned_recruiter'] ?? '')]);
            $appId = (int)$this->pdo->lastInsertId();
            $this->logActivity('application_created', "Applied to job", $candidateId, $jobId, $appId);
            $match = $this->updateMatchScore($appId, $candidateId, $jobId);
        }
        echo json_encode(['success' => true, 'candidate_id' => $candidateId]);
        exit;
    }

    private function updateCandidate($input) {
        $id = (int)($input['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Candidate ID required']); exit; }
        
        if (isset($input['name'])) { $input['name'] = mb_substr(trim($input['name']), 0, 255); if (empty($input['name'])) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Name is required']); exit; } }
        if (isset($input['email']) && trim($input['email']) !== '') { if (!filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid email format']); exit; } $input['email'] = mb_substr(trim($input['email']), 0, 255); }
        if (isset($input['experience_years']) && $input['experience_years'] !== '' && (int)$input['experience_years'] < 0) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Experience years cannot be negative']); exit; }
        if (isset($input['salary_expectation']) && $input['salary_expectation'] !== '' && (float)$input['salary_expectation'] < 0) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Salary expectation cannot be negative']); exit; }

        $fields = []; $params = [];
        foreach (['name', 'email', 'phone', 'location', 'source', 'assigned_recruiter', 'assigned_hiring_manager', 'status'] as $f) {
            if (isset($input[$f])) { 
                $fields[] = "`$f` = ?"; 
                $val = $input[$f];
                if (in_array($f, ['phone', 'status'])) $val = mb_substr(trim((string)$val), 0, 50);
                if ($f === 'source') $val = mb_substr(trim((string)$val), 0, 100);
                if (in_array($f, ['location', 'assigned_recruiter', 'assigned_hiring_manager'])) $val = mb_substr(trim((string)$val), 0, 150);
                $params[] = $val; 
            }
        }
        if (isset($input['skills'])) { $fields[] = "`skills` = ?"; $params[] = is_array($input['skills']) ? implode(', ', $input['skills']) : $input['skills']; }
        if (isset($input['tags'])) { $fields[] = "`tags` = ?"; $params[] = is_array($input['tags']) ? implode(', ', $input['tags']) : $input['tags']; }
        if (isset($input['experience_years'])) { $fields[] = "`experience_years` = ?"; $params[] = $input['experience_years'] !== '' ? (int)$input['experience_years'] : 0; }
        if (isset($input['salary_expectation'])) { $fields[] = "`salary_expectation` = ?"; $params[] = $input['salary_expectation'] !== '' ? (float)$input['salary_expectation'] : null; }
        if (isset($input['resume_text'])) { $fields[] = "`resume_text` = ?"; $params[] = $input['resume_text']; }
        if (isset($input['ai_summary'])) { $fields[] = "`ai_summary` = ?"; $params[] = $input['ai_summary']; }
        if (empty($fields)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'No fields to update']); exit; }
        $params[] = $id;
        $params[] = $this->tenantId; $this->pdo->prepare("UPDATE `candidate_profiles` SET " . implode(', ', $fields) . " WHERE `id` = ? AND `tenant_id` = ?")->execute($params);
        $this->logActivity('candidate_updated', "Candidate profile updated", $id);
        echo json_encode(['success' => true]);
        exit;
    }

    private function addApplication($input) {
        $candidateId = (int)($input['candidate_id'] ?? 0); $jobId = (int)($input['job_id'] ?? 0);
        if (!$candidateId || !$jobId) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Candidate ID and Job ID required']); exit; }
        $stmt = $this->pdo->prepare("INSERT INTO `candidate_applications` (`tenant_id`, `candidate_id`, `job_id`, `stage`, `source`, `assigned_recruiter`) VALUES (?, ?, ?, 'Applied', ?, ?)");
        $stmt->execute([$this->tenantId, $candidateId, $jobId, $input['source'] ?? 'Direct', trim($input['assigned_recruiter'] ?? '')]);
        $appId = (int)$this->pdo->lastInsertId();
        $match = $this->updateMatchScore($appId, $candidateId, $jobId);
        $this->logActivity('application_created', "Applied to job", $candidateId, $jobId, $appId);
        echo json_encode(['success' => true, 'application_id' => $appId, 'ai_match_score' => $match['total']]);
        exit;
    }

    private function updateStage($input) {
        $id = (int)($input['id'] ?? 0); $stage = trim($input['stage'] ?? '');
        if (!$id || empty($stage)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Missing ID or stage']); exit; }
        if (!in_array($stage, self::ALLOWED_STAGES, true)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid stage']); exit; }
        $oldStage = $this->pdo->prepare("SELECT a.`stage`, a.`candidate_id`, a.`job_id`, p.`email`, p.`name` FROM `candidate_applications` a JOIN `candidate_profiles` p ON a.`candidate_id` = p.`id` WHERE a.`id` = ? AND a.`tenant_id` = ?"); $oldStage->execute([$id, $this->tenantId]); $old = $oldStage->fetch();
        $updateFields = "`stage` = ?, `stage_entered_at` = NOW()";
        $updateParams = [$stage];

        if ($stage === 'Hired') {
            $updateFields .= ", `hired_at` = NOW()";
        } else {
            $updateFields .= ", `hired_at` = NULL";
        }
        
        if ($stage !== 'Rejected') {
            $updateFields .= ", `rejected_at` = NULL, `rejection_reason` = NULL";
        }

        $updateParams[] = $id;
        $updateParams[] = $this->tenantId;
        $this->pdo->prepare("UPDATE `candidate_applications` SET $updateFields WHERE `id` = ? AND `tenant_id` = ?")->execute($updateParams);
        if ($old) { 
            $this->logActivity('stage_changed', "Moved from {$old['stage']} to $stage", (int)$old['candidate_id'], (int)$old['job_id'], $id); 
            
            // Send email notification based on new stage
            if (!empty($old['email']) && $old['stage'] !== $stage) {
                if ($stage === 'Offer') {
                    Mailer::send($old['email'], $old['name'], 'You have received an offer!', "<p>Hi {$old['name']},</p><p>We are thrilled to extend an offer for your recent application. Please check your portal for more details.</p>");
                } elseif ($stage === 'Hired') {
                    Mailer::send($old['email'], $old['name'], 'Welcome to the team!', "<p>Hi {$old['name']},</p><p>Congratulations! We are excited to welcome you to the team.</p>");
                } elseif ($stage === 'Rejected') {
                    Mailer::send($old['email'], $old['name'], 'Update on your application', "<p>Hi {$old['name']},</p><p>Thank you for taking the time to interview with us. Unfortunately, we have decided to move forward with other candidates at this time. We wish you the best in your job search.</p>");
                }
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    private function updateRating($input) {
        $id = (int)($input['id'] ?? 0); $rating = (int)($input['rating'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Missing application ID']); exit; }
        $this->pdo->prepare("UPDATE `candidate_applications` SET `rating` = ? WHERE `id` = ? AND `tenant_id` = ?")->execute([$rating, $id, $this->tenantId]);
        $this->logActivity('rating_updated', "Updated rating to $rating stars", null, null, $id);
        echo json_encode(['success' => true]);
        exit;
    }

    private function bulkAdvance($input) {
        $ids = $input['ids'] ?? []; $stage = trim($input['stage'] ?? '');
        if (empty($ids) || empty($stage)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Missing IDs or stage']); exit; }
        if (!in_array($stage, self::ALLOWED_STAGES, true)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid stage']); exit; }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($stage === 'Hired') {
            $set = "`stage` = ?, `stage_entered_at` = NOW(), `hired_at` = NOW(), `rejected_at` = NULL, `rejection_reason` = NULL";
        } else {
            $set = "`stage` = ?, `stage_entered_at` = NOW(), `hired_at` = NULL";
            if ($stage !== 'Rejected') {
                $set .= ", `rejected_at` = NULL, `rejection_reason` = NULL";
            }
        }
        $params = array_merge([$stage], $ids);
        $params[] = $this->tenantId;
        $this->pdo->prepare("UPDATE `candidate_applications` SET $set WHERE `id` IN ($placeholders) AND `tenant_id` = ?")->execute($params);
        foreach ($ids as $appId) {
            $app = $this->pdo->prepare("SELECT a.`candidate_id`, a.`job_id`, p.`email`, p.`name`, a.`stage` AS old_stage FROM `candidate_applications` a JOIN `candidate_profiles` p ON a.`candidate_id` = p.`id` WHERE a.`id` = ? AND a.`tenant_id` = ?"); $app->execute([$appId, $this->tenantId]); $a = $app->fetch();
            if ($a) {
                $this->logActivity('bulk_advance', "Bulk advanced to $stage", (int)$a['candidate_id'], (int)$a['job_id'], (int)$appId);
                
                if (!empty($a['email']) && $a['old_stage'] !== $stage) {
                    if ($stage === 'Offer') {
                        Mailer::send($a['email'], $a['name'], 'You have received an offer!', "<p>Hi {$a['name']},</p><p>We are thrilled to extend an offer for your recent application. Please check your portal for more details.</p>");
                    } elseif ($stage === 'Hired') {
                        Mailer::send($a['email'], $a['name'], 'Welcome to the team!', "<p>Hi {$a['name']},</p><p>Congratulations! We are excited to welcome you to the team.</p>");
                    } elseif ($stage === 'Rejected') {
                        Mailer::send($a['email'], $a['name'], 'Update on your application', "<p>Hi {$a['name']},</p><p>Thank you for taking the time to interview with us. Unfortunately, we have decided to move forward with other candidates at this time. We wish you the best in your job search.</p>");
                    }
                }
            }
        }
        echo json_encode(['success' => true, 'updated' => count($ids)]);
        exit;
    }

    private function bulkReject($input) {
        $ids = $input['ids'] ?? []; $reason = trim($input['reason'] ?? '');
        if (empty($ids)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Missing IDs']); exit; }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$reason], $ids); $params[] = $this->tenantId; $this->pdo->prepare("UPDATE `candidate_applications` SET `stage` = 'Rejected', `rejected_at` = NOW(), `rejection_reason` = ? WHERE `id` IN ($placeholders) AND `tenant_id` = ?")->execute($params);
        foreach ($ids as $appId) {
            $app = $this->pdo->prepare("SELECT a.`candidate_id`, a.`job_id`, p.`email`, p.`name`, a.`stage` AS old_stage FROM `candidate_applications` a JOIN `candidate_profiles` p ON a.`candidate_id` = p.`id` WHERE a.`id` = ? AND a.`tenant_id` = ?"); $app->execute([$appId, $this->tenantId]); $a = $app->fetch();
            if ($a) {
                $this->logActivity('bulk_reject', "Bulk rejected", (int)$a['candidate_id'], (int)$a['job_id'], (int)$appId);
                if (!empty($a['email']) && $a['old_stage'] !== 'Rejected') {
                    Mailer::send($a['email'], $a['name'], 'Update on your application', "<p>Hi {$a['name']},</p><p>Thank you for taking the time to interview with us. Unfortunately, we have decided to move forward with other candidates at this time. We wish you the best in your job search.</p>");
                }
            }
        }
        echo json_encode(['success' => true, 'updated' => count($ids)]);
        exit;
    }

    private function bulkDelete($input) {
        $ids = $input['ids'] ?? [];
        if (empty($ids)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Missing IDs']); exit; }
        foreach ($ids as $appId) {
            $app = $this->pdo->prepare("SELECT `candidate_id`, `job_id` FROM `candidate_applications` WHERE `id` = ? AND `tenant_id` = ?"); $app->execute([$appId, $this->tenantId]); $a = $app->fetch();
            if ($a) $this->logActivity('bulk_delete', "Application deleted", (int)$a['candidate_id'], (int)$a['job_id']);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = $ids; $params[] = $this->tenantId; $this->pdo->prepare("DELETE FROM `candidate_applications` WHERE `id` IN ($placeholders) AND `tenant_id` = ?")->execute($params);
        echo json_encode(['success' => true, 'deleted' => count($ids)]);
        exit;
    }

    private function deleteCandidate($input) {
        $id = (int)($input['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Missing candidate ID']); exit; }
        $this->logActivity('candidate_deleted', "Candidate deleted", $id);
        $this->pdo->prepare("DELETE FROM `candidate_profiles` WHERE `id` = ? AND `tenant_id` = ?")->execute([$id, $this->tenantId]);
        echo json_encode(['success' => true]);
        exit;
    }

    private function addInterview($input) {
        $applicationId = (int)($input['application_id'] ?? 0); $candidateId = (int)($input['candidate_id'] ?? 0); $jobId = (int)($input['job_id'] ?? 0); $scheduledAt = $input['scheduled_at'] ?? '';
        if (!$applicationId || !$candidateId || !$jobId || empty($scheduledAt)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Missing required fields']); exit; }
        $stmt = $this->pdo->prepare("INSERT INTO `interviews` (`tenant_id`, `application_id`, `candidate_id`, `job_id`, `interview_type`, `scheduled_at`, `duration_minutes`, `location`, `meeting_link`, `interviewer_name`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $applicationId, $candidateId, $jobId, $input['interview_type'] ?? 'General', $scheduledAt, (int)($input['duration_minutes'] ?? 60), trim($input['location'] ?? ''), trim($input['meeting_link'] ?? ''), trim($input['interviewer_name'] ?? '')]);
        $interviewId = (int)$this->pdo->lastInsertId();
        $this->logActivity('interview_scheduled', "Interview scheduled for " . date('M j, Y', strtotime($scheduledAt)), $candidateId, $jobId, $applicationId);

        $prof = $this->pdo->prepare("SELECT `name`, `email` FROM `candidate_profiles` WHERE `id` = ?"); $prof->execute([$candidateId]); $p = $prof->fetch();
        if ($p && !empty($p['email'])) {
            $type = htmlspecialchars($input['interview_type'] ?? 'General');
            $loc = htmlspecialchars(trim($input['location'] ?? ''));
            $link = htmlspecialchars(trim($input['meeting_link'] ?? ''));
            $dateFormatted = date('l, M j, Y g:i A', strtotime($scheduledAt));
            
            $html = "<p>Hi {$p['name']},</p>";
            $html .= "<p>An interview has been scheduled for your application.</p>";
            $html .= "<ul>";
            $html .= "<li><strong>Type:</strong> $type</li>";
            $html .= "<li><strong>Date/Time:</strong> $dateFormatted</li>";
            if ($loc) $html .= "<li><strong>Location:</strong> $loc</li>";
            if ($link) $html .= "<li><strong>Meeting Link:</strong> <a href=\"$link\">$link</a></li>";
            $html .= "</ul>";
            $html .= "<p>Looking forward to speaking with you!</p>";
            
            Mailer::send($p['email'], $p['name'], "Interview Scheduled: $type", $html);
        }

        echo json_encode(['success' => true, 'interview_id' => $interviewId]);
        exit;
    }

    private function updateInterview($input) {
        $id = (int)($input['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Interview ID required']); exit; }
        $fields = []; $params = [];
        foreach (['status', 'notes', 'interview_type', 'location', 'meeting_link', 'interviewer_name'] as $f) {
            if (isset($input[$f])) { $fields[] = "`$f` = ?"; $params[] = $input[$f]; }
        }
        if (isset($input['score'])) { $fields[] = "`score` = ?"; $params[] = (int)$input['score']; }
        if (isset($input['scheduled_at'])) { $fields[] = "`scheduled_at` = ?"; $params[] = $input['scheduled_at']; }
        if (isset($input['duration_minutes'])) { $fields[] = "`duration_minutes` = ?"; $params[] = (int)$input['duration_minutes']; }
        if (!empty($fields)) {
            $params[] = $id;
            $params[] = $this->tenantId; $this->pdo->prepare("UPDATE `interviews` SET " . implode(', ', $fields) . " WHERE `id` = ? AND `tenant_id` = ?")->execute($params);
        }
        $iv = $this->pdo->prepare("SELECT `candidate_id`, `job_id`, `application_id` FROM `interviews` WHERE `id` = ? AND `tenant_id` = ?"); $iv->execute([$id, $this->tenantId]); $ivData = $iv->fetch();
        if ($ivData) $this->logActivity('interview_updated', "Interview updated", (int)$ivData['candidate_id'], (int)$ivData['job_id'], (int)$ivData['application_id']);
        echo json_encode(['success' => true]);
        exit;
    }

    private function addScorecard($input) {
        $interviewId = (int)($input['interview_id'] ?? 0);
        if (!$interviewId) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Interview ID required']); exit; }
        $stmt = $this->pdo->prepare("INSERT INTO `scorecards` (`tenant_id`, `interview_id`, `evaluator_name`, `technical_score`, `communication_score`, `culture_score`, `overall_score`, `recommendation`, `strengths`, `concerns`, `notes`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $interviewId, trim($input['evaluator_name'] ?? ''), (int)($input['technical_score'] ?? 0), (int)($input['communication_score'] ?? 0), (int)($input['culture_score'] ?? 0), (int)($input['overall_score'] ?? 0), $input['recommendation'] ?? 'Maybe', trim($input['strengths'] ?? ''), trim($input['concerns'] ?? ''), trim($input['notes'] ?? '')]);
        $overall = (int)($input['overall_score'] ?? 0);
        $this->pdo->prepare("UPDATE `interviews` SET `score` = ? WHERE `id` = ? AND `tenant_id` = ?")->execute([$overall, $interviewId, $this->tenantId]);
        $iv = $this->pdo->prepare("SELECT `candidate_id`, `job_id` FROM `interviews` WHERE `id` = ? AND `tenant_id` = ?"); $iv->execute([$interviewId, $this->tenantId]); $ivData = $iv->fetch();
        if ($ivData) $this->logActivity('scorecard_submitted', "Scorecard submitted (score: $overall)", (int)$ivData['candidate_id'], (int)$ivData['job_id']);
        echo json_encode(['success' => true, 'scorecard_id' => (int)$this->pdo->lastInsertId()]);
        exit;
    }

    private function addNote($input) {
        $candidateId = (int)($input['candidate_id'] ?? 0);
        if (!$candidateId) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Candidate ID required']); exit; }
        $stmt = $this->pdo->prepare("INSERT INTO `candidate_notes` (`tenant_id`, `candidate_id`, `application_id`, `author_name`, `content`, `note_type`) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $candidateId, !empty($input['application_id']) ? (int)$input['application_id'] : null, trim($input['author_name'] ?? 'System'), trim($input['content'] ?? ''), $input['note_type'] ?? 'Comment']);
        $this->logActivity('note_added', "Note added", $candidateId);
        echo json_encode(['success' => true, 'note_id' => (int)$this->pdo->lastInsertId()]);
        exit;
    }

    private function addPool($input) {
        $name = trim($input['name'] ?? '');
        if (empty($name)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Pool name required']); exit; }
        $stmt = $this->pdo->prepare("INSERT INTO `talent_pools` (`tenant_id`, `name`, `description`, `created_by`) VALUES (?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $name, trim($input['description'] ?? ''), trim($input['created_by'] ?? 'System')]);
        $poolId = (int)$this->pdo->lastInsertId();
        $this->logActivity('pool_created', "Talent pool '$name' created");
        echo json_encode(['success' => true, 'pool_id' => $poolId]);
        exit;
    }

    private function updatePool($input) {
        $id = (int)($input['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Pool ID required']); exit; }
        $fields = []; $params = [];
        if (isset($input['name'])) { $fields[] = "`name` = ?"; $params[] = trim($input['name']); }
        if (isset($input['description'])) { $fields[] = "`description` = ?"; $params[] = trim($input['description']); }
        if (!empty($fields)) {
            $params[] = $id;
            $params[] = $this->tenantId; $this->pdo->prepare("UPDATE `talent_pools` SET " . implode(', ', $fields) . " WHERE `id` = ? AND `tenant_id` = ?")->execute($params);
            $this->logActivity('pool_updated', "Talent pool updated");
        }
        echo json_encode(['success' => true]);
        exit;
    }

    private function addToPool($input) {
        $poolId = (int)($input['pool_id'] ?? 0); $candidateId = (int)($input['candidate_id'] ?? 0);
        if (!$poolId || !$candidateId) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Pool ID and Candidate ID required']); exit; }
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO `pool_members` (`tenant_id`, `pool_id`, `candidate_id`, `added_by`) VALUES (?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $poolId, $candidateId, trim($input['added_by'] ?? 'System')]);
        $this->logActivity('added_to_pool', "Added to talent pool", $candidateId);
        echo json_encode(['success' => true]);
        exit;
    }

    private function removeFromPool($input) {
        $poolId = (int)($input['pool_id'] ?? 0); $candidateId = (int)($input['candidate_id'] ?? 0);
        if (!$poolId || !$candidateId) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Pool ID and Candidate ID required']); exit; }
        $this->pdo->prepare("DELETE FROM `pool_members` WHERE `pool_id` = ? AND `candidate_id` = ? AND `tenant_id` = ?")->execute([$poolId, $candidateId, $this->tenantId]);
        $this->logActivity('removed_from_pool', "Removed from talent pool", $candidateId);
        echo json_encode(['success' => true]);
        exit;
    }

    private function deletePool($input) {
        $id = (int)($input['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Pool ID required']); exit; }
        $this->logActivity('pool_deleted', "Talent pool deleted");
        $this->pdo->prepare("DELETE FROM `talent_pools` WHERE `id` = ? AND `tenant_id` = ?")->execute([$id, $this->tenantId]);
        echo json_encode(['success' => true]);
        exit;
    }

    private function submitApproval($input) {
        $type = $input['type'] ?? ''; $referenceId = (int)($input['reference_id'] ?? 0);
        if (empty($type) || !$referenceId) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Type and reference ID required']); exit; }
        $stmt = $this->pdo->prepare("INSERT INTO `approvals` (`tenant_id`, `type`, `reference_id`, `requested_by`, `approver_name`, `notes`) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->tenantId, $type, $referenceId, trim($input['requested_by'] ?? ''), trim($input['approver_name'] ?? ''), trim($input['notes'] ?? '')]);
        if ($type === 'Job') { $this->pdo->prepare("UPDATE `jobs` SET `approval_status` = 'Pending' WHERE `id` = ? AND `tenant_id` = ?")->execute([$referenceId, $this->tenantId]); }
        echo json_encode(['success' => true, 'approval_id' => (int)$this->pdo->lastInsertId()]);
        exit;
    }

    private function resolveApproval($input) {
        $id = (int)($input['id'] ?? 0); $status = $input['status'] ?? '';
        if (!$id || !in_array($status, ['Approved', 'Rejected'])) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid approval']); exit; }
        $this->pdo->prepare("UPDATE `approvals` SET `status` = ?, `resolved_at` = NOW(), `notes` = CONCAT(IFNULL(`notes`, ''), ?) WHERE `id` = ? AND `tenant_id` = ?")->execute([$status, !empty($input['notes']) ? "\n[Resolution] " . $input['notes'] : '', $id, $this->tenantId]);
        $approval = $this->pdo->prepare("SELECT * FROM `approvals` WHERE `id` = ? AND `tenant_id` = ?"); $approval->execute([$id, $this->tenantId]); $ap = $approval->fetch();
        if ($ap && $ap['type'] === 'Job' && $status === 'Approved') {
            $this->pdo->prepare("UPDATE `jobs` SET `approval_status` = 'Approved', `approved_by` = ?, `approved_at` = NOW(), `status` = 'Open' WHERE `id` = ? AND `tenant_id` = ?")->execute([trim($input['approver'] ?? 'Admin'), (int)$ap['reference_id'], $this->tenantId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    private function computeAiScores($input) {
        $jobId = (int)($input['job_id'] ?? 0);
        if (!$jobId) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Job ID required']); exit; }
        $apps = $this->pdo->prepare("SELECT `id`, `candidate_id` FROM `candidate_applications` WHERE `job_id` = ? AND `tenant_id` = ?"); $apps->execute([$jobId, $this->tenantId]);
        $updated = 0;
        foreach ($apps->fetchAll() as $app) {
            $match = $this->updateMatchScore((int)$app['id'], (int)$app['candidate_id'], $jobId);
            $updated++;
        }
        echo json_encode(['success' => true, 'updated' => $updated]);
        exit;
    }

    private function legacyAdd($input) {
        $name = trim($input['name'] ?? '');
        if (empty($name)) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Name required']); exit; }
        $stmt = $this->pdo->prepare("INSERT INTO `candidate_profiles` (`tenant_id`, `name`, `tags`, `source`) VALUES (?, ?, ?, 'Direct')");
        $tags = isset($input['tags']) ? (is_array($input['tags']) ? implode(', ', $input['tags']) : $input['tags']) : '';
        $stmt->execute([$this->tenantId, $name, $tags]);
        echo json_encode(['success' => true, 'candidate_id' => (int)$this->pdo->lastInsertId()]);
        exit;
    }

    private function legacyDelete($input) {
        $id = (int)($input['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Missing ID']); exit; }
        $this->pdo->prepare("DELETE FROM `candidate_profiles` WHERE `id` = ? AND `tenant_id` = ?")->execute([$id, $this->tenantId]);
        echo json_encode(['success' => true]);
        exit;
    }

    private function uploadResume() {
        $candidateId = (int)($_POST['candidate_id'] ?? 0);
        if (!$candidateId) {
            echo json_encode(['success' => false, 'error' => 'Missing candidate ID']);
            exit;
        }

        // Verify candidate ownership
        $stmt = $this->pdo->prepare("SELECT id, resume_file_path FROM `candidate_profiles` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$candidateId, $this->tenantId]);
        $candidate = $stmt->fetch();
        if (!$candidate) {
            echo json_encode(['success' => false, 'error' => 'Candidate not found or access denied']);
            exit;
        }

        if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No valid file uploaded']);
            exit;
        }

        $file = $_FILES['resume'];

        // Size check (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'File exceeds 5MB limit']);
            exit;
        }

        // MIME check
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
        ];

        if (!array_key_exists($mime, $allowedMimes)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Only PDF, DOC, and DOCX are allowed.']);
            exit;
        }

        $ext = $allowedMimes[$mime];
        $originalName = basename($file['name']);
        
        // Define storage base configurable via env var for persistent storage (e.g. /data mounted on Railway), fallback to local storage
        $storageBase = \App\Utils\Storage::resolveStorageBase(true, true);
        $storageDir = rtrim($storageBase, '/') . '/tenant_' . $this->tenantId . '/resumes';

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        // Generate secure filename
        $secureFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath = $storageDir . '/' . $secureFilename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            // Delete old resume if exists
            if (!empty($candidate['resume_file_path'])) {
                $oldPath = $storageBase . '/' . $candidate['resume_file_path'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            // Store path relative to storage base so downloadResume can resolve it dynamically
            $relativePath = 'tenant_' . $this->tenantId . '/resumes/' . $secureFilename;
            $now = date('Y-m-d H:i:s');

            $updateStmt = $this->pdo->prepare("UPDATE `candidate_profiles` SET `resume_file_path` = ?, `resume_filename` = ?, `resume_mime` = ?, `resume_uploaded_at` = ? WHERE `id` = ?");
            $updateStmt->execute([$relativePath, $originalName, $mime, $now, $candidateId]);

            $this->logActivity('resume_uploaded', "Uploaded resume: $originalName", $candidateId);

            echo json_encode([
                'success' => true,
                'resume_filename' => $originalName,
                'resume_uploaded_at' => $now,
                'resume_download_url' => '../api/index.php?route=candidates&action=download_resume&id=' . $candidateId
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
        }
        exit;
    }

    private function downloadResume() {
        $candidateId = (int)($_GET['id'] ?? 0);
        if (!$candidateId) {
            http_response_code(400);
            echo "Missing candidate ID";
            exit;
        }

        $stmt = $this->pdo->prepare("SELECT resume_file_path, resume_filename, resume_mime FROM `candidate_profiles` WHERE `id` = ? AND `tenant_id` = ?");
        $stmt->execute([$candidateId, $this->tenantId]);
        $candidate = $stmt->fetch();

        if (!$candidate || empty($candidate['resume_file_path'])) {
            http_response_code(404);
            echo "Resume not found";
            exit;
        }

        $storageBase = \App\Utils\Storage::resolveStorageBase(true, false);

        // Strip off any old 'storage/' prefix from earlier uploads to handle migration gracefully
        $dbPath = preg_replace('/^storage\//', '', $candidate['resume_file_path']);
        $fullPath = rtrim($storageBase, '/') . '/' . ltrim($dbPath, '/');

        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo "File missing from storage";
            exit;
        }

        header('Content-Type: ' . $candidate['resume_mime']);
        header('Content-Disposition: attachment; filename="' . basename($candidate['resume_filename']) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        readfile($fullPath);
        exit;
    }

    private function hireCandidate($data) {
        // Enforce basic ATS edit and HR permission
        if (!hasPermission('ats.edit') && !hasPermission('core_hr.create_employee')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You do not have permission to hire candidates.']);
            exit;
        }

        $appId = $data['application_id'] ?? null;
        $employeeId = $data['employee_id'] ?? null;
        $hireDate = $data['hire_date'] ?? null;
        $jobTitle = $data['job_title'] ?? null;
        $department = $data['department'] ?? null;
        $baseSalary = $data['base_salary'] ?? null;

        if (!$appId || !$employeeId || !$hireDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Application ID, Employee ID, and Hire Date are required.']);
            exit;
        }

        try {
            $this->pdo->beginTransaction();

            // 1. Get candidate application and profile info
            $stmt = $this->pdo->prepare("SELECT ca.candidate_id, ca.job_id, cp.name, cp.email, cp.phone 
                FROM candidate_applications ca 
                JOIN candidate_profiles cp ON ca.candidate_id = cp.id 
                WHERE ca.id = ? AND ca.tenant_id = ?");
            $stmt->execute([$appId, $this->tenantId]);
            $candidate = $stmt->fetch();

            if (!$candidate) {
                $this->pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Candidate application not found.']);
                exit;
            }

            // Check if user email already exists
            if (empty($candidate['email'])) {
                $this->pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Candidate email is required to create an employee account.']);
                exit;
            }

            $checkStmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ?");
            $checkStmt->execute([$candidate['email'], $this->tenantId]);
            if ($checkStmt->fetch()) {
                $this->pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'An employee with this email already exists in the system.']);
                exit;
            }

            // 2. Insert into users table
            $tempPassword = bin2hex(random_bytes(6)); // 12-character random hex string
            $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
            $emailToUse = $candidate['email'];
            
            $insertUser = $this->pdo->prepare("INSERT INTO users 
                (tenant_id, full_name, email, password_hash, role, employment_status, employee_id, hire_date, job_title, department, base_salary, phone, must_change_password, created_at) 
                VALUES (?, ?, ?, ?, 'Employee', 'Active', ?, ?, ?, ?, ?, ?, 1, NOW())");
            
            $insertUser->execute([
                $this->tenantId,
                $candidate['name'],
                $emailToUse,
                $passwordHash,
                $employeeId,
                $hireDate,
                $jobTitle,
                $department,
                $baseSalary,
                $candidate['phone']
            ]);

            // 3. Update application stage
            $updateApp = $this->pdo->prepare("UPDATE candidate_applications SET stage = 'Hired', hired_at = NOW() WHERE id = ?");
            $updateApp->execute([$appId]);

            // 4. Log activity
            $this->logActivity(
                "candidate_hired",
                "Hired and enrolled as Employee ID: $employeeId",
                $candidate['candidate_id'],
                $candidate['job_id'],
                $appId
            );

            $this->pdo->commit();
            echo json_encode(['success' => true, 'temp_password' => $tempPassword]);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            error_log('Error hiring candidate: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to hire candidate.']);
        }
    }
}

function humanTimeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}
