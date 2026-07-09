import { useState, useEffect } from "react";
import { ThemeProvider } from "next-themes";
import { useAuth } from "../context/AuthContext";
import { apiFetch } from "../lib/apiClient";
import { 
  Banknote, 
  Percent, 
  Plus, 
  Edit2, 
  Trash2, 
  Loader2, 
  AlertCircle, 
  CheckCircle,
  Coins,
  Scale
} from "lucide-react";

interface SalaryBand {
  id: number;
  job_title: string;
  min_salary: number;
  mid_salary: number;
  max_salary: number;
  currency: string;
}

interface EquityGrant {
  id: number;
  employee_name: string;
  grant_type: "ESOP" | "RSU" | "Phantom";
  total_shares: number;
  vested_shares: number;
  vesting_schedule: string;
  grant_date: string;
}

export function CompensationAdminContent() {
  const { hasPermission } = useAuth();
  const canManage = hasPermission("compensation.manage");

  const [activeTab, setActiveTab] = useState<"bands" | "equity">("bands");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [bands, setBands] = useState<SalaryBand[]>([]);
  const [equity, setEquity] = useState<EquityGrant[]>([]);

  // Feedback notifications
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  // Modal States
  const [showBandModal, setShowBandModal] = useState(false);
  const [showEquityModal, setShowEquityModal] = useState(false);
  const [modalMode, setModalMode] = useState<"add" | "edit">("add");
  const [submitting, setSubmitting] = useState(false);

  // Band Form Fields
  const [bandId, setBandId] = useState<number | null>(null);
  const [jobTitle, setJobTitle] = useState("");
  const [minSalary, setMinSalary] = useState("");
  const [midSalary, setMidSalary] = useState("");
  const [maxSalary, setMaxSalary] = useState("");
  const [currency, setCurrency] = useState("PHP");

  // Equity Form Fields
  const [equityId, setEquityId] = useState<number | null>(null);
  const [employeeName, setEmployeeName] = useState("");
  const [grantType, setGrantType] = useState<"ESOP" | "RSU" | "Phantom">("ESOP");
  const [totalShares, setTotalShares] = useState("");
  const [vestedShares, setVestedShares] = useState("");
  const [vestingSchedule, setVestingSchedule] = useState("");
  const [grantDate, setGrantDate] = useState("");

  const showFeedback = (success: boolean, message: string) => {
    if (success) {
      setSuccessMsg(message);
      setActionError(null);
      setTimeout(() => setSuccessMsg(null), 5000);
    } else {
      setActionError(message);
      setSuccessMsg(null);
      setTimeout(() => setActionError(null), 7000);
    }
  };

  const loadData = async () => {
    setLoading(true);
    setError(null);
    try {
      if (activeTab === "bands") {
        const res = await apiFetch("/api/index.php?route=compensation&action=bands");
        if (!res.ok) throw new Error(`HTTP error ${res.status}`);
        const data = await res.json();
        if (data.success) {
          setBands(data.data || []);
        } else {
          setError(data.error || "Failed to load salary bands.");
        }
      } else {
        const res = await apiFetch("/api/index.php?route=compensation&action=equity");
        if (!res.ok) throw new Error(`HTTP error ${res.status}`);
        const data = await res.json();
        if (data.success) {
          setEquity(data.data || []);
        } else {
          setError(data.error || "Failed to load equity grants.");
        }
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || "An unexpected error occurred while loading compensation metadata.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, [activeTab]);

  // Band Submit
  const handleBandSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setActionError(null);

    const payload: any = {
      job_title: jobTitle,
      min_salary: parseFloat(minSalary),
      mid_salary: parseFloat(midSalary),
      max_salary: parseFloat(maxSalary),
      currency
    };
    if (modalMode === "edit" && bandId !== null) {
      payload.id = bandId;
    }

    try {
      const res = await apiFetch("/api/index.php?route=compensation&action=save_band", {
        method: "POST",
        body: JSON.stringify(payload)
      });

      if (!res.ok) {
        if (res.status === 403) throw new Error("Permission Denied: You do not have permission to manage salary bands.");
        throw new Error(`HTTP error ${res.status}`);
      }

      const data = await res.json();
      if (data.success) {
        showFeedback(true, `Salary band successfully ${modalMode === "add" ? "registered" : "updated"}.`);
        setShowBandModal(false);
        loadData();
      } else {
        showFeedback(false, data.error || "Failed to save salary band.");
      }
    } catch (err: any) {
      showFeedback(false, err.message || "Unable to save salary band.");
    } finally {
      setSubmitting(false);
    }
  };

  // Delete Band
  const handleBandDelete = async (id: number) => {
    if (!confirm("Are you sure you want to delete this salary band?")) return;
    try {
      const res = await apiFetch("/api/index.php?route=compensation&action=delete_band", {
        method: "POST",
        body: JSON.stringify({ id })
      });
      if (!res.ok) {
        if (res.status === 403) throw new Error("Permission Denied: You do not have permission to delete salary bands.");
        throw new Error(`HTTP error ${res.status}`);
      }
      const data = await res.json();
      if (data.success) {
        showFeedback(true, "Salary band removed successfully.");
        loadData();
      } else {
        showFeedback(false, data.error || "Failed to delete salary band.");
      }
    } catch (err: any) {
      showFeedback(false, err.message || "Failed to remove salary band.");
    }
  };

  // Equity Submit
  const handleEquitySubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setActionError(null);

    const payload: any = {
      employee_name: employeeName,
      grant_type: grantType,
      total_shares: parseFloat(totalShares),
      vested_shares: parseFloat(vestedShares),
      vesting_schedule: vestingSchedule,
      grant_date: grantDate
    };
    if (modalMode === "edit" && equityId !== null) {
      payload.id = equityId;
    }

    try {
      const res = await apiFetch("/api/index.php?route=compensation&action=save_equity", {
        method: "POST",
        body: JSON.stringify(payload)
      });

      if (!res.ok) {
        if (res.status === 403) throw new Error("Permission Denied: You do not have permission to manage equity grants.");
        throw new Error(`HTTP error ${res.status}`);
      }

      const data = await res.json();
      if (data.success) {
        showFeedback(true, `Equity grant successfully ${modalMode === "add" ? "registered" : "updated"}.`);
        setShowEquityModal(false);
        loadData();
      } else {
        showFeedback(false, data.error || "Failed to save equity grant.");
      }
    } catch (err: any) {
      showFeedback(false, err.message || "Unable to save equity grant.");
    } finally {
      setSubmitting(false);
    }
  };

  // Delete Equity
  const handleEquityDelete = async (id: number) => {
    if (!confirm("Are you sure you want to delete this equity grant?")) return;
    try {
      const res = await apiFetch("/api/index.php?route=compensation&action=delete_equity", {
        method: "POST",
        body: JSON.stringify({ id })
      });
      if (!res.ok) {
        if (res.status === 403) throw new Error("Permission Denied: You do not have permission to delete equity grants.");
        throw new Error(`HTTP error ${res.status}`);
      }
      const data = await res.json();
      if (data.success) {
        showFeedback(true, "Equity grant removed successfully.");
        loadData();
      } else {
        showFeedback(false, data.error || "Failed to delete equity grant.");
      }
    } catch (err: any) {
      showFeedback(false, err.message || "Failed to remove equity grant.");
    }
  };

  const openAddBand = () => {
    setModalMode("add");
    setBandId(null);
    setJobTitle("");
    setMinSalary("");
    setMidSalary("");
    setMaxSalary("");
    setCurrency("PHP");
    setShowBandModal(true);
  };

  const openEditBand = (b: SalaryBand) => {
    setModalMode("edit");
    setBandId(b.id);
    setJobTitle(b.job_title);
    setMinSalary(b.min_salary.toString());
    setMidSalary(b.mid_salary.toString());
    setMaxSalary(b.max_salary.toString());
    setCurrency(b.currency);
    setShowBandModal(true);
  };

  const openAddEquity = () => {
    setModalMode("add");
    setEquityId(null);
    setEmployeeName("");
    setGrantType("ESOP");
    setTotalShares("");
    setVestedShares("");
    setVestingSchedule("");
    setGrantDate(new Date().toISOString().split("T")[0]);
    setShowEquityModal(true);
  };

  const openEditEquity = (eq: EquityGrant) => {
    setModalMode("edit");
    setEquityId(eq.id);
    setEmployeeName(eq.employee_name);
    setGrantType(eq.grant_type);
    setTotalShares(eq.total_shares.toString());
    setVestedShares(eq.vested_shares.toString());
    setVestingSchedule(eq.vesting_schedule || "");
    setGrantDate(eq.grant_date);
    setShowEquityModal(true);
  };

  const formatCurrency = (val: number, cur: string) => {
    return new Intl.NumberFormat("en-US", { style: "currency", currency: cur }).format(val);
  };

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-background text-foreground">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-border bg-card text-card-foreground/50 backdrop-blur-md">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-foreground mb-1 font-['Space_Grotesk']">
              Compensation & Equity Manager
            </h1>
            <p className="text-sm text-muted-foreground">Configure salary ranges and equity structures across job roles</p>
          </div>
          {canManage && (
            <button 
              onClick={activeTab === "bands" ? openAddBand : openAddEquity}
              className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-sm transition-all shadow-[0_0_15px_rgba(0,224,122,0.2)] flex items-center gap-2 cursor-pointer"
            >
              <Plus size={16} /> Add {activeTab === "bands" ? "Salary Band" : "Equity Grant"}
            </button>
          )}
        </div>
      </div>

      {/* Main Container */}
      <div className="flex-1 overflow-auto p-8 space-y-6">
        
        {/* Inline success or error prompts */}
        {successMsg && (
          <div className="p-4 bg-[#00e07a]/10 border border-[#00e07a]/20 rounded-xl text-[#00e07a] text-sm flex items-start gap-3 max-w-4xl mx-auto">
            <CheckCircle className="w-5 h-5 flex-shrink-0" />
            <span>{successMsg}</span>
          </div>
        )}
        {actionError && (
          <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm flex items-start gap-3 max-w-4xl mx-auto">
            <AlertCircle className="w-5 h-5 flex-shrink-0" />
            <span>{actionError}</span>
          </div>
        )}

        {/* Tab Selection */}
        <div className="flex gap-4 border-b border-border max-w-4xl mx-auto">
          <button 
            onClick={() => setActiveTab("bands")}
            className={`pb-3 px-1 text-sm font-semibold transition-colors flex items-center gap-2 cursor-pointer ${
              activeTab === "bands" ? "text-foreground border-b-2 border-[#00e07a]" : "text-gray-500 hover:text-gray-300"
            }`}
          >
            <Banknote size={16} /> Salary Bands
          </button>
          <button 
            onClick={() => setActiveTab("equity")}
            className={`pb-3 px-1 text-sm font-semibold transition-colors flex items-center gap-2 cursor-pointer ${
              activeTab === "equity" ? "text-foreground border-b-2 border-[#00e07a]" : "text-gray-500 hover:text-gray-300"
            }`}
          >
            <Coins size={16} /> Employee Equity
          </button>
        </div>

        {/* Tab Contents */}
        <div className="max-w-4xl mx-auto">
          {loading ? (
            <div className="flex flex-col items-center justify-center py-20 gap-3 text-muted-foreground">
              <Loader2 className="w-8 h-8 animate-spin text-[#00e07a]" />
              <p className="text-sm font-medium">Decrypting compensation scales...</p>
            </div>
          ) : error ? (
            <div className="flex flex-col items-center justify-center py-16 px-6 bg-red-500/10 border border-red-500/20 rounded-xl text-center space-y-3">
              <AlertCircle className="w-10 h-10 text-red-500" />
              <h3 className="text-lg font-bold text-slate-900 dark:text-white">Load Error</h3>
              <p className="text-sm text-muted-foreground">{error}</p>
              <button 
                onClick={loadData}
                className="mt-2 px-4 py-2 bg-white/5 hover:bg-accent text-slate-900 dark:text-white rounded-lg text-xs transition-colors border border-border"
              >
                Retry
              </button>
            </div>
          ) : activeTab === "bands" ? (
            /* SALARY BANDS TAB */
            <div className="bg-card text-card-foreground/70 border border-border rounded-xl overflow-hidden shadow-2xl">
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead className="bg-black/25 text-gray-500 text-xs font-semibold uppercase tracking-wider">
                    <tr>
                      <th className="py-4 px-6">Job Title</th>
                      <th className="py-4 px-6 text-right">Min Salary</th>
                      <th className="py-4 px-6 text-right">Mid Salary</th>
                      <th className="py-4 px-6 text-right">Max Salary</th>
                      {canManage && <th className="py-4 px-6 text-center">Actions</th>}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-white/[0.03]">
                    {bands.map((b) => (
                      <tr key={b.id} className="hover:bg-white/[0.02] transition-colors">
                        <td className="py-4 px-6 font-semibold text-slate-900 dark:text-white">{b.job_title}</td>
                        <td className="py-4 px-6 text-right font-mono text-xs text-gray-300">
                          {formatCurrency(b.min_salary, b.currency)}
                        </td>
                        <td className="py-4 px-6 text-right font-mono text-xs text-foreground">
                          {formatCurrency(b.mid_salary, b.currency)}
                        </td>
                        <td className="py-4 px-6 text-right font-mono text-xs text-gray-300">
                          {formatCurrency(b.max_salary, b.currency)}
                        </td>
                        {canManage && (
                          <td className="py-4 px-6 text-center">
                            <div className="flex justify-center gap-2">
                              <button 
                                onClick={() => openEditBand(b)}
                                className="p-1.5 hover:bg-accent text-muted-foreground hover:text-foreground rounded transition-colors"
                              >
                                <Edit2 size={13} />
                              </button>
                              <button 
                                onClick={() => handleBandDelete(b.id)}
                                className="p-1.5 hover:bg-red-500/10 text-muted-foreground hover:text-red-400 rounded transition-colors"
                              >
                                <Trash2 size={13} />
                              </button>
                            </div>
                          </td>
                        )}
                      </tr>
                    ))}
                    {bands.length === 0 && (
                      <tr>
                        <td colSpan={canManage ? 5 : 4} className="py-12 text-center text-gray-500 text-sm">
                          No salary bands configured for this tenant.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            /* EMPLOYEE EQUITY TAB */
            <div className="bg-card text-card-foreground/70 border border-border rounded-xl overflow-hidden shadow-2xl">
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead className="bg-black/25 text-gray-500 text-xs font-semibold uppercase tracking-wider">
                    <tr>
                      <th className="py-4 px-6">Employee</th>
                      <th className="py-4 px-6">Type</th>
                      <th className="py-4 px-6 text-right">Shares (Vested / Total)</th>
                      <th className="py-4 px-6">Vesting Schedule</th>
                      <th className="py-4 px-6">Vesting Progress</th>
                      {canManage && <th className="py-4 px-6 text-center">Actions</th>}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-white/[0.03]">
                    {equity.map((eq) => {
                      const percent = eq.total_shares > 0 
                        ? Math.min(100, Math.round((eq.vested_shares / eq.total_shares) * 100)) 
                        : 0;
                      return (
                        <tr key={eq.id} className="hover:bg-white/[0.02] transition-colors">
                          <td className="py-4 px-6">
                            <div className="font-semibold text-slate-900 dark:text-white">{eq.employee_name}</div>
                            <div className="text-[10px] text-gray-500 font-mono mt-0.5">Granted: {eq.grant_date}</div>
                          </td>
                          <td className="py-4 px-6">
                            <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border bg-purple-500/10 text-purple-400 border-purple-500/20">
                              {eq.grant_type}
                            </span>
                          </td>
                          <td className="py-4 px-6 text-right font-mono text-xs">
                            <span className="text-slate-900 dark:text-white font-bold">{eq.vested_shares.toLocaleString()}</span>
                            <span className="text-gray-500"> / {eq.total_shares.toLocaleString()}</span>
                          </td>
                          <td className="py-4 px-6 text-xs text-muted-foreground max-w-[150px] truncate" title={eq.vesting_schedule}>
                            {eq.vesting_schedule}
                          </td>
                          <td className="py-4 px-6 w-44">
                            <div className="space-y-1.5">
                              <div className="flex justify-between text-[10px] font-mono font-bold leading-none">
                                <span className="text-muted-foreground">{percent}% Vested</span>
                              </div>
                              <div className="h-1.5 w-full bg-white/5 rounded-full overflow-hidden">
                                <div 
                                  className="h-full bg-gradient-to-r from-purple-500 to-[#00b8ff] rounded-full transition-all duration-500" 
                                  style={{ width: `${percent}%` }}
                                />
                              </div>
                            </div>
                          </td>
                          {canManage && (
                            <td className="py-4 px-6 text-center">
                              <div className="flex justify-center gap-2">
                                <button 
                                  onClick={() => openEditEquity(eq)}
                                  className="p-1.5 hover:bg-accent text-muted-foreground hover:text-foreground rounded transition-colors"
                                >
                                  <Edit2 size={13} />
                                </button>
                                <button 
                                  onClick={() => handleEquityDelete(eq.id)}
                                  className="p-1.5 hover:bg-red-500/10 text-muted-foreground hover:text-red-400 rounded transition-colors"
                                >
                                  <Trash2 size={13} />
                                </button>
                              </div>
                            </td>
                          )}
                        </tr>
                      );
                    })}
                    {equity.length === 0 && (
                      <tr>
                        <td colSpan={canManage ? 6 : 5} className="py-12 text-center text-gray-500 text-sm">
                          No equity grants registered for this tenant.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Salary Band Modal */}
      {showBandModal && (
        <div className="fixed inset-0 bg-black/75 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-card text-card-foreground border border-border rounded-xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="p-5 border-b border-border flex justify-between items-center bg-black/10">
              <h3 className="text-base font-bold text-foreground uppercase tracking-wider">
                {modalMode === "add" ? "Register Salary Band" : "Adjust Salary Band"}
              </h3>
              <button onClick={() => setShowBandModal(false)} className="text-muted-foreground hover:text-white text-xl leading-none">&times;</button>
            </div>
            <form onSubmit={handleBandSubmit} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Job Title</label>
                <input 
                  type="text" 
                  required
                  value={jobTitle}
                  onChange={(e) => setJobTitle(e.target.value)}
                  className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                  placeholder="e.g. Senior Software Engineer"
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Min Salary</label>
                  <input 
                    type="number" 
                    required
                    value={minSalary}
                    onChange={(e) => setMinSalary(e.target.value)}
                    className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                    placeholder="Min"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Mid Salary</label>
                  <input 
                    type="number" 
                    required
                    value={midSalary}
                    onChange={(e) => setMidSalary(e.target.value)}
                    className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                    placeholder="Mid"
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Max Salary</label>
                  <input 
                    type="number" 
                    required
                    value={maxSalary}
                    onChange={(e) => setMaxSalary(e.target.value)}
                    className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                    placeholder="Max"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Currency</label>
                  <input 
                    type="text" 
                    required
                    value={currency}
                    onChange={(e) => setCurrency(e.target.value.toUpperCase())}
                    className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                    placeholder="PHP"
                  />
                </div>
              </div>
              <div className="pt-2 flex justify-end gap-3 border-t border-border mt-4">
                <button type="button" onClick={() => setShowBandModal(false)} className="px-3 py-1.5 text-muted-foreground hover:text-white text-xs font-semibold">Cancel</button>
                <button type="submit" disabled={submitting} className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-xs transition-colors flex items-center gap-1.5">
                  {submitting && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                  Save Band Changes
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Equity Modal */}
      {showEquityModal && (
        <div className="fixed inset-0 bg-black/75 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-card text-card-foreground border border-border rounded-xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="p-5 border-b border-border flex justify-between items-center bg-black/10">
              <h3 className="text-base font-bold text-foreground uppercase tracking-wider">
                {modalMode === "add" ? "Grant Employee Equity" : "Adjust Equity Grant"}
              </h3>
              <button onClick={() => setShowEquityModal(false)} className="text-muted-foreground hover:text-white text-xl leading-none">&times;</button>
            </div>
            <form onSubmit={handleEquitySubmit} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Employee Name</label>
                <input 
                  type="text" 
                  required
                  value={employeeName}
                  onChange={(e) => setEmployeeName(e.target.value)}
                  className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                  placeholder="e.g. Juan dela Cruz"
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Grant Type</label>
                  <select 
                    value={grantType}
                    onChange={(e) => setGrantType(e.target.value as any)}
                    className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:outline-none focus:border-[#00e07a]/50"
                  >
                    <option value="ESOP">ESOP</option>
                    <option value="RSU">RSU</option>
                    <option value="Phantom">Phantom</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Grant Date</label>
                  <input 
                    type="date" 
                    required
                    value={grantDate}
                    onChange={(e) => setGrantDate(e.target.value)}
                    className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:outline-none focus:border-[#00e07a]/50 [color-scheme:dark]" 
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Total Shares</label>
                  <input 
                    type="number" 
                    required
                    value={totalShares}
                    onChange={(e) => setTotalShares(e.target.value)}
                    className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                    placeholder="e.g. 10000"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Vested Shares</label>
                  <input 
                    type="number" 
                    required
                    value={vestedShares}
                    onChange={(e) => setVestedShares(e.target.value)}
                    className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                    placeholder="e.g. 2500"
                  />
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Vesting Schedule</label>
                <input 
                  type="text" 
                  required
                  value={vestingSchedule}
                  onChange={(e) => setVestingSchedule(e.target.value)}
                  className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-slate-900 dark:text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                  placeholder="e.g. 4-year monthly vesting, 1-year cliff"
                />
              </div>
              <div className="pt-2 flex justify-end gap-3 border-t border-border mt-4">
                <button type="button" onClick={() => setShowEquityModal(false)} className="px-3 py-1.5 text-muted-foreground hover:text-white text-xs font-semibold">Cancel</button>
                <button type="submit" disabled={submitting} className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-xs transition-colors flex items-center gap-1.5">
                  {submitting && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                  Save Grant Changes
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

export function CompensationAdmin() {
  return (
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <div className="h-full w-full flex-1 overflow-hidden relative" style={{ isolation: 'isolate' }}>
        <CompensationAdminContent />
      </div>
    </ThemeProvider>
  );
}
