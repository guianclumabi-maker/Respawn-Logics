<?php
require_once __DIR__ . '/../bootstrap/app.php';

// Gate the page (same logic as the controller)
if (!hasPermission('settings.manage') && !in_array('Super_Admin', $_SESSION['roles'] ?? [])) {
    http_response_code(403);
    echo "<h1>403 Access Denied</h1><p>You must be a Super Admin or have settings.manage permission to view this page.</p>";
    exit;
}

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/app-header.php';
?>

<div class="flex min-h-screen bg-gray-900 text-white">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-1 p-8 ml-64">
        <h1 class="text-3xl font-bold mb-2">System Health & Diagnostics</h1>
        <p class="text-gray-400 mb-8">Verifying M2 prerequisites and environment configuration.</p>

        <div id="health-results" class="bg-gray-800 rounded-lg shadow border border-gray-700 overflow-hidden hidden">
            <table class="min-w-full divide-y divide-gray-700 text-left text-sm">
                <thead class="bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 font-medium text-gray-300">Diagnostic Check</th>
                        <th class="px-6 py-3 font-medium text-gray-300 w-32">Status</th>
                        <th class="px-6 py-3 font-medium text-gray-300">Detail</th>
                    </tr>
                </thead>
                <tbody id="health-tbody" class="divide-y divide-gray-700">
                    <!-- Results injected here -->
                </tbody>
            </table>
        </div>

        <div id="loading" class="text-gray-400 flex items-center gap-2">
            <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            Running system diagnostics...
        </div>
        <div id="error" class="hidden text-red-500 font-bold mt-4 p-4 bg-red-500/10 rounded-lg border border-red-500/50"></div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res = await fetch('/api/index.php?route=health&action=check');
        if (!res.ok) {
            throw new Error(`HTTP ${res.status} - Access Denied or Server Error`);
        }
        
        const data = await res.json();
        if (data.success) {
            document.getElementById('loading').classList.add('hidden');
            document.getElementById('health-results').classList.remove('hidden');
            
            const tbody = document.getElementById('health-tbody');
            data.checks.forEach(check => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-700/50 transition-colors';
                
                const statusHtml = check.status === 'pass' 
                    ? `<span class="inline-flex items-center text-green-400 bg-green-400/10 px-2 py-1 rounded-full text-xs font-bold border border-green-500/20">PASS</span>`
                    : `<span class="inline-flex items-center text-red-400 bg-red-400/10 px-2 py-1 rounded-full text-xs font-bold border border-red-500/20">FAIL</span>`;

                tr.innerHTML = `
                    <td class="px-6 py-4 font-medium text-white">${check.name}</td>
                    <td class="px-6 py-4">${statusHtml}</td>
                    <td class="px-6 py-4 text-gray-400 font-mono text-xs">${check.detail}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            throw new Error(data.error || 'Failed to fetch checks');
        }
    } catch (err) {
        document.getElementById('loading').classList.add('hidden');
        const errDiv = document.getElementById('error');
        errDiv.classList.remove('hidden');
        errDiv.textContent = err.message;
    }
});
</script>
