<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../includes/auth.php';

$user = getCurrentUser();
if (!hasRole(['Platform_Admin', 'Support_Agent', 'Implementation_Specialist', 'Super_Admin']) && (!empty($user['tenant_id']) && $user['tenant_id'] != '1')) {
    header("Location: dashboard.php");
    exit;
}

$current_page = 'saas_feedback.php';

// Fetch feedback tickets (Tag = 'Feedback' OR csat_score is not null)
$stmt = $pdo->prepare("
    SELECT pt.id, pt.subject, pt.status, pt.csat_score, pt.csat_comment, pt.created_at, t.company_name, u.full_name as reporter
    FROM platform_tickets pt
    LEFT JOIN tenants t ON pt.tenant_id = t.id
    LEFT JOIN users u ON pt.created_by = u.id
    LEFT JOIN platform_ticket_tags tag ON pt.id = tag.ticket_id AND tag.tag = 'Feedback'
    WHERE tag.id IS NOT NULL OR pt.csat_score IS NOT NULL
    GROUP BY pt.id
    ORDER BY pt.updated_at DESC
");
$stmt->execute();
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$reviews = [];
$suggestions = [];
foreach ($feedbacks as $fb) {
    if ($fb['csat_score'] !== null) {
        $reviews[] = $fb;
    } else {
        $suggestions[] = $fb;
    }
}

?>
<?php $page_title = 'Feedback Corner - Respawn Logic'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>

        .staff-header {
            background: #0f1422;
            border-radius: 8px;
            padding: 40px 30px;
            margin-bottom: 30px;
            box-shadow: 0 0 40px rgba(0, 224, 122, 0.05);
            border: 1px solid rgba(0, 224, 122, 0.2);
            position: relative;
            overflow: hidden;
        }
        .staff-header h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(to right, #00e07a, #00b8ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }
        .staff-header h2::after {
            content: '_';
            color: #00e07a;
            animation: blink 1s step-end infinite;
            -webkit-text-fill-color: #00e07a;
        }
        @keyframes blink { 50% { opacity: 0; } }
        .staff-header p {
            color: #8b95a8;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
            font-family: 'Space Grotesk', sans-serif;
        }
        
        .feedback-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .feedback-card {
            background: #0f1422;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .feedback-card:hover {
            border-color: rgba(0, 224, 122, 0.3);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 224, 122, 0.05);
        }

        .fc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .fc-company {
            font-size: 11px;
            color: #00e07a;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-family: 'JetBrains Mono', monospace;
        }

        .fc-reporter {
            font-size: 11px;
            color: #5e6a82;
            font-family: 'JetBrains Mono', monospace;
        }

        .fc-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .fc-score {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 12px;
            color: #00e07a;
        }

        .fc-score i {
            font-size: 0.9rem;
        }

        .fc-comment {
            background: rgba(0, 224, 122, 0.05);
            padding: 15px;
            border-radius: 4px;
            font-size: 14px;
            color: #c8d0e0;
            font-family: 'Inter', sans-serif;
            border-left: 2px solid #00e07a;
            flex: 1;
            margin-bottom: 16px;
        }

        .fc-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 15px;
            font-size: 11px;
            color: #5e6a82;
            font-family: 'JetBrains Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .badge-resolved { background: rgba(0, 224, 122, 0.1); color: #00e07a; border: 1px solid rgba(0, 224, 122, 0.2); }
        .badge-open { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .badge-progress { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }

    </style>


<body>
    <div class="app-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>
            
            <div style="padding: 10px;">
                <h1 style="font-family: 'Space Grotesk'; font-size: 2rem; color: white; margin-bottom: 20px;">Feedback Corner</h1>
                
                <div class="staff-header">
                    <h2><i class="fa-solid fa-star" style="color: #f59e0b;"></i> Customer Feedback & CSAT</h2>
                    <p>Review customer satisfaction ratings and feedback from the Global Support Inbox.</p>
                </div>

                <h3 style="font-family: 'Space Grotesk'; font-size: 1.5rem; color: #f0f4ff; margin-bottom: 15px;">Ticket Reviews</h3>
                <div class="feedback-grid">
                    <?php if (empty($reviews)): ?>
                        <div style="color:#9ca3af;">No CSAT scores received yet.</div>
                    <?php else: ?>
                        <?php foreach($reviews as $fb): ?>
                            <div class="feedback-card">
                                <div class="fc-header">
                                    <div class="fc-company"><?= htmlspecialchars($fb['company_name'] ?? 'Vendor / Global') ?></div>
                                    <div class="fc-reporter"><?= htmlspecialchars($fb['reporter'] ?? 'Unknown') ?></div>
                                </div>
                                <div class="fc-title">
                                    <?= htmlspecialchars($fb['subject']) ?>
                                </div>
                                
                                <div class="fc-score">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <?php if ($i <= $fb['csat_score']): ?>
                                            <i class="fa-solid fa-star"></i>
                                        <?php else: ?>
                                            <i class="fa-regular fa-star" style="color: rgba(245, 158, 11, 0.3);"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>

                                <?php if ($fb['csat_comment']): ?>
                                    <div class="fc-comment">"<?= htmlspecialchars($fb['csat_comment']) ?>"</div>
                                <?php else: ?>
                                    <div style="flex:1;"></div>
                                <?php endif; ?>

                                <div class="fc-footer">
                                    <span><?= date('M d, Y', strtotime($fb['created_at'])) ?></span>
                                    <a href="<?= url('/pages/saas_support.php') ?>" class="view-btn">View Ticket #<?= $fb['id'] ?></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <h3 style="font-family: 'Space Grotesk'; font-size: 1.5rem; color: #f0f4ff; margin-top: 40px; margin-bottom: 15px;">Suggestions</h3>
                <div class="feedback-grid">
                    <?php if (empty($suggestions)): ?>
                        <div style="color:#9ca3af;">No suggestions received yet.</div>
                    <?php else: ?>
                        <?php foreach($suggestions as $fb): ?>
                            <div class="feedback-card">
                                <div class="fc-header">
                                    <div class="fc-company"><?= htmlspecialchars($fb['company_name'] ?? 'Vendor / Global') ?></div>
                                    <div class="fc-reporter"><?= htmlspecialchars($fb['reporter'] ?? 'Unknown') ?></div>
                                </div>
                                <div class="fc-title">
                                    <?= htmlspecialchars($fb['subject']) ?>
                                </div>
                                
                                <div style="color: #6366f1; font-size: 0.85rem; font-weight: 500; margin-bottom: 12px; display: inline-block; background: rgba(99,102,241,0.1); padding: 4px 8px; border-radius: 4px;">
                                    <i class="fa-solid fa-lightbulb"></i> Suggestion
                                </div>

                                <?php if ($fb['csat_comment']): ?>
                                    <div class="fc-comment">"<?= htmlspecialchars($fb['csat_comment']) ?>"</div>
                                <?php else: ?>
                                    <div style="flex:1;"></div>
                                <?php endif; ?>

                                <div class="fc-footer">
                                    <span><?= date('M d, Y', strtotime($fb['created_at'])) ?></span>
                                    <a href="<?= url('/pages/saas_support.php') ?>" class="view-btn">View Ticket #<?= $fb['id'] ?></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
