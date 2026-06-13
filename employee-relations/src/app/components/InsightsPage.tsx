import { useState, useEffect } from "react";
import { 
  TrendingUp, 
  Users, 
  Search, 
  MapPin, 
  ArrowUpRight, 
  Layers, 
  Globe, 
  Target,
  Filter
} from "lucide-react";

type InsightsPageProps = {
  onViewChange: (view: any) => void;
};

export function InsightsPage({ onViewChange }: InsightsPageProps) {
  const [channels, setChannels] = useState<any[]>([]);
  const [trendData, setTrendData] = useState<any[]>([]);
  const [filterPeriod, setFilterPeriod] = useState("Last 6 Months");

  useEffect(() => {
    fetch("/respawn-logics/elr_api.php?action=analytics")
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setChannels(data.channels);
          setTrendData(data.trend);
        }
      });
  }, []);

  // Helper to dynamically draw graph points based on returned trend Data
  const generateGraph = () => {
    if (!trendData || trendData.length === 0) {
      return { path: "50,150 550,150", points: [] };
    }
    
    // x spacing
    const width = 500;
    const spacing = width / Math.max(1, (trendData.length - 1));
    
    // y scaling
    const maxVal = Math.max(...trendData.map(d => parseInt(d.count)), 10); // min max of 10
    const minVal = 0;
    const height = 120; // 30 to 150
    
    let pathStr = "";
    const points = [];
    
    trendData.forEach((d, i) => {
      const cx = 50 + (i * spacing);
      // Invert Y axis for SVG (0 is top)
      const cy = 150 - ((parseInt(d.count) / maxVal) * height);
      pathStr += `${cx},${cy} `;
      points.push({ cx, cy, label: d.month, count: d.count });
    });
    
    return { path: pathStr.trim(), points };
  };

  const graph = generateGraph();

  return (
    <div className="flex-1 flex flex-col overflow-y-auto px-8 py-6 text-white font-sans relative scrollbar-thin" style={{ backgroundColor: "#0d0f19" }}>
      {/* Background glows */}
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-[#8b5cf6] blur-[120px] opacity-10 pointer-events-none z-0" />
      <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#ec4899] blur-[140px] opacity-8 pointer-events-none z-0" />

      {/* Header */}
      <div className="relative z-10 flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-white to-gray-400 bg-clip-text text-transparent" style={{ fontFamily: "'Outfit', 'Inter', sans-serif" }}>
            Relations Insights
          </h1>
          <p className="text-xs text-[#9ca3af] mt-1">Case trends, issue categories performance, and resolution analytics.</p>
        </div>
      </div>

      {/* Core Insights grid */}
      <div className="relative z-10 grid lg:grid-cols-3 gap-6 items-stretch mb-8">
        
        {/* Applications Trend SVG Chart */}
        <div className="lg:col-span-2 p-6 rounded-2xl border bg-[#161922]/20 backdrop-blur-md flex flex-col justify-between" style={{ borderColor: "rgba(255, 255, 255, 0.06)" }}>
          <div>
            <div className="flex justify-between items-start mb-6">
              <div>
                <h3 className="text-sm font-semibold tracking-wide">Case Volume Trend</h3>
                <p className="text-[10px] text-gray-500 mt-0.5">Monthly breakdown of filed grievances and cases.</p>
              </div>
              <div className="flex items-center gap-3">
                <button className="flex items-center gap-2 px-3 py-1.5 bg-white/[0.03] hover:bg-white/[0.08] border border-white/[0.06] rounded-md text-xs transition-colors">
                  <Filter size={12} />
                  {filterPeriod}
                </button>
                <span className="text-[10px] font-bold text-emerald-400 flex items-center gap-1 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                  <TrendingUp size={10} /> +5% Year-over-Year
                </span>
              </div>
            </div>

            {/* Custom SVG Line Chart */}
            <div className="w-full h-44 relative bg-black/10 border border-white/[0.03] rounded-xl p-4 overflow-hidden">
              <svg viewBox="0 0 600 160" className="w-full h-full">
                {/* Horizontal Guide Lines */}
                <line x1="30" y1="30" x2="570" y2="30" stroke="rgba(255,255,255,0.03)" strokeWidth="1" />
                <line x1="30" y1="80" x2="570" y2="80" stroke="rgba(255,255,255,0.03)" strokeWidth="1" />
                <line x1="30" y1="130" x2="570" y2="130" stroke="rgba(255,255,255,0.03)" strokeWidth="1" />
                
                {/* Graph line path */}
                <polyline
                  fill="none"
                  stroke="url(#chartGrad)"
                  strokeWidth="3.5"
                  points={graph.path}
                />
                
                {/* Shading under path */}
                {graph.points.length > 0 && (
                  <path
                    fill="url(#chartAreaGrad)"
                    d={`M 50,160 L ${graph.points.map(p => `${p.cx},${p.cy}`).join(' L ')} L ${graph.points[graph.points.length-1].cx},160 Z`}
                  />
                )}

                {/* Graph Dots */}
                {graph.points.map((pt, idx) => (
                  <g key={idx} className="group">
                    <circle cx={pt.cx} cy={pt.cy} r="4.5" fill="#a855f7" stroke="#ffffff" strokeWidth="1.5" className="transition-all duration-300 group-hover:r-[6px]" />
                    <text x={pt.cx} y="155" fill="#4b5563" fontSize="10" textAnchor="middle" fontWeight="bold">{pt.label}</text>
                    <text x={pt.cx} y={pt.cy - 12} fill="#ffffff" fontSize="10" textAnchor="middle" fontWeight="bold" className="opacity-0 group-hover:opacity-100 transition-opacity">{pt.count}</text>
                  </g>
                ))}

                {/* Gradients */}
                <defs>
                  <linearGradient id="chartGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#8b5cf6" />
                    <stop offset="100%" stopColor="#ec4899" />
                  </linearGradient>
                  <linearGradient id="chartAreaGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stopColor="#8b5cf6" stopOpacity="0.25" />
                    <stop offset="100%" stopColor="#8b5cf6" stopOpacity="0.0" />
                  </linearGradient>
                </defs>
              </svg>
            </div>
          </div>
          
          <div className="border-t border-white/[0.04] pt-4 mt-6 flex justify-between items-center text-[10px] text-gray-500">
            <span>Peak Month: **June** with 15 new cases</span>
            <span>Report updated live</span>
          </div>
        </div>

        {/* Source Channels Breakdown */}
        <div className="p-6 rounded-2xl border bg-[#161922]/20 backdrop-blur-md flex flex-col justify-between" style={{ borderColor: "rgba(255, 255, 255, 0.06)" }}>
          <div>
            <h3 className="text-sm font-semibold tracking-wide mb-1">Report Channels</h3>
            <p className="text-[10px] text-gray-500 mb-5">Primary reporting distributions.</p>
            
            <div className="space-y-4.5">
              {channels.map((c, idx) => (
                <div key={idx}>
                  <div className="flex justify-between items-center text-xs mb-1.5 font-medium">
                    <span className="text-white">{c.source}</span>
                    <span className="text-gray-500">{c.percentage}%</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="flex-1 h-2 bg-white/[0.02] border border-white/[0.04] rounded-full overflow-hidden">
                      <div 
                        className="h-full rounded-full transition-all duration-500"
                        style={{ width: `${c.percentage}%`, backgroundColor: c.color }}
                      />
                    </div>
                    <span className="text-[9px] font-bold text-gray-500 uppercase leading-none min-w-24 text-right">
                      {c.applications} Cases
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="border-t border-white/[0.04] pt-4 mt-5 text-[10px] text-gray-500 leading-relaxed">
            💡 Direct HR Reports are resolved **20% faster** than external compliance escalations.
          </div>
        </div>
      </div>

      {/* Relations Efficiency Cards */}
      <div className="relative z-10 grid md:grid-cols-3 gap-5">
        <div className="p-5 rounded-2xl border bg-[#161922]/20 backdrop-blur-md flex gap-4 items-start" style={{ borderColor: "rgba(255, 255, 255, 0.06)" }}>
          <div className="p-3 rounded-xl bg-purple-500/10 text-purple-400 border border-purple-500/20"><Target size={16} /></div>
          <div>
            <h4 className="text-xs font-bold mb-1.5">Resolution Quality</h4>
            <p className="text-[11px] text-gray-500 leading-normal">
              85% of resolved cases result in mutual agreements and signed closing letters.
            </p>
          </div>
        </div>

        <div className="p-5 rounded-2xl border bg-[#161922]/20 backdrop-blur-md flex gap-4 items-start" style={{ borderColor: "rgba(255, 255, 255, 0.06)" }}>
          <div className="p-3 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20"><Globe size={16} /></div>
          <div>
            <h4 className="text-xs font-bold mb-1.5">Department Distribution</h4>
            <p className="text-[11px] text-gray-500 leading-normal">
              Operations and Finance represent 65% of all filed relations inquiries this quarter.
            </p>
          </div>
        </div>

        <div className="p-5 rounded-2xl border bg-[#161922]/20 backdrop-blur-md flex gap-4 items-start" style={{ borderColor: "rgba(255, 255, 255, 0.06)" }}>
          <div className="p-3 rounded-xl bg-pink-500/10 text-pink-400 border border-pink-500/20"><Layers size={16} /></div>
          <div>
            <h4 className="text-xs font-bold mb-1.5">Resolution Velocity</h4>
            <p className="text-[11px] text-gray-500 leading-normal">
              Simple policy inquiries are resolved fastest, averaging 3 days to complete resolution.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
