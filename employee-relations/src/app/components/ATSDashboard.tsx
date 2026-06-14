import { useState, useEffect } from "react";
import { 
  Users, 
  Briefcase, 
  Calendar, 
  TrendingUp, 
  CheckCircle, 
  ArrowRight,
  TrendingDown,
  ShieldAlert,
  Clock
} from "lucide-react";
import { SpinningDonut } from './SpinningDonut';

type ELRDashboardProps = {
  onViewChange: (view: any) => void;
};

export function ATSDashboard({ onViewChange }: ELRDashboardProps) {
  const [cases, setCases] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Fetch live cases from the ESM API
  useEffect(() => {
    const basePath = window.location.hostname === 'localhost' ? '/respawn-logics' : '';
    fetch(`${basePath}/api/index.php?route=esm&action=agent_queue`)
      .then((res) => res.json())
      .then((data) => {
        if (data.success && Array.isArray(data.data)) {
          const elrCases = data.data.filter((t: any) => t.team_name === 'Employee Relations' || t.is_confidential == 1);
          setCases(elrCases);
        } else {
          setCases([]);
        }
        setLoading(false);
      })
      .catch((err) => {
        console.error("Error loading ELR cases:", err);
        setLoading(false);
      });
  }, []);

  // Compute live values from cases
  const totalCases = cases.length;
  const openCases = cases.filter(c => c.status !== 'Closed' && c.status !== 'Resolved').length;
  const resolvedCases = cases.filter(c => c.status === 'Closed' || c.status === 'Resolved').length;
  const confidentialCases = cases.filter(c => c.is_confidential == 1).length;

  const metrics = [
    { label: "Total Cases", value: totalCases, icon: <Briefcase size={20} />, color: "from-blue-500 to-cyan-500" },
    { label: "Open Investigations", value: openCases, icon: <Clock size={20} />, color: "from-orange-500 to-yellow-500" },
    { label: "Resolved Cases", value: resolvedCases, icon: <CheckCircle size={20} />, color: "from-emerald-500 to-green-500" },
    { label: "Confidential", value: confidentialCases, icon: <ShieldAlert size={20} />, color: "from-red-500 to-rose-600" },
  ];

  return (
    <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#0b0f1a] text-slate-900 dark:text-white overflow-y-auto transition-colors duration-300">
      <div className="p-8">
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-3xl font-bold tracking-tight mb-2 bg-gradient-to-r from-[#00e07a] to-[#06b6d4] bg-clip-text text-transparent drop-shadow-[0_0_8px_rgba(0,224,122,0.3)]">ELR Overview</h1>
            <p className="text-slate-500 dark:text-slate-400 text-sm">Monitor employee relations health and investigations.</p>
          </div>
          <button 
            onClick={() => onViewChange("Cases")}
            className="px-4 py-2 bg-white dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.05] rounded-lg text-sm font-medium hover:border-[#00e07a]/50 dark:hover:bg-white/[0.04] transition-all flex items-center gap-2 shadow-sm"
          >
            View All Cases
            <ArrowRight size={16} />
          </button>
        </div>

        {/* Metrics Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {metrics.map((metric, i) => (
            <div key={i} className="p-5 bg-white dark:bg-[#0f1422]/80 border border-gray-200 dark:border-[#2a2d36] rounded-2xl relative overflow-hidden group hover:border-[#00e07a]/30 transition-colors shadow-sm dark:shadow-none">
              <div className="flex justify-between items-start mb-4 relative z-10">
                <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${metric.color} flex items-center justify-center text-white shadow-lg shadow-black/20`}>
                  {metric.icon}
                </div>
              </div>
              <div className="relative z-10">
                <div className="text-2xl font-bold text-slate-800 dark:text-white">{loading ? "-" : metric.value}</div>
                <div className="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">{metric.label}</div>
              </div>
            </div>
          ))}
        </div>

        {/* Main Content Area */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <div className="lg:col-span-2">
            <div className="flex items-center gap-2 mb-4">
              <AlertCircle size={16} className="text-[#f5a623]" />
              <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">Recent Open Cases</h2>
            </div>
            
            <div className="space-y-4">
              {loading ? (
                <div className="flex flex-col items-center justify-center py-8">
                   <SpinningDonut />
                   <div className="mt-2 text-[#00e07a]/80 tracking-widest uppercase text-xs font-bold animate-pulse">Loading recent cases...</div>
                </div>
              ) : cases.filter(c => c.status !== 'Closed' && c.status !== 'Resolved').slice(0, 5).length === 0 ? (
                <div className="text-center py-12 text-slate-500 bg-gray-50 dark:bg-white/[0.01] rounded-xl border border-gray-200 dark:border-white/[0.02]">
                  <CheckCircle size={32} className="mx-auto mb-3 text-[#00e07a]/50" />
                  <p>All clear! No active cases require attention.</p>
                </div>
              ) : (
                cases.filter(c => c.status !== 'Closed' && c.status !== 'Resolved').slice(0, 5).map((c, i) => (
                  <div key={i} className="flex items-center justify-between p-4 bg-white dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.03] rounded-xl hover:border-[#00e07a]/30 dark:hover:bg-white/[0.04] cursor-pointer transition-colors shadow-sm dark:shadow-none" onClick={() => onViewChange("Cases")}>
                    <div className="flex items-center gap-4">
                      <div className="w-10 h-10 rounded-full bg-gray-100 dark:bg-white/[0.05] flex items-center justify-center text-xs font-bold text-slate-600 dark:text-slate-300">
                        {c.employee_name ? c.employee_name.substring(0, 2).toUpperCase() : '?'}
                      </div>
                      <div>
                        <div className="font-medium text-slate-800 dark:text-slate-200 text-sm mb-0.5">{c.ticket_number} • {c.subject || c.type_name}</div>
                        <div className="text-xs text-slate-500 dark:text-slate-400">{c.employee_name || 'Unknown Employee'}</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-4">
                      <span className="text-xs font-medium px-2 py-1 rounded bg-orange-500/20 text-orange-400 border border-orange-500/30">
                        {c.status}
                      </span>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          <div className="bg-[#0a0c14]/0">
            <div className="flex items-center gap-2 mb-4">
              <TrendingDown size={16} className="text-[#00e07a]" />
              <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">Quick Actions</h2>
            </div>
            
            <div className="space-y-3">
              <button onClick={() => onViewChange("Cases")} className="w-full text-left p-4 rounded-xl bg-white dark:bg-[#0f1422]/80 border border-gray-200 dark:border-[#2a2d36] hover:border-[#00e07a]/50 transition-colors group shadow-sm dark:shadow-none">
                <div className="flex items-center justify-between mb-1">
                  <span className="font-medium text-slate-800 dark:text-white group-hover:text-[#00e07a] transition-colors">Start Investigation</span>
                  <ArrowRight size={16} className="text-slate-400 group-hover:text-[#00e07a] transition-colors" />
                </div>
                <div className="text-xs text-slate-500 dark:text-slate-400">Open a new confidential case file</div>
              </button>
              
              <button 
                onClick={() => onViewChange("Analytics")}
                className="w-full text-left p-4 rounded-xl bg-white dark:bg-[#0f1422]/80 border border-gray-200 dark:border-[#2a2d36] hover:border-[#00e07a]/50 transition-colors group shadow-sm dark:shadow-none"
              >
                <div className="flex items-center justify-between mb-1">
                  <span className="font-medium text-slate-800 dark:text-white group-hover:text-[#00e07a] transition-colors">View Analytics</span>
                  <ArrowRight size={16} className="text-slate-400 group-hover:text-[#00e07a] transition-colors" />
                </div>
                <div className="text-xs text-slate-500 dark:text-slate-400">Review department grievance metrics</div>
              </button>
            </div>
          </div>

        </div>
      </div>
    </main>
  );
}
