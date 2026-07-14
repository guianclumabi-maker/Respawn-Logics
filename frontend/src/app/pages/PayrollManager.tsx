import React from 'react';
import {
  PlayCircle, Download, LayoutDashboard, ListChecks, AlertTriangle,
  Wallet, Receipt, Clock, FileText, Settings as SettingsIcon,
} from 'lucide-react';
import { PayrollProvider, usePayroll } from './payroll/PayrollContext';
import { DashboardTab } from './payroll/DashboardTab';
import { TimesheetsTab } from './payroll/TimesheetsTab';
import { RunTab } from './payroll/RunTab';
import { SettingsTab } from './payroll/SettingsTab';
import './PayrollManager.css';

function PayrollManagerInner() {
  const {
    activeTab,
    setActiveTab,
    isLoading,
    exceptions,
    handleExport,
    hasPermission,
  } = usePayroll();

  const criticalCount = exceptions.filter((e) => e.severity === 'Critical').length;

  // Tab definitions — same keys/order as before, now with icons + friendly labels.
  const tabs = [
    { key: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { key: 'queue', label: 'Payroll Runs', icon: ListChecks },
    { key: 'exceptions', label: 'Exceptions', icon: AlertTriangle, danger: true, badge: criticalCount },
    { key: 'compensation', label: 'Compensation', icon: Wallet },
    { key: 'payslips', label: 'Payslips', icon: Receipt },
    { key: 'timesheets', label: 'Timesheets', icon: Clock },
    { key: 'govreports', label: 'Gov Reports', icon: FileText },
    { key: 'settings', label: 'Settings', icon: SettingsIcon },
  ] as const;

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center h-screen bg-app gap-3">
        <div className="pulse-indicator w-8 h-8"></div>
        <p className="text-sm text-muted-foreground">Loading payroll…</p>
      </div>
    );
  }

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-input border-border relative z-0">
      {/* Global Background Glow Effects */}
      <div style={{ position: 'absolute', top: -100, left: -100, width: 500, height: 500, borderRadius: '50%', background: '#00e07a', filter: 'blur(120px)', opacity: 0.06, pointerEvents: 'none', zIndex: -1 }} />
      <div style={{ position: 'absolute', bottom: -150, right: -100, width: 600, height: 600, borderRadius: '50%', background: '#9b6dff', filter: 'blur(140px)', opacity: 0.05, pointerEvents: 'none', zIndex: -1 }} />

      {/* Main Content */}
      <div className="flex-1 flex flex-col h-full overflow-hidden">
        <header className="flex-none border-b border-border bg-card/60 backdrop-blur-md">
          {/* Title row */}
          <div className="px-8 pt-5 pb-4 flex items-start justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-[#00e07a]/15 text-[#00e07a] flex items-center justify-center flex-shrink-0">
                <Wallet size={20} />
              </div>
              <div>
                <h1 className="text-xl font-bold text-foreground leading-tight">Payroll</h1>
                <p className="text-sm text-muted-foreground">Run payroll, review exceptions, and manage compensation.</p>
              </div>
            </div>

            <div className="flex items-center gap-2 flex-shrink-0">
              {hasPermission('payroll.view') && (
                <button
                  onClick={handleExport}
                  className="px-3.5 py-2 bg-card hover:bg-accent border border-border text-foreground rounded-lg text-sm font-medium transition-colors flex items-center gap-2 cursor-pointer"
                >
                  <Download size={16} /> Export CSV
                </button>
              )}
              <button
                onClick={() => setActiveTab('queue')}
                className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96e] text-black font-semibold rounded-lg text-sm shadow-[0_0_16px_rgba(0,224,122,0.25)] transition-colors flex items-center gap-2 cursor-pointer"
              >
                <PlayCircle size={16} /> New Run
              </button>
            </div>
          </div>

          {/* Tab bar — icon + label, scrollable on narrow screens */}
          <div className="px-6 flex items-center gap-1 overflow-x-auto no-scrollbar">
            {tabs.map(({ key, label, icon: Icon, danger, badge }) => {
              const active = activeTab === key;
              const activeCls = danger ? 'text-red-500 border-red-500' : 'text-[#00e07a] border-[#00e07a]';
              return (
                <button
                  key={key}
                  onClick={() => setActiveTab(key)}
                  className={`flex items-center gap-2 px-3.5 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors -mb-px ${
                    active ? activeCls : 'text-muted-foreground border-transparent hover:text-foreground'
                  }`}
                >
                  <Icon size={16} />
                  {label}
                  {typeof badge === 'number' && badge > 0 && (
                    <span className="ml-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500/20 text-red-500 text-[11px] font-bold flex items-center justify-center">
                      {badge}
                    </span>
                  )}
                </button>
              );
            })}
          </div>
        </header>

        <div className="flex-1 overflow-auto p-8 font-sans">
          {activeTab === 'dashboard' && <DashboardTab />}
          {activeTab === 'queue' && <RunTab view="queue" />}
          {activeTab === 'exceptions' && <RunTab view="exceptions" />}
          {activeTab === 'compensation' && <RunTab view="compensation" />}
          {activeTab === 'settings' && <SettingsTab />}
          {activeTab === 'payslips' && <RunTab view="payslips" />}
          {activeTab === 'timesheets' && <TimesheetsTab />}
          {activeTab === 'govreports' && <RunTab view="reports" />}
        </div>
      </div>
    </div>
  );
}

export function PayrollManager() {
  return (
    <PayrollProvider>
      <PayrollManagerInner />
    </PayrollProvider>
  );
}
