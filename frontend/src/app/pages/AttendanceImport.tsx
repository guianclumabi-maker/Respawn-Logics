import { useState, useRef } from "react";
import { ThemeProvider } from "next-themes";
import { useAuth } from "../context/AuthContext";
import { 
  Upload, 
  Download, 
  CheckCircle, 
  AlertCircle, 
  Loader2, 
  FileText, 
  HelpCircle,
  Clock
} from "lucide-react";

interface UploadResponse {
  success: boolean;
  processed?: number;
  skipped?: number;
  warnings?: string[];
  error?: string;
}

export function AttendanceImportContent() {
  const { hasPermission } = useAuth();
  const canManage = hasPermission("attendance.manage");

  const [file, setFile] = useState<File | null>(null);
  const [dragActive, setDragActive] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [response, setResponse] = useState<UploadResponse | null>(null);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleDrag = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === "dragenter" || e.type === "dragover") {
      setDragActive(true);
    } else if (e.type === "dragleave") {
      setDragActive(false);
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      const droppedFile = e.dataTransfer.files[0];
      if (droppedFile.name.endsWith(".csv")) {
        setFile(droppedFile);
      } else {
        setErrorMsg("Invalid file format. Please drop a CSV file.");
      }
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      setFile(e.target.files[0]);
      setErrorMsg(null);
    }
  };

  const downloadTemplate = () => {
    const headers = ["employee_id", "time_in", "time_out"];
    const rows = [
      ["EMP-001", "2026-07-01 08:00", "2026-07-01 17:00"],
      ["EMP-002", "2026-07-01 08:30", "2026-07-01 17:30"],
      ["test@company.com", "2026-07-01 09:00", "2026-07-01 18:00"]
    ];
    const csvContent = [headers.join(","), ...rows.map(e => e.join(","))].join("\n");
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", "attendance_import_template.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleUploadSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!file) return;

    setSubmitting(true);
    setErrorMsg(null);
    setResponse(null);

    const formData = new FormData();
    formData.append("file", file);

    const isLocal = window.location.hostname === "localhost";
    const basePath = isLocal ? "/respawn-logics" : "";
    const url = `${window.location.origin}${basePath}/api/index.php?route=attendance&action=import_punches`;

    try {
      const res = await fetch(url, {
        method: "POST",
        credentials: "include",
        headers: {
          "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
        },
        body: formData
      });

      if (!res.ok) {
        if (res.status === 403) throw new Error("Permission Denied: Only administrators can import biometric punches.");
        throw new Error(`HTTP error ${res.status}`);
      }

      const data = await res.json();
      if (data.success) {
        setResponse(data);
        setFile(null); // clear file on success
      } else {
        setErrorMsg(data.error || "Failed to process attendance import.");
      }
    } catch (err: any) {
      console.error(err);
      setErrorMsg(err.message || "Connection failure. Unable to contact database.");
    } finally {
      setSubmitting(false);
    }
  };

  if (!canManage) {
    return (
      <div className="flex-1 flex flex-col items-center justify-center p-8 bg-background text-foreground">
        <AlertCircle size={64} className="text-red-500 mb-4" />
        <h1 className="text-2xl font-bold mb-2">Access Denied</h1>
        <p className="text-muted-foreground">You do not have the required permissions to manage attendance records.</p>
      </div>
    );
  }

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-background text-foreground">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-border bg-card text-card-foreground/50 backdrop-blur-md">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-foreground mb-1 font-['Space_Grotesk']">
              Import Attendance Punches
            </h1>
            <p className="text-sm text-muted-foreground">Upload biometric CSV logs to bulk import employee clock-ins and clock-outs</p>
          </div>
          <button 
            onClick={downloadTemplate}
            className="px-4 py-2 bg-white/5 hover:bg-accent border border-border text-foreground font-semibold rounded-lg text-sm transition-all flex items-center gap-2 cursor-pointer"
          >
            <Download size={16} /> Download CSV Template
          </button>
        </div>
      </div>

      {/* Main Body */}
      <div className="flex-1 overflow-auto p-8 max-w-4xl mx-auto w-full space-y-6">
        
        {/* Helper Instructions card */}
        <div className="bg-card text-card-foreground/40 border border-border p-6 rounded-2xl space-y-3">
          <h3 className="text-sm font-bold text-foreground uppercase tracking-wider flex items-center gap-2">
            <HelpCircle size={16} className="text-[#00e07a]" /> CSV Guidelines & Formatting
          </h3>
          <div className="text-xs text-muted-foreground space-y-2 leading-relaxed font-sans">
            <p>
              Please structure your biometric import using these guidelines. Columns must match the exact case-sensitive headers:
            </p>
            <div className="bg-black/35 p-3 rounded-lg font-mono text-[11px] text-gray-300 border border-border">
              employee_id, time_in, time_out<br />
              EMP-001, 2026-07-01 08:00, 2026-07-01 17:00<br />
              maria.clara@company.com, 2026-07-01 08:30, 2026-07-01 17:30
            </div>
            <ul className="list-disc pl-5 space-y-1">
              <li><strong>Identifiers:</strong> You can supply either the alphanumeric <code>employee_id</code> or the employee's email address.</li>
              <li><strong>Timestamps:</strong> Date/Time columns must follow standard ISO or SQL formats (e.g., <code>YYYY-MM-DD HH:MM</code>).</li>
              <li><strong>Optional:</strong> A separate date column is optional. The engine extracts the calendar date directly from the timestamps.</li>
            </ul>
          </div>
        </div>

        {/* Upload workspace */}
        <div className="bg-card text-card-foreground/70 border border-border rounded-2xl p-8 shadow-2xl space-y-6">
          
          {/* Status Responses */}
          {errorMsg && (
            <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm flex items-start gap-3">
              <AlertCircle className="w-5 h-5 flex-shrink-0" />
              <span>{errorMsg}</span>
            </div>
          )}

          {response && (
            <div className="p-5 bg-white/[0.02] border border-border rounded-xl space-y-4">
              <div className="flex items-center gap-2.5">
                <CheckCircle className="w-5 h-5 text-[#00e07a] flex-shrink-0" />
                <h3 className="text-sm font-bold text-white uppercase tracking-wider">Import Processing Complete</h3>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="bg-input border-border p-4 rounded-lg border border-border text-center">
                  <span className="block text-2xl font-bold text-[#00e07a]">{response.processed ?? 0}</span>
                  <span className="text-[10px] text-gray-500 uppercase font-semibold tracking-wider">Processed & Saved</span>
                </div>
                <div className="bg-input border-border p-4 rounded-lg border border-border text-center">
                  <span className="block text-2xl font-bold text-amber-500">{response.skipped ?? 0}</span>
                  <span className="text-[10px] text-gray-500 uppercase font-semibold tracking-wider">Skipped</span>
                </div>
              </div>

              {response.warnings && response.warnings.length > 0 && (
                <div className="border-t border-border pt-3 mt-3 space-y-2">
                  <span className="text-xs font-bold text-amber-500 uppercase tracking-wider block">Import warnings:</span>
                  <div className="bg-black/25 p-3 rounded-lg border border-border space-y-1.5 max-h-48 overflow-y-auto scrollbar-thin">
                    {response.warnings.map((w, idx) => (
                      <p key={idx} className="text-xs text-muted-foreground font-sans flex items-start gap-2">
                        <span className="text-amber-500 font-bold">•</span>
                        <span>{w}</span>
                      </p>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Form */}
          <form onSubmit={handleUploadSubmit} className="space-y-6">
            <div 
              onDragEnter={handleDrag}
              onDragLeave={handleDrag}
              onDragOver={handleDrag}
              onDrop={handleDrop}
              onClick={() => fileInputRef.current?.click()}
              className={`border-2 border-dashed rounded-2xl p-12 text-center transition-all cursor-pointer flex flex-col items-center justify-center gap-3 ${
                dragActive 
                  ? "border-[#00e07a] bg-[#00e07a]/5" 
                  : "border-border hover:border-white/20 bg-black/15"
              }`}
            >
              <input 
                type="file" 
                ref={fileInputRef}
                onChange={handleFileChange}
                accept=".csv"
                className="hidden"
              />
              <div className="w-14 h-14 rounded-full bg-white/5 border border-border flex items-center justify-center shadow-lg">
                <Upload className="w-6 h-6 text-muted-foreground" />
              </div>
              
              {file ? (
                <div className="space-y-1">
                  <p className="text-sm font-bold text-foreground flex items-center gap-2 justify-center">
                    <FileText size={16} className="text-[#00e07a]" /> {file.name}
                  </p>
                  <p className="text-xs text-gray-500">{(file.size / 1024).toFixed(2)} KB · Ready to import</p>
                </div>
              ) : (
                <div className="space-y-1 font-sans">
                  <p className="text-sm font-semibold text-white">Drag and drop your biometric CSV log here</p>
                  <p className="text-xs text-gray-500">or click to browse local files (Accepts .csv format)</p>
                </div>
              )}
            </div>

            <div className="flex justify-end pt-2">
              <button 
                type="submit" 
                disabled={!file || submitting}
                className="px-6 py-3 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-sm transition-all shadow-[0_0_20px_rgba(0,224,122,0.3)] disabled:opacity-50 flex items-center gap-2 cursor-pointer"
              >
                {submitting && <Loader2 className="w-4 h-4 animate-spin" />}
                [ START_ATTENDANCE_IMPORT ]
              </button>
            </div>
          </form>

        </div>

      </div>
    </div>
  );
}

export function AttendanceImport() {
  return (
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <div className="h-full w-full flex-1 overflow-hidden relative" style={{ isolation: 'isolate' }}>
        <AttendanceImportContent />
      </div>
    </ThemeProvider>
  );
}
