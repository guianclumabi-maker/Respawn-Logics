import { useState, useEffect } from "react";
import { Activity, Clock, ShieldAlert, CheckCircle, Info } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=audit`;

type AuditLog = {
  id: number;
  action: string;
  user_name: string;
  ip_address: string;
  status: "success" | "warning" | "error";
  details: string;
  created_at: string;
};

export function AuditLogs() {
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchLogs = async () => {
      try {
        const res = await fetch(API, { credentials: "include" });
        if (res.ok) {
          const json = await res.json();
          if (json.success && json.logs) {
            setLogs(json.logs);
            return;
          }
        }
        // Mock data
        setLogs([
          { id: 101, action: "User Login", user_name: "Admin User", ip_address: "192.168.1.10", status: "success", details: "Successful authentication", created_at: new Date().toISOString() },
          { id: 102, action: "Failed Login", user_name: "Unknown", ip_address: "203.0.113.45", status: "error", details: "Invalid credentials provided", created_at: new Date(Date.now() - 3600000).toISOString() },
          { id: 103, action: "Update Settings", user_name: "Admin User", ip_address: "192.168.1.10", status: "success", details: "Updated tenant settings", created_at: new Date(Date.now() - 7200000).toISOString() },
          { id: 104, action: "Role Changed", user_name: "John Doe", ip_address: "192.168.1.12", status: "warning", details: "Changed 'Jane Smith' to HR Manager", created_at: new Date(Date.now() - 86400000).toISOString() }
        ]);
      } catch (e) {
        console.error(e);
      } finally {
        setLoading(false);
      }
    };
    fetchLogs();
  }, []);

  const getStatusIcon = (status: string) => {
    switch(status) {
      case 'success': return <CheckCircle className="w-4 h-4 text-emerald-500" />;
      case 'warning': return <ShieldAlert className="w-4 h-4 text-amber-500" />;
      case 'error': return <ShieldAlert className="w-4 h-4 text-red-500" />;
      default: return <Info className="w-4 h-4 text-blue-500" />;
    }
  };

  const getStatusClass = (status: string) => {
    switch(status) {
      case 'success': return "bg-emerald-100 text-emerald-800";
      case 'warning': return "bg-amber-100 text-amber-800";
      case 'error': return "bg-red-100 text-red-800";
      default: return "bg-gray-100 text-gray-800";
    }
  };

  if (loading) {
    return <div className="p-8 text-slate-400">Loading audit logs...</div>;
  }

  return (
    <div className="h-full w-full flex flex-col p-8 overflow-y-auto" style={{ backgroundColor: "#f9fafb" }}>
      <header className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900 tracking-tight mb-2">Audit Logs</h1>
        <p className="text-gray-500">Track and monitor user activities, system changes, and security events.</p>
      </header>

      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col flex-1 max-h-full">
        <div className="p-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
          <div className="flex items-center gap-2 font-medium text-gray-700">
            <Activity className="w-5 h-5 text-gray-500" />
            System Activity Stream
          </div>
          <div className="text-sm text-gray-500 flex items-center gap-2">
            <Clock className="w-4 h-4" />
            Last updated: Just now
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-gray-600">
            <thead className="bg-gray-50/50 border-b border-gray-200 text-gray-700 uppercase text-xs font-semibold">
              <tr>
                <th className="px-6 py-4">Status</th>
                <th className="px-6 py-4">Action</th>
                <th className="px-6 py-4">User</th>
                <th className="px-6 py-4">Details</th>
                <th className="px-6 py-4">IP Address</th>
                <th className="px-6 py-4 text-right">Timestamp</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {logs.map(log => (
                <tr key={log.id} className="hover:bg-gray-50 transition-colors">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-xs font-medium uppercase tracking-wide ${getStatusClass(log.status)}`}>
                      {getStatusIcon(log.status)}
                      {log.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                    {log.action}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {log.user_name}
                  </td>
                  <td className="px-6 py-4">
                    <span className="truncate max-w-xs block text-gray-500" title={log.details}>
                      {log.details}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-gray-500 font-mono text-xs">
                    {log.ip_address}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right text-gray-500">
                    {new Date(log.created_at).toLocaleString()}
                  </td>
                </tr>
              ))}
              {logs.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-6 py-8 text-center text-gray-500">
                    No audit logs found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
