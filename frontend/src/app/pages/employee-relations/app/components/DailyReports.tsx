import { useState } from "react";
import { FileText, Download, Calendar, Filter, Search } from "lucide-react";

export function DailyReports() {
  const [search, setSearch] = useState("");

  // Generate the last 7 days of reports
  const reports = Array.from({ length: 7 }).map((_, i) => {
    const d = new Date();
    d.setDate(d.getDate() - i);
    return {
      id: `REP-${d.toISOString().split('T')[0]}`,
      date: d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }),
      title: `Daily Case Summary - ${d.toISOString().split('T')[0]}`,
      size: `${(Math.random() * 2 + 1).toFixed(1)} MB`,
      status: "Ready"
    };
  });

  return (
    <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#0b0f1a] text-slate-900 dark:text-white overflow-hidden transition-colors duration-300">
      <div className="p-8 border-b border-gray-200 dark:border-white/[0.04] shrink-0">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-3xl font-bold tracking-tight mb-2 bg-gradient-to-r from-[#10b981] to-[#0ea5e9] bg-clip-text text-transparent drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]">
              Daily Reports
            </h1>
            <p className="text-slate-500 dark:text-slate-400 text-sm">Download auto-generated daily summaries of all ELR activities.</p>
          </div>
          <button className="h-10 px-4 bg-white dark:bg-[#1a2035] border border-gray-200 dark:border-white/[0.06] rounded-lg font-bold text-sm flex items-center gap-2 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-all shadow-sm dark:shadow-none text-slate-700 dark:text-white">
            <Calendar size={16} />
            Select Date Range
          </button>
        </div>

        <div className="flex gap-4">
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" size={16} />
            <input 
              type="text" 
              placeholder="Search reports by date or title..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full h-10 bg-white dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.06] rounded-lg pl-10 pr-4 text-sm focus:outline-none focus:border-[#10b981]/50 transition-all text-slate-800 dark:text-white"
            />
          </div>
          <button className="h-10 px-4 bg-white dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.06] rounded-lg font-medium text-sm flex items-center gap-2 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors shadow-sm dark:shadow-none text-slate-700 dark:text-white">
            <Filter size={16} />
            Filters
          </button>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="bg-white dark:bg-[#1a2035] border border-gray-200 dark:border-white/[0.04] rounded-xl overflow-hidden shadow-sm">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-gray-200 dark:border-white/[0.04] bg-gray-50 dark:bg-white/[0.02]">
                <th className="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Report ID</th>
                <th className="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Date</th>
                <th className="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Title</th>
                <th className="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Size</th>
                <th className="p-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-white/[0.04]">
              {reports.filter(r => r.title.toLowerCase().includes(search.toLowerCase()) || r.date.toLowerCase().includes(search.toLowerCase())).map((report) => (
                <tr key={report.id} className="hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors group">
                  <td className="p-4 text-sm font-mono text-slate-500 dark:text-slate-400">
                    {report.id}
                  </td>
                  <td className="p-4 text-sm font-medium text-slate-700 dark:text-slate-300">
                    {report.date}
                  </td>
                  <td className="p-4 text-sm text-slate-800 dark:text-white flex items-center gap-2">
                    <FileText size={16} className="text-[#10b981]" />
                    {report.title}
                  </td>
                  <td className="p-4 text-sm text-slate-500 dark:text-slate-400">
                    {report.size}
                  </td>
                  <td className="p-4 text-right">
                    <button className="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[#10b981]/10 text-[#10b981] hover:bg-[#10b981] hover:text-white transition-colors cursor-pointer">
                      <Download size={16} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </main>
  );
}
