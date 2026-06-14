import React, { useState, useEffect } from "react";
import { Plus, Search, Filter, AlertCircle, Clock, Lock } from "lucide-react";
import { SpinningDonut } from './SpinningDonut';

export function PipelineBoard({ onViewChange }: { onViewChange: (v: string) => void }) {
  const [cases, setCases] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const basePath = window.location.hostname === 'localhost' ? '/respawn-logics' : '';
    fetch(`${basePath}/api/index.php?route=esm&action=agent_queue`)
      .then(res => res.json())
      .then(data => {
        if (data.success && Array.isArray(data.data)) {
          const elrCases = data.data.filter((t: any) => t.team_name === 'Employee Relations' || t.is_confidential == 1);
          setCases(elrCases.map((t: any) => ({
            id: t.id,
            is_confidential: t.is_confidential,
            case_number: t.ticket_number,
            case_type_name: t.type_name,
            severity: 'High', // Defaulting since ESM doesn't have severity explicitly mapped yet
            status: t.status,
            investigator_id: t.employee_name, // Mapping employee_name here for display
            date_opened: t.created_at
          })));
        } else {
          setCases([]);
        }
        setLoading(false);
      })
      .catch(err => {
        console.error("Error fetching cases:", err);
        setLoading(false);
      });
  }, []);

  const getSeverityColor = (sev: string) => {
    switch (sev) {
      case 'Critical': return 'bg-red-500/20 text-red-400 border-red-500/30';
      case 'High': return 'bg-orange-500/20 text-orange-400 border-orange-500/30';
      case 'Medium': return 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30';
      default: return 'bg-green-500/20 text-green-400 border-green-500/30';
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'Open': return 'bg-blue-500/20 text-blue-400';
      case 'Under Review': return 'bg-purple-500/20 text-purple-400';
      case 'Investigating': return 'bg-orange-500/20 text-orange-400';
      case 'Pending Approval': return 'bg-yellow-500/20 text-yellow-400';
      case 'Resolved': return 'bg-green-500/20 text-green-400';
      case 'Closed': return 'bg-gray-500/20 text-gray-400';
      default: return 'bg-gray-500/20 text-gray-400';
    }
  };

  return (
    <main className="flex-1 flex flex-col h-full bg-[#06070a] text-white overflow-hidden">
      <div className="p-8 border-b border-white/[0.04] shrink-0">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-3xl font-bold tracking-tight mb-2 bg-gradient-to-r from-red-400 to-orange-500 bg-clip-text text-transparent">ELR Cases</h1>
            <p className="text-slate-400 text-sm">Manage employee relations cases and investigations</p>
          </div>
          <button 
            className="h-10 px-4 bg-gradient-to-r from-red-500 to-orange-600 text-white rounded-lg font-medium text-sm flex items-center gap-2 hover:from-red-400 hover:to-orange-500 transition-all shadow-lg shadow-red-500/20 border border-white/10"
          >
            <Plus size={16} />
            New Case
          </button>
        </div>

        <div className="flex gap-4">
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" size={16} />
            <input 
              type="text" 
              placeholder="Search by case number, employee, or department..."
              className="w-full h-10 bg-white/[0.02] border border-white/[0.06] rounded-lg pl-10 pr-4 text-sm focus:outline-none focus:border-red-500/50 focus:bg-white/[0.04] transition-all"
            />
          </div>
          <button className="h-10 px-4 bg-white/[0.02] border border-white/[0.06] rounded-lg font-medium text-sm flex items-center gap-2 hover:bg-white/[0.06] transition-colors">
            <Filter size={16} />
            Filters
          </button>
        </div>
      </div>

      <div className="flex-1 overflow-auto p-8 relative">
        <div className="absolute inset-0 bg-gradient-to-b from-red-500/5 to-transparent pointer-events-none" />
        <div className="bg-[#0a0c14]/80 backdrop-blur-xl border border-white/[0.04] rounded-xl overflow-hidden shadow-2xl shadow-black/40 relative z-10">
          <table className="w-full text-left text-sm">
            <thead className="bg-white/[0.02] border-b border-white/[0.04] text-slate-400 text-xs uppercase tracking-wider">
              <tr>
                <th className="px-6 py-4 font-medium">Case Number</th>
                <th className="px-6 py-4 font-medium">Type</th>
                <th className="px-6 py-4 font-medium">Severity</th>
                <th className="px-6 py-4 font-medium">Status</th>
                <th className="px-6 py-4 font-medium">Employee</th>
                <th className="px-6 py-4 font-medium">Date Opened</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/[0.02]">
              {loading ? (
                <tr>
                  <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                    <div className="flex flex-col items-center justify-center">
                       <SpinningDonut />
                       <div className="mt-2 text-orange-500/80 tracking-widest uppercase text-xs font-bold animate-pulse">Computing Matrix...</div>
                    </div>
                  </td>
                </tr>
              ) : cases.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-6 py-20 text-center text-slate-500">
                    <div className="flex flex-col items-center gap-3">
                      <div className="w-16 h-16 rounded-full bg-white/[0.02] flex items-center justify-center mb-2">
                        <AlertCircle className="text-slate-600" size={32} />
                      </div>
                      <div className="text-white font-medium">Queue is Empty</div>
                      <div className="text-sm">No active ELR cases found.</div>
                    </div>
                  </td>
                </tr>
              ) : cases.map((c) => (
                <tr key={c.id} className="hover:bg-white/[0.02] transition-colors cursor-pointer group">
                  <td className="px-6 py-4">
                    <div className="font-medium text-white group-hover:text-red-400 transition-colors flex items-center gap-2">
                      {c.is_confidential == 1 && <Lock size={14} className="text-red-500" />}
                      {c.case_number}
                    </div>
                  </td>
                  <td className="px-6 py-4 text-slate-300">{c.case_type_name || 'General'}</td>
                  <td className="px-6 py-4">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ${getSeverityColor(c.severity)}`}>
                      {c.severity}
                    </span>
                  </td>
                  <td className="px-6 py-4">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${getStatusColor(c.status)}`}>
                      {c.status}
                    </span>
                  </td>
                  <td className="px-6 py-4 text-slate-400">
                    {c.investigator_id || 'Unknown'}
                  </td>
                  <td className="px-6 py-4 text-slate-400">
                    <div className="flex items-center gap-2">
                      <Clock size={14} />
                      {new Date(c.date_opened).toLocaleDateString()}
                    </div>
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
