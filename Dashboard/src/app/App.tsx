import { useState } from "react";
import { TicketList } from "./components/tickets/TicketList";
import { TicketDetail } from "./components/tickets/TicketDetail";
import { NewTicketModal } from "./components/tickets/NewTicketModal";
import { AnalyticsDashboard } from "./components/tickets/AnalyticsDashboard";
import { ErrorBoundary } from "./components/ErrorBoundary";
import { TICKETS, Ticket, Status, Priority } from "./components/tickets/data";
import { Headphones, BarChart2, Bell, LayoutList, ArrowLeft, Search } from "lucide-react";

type Page = "analytics" | "tickets";

declare global {
  interface Window {
    __INITIAL_DATA__: Ticket[];
    __AGENTS__: { id: number, name: string, initials: string, color: string }[];
    __ROLE__?: "agent" | "client";
    __API_ROUTE__?: string;
    __CSRF_TOKEN__: string;
  }
}

export default function App() {
  const role = typeof window !== 'undefined' && window.__ROLE__ ? window.__ROLE__ : "agent";
  const apiRoute = typeof window !== 'undefined' && window.__API_ROUTE__ ? window.__API_ROUTE__ : "platform_support";
  
  const [page, setPage] = useState<Page>(role === "client" ? "tickets" : "analytics");
  const initialTickets = typeof window !== 'undefined' && window.__INITIAL_DATA__ ? window.__INITIAL_DATA__ : TICKETS;
  const [tickets, setTickets] = useState<Ticket[]>(initialTickets);
  const [selectedId, setSelectedId] = useState<string | null>(initialTickets.length > 0 ? initialTickets[0].id : null);
  const [filterStatus, setFilterStatus] = useState<Status | "all">("all");
  const [showNew, setShowNew] = useState(false);
  const [mobileView, setMobileView] = useState<"list" | "detail">("list");
  const [selectedTicketIds, setSelectedTicketIds] = useState<string[]>([]);
  const [globalSearch, setGlobalSearch] = useState("");

  const displayTickets = tickets.filter(t => {
    if (!globalSearch.trim()) return true;
    const q = globalSearch.toLowerCase();
    return t.title.toLowerCase().includes(q) ||
           t.description.toLowerCase().includes(q) ||
           t.id.toLowerCase().includes(q) ||
           t.tags.some(tag => tag.toLowerCase().includes(q));
  });

  const selected = tickets.find(t => t.id === selectedId) ?? null;

  const handleStatusChange = async (id: string, status: Status) => {
    setTickets(prev => prev.map(t => t.id === id ? { ...t, status, updated: new Date().toISOString() } : t));
    const ticketId = id.replace("TKT-", "");
    await fetch(`../api/index.php?route=${apiRoute}&action=update_ticket`, {
      method: "POST", headers: { "Content-Type": "application/json", "X-CSRF-Token": window.__CSRF_TOKEN__ || "" },
      body: JSON.stringify({ ticket_id: ticketId, status })
    });
  };

  const handlePriorityChange = async (id: string, priority: Priority) => {
    setTickets(prev => prev.map(t => t.id === id ? { ...t, priority, updated: new Date().toISOString() } : t));
    const ticketId = id.replace("TKT-", "");
    await fetch(`../api/index.php?route=${apiRoute}&action=update_ticket`, {
      method: "POST", headers: { "Content-Type": "application/json", "X-CSRF-Token": window.__CSRF_TOKEN__ || "" },
      body: JSON.stringify({ ticket_id: ticketId, priority })
    });
  };

  const handleAssigneeChange = async (id: string, assigneeId: number | null) => {
    const agents = window.__AGENTS__ || [];
    const agent = agents.find(a => a.id === assigneeId);
    
    setTickets(prev => prev.map(t => t.id === id ? { 
      ...t, 
      assignee: agent ? { name: agent.name, initials: agent.initials, color: agent.color } : null,
      updated: new Date().toISOString() 
    } : t));
    
    const ticketId = id.replace("TKT-", "");
    await fetch(`../api/index.php?route=${apiRoute}&action=update_ticket`, {
      method: "POST", headers: { "Content-Type": "application/json", "X-CSRF-Token": window.__CSRF_TOKEN__ || "" },
      body: JSON.stringify({ ticket_id: ticketId, assigned_to: assigneeId })
    });
  };

  const handleAddTag = async (id: string, tag: string) => {
    setTickets(prev => prev.map(t => {
      if (t.id === id && !t.tags.includes(tag)) {
        return { ...t, tags: [...t.tags, tag] };
      }
      return t;
    }));
    
    const ticketId = id.replace("TKT-", "");
    await fetch(`../api/index.php?route=${apiRoute}&action=add_ticket_tag`, {
      method: "POST", headers: { "Content-Type": "application/json", "X-CSRF-Token": window.__CSRF_TOKEN__ || "" },
      body: JSON.stringify({ ticket_id: ticketId, tag })
    });
  };

  const handleRemoveTag = async (id: string, tag: string) => {
    setTickets(prev => prev.map(t => {
      if (t.id === id) {
        return { ...t, tags: t.tags.filter(t => t !== tag) };
      }
      return t;
    }));
    
    const ticketId = id.replace("TKT-", "");
    await fetch(`../api/index.php?route=${apiRoute}&action=remove_ticket_tag`, {
      method: "POST", headers: { "Content-Type": "application/json", "X-CSRF-Token": window.__CSRF_TOKEN__ || "" },
      body: JSON.stringify({ ticket_id: ticketId, tag })
    });
  };

  const handleBulkAction = async (action: string, value: string = "") => {
    if (selectedTicketIds.length === 0) return;

    const rawIds = selectedTicketIds.map(id => id.replace("TKT-", ""));
    await fetch(`../api/index.php?route=${apiRoute}&action=bulk_action`, {
      method: "POST", headers: { "Content-Type": "application/json", "X-CSRF-Token": window.__CSRF_TOKEN__ || "" },
      body: JSON.stringify({ ticket_ids: rawIds, action, value })
    });

    if (action === "resolve" || action === "close") {
      setTickets(prev => prev.map(t => selectedTicketIds.includes(t.id) ? { ...t, status: action as Status, updated: new Date().toISOString() } : t));
    } else if (action === "assign") {
      const agents = window.__AGENTS__ || [];
      const agent = agents.find(a => a.id === parseInt(value));
      setTickets(prev => prev.map(t => selectedTicketIds.includes(t.id) ? { 
        ...t, 
        assignee: agent ? { name: agent.name, initials: agent.initials, color: agent.color } : null,
        updated: new Date().toISOString() 
      } : t));
    }

    setSelectedTicketIds([]); // Clear selection after action
  };

  const handleCSAT = async (ticketId: string, score: number, comment: string) => {
    await fetch(`../api/index.php?route=${apiRoute}&action=submit_csat`, {
      method: "POST", headers: { "Content-Type": "application/json", "X-CSRF-Token": window.__CSRF_TOKEN__ || "" },
      body: JSON.stringify({ ticket_id: ticketId, score, comment })
    });
  };

  const handleAddMessage = (ticketId: string, body: string, internal: boolean, attachments: any[]) => {
    const newMsg = {
      id: "m" + Date.now(),
      author: { name: "You", initials: "YO", color: role === "client" ? "#3b82f6" : "#ec4899", role },
      body,
      timestamp: new Date().toISOString(),
      internal,
      attachments
    };
    setTickets(prev => prev.map(t => t.id === ticketId ? {
      ...t,
      updated: new Date().toISOString(),
      messages: [...t.messages, newMsg]
    } : t));
  };

  const handleCreate = (data: { title: string; description: string; priority: Priority; category: string }) => {
    const num = Math.max(...tickets.map(t => parseInt(t.id.replace("TKT-", "")))) + 1;
    const now = new Date().toISOString();
    const newTicket: Ticket = {
      id: `TKT-${num}`,
      title: data.title,
      description: data.description,
      status: "open",
      priority: data.priority,
      category: data.category,
      assignee: null,
      reporter: { name: "You", initials: "YO", color: "#3b82f6" },
      created: now,
      updated: now,
      messages: [],
      tags: [],
    };
    setTickets(prev => [newTicket, ...prev]);
    setSelectedId(newTicket.id);
    setPage("tickets");
    setMobileView("detail");
  };

  const openCritical = tickets.filter(t => t.priority === "critical" && t.status !== "resolved" && t.status !== "closed").length;
  const openCount = tickets.filter(t => t.status === "open").length;

  let navItems: { key: Page; label: string; icon: React.ReactNode }[] = [
    { key: "tickets",   label: "Tickets",   icon: <LayoutList size={15} /> },
  ];
  if (role === "agent") {
    navItems.unshift({ key: "analytics", label: "Analytics", icon: <BarChart2 size={15} /> });
  }

  return (
    <div style={{
      minHeight: "100vh",
      background: "linear-gradient(160deg, #080d14 0%, #0d1117 40%, #0f1420 100%)",
      fontFamily: "'Inter', system-ui, sans-serif",
      color: "#f0f4ff",
      display: "flex",
      flexDirection: "column",
      position: "relative",
      zIndex: 0,
    }}>
      {/* Global Background Glow Effects */}
      <div style={{ position: "absolute", top: -100, left: -100, width: 500, height: 500, borderRadius: "50%", background: "#00e07a", filter: "blur(120px)", opacity: 0.06, pointerEvents: "none", zIndex: -1 }} />
      <div style={{ position: "absolute", bottom: -150, right: -100, width: 600, height: 600, borderRadius: "50%", background: "#9b6dff", filter: "blur(140px)", opacity: 0.05, pointerEvents: "none", zIndex: -1 }} />
      {/* Top nav */}
      <header style={{
        borderBottom: "1px solid rgba(255,255,255,0.07)",
        padding: "0 1.5rem",
        height: 56,
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        background: "rgba(8,13,20,0.9)",
        backdropFilter: "blur(12px)",
        position: "sticky",
        top: 0,
        zIndex: 30,
        flexShrink: 0,
      }}>
        {/* Left: logo + nav */}
        <div style={{ display: "flex", alignItems: "center", gap: "1.75rem" }}>
          <div style={{ display: "flex", alignItems: "center", gap: "0.625rem" }}>
            <a href="dashboard.php" style={{ 
              color: "#8899b4", textDecoration: "none", display: "flex", alignItems: "center", gap: "0.5rem",
              background: "rgba(255,255,255,0.05)", padding: "0.3rem 0.75rem", borderRadius: "0.5rem",
              fontSize: "0.875rem", transition: "background 0.2s"
            }}
            onMouseEnter={(e) => e.currentTarget.style.background = "rgba(255,255,255,0.1)"}
            onMouseLeave={(e) => e.currentTarget.style.background = "rgba(255,255,255,0.05)"}>
              <ArrowLeft size={14} /> Back to Hub
            </a>
            <span style={{ color: "rgba(255,255,255,0.15)", fontSize: "0.875rem", margin: "0 0.25rem" }}>|</span>
            <div style={{
              width: 30, height: 30, borderRadius: "0.5rem",
              background: "linear-gradient(135deg, #00e07a, #00b8ff)",
              display: "flex", alignItems: "center", justifyContent: "center",
            }}>
              <Headphones size={15} color="white" />
            </div>
            <span style={{ fontWeight: 600, fontSize: "0.9375rem" }}>Support</span>

            {/* Global Search */}
            <div style={{ position: "relative", marginLeft: "1rem" }}>
              <Search size={13} style={{ position: "absolute", left: "0.75rem", top: "50%", transform: "translateY(-50%)", color: "#8899b4" }} />
              <input 
                type="text" 
                placeholder="Search globally..." 
                value={globalSearch}
                onChange={e => { setGlobalSearch(e.target.value); setPage("tickets"); }}
                style={{
                  background: "rgba(255,255,255,0.04)", border: "1px solid rgba(255,255,255,0.08)",
                  borderRadius: "99px", padding: "0.35rem 1rem 0.35rem 2rem", color: "#f0f4ff",
                  fontSize: "0.8125rem", width: "200px", outline: "none", transition: "all 0.2s"
                }}
                onFocus={e => e.currentTarget.style.background = "rgba(255,255,255,0.08)"}
                onBlur={e => e.currentTarget.style.background = "rgba(255,255,255,0.04)"}
              />
            </div>
          </div>

          {/* Nav tabs */}
          <nav style={{ display: "flex", alignItems: "center", gap: "0.125rem" }}>
            {navItems.map(item => {
              const active = page === item.key;
              return (
                <button
                  key={item.key}
                  onClick={() => setPage(item.key)}
                  style={{
                    display: "flex", alignItems: "center", gap: "0.4rem",
                    background: active ? "rgba(255,255,255,0.07)" : "transparent",
                    border: "none",
                    color: active ? "#f0f4ff" : "#4b5a6e",
                    borderRadius: "0.5rem",
                    padding: "0.375rem 0.75rem",
                    fontSize: "0.8125rem",
                    fontWeight: active ? 500 : 400,
                    cursor: "pointer",
                    transition: "all 0.15s ease",
                    position: "relative",
                  }}
                  onMouseEnter={e => { if (!active) e.currentTarget.style.color = "#8899b4"; }}
                  onMouseLeave={e => { if (!active) e.currentTarget.style.color = "#4b5a6e"; }}
                >
                  {item.icon}
                  {item.label}
                  {item.key === "tickets" && openCount > 0 && (
                    <span style={{
                      background: "rgba(0,224,122,0.15)",
                      color: "#00e07a",
                      fontSize: "0.625rem",
                      fontFamily: "'JetBrains Mono', monospace",
                      padding: "0.05rem 0.375rem",
                      borderRadius: 99,
                      border: "1px solid rgba(0,224,122,0.25)",
                      marginLeft: "0.125rem",
                    }}>{openCount}</span>
                  )}
                </button>
              );
            })}
          </nav>
        </div>

        {/* Right: status + avatar */}
        <div style={{ display: "flex", alignItems: "center", gap: "1rem" }}>
          {openCritical > 0 && (
            <div style={{ display: "flex", alignItems: "center", gap: "0.375rem" }}>
              <div style={{ width: 6, height: 6, borderRadius: "50%", background: "#ef4444", animation: "pulse 2s infinite" }} />
              <span style={{ fontSize: "0.8125rem", color: "#ef4444" }}>
                <span style={{ fontFamily: "'JetBrains Mono', monospace" }}>{openCritical}</span> critical
              </span>
            </div>
          )}

          <button style={{
            background: "transparent", border: "1px solid rgba(255,255,255,0.08)",
            color: "#4b5a6e", borderRadius: "0.5rem",
            width: 30, height: 30, display: "flex", alignItems: "center", justifyContent: "center",
            cursor: "pointer",
          }}>
            <Bell size={14} />
          </button>

          <div style={{
            width: 30, height: 30, borderRadius: "50%",
            background: "linear-gradient(135deg, #00e07a, #00b8ff)",
            display: "flex", alignItems: "center", justifyContent: "center",
            fontSize: "0.6875rem", fontWeight: 700, color: "#0b0f1a",
          }}>
            {(typeof window !== 'undefined' && window.__USER_INITIALS__) || 'RL'}
          </div>
        </div>
      </header>

      {/* Page content */}
      {page === "analytics" ? (
        <div style={{ flex: 1, overflowY: "auto" }}>
          <AnalyticsDashboard tickets={tickets} />
        </div>
      ) : (
        <div style={{
          flex: 1,
          display: "grid",
          gridTemplateColumns: "300px 1fr",
          overflow: "hidden",
          height: "calc(100vh - 56px)",
        }}>
          <div className="ticket-list-panel" style={{ overflow: "hidden", display: "flex", flexDirection: "column" }}>
            <ErrorBoundary>
              <TicketList
                tickets={displayTickets}
                selectedId={selectedId}
                onSelect={(id) => { setSelectedId(id); setMobileView("detail"); }}
                onNew={() => setShowNew(true)}
                filterStatus={filterStatus}
                onFilterStatus={setFilterStatus}
                selectedTicketIds={selectedTicketIds}
                onToggleSelect={(id) => setSelectedTicketIds(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id])}
                onToggleSelectAll={() => setSelectedTicketIds(prev => prev.length === tickets.length ? [] : tickets.map(t => t.id))}
                onBulkAction={handleBulkAction}
              />
            </ErrorBoundary>
          </div>

          <div className="ticket-detail-panel" style={{
            overflow: "hidden",
            display: "flex",
            flexDirection: "column",
            background: "rgba(255,255,255,0.015)",
          }}>
            <ErrorBoundary>
              {selected ? (
                <TicketDetail
                  ticket={selected}
                  onBack={mobileView === "detail" ? () => setMobileView("list") : undefined}
                  onStatusChange={handleStatusChange}
                  onPriorityChange={handlePriorityChange}
                  onAssigneeChange={handleAssigneeChange}
                  onAddTag={handleAddTag}
                  onRemoveTag={handleRemoveTag}
                  onAddMessage={handleAddMessage}
                  onSubmitCSAT={handleCSAT}
                />
              ) : (
                <div style={{
                  flex: 1, display: "flex", alignItems: "center", justifyContent: "center",
                  flexDirection: "column", gap: "0.75rem", color: "#4b5a6e",
                }}>
                  <LayoutList size={28} style={{ opacity: 0.25 }} />
                  <p style={{ fontSize: "0.875rem" }}>Select a ticket to view details</p>
                </div>
              )}
            </ErrorBoundary>
          </div>
        </div>
      )}

      {showNew && (
        <NewTicketModal onClose={() => setShowNew(false)} onCreate={handleCreate} />
      )}

      <style>{`
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        * { box-sizing: border-box; }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 99px; }
        input, textarea, select { font-family: 'Inter', system-ui, sans-serif; }
        @media (max-width: 700px) {
          .ticket-list-panel { display: ${mobileView === "list" ? "flex" : "none"} !important; }
          .ticket-detail-panel { display: ${mobileView === "detail" ? "flex" : "none"} !important; }
          div[style*="300px 1fr"] { grid-template-columns: 1fr !important; }
        }
      `}</style>
    </div>
  );
}
