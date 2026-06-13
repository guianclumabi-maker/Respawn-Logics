import { CheckCircle2, XCircle, Download, RefreshCw, Users } from "lucide-react";

interface ResultsViewProps {
  results: { total: number; success: number; errors: number };
  fileName: string;
  onReset: () => void;
}

const errorRows = [
  { row: 14, email: "james.wi@acme.co", field: "department", issue: "Unknown department 'Engineering-X'" },
  { row: 27, email: "sara.chen@acme.co", field: "start_date", issue: "Invalid date format '2024/13/01'" },
  { row: 31, email: "", field: "email", issue: "Missing required field" },
  { row: 45, email: "bob.t@acme.co", field: "salary", issue: "Non-numeric value '85k'" },
  { row: 62, email: "priya.n@acme.co", field: "manager_email", issue: "Manager not found in system" },
  { row: 78, email: "tom.lee@acme.co", field: "email", issue: "Duplicate email — record already exists" },
  { row: 93, email: "ana.g@acme.co", field: "job_title", issue: "Exceeds max length (128 chars)" },
  { row: 107, email: "mike.d@acme.co", field: "department", issue: "Unknown department 'Ops & Infra'" },
];

export function ResultsView({ results, fileName, onReset }: ResultsViewProps) {
  const successRate = Math.round((results.success / results.total) * 100);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 style={{ fontSize: "1.5rem", fontWeight: 600, color: "#f0f4ff", lineHeight: 1.3 }}>
          Import Complete
        </h2>
        <p style={{ color: "#8899b4", marginTop: "0.375rem", fontSize: "0.9375rem" }}>
          <span style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: "0.875rem", color: "#f0f4ff" }}>
            {fileName}
          </span>
          {" "}processed successfully
        </p>
      </div>

      {/* Stat cards */}
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: "0.75rem" }}>
        {[
          {
            icon: <Users size={18} style={{ color: "#3b82f6" }} />,
            label: "Total Records",
            value: results.total.toLocaleString(),
            color: "#3b82f6",
            bg: "rgba(59,130,246,0.08)",
            border: "rgba(59,130,246,0.2)",
          },
          {
            icon: <CheckCircle2 size={18} style={{ color: "#10b981" }} />,
            label: "Imported",
            value: results.success.toLocaleString(),
            sub: `${successRate}% success`,
            color: "#10b981",
            bg: "rgba(16,185,129,0.08)",
            border: "rgba(16,185,129,0.2)",
          },
          {
            icon: <XCircle size={18} style={{ color: "#ef4444" }} />,
            label: "Failed",
            value: results.errors.toLocaleString(),
            sub: "see report below",
            color: "#ef4444",
            bg: "rgba(239,68,68,0.08)",
            border: "rgba(239,68,68,0.2)",
          },
        ].map((card) => (
          <div
            key={card.label}
            style={{
              background: card.bg,
              border: `1px solid ${card.border}`,
              borderRadius: "0.875rem",
              padding: "1.125rem",
            }}
          >
            <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", marginBottom: "0.625rem" }}>
              {card.icon}
              <span style={{ color: "#8899b4", fontSize: "0.8125rem" }}>{card.label}</span>
            </div>
            <p style={{
              color: card.color,
              fontFamily: "'JetBrains Mono', monospace",
              fontSize: "1.625rem",
              fontWeight: 600,
              lineHeight: 1,
            }}>
              {card.value}
            </p>
            {card.sub && (
              <p style={{ color: "#4b5a6e", fontSize: "0.75rem", marginTop: "0.375rem" }}>{card.sub}</p>
            )}
          </div>
        ))}
      </div>

      {/* Progress bar */}
      <div>
        <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "0.5rem" }}>
          <span style={{ fontSize: "0.8125rem", color: "#8899b4" }}>Success rate</span>
          <span style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: "0.8125rem", color: "#10b981" }}>
            {successRate}%
          </span>
        </div>
        <div style={{ height: 6, background: "rgba(255,255,255,0.07)", borderRadius: 99, overflow: "hidden" }}>
          <div style={{
            height: "100%",
            width: `${successRate}%`,
            background: "linear-gradient(90deg, #10b981, #3b82f6)",
            borderRadius: 99,
          }} />
        </div>
      </div>

      {/* Error table */}
      {results.errors > 0 && (
        <div>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: "0.75rem" }}>
            <h3 style={{ color: "#f0f4ff", fontSize: "0.9375rem", fontWeight: 500 }}>
              Error Report
              <span style={{
                marginLeft: "0.5rem",
                background: "rgba(239,68,68,0.15)",
                color: "#ef4444",
                fontSize: "0.75rem",
                fontFamily: "'JetBrains Mono', monospace",
                padding: "0.125rem 0.5rem",
                borderRadius: 99,
                border: "1px solid rgba(239,68,68,0.2)",
              }}>
                {results.errors} errors
              </span>
            </h3>
            <button
              style={{
                display: "flex", alignItems: "center", gap: "0.4rem",
                background: "transparent",
                border: "1px solid rgba(255,255,255,0.1)",
                color: "#8899b4",
                borderRadius: "0.5rem",
                padding: "0.375rem 0.75rem",
                fontSize: "0.8125rem",
                cursor: "pointer",
              }}
            >
              <Download size={13} />
              Export CSV
            </button>
          </div>

          <div style={{
            border: "1px solid rgba(255,255,255,0.08)",
            borderRadius: "0.75rem",
            overflow: "hidden",
          }}>
            <div style={{
              display: "grid",
              gridTemplateColumns: "60px 1fr 100px 1fr",
              gap: 0,
              background: "rgba(255,255,255,0.03)",
              borderBottom: "1px solid rgba(255,255,255,0.07)",
              padding: "0.625rem 1rem",
            }}>
              {["Row", "Email", "Field", "Issue"].map((h) => (
                <span key={h} style={{
                  fontSize: "0.75rem",
                  fontWeight: 500,
                  color: "#4b5a6e",
                  textTransform: "uppercase",
                  letterSpacing: "0.05em",
                  fontFamily: "'JetBrains Mono', monospace",
                }}>
                  {h}
                </span>
              ))}
            </div>

            {errorRows.slice(0, results.errors).map((row, i) => (
              <div
                key={row.row}
                style={{
                  display: "grid",
                  gridTemplateColumns: "60px 1fr 100px 1fr",
                  gap: 0,
                  padding: "0.625rem 1rem",
                  borderBottom: i < errorRows.length - 1 ? "1px solid rgba(255,255,255,0.05)" : "none",
                  background: i % 2 === 0 ? "transparent" : "rgba(255,255,255,0.015)",
                  alignItems: "center",
                }}
              >
                <span style={{
                  fontFamily: "'JetBrains Mono', monospace",
                  fontSize: "0.8125rem",
                  color: "#ef4444",
                }}>
                  #{row.row}
                </span>
                <span style={{ fontSize: "0.8125rem", color: "#8899b4", fontFamily: "'JetBrains Mono', monospace" }}>
                  {row.email || <span style={{ color: "#4b5a6e", fontStyle: "italic" }}>—</span>}
                </span>
                <span style={{
                  fontSize: "0.75rem",
                  background: "rgba(239,68,68,0.1)",
                  color: "#ef4444",
                  padding: "0.125rem 0.5rem",
                  borderRadius: 4,
                  fontFamily: "'JetBrains Mono', monospace",
                  display: "inline-block",
                  width: "fit-content",
                }}>
                  {row.field}
                </span>
                <span style={{ fontSize: "0.8125rem", color: "#8899b4" }}>{row.issue}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Actions */}
      <div style={{ display: "flex", gap: "0.75rem", paddingTop: "0.25rem" }}>
        <button
          onClick={onReset}
          style={{
            display: "flex", alignItems: "center", gap: "0.5rem",
            background: "transparent",
            border: "1px solid rgba(255,255,255,0.1)",
            color: "#8899b4",
            borderRadius: "0.625rem",
            padding: "0.625rem 1.125rem",
            fontSize: "0.875rem",
            cursor: "pointer",
          }}
        >
          <RefreshCw size={14} />
          Import Another File
        </button>
        <button
          style={{
            flex: 1,
            background: "linear-gradient(135deg, #6366f1 0%, #ec4899 100%)",
            border: "none",
            color: "#ffffff",
            borderRadius: "0.625rem",
            padding: "0.625rem 1.375rem",
            fontSize: "0.875rem",
            fontWeight: 500,
            cursor: "pointer",
            boxShadow: "0 4px 20px rgba(99,102,241,0.35)",
          }}
        >
          View Imported Employees →
        </button>
      </div>
    </div>
  );
}
