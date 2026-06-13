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
     * Placeholder method for external LLM API Integration (e.g., OpenAI, Anthropic, Gemini).
     * This will allow true reasoning, summarization, and synthesis of the local database context.
     */
    private function callLLMAPI($prompt, $context)
    {
        $apiUrl = "https://api.placeholder-llm.com/v1/chat/completions";
        $apiKey = "YOUR_API_KEY_HERE"; // TODO: Move to .env when actual API is selected

        // System prompt to instruct the LLM on its role
        $systemPrompt = "You are an expert HR and Employee Relations AI Companion for Philippine Labor Law and company policies. 
        You must strictly synthesize and base your answers on the provided CONTEXT. Do not invent policies. 
        If the context is empty or says no precedent is found, state that you do not have enough internal data and suggest consulting HR.";

        // --- MOCK API RESPONSE START ---
        // For now, since we don't have a real API key, we simulate the LLM's synthesis.
        // Once an API key is available, remove this block and use the cURL request below.
        
        if (strpos($context, 'No internal policy') !== false) {
            if (stripos($prompt, 'draft') !== false || stripos($prompt, 'incident') !== false) {
                return "**Incident Report Draft**\n\n**Date of Incident:** [Insert Date]\n**Time:** [Insert Time]\n**Location:** [Insert Location]\n**Parties Involved:** [Insert Names]\n\n**Description of Event:**\n[Provide a clear, objective summary of the event.]\n\n*Would you like me to open a new case for this on the HR Cases Board?*";
            } elseif (stripos($prompt, 'case #12') !== false || stripos($prompt, 'resolution') !== false) {
                return "**Case Summary (Case #12: Unprofessional Conduct)**\n\n**Timeline:**\n* June 8, 2026: Incident reported by employee.\n\n**Policy References:**\n* Code of Conduct v2\n\n**Potential Risk:**\n* Medium (Interpersonal conflict affecting team morale)\n\n**Recommended Next Step:**\n* Initiate a Formal Mediation Session between the involved parties within 48 hours.";
            } else {
                return "I could not find a specific policy matching your query in the HR Knowledge Base. Try asking me to analyze a specific case or explain standard labor codes.";
            }
        }

        // Mocking the LLM synthesis of the context
        return "Based on our internal records:\n\n" . $context . "\n\n*(Note: This is a placeholder LLM synthesis. Connect a real API key to enable advanced reasoning and drafting.)*";
        // --- MOCK API RESPONSE END ---

        /*
        // ACTUAl API CALL LOGIC (Commented out until an API is chosen)
        $data = [
            "model" => "placeholder-model-name",
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => "CONTEXT:\n" . $context . "\n\nUSER QUESTION:\n" . $prompt]
            ],
            "temperature" => 0.2
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            return $responseData['choices'][0]['message']['content'] ?? "Error parsing LLM response.";
        }

        return "LLM API Error: Unable to fetch response from reasoning engine.";
        */
    }
}
