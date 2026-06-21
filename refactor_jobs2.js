const fs = require('fs');

function processJobsPage() {
  let file = 'frontend/src/app/components/JobsPage.tsx';
  let code = fs.readFileSync(file, 'utf8');

  // Add AlertTriangle, Check to lucide-react imports if not there
  if (!code.includes('AlertTriangle')) {
    code = code.replace(/import \{\s*Briefcase,/, 'import {\n  AlertTriangle,\n  Check,\n  Briefcase,');
  }

  // Inject toast UI before final closing div of JobsPage component
  const toastUI = `
      {toast && (
        <div className={\`fixed bottom-6 right-6 z-50 flex items-center gap-2 px-4 py-3 rounded-xl shadow-2xl text-xs font-mono font-bold border \${toast.type === "error" ? "border-red-500/20 text-red-400 bg-background" : "border-[#00e07a]/20 text-primary bg-background"}\`}>
          {toast.type === "error" ? <AlertTriangle size={14} /> : <Check size={14} />}
          {toast.msg}
        </div>
      )}
    </div>
  );
}

// ─── Job Card`;
  
  // Notice that I'm replacing the end of JobsPage
  code = code.replace(/    <\/div>\s*\);\s*}\s*\/\/\s*─── Job Card/g, toastUI);

  fs.writeFileSync(file, code);
  console.log("JobsPage updated.");
}

processJobsPage();
