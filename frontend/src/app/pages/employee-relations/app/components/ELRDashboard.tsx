import { useState, useEffect } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  Users, 
  Briefcase, 
  Clock, 
  CheckCircle, 
  ShieldAlert, 
  AlertCircle, 
  ArrowRight,
  TrendingUp,
  FileText
} from "lucide-react";
import { SpinningDonut } from "./SpinningDonut";

interface ELRDashboardProps {
  onViewChange: (view: string) => void;
}

export function ELRDashboard({ onViewChange }: ELRDashboardProps) {
  const [cases, setCases] = useState<any[]>([]);
  const [analytics, setAnalytics] = useState<any>({ trend: [], channels: [] });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadDashboardData = async () => {
      setLoading(true);
      setError(null);
      try {
        const [casesRes, analyticsRes] = await Promise.all([
          apiFetch("/api/index.php?route=elr&action=cases"),
          apiFetch("/api/index.php?route=elr&action=analytics")
        ]);

        if (!casesRes.ok || !analyticsRes.ok) throw new Error("Failed to communicate with ELR endpoints");

        const casesData = await casesRes.json();
        const analyticsData = await analyticsRes.json();

        if (casesData.success) {
          setCases(casesData.cases || []);
        } else {
          setError(casesData.error || "Failed to fetch cases list");
        }

        if (analyticsData.success) {
          setAnalytics(analyticsData);
        }
      } catch (err: any) {
        console.error(err);
        setError(err.message || "Unable to load employee relations overview.");
      } finally {
        setLoading(false);
      }
    };

    loadDashboardData();
  }, []);

  // Compute stats
  const totalCases = cases.length;
  const openCases = cases.filter(c => c.status !== "Closed" && c.status !== "Resolved").length;
  const resolvedCases = cases.filter(c => c.status === "Closed" || c.status === "Resolved").length;
  const confidentialCases = cases.filter(c => c.is_confidential == 1).length;

  const metrics = [
    { label: "Total Cases Registered", value: totalCases, icon: <Briefcase size={20} />, color: "from-blue-500 to-cyan-500" },
    { label: "Open Investigations", value: openCases, icon: <Clock size={20} />, color: "from-orange-500 to-yellow-500" },
    { label: "Resolved / Closed", value: resolvedCases, icon: <CheckCircle size={20} />, color: "from-emerald-500 to-green-500" },
    { label: "Confidential Cases", value: confidentialCases, icon: <ShieldAlert size={20} />, color: "from-red-500 to-rose-600" },
  ];

  const recentOpenCases = cases
    .filter(c => c.status !== "Closed" && c.status !== "Resolved")
    .slice(0, 5);

  return (
    <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#0b0f1a] text-foreground overflow-y-auto transition-colors duration-300">
      <div className="p-8">
        
        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
              ELR Overview
            </h1>
            <p className="text-slate-500 dark:text-slate-400 text-sm">Monitor employee relations health, active cases, and investigations.</p>
          </div>
          <button 
            onClick={() => onViewChange("Cases")}
            className="px-4 py-2 bg-card border border-border rounded-lg text-sm font-medium hover:border-[#00e07a]/50 dark:hover:bg-white/[0.04] transition-all flex items-center gap-2 shadow-sm text-foreground cursor-pointer"
          >
            View Cases Board
            <ArrowRight size={16} />
          </button>
        </div>

        {error && (
          <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm mb-6 flex items-start gap-3">
            <AlertCircle className="w-5 h-5 flex-shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {/* Metrics Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {metrics.map((metric, i) => (
            <div key={i} className="p-5 bg-card border border-border rounded-2xl relative overflow-hidden shadow-sm hover:border-[#00e07a]/20 transition-all">
              <div className="flex justify-between items-start mb-4 relative z-10">
                <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${metric.color} flex items-center justify-center text-slate-900 dark:text-white shadow-lg shadow-black/20`}>
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

        {/* Content Layout */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          {/* Left Column: Recent Open Cases */}
          <div className="lg:col-span-2 space-y-4">
            <div className="flex items-center gap-2 mb-2">
              <AlertCircle size={16} className="text-amber-500" />
              <h2 className="text-xs font-bold uppercase tracking-wider text-slate-500">Recent Active Investigations</h2>
            </div>

            <div className="bg-card/80 border border-border rounded-2xl p-5 space-y-4">
              {loading ? (
                <div className="text-center py-8 text-gray-500 text-sm">Loading cases...</div>
              ) : recentOpenCases.length === 0 ? (
                <div className="text-center py-8 text-gray-500 text-sm">
                  <FileText className="w-8 h-8 text-gray-600 mx-auto mb-2" />
                  No active employee relations cases at this time.
                </div>
              ) : (
                <div className="divide-y divide-white/[0.04] space-y-4">
                  {recentOpenCases.map((c) => (
                    <div key={c.id} className="pt-4 first:pt-0 flex justify-between items-center">
                      <div>
                        <div className="flex items-center gap-2">
                          <span className="font-semibold text-foreground hover:underline cursor-pointer" onClick={() => onViewChange("Cases")}>
                            {c.case_number}
                          </span>
                          <span className="text-xs text-gray-500">({c.employee_id})</span>
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">{c.case_type_name || "General Inquiry"} • {c.department}</p>
                      </div>
                      <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase border bg-blue-500/10 text-blue-400 border-blue-500/20`}>
                        {c.status}
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Right Column: Case Types Pie chart or visual distribution */}
          <div className="space-y-4">
            <div className="flex items-center gap-2 mb-2">
              <TrendingUp size={16} className="text-[#00e07a]" />
              <h2 className="text-xs font-bold uppercase tracking-wider text-slate-500">Case Distribution By Type</h2>
            </div>

            <div className="bg-card/80 border border-border rounded-2xl p-6 flex flex-col items-center">
              {loading ? (
                <div className="text-center py-8 text-gray-500 text-sm">Loading chart...</div>
              ) : analytics.channels && analytics.channels.length > 0 ? (
                <>
                  <div className="w-full h-40 flex justify-center mb-6">
                    <SpinningDonut data={analytics.channels} />
                  </div>
                  <div className="w-full space-y-2 max-h-40 overflow-y-auto scrollbar-thin">
                    {analytics.channels.map((chan: any, idx: number) => (
                      <div key={idx} className="flex justify-between text-xs items-center">
                        <div className="flex items-center gap-2">
                          <div className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: chan.color }}></div>
                          <span className="text-gray-300 font-medium">{chan.source}</span>
                        </div>
                        <span className="font-mono text-muted-foreground font-bold">{chan.applications} cases ({chan.percentage}%)</span>
                      </div>
                    ))}
                  </div>
                </>
              ) : (
                <div className="text-center py-8 text-gray-500 text-sm">No distribution metrics available.</div>
              )}
            </div>
          </div>

        </div>

      </div>
    </main>
  );
}
