import { useState } from "react";
import { X } from "lucide-react";
import { Priority } from "./data";

interface NewTicketModalProps {
  onClose: () => void;
  onCreate: (data: { title: string; description: string; priority: Priority; category: string }) => void;
}

const CATEGORIES = ["Authentication", "Data Export", "Notifications", "Performance", "Integrations", "Feature Request", "Billing", "Other"];
const PRIORITIES: Priority[] = ["critical", "high", "medium", "low"];

const PRIORITY_META: Record<Priority, { label: string; color: string }> = {
  critical: { label: "Critical", color: "#ef4444" },
  high:     { label: "High",     color: "#f97316" },
  medium:   { label: "Medium",   color: "#f59e0b" },
  low:      { label: "Low",      color: "#8899b4" },
};

export function NewTicketModal({ onClose, onCreate }: NewTicketModalProps) {
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [priority, setPriority] = useState<Priority>("medium");
  const [category, setCategory] = useState("Authentication");

  const canSubmit = title.trim().length > 0 && description.trim().length > 0;

  return (
    <div
      onClick={onClose}
      style={{
        position: "fixed", inset: 0,
        background: "rgba(0,0,0,0.65)",
        backdropFilter: "blur(6px)",
        zIndex: 50,
        display: "flex", alignItems: "center", justifyContent: "center",
        padding: "1.5rem",
      }}
    >
      <div
        onClick={e => e.stopPropagation()}
        style={{
          background: "#111827",
          border: "1px solid rgba(255,255,255,0.1)",
          borderRadius: "1.25rem",
          width: "100%",
          maxWidth: 540,
          boxShadow: "0 24px 80px rgba(0,0,0,0.7), inset 0 1px 0 rgba(255,255,255,0.06)",
          overflow: "hidden",
        }}
      >
        {/* Modal header */}
        <div style={{
          display: "flex", alignItems: "center", justifyContent: "space-between",
          padding: "1.25rem 1.5rem",
          borderBottom: "1px solid rgba(255,255,255,0.07)",
        }}>
          <div>
            <h2 style={{ fontSize: "1rem", fontWeight: 600, color: "#f0f4ff", margin: 0 }}>
              New Support Ticket
            </h2>
            <p style={{ fontSize: "0.8125rem", color: "#4b5a6e", margin: "0.125rem 0 0" }}>
              Our team typically responds within 2 business hours
            </p>
          </div>
          <button
            onClick={onClose}
            style={{
              background: "rgba(255,255,255,0.06)", border: "1px solid rgba(255,255,255,0.08)",
              borderRadius: "0.5rem", width: 30, height: 30,
              display: "flex", alignItems: "center", justifyContent: "center",
              color: "#8899b4", cursor: "pointer",
            }}
          >
            <X size={14} />
          </button>
        </div>

        {/* Form */}
        <div style={{ padding: "1.5rem", display: "flex", flexDirection: "column", gap: "1.125rem" }}>
          {/* Title */}
          <div>
            <label style={{ display: "block", fontSize: "0.8125rem", color: "#8899b4", marginBottom: "0.375rem" }}>
              Title <span style={{ color: "#ef4444" }}>*</span>
            </label>
            <input
              value={title}
              onChange={e => setTitle(e.target.value)}
              placeholder="Brief summary of the issue"
              style={{
                width: "100%",
                background: "rgba(255,255,255,0.05)",
                border: "1px solid rgba(255,255,255,0.09)",
                borderRadius: "0.625rem",
                padding: "0.625rem 0.875rem",
                fontSize: "0.875rem",
                color: "#f0f4ff",
                outline: "none",
              }}
            />
          </div>

          {/* Priority + Category row */}
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "0.875rem" }}>
            <div>
              <label style={{ display: "block", fontSize: "0.8125rem", color: "#8899b4", marginBottom: "0.375rem" }}>
                Priority
              </label>
              <div style={{ display: "flex", gap: "0.25rem" }}>
                {PRIORITIES.map(p => {
                  const meta = PRIORITY_META[p];
                  const active = priority === p;
                  return (
                    <button
                      key={p}
                      onClick={() => setPriority(p)}
                      style={{
                        flex: 1,
                        background: active ? `${meta.color}18` : "rgba(255,255,255,0.04)",
                        border: active ? `1px solid ${meta.color}44` : "1px solid rgba(255,255,255,0.08)",
                        color: active ? meta.color : "#4b5a6e",
                        borderRadius: "0.5rem",
                        padding: "0.4375rem 0",
                        fontSize: "0.6875rem",
                        fontWeight: 500,
                        cursor: "pointer",
                        transition: "all 0.15s",
                      }}
                    >
                      {meta.label}
                    </button>
                  );
                })}
              </div>
            </div>

            <div>
              <label style={{ display: "block", fontSize: "0.8125rem", color: "#8899b4", marginBottom: "0.375rem" }}>
                Category
              </label>
              <select
                value={category}
                onChange={e => setCategory(e.target.value)}
                style={{
                  width: "100%",
                  background: "rgba(255,255,255,0.05)",
                  border: "1px solid rgba(255,255,255,0.09)",
                  borderRadius: "0.625rem",
                  padding: "0.5625rem 0.875rem",
                  fontSize: "0.875rem",
                  color: "#f0f4ff",
                  outline: "none",
                  cursor: "pointer",
                }}
              >
                {CATEGORIES.map(c => (
                  <option key={c} value={c} style={{ background: "#111827" }}>{c}</option>
                ))}
              </select>
            </div>
          </div>

          {/* Description */}
          <div>
            <label style={{ display: "block", fontSize: "0.8125rem", color: "#8899b4", marginBottom: "0.375rem" }}>
              Description <span style={{ color: "#ef4444" }}>*</span>
            </label>
            <textarea
              value={description}
              onChange={e => setDescription(e.target.value)}
              placeholder="Describe the issue in detail — include steps to reproduce, affected users, and any relevant error messages."
              rows={5}
              style={{
                width: "100%",
                background: "rgba(255,255,255,0.05)",
                border: "1px solid rgba(255,255,255,0.09)",
                borderRadius: "0.625rem",
                padding: "0.625rem 0.875rem",
                fontSize: "0.875rem",
                color: "#f0f4ff",
                outline: "none",
                resize: "vertical",
                lineHeight: 1.65,
              }}
            />
          </div>
        </div>

        {/* Footer */}
        <div style={{
          padding: "1rem 1.5rem 1.5rem",
          display: "flex", gap: "0.75rem", justifyContent: "flex-end",
        }}>
          <button
            onClick={onClose}
            style={{
              background: "transparent",
              border: "1px solid rgba(255,255,255,0.1)",
              color: "#8899b4",
              borderRadius: "0.625rem",
              padding: "0.5625rem 1.125rem",
              fontSize: "0.875rem",
              cursor: "pointer",
            }}
          >
            Cancel
          </button>
          <button
            disabled={!canSubmit}
            onClick={() => { if (canSubmit) { onCreate({ title, description, priority, category }); onClose(); } }}
            style={{
              background: canSubmit
                ? "linear-gradient(135deg, #6366f1, #ec4899)"
                : "rgba(255,255,255,0.06)",
              border: "none",
              color: canSubmit ? "#fff" : "#4b5a6e",
              borderRadius: "0.625rem",
              padding: "0.5625rem 1.375rem",
              fontSize: "0.875rem",
              fontWeight: 500,
              cursor: canSubmit ? "pointer" : "not-allowed",
              boxShadow: canSubmit ? "0 4px 20px rgba(99,102,241,0.35)" : "none",
              transition: "all 0.2s ease",
            }}
          >
            Submit Ticket
          </button>
        </div>
      </div>
    </div>
  );
}
