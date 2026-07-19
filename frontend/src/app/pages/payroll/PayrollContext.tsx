import React, { createContext, useContext, useState, useEffect } from 'react';
import { apiFetch } from '../../lib/apiClient';
import { useAuth } from '../../context/AuthContext';
import { useTour } from '../../lib/useTour';

const API = {
  fetchDashboardInfo: () => apiFetch('/api/index.php?route=payroll_engine&action=dashboard_kpis').then(r => r.json()).then(d => d.data || {
    nextDate: 'N/A', estimatedCost: 0, costIncrease: 0, readiness: 'N/A', activeRunName: 'None', activeRunTotalEmployees: 0, activeRunProcessed: 0
  }),
  fetchChartData: () => apiFetch('/api/index.php?route=payroll_engine&action=chart_data').then(r => r.json()).then(d => d.data || []),
  fetchExceptions: () => apiFetch('/api/index.php?route=payroll_engine&action=exceptions_list').then(r => r.json()).then(d => d.data || []),
  fetchQueue: () => apiFetch('/api/index.php?route=payroll_engine&action=runs').then(r => r.json()).then(d => {
    return (d.data || []).map((r:any) => ({
      id: `PR-${r.id}`, origin: r.schedule_name || 'Manual', period: `${r.payroll_period_start} to ${r.payroll_period_end}`, status: r.status, employees: 0, cost: 'Pending'
    }));
  }),
  fetchCompHistory: () => apiFetch('/api/index.php?route=payroll_engine&action=comp_history').then(r => r.json()).then(d => d.data || { history: [], audits: [] }),
  fetchSettings: () => apiFetch('/api/index.php?route=payroll_engine&action=settings').then(r => r.json()).then(d => d.data || {}),
  saveSettings: (data: any) => apiFetch('/api/index.php?route=payroll_engine&action=save_settings', { method: 'POST', body: JSON.stringify(data) }).then(r => r.json()),
  fetchComponents: () => apiFetch('/api/index.php?route=payroll_engine&action=components_list').then(r => r.json()).then(d => d.data || []),
  saveComponent: (data: any) => apiFetch('/api/index.php?route=payroll_engine&action=component_save', { method: 'POST', body: JSON.stringify(data) }).then(r => r.json()),
  deleteComponent: (id: number) => apiFetch('/api/index.php?route=payroll_engine&action=component_delete', { method: 'POST', body: JSON.stringify({id}) }).then(r => r.json()),
  fetchPayslipsList: () => apiFetch('/api/index.php?route=payroll_engine&action=payslips_admin').then(r => r.json()).then(d => {
    return (d.data || []).map((ps:any) => ({ id: `PS-${ps.id}`, emp: ps.empName, period: ps.period, net: ps.net, status: ps.status }));
  }),
  fetchPayslipDetails: (id: string) => {
    const rawId = id.replace('PS-', '');
    return apiFetch(`/api/index.php?route=payroll_engine&action=payslip_details&id=${rawId}`).then(r => r.json()).then(d => {
      const p = d.data;
      if(!p) return null;
      return {
        id, companyName: 'Respawn Logic', companyAddress: 'Enterprise HRIS', period: p.period, empName: p.empName, empId: p.empId, empPosition: 'Staff', bankDetails: 'N/A', status: 'Published',
        earnings: p.earnings || [], deductions: p.deductions || [], gross: p.gross, totalDeductions: p.totalDeductions, netPay: p.netPay
      };
    });
  },
  fetchGovReports: () => apiFetch('/api/index.php?route=payroll_engine&action=gov_reports').then(r => r.json()).then(d => d.data || []),
  fetchSchedules: () => apiFetch('/api/index.php?route=payroll_engine&action=schedules').then(r => r.json()).then(d => d.data || []),
  generateRun: (payload: any) => apiFetch('/api/index.php?route=payroll_engine&action=generate_run', { method: 'POST', body: JSON.stringify(payload) }).then(r => r.json()),
  updateRunStatus: (runId: number, status: string) => apiFetch('/api/index.php?route=payroll_engine&action=update_run_status', { method: 'POST', body: JSON.stringify({ run_id: runId, status }) }).then(r => r.json()),
  fetchRunDetails: (runId: number) => apiFetch(`/api/index.php?route=payroll_engine&action=run_details&id=${runId}`).then(r => r.json()).then(d => d.data || null)
};

interface PayrollContextType {
  activeTab: string;
  setActiveTab: (tab: string) => void;
  isLoading: boolean;
  dashInfo: any;
  chartData: any[];
  exceptions: any[];
  queue: any[];
  compData: any;
  settings: any;
  setSettings: (s: any) => void;
  payComponents: any[];
  setPayComponents: (c: any[]) => void;
  showComponentModal: boolean;
  setShowComponentModal: (show: boolean) => void;
  editingComponent: any;
  setEditingComponent: (c: any) => void;
  payslipsList: any[];
  govReports: any[];
  selectedPayslipDetails: any;
  setSelectedPayslipDetails: (d: any) => void;
  timesheets: any[];
  employees: any[];
  tsStart: string;
  setTsStart: (d: string) => void;
  tsEnd: string;
  setTsEnd: (d: string) => void;
  tsEmpId: string;
  setTsEmpId: (id: string) => void;
  tsStatus: string;
  setTsStatus: (s: string) => void;
  editingTsId: number | null;
  setEditingTsId: (id: number | null) => void;
  editingTsData: any;
  setEditingTsData: (d: any) => void;
  selectedTsIds: number[];
  setSelectedTsIds: (ids: number[]) => void;
  isTsLoading: boolean;
  isGenerating: boolean;
  holidays: any[];
  showHolidays: boolean;
  setShowHolidays: (show: boolean) => void;
  isHolidaysLoading: boolean;
  newHoliday: any;
  setNewHoliday: (h: any) => void;
  progress: number;
  processedEmployees: number;
  fetchTimesheets: () => Promise<void>;
  fetchEmployees: () => Promise<void>;
  handleSaveTsRow: (row: any) => Promise<void>;
  handleSetTsStatus: (status: 'Approved' | 'Rejected', ids?: number[]) => Promise<void>;
  handleSetTsStatusPeriod: (status: 'Approved' | 'Rejected') => Promise<void>;
  handleDeleteTsRow: (id: number) => Promise<void>;
  handleGenerateDraft: () => Promise<void>;
  fetchHolidays: () => Promise<void>;
  handleSaveHoliday: (e: any) => Promise<void>;
  handleDeleteHoliday: (id: number) => Promise<void>;
  handleSaveSettings: () => void;
  handleSaveComponent: (e: any) => void;
  handleDeleteComponent: (id: number) => void;
  handleExport: () => void;
  handleViewPayslip: (id: string) => Promise<void>;
  formatCurrency: (amount: number) => string;
  startTimesheetTour: () => void;
  hasPermission: (perm: string) => boolean;
  // Run lifecycle
  showNewRunModal: boolean;
  setShowNewRunModal: (show: boolean) => void;
  schedules: any[];
  openNewRunModal: () => void;
  handleGenerateRun: (payload: { schedule_id: number; start_date: string; end_date: string; pay_date: string }) => Promise<void>;
  handleUpdateRunStatus: (runId: number, status: string) => Promise<void>;
  runDetails: any;
  setRunDetails: (d: any) => void;
  handleViewRunDetails: (runId: number) => Promise<void>;
  isRunActionBusy: boolean;
  refreshQueue: () => Promise<void>;
  API: typeof API;
}

const PayrollContext = createContext<PayrollContextType | undefined>(undefined);

export function PayrollProvider({ children }: { children: React.ReactNode }) {
  const { hasPermission } = useAuth();
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

  // Timesheets States
  const [timesheets, setTimesheets] = useState<any[]>([]);
  const [employees, setEmployees] = useState<any[]>([]);
  const [tsStart, setTsStart] = useState(() => {
    const d = new Date();
    return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0];
  });
  const [tsEnd, setTsEnd] = useState(() => {
    const d = new Date();
    return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split('T')[0];
  });
  const [tsEmpId, setTsEmpId] = useState('');
  const [tsStatus, setTsStatus] = useState('');
  const [editingTsId, setEditingTsId] = useState<number | null>(null);
  const [editingTsData, setEditingTsData] = useState<any>({});
  const [selectedTsIds, setSelectedTsIds] = useState<number[]>([]);
  const [isTsLoading, setIsTsLoading] = useState(false);
  const [isGenerating, setIsGenerating] = useState(false);
  const [holidays, setHolidays] = useState<any[]>([]);
  const [showHolidays, setShowHolidays] = useState(false);
  const [isHolidaysLoading, setIsHolidaysLoading] = useState(false);
  const [newHoliday, setNewHoliday] = useState({ holiday_date: '', name: '', type: 'Regular Holiday' });

  const fetchTimesheets = async () => {
    setIsTsLoading(true);
    try {
      const url = `/api/index.php?route=timesheets&action=list&start_date=${tsStart}&end_date=${tsEnd}&employee_id=${tsEmpId}&status=${tsStatus}`;
      const res = await apiFetch(url);
      if (res.ok) {
        const json = await res.json();
        if (json.success) {
          setTimesheets(json.timesheets || []);
        }
      }
    } catch (err) {
      console.error("Error fetching timesheets:", err);
    } finally {
      setIsTsLoading(false);
    }
  };

  const fetchEmployees = async () => {
    try {
      const res = await apiFetch('/api/index.php?route=iam&action=users');
      if (res.ok) {
        const json = await res.json();
        if (json.success && json.data) {
          setEmployees(json.data);
        }
      }
    } catch (err) {
      console.error("Error fetching employees:", err);
    }
  };

  useEffect(() => {
    if (activeTab === 'timesheets') {
      fetchTimesheets();
    }
  }, [activeTab, tsStart, tsEnd, tsEmpId, tsStatus]);

  useEffect(() => {
    if (activeTab === 'timesheets' && employees.length === 0) {
      fetchEmployees();
    }
  }, [activeTab]);

  const timesheetTourSteps = [
    {
      element: '#tour-ts-header',
      popover: {
        title: 'Timesheets Checkpoint',
        description: 'This is where daily work hours are reviewed and approved before payroll runs. Nothing gets paid until it is approved here.',
        side: 'bottom',
        align: 'start',
      },
    },
    {
      element: '#tour-ts-filters',
      popover: {
        title: 'Filter the view',
        description: 'Narrow down by date range, a specific employee, or status (Pending / Approved / Rejected) to find the rows you need.',
        side: 'bottom',
        align: 'start',
      },
    },
    {
      element: '#tour-ts-approve',
      popover: {
        title: 'Approve hours',
        description: 'Tick the rows you have checked, then approve them. Only Approved hours are paid — Pending and Rejected rows are ignored by payroll.',
        side: 'left',
        align: 'start',
      },
    },
    {
      element: '#tour-ts-table',
      popover: {
        title: 'The daily grid',
        description: 'Each row is one employee for one day. You can edit hours inline, then approve. This is your audit trail for every payroll run.',
        side: 'top',
        align: 'start',
      },
    },
  ];

  const { startTour: startTimesheetTour } = useTour('payroll', timesheetTourSteps, {
    enabled: activeTab === 'timesheets',
  });

  const handleSaveTsRow = async (row: any) => {
    try {
      const res = await apiFetch('/api/index.php?route=timesheets&action=save', {
        method: 'POST',
        body: JSON.stringify(row)
      });
      if (res.ok) {
        const json = await res.json();
        if (json.success) {
          setEditingTsId(null);
          fetchTimesheets();
        } else {
          alert(json.error || "Failed to save timesheet");
        }
      }
    } catch (err) {
      console.error("Error saving timesheet:", err);
    }
  };

  const handleSetTsStatus = async (status: 'Approved' | 'Rejected', ids?: number[]) => {
    const targetIds = ids || selectedTsIds;
    if (targetIds.length === 0) {
      alert("No timesheets selected.");
      return;
    }
    try {
      const action = status === 'Approved' ? 'approve' : 'reject';
      const res = await apiFetch(`/api/index.php?route=timesheets&action=${action}`, {
        method: 'POST',
        body: JSON.stringify({ ids: targetIds })
      });
      if (res.ok) {
        const json = await res.json();
        if (json.success) {
          setSelectedTsIds([]);
          fetchTimesheets();
        } else {
          alert(json.error || `Failed to ${action} timesheets`);
        }
      }
    } catch (err) {
      console.error(`Error performing ${status} action:`, err);
    }
  };

  const handleSetTsStatusPeriod = async (status: 'Approved' | 'Rejected') => {
    if (!tsEmpId) {
      alert("Please select an employee filter first to perform period-based approval/rejection.");
      return;
    }
    if (!confirm(`Are you sure you want to ${status.toLowerCase()} all timesheets in the selected date range for this employee?`)) {
      return;
    }
    try {
      const action = status === 'Approved' ? 'approve' : 'reject';
      const res = await apiFetch(`/api/index.php?route=timesheets&action=${action}`, {
        method: 'POST',
        body: JSON.stringify({
          employee_id: tsEmpId,
          start_date: tsStart,
          end_date: tsEnd
        })
      });
      if (res.ok) {
        const json = await res.json();
        if (json.success) {
          fetchTimesheets();
        } else {
          alert(json.error || `Failed to ${action} timesheets for period`);
        }
      }
    } catch (err) {
      console.error(`Error performing ${status} action for period:`, err);
    }
  };

  const handleDeleteTsRow = async (id: number) => {
    if (!confirm("Are you sure you want to delete this timesheet entry?")) {
      return;
    }
    try {
      const res = await apiFetch('/api/index.php?route=timesheets&action=delete', {
        method: 'POST',
        body: JSON.stringify({ id })
      });
      if (res.ok) {
        const json = await res.json();
        if (json.success) {
          fetchTimesheets();
        } else {
          alert(json.error || "Failed to delete timesheet");
        }
      }
    } catch (err) {
      console.error("Error deleting timesheet:", err);
    }
  };

  const handleGenerateDraft = async () => {
    if (!confirm("Generate draft timesheets from attendance punches for the selected date range? Existing Approved days are never overwritten.")) {
      return;
    }
    setIsGenerating(true);
    try {
      const res = await apiFetch('/api/index.php?route=timesheets&action=generate_draft', {
        method: 'POST',
        body: JSON.stringify({
          start_date: tsStart,
          end_date: tsEnd,
          employee_id: tsEmpId || undefined
        })
      });
      if (res.ok) {
        const json = await res.json();
        if (json.success) {
          fetchTimesheets();
          alert(`Drafted ${json.drafted} day(s). Skipped ${json.skipped_approved} already-approved.\n\n${json.note || ''}`);
        } else {
          alert(json.error || "Failed to generate drafts.");
        }
      } else {
        alert("Server returned error response.");
      }
    } catch (err) {
      console.error("Error generating drafts:", err);
      alert("Error generating drafts.");
    } finally {
      setIsGenerating(false);
    }
  };

  const fetchHolidays = async () => {
    setIsHolidaysLoading(true);
    try {
      const url = `/api/index.php?route=timesheets&action=holidays&start_date=${tsStart}&end_date=${tsEnd}`;
      const res = await apiFetch(url);
      if (res.ok) {
        const json = await res.json();
        if (json.success) {
          setHolidays(json.holidays || []);
        }
      }
    } catch (err) {
      console.error("Error fetching holidays:", err);
    } finally {
      setIsHolidaysLoading(false);
    }
  };

  const handleSaveHoliday = async (e: any) => {
    e.preventDefault();
    if (!newHoliday.holiday_date || !newHoliday.name) {
      alert("Please fill in all holiday fields.");
      return;
    }
    try {
      const res = await apiFetch('/api/index.php?route=timesheets&action=save_holiday', {
        method: 'POST',
        body: JSON.stringify(newHoliday)
      });
      if (res.ok) {
        const json = await res.json();
        if (json.success) {
          setNewHoliday({ holiday_date: '', name: '', type: 'Regular Holiday' });
          fetchHolidays();
        } else {
          alert(json.error || "Failed to save holiday.");
        }
      }
    } catch (err) {
      console.error("Error saving holiday:", err);
    }
  };

  const handleDeleteHoliday = async (id: number) => {
    if (!confirm("Are you sure you want to delete this holiday?")) {
      return;
    }
    try {
      const res = await apiFetch('/api/index.php?route=timesheets&action=delete_holiday', {
        method: 'POST',
        body: JSON.stringify({ id })
      });
      if (res.ok) {
        const json = await res.json();
        if (json.success) {
          fetchHolidays();
        } else {
          alert(json.error || "Failed to delete holiday.");
        }
      }
    } catch (err) {
      console.error("Error deleting holiday:", err);
    }
  };

  useEffect(() => {
    if (activeTab === 'timesheets' && showHolidays) {
      fetchHolidays();
    }
  }, [activeTab, showHolidays, tsStart, tsEnd]);

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

  useEffect(() => {
    const applyTheme = (theme: string) => {
      document.documentElement.setAttribute('data-theme', theme);
      try { localStorage.setItem('theme', theme); } catch(e) {}
    };

    let themeChannel: BroadcastChannel | null = null;
    try {
      themeChannel = new BroadcastChannel('respawn_theme');
      themeChannel.onmessage = (e: MessageEvent) => {
        if (e.data?.theme) applyTheme(e.data.theme);
      };
    } catch(e) {}

    const handleStorageChange = (e: StorageEvent) => {
      if (e.key === 'theme' && e.newValue) applyTheme(e.newValue);
    };
    window.addEventListener('storage', handleStorageChange);

    return () => {
      themeChannel?.close();
      window.removeEventListener('storage', handleStorageChange);
    };
  }, []);

  useEffect(() => {
    if (dashInfo) {
      setProcessedEmployees(dashInfo.activeRunProcessed);
      setProgress(Math.floor((dashInfo.activeRunProcessed / dashInfo.activeRunTotalEmployees) * 100));

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

  // ── Run lifecycle ────────────────────────────────────────────────
  const [showNewRunModal, setShowNewRunModal] = useState(false);
  const [schedules, setSchedules] = useState<any[]>([]);
  const [runDetails, setRunDetails] = useState<any>(null);
  const [isRunActionBusy, setIsRunActionBusy] = useState(false);

  const refreshQueue = async () => {
    const q = await API.fetchQueue();
    setQueue(q);
  };

  const openNewRunModal = () => {
    setShowNewRunModal(true);
    if (schedules.length === 0) {
      API.fetchSchedules().then(setSchedules).catch(() => setSchedules([]));
    }
  };

  const handleGenerateRun = async (payload: { schedule_id: number; start_date: string; end_date: string; pay_date: string }) => {
    setIsRunActionBusy(true);
    try {
      const res = await API.generateRun(payload);
      if (res.success) {
        setShowNewRunModal(false);
        await refreshQueue();
        API.fetchDashboardInfo().then(setDashInfo);
        API.fetchExceptions().then(setExceptions);
        alert(`Payroll run #${res.run_id ?? ''} generated successfully.`);
      } else {
        // Fail-loud: surface the engine's exact error (e.g. "no approved timesheets").
        alert(res.error || 'Failed to generate payroll run.');
      }
    } catch (err) {
      console.error('Error generating run:', err);
      alert('Error generating payroll run.');
    } finally {
      setIsRunActionBusy(false);
    }
  };

  const handleUpdateRunStatus = async (runId: number, status: string) => {
    if (!confirm(`Set this payroll run to "${status}"?`)) return;
    setIsRunActionBusy(true);
    try {
      const res = await API.updateRunStatus(runId, status);
      if (res.success) {
        setRunDetails(null);
        await refreshQueue();
      } else {
        alert(res.error || 'Failed to update run status.');
      }
    } catch (err) {
      console.error('Error updating run status:', err);
      alert('Error updating run status.');
    } finally {
      setIsRunActionBusy(false);
    }
  };

  const handleViewRunDetails = async (runId: number) => {
    try {
      const d = await API.fetchRunDetails(runId);
      if (d) setRunDetails(d);
      else alert('Could not load run details.');
    } catch (err) {
      console.error('Error fetching run details:', err);
    }
  };

  const handleViewPayslip = async (id: string) => {
    try {
      const details = await API.fetchPayslipDetails(id);
      setSelectedPayslipDetails(details);
    } catch (err) {
      console.error("Failed to fetch payslip details:", err);
    }
  };

  const handleExport = () => {
    const basePath = window.location.pathname.replace('/frontend/dist/index.html', '');
    window.open(`${window.location.origin}${basePath}/api/index.php?route=export&action=payroll`, '_blank');
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
  };

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

  return (
    <PayrollContext.Provider value={{
      activeTab, setActiveTab, isLoading, dashInfo, chartData, exceptions, queue, compData,
      settings, setSettings, payComponents, setPayComponents, showComponentModal, setShowComponentModal,
      editingComponent, setEditingComponent, payslipsList, govReports, selectedPayslipDetails, setSelectedPayslipDetails,
      timesheets, employees, tsStart, setTsStart, tsEnd, setTsEnd, tsEmpId, setTsEmpId, tsStatus, setTsStatus,
      editingTsId, setEditingTsId, editingTsData, setEditingTsData, selectedTsIds, setSelectedTsIds,
      isTsLoading, isGenerating, holidays, showHolidays, setShowHolidays, isHolidaysLoading, newHoliday, setNewHoliday,
      progress, processedEmployees, fetchTimesheets, fetchEmployees, handleSaveTsRow, handleSetTsStatus,
      handleSetTsStatusPeriod, handleDeleteTsRow, handleGenerateDraft, fetchHolidays, handleSaveHoliday,
      handleDeleteHoliday, handleExport, handleViewPayslip, formatCurrency, startTimesheetTour, hasPermission,
      handleSaveSettings, handleSaveComponent, handleDeleteComponent,
      showNewRunModal, setShowNewRunModal, schedules, openNewRunModal, handleGenerateRun,
      handleUpdateRunStatus, runDetails, setRunDetails, handleViewRunDetails, isRunActionBusy, refreshQueue, API
    }}>
      {children}
    </PayrollContext.Provider>
  );
}

export function usePayroll() {
  const context = useContext(PayrollContext);
  if (!context) {
    throw new Error('usePayroll must be used within a PayrollProvider');
  }
  return context;
}
