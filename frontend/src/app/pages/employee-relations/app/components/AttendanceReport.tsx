import { useState, useEffect } from "react";
import { useAuth } from "../../../../context/AuthContext";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  Calendar, 
  Users, 
  Search, 
  Filter, 
  Loader2, 
  AlertCircle,
  FileText,
  Clock,
  UserCheck,
  AlertTriangle,
  PlaneTakeoff,
  Activity
} from "lucide-react";

interface Summary {
  present: number;
  late: number;
  on_leave: number;
  absent: number;
  rest_day: number;
}

interface ReportRow {
  employee_id: string;
  name: string;
  department: string;
  date: string;
  status: "Present" | "Late" | "On Leave" | "Absent" | "Rest Day" | string;
  time_in: string | null;
  time_out: string | null;
  detail: string | null;
}

interface ReportResponse {
  success: boolean;
  start_date: string;
  end_date: string;
  departments: string[];
  summary: Summary;
  rows: ReportRow[];
  error?: string;
}

export function AttendanceReport() {
  const { hasPermission } = useAuth();
  
  // Last 7 days helper
  const getPastDateStr = (daysAgo: number) => {
    const d = new Date();
    d.setDate(d.getDate() - daysAgo);
    return d.toISOString().split("T")[0];
  };

  const [startDate, setStartDate] = useState(getPastDateStr(7));
  const [endDate, setEndDate] = useState(getPastDateStr(0));
  const [department, setDepartment] = useState("");
  const [search, setSearch] = useState("");

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  const [departments, setDepartments] = useState<string[]>([]);
  const [summary, setSummary] = useState<Summary>({ present: 0, late: 0, on_leave: 0, absent: 0, rest_day: 0 });
  const [rows, setRows] = useState<ReportRow[]>([]);

  const fetchReport = async () => {
    setLoading(true);
    setError(null);
    try {
      const queryParams = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        department,
        search
      });
      const res = await apiFetch(`/api/index.php?route=attendance&action=report&${queryParams.toString()}`);
      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data: ReportResponse = await res.json();
      
      if (data.success) {
        setDepartments(data.departments || []);
        setSummary(data.summary || { present: 0, late: 0, on_leave: 0, absent: 0, rest_day: 0 });
        setRows(data.rows || []);
      } else {
        setError(data.error || "Failed to load consolidated attendance report.");
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || "An unexpected error occurred while communicating with database.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchReport();
  }, [startDate, endDate, department, search]);

  const getStatusBadgeStyle = (status: string) => {
    switch (status) {
      case "Present": return "bg-[#00e07a]/15 text-[#00e07a] border-[#00e07a]/25";
      case "Late": return "bg-amber-500/15 text-amber-400 border-amber-500/25";
      case "On Leave": return "bg-blue-500/15 text-blue-400 border-blue-500/25";
      case "Absent": return "bg-red-500/15 text-red-400 border-red-500/25";
      default: return "bg-gray-500/15 text-gray-400 border-gray-500/25";
    }
  };

  const handleExport = () => {
    const isLocal = window.location.hostname === "localhost";
    const basePath = isLocal ? "/respawn-logics" : "";
    const url = `${window.location.origin}${basePath}/api/index.php?route=export&action=attendance&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;
    window.open(url, '_blank');
  };

  return (
    <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#0b0f1a] text-slate-900 dark:text-white overflow-hidden transition-colors duration-300">
      
      {/* Top Header Filter Bar */}
      <div className="p-8 border-b border-gray-200 dark:border-white/[0.04] shrink-0 space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight mb-2 bg-gradient-to-r from-[#10b981] to-[#0ea5e9] bg-clip-text text-transparent drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]">
              Attendance & Leave Report
            </h1>
            <p className="text-slate-500 dark:text-slate-400 text-sm">Consolidated organizational attendance audit log for Employee Relations analysis.</p>
          </div>
          <div className="flex gap-3">
            {hasPermission("attendance.view") && (
              <button 
                onClick={handleExport}
                className="px-4 py-2 bg-white dark:bg-[#1a2035] border border-gray-200 dark:border-white/[0.06] hover:bg-gray-50 dark:hover:bg-white/[0.04] rounded-lg font-bold text-sm text-slate-700 dark:text-white transition-all shadow-sm flex items-center gap-2 cursor-pointer"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export CSV
              </button>
            )}
            <button 
              onClick={fetchReport}
              disabled={loading}
              className="px-4 py-2 bg-white dark:bg-[#1a2035] border border-gray-200 dark:border-white/[0.06] hover:bg-gray-50 dark:hover:bg-white/[0.04] rounded-lg font-bold text-sm text-slate-700 dark:text-white transition-all shadow-sm flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              {loading ? <Loader2 className="w-4 h-4 animate-spin" /> : <Activity className="w-4 h-4" />}
              Refresh Report
            </button>
          </div>
        </div>

        {/* Filter Controls */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 bg-white dark:bg-[#111625]/40 border border-gray-200 dark:border-white/5 p-4 rounded-xl shadow-sm">
          <div>
            <label className="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Start Date</label>
            <div className="relative">
              <input 
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="w-full h-10 bg-gray-50 dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.06] rounded-lg px-3 text-sm focus:outline-none focus:border-[#10b981]/50 [color-scheme:dark]"
              />
            </div>
          </div>
          <div>
            <label className="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">End Date</label>
            <div className="relative">
              <input 
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="w-full h-10 bg-gray-50 dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.06] rounded-lg px-3 text-sm focus:outline-none focus:border-[#10b981]/50 [color-scheme:dark]"
              />
            </div>
          </div>
          <div>
            <label className="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Department</label>
            <select
              value={department}
              onChange={(e) => setDepartment(e.target.value)}
              className="w-full h-10 bg-gray-50 dark:bg-[#0b0f1a] border border-gray-200 dark:border-white/[0.06] rounded-lg px-3 text-sm focus:outline-none focus:border-[#10b981]/50"
            >
              <option value="">All Departments</option>
              {departments.map((dept, i) => (
                <option key={i} value={dept}>{dept}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Search Employee</label>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" size={15} />
              <input 
                type="text"
                placeholder="Search by name..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full h-10 bg-gray-50 dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.06] rounded-lg pl-9 pr-3 text-sm focus:outline-none focus:border-[#10b981]/50"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Main Workspace Body */}
      <div className="flex-1 overflow-y-auto p-8 space-y-6">

        {error ? (
          <div className="flex flex-col items-center justify-center py-16 px-6 bg-red-500/10 border border-red-500/20 rounded-xl max-w-xl mx-auto text-center space-y-3">
            <AlertCircle className="w-10 h-10 text-red-500" />
            <h3 className="text-lg font-bold text-white">Report Generation Failed</h3>
            <p className="text-sm text-gray-400">{error}</p>
            <button 
              onClick={fetchReport}
              className="mt-2 px-4 py-2 bg-white/5 hover:bg-white/10 text-white border border-white/10 rounded-lg text-xs transition-all"
            >
              Retry Generation
            </button>
          </div>
        ) : (
          <>
            {/* Summary Cards */}
            <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
              
              {/* Present */}
              <div className="bg-white dark:bg-[#1a2035] border border-gray-200 dark:border-white/[0.04] p-4 rounded-xl flex items-center justify-between shadow-sm">
                <div>
                  <span className="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Present</span>
                  <span className="text-2xl font-bold text-[#00e07a] mt-1 block">{summary.present}</span>
                </div>
                <div className="w-10 h-10 rounded-full bg-[#00e07a]/10 flex items-center justify-center text-[#00e07a]">
                  <UserCheck size={18} />
                </div>
              </div>

              {/* Late */}
              <div className="bg-white dark:bg-[#1a2035] border border-gray-200 dark:border-white/[0.04] p-4 rounded-xl flex items-center justify-between shadow-sm">
                <div>
                  <span className="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Late</span>
                  <span className="text-2xl font-bold text-amber-400 mt-1 block">{summary.late}</span>
                </div>
                <div className="w-10 h-10 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-400">
                  <Clock size={18} />
                </div>
              </div>

              {/* On Leave */}
              <div className="bg-white dark:bg-[#1a2035] border border-gray-200 dark:border-white/[0.04] p-4 rounded-xl flex items-center justify-between shadow-sm">
                <div>
                  <span className="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">On Leave</span>
                  <span className="text-2xl font-bold text-blue-400 mt-1 block">{summary.on_leave}</span>
                </div>
                <div className="w-10 h-10 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-400">
                  <PlaneTakeoff size={18} />
                </div>
              </div>

              {/* Absent */}
              <div className="bg-white dark:bg-[#1a2035] border border-gray-200 dark:border-white/[0.04] p-4 rounded-xl flex items-center justify-between shadow-sm">
                <div>
                  <span className="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Absent</span>
                  <span className="text-2xl font-bold text-red-400 mt-1 block">{summary.absent}</span>
                </div>
                <div className="w-10 h-10 rounded-full bg-red-500/10 flex items-center justify-center text-red-400">
                  <AlertTriangle size={18} />
                </div>
              </div>

              {/* Rest Day */}
              <div className="bg-white dark:bg-[#1a2035] border border-gray-200 dark:border-white/[0.04] p-4 rounded-xl flex items-center justify-between shadow-sm">
                <div>
                  <span className="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Rest Day</span>
                  <span className="text-2xl font-bold text-gray-400 mt-1 block">{summary.rest_day}</span>
                </div>
                <div className="w-10 h-10 rounded-full bg-gray-500/10 flex items-center justify-center text-gray-400">
                  <Calendar size={18} />
                </div>
              </div>

            </div>

            {/* Table */}
            <div className="bg-white dark:bg-[#1a2035] border border-gray-200 dark:border-white/[0.04] rounded-xl overflow-hidden shadow-sm relative">
              
              {loading && (
                <div className="absolute inset-0 bg-[#111625]/25 backdrop-blur-[1px] flex items-center justify-center z-10">
                  <Loader2 className="w-8 h-8 animate-spin text-[#10b981]" />
                </div>
              )}

              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead className="border-b border-gray-200 dark:border-white/[0.04] bg-gray-50 dark:bg-white/[0.02] text-xs font-bold text-slate-500 uppercase tracking-wider">
                    <tr>
                      <th className="p-4 pl-6">Employee</th>
                      <th className="p-4">Department</th>
                      <th className="p-4">Date</th>
                      <th className="p-4">Time In</th>
                      <th className="p-4">Time Out</th>
                      <th className="p-4 text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-150 dark:divide-white/[0.03]">
                    {rows.map((row, i) => (
                      <tr key={i} className="hover:bg-gray-50 dark:hover:bg-white/[0.01] transition-colors">
                        <td className="p-4 pl-6">
                          <div className="font-semibold text-slate-800 dark:text-white">{row.name}</div>
                          <div className="text-[10px] text-gray-500 font-mono mt-0.5">{row.employee_id}</div>
                        </td>
                        <td className="p-4 text-sm text-slate-600 dark:text-gray-300">{row.department}</td>
                        <td className="p-4 text-sm font-mono text-slate-600 dark:text-gray-400">{row.date}</td>
                        <td className="p-4 text-sm font-mono text-slate-600 dark:text-gray-300">
                          {row.time_in ? row.time_in.substring(0, 5) : "—"}
                        </td>
                        <td className="p-4 text-sm font-mono text-slate-600 dark:text-gray-300">
                          {row.time_out ? row.time_out.substring(0, 5) : "—"}
                        </td>
                        <td className="p-4 text-center">
                          <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold border ${getStatusBadgeStyle(row.status)}`}>
                            {row.status}
                            {row.status === "On Leave" && row.detail && (
                              <span className="opacity-75 font-normal"> ({row.detail})</span>
                            )}
                          </span>
                        </td>
                      </tr>
                    ))}

                    {rows.length === 0 && !loading && (
                      <tr>
                        <td colSpan={6} className="py-12 text-center text-gray-500 text-sm">
                          <FileText className="w-10 h-10 text-gray-600 mx-auto mb-2" />
                          No attendance records found matching the criteria.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

            </div>
          </>
        )}

      </div>
    </main>
  );
}
