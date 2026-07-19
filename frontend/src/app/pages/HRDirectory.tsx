import { useState, useEffect } from "react";
import { useAuth } from "../context/AuthContext";
import { AlertTriangle, AlertCircle, X, ShieldAlert } from "lucide-react";
import { apiFetch } from "../lib/apiClient";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=core_hr`;

interface Employee {
  id: number;
  full_name: string;
  email: string;
  employment_status: string;
  department: string;
  job_title: string;
  created_at: string;
}

interface SuspensionRecord {
  id: number;
  employee_id: number;
  reason: string;
  start_date: string;
  end_date: string | null;
  status: string;
  source: string;
  elr_case_id: number | null;
  actor_name: string;
  created_at: string;
  reinstated_at: string | null;
}

export function HRDirectory() {
  const { hasPermission } = useAuth();
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);

  const [overdue, setOverdue] = useState<any[]>([]);
  const [selectedEmp, setSelectedEmp] = useState<Employee | null>(null);
  const [history, setHistory] = useState<SuspensionRecord[]>([]);

  const [suspendModalOpen, setSuspendModalOpen] = useState(false);
  const [suspendReason, setSuspendReason] = useState("");
  const [suspendEndDate, setSuspendEndDate] = useState("");
  const [suspendWarning, setSuspendWarning] = useState<string | null>(null);

  useEffect(() => {
    fetchDirectory();
    fetchOverdue();
  }, []);

  const fetchDirectory = async () => {
    try {
      setLoading(true);
      const res = await fetch(`${API}&action=directory`, { credentials: "include" });
      if (!res.ok) throw new Error("Failed to fetch");
      const data = await res.json();
      if (data.success && data.data) {
        setEmployees(data.data);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const fetchOverdue = async () => {
    try {
      const res = await fetch(`${API}&action=overdue_suspensions`, { credentials: "include" });
      const data = await res.json();
      if (data.success && data.data) {
        setOverdue(data.data);
      }
    } catch (e) {
      console.error(e);
    }
  };

  const fetchHistory = async (empId: number) => {
    try {
      const res = await apiFetch(`/api/index.php?route=core_hr&action=suspension_history`, {
        method: "POST",
        body: JSON.stringify({ employee_id: empId })
      });
      const data = await res.json();
      if (data.success && data.data) {
        setHistory(data.data);
      }
    } catch (e) {
      console.error(e);
    }
  };

  const openMaster = (emp: Employee) => {
    setSelectedEmp(emp);
    fetchHistory(emp.id);
  };

  const closeMaster = () => {
    setSelectedEmp(null);
    setHistory([]);
  };

  const handleSuspend = async () => {
    if (!selectedEmp) return;
    try {
      const res = await apiFetch(`/api/index.php?route=core_hr&action=suspend_employee`, {
        method: "POST",
        body: JSON.stringify({ 
          employee_id: selectedEmp.id,
          reason: suspendReason,
          end_date: suspendEndDate || null
        })
      });
      const data = await res.json();
      if (data.success) {
        if (data.warning) {
          setSuspendWarning(data.warning);
        } else {
          closeSuspendModal();
        }
        fetchDirectory();
        fetchHistory(selectedEmp.id);
        setSelectedEmp({...selectedEmp, employment_status: "Suspended"});
      } else {
        alert(data.error || "Failed to suspend");
      }
    } catch (e) {
      console.error(e);
    }
  };

  const handleReinstate = async () => {
    if (!selectedEmp) return;
    if (!window.confirm("Are you sure you want to reinstate this employee?")) return;
    try {
      const res = await apiFetch(`/api/index.php?route=core_hr&action=reinstate_employee`, {
        method: "POST",
        body: JSON.stringify({ employee_id: selectedEmp.id })
      });
      const data = await res.json();
      if (data.success) {
        fetchDirectory();
        fetchHistory(selectedEmp.id);
        fetchOverdue();
        setSelectedEmp({...selectedEmp, employment_status: "Active"});
      } else {
        alert(data.error || "Failed to reinstate");
      }
    } catch (e) {
      console.error(e);
    }
  };

  const closeSuspendModal = () => {
    setSuspendModalOpen(false);
    setSuspendReason("");
    setSuspendEndDate("");
    setSuspendWarning(null);
  };

  const filteredEmployees = employees.filter(emp => {
    const q = search.toLowerCase();
    return (
      (emp.full_name || "").toLowerCase().includes(q) ||
      (emp.email || "").toLowerCase().includes(q) ||
      (emp.department || "").toLowerCase().includes(q) ||
      (emp.job_title || "").toLowerCase().includes(q)
    );
  });

  const getStatusColor = (status: string) => {
    switch (status) {
      case "Active": return "bg-[#00e07a]/10 text-[#00e07a] border-[#00e07a]/20";
      case "Terminated": return "bg-red-500/10 text-red-500 border-red-500/20";
      case "Suspended": return "bg-red-500/10 text-red-500 border-red-500/20";
      case "LOA": return "bg-amber-500/10 text-amber-500 border-amber-500/20";
      case "Probation": return "bg-[#c084fc]/10 text-[#c084fc] border-[#c084fc]/20";
      default: return "bg-gray-500/10 text-muted-foreground border-gray-500/20";
    }
  };

  const getInitials = (name: string) => {
    if (!name) return "?";
    return name.split(" ").map(n => n[0]).join("").substring(0, 2).toUpperCase();
  };

  const handleExport = () => {
    const isLocal = window.location.hostname === "localhost";
    const basePath = isLocal ? "/respawn-logics" : "";
    window.open(`${window.location.origin}${basePath}/api/index.php?route=export&action=employees`, '_blank');
  };

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden relative">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-border bg-card text-card-foreground/50 backdrop-blur-md">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-foreground mb-1" style={{ fontFamily: "'Plus Jakarta Sans', sans-serif" }}>
              Employee Master Directory
            </h1>
            <p className="text-sm text-muted-foreground">Core HR &bull; Manage personnel records</p>
          </div>
          <div className="flex gap-3">
            {hasPermission("employees.view") && (
              <button 
                onClick={handleExport}
                className="px-4 py-2 bg-card/50 hover:bg-accent border border-border text-foreground rounded-lg text-sm font-medium transition-all flex items-center gap-2 cursor-pointer"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export CSV
              </button>
            )}
            <button className="px-4 py-2 bg-[#00e07a]/10 text-[#00e07a] border border-[#00e07a]/20 rounded-lg text-sm font-medium hover:bg-[#00e07a]/20 transition-all shadow-[0_0_15px_rgba(0,224,122,0.15)] flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
              Add Employee
            </button>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="flex-1 overflow-auto p-8 relative flex gap-6">
        
        <div className="flex-1 bg-card text-card-foreground/70 border border-border rounded-xl flex flex-col max-h-full">
          {/* Overdue Nudge */}
          {overdue.length > 0 && (
            <div className="bg-red-500/10 border-b border-red-500/20 p-4 flex items-center gap-3 text-red-500">
              <ShieldAlert size={20} />
              <div className="flex-1 text-sm font-medium">
                There {overdue.length === 1 ? 'is' : 'are'} {overdue.length} active suspension{overdue.length === 1 ? '' : 's'} past the set end date.
              </div>
              <button 
                onClick={() => alert(overdue.map(o => `${o.full_name} (${o.days_overdue} days overdue)`).join('\n'))}
                className="text-xs bg-red-500/20 px-3 py-1.5 rounded hover:bg-red-500/30 transition-colors"
              >
                View Overdue
              </button>
            </div>
          )}

          {/* Panel Header */}
          <div className="flex-none p-5 border-b border-border flex justify-between items-center">
            <div className="relative w-72">
              <input
                type="text"
                placeholder="Search employees..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full bg-input/80 border border-border rounded-lg py-2 pl-9 pr-4 text-foreground text-sm focus:outline-none focus:border-[#00e07a]/50 transition-colors"
              />
              <svg className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <div className="text-sm text-muted-foreground">
              {filteredEmployees.length} record{filteredEmployees.length !== 1 ? "s" : ""}
            </div>
          </div>

          {/* Table */}
          <div className="flex-1 overflow-auto">
            {loading ? (
              <div className="p-8 text-center text-muted-foreground">Loading directory...</div>
            ) : filteredEmployees.length === 0 ? (
              <div className="p-8 text-center text-muted-foreground">No employees found.</div>
            ) : (
              <table className="w-full text-left border-collapse">
                <thead className="sticky top-0 bg-card text-card-foreground shadow-sm z-10">
                  <tr>
                    <th className="py-3 px-5 text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b border-border">Employee</th>
                    <th className="py-3 px-5 text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b border-border">Role & Department</th>
                    <th className="py-3 px-5 text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b border-border">Status</th>
                    <th className="py-3 px-5 text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b border-border text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredEmployees.map((emp) => (
                    <tr key={emp.id} className={`hover:bg-card/[0.02] transition-colors border-b border-white/[0.02] ${selectedEmp?.id === emp.id ? 'bg-white/[0.02]' : ''}`}>
                      <td className="py-4 px-5 align-middle">
                        <div className="flex items-center gap-3">
                          <div className="w-9 h-9 rounded-full bg-[#00e07a]/15 border border-[#00e07a]/30 flex items-center justify-center text-[#c084fc] font-bold text-sm">
                            {getInitials(emp.full_name)}
                          </div>
                          <div>
                            <div className="text-sm font-semibold text-foreground mb-0.5">{emp.full_name}</div>
                            <div className="text-xs text-muted-foreground">{emp.email}</div>
                          </div>
                        </div>
                      </td>
                      <td className="py-4 px-5 align-middle">
                        <div className="text-sm text-foreground">{emp.job_title || "—"}</div>
                        <div className="text-xs text-muted-foreground">{emp.department || "—"}</div>
                      </td>
                      <td className="py-4 px-5 align-middle">
                        <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ${getStatusColor(emp.employment_status)}`}>
                          {emp.employment_status || "Unknown"}
                        </span>
                      </td>
                      <td className="py-4 px-5 align-middle text-right">
                        <button onClick={() => openMaster(emp)} className="px-3 py-1.5 bg-transparent border border-border rounded text-xs font-medium text-foreground hover:bg-accent hover:border-border transition-all">
                          View Master
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>

        {/* Master Profile Slide-over */}
        {selectedEmp && (
          <div className="w-96 bg-card border border-border rounded-xl flex flex-col overflow-hidden shadow-2xl relative">
            <div className="p-5 border-b border-border flex justify-between items-start bg-card/50">
              <div>
                <div className="w-12 h-12 rounded-full bg-[#00e07a]/15 border border-[#00e07a]/30 flex items-center justify-center text-[#c084fc] font-bold text-lg mb-3">
                  {getInitials(selectedEmp.full_name)}
                </div>
                <h2 className="text-xl font-bold text-foreground flex items-center gap-2">
                  {selectedEmp.full_name}
                  {selectedEmp.employment_status === 'Suspended' && (
                    <span className="text-[10px] uppercase px-2 py-0.5 rounded bg-red-500/20 text-red-500 border border-red-500/30">
                      Suspended
                    </span>
                  )}
                </h2>
                <p className="text-sm text-muted-foreground">{selectedEmp.job_title} &bull; {selectedEmp.department}</p>
              </div>
              <button onClick={closeMaster} className="text-muted-foreground hover:text-foreground">
                <X size={20} />
              </button>
            </div>
            
            <div className="flex-1 overflow-auto p-5">
              <div className="mb-6">
                <h3 className="text-xs font-semibold uppercase text-muted-foreground mb-3 tracking-wider">Quick Actions</h3>
                <div className="flex gap-2">
                  {selectedEmp.employment_status === 'Suspended' ? (
                    <button onClick={handleReinstate} className="flex-1 flex justify-center items-center gap-2 py-2 bg-[#00e07a]/10 hover:bg-[#00e07a]/20 text-[#00e07a] border border-[#00e07a]/30 rounded-lg text-sm font-medium transition-colors">
                      Reinstate Employee
                    </button>
                  ) : (
                    <button onClick={() => setSuspendModalOpen(true)} className="flex-1 flex justify-center items-center gap-2 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/30 rounded-lg text-sm font-medium transition-colors">
                      Suspend Employee
                    </button>
                  )}
                </div>
              </div>

              <div>
                <h3 className="text-xs font-semibold uppercase text-muted-foreground mb-3 tracking-wider">Suspension History</h3>
                {history.length === 0 ? (
                  <p className="text-sm text-muted-foreground italic">No suspension records found.</p>
                ) : (
                  <div className="space-y-4">
                    {history.map(record => (
                      <div key={record.id} className="p-4 bg-white/[0.02] border border-border rounded-lg">
                        <div className="flex justify-between items-start mb-2">
                          <span className={`text-xs font-medium px-2 py-0.5 rounded ${record.status === 'Active' ? 'bg-red-500/20 text-red-500' : 'bg-gray-500/20 text-gray-400'}`}>
                            {record.status}
                          </span>
                          <span className="text-xs text-muted-foreground">{record.source}</span>
                        </div>
                        <p className="text-sm text-foreground mb-2">"{record.reason}"</p>
                        <div className="text-xs text-muted-foreground space-y-1">
                          <div className="flex justify-between">
                            <span>Started:</span>
                            <span>{new Date(record.start_date).toLocaleDateString()}</span>
                          </div>
                          {record.end_date && (
                            <div className="flex justify-between">
                              <span>Expected End:</span>
                              <span>{new Date(record.end_date).toLocaleDateString()}</span>
                            </div>
                          )}
                          {record.reinstated_at && (
                            <div className="flex justify-between">
                              <span>Reinstated:</span>
                              <span>{new Date(record.reinstated_at).toLocaleDateString()}</span>
                            </div>
                          )}
                          <div className="flex justify-between pt-1 mt-1 border-t border-border">
                            <span>Actor:</span>
                            <span>{record.actor_name}</span>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

      </div>

      {/* Suspend Modal */}
      {suspendModalOpen && selectedEmp && (
        <div className="absolute inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
          <div className="bg-card w-full max-w-md rounded-xl border border-border shadow-2xl p-6">
            <h2 className="text-lg font-bold text-foreground mb-1">Suspend {selectedEmp.full_name}</h2>
            <p className="text-sm text-muted-foreground mb-5">This action will immediately restrict their access and exclude them from payroll runs.</p>

            {suspendWarning && (
              <div className="mb-4 p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg flex gap-3 text-amber-500 text-sm">
                <AlertCircle className="w-5 h-5 flex-shrink-0" />
                <div>
                  <strong className="block mb-1">Constructive Dismissal Risk</strong>
                  {suspendWarning}
                  <div className="mt-2 flex gap-2">
                    <button onClick={closeSuspendModal} className="px-3 py-1 bg-amber-500/20 rounded hover:bg-amber-500/30 transition-colors">Cancel</button>
                    <button onClick={() => { setSuspendWarning(null); handleSuspend(); }} className="px-3 py-1 bg-amber-500/20 text-white rounded hover:bg-amber-500/30 transition-colors font-medium">Acknowledge & Proceed</button>
                  </div>
                </div>
              </div>
            )}

            {!suspendWarning && (
              <>
                <div className="space-y-4 mb-6">
                  <div>
                    <label className="block text-xs font-medium text-muted-foreground mb-1.5">Reason for Suspension <span className="text-red-500">*</span></label>
                    <textarea 
                      value={suspendReason}
                      onChange={e => setSuspendReason(e.target.value)}
                      className="w-full bg-input border border-border rounded-lg p-3 text-sm text-foreground focus:outline-none focus:border-[#00e07a]/50 min-h-[80px]"
                      placeholder="e.g. Pending investigation for..."
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-muted-foreground mb-1.5">Expected End Date (Optional)</label>
                    <input 
                      type="date" 
                      value={suspendEndDate}
                      onChange={e => setSuspendEndDate(e.target.value)}
                      className="w-full bg-input border border-border rounded-lg p-2 text-sm text-foreground focus:outline-none focus:border-[#00e07a]/50"
                      style={{ colorScheme: 'dark' }}
                    />
                  </div>
                </div>
                <div className="flex justify-end gap-3">
                  <button onClick={closeSuspendModal} className="px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-accent transition-colors">
                    Cancel
                  </button>
                  <button 
                    onClick={handleSuspend}
                    disabled={!suspendReason.trim()}
                    className="px-4 py-2 bg-red-500/20 text-red-500 border border-red-500/30 rounded-lg text-sm font-medium hover:bg-red-500/30 transition-colors disabled:opacity-50"
                  >
                    Suspend Employee
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      )}

    </div>
  );
}
