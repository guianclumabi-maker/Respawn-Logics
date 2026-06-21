import { useState, useEffect } from "react";
import { TicketList } from "./components/tickets/TicketList";
import { TicketDetail } from "./components/tickets/TicketDetail";
import { NewTicketModal } from "./components/tickets/NewTicketModal";
import { AnalyticsDashboard } from "./components/analytics/AnalyticsDashboard";
import { GamifiedThemeToggle } from "./components/GamifiedThemeToggle";
import { TICKETS, Ticket, Status, Priority } from "./components/tickets/data";
import { Headphones, Bell, Settings, ArrowLeft, Search } from "lucide-react";
import { useAuth } from "../../../context/AuthContext";

const API_BASE = window.location.origin + (window.location.hostname === 'localhost' ? '/respawn-logics' : '') + '/api/index.php?route=esm';

// Globals are now casted as any to avoid TS2717

export default function App() {
  const { user, hasPermission } = useAuth();
  const isAgent = hasPermission("esm.manage");
  const ROLE = isAgent ? 'agent' : 'client';
  const USER_INITIALS = user ? user.name.split(' ').map((n: string) => n[0]).join('').substring(0, 2).toUpperCase() : 'U';
  
  const [tickets, setTickets] = useState<Ticket[]>(TICKETS);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [filterStatus, setFilterStatus] = useState<Status | "all">("all");
  const [filterCompany, setFilterCompany] = useState<string | "all">("all");
  const [showNew, setShowNew] = useState(false);
  const [mobileView, setMobileView] = useState<"list" | "detail">("list");
  const [activeTab, setActiveTab] = useState<"analytics" | "tickets">("tickets");
  const [agentStatus, setAgentStatus] = useState<"online" | "away" | "offline">("online");
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const action = isAgent ? 'agent_queue' : 'my_tickets';
    fetch(`${API_BASE}&action=${action}`)
      .then(r => r.json())
      .then(d => {
        if (d.success && d.data) {
          const mapped = d.data.map((t: any) => ({
            id: `TKT-${t.id}`,
            title: t.subject,
            description: t.description,
            status: (t.status === 'Open' ? 'open' : t.status === 'In Progress' ? 'in_progress' : t.status === 'Resolved' ? 'resolved' : 'closed') as Status,
            priority: t.priority.toLowerCase() as Priority,
            category: t.type_name || 'General',
            assignee: t.assigned_to_user_id ? { name: t.agent_name || 'Agent', initials: 'AG', color: '#6366f1' } : null,
            reporter: { name: t.employee_name || 'User', initials: 'US', color: '#3b82f6' },
            created: t.created_at,
            updated: t.updated_at,
            messages: [],
            tags: [t.team_name || 'Queue']
          }));
          if (mapped.length > 0) {
            setTickets(mapped);
            setSelectedId(mapped[0].id);
          }
        }
        setIsLoading(false);
      })
      .catch(e => {
        console.error(e);
        setIsLoading(false);
      });
  }, [isAgent]);

  const selected = tickets.find(t => t.id === selectedId) ?? null;

  const handleStatusChange = (id: string, status: Status) => {
    setTickets(prev => prev.map(t => t.id === id ? { ...t, status, updated: new Date().toISOString() } : t));
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
    setMobileView("detail");
  };

  const openCritical = tickets.filter(t => t.priority === "critical" && t.status !== "resolved" && t.status !== "closed").length;
  const openCount = tickets.filter(t => t.status === "open").length;

  return (
    <div style={{
      height: "100%",
      background: "#0b0f1a",
      backgroundImage: "linear-gradient(rgba(0, 224, 122, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 224, 122, 0.03) 1px, transparent 1px)",
      backgroundSize: "30px 30px",
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
      {/* Module-specific top bar (stripped of global chrome) */}
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
        {/* Left: module nav + search */}
        <div style={{ display: "flex", alignItems: "center", gap: "1rem" }}>
          <div style={{ display: "flex", gap: "0.5rem" }}>
            <button 
              onClick={() => setActiveTab("analytics")}
              style={{
                background: activeTab === "analytics" ? "rgba(255,255,255,0.1)" : "transparent",
                border: "none", padding: "0.4rem 0.75rem", borderRadius: "0.5rem",
                color: activeTab === "analytics" ? "#fff" : "#8899b4",
                fontSize: "0.875rem", fontWeight: 500, cursor: "pointer", transition: "all 0.2s"
              }}
            >
              Analytics
            </button>
            <button 
              onClick={() => setActiveTab("tickets")}
              style={{
                background: activeTab === "tickets" ? "rgba(255,255,255,0.1)" : "transparent",
                border: "none", padding: "0.4rem 0.75rem", borderRadius: "0.5rem",
                color: activeTab === "tickets" ? "#fff" : "#8899b4",
                fontSize: "0.875rem", fontWeight: 500, cursor: "pointer", transition: "all 0.2s"
              }}
            >
              Tickets
            </button>
          </div>

          <div style={{ position: "relative", width: "200px", marginLeft: "1rem" }}>
            <Search size={14} color="#8899b4" style={{ position: "absolute", left: "0.75rem", top: "50%", transform: "translateY(-50%)" }} />
            <input 
              placeholder="Search tickets..." 
              style={{
                background: "rgba(255,255,255,0.05)", border: "1px solid rgba(255,255,255,0.08)",
                borderRadius: "99px", padding: "0.35rem 1rem 0.35rem 2rem", fontSize: "0.8125rem",
                color: "#f0f4ff", outline: "none", width: "100%", fontFamily: "'Inter', sans-serif"
              }}
            />
          </div>
        </div>

        {/* Right: stats */}
        <div style={{ display: "flex", alignItems: "center", gap: "1.25rem" }}>
          <div style={{ display: "flex", alignItems: "center", gap: "1rem" }}>
            <div style={{ display: "flex", alignItems: "center", gap: "0.375rem" }}>
              <div style={{ width: 6, height: 6, borderRadius: "50%", background: "#00e07a", boxShadow: "0 0 8px #00e07a" }} />
              <span style={{ fontSize: "0.8125rem", color: "#8899b4" }}>
                <span style={{ color: "#f0f4ff", fontFamily: "'JetBrains Mono', monospace" }}>{openCount}</span> open
              </span>
            </div>
            {openCritical > 0 && (
              <div style={{ display: "flex", alignItems: "center", gap: "0.375rem" }}>
                <div style={{ width: 6, height: 6, borderRadius: "50%", background: "#ef4444", animation: "pulse 2s infinite" }} />
                <span style={{ fontSize: "0.8125rem", color: "#ef4444" }}>
                  <span style={{ fontFamily: "'JetBrains Mono', monospace" }}>{openCritical}</span> critical
                </span>
              </div>
            )}
          </div>
        </div>
      </header>

      {/* Body */}
      {activeTab === "analytics" ? (
        <div style={{ flex: 1, display: "flex", overflow: "hidden", height: "calc(100vh - 56px)" }}>
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
        {/* Ticket list panel */}
        <div className="ticket-list-panel" style={{ overflow: "hidden", display: "flex", flexDirection: "column" }}>
          <TicketList
            tickets={tickets}
            selectedId={selectedId}
            onSelect={(id) => { setSelectedId(id); setMobileView("detail"); }}
            onNew={() => setShowNew(true)}
            filterStatus={filterStatus}
            onFilterStatus={setFilterStatus}
            filterCompany={filterCompany}
            onFilterCompany={setFilterCompany}
          />
        </div>

        {/* Detail panel */}
        <div className="ticket-detail-panel" style={{
          overflow: "hidden",
          display: "flex",
          flexDirection: "column",
          background: "rgba(255,255,255,0.015)",
        }}>
          {selected ? (
            <TicketDetail
              ticket={selected}
              onBack={mobileView === "detail" ? () => setMobileView("list") : undefined}
              onStatusChange={handleStatusChange}
            />
          ) : (
            <div style={{
              flex: 1, display: "flex", alignItems: "center", justifyContent: "center",
              flexDirection: "column", gap: "0.75rem",
              color: "#4b5a6e",
            }}>
              <Headphones size={32} style={{ opacity: 0.3 }} />
              <p style={{ fontSize: "0.875rem" }}>Select a ticket to view details</p>
            </div>
          )}
        </div>
      </div>
      )}

      {showNew && (
        <NewTicketModal onClose={() => setShowNew(false)} onCreate={handleCreate} />
      )}

      <style>{`
        @keyframes pulse {
          0%, 100% { opacity: 1; }
          50% { opacity: 0.4; }
        }
        * { box-sizing: border-box; }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 99px; }
        input, textarea, select { font-family: 'Inter', system-ui, sans-serif; }
        @media (max-width: 700px) {
          .ticket-list-panel { display: ${mobileView === "list" ? "flex" : "none"} !important; }
          .ticket-detail-panel { display: ${mobileView === "detail" ? "flex" : "none"} !important; }
          div[style*="grid-template-columns: 300px"] { grid-template-columns: 1fr !important; }
        }
      `}</style>
    </div>
  );
}
