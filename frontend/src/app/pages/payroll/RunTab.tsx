import React, { useState } from 'react';
import {
  PlayCircle, Search, AlertCircle, AlertTriangle, Info, Eye, X,
  Printer, Download, History, ArrowUpRight, CalendarClock, Users, ShieldCheck, Banknote
} from 'lucide-react';
import { usePayroll } from './PayrollContext';

interface RunTabProps {
  view: 'queue' | 'exceptions' | 'payslips' | 'reports' | 'compensation';
}

/** Strip UI prefixes like PR-/PS- to get the numeric DB id. */
const rawId = (id: string | number) => parseInt(String(id).replace(/^\D+/, ''), 10);

const csvEscape = (v: any) => `"${String(v ?? '').replace(/"/g, '""')}"`;

export function RunTab({ view }: RunTabProps) {
  const {
    queue,
    exceptions,
    setActiveTab,
    payslipsList,
    selectedPayslipDetails,
    setSelectedPayslipDetails,
    handleViewPayslip,
    govReports,
    compData,
    formatCurrency,
    showNewRunModal,
    setShowNewRunModal,
    schedules,
    openNewRunModal,
    handleGenerateRun,
    handleUpdateRunStatus,
    runDetails,
    setRunDetails,
    handleViewRunDetails,
    isRunActionBusy,
  } = usePayroll();

  // Queue: client-side search. Payslips: period filter.
  const [queueSearch, setQueueSearch] = useState('');
  const [payslipPeriod, setPayslipPeriod] = useState('');

  // New-run form state
  const [runForm, setRunForm] = useState({ schedule_id: '', start_date: '', end_date: '', pay_date: '' });

  const submitNewRun = (e: React.FormEvent) => {
    e.preventDefault();
    if (!runForm.schedule_id || !runForm.start_date || !runForm.end_date || !runForm.pay_date) {
      alert('Please fill in schedule, period start/end, and pay date.');
      return;
    }
    handleGenerateRun({
      schedule_id: parseInt(runForm.schedule_id, 10),
      start_date: runForm.start_date,
      end_date: runForm.end_date,
      pay_date: runForm.pay_date,
    });
  };

  const exportExceptionsCsv = () => {
    const rows = [['Severity', 'Type', 'Employee', 'Description'],
      ...exceptions.map((e: any) => [e.severity, e.type, e.empName, e.desc])];
    const csv = rows.map(r => r.map(csvEscape).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    const a = document.createElement('a');
    a.href = url; a.download = 'payroll_exceptions.csv'; a.click();
    URL.revokeObjectURL(url);
  };

  /** Route the user to the tab where this exception is actually fixed. */
  const fixException = (exc: any) => {
    const t = `${exc.type ?? ''} ${exc.desc ?? ''}`.toLowerCase();
    if (t.includes('timesheet') || t.includes('attendance') || t.includes('hours')) setActiveTab('timesheets');
    else if (t.includes('salary') || t.includes('compensation') || t.includes('rate')) setActiveTab('compensation');
    else setActiveTab('settings');
  };

  const downloadPayslipPdf = (id: string) => {
    const basePath = window.location.pathname.replace('/frontend/dist/index.html', '');
    window.open(`${window.location.origin}${basePath}/api/index.php?route=payroll_engine&action=download_payslip&id=${rawId(id)}`, '_blank');
  };

  if (view === 'queue') {
    const q = queueSearch.trim().toLowerCase();
    const visibleQueue = q
      ? queue.filter((r: any) => `${r.id} ${r.origin} ${r.period} ${r.status}`.toLowerCase().includes(q))
      : queue;

    return (
      <div className="dashboard-content animate-slide-up">
        <div className="flex justify-between items-center mb-6">
          <div>
            <h2 className="text-2xl font-bold">Payroll Queue</h2>
            <p className="text-muted mt-1">Manage and track your active and pending payroll runs.</p>
          </div>
          <button className="btn btn-primary" onClick={openNewRunModal}><PlayCircle size={18} /> Run New Payroll</button>
        </div>

        <div className="card p-0 overflow-hidden">
          <div className="p-4 border-b border-border-light flex justify-between">
            <div className="flex gap-2">
              <div className="search-bar">
                <Search size={16} className="text-muted" />
                <input
                  type="text"
                  placeholder="Search runs..."
                  value={queueSearch}
                  onChange={(e) => setQueueSearch(e.target.value)}
                  className="bg-transparent border-none text-foreground outline-none"
                />
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
              {visibleQueue.map((run) => (
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
                    <button
                      className="btn btn-secondary text-xs"
                      onClick={() => handleViewRunDetails(rawId(run.id))}
                    >
                      <Eye size={14} /> View
                    </button>
                  </td>
                </tr>
              ))}
              {visibleQueue.length === 0 && (
                <tr><td colSpan={7} className="p-8 text-center text-muted">No payroll runs{q ? ' match your search' : ' yet — start one with "Run New Payroll"'}.</td></tr>
              )}
            </tbody>
          </table>
        </div>

        {/* ── New Run modal ── */}
        {showNewRunModal && (
          <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onClick={() => setShowNewRunModal(false)}>
            <form
              onSubmit={submitNewRun}
              onClick={(e) => e.stopPropagation()}
              className="card w-full max-w-md bg-card border border-border-color"
            >
              <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-bold">Run New Payroll</h3>
                <button type="button" className="icon-btn" onClick={() => setShowNewRunModal(false)}><X size={18} /></button>
              </div>

              <label className="block text-sm text-tertiary mb-1">Payroll Schedule</label>
              <select
                value={runForm.schedule_id}
                onChange={(e) => setRunForm({ ...runForm, schedule_id: e.target.value })}
                className="w-full mb-3 p-2 rounded-md bg-bg-item border border-border-color text-foreground"
              >
                <option value="">Select a schedule…</option>
                {schedules.map((s: any) => (
                  <option key={s.id} value={s.id}>{s.name} ({s.frequency})</option>
                ))}
              </select>
              {schedules.length === 0 && (
                <p className="text-xs text-amber-400 mb-3">No payroll schedules found — create one in Tenant Settings first.</p>
              )}

              <div className="grid grid-cols-2 gap-3 mb-3">
                <div>
                  <label className="block text-sm text-tertiary mb-1">Period Start</label>
                  <input type="date" value={runForm.start_date} onChange={(e) => setRunForm({ ...runForm, start_date: e.target.value })}
                    className="w-full p-2 rounded-md bg-bg-item border border-border-color text-foreground" />
                </div>
                <div>
                  <label className="block text-sm text-tertiary mb-1">Period End</label>
                  <input type="date" value={runForm.end_date} onChange={(e) => setRunForm({ ...runForm, end_date: e.target.value })}
                    className="w-full p-2 rounded-md bg-bg-item border border-border-color text-foreground" />
                </div>
              </div>
              <label className="block text-sm text-tertiary mb-1">Pay Date</label>
              <input type="date" value={runForm.pay_date} onChange={(e) => setRunForm({ ...runForm, pay_date: e.target.value })}
                className="w-full mb-4 p-2 rounded-md bg-bg-item border border-border-color text-foreground" />

              <p className="text-xs text-muted mb-4">Only <strong>Approved</strong> timesheets inside the period are paid. The run fails loudly if timesheets or statutory tables are missing — nothing is silently computed.</p>

              <div className="flex justify-end gap-2">
                <button type="button" className="btn btn-secondary" onClick={() => setShowNewRunModal(false)}>Cancel</button>
                <button type="submit" className="btn btn-primary" disabled={isRunActionBusy}>
                  {isRunActionBusy ? 'Generating…' : 'Generate Run'}
                </button>
              </div>
            </form>
          </div>
        )}

        {/* ── Run details modal ── */}
        {runDetails && (
          <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onClick={() => setRunDetails(null)}>
            <div className="card w-full max-w-3xl max-h-[85vh] overflow-y-auto bg-card border border-border-color" onClick={(e) => e.stopPropagation()}>
              <div className="flex justify-between items-center mb-4">
                <div>
                  <h3 className="text-lg font-bold">Run #{runDetails.run?.id} — {runDetails.run?.status}</h3>
                  <p className="text-sm text-muted">{runDetails.run?.payroll_period_start} to {runDetails.run?.payroll_period_end} · Pay date {runDetails.run?.pay_date}</p>
                </div>
                <button className="icon-btn" onClick={() => setRunDetails(null)}><X size={18} /></button>
              </div>

              <table className="data-table w-full text-left border-collapse mb-4">
                <thead>
                  <tr>
                    <th className="p-3 text-tertiary text-sm">Employee</th>
                    <th className="p-3 text-tertiary text-sm text-right">Gross</th>
                    <th className="p-3 text-tertiary text-sm text-right">Deductions</th>
                    <th className="p-3 text-tertiary text-sm text-right">Net Pay</th>
                  </tr>
                </thead>
                <tbody>
                  {(runDetails.employees || []).map((emp: any) => (
                    <tr key={emp.id} className="border-t border-border-light">
                      <td className="p-3">{emp.full_name} <span className="text-muted text-xs">({emp.employee_number || emp.department || '—'})</span></td>
                      <td className="p-3 text-right">{formatCurrency(parseFloat(emp.gross_pay ?? 0))}</td>
                      <td className="p-3 text-right text-red-400">- {formatCurrency(parseFloat(emp.total_deductions ?? 0))}</td>
                      <td className="p-3 text-right font-bold">{formatCurrency(parseFloat(emp.net_pay ?? 0))}</td>
                    </tr>
                  ))}
                </tbody>
              </table>

              {/* Status transitions — backend enforces the approver role; a 403 surfaces as an alert. */}
              <div className="flex justify-end gap-2">
                {runDetails.run?.status === 'Draft' && (
                  <>
                    <button className="btn btn-secondary" disabled={isRunActionBusy}
                      onClick={() => handleUpdateRunStatus(runDetails.run.id, 'Rejected')}>Reject</button>
                    <button className="btn btn-primary" disabled={isRunActionBusy}
                      onClick={() => handleUpdateRunStatus(runDetails.run.id, 'Approved')}>Approve</button>
                  </>
                )}
                {runDetails.run?.status === 'Approved' && (
                  <button className="btn btn-primary" disabled={isRunActionBusy}
                    onClick={() => handleUpdateRunStatus(runDetails.run.id, 'Processed')}>Mark Processed (generates payslips)</button>
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    );
  }

  if (view === 'exceptions') {
    return (
      <div className="dashboard-content animate-slide-up">
        <div className="flex justify-between items-center mb-6">
          <div>
            <h2 className="text-2xl font-bold text-red-100">Exceptions Center</h2>
            <p className="text-muted mt-1">Resolve data anomalies before processing payroll.</p>
          </div>
          <div className="flex gap-2">
            <button className="btn btn-secondary" onClick={exportExceptionsCsv}><Download size={16}/> Export Log</button>
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
                    {/* Navigates to the tab where this class of exception is corrected. */}
                    <button className="btn btn-secondary text-xs" onClick={() => fixException(exc)}>Fix Now</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  if (view === 'payslips') {
    return (
      <div className="dashboard-content animate-slide-up">
        {!selectedPayslipDetails ? (
          <>
            <div className="flex justify-between items-center mb-6">
              <div>
                <h2 className="text-2xl font-bold">Payslips</h2>
                <p className="text-muted mt-1">Review and distribute employee payslips.</p>
              </div>
              <div className="flex gap-2">
                <select
                  value={payslipPeriod}
                  onChange={(e) => setPayslipPeriod(e.target.value)}
                  className="p-2 rounded-md bg-bg-item border border-border-color text-foreground text-sm"
                >
                  <option value="">All periods</option>
                  {[...new Set(payslipsList.map((p: any) => p.period))].map((per: any) => (
                    <option key={per} value={per}>{per}</option>
                  ))}
                </select>
                <button className="btn btn-primary" onClick={() => window.print()}><Printer size={18} /> Print List</button>
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
                  {payslipsList.filter((ps: any) => !payslipPeriod || ps.period === payslipPeriod).map((ps) => (
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
          <div className="flex flex-col items-center">
            <div className="w-full max-w-3xl flex justify-between items-center mb-4">
               <button className="btn btn-secondary" onClick={() => setSelectedPayslipDetails(null)}>Back to List</button>
               <div className="flex gap-2">
                  <button className="btn btn-secondary" onClick={() => downloadPayslipPdf(selectedPayslipDetails.id)}><Download size={18}/> Download PDF</button>
                  <button className="btn btn-primary" onClick={() => window.print()}><Printer size={18}/> Print</button>
               </div>
            </div>
            
            <div className="card w-full max-w-3xl bg-card text-slate-900">
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

              <div className="bg-slate-100 p-6 rounded-lg flex justify-between items-center border border-slate-200">
                <span className="text-xl font-bold text-slate-700">NET PAY</span>
                <span className="text-3xl font-bold text-emerald-600">{formatCurrency(selectedPayslipDetails.netPay)}</span>
              </div>
            </div>
          </div>
        )}
      </div>
    );
  }

  if (view === 'reports') {
    return (
      <div className="dashboard-content animate-slide-up">
        <div className="flex justify-between items-center mb-6">
          <div>
            <h2 className="text-2xl font-bold">Tax & Government Reports</h2>
            <p className="text-muted mt-1">Generate compliant reports for SSS, PhilHealth, Pag-IBIG, and BIR.</p>
          </div>
          {/* Reports are derived from Processed runs — there is no separate "generate" endpoint yet.
              A dead primary button here read as broken, so it was removed until the backend exists. */}
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
                    {/* Agency e-file formats (SSS R-3 DAT, BIR Alphalist) are not implemented server-side yet. */}
                    <button className="btn btn-secondary text-xs opacity-50 cursor-not-allowed" disabled title="XML/DAT export is not available yet — agency e-file formats are on the roadmap.">
                      <Download size={14}/> Download XML/DAT
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  if (view === 'compensation') {
    if (!compData) return null;
    const dailyRate = compData.currentBase / 21.8;
    const hourlyRate = dailyRate / 8;

    return (
      <div className="dashboard-content animate-slide-up">
        <div className="flex justify-between items-center mb-6">
          <div>
            <h2 className="text-2xl font-bold">Employee Compensation</h2>
            <p className="text-muted mt-1">Viewing salary history for: <strong className="text-foreground">{compData.employeeName} ({compData.employeeId})</strong></p>
          </div>
          {/* "New Compensation Record" removed: comp_history is read-only server-side.
              Salary changes flow through Core HR / employee profile, not this view. */}
        </div>

        <div className="dashboard-bottom-grid">
          <div className="card col-span-2 relative">
            <h3 className="mb-4 flex items-center gap-2"><History size={20} className="text-blue-500"/> Compensation History</h3>
            
            <div className="relative pl-6 border-l-2 border-border-light space-y-6 mt-6 ml-2">
              {[...compData.history].reverse().map((comp: any, idx, arr) => {
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
                          comp.status === 'Future' ? 'badge-amber' : 'bg-slate-800 text-muted-foreground'
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
                  <span className="text-foreground">{formatCurrency(dailyRate)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-tertiary">Hourly Rate:</span>
                  <span className="text-foreground">{formatCurrency(hourlyRate)}</span>
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
              <button className="btn btn-secondary w-full mt-4 text-xs" onClick={() => { window.location.hash = '#/audit'; }}>View Full Audit Trail</button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return null;
}
