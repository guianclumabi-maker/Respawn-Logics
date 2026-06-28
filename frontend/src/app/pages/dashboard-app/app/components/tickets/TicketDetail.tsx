import { apiFetch } from "../../../../../lib/apiClient";
import { useState, useEffect, useRef } from "react";
import { Ticket, STATUS_META, PRIORITY_META, Status, Priority } from "./data";
import { Tag as TagIcon, Clock, User, Send, Lock, ArrowLeft, CheckCircle2, CircleDot, Hourglass, XCircle, RefreshCw, ChevronDown, Plus, X, Zap, Paperclip, Star, Loader2, FileText } from "lucide-react";

interface TicketDetailProps {
  ticket: Ticket;
  onBack?: () => void;
  onStatusChange: (id: string, status: Status) => void;
  onPriorityChange?: (id: string, priority: Priority) => void;
  onAssigneeChange?: (id: string, assigneeId: number | null) => void;
  onAddTag?: (id: string, tag: string) => void;
  onRemoveTag?: (id: string, tag: string) => void;
  onAddMessage?: (id: string, body: string, internal: boolean, attachments: any[]) => void;
  onSubmitCSAT?: (id: string, score: number, comment: string) => void;
  isClient?: boolean;
  cannedResponses?: any[];
}

declare global {
  interface Window {
    __AGENTS__: Array<{ id: number, name: string, initials: string, color: string }>;
  }
}

function formatTime(iso: string) {
  const d = new Date(iso);
  return d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) +
    " · " + d.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit" });
}

const STATUS_ACTIONS: {
  key: Status;
  label: string;
  icon: React.ReactNode;
  color: string;
  bg: string;
  border: string;
  hoverBg: string;
}[] = [
  {
    key: "open",
    label: "Open",
    icon: <CircleDot size={13} />,
    color: "#3b82f6",
    bg: "rgba(59,130,246,0.1)",
    border: "rgba(59,130,246,0.25)",
    hoverBg: "rgba(59,130,246,0.18)",
  },
  {
    key: "in_progress",
    label: "In Progress",
    icon: <RefreshCw size={13} />,
    color: "#f59e0b",
    bg: "rgba(245,158,11,0.1)",
    border: "rgba(245,158,11,0.25)",
    hoverBg: "rgba(245,158,11,0.18)",
  },
  {
    key: "waiting",
    label: "Waiting",
    icon: <Hourglass size={13} />,
    color: "#8b5cf6",
    bg: "rgba(139,92,246,0.1)",
    border: "rgba(139,92,246,0.25)",
    hoverBg: "rgba(139,92,246,0.18)",
  },
  {
    key: "resolved",
    label: "Resolved",
    icon: <CheckCircle2 size={13} />,
    color: "#10b981",
    bg: "rgba(16,185,129,0.1)",
    border: "rgba(16,185,129,0.25)",
    hoverBg: "rgba(16,185,129,0.18)",
  },
  {
    key: "closed",
    label: "Closed",
    icon: <XCircle size={13} />,
    color: "#4b5a6e",
    bg: "rgba(75,90,110,0.1)",
    border: "rgba(75,90,110,0.25)",
    hoverBg: "rgba(75,90,110,0.18)",
  },
];

export function TicketDetail({ ticket, onBack, onStatusChange, onPriorityChange, onAssigneeChange, onAddTag, onRemoveTag, onAddMessage, onSubmitCSAT, isClient = false, cannedResponses = [] }: TicketDetailProps) {
  const [reply, setReply] = useState("");
  const [internal, setInternal] = useState(false);
  const [hovered, setHovered] = useState<Status | null>(null);
  const [addingTag, setAddingTag] = useState(false);
  const [newTag, setNewTag] = useState("");
  const [pendingAttachments, setPendingAttachments] = useState<{name: string, url: string}[]>([]);
  const [csatScore, setCsatScore] = useState(0);
  const [csatComment, setCsatComment] = useState("");
  const [uploading, setUploading] = useState(false);
  const [showCanned, setShowCanned] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  
  const sm = STATUS_META[ticket.status];
  const pm = PRIORITY_META[ticket.priority];
  
  const agents = window.__AGENTS__ || [];

  const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (!files || files.length === 0) return;
    
    setUploading(true);
    for (let i = 0; i < files.length; i++) {
      const formData = new FormData();
      formData.append("attachment", files[i]);
      
      try {
        const res = await fetch("../api/index.php?route=platform_support&action=upload_attachment", {
          method: "POST",

          body: formData
        });
        const data = await res.json();
        if (data.success) {
          setPendingAttachments(prev => [...prev, { name: data.name, url: data.url }]);
        }
      } catch (err) {
        console.error("Upload failed", err);
      }
    }
    setUploading(false);
    if (fileInputRef.current) fileInputRef.current.value = "";
  };

  const submitReply = async () => {
    if (!reply.trim() && pendingAttachments.length === 0) return;
    
    const bodyText = reply.trim() ? (internal ? `[SYSTEM] ${reply}` : reply) : (internal ? `[SYSTEM] Attached files.` : `Attached files.`);
    
    const ticketIdStr = ticket.id.replace("TKT-", "");
    await fetch("../api/index.php?route=platform_support&action=add_comment", {
      method: "POST", headers: { "Content-Type": "application/json", },
      body: JSON.stringify({ ticket_id: ticketIdStr, comment: bodyText, attachments: pendingAttachments })
    });
    
    if (onAddMessage) onAddMessage(ticket.id, bodyText, internal, pendingAttachments);
    
    setReply("");
    setPendingAttachments([]);
    setInternal(false);
  };

  const removeAttachment = (idx: number) => {
    setPendingAttachments(prev => prev.filter((_, i) => i !== idx));
  };

  const insertCannedResponse = (content: string) => {
    setReply(prev => prev + content);
  };

  const submitNewTag = () => {
    if (newTag.trim() && onAddTag) {
      onAddTag(ticket.id, newTag.trim());
    }
    setNewTag("");
    setAddingTag(false);
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

        <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", gap: "1rem", marginBottom: "1rem" }}>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", marginBottom: "0.375rem" }}>
              <span style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: "0.75rem", color: "#4b5a6e" }}>
                {ticket.id}
              </span>
              <span style={{ color: "#4b5a6e" }}>·</span>
              <span style={{ fontSize: "0.75rem", color: "#4b5a6e" }}>{ticket.category}</span>
            </div>
            <h1 style={{ fontSize: "1.125rem", fontWeight: 600, color: "#f0f4ff", lineHeight: 1.4, margin: 0 }}>
              {ticket.title}
            </h1>
          </div>

          {/* CSAT and Status badge */}
          <div style={{ display: "flex", alignItems: "center", gap: "0.75rem", flexShrink: 0 }}>
            {ticket.csat_score !== undefined && ticket.csat_score !== null && (
              <div style={{
                display: "flex", alignItems: "center", gap: "0.25rem",
                background: "linear-gradient(135deg, rgba(245,158,11,0.1), rgba(245,158,11,0.05))",
                border: "1px solid rgba(245,158,11,0.2)",
                padding: "0.25rem 0.5rem", borderRadius: "0.5rem",
                cursor: "default",
              }} title={ticket.csat_comment || "No comment provided"}>
                {[1, 2, 3, 4, 5].map(star => (
                  <Star key={star} size={13} fill={star <= ticket.csat_score! ? "#f59e0b" : "transparent"} color={star <= ticket.csat_score! ? "#f59e0b" : "rgba(245,158,11,0.3)"} />
                ))}
              </div>
            )}
            <span style={{
              background: sm.bg,
              border: `1px solid ${sm.border}`,
              color: sm.color,
              borderRadius: "0.5rem",
              padding: "0.3rem 0.75rem",
              fontSize: "0.75rem",
              fontWeight: 500,
            }}>
              {sm.label}
            </span>
          </div>
        </div>

        {/* Status action buttons */}
        {!isClient && (
        <div style={{
          background: "rgba(255,255,255,0.03)",
          border: "1px solid rgba(255,255,255,0.07)",
          borderRadius: "0.875rem",
          padding: "0.75rem 1rem",
          marginBottom: "0.75rem",
        }}>
          <p style={{ fontSize: "0.6875rem", color: "#4b5a6e", fontWeight: 500, textTransform: "uppercase", letterSpacing: "0.07em", marginBottom: "0.625rem" }}>
            Set Status
          </p>
          <div style={{ display: "flex", gap: "0.4rem", flexWrap: "wrap" }}>
            {STATUS_ACTIONS.map(action => {
              const isActive = ticket.status === action.key;
              const isHovered = hovered === action.key;
              return (
                <button
                  key={action.key}
                  onClick={() => !isActive && onStatusChange(ticket.id, action.key)}
                  onMouseEnter={() => setHovered(action.key)}
                  onMouseLeave={() => setHovered(null)}
                  style={{
                    display: "flex", alignItems: "center", gap: "0.375rem",
                    background: isActive ? action.bg : isHovered ? action.hoverBg : "rgba(255,255,255,0.04)",
                    border: isActive
                      ? `1px solid ${action.border}`
                      : isHovered
                      ? `1px solid ${action.border}`
                      : "1px solid rgba(255,255,255,0.08)",
                    color: isActive ? action.color : isHovered ? action.color : "#8899b4",
                    borderRadius: "0.5rem",
                    padding: "0.4rem 0.75rem",
                    fontSize: "0.8125rem",
                    fontWeight: isActive ? 500 : 400,
                    cursor: isActive ? "default" : "pointer",
                    transition: "all 0.15s ease",
                    boxShadow: isActive ? `0 0 0 1px ${action.border}` : "none",
                  }}
                >
                  {action.icon}
                  {action.label}
                  {isActive && (
                    <span style={{
                      width: 5, height: 5, borderRadius: "50%",
                      background: action.color,
                      marginLeft: "0.1rem",
                      boxShadow: `0 0 5px ${action.color}`,
                    }} />
                  )}
                </button>
              );
            })}
          </div>
        </div>
        )}

        {/* Meta row */}
        <div style={{ display: "flex", flexWrap: "wrap", gap: "1.25rem", fontSize: "0.8125rem" }}>
          <div style={{ position: "relative", display: "flex", alignItems: "center", gap: "0.375rem", color: "#4b5a6e", cursor: "pointer", padding: "0.2rem 0.5rem", borderRadius: "0.25rem", background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.05)" }}>
            <div style={{ width: 6, height: 6, borderRadius: "50%", background: pm.dot }} />
            <span style={{ color: pm.color }}>{pm.label}</span>
            <ChevronDown size={12} style={{ opacity: 0.5 }} />
            <select
              value={ticket.priority}
              disabled={isClient}
              onChange={(e) => onPriorityChange && onPriorityChange(ticket.id, e.target.value as Priority)}
              style={{
                position: "absolute", top: 0, left: 0, width: "100%", height: "100%",
                opacity: 0, cursor: isClient ? "default" : "pointer", appearance: "none"
              }}
            >
              {Object.entries(PRIORITY_META).map(([k, v]) => (
                <option key={k} value={k} style={{ color: '#000' }}>{v.label}</option>
              ))}
            </select>
          </div>

          <div style={{ position: "relative", display: "flex", alignItems: "center", gap: "0.375rem", color: "#4b5a6e", cursor: "pointer", padding: "0.2rem 0.5rem", borderRadius: "0.25rem", background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.05)" }}>
            <User size={12} />
            {ticket.assignee
              ? <span style={{ color: "#8899b4" }}>{ticket.assignee.name}</span>
              : <span style={{ fontStyle: "italic" }}>Unassigned</span>
            }
            <ChevronDown size={12} style={{ opacity: 0.5 }} />
            <select
              value={ticket.assignee ? agents.find(a => a.name === ticket.assignee!.name)?.id || "" : ""}
              disabled={isClient}
              onChange={(e) => onAssigneeChange && onAssigneeChange(ticket.id, e.target.value ? parseInt(e.target.value) : null)}
              style={{
                position: "absolute", top: 0, left: 0, width: "100%", height: "100%",
                opacity: 0, cursor: isClient ? "default" : "pointer", appearance: "none"
              }}
            >
              <option value="" style={{ color: '#000' }}>Unassigned</option>
              {agents.map(a => (
                <option key={a.id} value={a.id} style={{ color: '#000' }}>{a.name}</option>
              ))}
            </select>
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: "0.375rem", color: "#4b5a6e" }}>
            <Clock size={12} />
            <span>{formatTime(ticket.created)}</span>
          </div>

          {ticket.sla_breach_at && ticket.status !== "resolved" && ticket.status !== "closed" && (() => {
            const diff = new Date(ticket.sla_breach_at).getTime() - Date.now();
            const breached = diff < 0;
            const absDiff = Math.abs(diff);
            const h = Math.floor(absDiff / 3600000);
            const m = Math.floor((absDiff % 3600000) / 60000);
            return (
              <div style={{ display: "flex", alignItems: "center", gap: "0.375rem", color: breached ? "#ef4444" : "#f59e0b", background: breached ? "rgba(239,68,68,0.1)" : "rgba(245,158,11,0.1)", padding: "0.2rem 0.5rem", borderRadius: "0.25rem" }}>
                <Clock size={12} />
                <span style={{ fontWeight: 500 }}>SLA: {breached ? `Breached by ${h}h ${m}m` : `${h}h ${m}m left`}</span>
              </div>
            );
          })()}

          <div style={{ display: "flex", alignItems: "center", gap: "0.375rem" }}>
            <TagIcon size={12} style={{ color: "#4b5a6e" }} />
            {ticket.tags.map(tag => (
              <span key={tag} style={{
                background: "rgba(255,255,255,0.06)",
                border: "1px solid rgba(255,255,255,0.08)",
                color: "#8899b4",
                fontSize: "0.6875rem",
                padding: "0.125rem 0.4rem",
                borderRadius: 4,
                fontFamily: "'JetBrains Mono', monospace",
                display: "flex", alignItems: "center", gap: "0.25rem",
              }}>
                {tag}
                {!isClient && <X size={10} style={{ cursor: "pointer", opacity: 0.6 }} onClick={() => onRemoveTag && onRemoveTag(ticket.id, tag)} />}
              </span>
            ))}
            
            {!isClient && (addingTag ? (
              <input
                type="text"
                value={newTag}
                onChange={e => setNewTag(e.target.value)}
                onBlur={submitNewTag}
                onKeyDown={e => { if (e.key === 'Enter') submitNewTag(); else if (e.key === 'Escape') setAddingTag(false); }}
                autoFocus
                style={{
                  background: "rgba(255,255,255,0.05)", border: "1px solid rgba(255,255,255,0.1)",
                  color: "#f0f4ff", fontSize: "0.6875rem", padding: "0.125rem 0.4rem", borderRadius: 4,
                  width: "60px", outline: "none", fontFamily: "'JetBrains Mono', monospace"
                }}
              />
            ) : (
              <span 
                onClick={() => setAddingTag(true)}
                style={{
                  background: "transparent",
                  border: "1px dashed rgba(255,255,255,0.2)",
                  color: "#4b5a6e", cursor: "pointer",
                  fontSize: "0.6875rem", padding: "0.125rem 0.4rem", borderRadius: 4,
                  display: "flex", alignItems: "center"
                }}>
                <Plus size={10} />
              </span>
            ))}
          </div>
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
            const isSystem = msg.internal && msg.body.startsWith("[SYSTEM]");
            
            if (isSystem) {
              return (
                <div key={msg.id} style={{ display: "flex", justifyContent: "center", margin: "0.5rem 0" }}>
                  <div style={{
                    background: "rgba(255,255,255,0.03)",
                    border: "1px solid rgba(255,255,255,0.06)",
                    borderRadius: "99px",
                    padding: "0.3rem 0.8rem",
                    fontSize: "0.6875rem",
                    color: "#8899b4",
                    display: "flex", alignItems: "center", gap: "0.5rem"
                  }}>
                    <RefreshCw size={10} style={{ opacity: 0.6 }} />
                    <span>{msg.body.replace("[SYSTEM] ", "")}</span>
                    <span style={{ color: "#4b5a6e", borderLeft: "1px solid rgba(255,255,255,0.1)", paddingLeft: "0.5rem", marginLeft: "0.2rem" }}>
                      {formatTime(msg.timestamp)}
                    </span>
                  </div>
                </div>
              );
            }

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
                        background: "rgba(99,102,241,0.12)", color: "#6366f1",
                        fontSize: "0.625rem", fontWeight: 500,
                        padding: "0.1rem 0.4rem", borderRadius: 3,
                        border: "1px solid rgba(99,102,241,0.2)",
                      }}>
                        Agent
                      </span>
                    )}
                    {msg.internal && (
                      <span style={{
                        background: "rgba(245,158,11,0.12)", color: "#f59e0b",
                        fontSize: "0.625rem", fontWeight: 500,
                        padding: "0.1rem 0.4rem", borderRadius: 3,
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
                    background: isAgent ? "rgba(99,102,241,0.07)" : "rgba(255,255,255,0.04)",
                    border: isAgent ? "1px solid rgba(99,102,241,0.15)" : "1px solid rgba(255,255,255,0.07)",
                    borderRadius: "0.75rem",
                    padding: "0.875rem 1rem",
                  }}>
                    <p style={{ fontSize: "0.875rem", color: "#c8d4e8", lineHeight: 1.7, margin: 0, whiteSpace: "pre-wrap" }}>
                      {msg.body.replace("[SYSTEM] ", "")}
                    </p>
                    {msg.attachments && msg.attachments.length > 0 && (
                      <div style={{ display: "flex", flexWrap: "wrap", gap: "0.5rem", marginTop: "0.875rem", paddingTop: "0.875rem", borderTop: "1px dashed rgba(255,255,255,0.1)" }}>
                        {msg.attachments.map((att, i) => (
                          <a key={i} href={att.url} target="_blank" rel="noopener noreferrer" style={{
                            display: "flex", alignItems: "center", gap: "0.375rem", textDecoration: "none",
                            background: "rgba(255,255,255,0.05)", border: "1px solid rgba(255,255,255,0.1)",
                            padding: "0.35rem 0.625rem", borderRadius: "0.375rem", color: "#8899b4",
                            fontSize: "0.75rem", transition: "all 0.2s"
                          }} onMouseEnter={e => { e.currentTarget.style.color = "#f0f4ff"; e.currentTarget.style.background = "rgba(255,255,255,0.08)"; }} onMouseLeave={e => { e.currentTarget.style.color = "#8899b4"; e.currentTarget.style.background = "rgba(255,255,255,0.05)"; }}>
                            {att.url.match(/\.(jpeg|jpg|gif|png)$/i) ? <div style={{ width: 14, height: 14, borderRadius: 2, background: `url(${att.url}) center/cover` }} /> : <FileText size={12} />}
                            {att.name}
                          </a>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Reply box or CSAT */}
      {isClient && (ticket.status === 'resolved' || ticket.status === 'closed') && !ticket.csat_score ? (
        <div style={{
          borderTop: "1px solid rgba(255,255,255,0.07)",
          padding: "1.5rem",
          flexShrink: 0,
          background: "rgba(0,0,0,0.2)",
          display: "flex", flexDirection: "column", alignItems: "center"
        }}>
          <h3 style={{ color: "#f59e0b", margin: "0 0 0.5rem 0", fontSize: "1rem" }}>How did we do?</h3>
          <p style={{ color: "#9ca3af", fontSize: "0.85rem", margin: "0 0 1rem 0" }}>Please rate your support experience.</p>
          
          <div style={{ display: "flex", justifyContent: "center", gap: "0.5rem", marginBottom: "1rem" }}>
            {[1, 2, 3, 4, 5].map(star => (
              <button
                key={star}
                onClick={() => setCsatScore(star)}
                style={{
                  background: "transparent", border: "none", cursor: "pointer", padding: "0.5rem",
                  transition: "transform 0.2s", transform: csatScore === star ? "scale(1.2)" : "scale(1)"
                }}
              >
                <Star size={28} fill={star <= csatScore ? "#f59e0b" : "transparent"} color={star <= csatScore ? "#f59e0b" : "rgba(245, 158, 11, 0.3)"} />
              </button>
            ))}
          </div>

          {csatScore > 0 && (
            <div style={{ display: "flex", flexDirection: "column", gap: "0.75rem", width: "100%", maxWidth: "400px" }}>
              <textarea
                placeholder="Optional: Tell us more about your experience..."
                value={csatComment}
                onChange={e => setCsatComment(e.target.value)}
                style={{
                  width: "100%", background: "rgba(255,255,255,0.04)", border: "1px solid rgba(255,255,255,0.1)",
                  borderRadius: "0.5rem", padding: "0.75rem", color: "#f0f4ff", fontSize: "0.875rem",
                  minHeight: "80px", resize: "vertical", fontFamily: "inherit", outline: "none"
                }}
              />
              <button
                onClick={() => onSubmitCSAT && onSubmitCSAT(ticket.id, csatScore, csatComment)}
                style={{
                  background: "linear-gradient(135deg, #f59e0b, #d97706)", border: "none", color: "white",
                  padding: "0.75rem 1.5rem", borderRadius: "0.5rem", fontSize: "0.875rem", fontWeight: 600,
                  cursor: "pointer", alignSelf: "center"
                }}
              >
                Submit Feedback
              </button>
            </div>
          )}
        </div>
      ) : (
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
          
          {/* Pending Attachments preview */}
          {pendingAttachments.length > 0 && (
            <div style={{ display: "flex", flexWrap: "wrap", gap: "0.5rem", padding: "0 1rem 0.5rem" }}>
              {pendingAttachments.map((att, i) => (
                <div key={i} style={{
                  display: "flex", alignItems: "center", gap: "0.375rem",
                  background: "rgba(255,255,255,0.08)", border: "1px solid rgba(255,255,255,0.1)",
                  padding: "0.25rem 0.5rem", borderRadius: "0.375rem", color: "#c8d4e8",
                  fontSize: "0.75rem"
                }}>
                  <Paperclip size={10} />
                  {att.name}
                  <X size={10} style={{ cursor: "pointer", opacity: 0.6, marginLeft: "0.2rem" }} onClick={() => setPendingAttachments(prev => prev.filter((_, idx) => idx !== i))} />
                </div>
              ))}
            </div>
          )}
          <div style={{
            display: "flex", alignItems: "center", justifyContent: "space-between",
            padding: "0.5rem 0.875rem 0.75rem",
          }}>
            <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
              {!isClient && (
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
              )}

              <button
                onClick={() => fileInputRef.current?.click()}
                disabled={uploading}
                style={{
                  display: "flex", alignItems: "center", gap: "0.35rem",
                  background: "transparent",
                  border: "1px solid rgba(255,255,255,0.08)",
                  color: "#4b5a6e",
                  borderRadius: "0.375rem",
                  padding: "0.25rem 0.5rem",
                  fontSize: "0.6875rem",
                  fontWeight: 500,
                  cursor: uploading ? "not-allowed" : "pointer",
                }}
              >
                {uploading ? <Loader2 size={10} className="lucide-spin" /> : <Paperclip size={10} />}
                {uploading ? "Uploading..." : "Attach Files"}
              </button>
              <input type="file" ref={fileInputRef} onChange={handleFileUpload} multiple style={{ display: "none" }} />
            </div>

            {!isClient && (
            <div style={{ position: "relative" }}>
              <button
                onClick={() => setShowCanned(v => !v)}
                style={{
                  display: "flex", alignItems: "center", gap: "0.35rem",
                  background: "transparent",
                  border: "1px solid rgba(255,255,255,0.08)",
                  color: "#4b5a6e",
                  borderRadius: "0.375rem",
                  padding: "0.25rem 0.5rem",
                  fontSize: "0.6875rem",
                  fontWeight: 500,
                  cursor: "pointer",
                  marginLeft: "0.5rem"
                }}
              >
                <Zap size={10} />
                Canned Responses
              </button>
              {showCanned && (
                <div style={{
                  position: "absolute", bottom: "100%", left: "0.5rem", marginBottom: "0.5rem",
                  background: "#1e2430", border: "1px solid rgba(255,255,255,0.1)",
                  borderRadius: "0.5rem", width: "250px", maxHeight: "200px", overflowY: "auto",
                  boxShadow: "0 10px 25px rgba(0,0,0,0.5)", zIndex: 10
                }}>
                  {cannedResponses.map(cr => (
                    <div key={cr.title} onClick={() => { setReply(cr.content); setShowCanned(false); }} style={{
                      padding: "0.5rem 0.75rem", fontSize: "0.8125rem", color: "#c8d4e8",
                      borderBottom: "1px solid rgba(255,255,255,0.05)", cursor: "pointer",
                    }} onMouseEnter={e => e.currentTarget.style.background = "rgba(255,255,255,0.05)"} onMouseLeave={e => e.currentTarget.style.background = "transparent"}>
                      <div style={{ fontWeight: 500, marginBottom: "0.2rem" }}>{cr.title}</div>
                      <div style={{ fontSize: "0.6875rem", color: "#4b5a6e", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{cr.content}</div>
                    </div>
                  ))}
                  {cannedResponses.length === 0 && (
                    <div style={{ padding: "0.75rem", fontSize: "0.8125rem", color: "#4b5a6e", textAlign: "center" }}>No canned responses found.</div>
                  )}
                </div>
              )}
            </div>
            )}

            <button
              disabled={(!reply.trim() && pendingAttachments.length === 0) || uploading}
              onClick={submitReply}
              style={{
                display: "flex", alignItems: "center", gap: "0.375rem",
                background: (reply.trim() || pendingAttachments.length > 0) && !uploading ? "linear-gradient(135deg, #6366f1, #ec4899)" : "rgba(255,255,255,0.06)",
                border: "none",
                color: (reply.trim() || pendingAttachments.length > 0) && !uploading ? "#fff" : "#4b5a6e",
                borderRadius: "0.5rem",
                padding: "0.375rem 0.875rem",
                fontSize: "0.8125rem",
                fontWeight: 500,
                cursor: (reply.trim() || pendingAttachments.length > 0) && !uploading ? "pointer" : "not-allowed",
                transition: "all 0.2s ease",
                boxShadow: (reply.trim() || pendingAttachments.length > 0) && !uploading ? "0 2px 12px rgba(99,102,241,0.3)" : "none",
              }}
            >
              <Send size={13} />
              Send Reply
            </button>
          </div>
        </div>
      </div>
      )}
    </div>
  );
}
