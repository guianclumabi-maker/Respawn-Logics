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
  Gamepad
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
import './App.css';

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

function App() {
  const [activeTab, setActiveTab] = useState('dashboard');
  const [isLoading, setIsLoading] = useState(true);
  
  // Data States
  const [dashInfo, setDashInfo] = useState<any>(null);
  const [chartData, setChartData] = useState<any[]>([]);
  const [exceptions, setExceptions] = useState<any[]>([]);
  const [queue, setQueue] = useState<any[]>([]);
  const [compData, setCompData] = useState<any>(null);
  const [settings, setSettings] = useState<any>({});
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
      API.fetchPayslipsList().then(setPayslipsList),
      API.fetchGovReports().then(setGovReports)
    ]).then(() => {
      setIsLoading(false);
    }).catch(err => {
      console.error("API Error during load:", err);
      setIsLoading(false);
    });
  }, []);

  // Theme sync: listen for changes made by the main platform in other tabs.
  // The main platform's toggleTheme() in sidebar.php writes to localStorage('theme').
  // The storage event fires in OTHER open tabs on the same origin.
  useEffect(() => {
    const handleStorageChange = (e: StorageEvent) => {
      if (e.key === 'theme' && e.newValue) {
        document.documentElement.setAttribute('data-theme', e.newValue);
      }
    };
    window.addEventListener('storage', handleStorageChange);
    return () => window.removeEventListener('storage', handleStorageChange);
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

  const renderSettings = () => (
    <div className="dashboard-content animate-slide-up">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-2xl font-bold">Payroll Automation Settings</h2>
          <p className="text-muted mt-1">Configure how the Payroll Engine calculates your runs.</p>
        </div>
        <button className="btn btn-primary"><Save size={18} /> Save Settings</button>
      </div>

      <div className="dashboard-bottom-grid" style={{ gridTemplateColumns: '1fr 1fr' }}>
        
        {/* Calc Mode */}
        <div className="card col-span-2">
          <h3 className="mb-4 flex items-center gap-2"><ServerCog className="text-blue-500"/> Payroll Calculation Source</h3>
          <div className="grid grid-cols-3 gap-4">
            {['Manual', 'Semi-Automatic', 'Fully Automatic'].map(mode => (
              <div 
                key={mode}
                className={`p-4 border rounded-lg cursor-pointer transition-all ${
                  settings.calcMode === mode 
                  ? 'border-blue-500 bg-blue-500/10' 
                  : 'border-border-color bg-bg-card hover:border-border-light'
                }`}
                onClick={() => setSettings({...settings, calcMode: mode})}
              >
                <div className="flex items-center justify-between mb-2">
                  <h4 className="font-semibold">{mode}</h4>
                  {settings.calcMode === mode && <CheckCircle2 size={18} className="text-blue-500" />}
                </div>
                <p className="text-sm text-muted">
                  {mode === 'Manual' && 'System only computes totals based on manual entry of Salary, OT, Allowances.'}
                  {mode === 'Semi-Automatic' && 'Pulls Attendance & Leave automatically. Calculates Work Days. Officer reviews.'}
                  {mode === 'Fully Automatic' && 'Automatically calculates everything including Lates, ND, Holidays, and Recurring modifiers.'}
                </p>
              </div>
            ))}
          </div>
        </div>

        {/* Automation Toggles */}
        <div className="card">
          <h3 className="mb-4">Data Extraction</h3>
          <div className="space-y-4">
            <div className="flex justify-between items-center p-3 bg-bg-card-hover rounded-lg border border-border-color">
              <div>
                <h4 className="font-medium text-sm">Auto Pull Attendance</h4>
                <p className="text-xs text-muted">Ingest data from attendance_payroll_summary</p>
              </div>
              <button onClick={() => toggleSetting('auto_pull_attendance')} className="text-blue-500 bg-transparent border-none cursor-pointer">
                {settings.auto_pull_attendance ? <ToggleRight size={32}/> : <ToggleLeft size={32} className="text-slate-500"/>}
              </button>
            </div>
            
            <div className="flex justify-between items-center p-3 bg-bg-card-hover rounded-lg border border-border-color">
              <div>
                <h4 className="font-medium text-sm">Auto Pull Leave</h4>
                <p className="text-xs text-muted">Calculate paid/unpaid leaves automatically</p>
              </div>
              <button onClick={() => toggleSetting('auto_pull_leave')} className="text-blue-500 bg-transparent border-none cursor-pointer">
                {settings.auto_pull_leave ? <ToggleRight size={32}/> : <ToggleLeft size={32} className="text-slate-500"/>}
              </button>
            </div>
            
            <div className="flex justify-between items-center p-3 bg-bg-card-hover rounded-lg border border-border-color">
              <div>
                <h4 className="font-medium text-sm">Auto Apply Recurring Modifiers</h4>
                <p className="text-xs text-muted">Apply recurring earnings and deductions</p>
              </div>
              <button onClick={() => toggleSetting('auto_apply_recurring')} className="text-blue-500 bg-transparent border-none cursor-pointer">
                {settings.auto_apply_recurring ? <ToggleRight size={32}/> : <ToggleLeft size={32} className="text-slate-500"/>}
              </button>
            </div>
          </div>
        </div>

        <div className="card">
          <h3 className="mb-4">Calculation Overrides</h3>
          <div className="space-y-4">
            <div className="flex justify-between items-center p-3 bg-bg-card-hover rounded-lg border border-border-color">
              <div>
                <h4 className="font-medium text-sm">Auto Calculate Overtime</h4>
                <p className="text-xs text-muted">Compute OT based on payroll_earning_types</p>
              </div>
              <button onClick={() => toggleSetting('auto_calc_ot')} className="text-blue-500 bg-transparent border-none cursor-pointer">
                {settings.auto_calc_ot ? <ToggleRight size={32}/> : <ToggleLeft size={32} className="text-slate-500"/>}
              </button>
            </div>
            
            <div className="flex justify-between items-center p-3 bg-bg-card-hover rounded-lg border border-border-color">
              <div>
                <h4 className="font-medium text-sm">Auto Calculate Lates & Undertime</h4>
                <p className="text-xs text-muted">Deduct from base salary based on minutes late</p>
              </div>
              <button onClick={() => toggleSetting('auto_calc_lates')} className="text-blue-500 bg-transparent border-none cursor-pointer">
                {settings.auto_calc_lates ? <ToggleRight size={32}/> : <ToggleLeft size={32} className="text-slate-500"/>}
              </button>
            </div>

            <div className="flex justify-between items-center p-3 bg-bg-card-hover rounded-lg border border-border-color">
              <div>
                <h4 className="font-medium text-sm text-amber-500">Allow Manual Overrides</h4>
                <p className="text-xs text-muted">Allow Payroll Officer to mutate final pay totals</p>
              </div>
              <button onClick={() => toggleSetting('allow_manual_override')} className="text-amber-500 bg-transparent border-none cursor-pointer">
                {settings.allow_manual_override ? <ToggleRight size={32}/> : <ToggleLeft size={32} className="text-slate-500"/>}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );

  return (
    <div className="app-container relative z-0">
      {/* Global Background Glow Effects */}
      <div style={{ position: "absolute", top: -100, left: -100, width: 500, height: 500, borderRadius: "50%", background: "#00e07a", filter: "blur(120px)", opacity: 0.06, pointerEvents: "none", zIndex: -1 }} />
      <div style={{ position: "absolute", bottom: -150, right: -100, width: 600, height: 600, borderRadius: "50%", background: "#9b6dff", filter: "blur(140px)", opacity: 0.05, pointerEvents: "none", zIndex: -1 }} />
      
      {/* Sidebar */}
      <aside className="sidebar glass">
        <div className="sidebar-brand" style={{ display: 'flex', alignItems: 'center', padding: '16px' }}>
          <div style={{ width: '32px', height: '32px', background: 'linear-gradient(135deg, #00e07a, #00b8ff)', borderRadius: '7px', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#000', fontSize: '16px', marginRight: '12px' }}>
            <Gamepad size={18} />
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <span className="brand-text" style={{ fontFamily: "'JetBrains Mono', monospace", fontWeight: 700, color: 'var(--text-primary)', textTransform: 'none', fontSize: '15px', letterSpacing: '-0.5px' }}>Respawn Logics</span>
            <span style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: '9px', fontWeight: 700, letterSpacing: '0.1em', color: '#00e07a', background: 'rgba(0,224,122,0.1)', padding: '2px 4px', border: '1px solid rgba(0,224,122,0.22)', borderRadius: '4px' }}>v2.0</span>
          </div>
        </div>
        
        <nav className="sidebar-nav">
          <div className="nav-section">
            <h4 className="nav-heading">PAYROLL</h4>
            <button className={`nav-item ${activeTab === 'dashboard' ? 'active' : ''}`} onClick={() => setActiveTab('dashboard')}>
              <LayoutDashboard size={18} /> Dashboard
            </button>
          </div>

          <div className="nav-section">
            <h4 className="nav-heading">PROCESSING</h4>
            <button className={`nav-item ${activeTab === 'queue' ? 'active' : ''}`} onClick={() => setActiveTab('queue')}>
              <CalendarClock size={18} /> Payroll Queue
            </button>
            <button className={`nav-item exceptions-alert ${activeTab === 'exceptions' ? 'active' : ''}`} onClick={() => setActiveTab('exceptions')}>
              <AlertCircle size={18} /> Exceptions 
              <span className="badge badge-red ml-auto">{exceptions.filter(e => e.severity === 'Critical').length}</span>
            </button>
          </div>

          <div className="nav-section">
            <h4 className="nav-heading">EMPLOYEE PAY</h4>
            <button className={`nav-item ${activeTab === 'compensation' ? 'active' : ''}`} onClick={() => setActiveTab('compensation')}>
              <Users size={18} /> Compensation
            </button>
            <button className={`nav-item ${activeTab === 'payslips' ? 'active' : ''}`} onClick={() => {setActiveTab('payslips'); setSelectedPayslipDetails(null);}}>
              <FileText size={18} /> Payslips
            </button>
          </div>

          <div className="nav-section">
            <h4 className="nav-heading">COMPLIANCE</h4>
            <button className={`nav-item ${activeTab === 'govreports' ? 'active' : ''}`} onClick={() => setActiveTab('govreports')}>
              <ShieldCheck size={18} /> Tax & Gov Reports
            </button>
          </div>

          <div className="nav-section">
            <h4 className="nav-heading">CONFIGURATION</h4>
            <button className={`nav-item ${activeTab === 'settings' ? 'active' : ''}`} onClick={() => setActiveTab('settings')}>
              <Settings size={18} /> Settings
            </button>
          </div>
        </nav>
        
        <div className="sidebar-footer">
          <a href={window.location.hostname === 'localhost' ? '/respawn-logics/pages/dashboard.php' : '/pages/dashboard.php'} className="btn btn-secondary w-full mb-4" style={{ textDecoration: 'none' }}>
             ← Return to Core HRIS
          </a>
          <div className="user-profile">
            <div className="avatar">AD</div>
            <div className="user-info">
              <span className="user-name">Admin User</span>
              <span className="user-role">System Admin</span>
            </div>
          </div>
        </div>
      </aside>

      {/* Main Content */}
      <main className="main-content">
        <header className="topbar glass">
          <div className="page-title">
            <h1 className="capitalize">{activeTab === 'govreports' ? 'Tax & Gov Reports' : activeTab.replace('-', ' ')}</h1>
            <p className="text-muted">Enterprise Payroll Module</p>
          </div>
          <div className="topbar-actions">
            <button className="icon-btn relative">
              <Bell size={20} />
              <span className="notification-dot"></span>
            </button>
            <button className="btn btn-primary">
              <PlayCircle size={18} /> New Payroll Run
            </button>
          </div>
        </header>

        {activeTab === 'dashboard' && renderDashboard()}
        {activeTab === 'queue' && renderQueue()}
        {activeTab === 'exceptions' && renderExceptions()}
        {activeTab === 'compensation' && renderCompensation()}
        {activeTab === 'settings' && renderSettings()}
        {activeTab === 'payslips' && renderPayslips()}
        {activeTab === 'govreports' && renderGovReports()}
        
      </main>
    </div>
  );
}

export default App;
