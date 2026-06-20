import { useState, useEffect } from "react";
import { 
  TrendingUp, 
  Users, 
  Briefcase,
  Layers, 
  Target,
  Clock,
  Compass
} from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL;
const API = `${API_BASE}/api/index.php?route=candidates`;

type InsightsPageProps = {
  onViewChange: (view: any) => void;
};

export function InsightsPage({ onViewChange }: InsightsPageProps) {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchAnalytics = async () => {
      try {
        const res = await fetch(`${API}&action=analytics`);
        const json = await res.json();
        if (json.success) {
          setData(json);
        } else {
          setError(json.error || "Failed to load analytics");
        }
      } catch (err) {
        console.error("Failed to fetch analytics", err);
        setError("Unable to connect to API");
      } finally {
        setLoading(false);
      }
    };
    fetchAnalytics();
  }, []);

  if (loading) {
    return (
      <div className="flex-1 flex items-center justify-center">
        <div className="animate-spin w-8 h-8 border-2 border-[#00e07a] border-t-transparent rounded-full"></div>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="flex-1 flex flex-col items-center justify-center py-24 text-center">
        <Target size={40} className="text-[#f5a623] mb-4" />
        <p className="text-gray-900 dark:text-foreground font-medium mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
          Unable to load analytics
        </p>
        <p className="text-sm text-muted-foreground dark:text-muted-foreground font-mono">{error || "No data available"}</p>
        <button
          onClick={() => window.location.reload()}
          className="mt-4 px-4 py-2 rounded-lg text-xs font-mono font-semibold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20 hover:bg-purple-500/20 cursor-pointer transition-all"
        >
          [ RETRY SESSION ]
        </button>
      </div>
    );
  }

  // Calculate dynamic source channels
  const totalApps = data.source_performance?.reduce((acc: number, curr: any) => acc + curr.total, 0) || 1;
  const colors = ["#3b82f6", "#8b5cf6", "#00e07a", "#f472b6", "#fbbf24"];
  const channels = data.source_performance?.slice(0, 4).map((c: any, i: number) => ({
    source: c.source || "Direct / Other",
    applications: c.total,
    percentage: Math.round((c.total / totalApps) * 100),
    color: colors[i % colors.length]
  })) || [];

  // Parse application volume for chart
  let volume = data.application_volume || [];
  if (volume.length === 0) {
    const today = new Date();
    for (let i = 5; i >= 0; i--) {
      const d = new Date(today.getFullYear(), today.getMonth() - i, 1);
      volume.push({
        cnt: 0,
        label: d.toLocaleString('default', { month: 'short' })
      });
    }
  }
  
  // Calculate SVG points based on dynamic data
  let graphPoints = "50,120 150,90 250,140 350,60 450,80 550,30"; 
  let pathD = "M 50,160 L 50,120 L 150,90 L 250,140 L 350,60 L 450,80 L 550,30 L 550,160 Z"; 
  let peakMonthLabel = "N/A";
  let peakApps = 0;
  let chartDots: any[] = [];

  if (volume.length > 0) {
    const maxVal = Math.max(...volume.map((v: any) => v.cnt), 1);
    const startX = 50;
    const endX = 550;
    const stepX = volume.length > 1 ? (endX - startX) / (volume.length - 1) : 0;
    const minHeight = 140;
    const maxHeight = 30;
    
    const pointsArr = volume.map((v: any, i: number) => {
      const x = startX + (i * stepX);
      const ratio = v.cnt / maxVal;
      const y = minHeight - (ratio * (minHeight - maxHeight));
      
      if (v.cnt > peakApps) {
        peakApps = v.cnt;
        peakMonthLabel = v.label;
      }
      
      chartDots.push({ cx: x, cy: y, label: v.label });
      return `${x},${y}`;
    });
    
    graphPoints = pointsArr.join(" ");
    pathD = `M ${startX},160 L ` + pointsArr.map((p: string) => `L ${p.replace(',', ',')}`).join(" ") + ` L ${endX},160 Z`;
  }

  // Stage calibration data
  const funnel = data.funnel || {};
  const stageCalibration = [
    { stage: "Applied", label: "APPLIED", count: funnel["Applied"] || 0, color: "#3b82f6" },
    { stage: "Review", label: "REVIEW", count: funnel["Review"] || 0, color: "#9b6dff" },
    { stage: "Phone Screen", label: "PHONE SCREEN", count: funnel["Phone Screen"] || 0, color: "#2dd4bf" },
    { stage: "Interview", label: "INTERVIEW", count: funnel["Interview"] || 0, color: "#fbbf24" },
    { stage: "Offer", label: "OFFER", count: funnel["Offer"] || 0, color: "#f472b6" },
    { stage: "Hired", label: "HIRED", count: funnel["Hired"] || 0, color: "#00e07a" },
  ];
  const maxStageCount = Math.max(...stageCalibration.map(s => s.count), 1);

  // Department distribution
  const deptVelocity = data.executive?.department_velocity || [];
  const maxDeptApps = Math.max(...deptVelocity.map((d: any) => (d.total || 0)), 1);

  return (
    <div className="flex-1 flex flex-col overflow-y-auto px-8 py-6 text-foreground font-sans relative scrollbar-thin" >
      <style>{`
        .blink {
          animation: blink-anim 1.1s step-start infinite;
        }
        @keyframes blink-anim {
          0%, 100% { opacity: 1; }
          50%       { opacity: 0; }
        }
      `}</style>
      {/* Background glows */}
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-primary blur-[120px] opacity-[0.06] pointer-events-none z-0" />
      <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#9b6dff] blur-[140px] opacity-[0.05] pointer-events-none z-0" />

      {/* Header */}
      <div className="relative z-10 flex items-center justify-between mb-8 border-b border-border pb-6">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground font-['Space_Grotesk'] flex items-center gap-1.5">
            RECRUITMENT ANALYTICS // METRICS DASHBOARD
            <span className="inline-block w-2.5 h-5 bg-primary blink"></span>
          </h1>
          <p className="text-xs font-mono text-muted-foreground mt-1 uppercase tracking-wider">acquisition conversions, pipeline calibration, and department recruitment velocity.</p>
        </div>
      </div>

      {/* Top Headline stats */}
      <div className="relative z-10 grid grid-cols-1 md:grid-cols-3 gap-5 mb-8 font-mono">
        <div className="p-5 rounded-xl border border-border bg-background flex gap-4 items-start">
          <div className="p-2.5 rounded bg-primary text-primary border border-[#00e07a]/20"><Clock size={16} /></div>
          <div>
            <h4 className="text-[10px] text-muted-foreground uppercase">AVG TIME TO HIRE</h4>
            <p className="text-xl font-bold text-foreground mt-1">{data.headline?.avg_time_to_hire} DAYS</p>
            <p className="text-[9px] text-muted-foreground mt-1 uppercase">Applied to hire cycle</p>
          </div>
        </div>

        <div className="p-5 rounded-xl border border-border bg-background flex gap-4 items-start">
          <div className="p-2.5 rounded bg-primary text-primary border border-[#00e07a]/20"><Target size={16} /></div>
          <div>
            <h4 className="text-[10px] text-muted-foreground uppercase">OFFER ACCEPTANCE</h4>
            <p className="text-xl font-bold text-foreground mt-1">{data.headline?.offer_acceptance_rate}%</p>
            <p className="text-[9px] text-muted-foreground mt-1 uppercase">Ratio of offers accepted</p>
          </div>
        </div>

        <div className="p-5 rounded-xl border border-border bg-background flex gap-4 items-start">
          <div className="p-2.5 rounded bg-primary text-primary border border-[#00e07a]/20"><Users size={16} /></div>
          <div>
            <h4 className="text-[10px] text-muted-foreground uppercase">TOTAL CANDIDATES</h4>
            <p className="text-xl font-bold text-foreground mt-1">{data.headline?.total_candidates}</p>
            <p className="text-[9px] text-muted-foreground mt-1 uppercase">Central talent database</p>
          </div>
        </div>
      </div>

      {/* Charts Grid */}
      <div className="relative z-10 grid lg:grid-cols-2 gap-6 mb-8 font-mono">
        
        {/* Application Volume Trend Chart */}
        <div className="p-6 rounded-xl border bg-background/60 border-border flex flex-col justify-between">
          <div>
            <div className="flex justify-between items-start mb-6">
              <div>
                <h3 className="text-xs font-bold text-foreground uppercase tracking-wider">// APPLICATION VOLUME TREND</h3>
                <p className="text-[9px] text-muted-foreground mt-0.5">Monthly breakdown of received resumes (Last 6 Months).</p>
              </div>
              <span className="text-[9px] font-bold text-primary flex items-center gap-1 bg-primary px-2 py-0.5 rounded border border-[#00e07a]/20">
                <TrendingUp size={10} /> LIVE
              </span>
            </div>

            {/* Custom SVG Line Chart */}
            <div className="w-full h-44 relative bg-black/10 border border-border rounded-xl p-4 overflow-hidden">
              <svg viewBox="0 0 600 160" className="w-full h-full">
                <line x1="30" y1="30" x2="570" y2="30" stroke="rgba(255,255,255,0.03)" strokeWidth="1" />
                <line x1="30" y1="80" x2="570" y2="80" stroke="rgba(255,255,255,0.03)" strokeWidth="1" />
                <line x1="30" y1="130" x2="570" y2="130" stroke="rgba(255,255,255,0.03)" strokeWidth="1" />
                
                <polyline
                  fill="none"
                  stroke="url(#chartGrad)"
                  strokeWidth="3"
                  points={graphPoints}
                />
                
                <path
                  fill="url(#chartAreaGrad)"
                  d={pathD}
                />

                {chartDots.map((pt, idx) => (
                  <g key={idx}>
                    <circle cx={pt.cx} cy={pt.cy} r="4" fill="#00e07a" stroke="#ffffff" strokeWidth="1.5" />
                    <text x={pt.cx} y="153" fill="#6b7280" fontSize="9" textAnchor="middle" fontWeight="bold" fontFamily="monospace">{pt.label}</text>
                  </g>
                ))}

                <defs>
                  <linearGradient id="chartGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#00e07a" />
                    <stop offset="100%" stopColor="#9b6dff" />
                  </linearGradient>
                  <linearGradient id="chartAreaGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stopColor="#00e07a" stopOpacity="0.2" />
                    <stop offset="100%" stopColor="#00e07a" stopOpacity="0.0" />
                  </linearGradient>
                </defs>
              </svg>
            </div>
          </div>
          
          <div className="border-t border-border pt-4 mt-6 flex justify-between items-center text-[9px] text-muted-foreground">
            <span>Peak Month: **{peakMonthLabel}** with {peakApps} new applications</span>
            <span>Report updated live</span>
          </div>
        </div>

        {/* Pipeline Stage Density Bar Chart */}
        <div className="p-6 rounded-xl border bg-background/60 border-border flex flex-col justify-between">
          <div>
            <h3 className="text-xs font-bold text-foreground uppercase tracking-wider mb-1">// PIPELINE STAGE CALIBRATION</h3>
            <p className="text-[9px] text-muted-foreground mb-6">Distribution of active candidates across recruiting milestones.</p>
            
            <div className="space-y-3">
              {stageCalibration.map((s) => {
                const pct = Math.max(Math.round((s.count / maxStageCount) * 100), 2);
                return (
                  <div key={s.stage} className="flex items-center gap-4">
                    <span className="text-[9px] font-bold text-muted-foreground w-24 shrink-0 truncate">{`[ ${s.label} ]`}</span>
                    <div className="flex-1 h-3 bg-muted border border-border rounded overflow-hidden relative">
                      <div 
                        className="h-full transition-all duration-500"
                        style={{ 
                          width: `${pct}%`, 
                          backgroundColor: s.color,
                          boxShadow: `0 0 6px ${s.color}80` 
                        }}
                      />
                    </div>
                    <span className="text-[10px] font-bold text-foreground w-12 text-right">{s.count} Apps</span>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="border-t border-border pt-4 mt-5 text-[9px] text-muted-foreground leading-relaxed">
            Active pipeline density tracks total unconverted applications.
          </div>
        </div>
      </div>

      {/* Grid Row 2 */}
      <div className="relative z-10 grid lg:grid-cols-2 gap-6 font-mono">
        {/* Applications by Department (adapted from headcount by dept) */}
        <div className="p-6 rounded-xl border bg-background/60 border-border">
          <h3 className="text-xs font-bold text-foreground uppercase tracking-wider mb-1">// APPLICATIONS BY DEPARTMENT</h3>
          <p className="text-[9px] text-muted-foreground mb-6">Volume distribution across organizational department sectors.</p>
          
          {deptVelocity.length === 0 ? (
            <div className="text-xs text-muted-foreground italic py-10 text-center">No department data available.</div>
          ) : (
            <div className="space-y-4">
              {deptVelocity.slice(0, 5).map((d: any, idx: number) => {
                const total = d.total || 0;
                const pct = Math.max(Math.round((total / maxDeptApps) * 100), 2);
                const color = colors[idx % colors.length];
                return (
                  <div key={d.department}>
                    <div className="flex justify-between items-center text-[10px] mb-1 font-semibold">
                      <span className="text-foreground uppercase">{d.department}</span>
                      <span className="text-muted-foreground">{total} Applicants</span>
                    </div>
                    <div className="flex items-center gap-3">
                      <div className="flex-1 h-2 bg-muted border border-border rounded overflow-hidden">
                        <div 
                          className="h-full transition-all duration-500"
                          style={{ width: `${pct}%`, backgroundColor: color }}
                        />
                      </div>
                      <span className="text-[9px] font-bold text-muted-foreground w-16 text-right">
                        {d.avg_time_to_hire ? `[ ${Math.round(d.avg_time_to_hire)}d cycle ]` : "[ N/A ]"}
                      </span>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Source Channels performance breakdown */}
        <div className="p-6 rounded-xl border bg-background/60 border-border flex flex-col justify-between">
          <div>
            <h3 className="text-xs font-bold text-foreground uppercase tracking-wider mb-1">// CANDIDATE ACQUISITION SOURCES</h3>
            <p className="text-[9px] text-muted-foreground mb-5">Primary channels generating applications and hired conversions.</p>
            
            {channels.length === 0 ? (
              <div className="text-xs text-muted-foreground italic mt-8 text-center">No source records logged.</div>
            ) : (
              <div className="space-y-4">
                {channels.map((c: any, idx: number) => (
                  <div key={idx}>
                    <div className="flex justify-between items-center text-[10px] mb-1.5 font-semibold">
                      <span className="text-foreground uppercase">{c.source}</span>
                      <span className="text-muted-foreground">{c.percentage}%</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <div className="flex-1 h-2 bg-muted border border-border rounded-full overflow-hidden">
                        <div 
                          className="h-full rounded-full transition-all duration-500"
                          style={{ width: `${c.percentage}%`, backgroundColor: c.color }}
                        />
                      </div>
                      <span className="text-[9px] font-bold text-muted-foreground uppercase leading-none min-w-20 text-right">
                        {c.applications} Apps
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="border-t border-border pt-4 mt-5 text-[9px] text-muted-foreground leading-relaxed">
            ?? {data.headline?.top_source ? `${data.headline.top_source.toUpperCase()} is currently your top performing channel.` : 'Tracking live source registry.'}
          </div>
        </div>
      </div>
    </div>
  );
}