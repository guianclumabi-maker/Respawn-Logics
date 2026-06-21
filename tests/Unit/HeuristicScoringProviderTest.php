<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// The provider requires its interface
require_once __DIR__ . '/../../backend/services/Scoring/ScoringProvider.php';
require_once __DIR__ . '/../../backend/services/Scoring/HeuristicScoringProvider.php';

use Respawn\Services\Scoring\HeuristicScoringProvider;

class HeuristicScoringProviderTest extends TestCase {
    private HeuristicScoringProvider $provider;

    protected function setUp(): void {
        $this->provider = new HeuristicScoringProvider();
    }

    public function testEmptyInputsReturnZeroScore() {
        $result = $this->provider->score([], []);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals('heuristic', $result['source']);
        $this->assertEquals(['skill_fit' => 0, 'experience_fit' => 0, 'location_fit' => 0, 'salary_fit' => 0], $result['breakdown']);
    }

    public function testPerfectMatch() {
        $candidate = [
            'skills' => 'PHP, React, MySQL',
            'experience_years' => 5,
            'location' => 'New York',
            'salary_expectation' => 100000
        ];
        
        $job = [
            'requirements' => 'Needs 5 years of experience. PHP React MySQL.',
            'description' => 'Looking for a dev.',
            'location' => 'New York',
            'salary_min' => 90000,
            'salary_max' => 110000
        ];

        $result = $this->provider->score($candidate, $job);
        
        $this->assertEquals(100, $result['breakdown']['skill_fit']);
        // 5 / 5 = 1, min(1, 1.5)/1.5 * 100 = 67 (rounded)
        $this->assertEquals(67, $result['breakdown']['experience_fit']); 
        $this->assertEquals(100, $result['breakdown']['location_fit']);
        $this->assertEquals(100, $result['breakdown']['salary_fit']);
        
        // Total = 100*.4 + 67*.25 + 100*.2 + 100*.15 = 40 + 16.75 + 20 + 15 = 91.75 -> 92
        $this->assertEquals(92, $result['total']);
    }

    public function testSalaryOutOfRangeHigh() {
        $candidate = ['salary_expectation' => 150000];
        $job = ['salary_min' => 90000, 'salary_max' => 110000];
        $result = $this->provider->score($candidate, $job);
        
        // 150k > 110k * 1.2 (132k)
        $this->assertEquals(20, $result['breakdown']['salary_fit']);
    }

    public function testLocationRemoteMatch() {
        $candidate = ['location' => 'Remote Worker'];
        $job = ['location' => 'New York'];
        $result = $this->provider->score($candidate, $job);
        
        $this->assertEquals(80, $result['breakdown']['location_fit']);
    }
}
