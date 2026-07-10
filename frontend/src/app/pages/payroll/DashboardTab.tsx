import React from 'react';
import { CalendarClock, TrendingUp, AlertCircle, CheckCircle2 } from 'lucide-react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { usePayroll } from './PayrollContext';

export function DashboardTab() {
  const {
    dashInfo,
    progress,
    processedEmployees,
    exceptions,
    chartData,
    setActiveTab,
    formatCurrency
  } = usePayroll();

  if (!dashInfo) return null;

  return (
    <div className="dashboard-content animate-slide-up">
      <div className="bg-card text-foreground border border-border rounded-xl p-5 mb-6">
        <div className="flex justify-between items-center mb-4">
          <div className="flex items-center gap-3">
            <div className="w-2 h-2 rounded-full bg-blue-500 animate-ping"></div>
            <h2 className="text-xl font-bold text-foreground">Active Payroll Generation</h2>
          </div>
          <span className="px-2 py-0.5 rounded text-xs font-mono font-bold bg-blue-500/10 text-blue-500">Processing: {dashInfo.activeRunName}</span>
        </div>
        <div className="mt-2">
          <div className="flex justify-between items-end mb-3">
            <div className="flex flex-col">
              <div className="flex items-baseline gap-3">
                <span className="text-4xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">
                  {progress}%
                </span>
                <span className="text-sm font-medium text-muted-foreground">
                  {processedEmployees.toLocaleString()} / {dashInfo.activeRunTotalEmployees.toLocaleString()} Employees
                </span>
              </div>
            </div>
            <span className="text-sm font-medium text-emerald-500 flex items-center gap-2">
              <div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
              Processing without errors
            </span>
          </div>
          <div className="w-full h-2 bg-muted rounded-full overflow-hidden">
            <div className="h-full bg-gradient-to-r from-blue-500 to-emerald-500 transition-all duration-300" style={{ width: `${progress}%` }}></div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div className="bg-card text-card-foreground border border-border rounded-xl p-5 flex flex-col gap-2">
          <div className="flex justify-between items-center">
            <div className="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center"><CalendarClock size={18} className="text-blue-400" /></div>
            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-500">On Track</span>
          </div>
          <div className="flex flex-col mt-1">
            <span className="text-xl font-bold font-mono text-foreground">{dashInfo.nextDate}</span>
            <span className="text-xs text-muted-foreground">Next Payroll Date</span>
          </div>
        </div>
        
        <div className="bg-card text-card-foreground border border-border rounded-xl p-5 flex flex-col gap-2">
          <div className="flex justify-between items-center">
            <div className="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center"><TrendingUp size={18} className="text-amber-400" /></div>
            <span className="text-[10px] font-bold text-amber-500">+{dashInfo.costIncrease}% from last</span>
          </div>
          <div className="flex flex-col mt-1">
            <span className="text-xl font-bold font-mono text-foreground">{formatCurrency(dashInfo.estimatedCost)}</span>
            <span className="text-xs text-muted-foreground">Estimated Payroll Cost</span>
          </div>
        </div>

        <div className="bg-card text-card-foreground border border-red-500/30 rounded-xl p-5 flex flex-col gap-2 cursor-pointer hover:bg-accent/50 transition-colors" onClick={() => setActiveTab('exceptions')}>
          <div className="flex justify-between items-center">
            <div className="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center"><AlertCircle size={18} className="text-red-400" /></div>
            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-red-500/10 text-red-500">Needs Review</span>
          </div>
          <div className="flex flex-col mt-1">
            <span className="text-xl font-bold font-mono text-red-400">{exceptions.filter(e => e.severity === 'Critical').length}</span>
            <span className="text-xs text-muted-foreground">Critical Exceptions</span>
          </div>
        </div>

        <div className="bg-card text-card-foreground border border-border rounded-xl p-5 flex flex-col gap-2">
          <div className="flex justify-between items-center">
            <div className="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center"><CheckCircle2 size={18} className="text-emerald-400" /></div>
            <span className="text-[10px] font-bold text-emerald-400">100% Ready</span>
          </div>
          <div className="flex flex-col mt-1">
            <span className="text-xl font-bold font-mono text-foreground">{dashInfo.readiness}</span>
            <span className="text-xs text-muted-foreground">Payroll Readiness</span>
          </div>
        </div>
      </div>

      <div className="dashboard-bottom-grid">
        <div className="card col-span-2">
          <h3 className="mb-4">Payroll Cost Forecast</h3>
          <div className="chart-container" style={{ height: '250px' }}>
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartData}>
                <defs>
                  <linearGradient id="colorCost" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="var(--accent-blue)" stopOpacity={0.3}/>
                    <stop offset="95%" stopColor="var(--accent-blue)" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border-color)" vertical={false} />
                <XAxis dataKey="name" stroke="var(--text-tertiary)" tick={{ fill: 'var(--text-tertiary)' }} />
                <YAxis stroke="var(--text-tertiary)" tick={{ fill: 'var(--text-tertiary)' }} tickFormatter={(val) => `₱${val/1000}k`} />
                <Tooltip contentStyle={{ backgroundColor: 'var(--bg-card)', borderColor: 'var(--border-color)', color: 'var(--foreground)', borderRadius: '8px' }} itemStyle={{ color: 'var(--accent-blue)' }} />
                <Area type="monotone" dataKey="cost" stroke="var(--accent-blue)" strokeWidth={3} fillOpacity={1} fill="url(#colorCost)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>
    </div>
  );
}
