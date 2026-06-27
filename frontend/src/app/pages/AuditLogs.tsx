import { useState, useEffect } from "react";
import { Activity, Clock, ShieldAlert, CheckCircle, Info, Search, Filter } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=audit`;

type AuditLog = {
  id: number;
  user_email: string;
  action: string;
  details: string;
  created_at: string;
  full_name: string | null;
  job_title: string | null;
  profile_image: string | null;
};

type Meta = {
  total: number;
  page: number;
  limit: number;
  total_pages: number;
};

export function AuditLogs() {
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [actions, setActions] = useState<string[]>([]);
  const [meta, setMeta] = useState<Meta | null>(null);
  
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [actionFilter, setActionFilter] = useState("");

  useEffect(() => {
    fetchActions();
  }, []);

  useEffect(() => {
    fetchLogs();
  }, [page, search, actionFilter]);

  const fetchActions = async () => {
    try {
      const res = await fetch(`${API}&action=fetch_actions`, { credentials: "include" });
      if (res.ok) {
        const json = await res.json();
        if (json.success && json.data) {
          setActions(json.data);
        }
      }
    } catch (e) {
      console.error("Failed to fetch actions", e);
    }
  };

  const fetchLogs = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams();
      params.append('page', page.toString());
      params.append('limit', '50');
      if (search) params.append('search', search);
      if (actionFilter) params.append('action_filter', actionFilter);

      const res = await fetch(`${API}&action=fetch_logs&${params.toString()}`, { credentials: "include" });
      if (res.status === 401 || res.status === 403) {
        setError("Access Denied. You do not have permission to view audit logs.");
        setLoading(false);
        return;
      }
      
      if (res.ok) {
        const json = await res.json();
        if (json.success) {
          setLogs(json.data || []);
          setMeta(json.meta || null);
        } else {
          setError(json.error || "Failed to load audit logs.");
        }
      } else {
        setError("Server error while loading audit logs.");
      }
    } catch (e) {
      setError("Network error while communicating with the server.");
    } finally {
      setLoading(false);
    }
  };

  const getStatusIcon = (actionType: string) => {
    const lower = actionType.toLowerCase();
    if (lower.includes('fail') || lower.includes('error') || lower.includes('delete')) {
      return <ShieldAlert className="w-4 h-4 text-red-500" />;
    } else if (lower.includes('login') || lower.includes('success') || lower.includes('create')) {
      return <CheckCircle className="w-4 h-4 text-emerald-500" />;
    } else if (lower.includes('update') || lower.includes('change')) {
      return <Activity className="w-4 h-4 text-amber-500" />;
    }
    return <Info className="w-4 h-4 text-blue-500" />;
  };

  if (error && !logs.length) {
    return (
      <div className="h-full w-full flex items-center justify-center bg-[#0b0f19] p-8">
        <div className="bg-[#141929] border border-red-500/20 rounded-xl p-8 max-w-md text-center">
          <ShieldAlert className="w-12 h-12 text-red-500 mx-auto mb-4" />
          <h2 className="text-xl font-bold text-white mb-2">Access Restricted</h2>
          <p className="text-gray-400">{error}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="h-full w-full flex flex-col p-8 overflow-y-auto" style={{ backgroundColor: "#0b0f19" }}>
      <header className="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
          <h1 className="text-2xl font-bold text-white tracking-tight mb-2 flex items-center gap-2">
            <Activity className="w-6 h-6 text-blue-400" />
            Audit Logs
          </h1>
          <p className="text-gray-400">Track and monitor security events and user activity across the system.</p>
        </div>
        
        <div className="flex items-center gap-3">
          <div className="relative">
            <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input 
              type="text"
              placeholder="Search logs..."
              className="pl-9 pr-4 py-2 bg-[#141929] border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500 w-64"
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
            />
          </div>
          <div className="relative">
            <Filter className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <select
              className="pl-9 pr-8 py-2 bg-[#141929] border border-white/10 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500 appearance-none"
              value={actionFilter}
              onChange={(e) => { setActionFilter(e.target.value); setPage(1); }}
            >
              <option value="">All Actions</option>
              {actions.map(a => (
                <option key={a} value={a}>{a}</option>
              ))}
            </select>
          </div>
        </div>
      </header>

      <div className="bg-[#141929] rounded-xl border border-white/10 overflow-hidden flex-1 flex flex-col">
        <div className="overflow-x-auto flex-1">
          <table className="w-full text-left text-sm text-gray-300">
            <thead className="text-xs text-gray-400 uppercase bg-black/20 border-b border-white/10">
              <tr>
                <th className="px-6 py-4 font-semibold">Event</th>
                <th className="px-6 py-4 font-semibold">User</th>
                <th className="px-6 py-4 font-semibold">Details</th>
                <th className="px-6 py-4 font-semibold text-right">Timestamp</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/5">
              {loading && logs.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-6 py-8 text-center text-gray-500">
                    Loading audit trail...
                  </td>
                </tr>
              ) : logs.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-6 py-8 text-center text-gray-500">
                    No audit logs found matching your criteria.
                  </td>
                </tr>
              ) : (
                logs.map((log) => (
                  <tr key={log.id} className="hover:bg-white/[0.02] transition-colors">
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-white/5 border border-white/5">
                          {getStatusIcon(log.action)}
                        </div>
                        <div>
                          <div className="font-medium text-gray-200">{log.action}</div>
                          <div className="text-xs text-gray-500">ID: {log.id}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center font-semibold text-xs border border-blue-500/20">
                          {log.profile_image ? (
                            <img src={log.profile_image} alt="User" className="w-8 h-8 rounded-full object-cover" />
                          ) : (
                            (log.full_name || log.user_email).charAt(0).toUpperCase()
                          )}
                        </div>
                        <div>
                          <div className="font-medium text-gray-200">{log.full_name || 'System User'}</div>
                          <div className="text-xs text-gray-500">{log.user_email}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <p className="text-gray-300 max-w-md truncate" title={log.details}>
                        {log.details}
                      </p>
                    </td>
                    <td className="px-6 py-4 text-right whitespace-nowrap">
                      <div className="flex items-center justify-end gap-2 text-gray-400">
                        <Clock className="w-4 h-4" />
                        {new Date(log.created_at).toLocaleString()}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        
        {meta && meta.total_pages > 1 && (
          <div className="p-4 border-t border-white/10 flex items-center justify-between bg-black/10">
            <div className="text-sm text-gray-400">
              Showing <span className="font-medium text-gray-200">{((meta.page - 1) * meta.limit) + 1}</span> to <span className="font-medium text-gray-200">{Math.min(meta.page * meta.limit, meta.total)}</span> of <span className="font-medium text-gray-200">{meta.total}</span> entries
            </div>
            <div className="flex items-center gap-2">
              <button 
                onClick={() => setPage(p => Math.max(1, p - 1))}
                disabled={page === 1}
                className="px-3 py-1 bg-white/5 border border-white/10 rounded text-gray-300 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Previous
              </button>
              <button 
                onClick={() => setPage(p => Math.min(meta.total_pages, p + 1))}
                disabled={page === meta.total_pages}
                className="px-3 py-1 bg-white/5 border border-white/10 rounded text-gray-300 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
