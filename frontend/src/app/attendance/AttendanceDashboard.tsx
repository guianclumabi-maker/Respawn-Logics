import { apiFetch } from "../lib/apiClient";
import { useState, useEffect } from "react";
import { Clock, CheckCircle, AlertCircle } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=attendance`;

export function AttendanceDashboard() {
  const [status, setStatus] = useState<any>(null);
  const [logs, setLogs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchData = async () => {
    try {
      const [statusRes, logsRes] = await Promise.all([
        apiFetch(`${API.replace(API_BASE, "")}&action=status`, { }),
        apiFetch(`${API.replace(API_BASE, "")}&action=timesheet`, { })
      ]);
      const statusData = await statusRes.json();
      const logsData = await logsRes.json();
      if (statusData.success) setStatus(statusData.data);
      if (logsData.success) setLogs(logsData.data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleClockAction = async (action: "clock_in" | "clock_out") => {
    try {
      setLoading(true);
      const res = await apiFetch(`${API.replace(API_BASE, "")}&action=${action}`, {
        method: "POST",

      });
      const data = await res.json();
      if (data.success) {
        await fetchData();
      } else {
        alert(data.error);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  if (loading && !status) return <div className="text-white p-8">Loading...</div>;

  return (
    <div className="flex-1 overflow-auto bg-[#06070a] text-white p-8 space-y-8">
      <div>
        <h1 className="text-3xl font-bold bg-gradient-to-r from-white to-gray-400 bg-clip-text text-transparent mb-2" style={{ fontFamily: "'Outfit', 'Inter', sans-serif" }}>
          Attendance Dashboard
        </h1>
        <p className="text-gray-400 text-sm">Track your hours and manage your timesheets.</p>
      </div>

      <div className="bg-[#0d0f19] border border-white/[0.04] p-8 rounded-2xl flex flex-col items-center justify-center space-y-6">
        <div className="w-24 h-24 rounded-full bg-[#8b5cf6]/10 flex items-center justify-center border border-[#8b5cf6]/30">
          <Clock className="w-10 h-10 text-[#a855f7]" />
        </div>
        
        <div className="text-center">
          <h2 className="text-2xl font-semibold mb-1">
            {status?.state === "in" ? "You are clocked in." : status?.state === "completed" ? "Shift completed." : "Ready to start?"}
          </h2>
          <p className="text-gray-400 text-sm">
            {status?.state === "in" && status.log && `Clocked in at ${new Date(status.log.time_in).toLocaleTimeString()}`}
            {status?.state === "completed" && "You have already completed your shift for today."}
            {status?.state === "out" && "Click the button below to clock in for the day."}
          </p>
        </div>

        {status?.state === "out" && (
          <button
            onClick={() => handleClockAction("clock_in")}
            className="px-8 py-3 bg-gradient-to-r from-[#8b5cf6] to-[#ec4899] rounded-xl font-semibold text-white shadow-[0_0_20px_rgba(139,92,246,0.3)] hover:shadow-[0_0_30px_rgba(139,92,246,0.5)] transition-all"
          >
            Clock In Now
          </button>
        )}

        {status?.state === "in" && (
          <button
            onClick={() => handleClockAction("clock_out")}
            className="px-8 py-3 bg-white/5 border border-white/10 hover:bg-white/10 rounded-xl font-semibold text-white transition-all"
          >
            Clock Out
          </button>
        )}
      </div>

      <div>
        <h3 className="text-lg font-semibold mb-4 text-gray-200">Recent Timesheets</h3>
        <div className="bg-[#0d0f19] border border-white/[0.04] rounded-2xl overflow-hidden">
          <table className="w-full text-left text-sm text-gray-400">
            <thead className="text-xs uppercase bg-white/[0.02] border-b border-white/[0.04]">
              <tr>
                <th className="px-6 py-4 font-medium">Date</th>
                <th className="px-6 py-4 font-medium">Clock In</th>
                <th className="px-6 py-4 font-medium">Clock Out</th>
                <th className="px-6 py-4 font-medium">Status</th>
                <th className="px-6 py-4 font-medium text-right">Approval</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/[0.04]">
              {logs.map((log) => (
                <tr key={log.id} className="hover:bg-white/[0.02] transition-colors">
                  <td className="px-6 py-4 text-white font-medium">
                    {new Date(log.time_in).toLocaleDateString()}
                  </td>
                  <td className="px-6 py-4">
                    {new Date(log.time_in).toLocaleTimeString()}
                  </td>
                  <td className="px-6 py-4">
                    {log.time_out ? new Date(log.time_out).toLocaleTimeString() : <span className="text-yellow-500/80">Active</span>}
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
                    {Number(log.manager_approved) === 1 ? (
                      <span className="text-emerald-400 flex items-center justify-end gap-1"><CheckCircle size={14}/> Approved</span>
                    ) : (
                      <span className="text-gray-500">Pending</span>
                    )}
                  </td>
                </tr>
              ))}
              {logs.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-gray-500">
                    No attendance logs found.
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
