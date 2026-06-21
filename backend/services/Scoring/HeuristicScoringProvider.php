<?php
namespace Respawn\Services\Scoring;

class HeuristicScoringProvider implements ScoringProvider {
    public function score(array $c, array $j): array {
        if (!$c || !$j) return [
            'total' => 0, 
            'breakdown' => ['skill_fit' => 0, 'experience_fit' => 0, 'location_fit' => 0, 'salary_fit' => 0],
            'source' => 'heuristic'
        ];
        
        $candidateSkills = array_map('trim', array_map('strtolower', explode(',', $c['skills'] ?? '')));
        $jobReqs = strtolower(($j['requirements'] ?? '') . ' ' . ($j['description'] ?? ''));
        $matchedSkills = 0;
        $totalSkills = max(count(array_filter($candidateSkills)), 1);
        foreach ($candidateSkills as $skill) {
            if (!empty($skill) && strpos($jobReqs, $skill) !== false) {
                $matchedSkills++;
            }
        }
        $skillFit = round(($matchedSkills / $totalSkills) * 100);
        
        $candidateYears = (int)($c['experience_years'] ?? 0);
        preg_match('/(\d+)\+?\s*(?:years?|yrs?)/i', $j['requirements'] ?? '', $m);
        $requiredYears = (int)($m[1] ?? 3);
        $experienceFit = $requiredYears > 0 ? round(min($candidateYears / $requiredYears, 1.5) / 1.5 * 100) : 75;
        
        $candidateLoc = strtolower(trim($c['location'] ?? ''));
        $jobLoc = strtolower(trim($j['location'] ?? ''));
        if (empty($candidateLoc) || empty($jobLoc)) {
            $locationFit = 50;
        } elseif ($candidateLoc === $jobLoc) {
            $locationFit = 100;
        } elseif (strpos($candidateLoc, $jobLoc) !== false || strpos($jobLoc, $candidateLoc) !== false) {
            $locationFit = 75;
        } elseif (stripos($jobLoc, 'remote') !== false || stripos($candidateLoc, 'remote') !== false) {
            $locationFit = 80;
        } else {
            $locationFit = 30;
        }
        
        $salaryExp = (float)($c['salary_expectation'] ?? 0);
        $salaryMin = (float)($j['salary_min'] ?? 0);
        $salaryMax = (float)($j['salary_max'] ?? 0);
        if ($salaryExp <= 0 || ($salaryMin <= 0 && $salaryMax <= 0)) {
            $salaryFit = 60;
        } elseif ($salaryExp >= $salaryMin && $salaryExp <= $salaryMax) {
            $salaryFit = 100;
        } elseif ($salaryExp < $salaryMin) {
            $salaryFit = 90;
        } elseif ($salaryMax > 0 && $salaryExp <= $salaryMax * 1.2) {
            $salaryFit = 50;
        } else {
            $salaryFit = 20;
        }
        
        $total = round($skillFit * 0.40 + $experienceFit * 0.25 + $locationFit * 0.20 + $salaryFit * 0.15);
        
        return [
            'total' => $total,
            'breakdown' => [
                'skill_fit' => $skillFit,
                'experience_fit' => $experienceFit,
                'location_fit' => $locationFit,
                'salary_fit' => $salaryFit
            ],
            'source' => 'heuristic'
        ];
    }
}
