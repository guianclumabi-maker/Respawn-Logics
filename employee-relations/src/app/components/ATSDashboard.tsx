import { useState, useEffect } from "react";
import { 
  Users, 
  Briefcase, 
  Calendar, 
  TrendingUp, 
  CheckCircle, 
  ArrowRight,
  TrendingDown
} from "lucide-react";

type ATSDashboardProps = {
  onViewChange: (view: any) => void;
};

export function ATSDashboard({ onViewChange }: ATSDashboardProps) {
  const [candidates, setCandidates] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [dashboardData, setDashboardData] = useState<any>({
    total_candidates: 0,
    interviews_scheduled: 0,
    offers_extended: 0,
    hired_count: 0,
    screened_count: 0,
    f2f_count: 0,
    activities: []
  });

  // Fetch pre-computed live metrics from the PHP backend API
  useEffect(() => {
    const API_URL = (window.location.pathname.includes("/dist/") || window.location.pathname.includes("-dist/")
      ? "../employee_relations_api.php"
      : "./employee_relations_api.php") + "?action=dashboard";

    fetch(API_URL)
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          setDashboardData(data);
        }
        setLoading(false);
      })
      .catch((err) => {
        console.error("Error loading computed metrics for ATS Dashboard:", err);
        setLoading(false);
      });
  }, []);

  // Compute live values from server payload
  const totalCount = dashboardData.total_candidates ?? dashboardData.total_cases ?? 0;
  const activeJobsCount = 5; // Static open jobs from Jobs directory
  const interviewsCount = dashboardData.interviews_scheduled ?? 0;
  const offersCount = dashboardData.offers_extended ?? 0;
  
  const stats = [
    { label: "Total Relations Cases", value: (totalCount ?? 0).toString(), change: "+5% vs last month", isPositive: true, icon: <Users size={16} />, color: "#06b6d4" },
    { label: "Issue Types Tracking", value: "4", change: "Stable", isPositive: true, icon: <Briefcase size={16} />, color: "#ec4899" },
    { label: "Investigations Active", value: (interviewsCount ?? 0).toString(), change: "+1 vs last week", isPositive: true, icon: <Calendar size={16} />, color: "#8b5cf6" },
    { label: "Pending Resolutions", value: (offersCount ?? 0).toString(), change: "-2 vs last week", isPositive: false, icon: <CheckCircle size={16} />, color: "#10b981" },
  ];

  // Funnel counts from server payload
  const screenedCount = dashboardData.screened_count ?? 0;
  const f2fCount = dashboardData.f2f_count ?? 0;
  
  const funnelData = [
    { stage: "Grievances Reported", count: totalCount, width: "100%", color: "#06b6d4" },
    { stage: "Under Review", count: screenedCount, width: totalCount > 0 ? `${Math.max(20, Math.round((screenedCount / totalCount) * 100))}%` : "0%", color: "#8b5cf6" },
    { stage: "Active Investigations", count: f2fCount, width: totalCount > 0 ? `${Math.max(20, Math.round((f2fCount / totalCount) * 100))}%` : "0%", color: "#a855f7" },
    { stage: "Resolved Cases", count: offersCount, width: totalCount > 0 ? `${Math.max(20, Math.round((offersCount / totalCount) * 100))}%` : "0%", color: "#10b981" },
  ];

  const hireRate = totalCount > 0
    ? (((dashboardData.hired_count ?? dashboardData.resolved_count ?? 0) / totalCount) * 100).toFixed(1)
    : "0.0";

  // Sourced from server pre-computed activities feed
  const activitiesList = dashboardData.activities || [];

  // Fallback if no database cases exist
  const displayActivities = activitiesList.length > 0 ? activitiesList : [
    { name: "John Doe - Overtime Dispute", action: "filed issue", role: "HR Cases", time: "10 mins ago", type: "apply" },
    { name: "Jane Smith - Transfer Request", action: "advanced case to", role: "Review", time: "2 hours ago", type: "advance" },
    { name: "Bob Johnson - Policy Inquiry", action: "resolved case", role: "HR Cases", time: "5 hours ago", type: "offer" },
  ];

  // Map candidate counts per active position
  const activeJobs = [
    { title: "Overtime & Pay Dispute", dept: "Finance / Ops", candidates: totalCount, progress: Math.min(100, Math.round((totalCount / 10) * 100)), status: "Urgent" },
    { title: "Policy Violations Inquiry", dept: "Legal / Compliance", candidates: 2, progress: 20, status: "Active" },
    { title: "Interpersonal Disputes", dept: "HR / Administration", candidates: 3, progress: 30, status: "Active" },
    { title: "Department Transfers", dept: "Operations / People", candidates: 1, progress: 10, status: "Active" },
  ];

  return (
    <div className="flex-1 flex flex-col overflow-y-auto px-8 py-6 text-white font-sans relative scrollbar-thin" style={{ backgroundColor: "#0d0f19" }}>
      {/* Glow graphics */}
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-[#8b5cf6] blur-[120px] opacity-10 pointer-events-none z-0" />
      <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#ec4899] blur-[140px] opacity-8 pointer-events-none z-0" />

      {/* Header */}
      <div className="relative z-10 flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent" style={{ fontFamily: "'Outfit', 'Inter', sans-serif" }}>
            Relations Dashboard
          </h1>
          <p className="text-xs text-[#9ca3af] mt-1">Employee Relations case metrics and resolution center.</p>
        </div>
        <button
          onClick={() => onViewChange("Candidates")}
          className="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold transition-all hover:opacity-95 cursor-pointer bg-gradient-to-r from-[#8b5cf6] to-[#ec4899] text-white shadow-lg shadow-purple-500/10 border-0"
        >
          View Case Board
          <ArrowRight size={14} />
        </button>
      </div>

      {loading ? (
        <div className="relative z-10 flex-1 flex items-center justify-center text-xs text-gray-500">
          Loading metrics from database...
        </div>
      ) : (
        <>
          {/* Stats Grid */}
          <div className="relative z-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            {stats.map((s, idx) => (
              <div 
                key={idx}
                className="p-5 rounded-2xl border bg-[#161922]/20 backdrop-blur-md transition-all hover:border-white/10 flex flex-col justify-between group" 
                style={{ borderColor: "rgba(255, 255, 255, 0.06)" }}
              >
                <div className="flex items-center justify-between mb-3">
                  <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">{s.label}</span>
                  <div 
                    className="w-8 h-8 rounded-lg flex items-center justify-center transition-transform group-hover:scale-105"
                    style={{ backgroundColor: `${s.color}15`, color: s.color }}
                  >
                    {s.icon}
                  </div>
                </div>
                <div>
                  <span className="text-2xl font-bold tracking-tight block mb-1">{s.value}</span>
                  <span className={`text-[10px] font-semibold flex items-center gap-1 ${s.isPositive ? "text-emerald-400" : "text-rose-400"}`}>
                    {s.isPositive ? <TrendingUp size={11} /> : <TrendingDown size={11} />}
                    {s.change}
                  </span>
                </div>
              </div>
            ))}
          </div>

          {/* Charts / Main Content Row */}
          <div className="relative z-10 grid lg:grid-cols-3 gap-6 mb-8 items-stretch">
            
            {/* Recruitment Funnel Card */}
            <div className="lg:col-span-2 p-6 rounded-2xl border bg-[#161922]/20 backdrop-blur-md flex flex-col justify-between" style={{ borderColor: "rgba(255, 255, 255, 0.06)" }}>
              <div>
                <h3 className="text-sm font-semibold tracking-wide mb-1">Case Resolution Funnel</h3>
                <p className="text-[10px] text-gray-500 mb-6">Case progression statistics across stages.</p>
                
                {/* Funnel representation */}
                <div className="flex flex-col gap-4">
                  {funnelData.map((item, idx) => (
                    <div key={idx} className="flex items-center gap-4">
                      <div className="w-32 text-xs font-semibold text-gray-400 truncate">{item.stage}</div>
                      <div className="flex-1 bg-white/[0.02] border border-white/[0.04] h-7 rounded-lg overflow-hidden relative">
                        <div 
                          className="h-full bg-gradient-to-r transition-all duration-500 rounded-lg flex items-center justify-end px-3 font-semibold text-[10px]"
                          style={{ 
                            width: item.width,
                            backgroundImage: `linear-gradient(90deg, ${item.color}50 0%, ${item.color}bb 100%)` 
                          }}
                        >
                          {item.count} cases
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
              
              <div className="border-t border-white/[0.04] pt-4 mt-6 flex justify-between items-center text-[10px] text-gray-500">
                <span>Case Resolution Efficiency: **{hireRate}%** resolution rate</span>
                <span className="text-[#06b6d4] font-semibold hover:underline cursor-pointer" onClick={() => onViewChange("Insights")}>View detailed resolution insights</span>
              </div>
            </div>

            {/* Recent Activities Panel */}
            <div className="p-6 rounded-2xl border bg-[#161922]/20 backdrop-blur-md flex flex-col" style={{ borderColor: "rgba(255, 255, 255, 0.06)" }}>
              <h3 className="text-sm font-semibold tracking-wide mb-1">Recent Activity</h3>
              <p className="text-[10px] text-gray-500 mb-5">Live case tracking logs.</p>
              
              <div className="flex-1 space-y-4">
                {displayActivities.map((a, idx) => {
                  let tagColor = "rgba(255, 255, 255, 0.04)";
                  let textColor = "#fff";
                  if (a.type === "apply") { tagColor = "rgba(139, 92, 246, 0.1)"; textColor = "#c084fc"; }
                  else if (a.type === "advance") { tagColor = "rgba(6, 182, 212, 0.1)"; textColor = "#2dd4bf"; }
                  else if (a.type === "offer") { tagColor = "rgba(16, 185, 129, 0.1)"; textColor = "#34d399"; }
                  else if (a.type === "reject") { tagColor = "rgba(239, 68, 68, 0.1)"; textColor = "#f87171"; }

                  return (
                    <div key={idx} className="flex gap-3 text-xs items-start leading-snug">
                      <div 
                        className="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-1"
                        style={{ backgroundColor: textColor }}
                      />
                      <div className="flex-1">
                        <span className="font-semibold text-white">{a.name}</span>{" "}
                        <span className="text-gray-500">{a.action}</span>{" "}
                        <span className="font-medium" style={{ color: textColor }}>{a.role}</span>
                        <span className="block text-[9px] text-gray-600 mt-0.5">{a.time}</span>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>

          {/* Active Jobs Grid */}
          <div className="relative z-10 p-6 rounded-2xl border bg-[#161922]/20 backdrop-blur-md" style={{ borderColor: "rgba(255, 255, 255, 0.06)" }}>
            <div className="flex items-center justify-between mb-6">
              <div>
                <h3 className="text-sm font-semibold tracking-wide">Active Issue Categories</h3>
                <p className="text-[10px] text-gray-500 mt-0.5">Tracking case velocity and metrics per issue type.</p>
              </div>
              <button 
                onClick={() => onViewChange("Jobs")}
                className="text-xs text-[#06b6d4] hover:text-[#0891b2] hover:underline font-semibold border-0 bg-transparent cursor-pointer"
              >
                Manage Categories
              </button>
            </div>

            <div className="grid md:grid-cols-2 gap-4">
              {activeJobs.map((job, idx) => {
                let statusStyle = "border-white/10 bg-white/5 text-gray-400";
                if (job.status === "Urgent") statusStyle = "border-rose-500/20 bg-rose-500/10 text-rose-400";
                else if (job.status === "Active") statusStyle = "border-cyan-500/20 bg-cyan-500/10 text-cyan-400";

                return (
                  <div 
                    key={idx}
                    className="p-4 rounded-xl border bg-[#11131a]/40 flex flex-col justify-between"
                    style={{ borderColor: "rgba(255, 255, 255, 0.04)" }}
                  >
                    <div className="flex items-start justify-between mb-3">
                      <div>
                        <h4 className="text-xs font-bold text-white leading-tight">{job.title}</h4>
                        <span className="text-[9px] font-bold tracking-wider text-gray-500 uppercase mt-0.5 block">{job.dept}</span>
                      </div>
                      <span className={`text-[8px] font-black uppercase px-2 py-0.5 rounded border ${statusStyle}`}>
                        {job.status}
                      </span>
                    </div>
                    
                    <div>
                      <div className="flex justify-between items-center text-[10px] text-gray-400 mb-1.5">
                        <span>{job.candidates} cases active</span>
                        <span className="font-bold text-white">{job.progress}% capacity</span>
                      </div>
                      <div className="w-full h-1.5 bg-white/[0.03] border border-white/[0.04] rounded-full overflow-hidden">
                        <div 
                          className="h-full bg-gradient-to-r from-[#8b5cf6] to-[#ec4899] rounded-full"
                          style={{ width: `${job.progress}%` }}
                        />
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </>
      )}
    </div>
  );
}
