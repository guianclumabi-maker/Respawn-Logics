import { useEffect, useState } from "react";
import { Search, UserCog, Building2, RefreshCw } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));

type Tenant = {
  id: string;
  company_name: string;
  contact_email: string;
  subscription_tier: string;
  status: string;
  user_count?: number;
  created_at?: string;
  impersonate_url?: string;
};

const TIER_COLORS: Record<string, string> = {
  starter: "bg-slate-700 text-slate-300",
  professional: "bg-blue-500/20 text-blue-300",
  enterprise: "bg-violet-500/20 text-violet-300",
};

const STATUS_COLORS: Record<string, string> = {
  active: "bg-emerald-500/20 text-emerald-300",
  suspended: "bg-red-500/20 text-red-300",
  trial: "bg-amber-500/20 text-amber-300",
};

export function PlatformAdminTenants() {
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");

  const load = () => {
    setLoading(true);
    fetch(`${API_BASE}/api/index.php?route=iam&action=platform_tenant_list`, {
      credentials: "include",
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) setTenants(data.data ?? []);
        else setError(data.error ?? "Failed to load tenants.");
      })
      .catch(() => setError("Could not reach the server."))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []);

  const filtered = tenants.filter(
    (t) =>
      t.company_name?.toLowerCase().includes(search.toLowerCase()) ||
      t.contact_email?.toLowerCase().includes(search.toLowerCase()) ||
      t.id?.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="p-8 max-w-6xl mx-auto">
      <div className="mb-8 flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
            Tenants
          </h1>
          <p className="text-slate-500 text-sm mt-1">All organizations on the platform.</p>
        </div>
        <button onClick={load} className="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/[0.03] border border-white/[0.07] text-slate-400 hover:text-slate-200 text-sm transition-colors">
          <RefreshCw size={14} className={loading ? "animate-spin" : ""} /> Refresh
        </button>
      </div>

      {/* Search */}
      <div className="relative mb-6">
        <Search size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search by name, email or tenant ID…"
          className="w-full pl-10 pr-4 py-2.5 bg-[#0c1018] border border-white/[0.07] rounded-xl text-sm text-slate-200 placeholder-slate-600 focus:outline-none focus:border-violet-500/40 transition-colors"
        />
      </div>

      {error && (
        <div className="bg-red-500/10 border border-red-500/20 rounded-xl p-4 text-red-400 text-sm mb-6">{error}</div>
      )}

      <div className="bg-[#0c1018] border border-white/[0.05] rounded-2xl overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-xs text-slate-600 uppercase tracking-widest border-b border-white/[0.05]">
              <th className="px-6 py-3 text-left">Company</th>
              <th className="px-6 py-3 text-left">Contact</th>
              <th className="px-6 py-3 text-left">Tier</th>
              <th className="px-6 py-3 text-left">Status</th>
              <th className="px-6 py-3 text-left">Users</th>
              <th className="px-6 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading && (
              <tr>
                <td colSpan={6} className="px-6 py-16 text-center text-slate-500">
                  <div className="flex items-center justify-center gap-2">
                    <div className="w-4 h-4 border-2 border-violet-500/40 border-t-violet-500 rounded-full animate-spin" />
                    Loading…
                  </div>
                </td>
              </tr>
            )}
            {!loading && filtered.length === 0 && (
              <tr>
                <td colSpan={6} className="px-6 py-16 text-center text-slate-600">
                  <Building2 size={32} className="mx-auto mb-3 opacity-30" />
                  No tenants found
                </td>
              </tr>
            )}
            {!loading && filtered.map((t) => (
              <tr key={t.id} className="border-b border-white/[0.03] hover:bg-white/[0.02] transition-colors">
                <td className="px-6 py-4">
                  <p className="text-slate-200 font-medium">{t.company_name}</p>
                  <p className="text-slate-600 text-xs font-mono mt-0.5">{t.id}</p>
                </td>
                <td className="px-6 py-4 text-slate-400 text-xs">{t.contact_email}</td>
                <td className="px-6 py-4">
                  <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${TIER_COLORS[t.subscription_tier?.toLowerCase()] ?? "bg-slate-700 text-slate-300"}`}>
                    {t.subscription_tier ?? "—"}
                  </span>
                </td>
                <td className="px-6 py-4">
                  <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${STATUS_COLORS[t.status?.toLowerCase()] ?? "bg-slate-700 text-slate-300"}`}>
                    {t.status ?? "—"}
                  </span>
                </td>
                <td className="px-6 py-4 text-slate-400">{t.user_count ?? "—"}</td>
                <td className="px-6 py-4 text-right">
                  <a
                    href={`${API_BASE}/pages/impersonate.php?action=start&tenant_id=${t.id}`}
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
    </div>
  );
}
