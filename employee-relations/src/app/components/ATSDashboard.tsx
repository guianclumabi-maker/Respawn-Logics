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
    <main className="flex-1 flex flex-col h-full bg-[#06070a] text-white overflow-y-auto">
      <div className="p-8">
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-3xl font-bold tracking-tight mb-2 bg-gradient-to-r from-red-400 to-orange-500 bg-clip-text text-transparent">ELR Overview</h1>
            <p className="text-slate-400 text-sm">Monitor employee relations health and investigations.</p>
          </div>
          <button 
            onClick={() => onViewChange("Cases")}
            className="h-10 px-4 bg-white/[0.03] border border-white/[0.06] rounded-lg font-medium text-sm flex items-center gap-2 hover:bg-white/[0.06] transition-colors"
          >
            View All Cases
            <ArrowRight size={16} />
          </button>
        </div>

        {/* Metrics Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {metrics.map((m, i) => (
            <div key={i} className="bg-[#0a0c14]/80 backdrop-blur-xl border border-white/[0.04] p-6 rounded-2xl shadow-xl shadow-black/20 hover:border-white/[0.08] transition-all relative overflow-hidden group">
              <div className={`absolute top-0 right-0 w-32 h-32 bg-gradient-to-br ${m.color} opacity-5 blur-3xl group-hover:opacity-10 transition-opacity`} />
              <div className="flex items-center justify-between mb-4 relative z-10">
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center bg-gradient-to-br ${m.color} bg-opacity-10 shadow-inner`}>
                  <div className="text-white drop-shadow-md">
                    {m.icon}
                  </div>
                </div>
              </div>
              <div className="relative z-10">
                <div className="text-3xl font-bold mb-1 tracking-tight">{loading ? "-" : m.value}</div>
                <div className="text-sm text-slate-400 font-medium">{m.label}</div>
              </div>
            </div>
          ))}
        </div>

        {/* Main Content Area */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <div className="lg:col-span-2 bg-[#0a0c14]/80 backdrop-blur-xl border border-white/[0.04] rounded-2xl p-6 shadow-xl shadow-black/20">
            <div className="flex justify-between items-center mb-6">
              <h3 className="font-semibold text-lg flex items-center gap-2">
                <Clock size={18} className="text-orange-400" />
                Recent Open Cases
              </h3>
            </div>
            
            <div className="space-y-4">
              {loading ? (
                <div className="text-center py-8 text-slate-500">Loading recent cases...</div>
              ) : cases.filter(c => c.status !== 'Closed' && c.status !== 'Resolved').slice(0, 5).length === 0 ? (
                <div className="text-center py-12 text-slate-500 bg-white/[0.01] rounded-xl border border-white/[0.02]">
                  <CheckCircle size={32} className="mx-auto mb-3 text-emerald-500/50" />
                  <p>All clear! No active cases require attention.</p>
                </div>
              ) : (
                cases.filter(c => c.status !== 'Closed' && c.status !== 'Resolved').slice(0, 5).map((c, i) => (
                  <div key={i} className="flex items-center justify-between p-4 bg-white/[0.02] border border-white/[0.03] rounded-xl hover:bg-white/[0.04] cursor-pointer transition-colors" onClick={() => onViewChange("Cases")}>
                    <div className="flex items-center gap-4">
                      <div className="w-10 h-10 rounded-full bg-white/[0.05] flex items-center justify-center text-xs font-bold text-slate-300">
                        {c.employee_name ? c.employee_name.substring(0, 2).toUpperCase() : '?'}
                      </div>
                      <div>
                        <div className="font-medium text-sm mb-0.5">{c.ticket_number} • {c.subject || c.type_name}</div>
                        <div className="text-xs text-slate-400">{c.employee_name || 'Unknown Employee'}</div>
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

          <div className="bg-[#0a0c14]/80 backdrop-blur-xl border border-white/[0.04] rounded-2xl p-6 shadow-xl shadow-black/20">
            <h3 className="font-semibold text-lg mb-6 flex items-center gap-2">
              <TrendingUp size={18} className="text-emerald-400" />
              Quick Actions
            </h3>
            
            <div className="space-y-3">
              <button 
                onClick={() => onViewChange("Cases")}
                className="w-full text-left p-4 bg-white/[0.02] border border-white/[0.04] rounded-xl hover:bg-white/[0.06] hover:border-white/[0.1] transition-all group flex items-center justify-between"
              >
                <div>
                  <div className="font-medium text-sm group-hover:text-red-400 transition-colors">Start Investigation</div>
                  <div className="text-xs text-slate-400 mt-1">Open a new confidential case file</div>
                </div>
                <ArrowRight size={16} className="text-slate-500 group-hover:text-red-400 group-hover:translate-x-1 transition-all" />
              </button>
              
              <button 
                onClick={() => onViewChange("Analytics")}
                className="w-full text-left p-4 bg-white/[0.02] border border-white/[0.04] rounded-xl hover:bg-white/[0.06] hover:border-white/[0.1] transition-all group flex items-center justify-between"
              >
                <div>
                  <div className="font-medium text-sm group-hover:text-orange-400 transition-colors">View Analytics</div>
                  <div className="text-xs text-slate-400 mt-1">Review department grievance metrics</div>
                </div>
                <ArrowRight size={16} className="text-slate-500 group-hover:text-orange-400 group-hover:translate-x-1 transition-all" />
              </button>
            </div>
          </div>

        </div>
      </div>
    </main>
  );
}
