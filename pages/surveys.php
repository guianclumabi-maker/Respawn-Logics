<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
$current_page = 'surveys.php';

$isAdmin = hasPermission('surveys.manage');
?>
<?php $page_title = 'Engagement Surveys - Respawn Logics'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color); }
        .title-block h1 { font-family: 'Space Grotesk'; font-size: 1.75rem; color: var(--text-primary); margin: 0 0 4px 0; }
        .title-block p { color: var(--text-muted); margin: 0; font-size: 0.95rem; }

        .survey-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        
        .survey-card {
            background: var(--bg-secondary); border: 1px solid var(--border-color);
            border-radius: var(--radius-lg); padding: 20px; display: flex; flex-direction: column;
            transition: transform 0.2s;
        }
        .survey-card:hover { transform: translateY(-3px); border-color: rgba(255,255,255,0.2); }
        .survey-title { font-family: 'Space Grotesk'; font-size: 1.2rem; color: var(--text-primary); margin: 0 0 8px 0; }
        .survey-desc { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px; flex-grow: 1; }
        
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; margin-bottom: 12px; }
        .status-badge.Draft { background: rgba(156,163,175,0.1); color: #9ca3af; }
        .status-badge.Active { background: rgba(0, 224, 122,0.1); color: #00e07a; }
        .status-badge.Closed { background: rgba(239,68,68,0.1); color: #ef4444; }

        .metric-row { display: flex; justify-content: space-between; border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: auto; margin-bottom: 16px;}
        .metric { text-align: center; }
        .metric-val { font-size: 1.5rem; font-weight: 700; font-family: 'Space Grotesk'; }
        .metric-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;}

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: var(--bg-secondary); width: 600px; max-height: 90vh; overflow-y: auto; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; color: white; font-family: 'Space Grotesk'; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; }

        /* Take Survey UI */
        .question-block { margin-bottom: 30px; background: rgba(255,255,255,0.02); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border-color); }
        .q-text { font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 16px; }
        
        .enps-scale { display: flex; gap: 8px; flex-wrap: wrap; }
        .enps-btn { 
            width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;
            background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 6px;
            color: var(--text-primary); font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .enps-btn:hover { border-color: var(--accent-blue); }
        .enps-btn.selected { background: var(--accent-blue); border-color: var(--accent-blue); color: white; }

        .text-answer { width: 100%; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 6px; padding: 12px; color: var(--text-primary); min-height: 100px; font-family: 'Space Grotesk'; resize: vertical; }
        .text-answer:focus { outline: none; border-color: var(--accent-blue); }

        .enps-score-display { color: #00e07a; }
        .enps-score-display.negative { color: #ef4444; }
    </style>


<body>
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>
            
            <div class="content-wrapper">
                <div class="page-header">
                    <div class="title-block">
                        <h1>Engagement Surveys</h1>
                        <p>Company-wide pulse surveys and eNPS tracking.</p>
                    </div>
                    <?php if ($isAdmin): ?>
                        <button class="btn btn-primary" onclick="openBuilderModal()"><i class="fa-solid fa-plus"></i> Create New Survey</button>
                    <?php endif; ?>
                </div>

                <div class="survey-grid" id="surveyContainer">
                    <div style="color: var(--text-muted); padding: 20px;">Loading surveys...</div>
                </div>

            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <!-- Create Survey Modal -->
    <div class="modal" id="builderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Survey Builder</h3>
                <button class="btn btn-secondary" style="padding: 4px 8px;" onclick="closeBuilderModal()"><i class="fa-solid fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Survey Title</label>
                    <input type="text" id="newTitle" class="form-input" placeholder="e.g. Q3 Company Pulse">
                </div>
                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label">Description (Optional)</label>
                    <textarea id="newDesc" class="form-input" style="min-height: 80px;"></textarea>
                </div>
                
                <div style="background: rgba(0, 224, 122,0.1); border: 1px solid rgba(0, 224, 122,0.3); border-radius: 6px; padding: 12px; margin-bottom: 24px;">
                    <div style="color: #00e07a; font-weight: 600; font-size: 0.85rem; margin-bottom: 4px;"><i class="fa-solid fa-magic"></i> Auto-Injected</div>
                    <div style="color: #d1d5db; font-size: 0.9rem;">The standard 0-10 eNPS question will automatically be added to this survey to calculate your score.</div>
                </div>

                <h4>Custom Questions</h4>
                <div id="customQuestionsList"></div>
                <button class="btn btn-secondary" onclick="addQuestionField()" style="width: 100%; margin-top: 12px;"><i class="fa-solid fa-plus"></i> Add Text Question</button>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeBuilderModal()">Cancel</button>
                <button class="btn btn-primary" onclick="createSurvey()">Draft Survey</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Take Survey Modal -->
    <div class="modal" id="takeModal">
        <div class="modal-content" style="width: 700px;">
            <div class="modal-header">
                <h3 id="takeTitle">Taking Survey</h3>
                <button class="btn btn-secondary" style="padding: 4px 8px;" onclick="closeTakeModal()"><i class="fa-solid fa-times"></i></button>
            </div>
            <div class="modal-body" id="takeBody" style="background: var(--bg-primary);">
                <!-- Dynamic Questions -->
            </div>
            <div class="modal-footer">
                <div style="flex-grow: 1; font-size: 0.85rem; color: var(--text-muted);"><i class="fa-solid fa-user-secret"></i> Your responses are 100% anonymous.</div>
                <button class="btn btn-secondary" onclick="closeTakeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="submitSurvey()">Submit Anonymous Answers</button>
            </div>
        </div>
    </div>

    <script>
        const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
        let currentTakingSurveyId = 0;
        let customQuestions = [];

        async function loadSurveys() {
            const endpoint = isAdmin ? 'fetch_admin_surveys' : 'fetch_my_surveys';
            try {
                const res = await fetch(`<?= url('/api/index.php?route=surveys&action=') ?>${endpoint}`);
                const data = await res.json();
                
                const container = document.getElementById('surveyContainer');
                if (data.success) {
                    if (data.data.length === 0) {
                        container.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding: 40px; color: var(--text-muted);">No surveys available.</div>`;
                        return;
                    }

                    let html = '';
                    data.data.forEach(s => {
                        html += `<div class="survey-card">`;
                        
                        if (isAdmin) {
                            html += `<span class="status-badge ${s.status}">${s.status}</span>`;
                            html += `<h3 class="survey-title">${s.title}</h3>`;
                            
                            const scoreClass = s.enps !== null && s.enps < 0 ? 'negative' : '';
                            const scoreDisplay = s.enps !== null ? s.enps : '--';
                            
                            html += `
                                <div class="metric-row">
                                    <div class="metric">
                                        <div class="metric-val enps-score-display ${scoreClass}">${scoreDisplay}</div>
                                        <div class="metric-label">eNPS Score</div>
                                    </div>
                                    <div class="metric">
                                        <div class="metric-val" style="color:var(--text-primary);">${s.completion_rate}%</div>
                                        <div class="metric-label">Completion</div>
                                    </div>
                                    <div class="metric">
                                        <div class="metric-val" style="color:var(--text-primary);">${s.responses}</div>
                                        <div class="metric-label">Responses</div>
                                    </div>
                                </div>
                            `;

                            if (s.status === 'Draft') {
                                html += `<button class="btn btn-primary" onclick="launchSurvey(${s.id})" style="width:100%;"><i class="fa-solid fa-rocket"></i> Launch to Company</button>`;
                            } else {
                                html += `<button class="btn btn-secondary" disabled style="width:100%;">Live / Tracking</button>`;
                            }

                        } else {
                            // Employee View
                            html += `<span class="status-badge Active">Pending</span>`;
                            html += `<h3 class="survey-title">${s.title}</h3>`;
                            html += `<p class="survey-desc">${s.description || 'Please take a moment to fill out this pulse survey.'}</p>`;
                            
                            if (s.has_completed == 0) {
                                html += `<button class="btn btn-primary" onclick="openTakeModal(${s.id})" style="width:100%;"><i class="fa-solid fa-pen-to-square"></i> Take Survey</button>`;
                            } else {
                                html += `<button class="btn btn-secondary" disabled style="width:100%;"><i class="fa-solid fa-check"></i> Completed</button>`;
                            }
                        }

                        html += `</div>`;
                    });
                    container.innerHTML = html;
                }
            } catch (e) {
                console.error(e);
            }
        }

        /* --- Admin Functions --- */
        function openBuilderModal() {
            document.getElementById('newTitle').value = '';
            document.getElementById('newDesc').value = '';
            customQuestions = [];
            renderCustomQuestions();
            document.getElementById('builderModal').classList.add('show');
        }
        function closeBuilderModal() {
            document.getElementById('builderModal').classList.remove('show');
        }
        function addQuestionField() {
            customQuestions.push({ text: '' });
            renderCustomQuestions();
        }
        function renderCustomQuestions() {
            let html = '';
            customQuestions.forEach((q, idx) => {
                html += `
                <div style="display:flex; gap:8px; margin-bottom:8px;">
                    <input type="text" class="form-input" placeholder="Type your question..." value="${q.text}" onchange="customQuestions[${idx}].text = this.value">
                    <button class="btn btn-secondary" onclick="customQuestions.splice(${idx}, 1); renderCustomQuestions();" style="padding: 0 12px;"><i class="fa-solid fa-trash"></i></button>
                </div>`;
            });
            document.getElementById('customQuestionsList').innerHTML = html;
        }

        async function createSurvey() {
            const title = document.getElementById('newTitle').value;
            const desc = document.getElementById('newDesc').value;
            if (!title) return alert('Title required');

            try {
                const res = await fetch(`<?= url('/api/index.php?route=surveys&action=create_survey') ?>`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__ },
                    body: JSON.stringify({ title, description: desc, questions: customQuestions })
                });
                const data = await res.json();
                if (data.success) {
                    closeBuilderModal();
                    loadSurveys();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (e) { console.error(e); }
        }

        async function launchSurvey(id) {
            if (!confirm('This will blast a push notification to ALL employees telling them to take the survey. Proceed?')) return;
            try {
                const res = await fetch(`<?= url('/api/index.php?route=surveys&action=launch_survey') ?>`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__ },
                    body: JSON.stringify({ id })
                });
                const data = await res.json();
                if (data.success) {
                    alert('Survey launched globally!');
                    loadSurveys();
                }
            } catch (e) { console.error(e); }
        }

        /* --- Employee Functions --- */
        async function openTakeModal(id) {
            currentTakingSurveyId = id;
            try {
                const res = await fetch(`<?= url('/api/index.php?route=surveys&action=fetch_survey') ?>&id=${id}`);
                const data = await res.json();
                if (data.success) {
                    document.getElementById('takeTitle').innerText = data.data.survey.title;
                    
                    let html = '';
                    data.data.questions.forEach((q, idx) => {
                        html += `<div class="question-block" data-qid="${q.id}">`;
                        html += `<div class="q-text">${idx+1}. ${q.question_text}</div>`;
                        
                        if (q.question_type === 'eNPS') {
                            html += `<div class="enps-scale">`;
                            for (let i = 0; i <= 10; i++) {
                                html += `<div class="enps-btn" onclick="selectEnps(this, ${i})">${i}</div>`;
                            }
                            html += `</div>`;
                            html += `<div style="display:flex; justify-content:space-between; margin-top:8px; font-size:0.75rem; color:var(--text-muted);"><span>Not likely</span><span>Extremely likely</span></div>`;
                            html += `<input type="hidden" class="ans-val" value="">`;
                        } else {
                            html += `<textarea class="text-answer ans-val" placeholder="Type your answer here..."></textarea>`;
                        }
                        html += `</div>`;
                    });
                    
                    document.getElementById('takeBody').innerHTML = html;
                    document.getElementById('takeModal').classList.add('show');
                }
            } catch (e) { console.error(e); }
        }

        function closeTakeModal() {
            document.getElementById('takeModal').classList.remove('show');
        }

        function selectEnps(btn, val) {
            const block = btn.closest('.question-block');
            const btns = block.querySelectorAll('.enps-btn');
            btns.forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            block.querySelector('.ans-val').value = val;
        }

        async function submitSurvey() {
            const blocks = document.querySelectorAll('.question-block');
            let answers = [];
            let isValid = true;

            blocks.forEach(b => {
                const qid = b.getAttribute('data-qid');
                const val = b.querySelector('.ans-val').value;
                if (val === '') isValid = false;
                answers.push({ question_id: qid, value: val });
            });

            if (!isValid) return alert('Please answer all questions before submitting.');

            try {
                const res = await fetch(`<?= url('/api/index.php?route=surveys&action=submit_survey') ?>`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__ },
                    body: JSON.stringify({ survey_id: currentTakingSurveyId, answers })
                });
                const data = await res.json();
                if (data.success) {
                    alert('Thank you! Your anonymous responses have been recorded.');
                    closeTakeModal();
                    loadSurveys();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (e) { console.error(e); }
        }

        document.addEventListener('DOMContentLoaded', loadSurveys);
    </script>
</body>
</html>
