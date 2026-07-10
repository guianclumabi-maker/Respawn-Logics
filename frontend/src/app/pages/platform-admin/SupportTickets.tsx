import { useEffect, useState } from "react";
import { Search, Clock, AlertCircle, ChevronDown, Filter, RefreshCw, ExternalLink, MoreHorizontal } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=platform_support`;

async function getCsrf(): Promise<string> {
  const r = await fetch(`${API_BASE}/api/index.php?route=auth&action=csrf`, { credentials: "include" });
  const d = await r.json();
  return d.csrf_token ?? "";
}

type Ticket = {
  id: number;
  subject: string;
  company_name: string;
  priority: string;
  status: string;
  created_at: string;
  aging_hours: number;
  sli_status: string;
  assigned_to?: string;
};

const PRIORITY_COLORS: Record<string, string> = {
  critical: "bg-red-500/20 text-red-300 border-red-500/30",
  high: "bg-orange-500/20 text-orange-300 border-orange-500/30",
  medium: "bg-amber-500/20 text-amber-300 border-amber-500/30",
  low: "bg-muted text-muted-foreground border-slate-600",
};

const SLI_COLORS: Record<string, string> = {
  Healthy: "text-emerald-400",
  Warning: "text-amber-400",
  Breached: "text-red-400",
};

const STATUS_COLORS: Record<string, string> = {
  open: "bg-blue-500/20 text-blue-300",
  "in_progress": "bg-violet-500/20 text-violet-300",
  waiting: "bg-amber-500/20 text-amber-300",
  resolved: "bg-emerald-500/20 text-emerald-300",
  closed: "bg-muted text-muted-foreground",
};

export function PlatformAdminSupport() {
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [tab, setTab] = useState<"pending" | "finished">("pending");
  const [priority, setPriority] = useState("");

  const load = () => {
    setLoading(true);
    const params = new URLSearchParams({ action: "vendor_list", tab });
    if (priority) params.set("priority", priority);
    if (search) params.set("search", search);
    fetch(`${API}&${params}`, { credentials: "include" })
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setTickets(d.data ?? []);
        else setError(d.error ?? "Failed to load tickets.");
      })
      .catch(() => setError("Could not reach server."))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [tab, priority]);

  const handleStatusChange = async (ticketId: number, status: string) => {
    const csrf = await getCsrf();
    await fetch(`${API}&action=update_status`, {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf },
      body: JSON.stringify({ ticket_id: ticketId, status }),
    });
    load();
  };

  return (
    <div className="p-8 max-w-7xl mx-auto">
      <div className="mb-8 flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-foreground" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
            Support Tickets
          </h1>
          <p className="text-slate-500 text-sm mt-1">All tenant support requests across the platform.</p>
        </div>
        <div className="flex gap-2">
          <button onClick={load} className="flex items-center gap-2 px-3 py-2 rounded-lg bg-card/[0.03] border border-white/[0.07] text-muted-foreground hover:text-foreground text-sm transition-colors">
            <RefreshCw size={14} className={loading ? "animate-spin" : ""} />
          </button>
          <a
            href={`${API}&action=export_report&tab=${tab}`}
            className="flex items-center gap-2 px-4 py-2 rounded-lg bg-card/[0.03] border border-white/[0.07] text-muted-foreground hover:text-foreground text-sm transition-colors"
          >
            <ExternalLink size={14} /> Export CSV
          </a>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 bg-background border border-white/[0.05] rounded-xl p-1 mb-6 w-fit">
        {(["pending", "finished"] as const).map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`px-5 py-2 rounded-lg text-sm font-medium transition-all capitalize cursor-pointer ${
              tab === t ? "bg-violet-600 text-foreground" : "text-muted-foreground hover:text-foreground"
            }`}
          >
            {t}
          </button>
        ))}
      </div>

      {/* Filters */}
      <div className="flex gap-3 mb-5">
        <div className="relative flex-1 max-w-sm">
          <Search size={14} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === "Enter" && load()}
            placeholder="Search tickets…"
            className="w-full pl-9 pr-4 py-2 bg-background border border-white/[0.07] rounded-lg text-sm text-foreground placeholder-slate-600 focus:outline-none focus:border-violet-500/40"
          />
        </div>
        <select
          value={priority}
          onChange={(e) => setPriority(e.target.value)}
          className="px-3 py-2 bg-background border border-white/[0.07] rounded-lg text-sm text-muted-foreground focus:outline-none focus:border-violet-500/40"
        >
          <option value="">All Priorities</option>
          <option value="critical">Critical</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
      </div>

      {error && <div className="bg-red-500/10 border border-red-500/20 rounded-xl p-4 text-red-400 text-sm mb-6">{error}</div>}

      <div className="bg-background border border-white/[0.05] rounded-2xl overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-xs text-slate-600 uppercase tracking-widest border-b border-white/[0.05]">
              <th className="px-5 py-3 text-left">Ticket</th>
              <th className="px-5 py-3 text-left">Tenant</th>
              <th className="px-5 py-3 text-left">Priority</th>
              <th className="px-5 py-3 text-left">Status</th>
              <th className="px-5 py-3 text-left">SLI</th>
              <th className="px-5 py-3 text-left">Age</th>
              <th className="px-5 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading && (
              <tr><td colSpan={7} className="px-5 py-16 text-center text-slate-500">
                <div className="flex items-center justify-center gap-2">
                  <div className="w-4 h-4 border-2 border-violet-500/40 border-t-violet-500 rounded-full animate-spin" />Loading…
                </div>
              </td></tr>
            )}
            {!loading && tickets.length === 0 && (
              <tr><td colSpan={7} className="px-5 py-16 text-center text-slate-600">No tickets found.</td></tr>
            )}
            {!loading && tickets.map((t) => (
              <tr key={t.id} className="border-b border-white/[0.03] hover:bg-card/[0.02] transition-colors">
                <td className="px-5 py-4">
                  <p className="text-foreground text-sm font-medium line-clamp-1">#{t.id} — {t.subject}</p>
                  <p className="text-slate-600 text-xs mt-0.5">{new Date(t.created_at).toLocaleDateString()}</p>
                </td>
                <td className="px-5 py-4 text-muted-foreground text-sm">{t.company_name}</td>
                <td className="px-5 py-4">
                  <span className={`px-2 py-0.5 rounded-full text-xs font-medium border capitalize ${PRIORITY_COLORS[t.priority?.toLowerCase()] ?? "bg-muted text-muted-foreground border-slate-600"}`}>
                    {t.priority}
                  </span>
                </td>
                <td className="px-5 py-4">
                  <select
                    defaultValue={t.status?.toLowerCase().replace(" ", "_")}
                    onChange={(e) => handleStatusChange(t.id, e.target.value)}
                    className={`px-2 py-1 rounded-lg text-xs font-medium border-none outline-none cursor-pointer capitalize ${STATUS_COLORS[t.status?.toLowerCase().replace(" ", "_")] ?? "bg-muted text-muted-foreground"}`}
                  >
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="waiting">Waiting</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                  </select>
                </td>
                <td className="px-5 py-4">
                  <span className={`text-xs font-medium flex items-center gap-1 ${SLI_COLORS[t.sli_status] ?? "text-muted-foreground"}`}>
                    <AlertCircle size={12} />
                    {t.sli_status}
                  </span>
                </td>
                <td className="px-5 py-4 text-slate-500 text-xs">
                  <Clock size={12} className="inline mr-1" />
                  {Math.round(t.aging_hours)}h
                </td>
                <td className="px-5 py-4 text-right">
                  <a
                    href={`#/platform-admin/support/${t.id}`}
                    className="text-slate-500 hover:text-foreground transition-colors cursor-pointer"
                  >
                    <MoreHorizontal size={16} />
                  </a>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
