import { useState } from "react";
import { TicketList } from "./components/tickets/TicketList";
import { TicketDetail } from "./components/tickets/TicketDetail";
import { NewTicketModal } from "./components/tickets/NewTicketModal";
import { AnalyticsDashboard } from "./components/analytics/AnalyticsDashboard";
import { GamifiedThemeToggle } from "./components/GamifiedThemeToggle";
import { TICKETS, Ticket, Status, Priority } from "./components/tickets/data";
import { Headphones, Bell, Settings, ArrowLeft, Search } from "lucide-react";

declare global {
  interface Window {
    __INITIAL_DATA__: Ticket[];
    __AGENTS__: {id: number, name: string, initials: string, color: string}[];
    __ROLE__: 'agent' | 'client';
    __USER_INITIALS__: string;
  }
}

export default function App() {
  const initialTickets = typeof window !== 'undefined' && window.__INITIAL_DATA__ ? window.__INITIAL_DATA__ : TICKETS;
  const ROLE = typeof window !== 'undefined' ? (window.__ROLE__ || 'agent') : 'agent';
  const USER_INITIALS = typeof window !== 'undefined' ? (window.__USER_INITIALS__ || 'U') : 'U';
  const [tickets, setTickets] = useState<Ticket[]>(initialTickets);
  const [selectedId, setSelectedId] = useState<string | null>(initialTickets.length > 0 ? initialTickets[0].id : null);
  const [filterStatus, setFilterStatus] = useState<Status | "all">("all");
  const [filterCompany, setFilterCompany] = useState<string | "all">("all");
  const [showNew, setShowNew] = useState(false);
  const [mobileView, setMobileView] = useState<"list" | "detail">("list");
  const [activeTab, setActiveTab] = useState<"analytics" | "tickets">("tickets");
  const [agentStatus, setAgentStatus] = useState<"online" | "away" | "offline">("online");

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
      minHeight: "100vh",
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
        <div style={{ display: "flex", alignItems: "center", gap: "0.75rem" }}>
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
            background: "rgba(0, 224, 122, 0.1)",
            border: "1px solid rgba(0, 224, 122, 0.2)",
            display: "flex", alignItems: "center", justifyContent: "center",
          }}>
            <Headphones size={15} color="#00e07a" />
          </div>
          <span style={{ fontWeight: 600, fontSize: "0.9375rem", fontFamily: "'Space Grotesk', sans-serif", marginRight: "1rem" }}>Support</span>
          
          <div style={{ position: "relative", width: "200px" }}>
            <Search size={14} color="#8899b4" style={{ position: "absolute", left: "0.75rem", top: "50%", transform: "translateY(-50%)" }} />
            <input 
              placeholder="Search globally..." 
              style={{
                background: "rgba(255,255,255,0.05)", border: "1px solid rgba(255,255,255,0.08)",
                borderRadius: "99px", padding: "0.35rem 1rem 0.35rem 2rem", fontSize: "0.8125rem",
                color: "#f0f4ff", outline: "none", width: "100%", fontFamily: "'Inter', sans-serif"
              }}
            />
          </div>

          <div style={{ display: "flex", gap: "0.5rem", marginLeft: "1rem" }}>
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
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: "1.25rem" }}>
          {/* Stats */}
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

          <GamifiedThemeToggle />

          <button style={{
            background: "transparent", border: "1px solid rgba(255,255,255,0.08)",
            color: "#4b5a6e", borderRadius: "0.5rem",
            width: 30, height: 30, display: "flex", alignItems: "center", justifyContent: "center",
            cursor: "pointer",
          }}>
            <Bell size={14} />
          </button>

          {/* Agent Status Dropdown */}
          <div style={{ position: "relative", display: "flex", alignItems: "center" }}>
            <select 
              value={agentStatus}
              onChange={(e) => setAgentStatus(e.target.value as any)}
              style={{
                appearance: "none",
                background: "rgba(255,255,255,0.05)",
                border: "1px solid rgba(255,255,255,0.08)",
                color: agentStatus === "online" ? "#00e07a" : agentStatus === "away" ? "#f59e0b" : "#8899b4",
                borderRadius: "99px",
                padding: "0.35rem 1.5rem 0.35rem 1.5rem",
                fontSize: "0.75rem",
                fontWeight: 600,
                fontFamily: "'JetBrains Mono', monospace",
                cursor: "pointer",
                outline: "none",
              }}
            >
              <option value="online" style={{ background: "#0f1422", color: "#00e07a" }}>● Online</option>
              <option value="away" style={{ background: "#0f1422", color: "#f59e0b" }}>● Away</option>
              <option value="offline" style={{ background: "#0f1422", color: "#8899b4" }}>● Offline</option>
            </select>
            <div style={{
              position: "absolute", left: "0.5rem", width: 6, height: 6, borderRadius: "50%", pointerEvents: "none",
              background: agentStatus === "online" ? "#00e07a" : agentStatus === "away" ? "#f59e0b" : "#8899b4",
              boxShadow: agentStatus === "online" ? "0 0 6px #00e07a" : agentStatus === "away" ? "0 0 6px #f59e0b" : "none"
            }} />
          </div>

          <div style={{
            width: 30, height: 30, borderRadius: "50%",
            background: "rgba(0, 224, 122, 0.1)", border: "1px solid rgba(0, 224, 122, 0.3)",
            display: "flex", alignItems: "center", justifyContent: "center",
            fontSize: "0.6875rem", fontWeight: 700, color: "#00e07a",
            fontFamily: "'JetBrains Mono', monospace"
          }}>
            {USER_INITIALS}
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
