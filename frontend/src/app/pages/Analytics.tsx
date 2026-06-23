import { useState, useEffect } from "react";
import { 
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip as RechartsTooltip, ResponsiveContainer,
  LineChart, Line
} from "recharts";
import { Target, Users, TrendingUp, AlertTriangle } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=analytics`;

type ChartData = { labels: string[], data: number[] };

export function Analytics() {
  const [headcount, setHeadcount] = useState<ChartData | null>(null);
  const [talent, setTalent] = useState<ChartData | null>(null);
  const [payroll, setPayroll] = useState<ChartData | null>(null);
  
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [accessDenied, setAccessDenied] = useState(false);

  useEffect(() => {
    const fetchAll = async () => {
      try {
        const fetchEndpoint = async (action: string) => {
          const res = await fetch(`${API}&action=${action}`, { credentials: "include" });
          if (res.status === 403) {
            setAccessDenied(true);
            return null;
          }
          const json = await res.json();
          if (json.success) return json;
          if (json.error && json.error.includes("Access Denied")) {
             setAccessDenied(true);
          }
          return null;
        };

        const [hc, td, pt] = await Promise.all([
          fetchEndpoint('headcount_by_dept'),
          fetchEndpoint('talent_density'),
          fetchEndpoint('payroll_trend')
        ]);

        if (hc && !accessDenied) setHeadcount(hc);
        if (td && !accessDenied) setTalent(td);
        if (pt && !accessDenied) setPayroll(pt);

      } catch (err) {
        console.error("Failed to fetch analytics", err);
        setError("Unable to connect to API");
      } finally {
        setLoading(false);
      }
    };
    
    fetchAll();
  }, [accessDenied]);

  if (loading) {
    return (
      <div className="flex-1 flex items-center justify-center">
        <div className="animate-spin w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full"></div>
      </div>
    );
  }

  if (accessDenied) {
    return (
      <div className="flex-1 flex items-center justify-center p-8 text-center" style={{ backgroundColor: '#0b0f19', color: '#8899b4' }}>
        <div className="max-w-md space-y-4">
          <div className="w-16 h-16 bg-red-500/10 rounded-2xl border border-red-500/20 flex items-center justify-center mx-auto mb-6 shadow-[0_0_20px_rgba(239,68,68,0.15)]">
            <AlertTriangle className="w-8 h-8 text-red-400" />
          </div>
          <h2 className="text-2xl font-bold text-white tracking-tight">Access Denied</h2>
          <p className="text-sm">You don't have access to Analytics. Executive clearance is required to view this module.</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex-1 flex flex-col items-center justify-center py-24 text-center">
        <Target size={40} className="text-[#f5a623] mb-4" />
        <p className="text-gray-900 dark:text-foreground font-medium mb-1 font-sans">
          Unable to load analytics
        </p>
        <p className="text-sm text-muted-foreground dark:text-muted-foreground font-mono">{error}</p>
      </div>
    );
  }

  // Format data for Recharts
  const headcountData = headcount?.labels.map((label, i) => ({ name: label, count: headcount.data[i] })) || [];
  const talentData = talent?.labels.map((label, i) => ({ name: label, count: talent.data[i] })) || [];
  const payrollData = payroll?.labels.map((label, i) => ({ name: label, total: payroll.data[i] })) || [];

  return (
    <div className="flex-1 overflow-y-auto p-8 font-sans text-foreground bg-background">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-foreground">Workforce Analytics</h1>
        <p className="text-sm text-muted-foreground mt-1">Executive Dashboard: Real-time insights into your human capital.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {/* Headcount by Department */}
        <div className="bg-background/60 border border-border rounded-xl p-6 flex flex-col shadow-sm">
          <div className="mb-6 flex items-start gap-3">
            <div className="p-2 bg-blue-500/10 rounded-lg border border-blue-500/20 text-blue-500">
              <Users size={18} />
            </div>
            <div>
              <h3 className="text-base font-bold text-foreground">Headcount by Department</h3>
              <p className="text-xs text-muted-foreground">Distribution of active employees.</p>
            </div>
          </div>
          <div className="flex-1 h-64 relative">
            {headcountData.length === 0 ? (
              <div className="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground italic">
                No active employees found.
              </div>
            ) : (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={headcountData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" vertical={false} />
                  <XAxis dataKey="name" stroke="#888888" fontSize={12} tickLine={false} axisLine={false} />
                  <YAxis stroke="#888888" fontSize={12} tickLine={false} axisLine={false} />
                  <RechartsTooltip 
                    cursor={{ fill: 'rgba(255,255,255,0.05)' }}
                    contentStyle={{ backgroundColor: 'rgba(15, 23, 42, 0.9)', borderColor: 'rgba(255,255,255,0.1)', borderRadius: '8px', color: '#fff' }}
                    itemStyle={{ color: '#fff' }}
                  />
                  <Bar dataKey="count" fill="#3b82f6" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            )}
          </div>
        </div>

        {/* Talent Density */}
        <div className="bg-background/60 border border-border rounded-xl p-6 flex flex-col shadow-sm">
          <div className="mb-6 flex items-start gap-3">
            <div className="p-2 bg-[#00e07a]/10 rounded-lg border border-[#00e07a]/20 text-[#00e07a]">
              <Target size={18} />
            </div>
            <div>
              <h3 className="text-base font-bold text-foreground">Talent Density</h3>
              <p className="text-xs text-muted-foreground">Company-wide 9-Box calibration distribution.</p>
            </div>
          </div>
          <div className="flex-1 h-64 relative">
            {talentData.length === 0 || talentData.every(d => d.count === 0) ? (
              <div className="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground italic">
                No finalized performance reviews yet.
              </div>
            ) : (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={talentData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" vertical={false} />
                  <XAxis dataKey="name" stroke="#888888" fontSize={10} tickLine={false} axisLine={false} angle={-45} textAnchor="end" height={60} />
                  <YAxis stroke="#888888" fontSize={12} tickLine={false} axisLine={false} />
                  <RechartsTooltip 
                    cursor={{ fill: 'rgba(255,255,255,0.05)' }}
                    contentStyle={{ backgroundColor: 'rgba(15, 23, 42, 0.9)', borderColor: 'rgba(255,255,255,0.1)', borderRadius: '8px', color: '#fff' }}
                    itemStyle={{ color: '#fff' }}
                  />
                  <Bar dataKey="count" fill="#00e07a" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            )}
          </div>
        </div>
      </div>

      {/* Payroll Expense Trend */}
      <div className="bg-background/60 border border-border rounded-xl p-6 flex flex-col shadow-sm">
        <div className="mb-6 flex items-start gap-3">
          <div className="p-2 bg-purple-500/10 rounded-lg border border-purple-500/20 text-purple-500">
            <TrendingUp size={18} />
          </div>
          <div>
            <h3 className="text-base font-bold text-foreground">Payroll Expense Trend</h3>
            <p className="text-xs text-muted-foreground">Total Gross Payroll per pay cycle (Processed & Locked runs).</p>
          </div>
        </div>
        <div className="flex-1 h-72 relative">
          {payrollData.length === 0 ? (
            <div className="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground italic">
              No processed payroll runs yet.
            </div>
          ) : (
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={payrollData} margin={{ top: 10, right: 30, left: 20, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" vertical={false} />
                <XAxis dataKey="name" stroke="#888888" fontSize={12} tickLine={false} axisLine={false} />
                <YAxis 
                  stroke="#888888" 
                  fontSize={12} 
                  tickLine={false} 
                  axisLine={false} 
                  tickFormatter={(value) => `$${value.toLocaleString()}`}
                />
                <RechartsTooltip 
                  contentStyle={{ backgroundColor: 'rgba(15, 23, 42, 0.9)', borderColor: 'rgba(255,255,255,0.1)', borderRadius: '8px', color: '#fff' }}
                  itemStyle={{ color: '#fff' }}
                  formatter={(value: any) => [`$${Number(value).toLocaleString()}`, 'Gross Payroll']}
                />
                <Line type="monotone" dataKey="total" stroke="#9b6dff" strokeWidth={3} dot={{ r: 4, fill: "#9b6dff", strokeWidth: 0 }} activeDot={{ r: 6 }} />
              </LineChart>
            </ResponsiveContainer>
          )}
        </div>
      </div>
    </div>
  );
}
