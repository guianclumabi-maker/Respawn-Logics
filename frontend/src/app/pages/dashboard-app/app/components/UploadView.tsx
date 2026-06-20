import { useState, useRef, useCallback } from "react";
import { Upload, FileSpreadsheet, Download, AlertCircle, ChevronRight } from "lucide-react";

interface UploadViewProps {
  onUpload: (file: File) => void;
}

export function UploadView({ onUpload }: UploadViewProps) {
  const [dragging, setDragging] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const validate = (file: File) => {
    const allowed = ["text/csv", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "application/vnd.ms-excel"];
    if (!allowed.includes(file.type) && !file.name.match(/\.(csv|xlsx|xls)$/i)) {
      return "Only CSV and Excel files are supported.";
    }
    if (file.size > 10 * 1024 * 1024) {
      return "File must be under 10 MB.";
    }
    return null;
  };

  const handleFile = (file: File) => {
    const err = validate(file);
    if (err) { setError(err); setSelectedFile(null); return; }
    setError(null);
    setSelectedFile(file);
  };

  const onDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setDragging(false);
    const file = e.dataTransfer.files[0];
    if (file) handleFile(file);
  }, []);

  const onDragOver = (e: React.DragEvent) => { e.preventDefault(); setDragging(true); };
  const onDragLeave = () => setDragging(false);

  const formatSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 style={{ fontSize: "1.5rem", fontWeight: 600, color: "#f0f4ff", lineHeight: 1.3 }}>
          Import Employee Records
        </h2>
        <p style={{ color: "#8899b4", marginTop: "0.375rem", fontSize: "0.9375rem" }}>
          Upload a CSV or Excel file to batch-create employee accounts. Changes are staged for review before applying.
        </p>
      </div>

      {/* Requirements */}
      <div style={{
        background: "rgba(59, 130, 246, 0.08)",
        border: "1px solid rgba(59, 130, 246, 0.2)",
        borderRadius: "0.75rem",
        padding: "1rem 1.25rem",
        display: "flex",
        gap: "0.875rem",
        alignItems: "flex-start"
      }}>
        <AlertCircle size={16} style={{ color: "#3b82f6", marginTop: "2px", flexShrink: 0 }} />
        <div style={{ fontSize: "0.875rem", color: "#8899b4", lineHeight: 1.6 }}>
          <span style={{ color: "#f0f4ff", fontWeight: 500 }}>Required columns: </span>
          first_name, last_name, email, department, job_title, start_date
          <span style={{ color: "#4b5a6e", marginLeft: "0.5rem" }}>· Optional: manager_email, salary, location</span>
        </div>
      </div>

      {/* Drop zone */}
      <div
        onDrop={onDrop}
        onDragOver={onDragOver}
        onDragLeave={onDragLeave}
        onClick={() => !selectedFile && inputRef.current?.click()}
        style={{
          border: `2px dashed ${dragging ? "#3b82f6" : error ? "#ef4444" : "rgba(255,255,255,0.12)"}`,
          borderRadius: "1rem",
          background: dragging
            ? "rgba(59,130,246,0.06)"
            : selectedFile
            ? "rgba(16,185,129,0.05)"
            : "rgba(255,255,255,0.025)",
          padding: "3.5rem 2rem",
          textAlign: "center",
          cursor: selectedFile ? "default" : "pointer",
          transition: "all 0.2s ease",
        }}
      >
        <input
          ref={inputRef}
          type="file"
          accept=".csv,.xlsx,.xls"
          style={{ display: "none" }}
          onChange={(e) => { const f = e.target.files?.[0]; if (f) handleFile(f); }}
        />

        {selectedFile ? (
          <div style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: "0.75rem" }}>
            <div style={{
              width: 56, height: 56, borderRadius: "0.875rem",
              background: "rgba(16,185,129,0.15)",
              display: "flex", alignItems: "center", justifyContent: "center"
            }}>
              <FileSpreadsheet size={26} style={{ color: "#10b981" }} />
            </div>
            <div>
              <p style={{ color: "#f0f4ff", fontWeight: 500, fontSize: "0.9375rem" }}>{selectedFile.name}</p>
              <p style={{ color: "#8899b4", fontSize: "0.8125rem", marginTop: "0.25rem" }}>
                {formatSize(selectedFile.size)}
              </p>
            </div>
            <button
              onClick={(e) => { e.stopPropagation(); setSelectedFile(null); setError(null); }}
              style={{
                background: "transparent",
                border: "1px solid rgba(255,255,255,0.12)",
                color: "#8899b4",
                borderRadius: "0.5rem",
                padding: "0.375rem 0.875rem",
                fontSize: "0.8125rem",
                cursor: "pointer",
                marginTop: "0.25rem",
              }}
            >
              Choose different file
            </button>
          </div>
        ) : (
          <div style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: "0.875rem" }}>
            <div style={{
              width: 56, height: 56, borderRadius: "0.875rem",
              background: "rgba(255,255,255,0.06)",
              display: "flex", alignItems: "center", justifyContent: "center"
            }}>
              <Upload size={24} style={{ color: "#8899b4" }} />
            </div>
            <div>
              <p style={{ color: "#f0f4ff", fontWeight: 500, fontSize: "0.9375rem" }}>
                Drop your file here, or{" "}
                <span style={{ color: "#3b82f6" }}>browse</span>
              </p>
              <p style={{ color: "#4b5a6e", fontSize: "0.8125rem", marginTop: "0.25rem" }}>
                CSV or Excel · Max 10 MB
              </p>
            </div>
          </div>
        )}
      </div>

      {error && (
        <div style={{
          display: "flex", gap: "0.5rem", alignItems: "center",
          color: "#ef4444", fontSize: "0.875rem"
        }}>
          <AlertCircle size={14} />
          {error}
        </div>
      )}

      {/* Actions */}
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: "1rem" }}>
        <button
          style={{
            display: "flex", alignItems: "center", gap: "0.5rem",
            background: "transparent",
            border: "1px solid rgba(255,255,255,0.1)",
            color: "#8899b4",
            borderRadius: "0.625rem",
            padding: "0.625rem 1.125rem",
            fontSize: "0.875rem",
            cursor: "pointer",
            transition: "all 0.15s ease",
          }}
          onMouseEnter={e => (e.currentTarget.style.color = "#f0f4ff")}
          onMouseLeave={e => (e.currentTarget.style.color = "#8899b4")}
        >
          <Download size={15} />
          Download template
        </button>

        <button
          disabled={!selectedFile}
          onClick={() => selectedFile && onUpload(selectedFile)}
          style={{
            display: "flex", alignItems: "center", gap: "0.5rem",
            background: selectedFile
              ? "linear-gradient(135deg, #6366f1 0%, #ec4899 100%)"
              : "rgba(255,255,255,0.06)",
            border: "none",
            color: selectedFile ? "#ffffff" : "#4b5a6e",
            borderRadius: "0.625rem",
            padding: "0.625rem 1.375rem",
            fontSize: "0.875rem",
            fontWeight: 500,
            cursor: selectedFile ? "pointer" : "not-allowed",
            transition: "all 0.2s ease",
            boxShadow: selectedFile ? "0 4px 20px rgba(99,102,241,0.35)" : "none",
          }}
        >
          Start Import
          <ChevronRight size={15} />
        </button>
      </div>
    </div>
  );
}
