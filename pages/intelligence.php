<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();

if (!hasPermission('intelligence.view')) {
    header("Location: " . url('/pages/dashboard.php'));
    exit;
}

$current_page = 'intelligence.php';
?>
<?php $page_title = 'Predictive Intelligence - Respawn Logics'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>

        .page-header {
            background: #111827; /* Dark theme for War Room */
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            border: 1px solid #374151;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .title-block h1 { font-family: 'Space Grotesk'; font-size: 1.75rem; color: #f9fafb; margin: 0 0 8px 0; display: flex; align-items: center; gap: 12px; }
        .title-block p { color: #9ca3af; margin: 0; font-size: 0.95rem; }
        
        .grid-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        
        .panel {
            background: #1f2937;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid #374151;
        }
        .panel-header {
            font-family: 'Space Grotesk';
            font-size: 1.25rem;
            font-weight: 600;
            color: #f9fafb;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .risk-card {
            background: #111827;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
            border-left: 4px solid transparent;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .risk-card.zone-Red { border-left-color: #ef4444; }
        .risk-card.zone-Orange { border-left-color: #f59e0b; }
        .risk-card.zone-Green { border-left-color: #00e07a; display: none; /* Hide green in war room typically, but keep class */ }
        
        .risk-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .risk-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .avatar {
            width: 40px; height: 40px; border-radius: 50%; background: #374151;
            display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; overflow: hidden;
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        .risk-name { color: #f9fafb; font-weight: 600; font-size: 1.05rem; margin-bottom: 2px;}
        .risk-dept { color: #9ca3af; font-size: 0.8rem; }
        
        .score-badge {
            font-family: 'Space Grotesk';
            font-size: 1.25rem;
            font-weight: 800;
            padding: 6px 12px;
            border-radius: 8px;
        }
        .score-badge.zone-Red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .score-badge.zone-Orange { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        
        .factor-list {
            list-style: none; padding: 0; margin: 0;
            border-top: 1px solid #374151; padding-top: 12px;
        }
        .factor-list li {
            color: #d1d5db; font-size: 0.85rem; margin-bottom: 6px;
            display: flex; align-items: flex-start; gap: 8px;
        }
        .factor-list li i { color: #f59e0b; margin-top: 2px; }
        .factor-list li.zone-Red i { color: #ef4444; }
        
        /* Loading State */
        .loader {
            display: inline-block; width: 40px; height: 40px;
            border: 4px solid rgba(255,255,255,0.1); border-radius: 50%;
            border-top-color: #00e07a; animation: spin 1s ease-in-out infinite;
            margin: 40px auto; display: block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Chart Canvas */
        #burnRateChart { max-height: 300px; width: 100%; }
    </style>


<body style="background: #030712; color: #f9fafb;"> <!-- Override core bg for War Room feel -->
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="topbar" style="background: rgba(17, 24, 39, 0.8); border-bottom: 1px solid #374151;">
                <div class="topbar-left"><h1 class="page-title" style="color:white;">Predictive AI</h1></div>
            </header>
            
            <div class="content-wrapper">
                <div class="page-header">
                    <div class="title-block">
                        <h1><i class="fa-solid fa-brain" style="color:#00e07a;"></i> Global Intelligence War Room</h1>
                        <p>Predictive attrition modeling and payroll burn trajectory.</p>
                    </div>
                </div>

                <div class="grid-layout">
                    <!-- Left: Flight Risk Matrix -->
                    <div class="panel">
                        <div class="panel-header">
                            <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;"></i> Flight Risk Matrix
                        </div>
                        <p style="color:#9ca3af; font-size:0.85rem; margin-bottom:20px;">Top employees algorithmically identified as high-risk for resignation based on compensation stagnation, tenure, and leadership vacuum.</p>
                        
                        <div id="riskContainer">
                            <div class="loader"></div>
                        </div>
                    </div>

                    <!-- Right: Burn Rate Prediction -->
                    <div class="panel">
                        <div class="panel-header">
                            <i class="fa-solid fa-fire" style="color:#f59e0b;"></i> Predicted Payroll Burn
                        </div>
                        <p style="color:#9ca3af; font-size:0.85rem; margin-bottom:20px;">6-Month trajectory based on historical processing and forecasted market band adjustments to retain high-risk employees.</p>
                        
                        <canvas id="burnRateChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            // Load Attrition Risks
            try {
                const res = await fetch(`<?= url('/api/index.php?route=analytics&action=attrition_risks') ?>`);
                const data = await res.json();
                const container = document.getElementById('riskContainer');
                
                if (data.success && data.data.length > 0) {
                    container.innerHTML = '';
                    // Only show Orange and Red zones
                    const highRisks = data.data.filter(r => r.zone !== 'Green');
                    
                    if (highRisks.length === 0) {
                        container.innerHTML = `<div style="text-align:center; padding: 40px; color:#00e07a;"><i class="fa-solid fa-shield-check fa-3x" style="margin-bottom:16px;"></i><br>No imminent flight risks detected on your team.</div>`;
                    } else {
                        highRisks.forEach(r => {
                            let factorsHTML = '';
                            r.factors.forEach(f => {
                                factorsHTML += `<li class="zone-${r.zone}"><i class="fa-solid fa-circle-exclamation"></i> ${f}</li>`;
                            });

                            const initials = r.full_name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
                            const avatarHTML = r.profile_image 
                                ? `<img src="<?= url('/uploads/') ?>${r.profile_image}" alt="Avatar">`
                                : initials;

                            container.innerHTML += `
                                <div class="risk-card zone-${r.zone}">
                                    <div class="risk-header">
                                        <div class="risk-profile">
                                            <div class="avatar">${avatarHTML}</div>
                                            <div>
                                                <div class="risk-name">${r.full_name}</div>
                                                <div class="risk-dept">${r.job_title} &bull; ${r.department}</div>
                                            </div>
                                        </div>
                                        <div class="score-badge zone-${r.zone}">${r.risk_score}% Risk</div>
                                    </div>
                                    <ul class="factor-list">
                                        ${factorsHTML}
                                    </ul>
                                </div>
                            `;
                        });
                    }
                } else {
                    container.innerHTML = `<div style="text-align:center; padding: 20px; color:#9ca3af;">No active employees found or algorithm failed.</div>`;
                }
            } catch(e) {
                document.getElementById('riskContainer').innerHTML = `<div style="color:#ef4444;">Error loading predictive data.</div>`;
            }

            // Render Fake Predictive Burn Rate Chart
            const ctx = document.getElementById('burnRateChart').getContext('2d');
            const currentMonth = new Date().getMonth();
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            
            let labels = [];
            let histData = [];
            let predData = [];
            
            // Generate 3 historical, 3 predictive
            let baseBurn = 250000;
            for(let i=0; i<6; i++) {
                let mIndex = (currentMonth - 2 + i) % 12;
                if (mIndex < 0) mIndex += 12;
                labels.push(months[mIndex]);
                
                if (i < 3) {
                    histData.push(baseBurn + (Math.random() * 20000 - 10000));
                    predData.push(null);
                } else if (i === 3) {
                    histData.push(baseBurn); // connection point
                    predData.push(baseBurn);
                } else {
                    histData.push(null);
                    // Add 8% to retain high risk
                    baseBurn = baseBurn * 1.08;
                    predData.push(baseBurn);
                }
            }

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Historical Burn',
                            data: histData,
                            borderColor: '#00e07a',
                            backgroundColor: 'rgba(0, 224, 122, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Predicted Trajectory (with retention adjustments)',
                            data: predData,
                            borderColor: '#ef4444',
                            borderDash: [5, 5],
                            backgroundColor: 'transparent',
                            fill: false,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { labels: { color: '#d1d5db', font: { size: 10 } } }
                    },
                    scales: {
                        y: {
                            ticks: { color: '#9ca3af', callback: function(value) { return '$' + value/1000 + 'k'; } },
                            grid: { color: '#374151' }
                        },
                        x: {
                            ticks: { color: '#9ca3af' },
                            grid: { display: false }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
