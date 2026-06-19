<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
?>
<?php $page_title = 'AI Companion - Respawn Logic Portal'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>
        /* Modern Chatbot UI Vanilla CSS */
        .chat-layout {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 80px); /* Adjust based on header */
            background: rgba(15, 15, 20, 0.6);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }

        .chat-header-bar {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background: rgba(20, 20, 25, 0.8);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .ai-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            box-shadow: var(--shadow-glow-green);
        }

        .chat-title-info h2 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--text-primary);
        }

        .chat-title-info p {
            margin: 2px 0 0;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .chat-body {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
            scroll-behavior: smooth;
        }

        /* Message Bubbles */
        .message-row {
            display: flex;
            width: 100%;
            animation: slideUp 0.3s ease forwards;
        }

        .message-row.user {
            justify-content: flex-end;
        }

        .message-bubble {
            max-width: 75%;
            padding: 14px 18px;
            border-radius: 18px;
            font-size: 0.95rem;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .message-row.ai .message-bubble {
            background: rgba(40, 40, 50, 0.8);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-bottom-left-radius: 4px;
        }

        .message-row.user .message-bubble {
            background: var(--primary-gradient);
            color: #ffffff;
            border-bottom-right-radius: 4px;
            box-shadow: 0 4px 15px rgba(0, 224, 122, 0.2);
        }

        /* Markdown-like styling inside AI bubble */
        .message-bubble strong { font-weight: 600; color: #fff; }
        .message-bubble ul { margin: 10px 0; padding-left: 20px; }
        .message-bubble li { margin-bottom: 5px; }

        .chat-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            background: rgba(20, 20, 25, 0.8);
        }

        .input-group {
            display: flex;
            gap: 10px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 5px 5px 5px 20px;
            align-items: center;
            transition: border-color 0.3s ease;
        }

        .input-group:focus-within {
            border-color: #00e07a;
        }

        .input-group input {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 0.95rem;
            outline: none;
            padding: 10px 0;
        }

        .input-group input::placeholder {
            color: var(--text-muted);
        }

        .send-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .send-btn:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-glow-green);
        }

        .send-btn:disabled {
            background: #444;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Typing Indicator Micro-Animation */
        .typing-indicator {
            display: flex;
            gap: 4px;
            padding: 5px;
        }
        .typing-indicator span {
            width: 6px;
            height: 6px;
            background: var(--text-muted);
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out both;
        }
        .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
        .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Suggested Prompts */
        .suggested-prompts {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .prompt-chip {
            background: rgba(0, 224, 122, 0.1);
            border: 1px solid rgba(0, 224, 122, 0.3);
            color: #c084fc;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .prompt-chip:hover {
            background: rgba(0, 224, 122, 0.2);
            border-color: rgba(0, 224, 122, 0.5);
        }

    </style>


<body>
    <div class="global-glow-green"></div>
    <div class="global-glow-purple"></div>

    <div class="app-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>

            <div class="chat-layout">
                <div class="chat-header-bar">
                    <div class="ai-avatar">AI</div>
                    <div class="chat-title-info">
                        <h2>HR Intelligence Companion</h2>
                        <p>Powered by Respawn Logic Knowledge Base</p>
                    </div>
                </div>

                <div class="chat-body" id="chatBody">
                    <div class="message-row ai">
                        <div class="message-bubble">
                            Hello <?= htmlspecialchars($user['full_name'] ?? 'there') ?>! I am your HR Intelligence Companion. I can search labor laws, analyze Employee Relations precedents, or draft incident reports. How can I assist you today?
                        </div>
                    </div>
                </div>

                <div class="chat-footer">
                    <div class="suggested-prompts" id="suggestedPrompts">
                        <div class="prompt-chip" onclick="fillPrompt('What is the policy on tardiness?')">What is the policy on tardiness?</div>
                        <div class="prompt-chip" onclick="fillPrompt('Draft an incident report')">Draft an incident report</div>
                        <div class="prompt-chip" onclick="fillPrompt('Analyze Case #12 resolution')">Analyze Case #12 resolution</div>
                    </div>
                    
                    <div class="input-group">
                        <input type="text" id="chatInput" placeholder="Ask a question about HR policies or cases..." autocomplete="off">
                        <button id="sendBtn" class="send-btn">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const chatBody = document.getElementById('chatBody');
        const chatInput = document.getElementById('chatInput');
        const sendBtn = document.getElementById('sendBtn');
        const suggestedPrompts = document.getElementById('suggestedPrompts');

        function fillPrompt(text) {
            chatInput.value = text;
            chatInput.focus();
        }

        function appendMessage(text, sender, isHtml = false) {
            const row = document.createElement('div');
            row.className = 'message-row ' + sender;
            
            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';
            
            if (isHtml) {
                // Simple bold parsing for markdown
                let formattedText = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                // Simple newline to br parsing
                formattedText = formattedText.replace(/\n/g, '<br>');
                // Simple bullet list parsing
                formattedText = formattedText.replace(/\* (.*)/g, '<li>$1</li>');
                
                bubble.innerHTML = formattedText;
            } else {
                bubble.textContent = text;
            }
            
            row.appendChild(bubble);
            chatBody.appendChild(row);
            scrollToBottom();
        }

        function appendTypingIndicator() {
            const row = document.createElement('div');
            row.className = 'message-row ai';
            row.id = 'typingIndicator';
            
            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';
            bubble.innerHTML = '<div class="typing-indicator"><span></span><span></span><span></span></div>';
            
            row.appendChild(bubble);
            chatBody.appendChild(row);
            scrollToBottom();
        }

        function removeTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            if (indicator) indicator.remove();
        }

        function scrollToBottom() {
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        async function handleSend() {
            const message = chatInput.value.trim();
            if (!message) return;

            // Hide suggested prompts after first interaction
            if (suggestedPrompts) {
                suggestedPrompts.style.display = 'none';
            }

            // Append User message
            appendMessage(message, 'user');
            chatInput.value = '';
            sendBtn.disabled = true;

            // Show typing indicator
            appendTypingIndicator();

            try {
                // POST to the backend
                const basePath = window.location.hostname === 'localhost' ? '/respawn-logics' : '';
                const response = await fetch(`${basePath}/api/index.php?route=ai_companion&action=chat`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: message })
                });

                const data = await response.json();
                
                removeTypingIndicator();
                sendBtn.disabled = false;

                if (data.success && data.reply) {
                    appendMessage(data.reply, 'ai', true);
                } else {
                    appendMessage("I'm sorry, I encountered an error connecting to the knowledge base.", 'ai');
                }
            } catch (error) {
                removeTypingIndicator();
                sendBtn.disabled = false;
                appendMessage("Network error. Please try again later.", 'ai');
            }
            
            chatInput.focus();
        }

        sendBtn.addEventListener('click', handleSend);
        chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleSend();
            }
        });
    </script>
</body>
</html>
