import { useState, useRef, useEffect } from "react";
import { Upload, Download, CheckCircle, AlertTriangle, FileSpreadsheet, ArrowRight, Settings, Users, Briefcase, Zap, Shield, Database, Gamepad2, User, Globe } from "lucide-react";

type ViewState = "setup_mode" | "upload" | "mapping" | "processing" | "admin_selection" | "results";
type SetupMode = "Solo" | "Quick" | "Standard" | "Enterprise";

const ROLE_DESCRIPTIONS: Record<string, string> = {
  "admin": "Full system access, configuration, and security controls.",
  "hr": "Access to personnel records, payroll, and organizational charts.",
  "recruiter": "Access to candidate pipelines and interview scheduling.",
  "employee": "Basic self-service portal access only."
};

export default function App() {
  const [currentView, setCurrentView] = useState<ViewState>("setup_mode");
  const [setupMode, setSetupMode] = useState<SetupMode>("Quick");
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [isDragging, setIsDragging] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  
  const [csvHeaders, setCsvHeaders] = useState<string[]>([]);
  const [csvRows, setCsvRows] = useState<string[][]>([]);
  
  // mapping state: Map<csvHeader, systemField>
  const [mapping, setMapping] = useState<Record<string, string>>({});
  
  const [importStats, setImportStats] = useState({
    batchId: 0,
    processed: 0,
    skipped: 0,
    warnings: [] as string[],
    suggestedAdmins: [] as any[],
    accounts: [] as any[]
  });

  const [selectedAdmins, setSelectedAdmins] = useState<Record<string, string>>({});

  const [toast, setToast] = useState<{message: string, type: 'error' | 'success'} | null>(null);
  const [csrfToken, setCsrfToken] = useState<string>("");
  const API_BASE = window.location.origin + (window.location.hostname === 'localhost' ? '/respawn-logics' : '');
  
  useEffect(() => {
    fetch(`${API_BASE}/get_csrf.php`, { credentials: "same-origin" })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setCsrfToken(data.csrf_token);
        }
      })
      .catch(console.error);
  }, []);

  const showToast = (message: string, type: 'error' | 'success' = 'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 5000);
  };

  const internalFields = [
    { value: "employee_id", label: "Employee ID (Required)" },
    { value: "first_name", label: "First Name (Required)" },
    { value: "last_name", label: "Last Name (Required)" },
    { value: "email", label: "Work Email (Required)" },
    { value: "manager_id", label: "Manager ID" },
    { value: "department", label: "Department" },
    { value: "job_title", label: "Job Title" },
    { value: "hire_date", label: "Hire Date" },
    { value: "system_role", label: "System Role (Admin/HR)" },
    { value: "organization_unit_1", label: "Org Unit 1 (e.g. Region)" },
    { value: "organization_unit_2", label: "Org Unit 2 (e.g. Division)" },
    { value: "organization_unit_3", label: "Org Unit 3 (e.g. Branch)" },
    { value: "organization_unit_4", label: "Org Unit 4" }
  ];

  const handleModeSelect = (mode: SetupMode) => {
    setSetupMode(mode);
      if (mode === "Solo") {
        window.location.href = `${API_BASE}/register.php`;
        return;
      }
    setCurrentView("upload");
  };

  const handleBrowseClick = () => fileInputRef.current?.click();

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      processFile(e.target.files[0]);
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => {
    setIsDragging(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      processFile(e.dataTransfer.files[0]);
    }
  };

  const processFile = (file: File) => {
    setFile(file);
    const reader = new FileReader();
    reader.onload = (e) => {
      const text = e.target?.result as string;
      const lines = text.split("\n").map(l => l.trim()).filter(Boolean);
      if (lines.length > 0) {
        const splitLine = (line: string) => {
          const result: string[] = [];
          let current = "";
          let inQuotes = false;
          for (let i = 0; i < line.length; i++) {
            const char = line[i];
            if (char === '"') inQuotes = !inQuotes;
            else if (char === ',' && !inQuotes) {
              result.push(current.replace(/^"|"$/g, '').replace(/""/g, '"').trim());
              current = "";
            } else current += char;
          }
          result.push(current.replace(/^"|"$/g, '').replace(/""/g, '"').trim());
          return result;
        };

        const headers = splitLine(lines[0]);
        setCsvHeaders(headers);
        
        const previewRows = lines.slice(1, 4).map(splitLine);
        setCsvRows(previewRows);

        // Auto-map obvious headers
        const initialMap: Record<string, string> = {};
        headers.forEach(h => {
          const norm = h.toLowerCase().replace(/[^a-z0-9]/g, '');
          if (norm.includes('employee') && norm.includes('id')) initialMap[h] = 'employee_id';
          else if (norm.includes('first')) initialMap[h] = 'first_name';
          else if (norm.includes('last')) initialMap[h] = 'last_name';
          else if (norm.includes('email')) initialMap[h] = 'email';
          else if (norm.includes('manager')) initialMap[h] = 'manager_id';
          else if (norm.includes('department')) initialMap[h] = 'department';
          else if (norm.includes('title')) initialMap[h] = 'job_title';
          else if (norm.includes('hire')) initialMap[h] = 'hire_date';
          else if (norm.includes('role')) initialMap[h] = 'system_role';
        });
        setMapping(initialMap);
        setCurrentView("mapping");
      }
    };
    reader.readAsText(file);
  };

  const handleMapChange = (header: string, field: string) => {
    setMapping(prev => {
      const newMap = { ...prev };
      if (!field) delete newMap[header];
      else newMap[header] = field;
      return newMap;
    });
  };

  const handleConfirmMapping = () => {
    const mappedFields = Object.values(mapping);
    if (!mappedFields.includes('employee_id') || !mappedFields.includes('first_name') || !mappedFields.includes('email')) {
      showToast("You must map Employee ID, First Name, and Email.");
      return;
    }
    submitImport();
  };

  const submitImport = () => {
    if (!file) return;
    setCurrentView("processing");
    const formData = new FormData();
    formData.append("file", file);
    formData.append("setup_mode", setupMode);
    formData.append("mapping", JSON.stringify(mapping));

    fetch(`${API_BASE}/api/index.php?route=onboarding&action=import`, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "X-CSRF-Token": csrfToken
      },
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        setImportStats({
          batchId: data.batch_id,
          processed: data.processed,
          skipped: data.skipped,
          warnings: data.warnings,
          suggestedAdmins: data.suggested_admins,
          accounts: data.accounts
        });
        
        if (data.suggested_admins && data.suggested_admins.length > 0) {
          setCurrentView("admin_selection");
        } else {
          setCurrentView("results");
        }
      } else {
        showToast("Import Failure: " + (data.error || "Unknown error."));
        setCurrentView("mapping");
      }
    })
    .catch(err => {
      console.error("Fetch failed for URL:", `${API_BASE}/api/index.php?route=onboarding&action=import`, "Error:", err);
      showToast("Import failed: " + err.message + " (Check console for URL)");
      setCurrentView("mapping");
    });
  };

  const handleAdminFinalize = async () => {
    try {
      const finalRoles = { ...selectedAdmins };
      importStats.suggestedAdmins.forEach((admin: any) => {
        if (!finalRoles[admin.employee_id]) {
          finalRoles[admin.employee_id] = "admin";
        }
      });

      // If no roles to update, just proceed
      if (Object.keys(finalRoles).length === 0) {
        setCurrentView("results");
        return;
      }

      const response = await fetch(`${API_BASE}/api/index.php?route=onboarding&action=update_roles`, {
        method: "POST",
        credentials: "same-origin",
        headers: { 
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfToken
        },
        body: JSON.stringify({ roles: finalRoles })
      });
      
      if (!response.ok) throw new Error("Failed to update roles.");
      setCurrentView("results");
    } catch (err) {
      console.error(err);
      showToast("Error updating roles to the database.");
    }
  };

  const downloadActivationCSV = () => {
    const csvContent = "Employee ID,Full Name,Email,Assigned Role,Activation Link\n" +
      importStats.accounts.map(a => `"${a.employee_id}","${a.full_name}","${a.email}","${a.role}","${a.activation_link}"`).join("\n");
    
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "activation_links.csv";
    link.click();
  };

  const handleDownloadTemplate = () => {
    let csvContent = "";
    
    if (setupMode === "Quick") {
      csvContent = 
        "employee_id,first_name,last_name,email\n" +
        "EMP-001,Jane,Doe,jane@company.com\n" +
        "EMP-002,John,Smith,john@company.com\n" +
        "EMP-003,Alice,Johnson,alice@company.com\n";
    } else if (setupMode === "Standard") {
      csvContent = 
        "employee_id,first_name,last_name,email,department,job_title,manager_id,hire_date\n" +
        "EMP-001,Jane,Doe,jane@company.com,Executive,Chief Executive Officer,,2024-01-15\n" +
        "EMP-002,John,Smith,john@company.com,Engineering,VP of Engineering,EMP-001,2024-02-10\n" +
        "EMP-003,Alice,Johnson,alice@company.com,Engineering,Software Engineer,EMP-002,2024-03-01\n";
    } else {
      csvContent = 
        "employee_id,first_name,last_name,email,job_title,manager_id,system_role,organization_unit_1,organization_unit_2,organization_unit_3\n" +
        "EMP-001,Jane,Doe,jane@company.com,CEO,,admin,North America,HQ,Executive Board\n" +
        "EMP-002,John,Smith,john@company.com,VP of Engineering,EMP-001,manager,North America,HQ,Engineering\n" +
        "EMP-003,Alice,Johnson,alice@company.com,Senior Developer,EMP-002,employee,Europe,London Branch,Engineering\n";
    }
    
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `template_${setupMode.toLowerCase()}.csv`;
    link.click();
  };

  return (
    <div className="h-full bg-[#0b0f1a] bg-[linear-gradient(to_right,rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:40px_40px] font-['Space_Grotesk'] selection:bg-[#00e07a]/30 overflow-hidden relative text-[#c8d0e0]">
      {/* Ambient Background Blobs */}
      <div className="ambient-blob blob-1"></div>
      <div className="ambient-blob blob-2"></div>
      <div className="ambient-blob blob-3"></div>

      {toast && (
        <div className={`fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg border ${toast.type === 'error' ? 'bg-red-500/10 border-red-500/50 text-red-400' : 'bg-emerald-500/10 border-emerald-500/50 text-emerald-400'} flex items-center gap-3 backdrop-blur-md`}>
          <AlertTriangle size={18} />
          <span className="font-medium text-sm">{toast.message}</span>
        </div>
      )}

      {/* HEADER */}
      <nav className="border-b border-white/[0.07] bg-[#0b0f1a]/90 backdrop-blur-[20px] fixed top-0 left-0 right-0 z-50 h-[62px] flex items-center justify-between px-6 md:px-12">
        <a href={`${API_BASE}/index.php`} className="flex items-center gap-[10px] no-underline font-['JetBrains_Mono'] text-[0.9375rem] font-bold text-white">
          <div className="w-[40px] h-[40px] rounded-[10px] bg-gradient-to-br from-[#00e07a] to-[#00b8ff] flex items-center justify-center shadow-[0_8px_20px_rgba(0,224,122,0.25)]">
            <i className="fa-solid fa-gamepad" style={{fontSize: "20px", color: "#000"}}></i>
          </div>
          <span className="hidden sm:flex items-center gap-1">
            Respawn Logics
            <span className="text-[9px] text-[#00e07a] border border-[#00e07a]/20 bg-[#00e07a]/10 px-1 py-0.5 rounded ml-1 tracking-[0.1em] font-bold">v2.0</span>
          </span>
        </a>

        <div className="hidden md:flex items-center gap-[30px] font-['Space_Grotesk'] text-[0.9rem] font-medium text-[#8b95a8]">
          <a href={`${API_BASE}/index.php#overview`} className="hover:text-white transition-colors no-underline">Platform</a>
          <a href={`${API_BASE}/deep_dive.php`} className="hover:text-white transition-colors no-underline">Deep Dive</a>
          <a href={`${API_BASE}/design.php`} className="hover:text-white transition-colors no-underline">Design</a>
          <a href={`${API_BASE}/index.php#why`} className="hover:text-white transition-colors no-underline">Why Us</a>
          <a href={`${API_BASE}/index.php#story`} className="hover:text-white transition-colors no-underline">The Story</a>
          <a href={`${API_BASE}/index.php#beta`} className="hover:text-white transition-colors no-underline">Beta</a>
          
          <a href={`${API_BASE}/login.php`} className="font-['JetBrains_Mono'] text-[0.8rem] font-bold tracking-[0.04em] text-black bg-[#00e07a] px-[20px] py-[9px] rounded-[5px] no-underline transition-all hover:bg-white hover:-translate-y-[1px] hover:shadow-[0_4px_16px_rgba(0,224,122,0.3)] ml-2">
            [ LOGIN ]
          </a>
        </div>
      </nav>

      <main className="max-w-5xl mx-auto px-6 py-12 mt-16">
        {currentView === "setup_mode" && (
          <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
            <div className="text-center max-w-2xl mx-auto mb-16 mt-8">
              <div className="inline-block px-3 py-1 mb-4 rounded-full border border-white/10 bg-white/5 text-[#8b95a8] text-sm font-['JetBrains_Mono']">// INIT_SEQUENCE</div>
              <h1 className="text-5xl font-bold text-white mb-6 tracking-tight">Select Setup Mode<span className="text-[#00e07a] animate-pulse">_</span></h1>
              <p className="text-[#8b95a8] text-lg font-['Space_Grotesk']">Choose the onboarding path that best fits your organizational scale.</p>
            </div>
            
            <div className="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
              {[
                { title: "Co-op Mode", desc: "1-100 employees. Minimal mapping, automatic suggestions.", icon: Users, mode: "Quick", color: "text-[#c8d0e0]", bg: "bg-[#0f1422]", border: "hover:border-[#00e07a]/50" },
                { title: "Multiplayer Guild", desc: "100-500 employees. Department structures and multiple admins.", icon: Shield, mode: "Standard", color: "text-[#c8d0e0]", bg: "bg-[#0f1422]", border: "hover:border-[#4f8ef7]/50" },
                { title: "MMO Server", desc: "500+ employees. Advanced org units and exact RBAC mapping.", icon: Globe, mode: "Enterprise", color: "text-[#00e07a]", bg: "bg-[#00e07a]/10 border border-[#00e07a]/30 shadow-[0_0_30px_rgba(0,224,122,0.15)]", border: "border-[#00e07a]" }
              ].map((s, i) => (
                <div key={i} onClick={() => handleModeSelect(s.mode as SetupMode)} className={`group bg-[#0f1422] border border-white/[0.05] ${s.border} rounded-xl p-8 cursor-pointer transition-all hover:-translate-y-2 hover:shadow-[0_8px_30px_rgba(0,0,0,0.5)] flex flex-col items-center text-center relative overflow-hidden`}>
                  
                  {/* Decorative corner brackets */}
                  <div className="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>
                  <div className="absolute top-0 right-0 w-4 h-4 border-t-2 border-r-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>
                  <div className="absolute bottom-0 left-0 w-4 h-4 border-b-2 border-l-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>
                  <div className="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>

                  <div className={`w-14 h-14 rounded-lg ${s.bg} flex items-center justify-center mb-6 transition-all group-hover:scale-110`}>
                    <s.icon className={`w-7 h-7 ${s.color}`} />
                  </div>
                  <h3 className="text-xl font-bold text-white mb-3 font-['JetBrains_Mono']">{s.title}</h3>
                  <p className="text-[#8b95a8] text-sm leading-relaxed">{s.desc}</p>
                </div>
              ))}
            </div>

            <div className="relative flex items-center py-8 max-w-4xl mx-auto">
              <div className="flex-grow border-t border-white/10"></div>
              <span className="flex-shrink-0 mx-6 text-[#8b95a8] text-sm font-['JetBrains_Mono'] tracking-widest uppercase">// Starting solo? I've got you covered</span>
              <div className="flex-grow border-t border-white/10"></div>
            </div>

            <div className="max-w-sm mx-auto">
              <div onClick={() => handleModeSelect("Solo")} className={`group bg-[#0f1422] border border-white/[0.05] hover:border-[#00e07a]/50 rounded-xl p-8 cursor-pointer transition-all hover:-translate-y-2 hover:shadow-[0_8px_30px_rgba(0,0,0,0.5)] flex flex-col items-center text-center relative overflow-hidden`}>
                {/* Decorative corner brackets */}
                <div className="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>
                <div className="absolute top-0 right-0 w-4 h-4 border-t-2 border-r-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>
                <div className="absolute bottom-0 left-0 w-4 h-4 border-b-2 border-l-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>
                <div className="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-white/10 group-hover:border-[#00e07a]/50 transition-colors m-2"></div>

                <div className={`w-14 h-14 rounded-lg bg-[#0f1422] flex items-center justify-center mb-6 transition-all group-hover:scale-110`}>
                  <User className="w-7 h-7 text-[#c8d0e0] group-hover:text-[#00e07a] transition-colors" />
                </div>
                <h3 className="text-xl font-bold text-white mb-3 font-['JetBrains_Mono']">Single Player</h3>
                <p className="text-[#8b95a8] text-sm leading-relaxed">1 employee. Direct dashboard access, no mapping required.</p>
              </div>
            </div>
          </div>
        )}

        {currentView === "upload" && (
          <div className="max-w-2xl mx-auto text-center space-y-8 animate-in fade-in zoom-in-95">
            <div className="text-center max-w-2xl mx-auto mb-16 mt-8">
              <div className="inline-block px-3 py-1 mb-4 rounded-full border border-white/10 bg-white/5 text-[#8b95a8] text-sm font-['JetBrains_Mono']">// AWAITING_PAYLOAD</div>
              <h2 className="text-4xl font-bold text-white mb-3">Upload Data Source<span className="text-[#00e07a] animate-pulse">_</span></h2>
              <p className="text-[#8b95a8] font-['Space_Grotesk']">Provide the CSV payload. Schema mapping will follow.</p>
            </div>
            
            <div 
              onDragOver={handleDragOver} onDragLeave={() => setIsDragging(false)} onDrop={handleDrop}
              onClick={handleBrowseClick}
              className={`border border-dashed rounded-xl p-16 cursor-pointer transition-all flex flex-col items-center justify-center gap-6 relative overflow-hidden group
                ${isDragging ? 'border-[#00e07a] bg-[#00e07a]/5' : 'border-white/[0.1] hover:border-[#00e07a]/30 bg-[#0f1422] hover:bg-[#0f1422]/80'}`}
            >
              <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(0,224,122,0.05)_0%,transparent_70%)] opacity-0 group-hover:opacity-100 transition-opacity"></div>
              
              <input type="file" accept=".csv" ref={fileInputRef} onChange={handleFileChange} className="hidden" />
              <div className="w-16 h-16 rounded-xl bg-[#0b0f1a] border border-white/5 flex items-center justify-center relative z-10 group-hover:border-[#00e07a]/30 transition-colors">
                <Upload className="w-8 h-8 text-[#8b95a8] group-hover:text-[#00e07a] transition-colors" />
              </div>
              <div className="relative z-10 text-center">
                <p className="text-white font-medium text-lg font-['JetBrains_Mono']">TRANSMIT PAYLOAD</p>
                <p className="text-[#8b95a8] text-sm mt-2">Drag & drop .csv or click to browse</p>
              </div>
            </div>

            <div className="flex justify-between items-center mt-8">
              <button onClick={() => setCurrentView("setup_mode")} className="px-6 py-2.5 rounded-sm text-[#c8d0e0] font-['JetBrains_Mono'] hover:text-white transition-colors flex items-center gap-2">
                <ArrowRight className="w-4 h-4 rotate-180" /> [ ABORT ]
              </button>
              <button onClick={handleDownloadTemplate} className="flex items-center gap-2 px-6 py-2.5 text-xs rounded-sm text-[#00e07a] font-['JetBrains_Mono'] font-bold border border-[#00e07a] bg-[#00e07a]/10 hover:bg-[#00e07a]/20 transition-all uppercase tracking-wider">
                <Download className="w-4 h-4" /> [ TEMPLATE.CSV ]
              </button>
            </div>
          </div>
        )}

        {currentView === "mapping" && (
          <div className="animate-in fade-in">
            <div className="text-center max-w-2xl mx-auto mb-16 mt-8">
              <div className="inline-block px-3 py-1 mb-4 rounded-full border border-white/10 bg-white/5 text-[#8b95a8] text-sm font-['JetBrains_Mono']">// SCHEMA_BINDING</div>
              <h2 className="text-4xl font-bold text-white mb-3">Map Data Structure<span className="text-[#00e07a] animate-pulse">_</span></h2>
              <p className="text-[#8b95a8] font-['Space_Grotesk']">Bind your CSV columns to the core system variables.</p>
            </div>
            
            <div className="bg-[#0f1422] border border-[#00e07a]/30 rounded-lg overflow-hidden mb-8 shadow-[0_0_20px_rgba(0,224,122,0.1)] relative">
              {/* Terminal scanline effect */}
              <div className="absolute inset-0 pointer-events-none bg-[linear-gradient(transparent_50%,rgba(0,0,0,0.1)_50%)] bg-[length:100%_4px] opacity-20"></div>
              
              <table className="w-full text-left text-sm relative z-10">
                <thead className="bg-[#0b0f1a] border-b border-[#00e07a]/30">
                  <tr>
                    <th className="p-4 text-[#00e07a] font-['JetBrains_Mono'] uppercase tracking-wider text-xs">Source Key</th>
                    <th className="p-4 text-[#00e07a] font-['JetBrains_Mono'] uppercase tracking-wider text-xs">System Target</th>
                    <th className="p-4 text-[#00e07a] font-['JetBrains_Mono'] uppercase tracking-wider text-xs">Sample Value</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/[0.05]">
                  {csvHeaders.map((header, i) => (
                    <tr key={i} className="hover:bg-white/[0.02] transition-colors group">
                      <td className="p-4 text-white font-['JetBrains_Mono']">{header}</td>
                      <td className="p-4">
                        <select 
                          value={mapping[header] || ""}
                          onChange={(e) => handleMapChange(header, e.target.value)}
                          className="bg-[#0b0f1a] border border-white/[0.1] text-white font-['JetBrains_Mono'] text-sm rounded-sm px-3 py-2 outline-none focus:border-[#00e07a] w-64 transition-colors"
                        >
                          <option value="">-- Ignore Column --</option>
                          {internalFields.map(f => (
                            <option key={f.value} value={f.value}>{f.label}</option>
                          ))}
                        </select>
                      </td>
                      <td className="p-4 text-gray-500 truncate max-w-xs">
                        {csvRows[0] && csvRows[0][i]}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="flex justify-end gap-6 items-center">
              <button onClick={() => setCurrentView("upload")} className="text-[#c8d0e0] font-['JetBrains_Mono'] hover:text-white flex items-center gap-2">
                <ArrowRight className="w-4 h-4 rotate-180" /> [ BACK ]
              </button>
              <button onClick={handleConfirmMapping} className="bg-[#00e07a] text-black px-8 py-3 rounded-sm font-['JetBrains_Mono'] font-bold hover:bg-[#00c96a] transition-all shadow-[0_0_20px_rgba(0,224,122,0.4)] flex items-center gap-2">
                [ INITIALIZE_IMPORT ]
              </button>
            </div>
          </div>
        )}

        {currentView === "processing" && (
          <div className="flex flex-col items-center justify-center py-20 animate-in zoom-in">
            <div className="relative w-24 h-24 mb-8">
              <div className="absolute inset-0 border-4 border-white/5 rounded-full"></div>
              <div className="absolute inset-0 border-4 border-t-[#00e07a] border-r-[#00e07a] rounded-full animate-spin shadow-[0_0_15px_rgba(0,224,122,0.5)]"></div>
              <div className="absolute inset-0 flex items-center justify-center text-[#00e07a] font-['JetBrains_Mono'] text-xs font-bold">SYS_OP</div>
            </div>
            <h2 className="text-3xl font-bold text-white mb-2">Compiling Hierarchy<span className="text-[#00e07a] animate-pulse">_</span></h2>
            <p className="text-[#8b95a8] font-['Space_Grotesk'] text-center max-w-md">Validating organizational matrices, resolving loops, and generating security tokens.</p>
          </div>
        )}

        {currentView === "admin_selection" && (
          <div className="animate-in fade-in max-w-3xl mx-auto">
            <div className="text-center mb-12">
              <div className="inline-block px-3 py-1 mb-4 rounded-full border border-white/10 bg-white/5 text-[#8b95a8] text-sm font-['JetBrains_Mono']">// ACCESS_CONTROL</div>
              <h2 className="text-4xl font-bold text-white mb-3">Provision Administrators<span className="text-[#00e07a] animate-pulse">_</span></h2>
              <p className="text-[#8b95a8] font-['Space_Grotesk']">We identified the following high-level nodes. Confirm security clearances.</p>
            </div>
            
            <div className="space-y-4 mb-10">
              {importStats.suggestedAdmins.map((admin: any, i: number) => (
                <div key={i} className="p-6 rounded-lg bg-[#0f1422] border border-white/[0.05] hover:border-[#00e07a]/40 transition-all shadow-[0_4px_20px_rgba(0,0,0,0.2)] flex items-start justify-between group">
                  <div>
                    <h4 className="text-lg font-bold text-white mb-1 font-['JetBrains_Mono']">{admin.full_name}</h4>
                    <p className="text-sm text-[#8b95a8]">{admin.job_title || "No Title"} <span className="text-white/20 mx-2">|</span> {admin.email}</p>
                  </div>
                  <div className="flex flex-col items-end">
                    <select 
                      value={selectedAdmins[admin.employee_id] || "admin"}
                      onChange={(e) => setSelectedAdmins({...selectedAdmins, [admin.employee_id]: e.target.value})}
                      className="bg-[#0b0f1a] border border-white/[0.1] text-[#00e07a] font-['JetBrains_Mono'] rounded-sm px-4 py-2 w-[220px] outline-none focus:border-[#00e07a] transition-colors"
                    >
                      <option value="admin">Platform Admin</option>
                      <option value="hr">HR Manager</option>
                      <option value="recruiter">Recruiter</option>
                      <option value="employee">Standard Employee</option>
                    </select>
                    <p className="text-xs text-[#5e6a82] mt-2 max-w-[250px] text-right font-['Space_Grotesk'] group-hover:text-[#8b95a8] transition-colors">
                      {ROLE_DESCRIPTIONS[selectedAdmins[admin.employee_id] || "admin"]}
                    </p>
                  </div>
                </div>
              ))}
            </div>

            <div className="flex justify-end border-t border-white/5 pt-8">
              <button onClick={handleAdminFinalize} className="bg-[#00e07a] hover:bg-[#00c96a] text-black px-8 py-3 rounded-sm font-['JetBrains_Mono'] font-bold transition-all shadow-[0_0_20px_rgba(0,224,122,0.4)]">
                [ FINALIZE_SETUP ]
              </button>
            </div>
          </div>
        )}

        {currentView === "results" && (
          <div className="max-w-3xl mx-auto animate-in fade-in zoom-in-95">
            <div className="bg-[#0f1422] border border-[#00e07a]/30 shadow-[0_0_30px_rgba(0,224,122,0.1)] rounded-lg p-10 text-center mb-8 relative overflow-hidden">
              <div className="absolute inset-0 pointer-events-none bg-[linear-gradient(transparent_50%,rgba(0,224,122,0.03)_50%)] bg-[length:100%_4px]"></div>
              
              <div className="w-20 h-20 bg-[#00e07a]/10 border border-[#00e07a]/40 rounded-full flex items-center justify-center mx-auto mb-6 relative z-10">
                <CheckCircle className="w-10 h-10 text-[#00e07a]" />
              </div>
              <h2 className="text-4xl font-bold text-white mb-3 font-['Space_Grotesk']">System Initialized</h2>
              <p className="text-[#8b95a8]">Corporate matrix has been successfully compiled.</p>
              
              <div className="grid grid-cols-2 gap-6 mt-10 relative z-10">
                <div className="bg-[#0b0f1a] rounded-sm p-6 border border-white/5 shadow-inner">
                  <div className="text-4xl font-bold text-[#00e07a] font-['JetBrains_Mono'] mb-2">{importStats.processed}</div>
                  <div className="text-xs text-[#5e6a82] font-['JetBrains_Mono'] uppercase tracking-wider">Nodes Connected</div>
                </div>
                <div className="bg-[#0b0f1a] rounded-sm p-6 border border-white/5 shadow-inner">
                  <div className="text-4xl font-bold text-[#ff4d6a] font-['JetBrains_Mono'] mb-2">{importStats.skipped}</div>
                  <div className="text-xs text-[#5e6a82] font-['JetBrains_Mono'] uppercase tracking-wider">Anomalies Dropped</div>
                </div>
              </div>
            </div>

            <div className="bg-transparent border border-white/10 rounded-lg p-10 text-center">
              <h3 className="text-2xl font-bold text-white mb-3">Distribute Access Keys</h3>
              <p className="text-[#8b95a8] mb-8 text-sm leading-relaxed max-w-lg mx-auto">
                Secure activation keys have been generated. Distribute these via internal channels so personnel can instantiate their dashboards.
              </p>
              <div className="flex flex-col sm:flex-row gap-6 justify-center">
                <button onClick={downloadActivationCSV} className="flex items-center justify-center gap-2 bg-[#00e07a] hover:bg-[#00c96a] text-black px-8 py-3 rounded-sm font-['JetBrains_Mono'] font-bold transition-all shadow-[0_0_20px_rgba(0,224,122,0.3)]">
                  <Download className="w-4 h-4" /> [ GET_KEYS.CSV ]
                </button>
                <button onClick={() => window.location.href = `${API_BASE}/`} className="flex items-center justify-center gap-2 bg-[#0f1422] hover:bg-white/10 border border-white/10 text-white px-8 py-3 rounded-sm font-['JetBrains_Mono'] font-bold transition-all">
                  [ ENTER_SYSTEM ] <ArrowRight className="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        )}
      </main>

    </div>
  );
}
