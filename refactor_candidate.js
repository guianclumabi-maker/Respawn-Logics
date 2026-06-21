const fs = require('fs');

function processCandidateProfile() {
  let file = 'frontend/src/app/components/CandidateProfile.tsx';
  let code = fs.readFileSync(file, 'utf8');

  // 1. Add AlertTriangle to lucide-react imports
  if (!code.includes('AlertTriangle')) {
    code = code.replace(/import \{\s*ArrowLeft,/, 'import {\n  AlertTriangle,\n  Check,\n  ArrowLeft,');
  }

  // 2. Fix TS errors in CandidateData type
  const targetTypeStr = 'activity_log: ActivityItem[];\n};';
  const newTypeStr = `activity_log: ActivityItem[];\n  consent_given?: boolean | number;\n  consent_at?: string;\n  data_retention_until?: string;\n  is_anonymized?: boolean | number;\n};`;
  code = code.replace(targetTypeStr, newTypeStr);

  // 3. Inject toast state
  const stateInjection = `  const [toast, setToast] = useState<{msg: string, type: "success"|"error"} | null>(null);
  const showToast = (msg: string, type: "success"|"error" = "success") => { setToast({ msg, type }); setTimeout(() => setToast(null), 3500); };\n\n`;
  code = code.replace(/const \[candidate, setCandidate\] = useState<CandidateData \| null>\(null\);/, stateInjection + '  const [candidate, setCandidate] = useState<CandidateData | null>(null);');

  // 4. Replace alerts
  code = code.replace(/alert\((.*?)\)/g, 'showToast($1, "error")');

  // 5. Inject toast UI before the closing div of CandidateProfile
  const toastUI = `
      {toast && (
        <div className={\`fixed bottom-6 right-6 z-50 flex items-center gap-2 px-4 py-3 rounded-xl shadow-2xl text-xs font-mono font-bold border \${toast.type === "error" ? "border-red-500/20 text-red-400 bg-background" : "border-[#00e07a]/20 text-primary bg-background"}\`}>
          {toast.type === "error" ? <AlertTriangle size={14} /> : <Check size={14} />}
          {toast.msg}
        </div>
      )}
    </div>
  );
}`;
  // Wait, let's just do a string replacement on the exact end of CandidateProfile
  const endTarget = `        </div>
      </div>
    </div>
  );
}`;
  
  const endReplace = `        </div>
      </div>
${toastUI}`;

  // Replace \r\n with \n
  code = code.replace(/\r\n/g, '\n');
  if (code.includes(endTarget)) {
    code = code.replace(endTarget, endReplace);
  } else {
    console.log("Could not find CandidateProfile end block");
  }

  fs.writeFileSync(file, code);
  console.log("CandidateProfile updated.");
}

processCandidateProfile();
