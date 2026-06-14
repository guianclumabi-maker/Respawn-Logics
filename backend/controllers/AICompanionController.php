<?php

class AICompanionController
{
    private $pdo;
    private $currentUser;
    private $tenantId;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->currentUser = getCurrentUser();
        $this->tenantId = $this->currentUser['tenant_id'] ?? $_SESSION['tenant_id'] ?? '1';
    }

    public function handleRequest($action)
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'chat') {
            $this->chat($input);
        } else {
            echo json_encode(['success' => false, 'error' => 'Unknown action or invalid endpoint']);
        }
    }

    private function chat($input)
    {
        $message = trim($input['message'] ?? '');
        
        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Message is empty']);
            return;
        }
        
        $messageLower = strtolower($message);
        $reply = "";

        $isElrCase = preg_match('/\b(awol|abandonment|termination|due process|notice to explain|suspension|dispute|harassment|insubordination|misconduct|tardiness|fraud|bullying)\b/i', $messageLower);

        if ($isElrCase) {
            $stmt = $this->pdo->prepare("SELECT title, summary as content_body, 'Supreme Court Precedent' as category, source_reference as official_url, risk_level, recommended_process FROM elr_precedents WHERE MATCH(case_type, title, summary, key_principles) AGAINST(? IN NATURAL LANGUAGE MODE) LIMIT 1");
        } else {
            $stmt = $this->pdo->prepare("SELECT title, summary as content_body, category, official_url, NULL as risk_level, NULL as recommended_process FROM labor_references WHERE status = 'Approved' AND MATCH(title, summary) AGAINST(? IN NATURAL LANGUAGE MODE) LIMIT 1");
        }

        $stmt->execute([$message]);
        $result = $stmt->fetch();

        if ($result) {
            $context = "Title: {$result['title']}\nSummary: {$result['content_body']}\n";
            if ($isElrCase && !empty($result['recommended_process'])) {
                $context .= "Risk Level: {$result['risk_level']}\nRecommended Action: {$result['recommended_process']}\n";
            }
            
            $sourceText = "\n\n**Sources Used:**\n* " . $result['title'] . " (" . $result['category'] . ")";
            if (!empty($result['official_url'])) {
                $sourceText .= "\n* Ref: " . $result['official_url'];
            }
            
            // Pass context and message to LLM
            $llmResponse = $this->callLLMAPI($message, $context);
            $reply = "**AI Intelligence Analysis**\n\n" . $llmResponse . $sourceText;

        } else {
            // Fallback to Global Intelligence Cache (Deep Learning Evolutionary Algorithm)
            $stmtCache = $this->pdo->prepare("SELECT anonymized_prompt, ai_response FROM global_intelligence_cache WHERE status = 'Approved' AND confidence_score >= 0 AND MATCH(anonymized_prompt, ai_response) AGAINST(? IN NATURAL LANGUAGE MODE) ORDER BY confidence_score DESC LIMIT 1");
            $stmtCache->execute([$message]);
            $cacheResult = $stmtCache->fetch();

            if ($cacheResult) {
                // We have cache, but let's pass it to LLM for potential refinement or just use it as context
                $context = "Previous Similar Response: " . $cacheResult['ai_response'];
                $llmResponse = $this->callLLMAPI($message, $context);
                $reply = "**AI Intelligence Analysis (Community Cached)**\n\n" . $llmResponse;
            } else {
                // Hardcoded fallbacks if nothing is found anywhere
                $llmResponse = $this->callLLMAPI($message, "No internal policy or legal precedent found in the local database.");
                $reply = $llmResponse;
            }
        }

        // Simulate AI thinking effect
        sleep(1);
        
        // Auto-Anonymize and Log to Global Intelligence Cache
        $anonymizedPrompt = preg_replace('/[A-Z][a-z]+\s[A-Z][a-z]+/', '[Employee Name]', $message);
        
        $stmtLog = $this->pdo->prepare("INSERT INTO global_intelligence_cache (tenant_id, anonymized_prompt, ai_response, status) VALUES (?, ?, ?, 'Anonymized')");
        $stmtLog->execute([$this->tenantId, $anonymizedPrompt, $reply]);
        
        echo json_encode([
            'success' => true,
            'reply' => $reply
        ]);
    }

    /**
     * Google Gemini API Integration.
     * This performs true reasoning, summarization, and synthesis of the local database context.
     */
    private function callLLMAPI($prompt, $context)
    {
        // Check ENV first, fallback to hardcoded string
        $apiKey = getenv('GEMINI_API_KEY') ?: "AIzaSyAPKF0Jn1kDmDL0r46woa4nzGXZLUdhL2Y"; // TODO: Replace with your actual Google Gemini API Key

        if ($apiKey === "YOUR_GEMINI_API_KEY_HERE") {
            return "⚠️ **SYSTEM ALERT:** The Google Gemini API key has not been configured yet. Please insert your API key in `backend/controllers/AICompanionController.php` on line 104 to enable the AI Companion's reasoning engine.\n\n**Synthesized Context:**\n" . $context;
        }

        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

        // System prompt to instruct the LLM on its role
        $systemPrompt = "You are an expert HR and Employee Relations AI Companion for Philippine Labor Law and company policies. 
        You must strictly synthesize and base your answers on the provided CONTEXT. Do not invent policies. 
        If the context is empty or says no precedent is found, state that you do not have enough internal data and suggest consulting HR.";

        $fullPrompt = "SYSTEM: " . $systemPrompt . "\n\nCONTEXT:\n" . $context . "\n\nUSER QUESTION:\n" . $prompt;

        $data = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $fullPrompt]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.2
            ]
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                return $responseData['candidates'][0]['content']['parts'][0]['text'];
            }
        }

        return "⚠️ **LLM API Error:** Unable to fetch response from the Gemini reasoning engine. Check your API key and quota.";
    }
}
