import { useState, useEffect } from "react";
import { CheckCircle, AlertCircle, Clock } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=attendance`;

export function ManagerApprovals() {
  const [logs, setLogs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchPending = async () => {
    try {
      const res = await fetch(`${API}&action=pending_approvals`, { credentials: "include" });
      const data = await res.json();
      if (data.success) {
        setLogs(data.data);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPending();
  }, []);

  const handleApprove = async (id: number) => {
    try {
      const res = await fetch(`${API}&action=approve_timesheet`, { credentials: "include",
        method: "POST",
        headers: { 
          "Content-Type": "application/json",
          "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
        },
        body: JSON.stringify({ record_id: id })
      });
      const data = await res.json();
      if (data.success) {
        setLogs(logs.filter(log => log.id !== id));
      } else {
        alert(data.error);
      }
    } catch (e) {
      console.error(e);
    }
  };

  if (loading) return <div className="text-white p-8">Loading...</div>;

  return (
    <div className="flex-1 overflow-auto bg-[#06070a] text-white p-8 space-y-8">
      <div>
        <h1 className="text-3xl font-bold bg-gradient-to-r from-white to-gray-400 bg-clip-text text-transparent mb-2" style={{ fontFamily: "'Outfit', 'Inter', sans-serif" }}>
          Timesheet Approvals
        </h1>
        <p className="text-gray-400 text-sm">Review and approve employee timesheets across your organization.</p>
      </div>

      <div className="bg-[#0d0f19] border border-white/[0.04] rounded-2xl overflow-hidden">
        <table className="w-full text-left text-sm text-gray-400">
          <thead className="text-xs uppercase bg-white/[0.02] border-b border-white/[0.04]">
            <tr>
              <th className="px-6 py-4 font-medium">Employee</th>
              <th className="px-6 py-4 font-medium">Date</th>
              <th className="px-6 py-4 font-medium">Clock In</th>
              <th className="px-6 py-4 font-medium">Clock Out</th>
              <th className="px-6 py-4 font-medium">Status</th>
              <th className="px-6 py-4 font-medium text-right">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/[0.04]">
            {logs.map((log) => (
              <tr key={log.id} className="hover:bg-white/[0.02] transition-colors">
                <td className="px-6 py-4">
                  <div className="flex flex-col">
                    <span className="text-white font-medium">{log.full_name}</span>
                    <span className="text-xs text-gray-500">{log.department}</span>
                  </div>
                </td>
                <td className="px-6 py-4 text-white">
                  {new Date(log.time_in).toLocaleDateString()}
                </td>
                <td className="px-6 py-4">
                  {new Date(log.time_in).toLocaleTimeString()}
                </td>
                <td className="px-6 py-4">
                  {new Date(log.time_out).toLocaleTimeString()}
                </td>
                <td className="px-6 py-4">
                  {log.status === "Late" ? (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">
                      <AlertCircle size={12} /> Late
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                      <CheckCircle size={12} /> On Time
                    </span>
                  )}
                </td>
                <td className="px-6 py-4 text-right">
                  <button
                    onClick={() => handleApprove(log.id)}
                    className="px-4 py-2 bg-[#8b5cf6]/10 text-[#c084fc] hover:bg-[#8b5cf6]/20 border border-[#8b5cf6]/20 rounded-lg text-xs font-medium transition-colors"
                  >
                    Approve
                  </button>
                </td>
              </tr>
            ))}
            {logs.length === 0 && (
              <tr>
                <td colSpan={6} className="px-6 py-12 text-center text-gray-500">
                  <div className="flex flex-col items-center justify-center">
                    <div className="w-12 h-12 rounded-full bg-emerald-500/10 flex items-center justify-center mb-4">
                      <CheckCircle className="text-emerald-500 w-6 h-6" />
                    </div>
                    <p className="text-base text-gray-300 font-medium">All Caught Up!</p>
                    <p className="text-sm mt-1">There are no pending timesheets to approve.</p>
                  </div>
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
