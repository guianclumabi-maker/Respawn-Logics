<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../includes/auth.php';

// Fetch Salary Bands
$stmtBands = $pdo->query("SELECT * FROM compensation_bands ORDER BY min_salary ASC");
$bands = $stmtBands->fetchAll();

// Fetch Equity Grants
$stmtEq = $pdo->query("SELECT * FROM employee_equity ORDER BY grant_date DESC");
$equity = $stmtEq->fetchAll();

$current_page = 'compensation_admin.php';
?>
<?php $page_title = 'Compensation & Equity - Respawn Logics'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>
        .page-header {
            background: #ffffff;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .title-block h1 {
            font-family: 'Space Grotesk';
            font-size: 1.5rem;
            color: #111827;
            margin: 0 0 4px 0;
        }
        .title-block p {
            color: #6b7280;
            margin: 0;
            font-size: 0.95rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
            margin-bottom: 30px;
        }
        .data-table th {
            background: #f9fafb;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            font-family: 'Space Grotesk';
        }
        .data-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
            color: #4b5563;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .currency {
            font-family: monospace;
            color: #374151;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-esop { background: #e0e7ff; color: #4338ca; }
        .badge-rsu { background: #dcfce7; color: #15803d; }
        .badge-phantom { background: #fef3c7; color: #b45309; }

        .btn-secondary {
            background: #ffffff;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-secondary:hover {
            background: #f9fafb;
        }
    </style>


<body class="theme-light">
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <h1 class="page-title">Total Rewards</h1>
                </div>
            </header>
            
            <div class="content-wrapper">
                
                <div class="page-header">
                    <div class="title-block">
                        <h1>Compensation & Equity Planning</h1>
                        <p>Manage salary parity bands and track employee stock option grants.</p>
                    </div>
                    <button class="btn-secondary">Export Cap Table</button>
                </div>

                <h2 style="font-family: 'Space Grotesk'; margin-bottom: 15px; color: #111827;">Salary Bands (Pay Equity)</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Minimum Salary</th>
                            <th>Midpoint Salary</th>
                            <th>Maximum Salary</th>
                            <th>Currency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bands as $b): ?>
                        <tr>
                            <td style="font-weight: 600;"><?= htmlspecialchars($b['job_title']) ?></td>
                            <td class="currency"><?= number_format($b['min_salary']) ?></td>
                            <td class="currency"><?= number_format($b['mid_salary']) ?></td>
                            <td class="currency"><?= number_format($b['max_salary']) ?></td>
                            <td><?= htmlspecialchars($b['currency']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="font-family: 'Space Grotesk'; margin-bottom: 15px; color: #111827;">Equity Grants (Cap Table Ledger)</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Grant Type</th>
                            <th>Total Shares</th>
                            <th>Vested Shares</th>
                            <th>Vesting Schedule</th>
                            <th>Grant Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equity as $e): 
                            $badgeClass = 'badge-esop';
                            if ($e['grant_type'] === 'RSU') $badgeClass = 'badge-rsu';
                            if ($e['grant_type'] === 'Phantom') $badgeClass = 'badge-phantom';
                        ?>
                        <tr>
                            <td style="font-weight: 600;"><?= htmlspecialchars($e['employee_name']) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($e['grant_type']) ?></span></td>
                            <td style="font-weight: 600;"><?= number_format($e['total_shares']) ?></td>
                            <td style="color: #00e07a; font-weight: 500;"><?= number_format($e['vested_shares']) ?></td>
                            <td><?= htmlspecialchars($e['vesting_schedule']) ?></td>
                            <td><?= date('M d, Y', strtotime($e['grant_date'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            </div>
        </div>
    </div>
</body>
</html>
