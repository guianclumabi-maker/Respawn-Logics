<?php
namespace Respawn\Services\Scoring;

class LLMScoringProvider implements ScoringProvider {
    
    public function score(array $candidate, array $job): array {
        $apiKey = getenv('GEMINI_API_KEY');
        if (!$apiKey) {
            error_log("LLMScoringProvider: GEMINI_API_KEY is missing. Falling back to heuristic scoring.");
            return (new HeuristicScoringProvider())->score($candidate, $job);
        }

        $resumeText = $candidate['resume_text'] ?? '';
        $jobTitle = $job['title'] ?? 'Unknown Position';
        $jobDesc = $job['description'] ?? '';
        $jobReqs = $job['requirements'] ?? '';

        if (empty(trim($resumeText))) {
            return [
                'total' => 0,
                'breakdown' => ['error' => 'No resume text available for AI analysis.'],
                'source' => 'Google Gemini (Failed)'
            ];
        }

        $prompt = "You are an expert ATS (Applicant Tracking System) AI assistant.
Your task is to analyze a candidate's resume against a job description and return a strict JSON object with the match score.

Job Title: $jobTitle
Job Description:
$jobDesc
$jobReqs

Candidate Resume Text:
$resumeText

Analyze the candidate's skills, experience, and overall fit for the job. 
Return ONLY a valid JSON object in the following format (no markdown code blocks, just raw JSON):
{
  \"total\": <integer between 0 and 100>,
  \"breakdown\": {
    \"skills_match\": <integer between 0 and 100>,
    \"experience_match\": <integer between 0 and 100>,
    \"summary_feedback\": \"<A short 2-sentence summary of why they scored this way>\"
  }
}
Do not include any extra text outside the JSON.";

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.2,
                "response_mime_type" => "application/json"
            ]
        ];

        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("LLMScoringProvider API Error: $response");
            return [
                'total' => 0,
                'breakdown' => ['error' => 'LLM API request failed.'],
                'source' => 'Google Gemini'
            ];
        }

        $data = json_decode($response, true);
        $resultText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        $parsed = json_decode($resultText, true);

        if (!$parsed || !isset($parsed['total'])) {
            error_log("LLMScoringProvider JSON Parse Error. Raw: $resultText");
            return [
                'total' => 0,
                'breakdown' => ['error' => 'Failed to parse AI response.'],
                'source' => 'Google Gemini'
            ];
        }

        return [
            'total' => (int)$parsed['total'],
            'breakdown' => $parsed['breakdown'] ?? [],
            'source' => 'Google Gemini'
        ];
    }
}
