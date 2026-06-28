const fs = require('fs');

const files = [
  "frontend/src/app/attendance/AttendanceDashboard.tsx",
  "frontend/src/app/attendance/ManagerApprovals.tsx",
  "frontend/src/app/components/CandidateProfile.tsx",
  "frontend/src/app/components/InterviewsPage.tsx",
  "frontend/src/app/components/JobsPage.tsx",
  "frontend/src/app/components/PipelineBoard.tsx",
  "frontend/src/app/pages/ExpensesAdmin.tsx",
  "frontend/src/app/pages/Scheduling.tsx",
  "frontend/src/app/pages/TenantSettings.tsx",
  "frontend/src/app/pages/dashboard-app/app/App.tsx",
  "frontend/src/app/pages/dashboard-app/app/components/tickets/TicketDetail.tsx",
  "frontend/src/app/pages/employee-relations/app/components/GamifiedThemeToggle.tsx",
  "frontend/src/app/pages/service-desk/app/components/GamifiedThemeToggle.tsx",
  "frontend/src/app/components/GamifiedThemeToggle.tsx"
];

for (const file of files) {
  if (!fs.existsSync(file)) continue;
  let code = fs.readFileSync(file, 'utf8');
  let original = code;
  
  if (code.includes('__CSRF_TOKEN__') && !code.includes('apiFetch')) {
    let ups = file.split('frontend/src/app/')[1].split('/').length - 1;
    let importPath = (ups === 0 ? './' : '../'.repeat(ups)) + 'lib/apiClient';
    
    // insert import
    if (code.includes('import React')) {
        code = code.replace('import React', `import { apiFetch } from "${importPath}";\nimport React`);
    } else if (code.includes('import {')) {
        code = code.replace('import {', `import { apiFetch } from "${importPath}";\nimport {`);
    } else {
        code = `import { apiFetch } from "${importPath}";\n` + code;
    }
  }

  // Remove variable declarations
  code = code.replace(/const\s+(?:csrfToken|token)\s*=\s*\(window\s+as\s+any\)\.__CSRF_TOKEN__\s*\|\|\s*['"]['"];?\n?/g, '');
  code = code.replace(/const\s+(?:csrfToken|token)\s*=\s*window\.__CSRF_TOKEN__\s*\|\|\s*['"]['"];?\n?/g, '');

  // Strip headers X-CSRF-Token lines
  code = code.replace(/[ \t]*['"]X-CSRF-Token['"]:\s*\(window\s+as\s+any\)\.__CSRF_TOKEN__\s*\|\|\s*['"]['"],?\n?/g, '');
  code = code.replace(/[ \t]*['"]X-CSRF-Token['"]:\s*window\.__CSRF_TOKEN__\s*\|\|\s*['"]['"],?\n?/g, '');
  code = code.replace(/[ \t]*['"]X-CSRF-Token['"]:\s*(?:token|csrfToken),?\n?/g, '');
  code = code.replace(/[ \t]*csrf_token:\s*\(window\s+as\s+any\)\.__CSRF_TOKEN__\s*\|\|\s*['"]['"],?\n?/g, '');

  // Strip empty headers
  code = code.replace(/[ \t]*headers:\s*\{\s*['"]?Content-Type['"]?:\s*['"]application\/json['"]\s*\},?\n?/g, '');
  code = code.replace(/[ \t]*headers:\s*\{\s*\},?\n?/g, '');
  
  // Strip credentials
  code = code.replace(/[ \t]*credentials:\s*['"]include['"],?\n?/g, '');

  // Rename fetch to apiFetch for ANY line containing `method: "POST"` or similar mutations.
  // We can do this safely by splitting by fetch( and checking if it contains method: "POST"
  // Wait, if we just let apiFetch handle everything it's fine. But we must not break URL.
  // If we just do: `fetch(`${API_BASE}/something`` -> `apiFetch(`/something``
  // We can do standard string replaces:
  code = code.split('fetch(`${API_BASE}').join('apiFetch(`');
  code = code.split('fetch(`${API}').join('apiFetch(`${API.replace(API_BASE, "")}');
  code = code.split('fetch(API,').join('apiFetch(API.replace(API_BASE, ""),');
  code = code.split('fetch("/api/index.php').join('apiFetch("/api/index.php');

  // Any remaining generic fetches (like `fetch(` ) could break GET if not apiFetch, but apiFetch handles GETs fine too!
  // It handles credentials: "include" automatically.
  // Wait, does apiFetch append API_BASE to EVERYTHING? Yes. So if there's any `fetch(` that doesn't use API_BASE, it should stay fetch.
  // But by specifically replacing the prefixes above, we ONLY change fetches targeting API_BASE.
  
  if (original !== code) {
    fs.writeFileSync(file, code);
    console.log('Updated', file);
  }
}
