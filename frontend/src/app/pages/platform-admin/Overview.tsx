import { useEffect, useState } from "react";
import { Building2, Users, Ticket, AlertTriangle, TrendingUp, ExternalLink, UserCog } from "lucide-react";
import { apiFetch } from "../../lib/apiClient";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));

type Stats = {
  tenantCount: number;
  userCount: number;
  openTickets: number;
  breachedTickets: number;
  mockMRR: number;
  recentTenants: { id: string; company_name: string; impersonate_url: string; user_count?: number; tier?: string }[];
};

function StatCard({ icon: Icon, label, value, sub, color }: { icon: any; label: string; value: string | number; sub?: string; color: string }) {
  return (
    <div className={`bg-background border border-white/[0.05] rounded-2xl p-6 flex items-start gap-4 relative overflow-hidden group hover:border-border transition-all`}>
      <div className={`w-11 h-11 rounded-xl ${color} flex items-center justify-center flex-shrink-0`}>
        <Icon size={20} className="text-foreground" />
      </div>
      <div>
        <p className="text-3xl font-bold text-foreground leading-tight">{value}</p>
        <p className="text-sm text-muted-foreground mt-0.5">{label}</p>
        {sub && <p className="text-xs text-slate-600 mt-1">{sub}</p>}
      </div>
      <div className={`absolute right-0 top-0 w-24 h-24 rounded-full ${color} opacity-5 translate-x-6 -translate-y-6`} />
    </div>
  );
}

export function PlatformAdminOverview() {
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    fetch(`${API_BASE}/pages/views/vendor_dashboard.php?action=get_vendor_stats`, {
      credentials: "include",
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.tenantCount !== undefined) setStats(data);
        else setError("Failed to load stats.");
      })
      .catch(() => setError("Could not reach the server."))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="p-8 max-w-6xl mx-auto">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-foreground" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
          Platform Overview
        </h1>
        <p className="text-slate-500 text-sm mt-1">Live snapshot across all tenants on the platform.</p>
      </div>

      {loading && (
        <div className="flex items-center gap-3 text-slate-500 py-16">
          <div className="w-5 h-5 border-2 border-violet-500/40 border-t-violet-500 rounded-full animate-spin" />
          Loading stats…
        </div>
      )}

      {error && (
        <div className="bg-red-500/10 border border-red-500/20 rounded-xl p-4 text-red-400 text-sm">{error}</div>
      )}

      {stats && (
        <>
          {/* Stat cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <StatCard icon={Building2} label="Total Tenants" value={stats.tenantCount} color="bg-violet-500" />
            <StatCard icon={Users} label="Total Users" value={stats.userCount} color="bg-blue-500" />
            <StatCard icon={Ticket} label="Open Tickets" value={stats.openTickets} color="bg-emerald-500" />
            <StatCard
              icon={AlertTriangle}
              label="Breached SLAs"
              value={stats.breachedTickets}
              sub="Tickets past SLA deadline"
              color={stats.breachedTickets > 0 ? "bg-red-500" : "bg-muted"}
            />
          </div>

          {/* MRR */}
          <div className="bg-gradient-to-r from-violet-500/10 to-fuchsia-500/10 border border-violet-500/20 rounded-2xl p-6 mb-8 flex items-center gap-4">
            <TrendingUp size={28} className="text-violet-400" />
            <div>
              <p className="text-xs text-slate-500 uppercase tracking-widest mb-0.5">Monthly Recurring Revenue</p>
              <p className="text-3xl font-bold text-foreground">
                ${stats.mockMRR?.toLocaleString() ?? "—"}
              </p>
            </div>
          </div>

          {/* Recent tenants */}
          <div className="bg-background border border-white/[0.05] rounded-2xl overflow-hidden">
            <div className="px-6 py-4 border-b border-white/[0.05] flex items-center justify-between">
              <h2 className="text-sm font-semibold text-foreground">Recent Tenants</h2>
              <span className="text-xs text-slate-500">Last 5 sign-ups</span>
            </div>
            <table className="w-full text-sm">
              <thead>
                <tr className="text-xs text-slate-600 uppercase tracking-widest border-b border-border">
                  <th className="px-6 py-3 text-left">Company</th>
                  <th className="px-6 py-3 text-left">Tenant ID</th>
                  <th className="px-6 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {stats.recentTenants.map((t) => (
                  <tr key={t.id} className="border-b border-white/[0.03] hover:bg-card/[0.02] transition-colors">
                    <td className="px-6 py-4 text-foreground font-medium">{t.company_name}</td>
                    <td className="px-6 py-4 text-slate-500 font-mono text-xs">{t.id}</td>
                    <td className="px-6 py-4 text-right">
                      <a
                        href={t.impersonate_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-500/10 text-violet-300 text-xs hover:bg-violet-500/20 transition-colors border border-violet-500/20"
                      >
                        <UserCog size={12} /> Impersonate
                      </a>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}
