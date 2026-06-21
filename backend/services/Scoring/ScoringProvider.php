<?php
namespace Respawn\Services\Scoring;

interface ScoringProvider {
    /**
     * Compute a match score between a candidate and a job.
     * @param array $candidate The candidate profile row.
     * @param array $job The job posting row.
     * @return array Array containing 'total' (int), 'breakdown' (array), and 'source' (string)
     */
    public function score(array $candidate, array $job): array;
}
