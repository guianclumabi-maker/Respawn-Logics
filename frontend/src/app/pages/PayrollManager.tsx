// @ts-nocheck
import { useState, useEffect } from 'react';
import { 
  LayoutDashboard, 
  Settings, 
  Users, 
  FileText, 
  ShieldCheck, 
  CalendarClock, 
  AlertCircle, 
  PlayCircle,
  TrendingUp,
  Banknote,
  CheckCircle2,
  Bell,
  Search,
  Filter,
  MoreVertical,
  AlertTriangle,
  Info,
  Plus,
  History,
  ArrowUpRight,
  Save,
  ToggleRight,
  ToggleLeft,
  ServerCog,
  Download,
  Printer,
  Gamepad2
} from 'lucide-react';
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer
} from 'recharts';
import './PayrollManager.css';

const API_BASE = window.location.origin + (window.location.hostname === 'localhost' ? '/respawn-logics' : '') + '/api/index.php?route=payroll_engine';

const API = {
  fetchDashboardInfo: () => fetch(`${API_BASE}&action=dashboard_kpis`).then(r => r.json()).then(d => d.data || {
    nextDate: 'N/A', estimatedCost: 0, costIncrease: 0, readiness: 'N/A', activeRunName: 'None', activeRunTotalEmployees: 0, activeRunProcessed: 0
  }),
  fetchChartData: () => fetch(`${API_BASE}&action=chart_data`).then(r => r.json()).then(d => d.data || []),
  fetchExceptions: () => fetch(`${API_BASE}&action=exceptions_list`).then(r => r.json()).then(d => d.data || []),
  fetchQueue: () => fetch(`${API_BASE}&action=runs`).then(r => r.json()).then(d => {
    return (d.data || []).map((r:any) => ({
      id: `PR-${r.id}`, origin: r.schedule_name || 'Manual', period: `${r.payroll_period_start} to ${r.payroll_period_end}`, status: r.status, employees: 0, cost: 'Pending'
    }));
  }),
  fetchCompHistory: () => fetch(`${API_BASE}&action=comp_history`).then(r => r.json()).then(d => d.data || { history: [], audits: [] }),
  fetchSettings: () => fetch(`${API_BASE}&action=settings`).then(r => r.json()).then(d => d.data || {}),
  saveSettings: (data: any) => fetch(`${API_BASE}&action=save_settings`, { method: 'POST', body: JSON.stringify(data) }).then(r => r.json()),
  fetchComponents: () => fetch(`${API_BASE}&action=components_list`).then(r => r.json()).then(d => d.data || []),
  saveComponent: (data: any) => fetch(`${API_BASE}&action=component_save`, { method: 'POST', body: JSON.stringify(data) }).then(r => r.json()),
  deleteComponent: (id: number) => fetch(`${API_BASE}&action=component_delete`, { method: 'POST', body: JSON.stringify({id}) }).then(r => r.json()),
  fetchPayslipsList: () => fetch(`${API_BASE}&action=payslips_admin`).then(r => r.json()).then(d => {
    return (d.data || []).map((ps:any) => ({ id: `PS-${ps.id}`, emp: ps.empName, period: ps.period, net: ps.net, status: ps.status }));
  }),
  fetchPayslipDetails: (id: string) => {
    const rawId = id.replace('PS-', '');
    return fetch(`${API_BASE}&action=payslip_details&id=${rawId}`).then(r => r.json()).then(d => {
      const p = d.data;
      if(!p) return null;
      return {
        id, companyName: 'Respawn Logic', companyAddress: 'Enterprise HRIS', period: p.period, empName: p.empName, empId: p.empId, empPosition: 'Staff', bankDetails: 'N/A', status: 'Published',
        earnings: p.earnings || [], deductions: p.deductions || [], gross: p.gross, totalDeductions: p.totalDeductions, netPay: p.netPay
      };
    });
  },
  fetchGovReports: () => fetch(`${API_BASE}&action=gov_reports`).then(r => r.json()).then(d => d.data || [])
};

export function PayrollManager() {
  const [activeTab, setActiveTab] = useState('dashboard');
  const [isLoading, setIsLoading] = useState(true);
  
  // Data States
  const [dashInfo, setDashInfo] = useState<any>(null);
  const [chartData, setChartData] = useState<any[]>([]);
  const [exceptions, setExceptions] = useState<any[]>([]);
  const [queue, setQueue] = useState<any[]>([]);
  const [compData, setCompData] = useState<any>(null);
  const [settings, setSettings] = useState<any>({});
  const [payComponents, setPayComponents] = useState<any[]>([]);
  const [showComponentModal, setShowComponentModal] = useState(false);
  const [editingComponent, setEditingComponent] = useState<any>(null);
  const [payslipsList, setPayslipsList] = useState<any[]>([]);
  const [govReports, setGovReports] = useState<any[]>([]);
  
  const [selectedPayslipDetails, setSelectedPayslipDetails] = useState<any>(null);

  // Live Progress Simulation State
  const [processedEmployees, setProcessedEmployees] = useState(0);
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    Promise.all([
      API.fetchDashboardInfo().then(setDashInfo),
      API.fetchChartData().then(setChartData),
      API.fetchExceptions().then(setExceptions),
      API.fetchQueue().then(setQueue),
      API.fetchCompHistory().then(setCompData),
      API.fetchSettings().then(setSettings),
      API.fetchComponents().then(setPayComponents),
      API.fetchPayslipsList().then(setPayslipsList),
      API.fetchGovReports().then(setGovReports)
    ]).then(() => {
      setIsLoading(false);
    }).catch(err => {
      console.error("API Error during load:", err);
      setIsLoading(false);
    });
  }, []);

  // Cross-tab theme sync: BroadcastChannel fires in ALL open same-origin tabs instantly.
  // The main platform's toggleTheme() in sidebar.php broadcasts on 'respawn_theme'.
  // storage event is kept as fallback for browsers without BroadcastChannel support.
  useEffect(() => {
    const applyTheme = (theme: string) => {
      document.documentElement.setAttribute('data-theme', theme);
      try { localStorage.setItem('theme', theme); } catch(e) {}
    };

    // Primary: BroadcastChannel (instant across ALL tabs)
    let themeChannel: BroadcastChannel | null = null;
    try {
      themeChannel = new BroadcastChannel('respawn_theme');
      themeChannel.onmessage = (e: MessageEvent) => {
        if (e.data?.theme) applyTheme(e.data.theme);
      };
    } catch(e) {}

    // Fallback: storage event (only fires in OTHER tabs)
    const handleStorageChange = (e: StorageEvent) => {
      if (e.key === 'theme' && e.newValue) applyTheme(e.newValue);
    };
    window.addEventListener('storage', handleStorageChange);

    return () => {
      themeChannel?.close();
      window.removeEventListener('storage', handleStorageChange);
    };
  }, []);


  // Set initial processed amount once dashInfo loads
  useEffect(() => {
    if (dashInfo) {
      setProcessedEmployees(dashInfo.activeRunProcessed);
      setProgress(Math.floor((dashInfo.activeRunProcessed / dashInfo.activeRunTotalEmployees) * 100));

      // Theme priority: localStorage (set by main platform toggle) > server preference.
      // The index.html script already applied the theme before React loaded, but
      // we re-apply here as a safety net and write back if only the server knows.
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
      } else if (dashInfo.themePreference) {
        document.documentElement.setAttribute('data-theme', dashInfo.themePreference);
        localStorage.setItem('theme', dashInfo.themePreference);
      }
    }
  }, [dashInfo]);

  useEffect(() => {
    if (dashInfo && progress < 100 && progress > 0) {
      const timer = setTimeout(() => {
        const increment = Math.floor(Math.random() * 50) + 10;
        const nextProcessed = Math.min(processedEmployees + increment, dashInfo.activeRunTotalEmployees);
        setProcessedEmployees(nextProcessed);
        setProgress(Math.floor((nextProcessed / dashInfo.activeRunTotalEmployees) * 100));
      }, 500);
      return () => clearTimeout(timer);
    }
  }, [processedEmployees, progress, dashInfo]);

  const toggleSetting = (key: string) => {
    setSettings({...settings, [key]: !settings[key]});
  };

  const handleViewPayslip = async (id: string) => {
    try {
      const details = await API.fetchPayslipDetails(id);
      setSelectedPayslipDetails(details);
    } catch (err) {
      console.error("Failed to fetch payslip details:", err);
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
  };

  if (isLoading) {
    return <div className="flex items-center justify-center h-screen bg-app"><div className="pulse-indicator w-8 h-8"></div></div>;
  }

  // View Renders
  const renderDashboard = () => (
    <div className="dashboard-content animate-slide-up">
      <div className="card generation-card">
        <div className="flex justify-between items-center mb-4">
          <div className="flex items-center gap-3">
            <div className="pulse-indicator"></div>
            <h2 className="text-xl">Active Payroll Generation</h2>
          </div>
          <span className="badge badge-blue">Processing: {dashInfo.activeRunName}</span>
        </div>
        <div className="progress-section mt-2">
          <div className="flex justify-between items-end mb-3">
            <div className="flex flex-col">
              <div className="flex items-baseline gap-3">
                <span className="text-4xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">
                  {progress}%
                </span>
                <span className="text-sm font-medium text-tertiary">
                  {processedEmployees.toLocaleString()} / {dashInfo.activeRunTotalEmployees.toLocaleString()} Employees
                </span>
              </div>
            </div>
            <span className="text-sm font-medium text-emerald-400 flex items-center gap-2">
              <div className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
              Processing without errors
            </span>
          </div>
          <div className="progress-container">
            <div className="progress-bar-fill" style={{ width: `${progress}%` }}></div>
          </div>
        </div>
      </div>

      <div className="kpi-grid">
        <div className="card kpi-card">
          <div className="kpi-header">
            <div className="kpi-icon bg-blue-glow"><CalendarClock size={20} color="var(--accent-blue)" /></div>
            <span className="badge badge-emerald">On Track</span>
          </div>
          <div className="kpi-body">
            <span className="kpi-value">{dashInfo.nextDate}</span>
            <span className="kpi-label">Next Payroll Date</span>
          </div>
        </div>
        
        <div className="card kpi-card">
          <div className="kpi-header">
            <div className="kpi-icon bg-amber-glow"><TrendingUp size={20} color="var(--accent-amber)" /></div>
            <span className="text-xs text-amber-500">+{dashInfo.costIncrease}% from last</span>
          </div>
          <div className="kpi-body">
            <span className="kpi-value">{formatCurrency(dashInfo.estimatedCost)}</span>
            <span className="kpi-label">Estimated Payroll Cost</span>
          </div>
        </div>

        <div className="card kpi-card exception-kpi border-red-glow cursor-pointer hover:border-red-500" onClick={() => setActiveTab('exceptions')}>
          <div className="kpi-header">
            <div className="kpi-icon bg-red-glow"><AlertCircle size={20} color="var(--accent-red)" /></div>
            <span className="badge badge-red">Needs Review</span>
          </div>
          <div className="kpi-body">
            <span className="kpi-value text-red-400">{exceptions.filter(e => e.severity === 'Critical').length}</span>
            <span className="kpi-label">Critical Exceptions</span>
          </div>
        </div>

        <div className="card kpi-card">
          <div className="kpi-header">
            <div className="kpi-icon bg-emerald-glow"><CheckCircle2 size={20} color="var(--accent-emerald)" /></div>
            <span className="text-xs text-emerald-400">100% Ready</span>
          </div>
          <div className="kpi-body">
            <span className="kpi-value">{dashInfo.readiness}</span>
            <span className="kpi-label">Payroll Readiness</span>
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
                <Tooltip contentStyle={{ backgroundColor: 'var(--bg-card)', borderColor: 'var(--border-color)', color: '#fff', borderRadius: '8px' }} itemStyle={{ color: 'var(--accent-blue)' }} />
                <Area type="monotone" dataKey="cost" stroke="var(--accent-blue)" strokeWidth={3} fillOpacity={1} fill="url(#colorCost)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>
    </div>
  );

  const renderQueue = () => (
    <div className="dashboard-content animate-slide-up">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-2xl font-bold">Payroll Queue</h2>
          <p className="text-muted mt-1">Manage and track your active and pending payroll runs.</p>
        </div>
        <button className="btn btn-primary"><PlayCircle size={18} /> Run New Payroll</button>
      </div>

      <div className="card p-0 overflow-hidden">
        <div className="p-4 border-b border-border-light flex justify-between">
          <div className="flex gap-2">
            <button className="btn btn-secondary"><Filter size={16}/> Filter</button>
            <div className="search-bar">
              <Search size={16} className="text-muted" />
              <input type="text" placeholder="Search origins..." className="bg-transparent border-none text-white outline-none" />
            </div>
          </div>
        </div>
        <table className="data-table w-full text-left border-collapse">
          <thead>
            <tr>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Run ID</th>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Origin</th>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Period</th>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Employees</th>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Est. Cost</th>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Status</th>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {queue.map((run) => (
              <tr key={run.id} className="hover:bg-bg-card-hover border-b border-border-light transition-colors">
                <td className="p-4 font-medium text-blue-400">{run.id}</td>
                <td className="p-4">{run.origin}</td>
                <td className="p-4 text-muted">{run.period}</td>
                <td className="p-4">{run.employees.toLocaleString()}</td>
                <td className="p-4 font-medium">{run.cost}</td>
                <td className="p-4">
                  <span className={`badge ${
                    run.status === 'Processing' ? 'badge-blue' : 
                    run.status === 'Approved' ? 'badge-emerald' : 
                    run.status === 'Draft' ? 'badge-amber' : 'badge-red'
                  }`}>
                    {run.status === 'Processing' ? <><div className="pulse-indicator w-2 h-2 mr-2 bg-blue-500 shadow-none animation-none"></div> Processing</> : run.status}
                  </span>
                </td>
                <td className="p-4 text-right">
                  <button className="icon-btn ml-auto"><MoreVertical size={16} /></button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );

  const renderExceptions = () => (
    <div className="dashboard-content animate-slide-up">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-2xl font-bold text-red-100">Exceptions Center</h2>
          <p className="text-muted mt-1">Resolve data anomalies before processing payroll.</p>
        </div>
        <div className="flex gap-2">
          <button className="btn btn-secondary">Export Log</button>
          <button className="btn btn-primary bg-red-600 hover:bg-red-700 shadow-none text-white">Resolve Selected</button>
        </div>
      </div>

      <div className="grid grid-cols-3 gap-4 mb-6">
        <div className="card bg-red-900/20 border-red-500/30">
          <div className="flex items-center gap-3 mb-2">
            <AlertCircle className="text-red-500" />
            <span className="font-semibold text-red-200">Critical</span>
          </div>
          <h3 className="text-3xl font-bold text-red-400">{exceptions.filter(e => e.severity === 'Critical').length}</h3>
          <p className="text-sm text-red-300/60 mt-1">Blocks Payroll Generation</p>
        </div>
        <div className="card bg-amber-900/20 border-amber-500/30">
          <div className="flex items-center gap-3 mb-2">
            <AlertTriangle className="text-amber-500" />
            <span className="font-semibold text-amber-200">Warning</span>
          </div>
          <h3 className="text-3xl font-bold text-amber-400">{exceptions.filter(e => e.severity === 'Warning').length}</h3>
          <p className="text-sm text-amber-300/60 mt-1">Requires Officer Review</p>
        </div>
        <div className="card bg-blue-900/20 border-blue-500/30">
          <div className="flex items-center gap-3 mb-2">
            <Info className="text-blue-500" />
            <span className="font-semibold text-blue-200">Info</span>
          </div>
          <h3 className="text-3xl font-bold text-blue-400">{exceptions.filter(e => e.severity === 'Info').length}</h3>
          <p className="text-sm text-blue-300/60 mt-1">FYI Noteworthy Changes</p>
        </div>
      </div>

      <div className="card p-0 overflow-hidden">
        <table className="data-table w-full text-left border-collapse">
          <thead>
            <tr className="bg-bg-sidebar">
              <th className="p-4 w-12"><input type="checkbox" className="accent-blue-500" /></th>
              <th className="p-4 text-tertiary font-medium text-sm">Severity</th>
              <th className="p-4 text-tertiary font-medium text-sm">Exception Type</th>
              <th className="p-4 text-tertiary font-medium text-sm">Employee</th>
              <th className="p-4 text-tertiary font-medium text-sm">Description</th>
              <th className="p-4 text-tertiary font-medium text-sm text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {exceptions.map((exc) => (
              <tr key={exc.id} className="hover:bg-bg-card-hover border-t border-border-light transition-colors">
                <td className="p-4"><input type="checkbox" className="accent-blue-500" /></td>
                <td className="p-4">
                  <span className={`badge ${
                    exc.severity === 'Critical' ? 'badge-red' : 
                    exc.severity === 'Warning' ? 'badge-amber' : 'badge-blue'
                  }`}>
                    {exc.severity}
                  </span>
                </td>
                <td className="p-4 font-medium">{exc.type}</td>
                <td className="p-4 text-blue-300 hover:underline cursor-pointer">{exc.empName}</td>
                <td className="p-4 text-muted">{exc.desc}</td>
                <td className="p-4 text-right">
                  <button className="btn btn-secondary text-xs">Fix Now</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );

  const renderCompensation = () => {
    const dailyRate = compData.currentBase / 21.8; // Example generic formula
    const hourlyRate = dailyRate / 8;

    return (
      <div className="dashboard-content animate-slide-up">
        <div className="flex justify-between items-center mb-6">
          <div>
            <h2 className="text-2xl font-bold">Employee Compensation</h2>
            <p className="text-muted mt-1">Viewing salary history for: <strong className="text-white">{compData.employeeName} ({compData.employeeId})</strong></p>
          </div>
          <button className="btn btn-primary"><Plus size={18} /> New Compensation Record</button>
        </div>

        <div className="dashboard-bottom-grid">
          <div className="card col-span-2 relative">
            <h3 className="mb-4 flex items-center gap-2"><History size={20} className="text-blue-500"/> Compensation History</h3>
            
            <div className="relative pl-6 border-l-2 border-border-light space-y-6 mt-6 ml-2">
              {[...compData.history].reverse().map((comp: any, idx, arr) => {
                // Correctly calculate percentage increase chronologically
                const actualIndex = arr.length - 1 - idx;
                const previousRecord = actualIndex > 0 ? compData.history[actualIndex - 1] : null;
                const isIncrease = previousRecord && comp.base > previousRecord.base;
                const percentChange = previousRecord ? ((comp.base - previousRecord.base) / previousRecord.base) * 100 : 0;

                return (
                  <div key={comp.id} className="relative">
                    <div className={`absolute -left-[33px] w-4 h-4 rounded-full border-4 border-bg-card ${
                      comp.status === 'Active' ? 'bg-emerald-500' : 
                      comp.status === 'Future' ? 'bg-amber-500' : 'bg-slate-500'
                    }`}></div>
                    
                    <div className={`p-4 rounded-lg border ${
                      comp.status === 'Active' ? 'border-emerald-500/30 bg-emerald-500/5' :
                      comp.status === 'Future' ? 'border-amber-500/30 bg-amber-500/5' : 'border-border-color bg-bg-card-hover'
                    }`}>
                      <div className="flex justify-between items-start mb-2">
                        <div className="flex items-center gap-3">
                          <h4 className="text-lg font-semibold">{formatCurrency(comp.base)} / {comp.type}</h4>
                          {isIncrease && (
                            <span className="badge badge-emerald py-0 px-2 gap-1"><ArrowUpRight size={12}/> +{percentChange.toFixed(1)}%</span>
                          )}
                        </div>
                        <span className={`badge ${
                          comp.status === 'Active' ? 'badge-emerald' : 
                          comp.status === 'Future' ? 'badge-amber' : 'bg-slate-800 text-slate-400'
                        }`}>
                          {comp.status}
                        </span>
                      </div>
                      
                      <div className="flex items-center gap-6 text-sm text-tertiary">
                        <span className="flex items-center gap-1"><CalendarClock size={14}/> Effective: {comp.effective}</span>
                        <span className="flex items-center gap-1"><Users size={14}/> Authored By: {comp.author}</span>
                      </div>
                    </div>
                  </div>
                );
              })} 
            </div>
          </div>

          <div className="flex flex-col gap-4">
            <div className="card border-emerald-500/30 bg-emerald-900/10">
              <h4 className="text-sm text-emerald-400 font-semibold mb-2 uppercase tracking-wider">Current Active Rate</h4>
              <p className="text-3xl font-bold mb-1">{formatCurrency(compData.currentBase)}</p>
              <p className="text-sm text-tertiary">Monthly • PHP</p>
              
              <div className="mt-4 pt-4 border-t border-emerald-500/20 text-sm">
                <div className="flex justify-between mb-2">
                  <span className="text-tertiary">Daily Rate:</span>
                  <span className="text-white">{formatCurrency(dailyRate)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-tertiary">Hourly Rate:</span>
                  <span className="text-white">{formatCurrency(hourlyRate)}</span>
                </div>
              </div>
            </div>

            <div className="card">
              <h4 className="text-sm text-tertiary font-semibold mb-3 uppercase tracking-wider">Recent Audit Logs</h4>
              <div className="space-y-3">
                {compData.audits.map((audit: any, i: number) => (
                  <div key={i} className={`text-xs ${i > 0 ? 'border-t border-border-light pt-3' : ''}`}>
                    <span className={`${audit.type === 'warning' ? 'text-amber-400' : 'text-blue-400'} block mb-1`}>{audit.action}</span>
                    <span className="text-muted">By {audit.user} on {audit.date}</span>
                  </div>
                ))}
              </div>
              <button className="btn btn-secondary w-full mt-4 text-xs">View Full Audit Trail</button>
            </div>
          </div>
        </div>
      </div>
    );
  };

  const renderPayslips = () => (
    <div className="dashboard-content animate-slide-up">
      {!selectedPayslipDetails ? (
        <>
          <div className="flex justify-between items-center mb-6">
            <div>
              <h2 className="text-2xl font-bold">Payslips</h2>
              <p className="text-muted mt-1">Review and distribute employee payslips.</p>
            </div>
            <div className="flex gap-2">
              <button className="btn btn-secondary"><Filter size={16}/> Filter by Period</button>
              <button className="btn btn-primary"><Printer size={18} /> Print Batch</button>
            </div>
          </div>

          <div className="card p-0 overflow-hidden">
            <table className="data-table w-full text-left border-collapse">
              <thead>
                <tr>
                  <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Payslip ID</th>
                  <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Employee</th>
                  <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Period</th>
                  <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Net Pay</th>
                  <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Status</th>
                  <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {payslipsList.map((ps) => (
                  <tr key={ps.id} className="hover:bg-bg-card-hover border-b border-border-light transition-colors">
                    <td className="p-4 font-medium text-blue-400 cursor-pointer hover:underline" onClick={() => handleViewPayslip(ps.id)}>{ps.id}</td>
                    <td className="p-4">{ps.emp}</td>
                    <td className="p-4 text-muted">{ps.period}</td>
                    <td className="p-4 font-medium">{formatCurrency(ps.net)}</td>
                    <td className="p-4">
                      <span className={`badge ${ps.status === 'Published' ? 'badge-emerald' : 'badge-amber'}`}>
                        {ps.status}
                      </span>
                    </td>
                    <td className="p-4 text-right">
                      <button className="btn btn-secondary text-xs" onClick={() => handleViewPayslip(ps.id)}>View</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      ) : (
        /* Detailed Payslip View */
        <div className="flex flex-col items-center">
          <div className="w-full max-w-3xl flex justify-between items-center mb-4">
             <button className="btn btn-secondary" onClick={() => setSelectedPayslipDetails(null)}>Back to List</button>
             <div className="flex gap-2">
                <button className="btn btn-secondary"><Download size={18}/> Download PDF</button>
                <button className="btn btn-primary"><Printer size={18}/> Print</button>
             </div>
          </div>
          
          <div className="card w-full max-w-3xl bg-white text-slate-900">
            <div className="flex justify-between items-start border-b border-slate-200 pb-6 mb-6">
              <div>
                <h1 className="text-3xl font-bold text-slate-800 tracking-tight">PAYSLIP</h1>
                <p className="text-slate-500 mt-1">Period: {selectedPayslipDetails.period}</p>
                <p className="text-slate-500 mt-1">ID: {selectedPayslipDetails.id}</p>
              </div>
              <div className="text-right">
                <h3 className="font-bold text-slate-800 text-xl">{selectedPayslipDetails.companyName}</h3>
                <p className="text-slate-500 text-sm">{selectedPayslipDetails.companyAddress}</p>
              </div>
            </div>

            <div className="flex justify-between mb-8">
              <div>
                <p className="text-sm text-slate-500 uppercase font-semibold">Employee Details</p>
                <p className="font-bold text-lg text-slate-800">{selectedPayslipDetails.empName}</p>
                <p className="text-slate-600">ID: {selectedPayslipDetails.empId} | Position: {selectedPayslipDetails.empPosition}</p>
              </div>
              <div className="text-right">
                <p className="text-sm text-slate-500 uppercase font-semibold">Payment Details</p>
                <p className="text-slate-600">Bank: {selectedPayslipDetails.bankDetails}</p>
                <p className="text-slate-600">Status: <span className={selectedPayslipDetails.status === 'Published' ? 'text-emerald-600 font-bold' : 'text-amber-500 font-bold'}>{selectedPayslipDetails.status}</span></p>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-8 mb-8">
              {/* Earnings */}
              <div>
                <h4 className="border-b-2 border-slate-800 pb-2 mb-3 font-bold text-slate-800">Earnings</h4>
                <div className="space-y-2 text-slate-700">
                  {selectedPayslipDetails.earnings.map((e: any, i: number) => (
                    <div key={i} className="flex justify-between"><span>{e.label}</span><span>{formatCurrency(e.amount)}</span></div>
                  ))}
                </div>
                <div className="flex justify-between mt-4 pt-3 border-t border-slate-200 font-bold text-slate-800">
                  <span>Gross Earnings</span><span>{formatCurrency(selectedPayslipDetails.gross)}</span>
                </div>
              </div>

              {/* Deductions */}
              <div>
                <h4 className="border-b-2 border-slate-800 pb-2 mb-3 font-bold text-slate-800">Deductions</h4>
                <div className="space-y-2 text-slate-700">
                  {selectedPayslipDetails.deductions.map((d: any, i: number) => (
                    <div key={i} className="flex justify-between"><span>{d.label}</span><span>{formatCurrency(d.amount)}</span></div>
                  ))}
                </div>
                <div className="flex justify-between mt-4 pt-3 border-t border-slate-200 font-bold text-red-600">
                  <span>Total Deductions</span><span>- {formatCurrency(selectedPayslipDetails.totalDeductions)}</span>
                </div>
              </div>
            </div>

            {/* Net Pay */}
            <div className="bg-slate-100 p-6 rounded-lg flex justify-between items-center border border-slate-200">
              <span className="text-xl font-bold text-slate-700">NET PAY</span>
              <span className="text-3xl font-bold text-emerald-600">{formatCurrency(selectedPayslipDetails.netPay)}</span>
            </div>
          </div>
        </div>
      )}
    </div>
  );

  const renderGovReports = () => (
    <div className="dashboard-content animate-slide-up">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-2xl font-bold">Tax & Government Reports</h2>
          <p className="text-muted mt-1">Generate compliant reports for SSS, PhilHealth, Pag-IBIG, and BIR.</p>
        </div>
        <button className="btn btn-primary"><ShieldCheck size={18} /> Generate New Report</button>
      </div>

      <div className="grid grid-cols-4 gap-4 mb-8">
        <div className="card text-center cursor-pointer hover:border-blue-500">
          <div className="w-12 h-12 bg-blue-500/20 text-blue-400 rounded-full flex items-center justify-center mx-auto mb-3">
             <ShieldCheck size={24}/>
          </div>
          <h4 className="font-bold">SSS</h4>
          <p className="text-xs text-muted mt-1">R-1A, R-3</p>
        </div>
        <div className="card text-center cursor-pointer hover:border-emerald-500">
          <div className="w-12 h-12 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-3">
             <ShieldCheck size={24}/>
          </div>
          <h4 className="font-bold">PhilHealth</h4>
          <p className="text-xs text-muted mt-1">Er2, RF-1</p>
        </div>
        <div className="card text-center cursor-pointer hover:border-amber-500">
          <div className="w-12 h-12 bg-amber-500/20 text-amber-400 rounded-full flex items-center justify-center mx-auto mb-3">
             <ShieldCheck size={24}/>
          </div>
          <h4 className="font-bold">Pag-IBIG</h4>
          <p className="text-xs text-muted mt-1">MCRF</p>
        </div>
        <div className="card text-center cursor-pointer hover:border-purple-500">
          <div className="w-12 h-12 bg-purple-500/20 text-purple-400 rounded-full flex items-center justify-center mx-auto mb-3">
             <Banknote size={24}/>
          </div>
          <h4 className="font-bold">BIR Tax</h4>
          <p className="text-xs text-muted mt-1">1601-C, Alphalist</p>
        </div>
      </div>

      <div className="card p-0 overflow-hidden">
        <div className="p-4 border-b border-border-light">
          <h3 className="font-bold">Recent Generated Reports</h3>
        </div>
        <table className="data-table w-full text-left border-collapse">
          <thead>
            <tr>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Report ID</th>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Report Type</th>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Coverage</th>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Total Remittance</th>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light">Status</th>
              <th className="p-4 text-tertiary font-medium text-sm border-b border-border-light text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {govReports.map((report) => (
              <tr key={report.id} className="hover:bg-bg-card-hover border-b border-border-light transition-colors">
                <td className="p-4 font-medium text-blue-400">{report.id}</td>
                <td className="p-4">{report.type}</td>
                <td className="p-4 text-muted">{report.month}</td>
                <td className="p-4 font-bold">{report.total}</td>
                <td className="p-4">
                  <span className={`badge ${report.status === 'Generated' ? 'badge-emerald' : 'badge-amber'}`}>
                    {report.status}
                  </span>
                </td>
                <td className="p-4 text-right">
                  <button className="btn btn-secondary text-xs"><Download size={14}/> Download XML/DAT</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );

  const handleSaveSettings = () => {
    API.saveSettings(settings).then(res => {
      if(res.success) alert("Settings saved!");
      else alert("Failed to save settings: " + res.error);
    });
  };

  const handleSaveComponent = (e: any) => {
    e.preventDefault();
    API.saveComponent(editingComponent).then(res => {
      if(res.success) {
        setShowComponentModal(false);
        API.fetchComponents().then(setPayComponents);
      } else {
        alert("Failed to save component: " + res.error);
      }
    });
  };

  const handleDeleteComponent = (id: number) => {
    if(confirm("Are you sure you want to delete this component?")) {
      API.deleteComponent(id).then(res => {
        if(res.success) API.fetchComponents().then(setPayComponents);
        else alert("Failed to delete component: " + res.error);
      });
    }
  };

  const renderSettings = () => (
    <div className="dashboard-content animate-slide-up pb-20">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-2xl font-bold">Payroll Configuration</h2>
          <p className="text-muted mt-1">Configure global payroll policies and pay components.</p>
        </div>
        <button className="btn btn-primary" onClick={handleSaveSettings}><Save size={18} /> Save Settings</button>
      </div>

      <div className="dashboard-bottom-grid" style={{ gridTemplateColumns: '1fr 1fr' }}>
        
        {/* General Settings */}
        <div className="card col-span-2">
          <h3 className="mb-4 flex items-center gap-2"><ServerCog className="text-blue-500"/> General Policies</h3>
          <div className="grid grid-cols-2 gap-4">
            <div className="form-group">
              <label className="text-sm font-semibold mb-1 block">Default Pay Frequency</label>
              <select className="form-input w-full p-2 rounded bg-bg-card border border-border-color"
                value={settings.default_pay_frequency || 'Semi-Monthly'}
                onChange={e => setSettings({...settings, default_pay_frequency: e.target.value})}
              >
                <option value="Monthly">Monthly</option>
                <option value="Semi-Monthly">Semi-Monthly</option>
                <option value="Weekly">Weekly</option>
                <option value="Daily">Daily</option>
              </select>
            </div>
            
            <div className="form-group">
              <label className="text-sm font-semibold mb-1 block">Proration Method</label>
              <select className="form-input w-full p-2 rounded bg-bg-card border border-border-color"
                value={settings.proration_method || 'split_even'}
                onChange={e => setSettings({...settings, proration_method: e.target.value})}
              >
                <option value="split_even">Split Evenly (50% per cutoff)</option>
                <option value="full_first_cutoff">Full deduction on First Cutoff</option>
                <option value="full_second_cutoff">Full deduction on Second Cutoff</option>
              </select>
            </div>

            <div className="form-group">
              <label className="text-sm font-semibold mb-1 block">Default Pay Basis</label>
              <select className="form-input w-full p-2 rounded bg-bg-card border border-border-color"
                value={settings.default_pay_basis || 'monthly'}
                onChange={e => setSettings({...settings, default_pay_basis: e.target.value})}
              >
                <option value="monthly">Monthly</option>
                <option value="daily">Daily</option>
                <option value="hourly">Hourly</option>
              </select>
            </div>

            <div className="form-group">
              <label className="text-sm font-semibold mb-1 block">Rounding Mode</label>
              <select className="form-input w-full p-2 rounded bg-bg-card border border-border-color"
                value={settings.rounding_mode || 'half_up'}
                onChange={e => setSettings({...settings, rounding_mode: e.target.value})}
              >
                <option value="half_up">Round Half Up (.5 goes up)</option>
                <option value="half_even">Round Half Even (Banker's Rounding)</option>
              </select>
            </div>

            <div className="form-group flex items-center justify-between p-3 bg-bg-card-hover rounded-lg border border-border-color">
              <div>
                <h4 className="font-medium text-sm">Tax Annualization</h4>
                <p className="text-xs text-muted">Automatically calculate annualized tax on last run of year</p>
              </div>
              <button onClick={() => setSettings({...settings, tax_annualization: settings.tax_annualization ? 0 : 1})} className="text-blue-500 bg-transparent border-none cursor-pointer">
                {settings.tax_annualization ? <ToggleRight size={32}/> : <ToggleLeft size={32} className="text-slate-500"/>}
              </button>
            </div>

            <div className="form-group flex items-center justify-between p-3 bg-bg-card-hover rounded-lg border border-border-color">
              <div>
                <h4 className="font-medium text-sm">MWE Auto Exempt</h4>
                <p className="text-xs text-muted">Automatically zero out tax for Minimum Wage Earners</p>
              </div>
              <button onClick={() => setSettings({...settings, mwe_auto_exempt: settings.mwe_auto_exempt ? 0 : 1})} className="text-blue-500 bg-transparent border-none cursor-pointer">
                {settings.mwe_auto_exempt ? <ToggleRight size={32}/> : <ToggleLeft size={32} className="text-slate-500"/>}
              </button>
            </div>

          </div>
        </div>

        {/* Pay Components Table */}
        <div className="card col-span-2">
          <div className="flex justify-between items-center mb-4">
            <h3 className="flex items-center gap-2"><Banknote className="text-emerald-500"/> Pay Components</h3>
            <button className="btn btn-primary py-1 px-3" onClick={() => { setEditingComponent({ kind: 'earning', calc_type: 'fixed', is_active: 1, taxable: 1 }); setShowComponentModal(true); }}>
              <Plus size={16}/> Add Component
            </button>
          </div>
          
          <div className="table-container">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-border-color">
                  <th className="p-3">Code</th>
                  <th className="p-3">Name</th>
                  <th className="p-3">Type</th>
                  <th className="p-3">Calculation</th>
                  <th className="p-3">Value</th>
                  <th className="p-3">Taxable</th>
                  <th className="p-3">Status</th>
                  <th className="p-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {payComponents.length === 0 ? (
                  <tr><td colSpan={8} className="p-4 text-center text-muted">No components configured.</td></tr>
                ) : payComponents.map(c => (
                  <tr key={c.id} className="border-b border-border-color hover:bg-bg-card-hover">
                    <td className="p-3 font-mono text-sm">{c.code}</td>
                    <td className="p-3 font-semibold">{c.name}</td>
                    <td className="p-3">
                      <span className={`badge ${c.kind === 'earning' ? 'badge-emerald' : 'badge-rose'}`}>{c.kind}</span>
                    </td>
                    <td className="p-3 text-sm">{c.calc_type.replace('_', ' ')}</td>
                    <td className="p-3 font-mono">{c.value ? parseFloat(c.value).toLocaleString() : '-'}</td>
                    <td className="p-3">{c.taxable ? 'Yes' : 'No'}</td>
                    <td className="p-3">{c.is_active ? 'Active' : 'Inactive'}</td>
                    <td className="p-3 flex gap-2">
                      <button className="text-blue-500 hover:text-blue-400" onClick={() => { setEditingComponent(c); setShowComponentModal(true); }}>Edit</button>
                      <button className="text-rose-500 hover:text-rose-400" onClick={() => handleDeleteComponent(c.id)}>Delete</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

      </div>

      {showComponentModal && (
        <div className="modal-overlay flex items-center justify-center fixed inset-0 bg-black/50 z-50">
          <div className="modal-content bg-bg-card p-6 rounded-lg w-[500px] border border-border-color shadow-xl">
            <h3 className="text-xl font-bold mb-4">{editingComponent?.id ? 'Edit Component' : 'Add Component'}</h3>
            <form onSubmit={handleSaveComponent} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm mb-1">Code</label>
                  <input required className="w-full p-2 rounded border border-border-color bg-bg-app" 
                    value={editingComponent?.code || ''} 
                    onChange={e => setEditingComponent({...editingComponent, code: e.target.value})} 
                  />
                </div>
                <div>
                  <label className="block text-sm mb-1">Name</label>
                  <input required className="w-full p-2 rounded border border-border-color bg-bg-app" 
                    value={editingComponent?.name || ''} 
                    onChange={e => setEditingComponent({...editingComponent, name: e.target.value})} 
                  />
                </div>
              </div>
              
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm mb-1">Kind</label>
                  <select className="w-full p-2 rounded border border-border-color bg-bg-app"
                    value={editingComponent?.kind || 'earning'}
                    onChange={e => setEditingComponent({...editingComponent, kind: e.target.value})}
                  >
                    <option value="earning">Earning</option>
                    <option value="deduction">Deduction</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm mb-1">Calc Type</label>
                  <select className="w-full p-2 rounded border border-border-color bg-bg-app"
                    value={editingComponent?.calc_type || 'fixed'}
                    onChange={e => setEditingComponent({...editingComponent, calc_type: e.target.value})}
                  >
                    <option value="fixed">Fixed Amount</option>
                    <option value="percent_of_base">% of Base Salary</option>
                    <option value="statutory">Statutory Config</option>
                  </select>
                </div>
              </div>

              {(editingComponent?.calc_type === 'fixed' || editingComponent?.calc_type === 'percent_of_base') && (
                <div>
                  <label className="block text-sm mb-1">Value {editingComponent?.calc_type === 'percent_of_base' ? '(%)' : '(Amount)'}</label>
                  <input type="number" step="0.01" className="w-full p-2 rounded border border-border-color bg-bg-app" 
                    value={editingComponent?.value || ''} 
                    onChange={e => setEditingComponent({...editingComponent, value: e.target.value})} 
                  />
                </div>
              )}

              {editingComponent?.calc_type === 'statutory' && (
                <div>
                  <label className="block text-sm mb-1">Statutory Key</label>
                  <input className="w-full p-2 rounded border border-border-color bg-bg-app" placeholder="e.g. SSS_EE, PHIC_EE"
                    value={editingComponent?.statutory_key || ''} 
                    onChange={e => setEditingComponent({...editingComponent, statutory_key: e.target.value})} 
                  />
                </div>
              )}

              <div className="flex gap-4">
                <label className="flex items-center gap-2 text-sm cursor-pointer">
                  <input type="checkbox" checked={editingComponent?.taxable == 1} 
                    onChange={e => setEditingComponent({...editingComponent, taxable: e.target.checked ? 1 : 0})} 
                  /> Taxable
                </label>
                <label className="flex items-center gap-2 text-sm cursor-pointer">
                  <input type="checkbox" checked={editingComponent?.is_active == 1} 
                    onChange={e => setEditingComponent({...editingComponent, is_active: e.target.checked ? 1 : 0})} 
                  /> Active
                </label>
              </div>

              <div className="flex justify-end gap-3 mt-6">
                <button type="button" className="btn bg-slate-700 hover:bg-slate-600 text-white" onClick={() => setShowComponentModal(false)}>Cancel</button>
                <button type="submit" className="btn btn-primary">Save Component</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-[#0f172a] relative z-0">
      {/* Global Background Glow Effects */}
      <div style={{ position: "absolute", top: -100, left: -100, width: 500, height: 500, borderRadius: "50%", background: "#00e07a", filter: "blur(120px)", opacity: 0.06, pointerEvents: "none", zIndex: -1 }} />
      <div style={{ position: "absolute", bottom: -150, right: -100, width: 600, height: 600, borderRadius: "50%", background: "#9b6dff", filter: "blur(140px)", opacity: 0.05, pointerEvents: "none", zIndex: -1 }} />
      
      {/* Main Content */}
      <div className="flex-1 flex flex-col h-full overflow-hidden">
        {/* Module-specific top bar (stripped of global chrome) */}
        <header className="flex-none px-8 py-4 border-b border-white/5 bg-[#161922]/50 backdrop-blur-md flex items-center justify-between">
          <div className="flex items-center gap-4 w-full justify-between">
            <div className="flex bg-black/20 rounded-lg p-1 border border-white/10">
              <button className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'dashboard' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-gray-400 hover:text-white'}`} onClick={() => setActiveTab('dashboard')}>Dashboard</button>
              <button className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'queue' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-gray-400 hover:text-white'}`} onClick={() => setActiveTab('queue')}>Queue</button>
              <button className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'exceptions' ? 'bg-red-500/20 text-red-500' : 'text-gray-400 hover:text-white'}`} onClick={() => setActiveTab('exceptions')}>Exceptions ({exceptions.filter(e => e.severity === 'Critical').length})</button>
              <button className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'compensation' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-gray-400 hover:text-white'}`} onClick={() => setActiveTab('compensation')}>Compensation</button>
              <button className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'payslips' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-gray-400 hover:text-white'}`} onClick={() => setActiveTab('payslips')}>Payslips</button>
              <button className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'govreports' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-gray-400 hover:text-white'}`} onClick={() => setActiveTab('govreports')}>Reports</button>
              <button className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'settings' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-gray-400 hover:text-white'}`} onClick={() => setActiveTab('settings')}>Settings</button>
            </div>
            <button className="px-4 py-2 bg-[#00e07a] text-black font-bold rounded-lg text-sm shadow-[0_0_10px_rgba(0,224,122,0.3)] flex items-center gap-2">
              <PlayCircle size={16} /> New Run
            </button>
          </div>
        </header>

        <div className="flex-1 overflow-auto p-8">
          {activeTab === 'dashboard' && renderDashboard()}
          {activeTab === 'queue' && renderQueue()}
          {activeTab === 'exceptions' && renderExceptions()}
          {activeTab === 'compensation' && renderCompensation()}
          {activeTab === 'settings' && renderSettings()}
          {activeTab === 'payslips' && renderPayslips()}
          {activeTab === 'govreports' && renderGovReports()}
        </div>
      </div>
    </div>
  );
}
