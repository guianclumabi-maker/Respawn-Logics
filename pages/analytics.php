<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
if (!hasPermission('analytics.view')) {
    header('Location: ' . url('/pages/dashboard.php'));
    exit;
}

$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<?php $page_title = 'Workforce Analytics - Respawn Logic'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    .global-glow-purple {
            position: fixed; bottom: -150px; right: -100px; width: 600px; height: 600px; border-radius: 50%; background: #9b6dff; filter: blur(140px); opacity: 0.06; pointer-events: none; z-index: -1;
        }

        .admin-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .admin-title h1 { font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 4px; }
        .admin-title p { font-size: 0.875rem; color: var(--text-muted); }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .chart-card {
            background: rgba(22, 25, 34, 0.7);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px;
            display: flex;
            flex-direction: column;
        }
        
        .chart-card.full-width {
            grid-column: 1 / -1;
        }

        .chart-header {
            margin-bottom: 20px;
        }
        .chart-header h3 {
            font-size: 1.1rem;
            color: white;
            margin: 0;
        }
        .chart-header p {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            font-size: 0.875rem;
            font-style: italic;
        }
    </style>


<body>
    <div class="ambient-glow glow-blue"></div>

    <div class="app-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>

            <div class="admin-header">
                <div class="admin-title">
                    <h1>Workforce Analytics</h1>
                    <p>Executive Dashboard: Real-time insights into your human capital.</p>
                </div>
            </div>

            <div class="dashboard-grid">
                
                <!-- Headcount by Department (Doughnut) -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Headcount by Department</h3>
                        <p>Distribution of active employees.</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="chart-headcount"></canvas>
                        <div id="empty-headcount" class="empty-state" style="display:none;">No active employees found.</div>
                    </div>
                </div>

                <!-- Talent Density / 9-Box Distribution (Bar) -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Talent Density</h3>
                        <p>Company-wide 9-Box calibration distribution.</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="chart-talent"></canvas>
                        <div id="empty-talent" class="empty-state" style="display:none;">No finalized performance reviews yet.</div>
                    </div>
                </div>

                <!-- Payroll Cost Trend (Line) -->
                <div class="chart-card full-width">
                    <div class="chart-header">
                        <h3>Payroll Expense Trend</h3>
                        <p>Total Gross Payroll per pay cycle (Processed & Locked runs).</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="chart-payroll"></canvas>
                        <div id="empty-payroll" class="empty-state" style="display:none;">No processed payroll runs yet.</div>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <script>
        // Global Chart.js defaults for dark theme
        Chart.defaults.color = 'rgba(255, 255, 255, 0.5)';
        Chart.defaults.font.family = 'Inter, sans-serif';
        Chart.defaults.scale.grid.color = 'rgba(255, 255, 255, 0.05)';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.9)';
        Chart.defaults.plugins.tooltip.titleColor = '#fff';
        Chart.defaults.plugins.tooltip.bodyColor = '#cbd5e1';
        Chart.defaults.plugins.tooltip.borderColor = 'rgba(255,255,255,0.1)';
        Chart.defaults.plugins.tooltip.borderWidth = 1;
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;

        const modernPalette = [
            '#00e07a', // Violet
            '#3b82f6', // Blue
            '#00e07a', // Emerald
            '#f59e0b', // Amber
            '#ef4444', // Red
            '#9b6dff', // Pink
            '#06b6d4', // Cyan
            '#f97316'  // Orange
        ];

        async function initDashboard() {
            await Promise.all([
                loadHeadcount(),
                loadTalentDensity(),
                loadPayrollTrend()
            ]);
        }

        async function loadHeadcount() {
            try {
                const res = await fetch(`<?= url('/analytics_api.php?action=headcount_by_dept') ?>`);
                const data = await res.json();
                
                if (data.success && data.labels.length > 0) {
                    const ctx = document.getElementById('chart-headcount').getContext('2d');
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.data,
                                backgroundColor: modernPalette,
                                borderWidth: 0,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'right' }
                            },
                            cutout: '70%'
                        }
                    });
                } else {
                    document.getElementById('chart-headcount').style.display = 'none';
                    document.getElementById('empty-headcount').style.display = 'flex';
                }
            } catch(e) {}
        }

        async function loadTalentDensity() {
            try {
                const res = await fetch(`<?= url('/analytics_api.php?action=talent_density') ?>`);
                const data = await res.json();
                
                // Only render if there is at least one non-zero value
                const hasData = data.data && data.data.some(val => val > 0);

                if (data.success && hasData) {
                    const ctx = document.getElementById('chart-talent').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Employees',
                                data: data.data,
                                backgroundColor: '#00e07a',
                                borderRadius: 4,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { precision: 0 } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                } else {
                    document.getElementById('chart-talent').style.display = 'none';
                    document.getElementById('empty-talent').style.display = 'flex';
                }
            } catch(e) {}
        }

        async function loadPayrollTrend() {
            try {
                const res = await fetch(`<?= url('/analytics_api.php?action=payroll_trend') ?>`);
                const data = await res.json();
                
                if (data.success && data.labels.length > 0) {
                    const ctx = document.getElementById('chart-payroll').getContext('2d');
                    
                    // Create gradient for the line chart fill
                    let gradient = ctx.createLinearGradient(0, 0, 0, 300);
                    gradient.addColorStop(0, 'rgba(0, 224, 122, 0.4)');
                    gradient.addColorStop(1, 'rgba(0, 224, 122, 0.0)');

                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Total Gross Payroll ($)',
                                data: data.data,
                                borderColor: '#00e07a',
                                backgroundColor: gradient,
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4, // Smooth curves
                                pointBackgroundColor: '#fff',
                                pointBorderColor: '#00e07a',
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: { 
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '$' + value.toLocaleString();
                                        }
                                    }
                                },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                } else {
                    document.getElementById('chart-payroll').style.display = 'none';
                    document.getElementById('empty-payroll').style.display = 'flex';
                }
            } catch(e) {}
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', initDashboard);
    </script>
</body>
</html>
