import { Ticket, Status, Priority, STATUS_META, PRIORITY_META } from "./data";
import { Search, Plus, SlidersHorizontal } from "lucide-react";
import { useState } from "react";

interface TicketListProps {
  tickets: Ticket[];
  selectedId: string | null;
  onSelect: (id: string) => void;
  onNew: () => void;
  filterStatus: Status | "all";
  onFilterStatus: (s: Status | "all") => void;
  selectedTicketIds?: string[];
  onToggleSelect?: (id: string) => void;
  onToggleSelectAll?: () => void;
  onBulkAction?: (action: string, value?: string) => void;
}

function relativeTime(iso: string) {
  const diff = Date.now() - new Date(iso).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h ago`;
  return `${Math.floor(h / 24)}d ago`;
}

const STATUS_FILTERS: Array<{ key: Status | "all"; label: string }> = [
  { key: "all", label: "All" },
  { key: "open", label: "Open" },
  { key: "in_progress", label: "In Progress" },
  { key: "waiting", label: "Waiting" },
  { key: "resolved", label: "Resolved" },
  { key: "closed", label: "Closed" },
];

export function TicketList({ tickets, selectedId, onSelect, onNew, filterStatus, onFilterStatus, selectedTicketIds = [], onToggleSelect, onToggleSelectAll, onBulkAction }: TicketListProps) {
  const [query, setQuery] = useState("");

  const filtered = tickets.filter(t => {
    const matchStatus = filterStatus === "all" || t.status === filterStatus;
    const matchQuery = !query || t.title.toLowerCase().includes(query.toLowerCase()) || t.id.toLowerCase().includes(query.toLowerCase());
    return matchStatus && matchQuery;
  });

  return (
    <div style={{
      display: "flex",
      flexDirection: "column",
      height: "100%",
      borderRight: "1px solid rgba(255,255,255,0.07)",
    }}>
      {/* Header */}
      <div style={{ padding: "1.25rem 1rem 0.875rem", borderBottom: "1px solid rgba(255,255,255,0.06)" }}>
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: "0.875rem" }}>
          <span style={{ fontSize: "0.8125rem", fontWeight: 600, color: "#f0f4ff" }}>
            Tickets
            <span style={{
              marginLeft: "0.5rem",
              background: "rgba(255,255,255,0.08)",
              color: "#8899b4",
              fontSize: "0.6875rem",
              padding: "0.125rem 0.4rem",
              borderRadius: 99,
              fontFamily: "'JetBrains Mono', monospace",
            }}>
              {filtered.length}
            </span>
          </span>
          <button
            onClick={onNew}
            style={{
              display: "flex", alignItems: "center", gap: "0.35rem",
              background: "linear-gradient(135deg, #00e07a, #00b8ff)",
              border: "none",
              color: "#0b0f1a",
              borderRadius: "0.5rem",
              padding: "0.375rem 0.75rem",
              fontSize: "0.75rem",
              fontWeight: 600,
              cursor: "pointer",
              boxShadow: "0 2px 12px rgba(0,224,122,0.25)",
            }}
          >
            <Plus size={13} />
            New
          </button>
        </div>

        {/* Search */}
        <div style={{ position: "relative", marginBottom: "0.75rem" }}>
          <Search size={13} style={{
            position: "absolute", left: "0.625rem", top: "50%", transform: "translateY(-50%)",
            color: "#4b5a6e", pointerEvents: "none",
          }} />
          <input
            value={query}
            onChange={e => setQuery(e.target.value)}
            placeholder="Search tickets…"
            style={{
              width: "100%",
              background: "rgba(255,255,255,0.05)",
              border: "1px solid rgba(255,255,255,0.08)",
              borderRadius: "0.5rem",
              padding: "0.4375rem 0.625rem 0.4375rem 2rem",
              fontSize: "0.8125rem",
              color: "#f0f4ff",
              outline: "none",
            }}
          />
        </div>

        {/* Status filter pills */}
        <div style={{ display: "flex", gap: "0.25rem", flexWrap: "wrap" }}>
          {STATUS_FILTERS.map(f => (
            <button
              key={f.key}
              onClick={() => onFilterStatus(f.key)}
              style={{
                background: filterStatus === f.key ? "rgba(0,224,122,0.12)" : "transparent",
                border: filterStatus === f.key ? "1px solid rgba(0,224,122,0.3)" : "1px solid rgba(255,255,255,0.07)",
                color: filterStatus === f.key ? "#00e07a" : "#4b5a6e",
                borderRadius: "0.375rem",
                padding: "0.25rem 0.5rem",
                fontSize: "0.6875rem",
                fontWeight: 500,
                cursor: "pointer",
                transition: "all 0.15s ease",
              }}
            >
              {f.label}
            </button>
          ))}
        </div>
      </div>

      {/* Bulk Action Banner */}
      {selectedTicketIds.length > 0 && (
        <div style={{
          background: "linear-gradient(135deg, rgba(0,224,122,0.08), rgba(0,184,255,0.08))",
          borderBottom: "1px solid rgba(0,224,122,0.2)",
          padding: "0.625rem 1rem",
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          animation: "slideDown 0.2s ease-out",
        }}>
          <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
            <span style={{ fontSize: "0.75rem", fontWeight: 600, color: "#00e07a" }}>
              {selectedTicketIds.length} selected
            </span>
          </div>
          <div style={{ display: "flex", gap: "0.375rem" }}>
            <button
              onClick={() => onBulkAction && onBulkAction("resolve")}
              style={{ background: "rgba(16,185,129,0.15)", border: "1px solid rgba(16,185,129,0.3)", color: "#10b981", borderRadius: "0.25rem", padding: "0.25rem 0.5rem", fontSize: "0.6875rem", cursor: "pointer" }}
            >
              Resolve
            </button>
            <button
              onClick={() => onBulkAction && onBulkAction("close")}
              style={{ background: "rgba(100,116,139,0.15)", border: "1px solid rgba(100,116,139,0.3)", color: "#94a3b8", borderRadius: "0.25rem", padding: "0.25rem 0.5rem", fontSize: "0.6875rem", cursor: "pointer" }}
            >
              Close
            </button>
          </div>
        </div>
      )}

      {/* List */}
      <div style={{ flex: 1, overflowY: "auto" }}>
        {filtered.length === 0 ? (
          <div style={{ padding: "2rem 1rem", textAlign: "center", color: "#4b5a6e", fontSize: "0.8125rem" }}>
            No tickets match your filters
          </div>
        ) : (
          filtered.map(ticket => {
            const sm = STATUS_META[ticket.status];
            const pm = PRIORITY_META[ticket.priority];
            const active = ticket.id === selectedId;

            return (
              <div
                key={ticket.id}
                onClick={() => onSelect(ticket.id)}
                style={{
                  padding: "0.875rem 1rem",
                  borderBottom: "1px solid rgba(255,255,255,0.04)",
                  background: active ? "rgba(0,224,122,0.07)" : "transparent",
                  borderLeft: active ? "2px solid #00e07a" : "2px solid transparent",
                  cursor: "pointer",
                  transition: "all 0.15s ease",
                }}
                onMouseEnter={e => { if (!active) e.currentTarget.style.background = "rgba(255,255,255,0.03)"; }}
                onMouseLeave={e => { if (!active) e.currentTarget.style.background = "transparent"; }}
              >
                <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", gap: "0.5rem", marginBottom: "0.375rem" }}>
                  <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
                    <input
                      type="checkbox"
                      checked={selectedTicketIds.includes(ticket.id)}
                      onChange={(e) => {
                        e.stopPropagation();
                        onToggleSelect && onToggleSelect(ticket.id);
                      }}
                      onClick={(e) => e.stopPropagation()}
                      style={{ cursor: "pointer" }}
                    />
                    <span style={{
                      fontFamily: "'JetBrains Mono', monospace",
                      fontSize: "0.6875rem",
                      color: "#4b5a6e",
                    }}>
                      {ticket.id}
                    </span>
                  </div>
                  <div style={{ display: "flex", alignItems: "center", gap: "0.35rem", flexShrink: 0 }}>
                    <div style={{ width: 6, height: 6, borderRadius: "50%", background: pm.dot, flexShrink: 0 }} />
                    <span style={{
                      background: sm.bg,
                      color: sm.color,
                      border: `1px solid ${sm.border}`,
                      borderRadius: 99,
                      fontSize: "0.625rem",
                      fontWeight: 500,
                      padding: "0.125rem 0.4rem",
                      whiteSpace: "nowrap",
                    }}>
                      {sm.label}
                    </span>
                  </div>
                </div>

                <p style={{
                  fontSize: "0.8125rem",
                  color: active ? "#f0f4ff" : "#c8d4e8",
                  fontWeight: active ? 500 : 400,
                  lineHeight: 1.45,
                  marginBottom: "0.5rem",
                  display: "-webkit-box",
                  WebkitLineClamp: 2,
                  WebkitBoxOrient: "vertical",
                  overflow: "hidden",
                }}>
                  {ticket.title}
                </p>

                <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                  <span style={{ fontSize: "0.6875rem", color: "#4b5a6e" }}>{ticket.category}</span>
                  <span style={{ fontSize: "0.6875rem", color: "#4b5a6e" }}>{relativeTime(ticket.updated)}</span>
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}
