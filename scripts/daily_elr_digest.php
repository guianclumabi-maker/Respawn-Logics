<?php
/**
 * ELR daily digest — emails each tenant a summary of every case filed today
 * (auto + manual, all incident types). Meant to be run once every 24h by a scheduler.
 * Sends only when there's something to report, to the tenant's contact_email.
 *
 * Usage (CLI):  php scripts/daily_elr_digest.php
 * CLI-only unless ALLOW_DIGEST=1 is set.
 */
if (php_sapi_name() !== 'cli' && getenv('ALLOW_DIGEST') !== '1') {
    http_response_code(403);
    die("This script is CLI-only. Set ALLOW_DIGEST=1 to override.\n");
}

require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../backend/controllers/ELRPipelineController.php';
require_once __DIR__ . '/../backend/services/Mailer.php';
global $pdo;

$today = date('Y-m-d');

try {
    $tenants = $pdo->query(
        "SELECT `id`, `company_name`, `contact_email` FROM `tenants`
         WHERE `contact_email` IS NOT NULL AND `contact_email` <> ''"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die("Digest aborted: " . $e->getMessage() . "\n");
}

echo "ELR daily digest for " . count($tenants) . " tenant(s), date $today.\n";
foreach ($tenants as $t) {
    $_SESSION['tenant_id'] = $t['id'];
    try {
        $ctrl   = new ELRPipelineController($pdo);
        $report = $ctrl->dailyReportData($today, $today);
        $total  = (int)($report['summary']['total'] ?? 0);
        if ($total === 0) {
            echo "[{$t['id']}] nothing filed today — skipped.\n";
            continue;
        }
        $html = elr_digest_html($t['company_name'] ?: 'Your Company', $report);
        Mailer::send(
            $t['contact_email'],
            $t['company_name'] ?: 'ELR Admin',
            "ELR Daily Report — {$today} ({$total} filed)",
            $html
        );
        echo "[{$t['id']}] sent to {$t['contact_email']} ({$total} case(s)).\n";
    } catch (Throwable $e) {
        echo "[{$t['id']}] ERROR: " . $e->getMessage() . "\n";
    }
}
echo "Digest run finished.\n";

/** Render the digest email body from a dailyReportData() result. */
function elr_digest_html($company, array $report)
{
    $s = $report['summary'];
    $rows = '';
    foreach ($report['cards'] as $c) {
        $src = ($c['entered_via'] ?? '') === 'auto' ? 'Auto' : 'Manual';
        $rows .= '<tr>'
            . '<td style="padding:6px;border:1px solid #ddd;">' . htmlspecialchars((string)($c['full_name'] ?: $c['employee_id'])) . '</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">' . htmlspecialchars((string)($c['department'] ?? '')) . '</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">' . htmlspecialchars((string)($c['pipeline_name'] ?? '')) . '</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">' . htmlspecialchars((string)($c['stage_name'] ?? '')) . '</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">' . $src . '</td>'
            . '</tr>';
    }
    $byType = '';
    foreach (($s['by_pipeline'] ?? []) as $k => $v) {
        $byType .= htmlspecialchars((string)$k) . ': ' . (int)$v . ' &nbsp; ';
    }

    return '<div style="font-family:Arial,sans-serif;color:#222;">'
        . '<h2 style="margin-bottom:4px;">ELR Daily Report — ' . htmlspecialchars($company) . '</h2>'
        . '<p style="color:#555;">' . (int)$s['total'] . ' case(s) filed on ' . htmlspecialchars((string)$report['window']['to'])
        . ' — ' . (int)$s['auto'] . ' automatic, ' . (int)$s['manual'] . ' manual.</p>'
        . ($byType !== '' ? '<p><strong>By incident type:</strong> ' . $byType . '</p>' : '')
        . '<table style="border-collapse:collapse;width:100%;font-size:13px;">'
        . '<tr style="background:#f3f4f6;">'
        . '<th style="padding:6px;border:1px solid #ddd;text-align:left;">Employee</th>'
        . '<th style="padding:6px;border:1px solid #ddd;text-align:left;">Dept</th>'
        . '<th style="padding:6px;border:1px solid #ddd;text-align:left;">Incident Type</th>'
        . '<th style="padding:6px;border:1px solid #ddd;text-align:left;">Stage</th>'
        . '<th style="padding:6px;border:1px solid #ddd;text-align:left;">Source</th>'
        . '</tr>' . $rows . '</table>'
        . '<p style="color:#999;font-size:12px;margin-top:16px;">Automated daily digest from Respawn Logics — Employee &amp; Labor Relations.</p>'
        . '</div>';
}
