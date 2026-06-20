import { useState } from "react";
import { Ticket, STATUS_META, PRIORITY_META, Status } from "./data";
import { Tag, Clock, User, ChevronDown, Send, Lock, ArrowLeft } from "lucide-react";

interface TicketDetailProps {
  ticket: Ticket;
  onBack?: () => void;
  onStatusChange: (id: string, status: Status) => void;
}

function formatTime(iso: string) {
  const d = new Date(iso);
  return d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) +
    " · " + d.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit" });
}

const STATUS_OPTIONS: Status[] = ["open", "in_progress", "waiting", "resolved", "closed"];

export function TicketDetail({ ticket, onBack, onStatusChange }: TicketDetailProps) {
  const [reply, setReply] = useState("");
  const [internal, setInternal] = useState(false);
  const [statusOpen, setStatusOpen] = useState(false);
  const sm = STATUS_META[ticket.status];
  const pm = PRIORITY_META[ticket.priority];

  const handleReply = () => {
    if (!reply.trim()) return;
    
    // Safely get user name from window object, fallback to 'Agent'
    const userName = typeof window !== 'undefined' && (window as any).__USER_NAME__ ? (window as any).__USER_NAME__ : "Agent";
    const userInitials = userName.split(" ").map((n: string) => n[0]).join("").toUpperCase();
    
    const newMsg: Comment = {
      id: "new" + Date.now(),
      author: { name: userName, initials: userInitials, color: "#00e07a", role: "agent" },
      body: reply,
      timestamp: new Date().toISOString(),
      internal: internal,
      attachments: []
    };
    setReply("");
  };

  return (
    <div style={{ display: "flex", flexDirection: "column", height: "100%", overflow: "hidden" }}>
      {/* Header */}
      <div style={{
        padding: "1.25rem 1.5rem",
        borderBottom: "1px solid rgba(255,255,255,0.07)",
        flexShrink: 0,
      }}>
        {onBack && (
          <button
            onClick={onBack}
            style={{
              display: "flex", alignItems: "center", gap: "0.375rem",
              background: "transparent", border: "none",
              color: "#4b5a6e", fontSize: "0.8125rem", cursor: "pointer",
              marginBottom: "0.875rem", padding: 0,
            }}
          >
            <ArrowLeft size={13} /> Back
          </button>
        )}

        <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", gap: "1rem" }}>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", marginBottom: "0.375rem" }}>
              <span style={{
                fontFamily: "'JetBrains Mono', monospace",
                fontSize: "0.75rem",
                color: "#4b5a6e",
              }}>{ticket.id}</span>
              <span style={{ color: "#4b5a6e" }}>·</span>
              <span style={{ fontSize: "0.75rem", color: "#4b5a6e" }}>{ticket.category}</span>
            </div>
            <h1 style={{
              fontSize: "1.125rem",
              fontWeight: 600,
              color: "#f0f4ff",
              lineHeight: 1.4,
              margin: 0,
            }}>
              {ticket.title}
            </h1>
          </div>

          {/* Status dropdown */}
          <div style={{ position: "relative", flexShrink: 0 }}>
            {(window as any).__ROLE__ === 'agent' ? (
              <button
                onClick={() => setStatusOpen(v => !v)}
                style={{
                  display: "flex", alignItems: "center", gap: "0.375rem",
                  background: sm.bg,
                  border: `1px solid ${sm.border}`,
                  color: sm.color,
                  borderRadius: "0.5rem",
                  padding: "0.375rem 0.625rem",
                  fontSize: "0.75rem",
                  fontWeight: 500,
                  cursor: "pointer",
                }}
              >
                {sm.label}
                <ChevronDown size={12} />
              </button>
            ) : (
              <div
                style={{
                  display: "flex", alignItems: "center", gap: "0.375rem",
                  background: sm.bg,
                  border: `1px solid ${sm.border}`,
                  color: sm.color,
                  borderRadius: "0.5rem",
                  padding: "0.375rem 0.625rem",
                  fontSize: "0.75rem",
                  fontWeight: 500,
                }}
              >
                {sm.label}
              </div>
            )}

            {statusOpen && (
              <div style={{
                position: "absolute", top: "calc(100% + 0.375rem)", right: 0,
                background: "#161b27",
                border: "1px solid rgba(255,255,255,0.1)",
                borderRadius: "0.625rem",
                overflow: "hidden",
                zIndex: 20,
                minWidth: 140,
                boxShadow: "0 8px 32px rgba(0,0,0,0.5)",
              }}>
                {STATUS_OPTIONS.map(s => {
                  const meta = STATUS_META[s];
                  return (
                    <div
                      key={s}
                      onClick={() => { onStatusChange(ticket.id, s); setStatusOpen(false); }}
                      style={{
                        padding: "0.5rem 0.875rem",
                        cursor: "pointer",
                        display: "flex", alignItems: "center", gap: "0.5rem",
                        background: ticket.status === s ? "rgba(59,130,246,0.08)" : "transparent",
                        fontSize: "0.8125rem",
                        color: meta.color,
                      }}
                      onMouseEnter={e => e.currentTarget.style.background = "rgba(255,255,255,0.04)"}
                      onMouseLeave={e => e.currentTarget.style.background = ticket.status === s ? "rgba(59,130,246,0.08)" : "transparent"}
                    >
                      <div style={{ width: 6, height: 6, borderRadius: "50%", background: meta.color }} />
                      {meta.label}
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>

        {/* Meta row */}
        <div style={{
          display: "flex", flexWrap: "wrap", gap: "1.25rem", marginTop: "1rem",
          fontSize: "0.8125rem",
        }}>
          <div style={{ display: "flex", alignItems: "center", gap: "0.375rem", color: "#4b5a6e" }}>
            <div style={{ width: 6, height: 6, borderRadius: "50%", background: pm.dot }} />
            <span style={{ color: pm.color }}>{pm.label}</span>
            <span style={{ color: "#4b5a6e" }}>priority</span>
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: "0.375rem", color: "#4b5a6e" }}>
            <User size={12} />
            {ticket.assignee ? (
              <span style={{ color: "#8899b4" }}>{ticket.assignee.name}</span>
            ) : (
              <span style={{ color: "#4b5a6e", fontStyle: "italic" }}>Unassigned</span>
            )}
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: "0.375rem", color: "#4b5a6e" }}>
            <Clock size={12} />
            <span>{formatTime(ticket.created)}</span>
          </div>

          {ticket.tags.length > 0 && (
            <div style={{ display: "flex", alignItems: "center", gap: "0.375rem" }}>
              <Tag size={12} style={{ color: "#4b5a6e" }} />
              <div style={{ display: "flex", gap: "0.25rem" }}>
                {ticket.tags.map(tag => (
                  <span key={tag} style={{
                    background: "rgba(255,255,255,0.06)",
                    border: "1px solid rgba(255,255,255,0.08)",
                    color: "#8899b4",
                    fontSize: "0.6875rem",
                    padding: "0.125rem 0.4rem",
                    borderRadius: 4,
                    fontFamily: "'JetBrains Mono', monospace",
                  }}>
                    {tag}
                  </span>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Description */}
      <div style={{
        padding: "1.25rem 1.5rem",
        borderBottom: "1px solid rgba(255,255,255,0.06)",
        flexShrink: 0,
      }}>
        <p style={{ fontSize: "0.875rem", color: "#8899b4", lineHeight: 1.7, margin: 0 }}>
          {ticket.description}
        </p>
      </div>

      {/* Thread */}
      <div style={{ flex: 1, overflowY: "auto", padding: "1.25rem 1.5rem" }}>
        <p style={{
          fontSize: "0.6875rem", fontWeight: 600, color: "#4b5a6e",
          textTransform: "uppercase", letterSpacing: "0.07em", marginBottom: "1rem"
        }}>
          Conversation · {ticket.messages.length} {ticket.messages.length === 1 ? "message" : "messages"}
        </p>

        <div style={{ display: "flex", flexDirection: "column", gap: "1.25rem" }}>
          {ticket.messages.map((msg) => {
            const isAgent = msg.author.role === "agent";
            return (
              <div key={msg.id} style={{ display: "flex", gap: "0.75rem", alignItems: "flex-start" }}>
                <div style={{
                  width: 32, height: 32, borderRadius: "50%", flexShrink: 0,
                  background: msg.author.color + "22",
                  border: `1px solid ${msg.author.color}44`,
                  display: "flex", alignItems: "center", justifyContent: "center",
                  fontSize: "0.625rem", fontWeight: 700, color: msg.author.color,
                  marginTop: "0.125rem",
                }}>
                  {msg.author.initials}
                </div>

                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", marginBottom: "0.375rem" }}>
                    <span style={{ fontSize: "0.8125rem", fontWeight: 500, color: "#f0f4ff" }}>
                      {msg.author.name}
                    </span>
                    {isAgent && (
                      <span style={{
                        background: "rgba(0, 224, 122, 0.12)",
                        color: "#00e07a",
                        fontSize: "0.625rem",
                        fontWeight: 500,
                        padding: "0.1rem 0.4rem",
                        borderRadius: 3,
                        border: "1px solid rgba(0, 224, 122, 0.2)",
                      }}>
                        Agent
                      </span>
                    )}
                    {msg.internal && (
                      <span style={{
                        background: "rgba(245,158,11,0.12)",
                        color: "#f59e0b",
                        fontSize: "0.625rem",
                        fontWeight: 500,
                        padding: "0.1rem 0.4rem",
                        borderRadius: 3,
                        display: "flex", alignItems: "center", gap: "0.2rem",
                      }}>
                        <Lock size={8} /> Internal
                      </span>
                    )}
                    <span style={{ fontSize: "0.6875rem", color: "#4b5a6e", marginLeft: "auto" }}>
                      {formatTime(msg.timestamp)}
                    </span>
                  </div>

                  <div style={{
                    background: isAgent ? "rgba(0, 224, 122, 0.07)" : "rgba(255,255,255,0.04)",
                    border: isAgent ? "1px solid rgba(0, 224, 122, 0.15)" : "1px solid rgba(255,255,255,0.07)",
                    borderRadius: "0.75rem",
                    padding: "0.875rem 1rem",
                  }}>
                    <p style={{ fontSize: "0.875rem", color: "#c8d4e8", lineHeight: 1.7, margin: 0 }}>
                      {msg.body}
                    </p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Reply box */}
      <div style={{
        borderTop: "1px solid rgba(255,255,255,0.07)",
        padding: "1rem 1.5rem",
        flexShrink: 0,
        background: "rgba(0,0,0,0.2)",
      }}>
        <div style={{
          background: "rgba(255,255,255,0.04)",
          border: `1px solid ${internal ? "rgba(245,158,11,0.2)" : "rgba(255,255,255,0.09)"}`,
          borderRadius: "0.75rem",
          overflow: "hidden",
          transition: "border-color 0.2s",
        }}>
          <textarea
            value={reply}
            onChange={e => setReply(e.target.value)}
            placeholder={internal ? "Internal note (only visible to agents)…" : "Write a reply…"}
            rows={3}
            style={{
              width: "100%",
              background: "transparent",
              border: "none",
              color: "#f0f4ff",
              fontSize: "0.875rem",
              padding: "0.875rem 1rem 0.5rem",
              resize: "none",
              outline: "none",
              lineHeight: 1.6,
            }}
          />
          <div style={{
            display: "flex", alignItems: "center", justifyContent: "space-between",
            padding: "0.5rem 0.875rem 0.75rem",
          }}>
            <button
              onClick={() => setInternal(v => !v)}
              style={{
                display: "flex", alignItems: "center", gap: "0.35rem",
                background: internal ? "rgba(245,158,11,0.12)" : "transparent",
                border: internal ? "1px solid rgba(245,158,11,0.25)" : "1px solid rgba(255,255,255,0.08)",
                color: internal ? "#f59e0b" : "#4b5a6e",
                borderRadius: "0.375rem",
                padding: "0.25rem 0.5rem",
                fontSize: "0.6875rem",
                fontWeight: 500,
                cursor: "pointer",
              }}
            >
              <Lock size={10} />
              Internal note
            </button>

            <button
              onClick={handleReply}
              disabled={!reply.trim()}
              style={{
                background: reply.trim() ? "#00e07a" : "rgba(255,255,255,0.05)",
                color: reply.trim() ? "#09090b" : "rgba(255,255,255,0.3)",
                border: reply.trim() ? "1px solid #00e07a" : "1px solid rgba(255,255,255,0.1)",
                padding: "0.5rem 1rem", borderRadius: "0.375rem",
                display: "flex", alignItems: "center", gap: "0.375rem",
                fontSize: "0.8125rem", fontWeight: 600, cursor: reply.trim() ? "pointer" : "not-allowed",
                transition: "all 0.2s",
                boxShadow: reply.trim() ? "0 2px 12px rgba(0,224,122,0.3)" : "none",
              }}
            >
              <Send size={13} />
              Send Reply
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
