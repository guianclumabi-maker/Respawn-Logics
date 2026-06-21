const fs = require('fs');

function processJobsPage() {
  let file = 'frontend/src/app/components/JobsPage.tsx';
  let code = fs.readFileSync(file, 'utf8');

  // Add AlertTriangle, Check to lucide-react imports if not there
  if (!code.includes('AlertTriangle')) {
    code = code.replace('import {\n  Briefcase,', 'import {\n  AlertTriangle,\n  Check,\n  Briefcase,');
  }

  // Inject state into JobsPage component
  const stateInjection = `  const [toast, setToast] = useState<{msg: string, type: "success"|"error"} | null>(null);
  const showToast = (msg: string, type: "success"|"error" = "success") => { setToast({ msg, type }); setTimeout(() => setToast(null), 3500); };\n\n`;
  code = code.replace(/const \[jobs, setJobs\] = useState<Job\[\]>\(\[\]\);/, stateInjection + '  const [jobs, setJobs] = useState<Job[]>([]);');

  // Replace specific alert blocks inside JobsPage
  // 1. alert("API Error: " + (data.error || JSON.stringify(data)));
  code = code.replace(/alert\("API Error: " \+ \(data\.error \|\| JSON\.stringify\(data\)\)\);/, `console.error("API Error: " + (data.error || JSON.stringify(data)));\n            showToast("Failed to create job. Please try again.", "error");`);

  // 2. alert("Server Response Error: " + text.substring(0, 100));
  code = code.replace(/alert\("Server Response Error: " \+ text\.substring\(0, 100\)\);/, `console.error("Server Response Error: " + text.substring(0, 100));\n          showToast("Failed to create job. Please try again.", "error");`);

  // 3. alert("Fetch Error: " + err);
  code = code.replace(/alert\("Fetch Error: " \+ err\);/, `console.error("Fetch Error: " + err);\n        showToast("Failed to create job. Please try again.", "error");`);

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
  code = code.replace(/    <\/div>\n  }\n  \n  \/\/ ─── Job Card/, toastUI);

  fs.writeFileSync(file, code);
  console.log("JobsPage updated again.");
}

processJobsPage();
