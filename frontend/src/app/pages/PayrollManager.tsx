import React from 'react';
import { PlayCircle } from 'lucide-react';
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
    openNewRunModal
  } = usePayroll();

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-app">
        <div className="pulse-indicator w-8 h-8"></div>
      </div>
    );
  }

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-input border-border relative z-0">
      {/* Global Background Glow Effects */}
      <div style={{ position: "absolute", top: -100, left: -100, width: 500, height: 500, borderRadius: "50%", background: "#00e07a", filter: "blur(120px)", opacity: 0.06, pointerEvents: "none", zIndex: -1 }} />
      <div style={{ position: "absolute", bottom: -150, right: -100, width: 600, height: 600, borderRadius: "50%", background: "#9b6dff", filter: "blur(140px)", opacity: 0.05, pointerEvents: "none", zIndex: -1 }} />
      
      {/* Main Content */}
      <div className="flex-1 flex flex-col h-full overflow-hidden">
        {/* Module-specific top bar */}
        <header className="flex-none px-8 py-4 border-b border-border bg-card text-card-foreground/50 backdrop-blur-md flex items-center justify-between">
          <div className="flex items-center gap-4 w-full justify-between">
            <div className="flex bg-input border-border rounded-lg p-1 border border-border">
              <button 
                className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'dashboard' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-muted-foreground hover:text-foreground'}`} 
                onClick={() => setActiveTab('dashboard')}
              >
                Dashboard
              </button>
              <button 
                className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'queue' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-muted-foreground hover:text-foreground'}`} 
                onClick={() => setActiveTab('queue')}
              >
                Queue
              </button>
              <button 
                className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'exceptions' ? 'bg-red-500/20 text-red-500' : 'text-muted-foreground hover:text-foreground'}`} 
                onClick={() => setActiveTab('exceptions')}
              >
                Exceptions ({exceptions.filter(e => e.severity === 'Critical').length})
              </button>
              <button 
                className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'compensation' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-muted-foreground hover:text-foreground'}`} 
                onClick={() => setActiveTab('compensation')}
              >
                Compensation
              </button>
              <button 
                className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'payslips' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-muted-foreground hover:text-foreground'}`} 
                onClick={() => setActiveTab('payslips')}
              >
                Payslips
              </button>
              <button 
                className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'timesheets' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-muted-foreground hover:text-foreground'}`} 
                onClick={() => setActiveTab('timesheets')}
              >
                Timesheets
              </button>
              <button 
                className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'govreports' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-muted-foreground hover:text-foreground'}`} 
                onClick={() => setActiveTab('govreports')}
              >
                Reports
              </button>
              <button 
                className={`px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${activeTab === 'settings' ? 'bg-[#00e07a]/20 text-[#00e07a]' : 'text-muted-foreground hover:text-foreground'}`} 
                onClick={() => setActiveTab('settings')}
              >
                Settings
              </button>
            </div>
            
            <div className="flex gap-3">
              {hasPermission("payroll.view") && (
                <button 
                  onClick={handleExport}
                  className="px-4 py-2 bg-card/50 hover:bg-accent border border-border text-foreground rounded-lg text-sm font-semibold transition-all flex items-center gap-2 cursor-pointer"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  Export CSV
                </button>
              )}
              <button
                onClick={() => { setActiveTab('queue'); openNewRunModal(); }}
                className="px-4 py-2 bg-[#00e07a] text-black font-bold rounded-lg text-sm shadow-[0_0_10px_rgba(0,224,122,0.3)] flex items-center gap-2 cursor-pointer"
              >
                <PlayCircle size={16} /> New Run
              </button>
            </div>
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
