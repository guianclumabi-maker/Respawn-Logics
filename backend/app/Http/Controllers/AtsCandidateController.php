<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AtsCandidateController extends Controller
{
    private function computeBatchPipelineHealth($jobIds) {
        if (empty($jobIds)) return [];
        
        $inClause = implode(',', array_fill(0, count($jobIds), '?'));
        
        // 1. Stage Counts
        $stagesSql = "SELECT `job_id`,
            SUM(CASE WHEN `stage` = 'Applied' THEN 1 ELSE 0 END) as applied,
            SUM(CASE WHEN `stage` = 'Review' THEN 1 ELSE 0 END) as review,
            SUM(CASE WHEN `stage` = 'Phone Screen' THEN 1 ELSE 0 END) as phone_screen,
            SUM(CASE WHEN `stage` = 'Interview' THEN 1 ELSE 0 END) as interview,
            SUM(CASE WHEN `stage` = 'Offer' THEN 1 ELSE 0 END) as offer,
            SUM(CASE WHEN `stage` = 'Hired' THEN 1 ELSE 0 END) as hired,
            COUNT(*) as total
            FROM `candidate_applications` 
            WHERE `job_id` IN ($inClause) AND `rejected_at` IS NULL 
            GROUP BY `job_id`";
        
        $stageData = [];
        foreach (DB::select($stagesSql, $jobIds) as $r) {
            $stageData[$r->job_id] = (array)$r;
        }
        
        // 2. Stuck Candidates
        $stuckSql = "SELECT `job_id`, COUNT(*) as stuck
            FROM `candidate_applications` 
            WHERE `job_id` IN ($inClause) 
            AND DATEDIFF(NOW(), `stage_entered_at`) >= 7 
            AND `stage` NOT IN ('Hired', 'Rejected') AND `rejected_at` IS NULL
            GROUP BY `job_id`";
        
        $stuckData = [];
        foreach (DB::select($stuckSql, $jobIds) as $r) {
            $stuckData[$r->job_id] = (int)$r->stuck;
        }
        
        // 3. Velocity
        $velocitySql = "SELECT `job_id`, COUNT(*) as velocity
            FROM `candidate_applications` 
            WHERE `job_id` IN ($inClause) 
            AND `applied_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY `job_id`";
            
        $velocityData = [];
        foreach (DB::select($velocitySql, $jobIds) as $r) {
            $velocityData[$r->job_id] = (int)$r->velocity;
        }
        
        // Combine
        $results = [];
        foreach ($jobIds as $jobId) {
            $data = $stageData[$jobId] ?? [
                'total' => 0, 'applied' => 0, 'review' => 0, 
                'phone_screen' => 0, 'interview' => 0, 'offer' => 0, 'hired' => 0
            ];
            
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
            
            if ($score >= 70) $status = 'Healthy';
            elseif ($score >= 40) $status = 'Needs Attention';
            else $status = 'Critical';
            
            $results[$jobId] = [
                'total' => $total,
                'applied' => (int)$data['applied'],
                'review' => (int)$data['review'],
                'phone_screen' => (int)$data['phone_screen'],
                'interview' => (int)$data['interview'],
                'offer' => (int)$data['offer'],
                'hired' => (int)$data['hired'],
                'stuck' => $stuck,
                'score' => $score,
                'status' => $status,
                'velocity' => $velocity
            ];
        }
        
        return $results;
    }

    private function computeBatchLastActivity($jobIds) {
        if (empty($jobIds)) return [];
        $inClause = implode(',', array_fill(0, count($jobIds), '?'));
        $stmt = DB::select("SELECT `job_id`, MAX(`created_at`) as last_act FROM `activities` WHERE `job_id` IN ($inClause) GROUP BY `job_id`", $jobIds);
        $results = [];
        foreach ($stmt as $r) {
            $results[$r->job_id] = $r->last_act;
        }
        return $results;
    }
    
    private function computePipelineHealth($jobId) {
        $res = $this->computeBatchPipelineHealth([$jobId]);
        return $res[$jobId];
    }

    public function jobs(Request $request) {
        $query = DB::table('jobs')->orderBy('created_at', 'desc');
        
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }
        if ($request->has('department') && $request->department !== '') {
            $query->where('department', $request->department);
        }
        if ($request->has('priority') && $request->priority !== '') {
            $query->where('priority', $request->priority);
        }
        if ($request->has('search') && $request->search !== '') {
            $query->where(function($q) use ($request) {
                $q->where('title', 'LIKE', '%'.$request->search.'%')
                  ->orWhere('department', 'LIKE', '%'.$request->search.'%')
                  ->orWhere('location', 'LIKE', '%'.$request->search.'%');
            });
        }
        
        $jobs = $query->get()->map(function($j) { return (array)$j; })->toArray();
        $fetchedJobIds = array_column($jobs, 'id');
        
        $batchHealth = $this->computeBatchPipelineHealth($fetchedJobIds);
        $batchLastAct = $this->computeBatchLastActivity($fetchedJobIds);
        
        foreach ($jobs as &$job) {
            $job['health'] = $batchHealth[$job['id']] ?? $this->computePipelineHealth($job['id']);
            $daysOpen = (int)((time() - strtotime($job['created_at'])) / 86400);
            $job['days_open'] = $daysOpen;
            
            $lastActivity = $batchLastAct[$job['id']] ?? null;
            $job['days_since_activity'] = $lastActivity ? (int)((time() - strtotime($lastActivity)) / 86400) : $daysOpen;
            $job['formatted_date'] = date('M j, Y', strtotime($job['created_at']));
        }
        
        $departments = DB::table('jobs')->whereNotNull('department')->where('department', '!=', '')->distinct()->pluck('department');
        
        return response()->json([
            'success' => true,
            'jobs' => $jobs,
            'departments' => $departments
        ]);
    }
}