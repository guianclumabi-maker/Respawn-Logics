import { useState, useEffect } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  AreaChart, 
  Area, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  ResponsiveContainer,
  BarChart,
  Bar,
  Cell
} from "recharts";
import { 
  Loader2, 
  AlertCircle, 
  BarChart3, 
  TrendingUp, 
  PieChart 
} from "lucide-react";

export function ELRAnalytics() {
  const [data, setData] = useState<any>({ trend: [], channels: [] });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchAnalytics = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch("/api/index.php?route=elr&action=analytics");
      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const resData = await res.json();
      if (resData.success) {
        setData(resData);
      } else {
        setError(resData.error || "Failed to load analytics.");
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || "An unexpected error occurred while loading analytics.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAnalytics();
  }, []);

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-background text-foreground">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-border bg-card text-card-foreground/50 backdrop-blur-md">
        <h1 className="text-2xl font-bold text-foreground mb-1 font-['Space_Grotesk']">
          Relations Analytics
        </h1>
        <p className="text-sm text-muted-foreground">Statistical distribution of investigations and case volume trend metrics</p>
      </div>

      {/* Main Container */}
      <div className="flex-1 overflow-auto p-8 space-y-6">
        
        {loading ? (
          <div className="flex flex-col items-center justify-center py-20 gap-3 text-muted-foreground">
            <Loader2 className="w-8 h-8 animate-spin text-[#00e07a]" />
            <p className="text-sm font-medium">Computing historical aggregates...</p>
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center py-16 px-6 bg-red-500/10 border border-red-500/20 rounded-xl text-center space-y-3">
            <AlertCircle className="w-10 h-10 text-red-500" />
            <h3 className="text-lg font-bold text-foreground">Load Error</h3>
            <p className="text-sm text-muted-foreground">{error}</p>
            <button 
              onClick={fetchAnalytics}
              className="mt-2 px-4 py-2 bg-card/50 hover:bg-accent text-foreground rounded-lg text-xs transition-colors border border-border"
            >
              Retry
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
            
            {/* Chart 1: Case Volume Trend (Full Width / Col Span 2) */}
            <div className="lg:col-span-2 bg-card text-card-foreground/70 border border-border p-6 rounded-2xl shadow-xl space-y-4">
              <h3 className="text-sm font-bold text-foreground uppercase tracking-wider flex items-center gap-2">
                <TrendingUp size={16} className="text-[#00e07a]" /> Case Volume Trend (Last 6 Months)
              </h3>
              
              <div className="h-72 w-full font-mono text-xs">
                {data.trend && data.trend.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={data.trend}>
                      <defs>
                        <linearGradient id="colorTrend" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="5%" stopColor="#00e07a" stopOpacity={0.2}/>
                          <stop offset="95%" stopColor="#00e07a" stopOpacity={0}/>
                        </linearGradient>
                      </defs>
                      <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" vertical={false} />
                      <XAxis dataKey="month" stroke="#4b5563" />
                      <YAxis stroke="#4b5563" allowDecimals={false} />
                      <Tooltip 
                        contentStyle={{ backgroundColor: 'var(--card)', borderColor: 'var(--border)', color: 'var(--foreground)', borderRadius: '8px' }}
                      />
                      <Area type="monotone" dataKey="count" stroke="#00e07a" strokeWidth={2.5} fillOpacity={1} fill="url(#colorTrend)" />
                    </AreaChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="h-full flex items-center justify-center text-muted-foreground">No volume data to chart.</div>
                )}
              </div>
            </div>

            {/* Chart 2: Case Distribution Bar Chart (Right side) */}
            <div className="bg-card text-card-foreground/70 border border-border p-6 rounded-2xl shadow-xl space-y-4">
              <h3 className="text-sm font-bold text-foreground uppercase tracking-wider flex items-center gap-2">
                <PieChart size={16} className="text-[#00b8ff]" /> Category Density
              </h3>
              
              <div className="h-72 w-full font-mono text-xs">
                {data.channels && data.channels.length > 0 ? (
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data.channels} layout="vertical">
                      <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" horizontal={false} />
                      <XAxis type="number" stroke="#4b5563" allowDecimals={false} />
                      <YAxis dataKey="source" type="category" stroke="#4b5563" width={100} tickLine={false} />
                      <Tooltip
                        contentStyle={{ backgroundColor: 'var(--card)', borderColor: 'var(--border)', color: 'var(--foreground)', borderRadius: '8px' }}
                      />
                      <Bar dataKey="applications" barSize={12} radius={[0, 4, 4, 0]}>
                        {data.channels.map((entry: any, index: number) => (
                          <Cell key={`cell-${index}`} fill={entry.color || "#00e07a"} />
                        ))}
                      </Bar>
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="h-full flex items-center justify-center text-muted-foreground">No density data to chart.</div>
                )}
              </div>
            </div>

          </div>
        )}

      </div>
    </div>
  );
}
