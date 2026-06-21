const fs = require('fs');
let code = fs.readFileSync('frontend/src/app/components/JobsPage.tsx', 'utf8');

const target = `            onSubmit={handleAddCandidate}
          />
        )}
      </div>
    );
  }`;

const toastUI = `            onSubmit={handleAddCandidate}
          />
        )}

        {toast && (
          <div className={\`fixed bottom-6 right-6 z-50 flex items-center gap-2 px-4 py-3 rounded-xl shadow-2xl text-xs font-mono font-bold border \${toast.type === "error" ? "border-red-500/20 text-red-400 bg-background" : "border-[#00e07a]/20 text-primary bg-background"}\`}>
            {toast.type === "error" ? <AlertTriangle size={14} /> : <Check size={14} />}
            {toast.msg}
          </div>
        )}
      </div>
    );
  }`;

// Remove \r to ensure matching works properly
code = code.replace(/\r\n/g, '\n');

if (code.includes(target)) {
  code = code.replace(target, toastUI);
  fs.writeFileSync('frontend/src/app/components/JobsPage.tsx', code);
  console.log("toastUI injected successfully into JobsPage.");
} else {
  console.log("Could not find the target string in JobsPage.");
}
