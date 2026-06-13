import { useEffect, useState } from "react";
import { Loader2 } from "lucide-react";

interface ProcessingViewProps {
  fileName: string;
  onComplete: (results: { total: number; success: number; errors: number }) => void;
}

const steps = [
  { id: "parse", label: "Parsing file structure", detail: "Validating columns and data types" },
  { id: "dedupe", label: "Checking for duplicates", detail: "Cross-referencing existing employee records" },
  { id: "validate", label: "Validating records", detail: "Checking required fields and formats" },
  { id: "stage", label: "Staging changes", detail: "Preparing records for import" },
];

export function ProcessingView({ fileName, onComplete }: ProcessingViewProps) {
  const [currentStep, setCurrentStep] = useState(0);
  const [progress, setProgress] = useState(0);
  const [rowsProcessed, setRowsProcessed] = useState(0);
  const totalRows = 247;

  useEffect(() => {
    const stepDuration = 1800;
    let stepTimer: ReturnType<typeof setTimeout>;
    let progressInterval: ReturnType<typeof setInterval>;

    const runStep = (step: number) => {
      const stepStart = (step / steps.length) * 100;
      const stepEnd = ((step + 1) / steps.length) * 100;
      let current = stepStart;

      progressInterval = setInterval(() => {
        current += (stepEnd - current) * 0.08;
        setProgress(Math.round(current));
        setRowsProcessed(Math.round((current / 100) * totalRows));
      }, 40);

      stepTimer = setTimeout(() => {
        clearInterval(progressInterval);
        if (step < steps.length - 1) {
          setCurrentStep(step + 1);
          runStep(step + 1);
        } else {
          setProgress(100);
          setRowsProcessed(totalRows);
          setTimeout(() => onComplete({ total: totalRows, success: 231, errors: 16 }), 600);
        }
      }, stepDuration);
    };

    runStep(0);
    return () => { clearTimeout(stepTimer); clearInterval(progressInterval); };
  }, []);

  return (
    <div className="space-y-8">
      <div>
        <h2 style={{ fontSize: "1.5rem", fontWeight: 600, color: "#f0f4ff", lineHeight: 1.3 }}>
          Processing Import
        </h2>
        <p style={{ color: "#8899b4", marginTop: "0.375rem", fontSize: "0.9375rem" }}>
          <span style={{ color: "#f0f4ff", fontFamily: "'JetBrains Mono', monospace", fontSize: "0.875rem" }}>
            {fileName}
          </span>
          {" "}· {totalRows} records detected
        </p>
      </div>

      {/* Progress bar */}
      <div>
        <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "0.625rem" }}>
          <span style={{ color: "#8899b4", fontSize: "0.8125rem" }}>
            {rowsProcessed.toLocaleString()} / {totalRows.toLocaleString()} rows
          </span>
          <span style={{
            fontFamily: "'JetBrains Mono', monospace",
            fontSize: "0.8125rem",
            color: "#3b82f6",
            fontWeight: 500
          }}>
            {progress}%
          </span>
        </div>
        <div style={{
          height: 6, background: "rgba(255,255,255,0.07)", borderRadius: 99, overflow: "hidden"
        }}>
          <div style={{
            height: "100%",
            width: `${progress}%`,
            background: "linear-gradient(90deg, #6366f1, #ec4899)",
            borderRadius: 99,
            transition: "width 0.1s ease",
            boxShadow: "0 0 12px rgba(99,102,241,0.5)",
          }} />
        </div>
      </div>

      {/* Steps */}
      <div style={{ display: "flex", flexDirection: "column", gap: "0.25rem" }}>
        {steps.map((step, idx) => {
          const done = idx < currentStep;
          const active = idx === currentStep;
          return (
            <div
              key={step.id}
              style={{
                display: "flex",
                alignItems: "center",
                gap: "0.875rem",
                padding: "0.875rem 1rem",
                borderRadius: "0.75rem",
                background: active ? "rgba(59,130,246,0.07)" : "transparent",
                border: active ? "1px solid rgba(59,130,246,0.15)" : "1px solid transparent",
                transition: "all 0.3s ease",
              }}
            >
              <div style={{
                width: 28, height: 28, borderRadius: "50%", flexShrink: 0,
                display: "flex", alignItems: "center", justifyContent: "center",
                background: done
                  ? "rgba(16,185,129,0.2)"
                  : active
                  ? "rgba(59,130,246,0.15)"
                  : "rgba(255,255,255,0.05)",
                border: done
                  ? "1px solid rgba(16,185,129,0.4)"
                  : active
                  ? "1px solid rgba(59,130,246,0.3)"
                  : "1px solid rgba(255,255,255,0.08)",
                transition: "all 0.3s ease",
              }}>
                {done ? (
                  <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M2 6l3 3 5-5" stroke="#10b981" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                ) : active ? (
                  <Loader2 size={13} style={{ color: "#3b82f6", animation: "spin 1s linear infinite" }} />
                ) : (
                  <div style={{ width: 6, height: 6, borderRadius: "50%", background: "rgba(255,255,255,0.15)" }} />
                )}
              </div>
              <div style={{ flex: 1 }}>
                <p style={{
                  color: done ? "#8899b4" : active ? "#f0f4ff" : "#4b5a6e",
                  fontSize: "0.875rem",
                  fontWeight: active ? 500 : 400,
                  transition: "all 0.3s ease",
                }}>
                  {step.label}
                </p>
                {active && (
                  <p style={{ color: "#4b5a6e", fontSize: "0.75rem", marginTop: "0.125rem" }}>
                    {step.detail}
                  </p>
                )}
              </div>
              {done && (
                <span style={{
                  fontFamily: "'JetBrains Mono', monospace",
                  fontSize: "0.75rem",
                  color: "#10b981",
                }}>done</span>
              )}
            </div>
          );
        })}
      </div>

      <style>{`
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
      `}</style>
    </div>
  );
}
