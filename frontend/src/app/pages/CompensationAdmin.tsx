import { useState, useEffect } from "react";
import { ThemeProvider } from "next-themes";
import { useAuth } from "../context/AuthContext";
import { DollarSign, Download, Award, Briefcase } from "lucide-react";

export function CompensationAdmin() {
  const { hasPermission } = useAuth();
  const isManager = hasPermission("compensation.manage");

  if (!isManager) {
    return (
      <div className="h-full flex items-center justify-center bg-[#0b0f1a] text-slate-400">
        You do not have permission to view compensation data.
      </div>
    );
  }

  // Mock data as backend endpoint doesn't exist yet for compensation
  const bands = [
    { id: 1, job_title: "Software Engineer I", min_salary: 60000, mid_salary: 80000, max_salary: 100000, currency: "USD" },
    { id: 2, job_title: "Software Engineer II", min_salary: 80000, mid_salary: 105000, max_salary: 130000, currency: "USD" },
    { id: 3, job_title: "Senior Engineer", min_salary: 110000, mid_salary: 140000, max_salary: 170000, currency: "USD" },
    { id: 4, job_title: "Engineering Manager", min_salary: 140000, mid_salary: 175000, max_salary: 210000, currency: "USD" }
  ];

  const equity = [
    { id: 1, employee_name: "Alice Johnson", grant_type: "ESOP", total_shares: 10000, vested_shares: 2500, vesting_schedule: "4-year standard", grant_date: "2024-01-15" },
    { id: 2, employee_name: "Bob Smith", grant_type: "RSU", total_shares: 5000, vested_shares: 5000, vesting_schedule: "Immediate", grant_date: "2023-06-01" },
    { id: 3, employee_name: "Charlie Davis", grant_type: "Phantom", total_shares: 2000, vested_shares: 500, vesting_schedule: "2-year cliff", grant_date: "2025-02-10" }
  ];

  const getBadgeClass = (type: string) => {
    switch (type) {
      case "ESOP": return "bg-indigo-500/10 text-indigo-400 border border-indigo-500/20";
      case "RSU": return "bg-green-500/10 text-green-400 border border-green-500/20";
      case "Phantom": return "bg-amber-500/10 text-amber-500 border border-amber-500/20";
      default: return "bg-slate-500/10 text-slate-400 border border-slate-500/20";
    }
  };

  return (
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <div className="h-full w-full flex-1 overflow-y-auto bg-[#0b0f1a] text-slate-200 p-8">
        <div className="max-w-6xl mx-auto space-y-8">
          
          <div className="bg-[#141929] border border-white/5 p-6 rounded-xl flex justify-between items-center shadow-lg">
            <div>
              <h1 className="text-2xl font-bold text-white mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>Compensation & Equity Planning</h1>
              <p className="text-sm text-slate-400">Manage salary parity bands and track employee stock option grants.</p>
            </div>
            <button className="flex items-center gap-2 bg-white/5 border border-white/10 text-white px-4 py-2 rounded-lg font-semibold text-sm hover:bg-white/10 transition-colors">
              <Download size={16} /> Export Cap Table
            </button>
          </div>

          <div>
            <h2 className="text-lg font-bold text-white mb-4 flex items-center gap-2" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
              <Briefcase size={20} className="text-[#00e07a]" /> Salary Bands (Pay Equity)
            </h2>
            <div className="bg-[#141929] border border-white/5 rounded-xl overflow-hidden">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr>
                    <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 bg-white/5">Job Title</th>
                    <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 bg-white/5">Min Salary</th>
                    <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 bg-white/5">Mid Salary</th>
                    <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 bg-white/5">Max Salary</th>
                    <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 bg-white/5">Currency</th>
                  </tr>
                </thead>
                <tbody>
                  {bands.map(b => (
                    <tr key={b.id} className="border-b border-white/5 hover:bg-white/[0.02]">
                      <td className="p-4 font-semibold text-white">{b.job_title}</td>
                      <td className="p-4 font-mono text-slate-300">{b.min_salary.toLocaleString()}</td>
                      <td className="p-4 font-mono text-slate-300">{b.mid_salary.toLocaleString()}</td>
                      <td className="p-4 font-mono text-slate-300">{b.max_salary.toLocaleString()}</td>
                      <td className="p-4 text-sm text-slate-400">{b.currency}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div>
            <h2 className="text-lg font-bold text-white mb-4 flex items-center gap-2" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
              <Award size={20} className="text-[#00b8ff]" /> Equity Grants (Cap Table Ledger)
            </h2>
            <div className="bg-[#141929] border border-white/5 rounded-xl overflow-hidden">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr>
                    <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 bg-white/5">Employee</th>
                    <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 bg-white/5">Grant Type</th>
                    <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 bg-white/5">Total Shares</th>
                    <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 bg-white/5">Vested Shares</th>
                    <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 bg-white/5">Vesting Schedule</th>
                    <th className="p-4 text-xs font-semibold text-slate-400 uppercase border-b border-white/5 bg-white/5">Grant Date</th>
                  </tr>
                </thead>
                <tbody>
                  {equity.map(e => (
                    <tr key={e.id} className="border-b border-white/5 hover:bg-white/[0.02]">
                      <td className="p-4 font-semibold text-white">{e.employee_name}</td>
                      <td className="p-4">
                        <span className={`px-2 py-1 rounded-full text-xs font-bold ${getBadgeClass(e.grant_type)}`}>
                          {e.grant_type}
                        </span>
                      </td>
                      <td className="p-4 font-semibold text-white">{e.total_shares.toLocaleString()}</td>
                      <td className="p-4 font-semibold text-[#00e07a]">{e.vested_shares.toLocaleString()}</td>
                      <td className="p-4 text-sm text-slate-300">{e.vesting_schedule}</td>
                      <td className="p-4 text-sm text-slate-400">
                        {new Date(e.grant_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>
    </ThemeProvider>
  );
}
