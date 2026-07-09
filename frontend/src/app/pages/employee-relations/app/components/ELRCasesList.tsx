import { useState, useEffect } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { useAuth } from "../../../../context/AuthContext";
import { 
  Search, 
  Plus, 
  AlertCircle, 
  Loader2, 
  FileText, 
  Eye, 
  Lock,
  X,
  SlidersHorizontal,
  Bookmark
} from "lucide-react";

interface CaseType {
  id: number;
  name: string;
  description: string;
}

interface ELRCase {
  id: number;
  case_number: string;
  employee_id: string;
  department: string;
  case_type_id: number;
  case_type_name: string;
  severity: "Low" | "Medium" | "High" | "Critical";
  status: string;
  is_confidential: number;
  created_at: string;
}

interface ELRCasesListProps {
  onViewChange: (view: string) => void;
  onSelectCase: (id: number) => void;
  mine?: boolean;
}

export function ELRCasesList({ onViewChange, onSelectCase, mine = false }: ELRCasesListProps) {
  const { hasPermission } = useAuth();
  const canInvestigate = hasPermission("elr.investigate");

  const [cases, setCases] = useState<ELRCase[]>([]);
  const [caseTypes, setCaseTypes] = useState<CaseType[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filter States
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [severityFilter, setSeverityFilter] = useState("");

  // Modal States
  const [showModal, setShowModal] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [modalError, setModalError] = useState<string | null>(null);

  // Form Fields
  const [employeeId, setEmployeeId] = useState("");
  const [department, setDepartment] = useState("");
  const [caseTypeId, setCaseTypeId] = useState("");
  const [severity, setSeverity] = useState<"Low" | "Medium" | "High" | "Critical">("Low");
  const [description, setDescription] = useState("");
  const [reportedBy, setReportedBy] = useState("");
  const [isConfidential, setIsConfidential] = useState(false);
  const [anonymousReport, setAnonymousReport] = useState(false);

  const fetchCases = async () => {
    setLoading(true);
    setError(null);
    try {
      const [casesRes, typesRes] = await Promise.all([
        apiFetch(`/api/index.php?route=elr&action=cases${mine ? "&mine=1" : ""}`),
        apiFetch("/api/index.php?route=elr&action=case_types")
      ]);

      if (!casesRes.ok || !typesRes.ok) throw new Error("Failed to load cases or case types.");

      const casesData = await casesRes.json();
      const typesData = await typesRes.json();

      if (casesData.success) {
        setCases(casesData.cases || []);
      } else {
        setError(casesData.error || "Failed to load cases.");
      }

      if (typesData.success) {
        setCaseTypes(typesData.case_types || []);
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || "An unexpected error occurred while loading investigations.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCases();
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setModalError(null);

    const payload = {
      employee_id: employeeId,
      department,
      case_type_id: parseInt(caseTypeId, 10),
      severity,
      description,
      reported_by_employee_id: reportedBy,
      is_confidential: isConfidential ? 1 : 0,
      anonymous_report: anonymousReport ? 1 : 0
    };

    try {
      const res = await apiFetch("/api/index.php?route=elr&action=create_case", {
        method: "POST",
        body: JSON.stringify(payload)
      });

      if (!res.ok) {
        if (res.status === 403) throw new Error("Access Denied: You do not have permissions to open investigations.");
        throw new Error(`HTTP error ${res.status}`);
      }

      const data = await res.json();
      if (data.success) {
        setShowModal(false);
        // Clear fields
        setEmployeeId("");
        setDepartment("");
        setCaseTypeId("");
        setSeverity("Low");
        setDescription("");
        setReportedBy("");
        setIsConfidential(false);
        setAnonymousReport(false);
        
        fetchCases();
      } else {
        setModalError(data.error || "Failed to create case.");
      }
    } catch (err: any) {
      setModalError(err.message || "Unable to save investigation record.");
    } finally {
      setSubmitting(false);
    }
  };

  const getSeverityStyle = (sev: string) => {
    switch (sev) {
      case "Critical": return "bg-red-500/10 text-red-500 border border-red-500/20";
      case "High": return "bg-orange-500/10 text-orange-400 border border-orange-500/20";
      case "Medium": return "bg-blue-500/10 text-blue-400 border border-blue-500/20";
      default: return "bg-gray-500/10 text-muted-foreground border border-gray-500/20";
    }
  };

  const getStatusStyle = (status: string) => {
    switch (status) {
      case "Closed": return "bg-gray-500/10 text-muted-foreground border border-gray-500/20";
      case "Resolved": return "bg-emerald-500/10 text-emerald-400 border border-emerald-500/20";
      case "Pending Approval": return "bg-purple-500/10 text-purple-400 border border-purple-500/20";
      default: return "bg-blue-500/10 text-blue-400 border border-blue-500/20";
    }
  };

  const filteredCases = cases.filter(c => {
    const searchString = search.toLowerCase();
    const matchesSearch = 
      c.case_number.toLowerCase().includes(searchString) ||
      c.employee_id.toLowerCase().includes(searchString) ||
      c.department.toLowerCase().includes(searchString) ||
      (c.case_type_name && c.case_type_name.toLowerCase().includes(searchString));

    const matchesStatus = statusFilter === "" || c.status === statusFilter;
    const matchesSeverity = severityFilter === "" || c.severity === severityFilter;

    return matchesSearch && matchesStatus && matchesSeverity;
  });

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-background text-foreground">
      
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-border bg-card text-card-foreground/50 backdrop-blur-md">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-foreground mb-1 font-['Space_Grotesk']">
              Investigation Registry
            </h1>
            <p className="text-sm text-muted-foreground">Track and manage employee relations cases and incidents</p>
          </div>
          {canInvestigate && (
            <button 
              onClick={() => setShowModal(true)}
              className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-sm transition-all shadow-[0_0_15px_rgba(0,224,122,0.2)] flex items-center gap-2 cursor-pointer"
            >
              <Plus size={16} /> Open New Case
            </button>
          )}
        </div>
      </div>

      {/* Main Container */}
      <div className="flex-1 overflow-auto p-8 space-y-6">
        
        {/* Filters */}
        <div className="bg-card text-card-foreground/40 border border-border p-4 rounded-xl flex flex-wrap gap-4 items-center justify-between shadow-sm">
          <div className="flex flex-wrap gap-3 items-center flex-1">
            <div className="relative w-64">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" size={15} />
              <input 
                type="text"
                placeholder="Search case, ID, department..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full bg-background border border-border rounded-lg py-2 pl-9 pr-3 text-foreground text-sm focus:outline-none focus:border-[#00e07a]/50"
              />
            </div>

            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="bg-background border border-border rounded-lg py-2 px-3 text-sm text-foreground focus:outline-none focus:border-[#00e07a]/50"
            >
              <option value="">All Statuses</option>
              <option value="Open">Open</option>
              <option value="Under Review">Under Review</option>
              <option value="Investigating">Investigating</option>
              <option value="Pending Approval">Pending Approval</option>
              <option value="Resolved">Resolved</option>
              <option value="Closed">Closed</option>
            </select>

            <select
              value={severityFilter}
              onChange={(e) => setSeverityFilter(e.target.value)}
              className="bg-background border border-border rounded-lg py-2 px-3 text-sm text-foreground focus:outline-none focus:border-[#00e07a]/50"
            >
              <option value="">All Severities</option>
              <option value="Low">Low</option>
              <option value="Medium">Medium</option>
              <option value="High">High</option>
              <option value="Critical">Critical</option>
            </select>
          </div>
          <div className="text-xs text-gray-500 font-sans">
            Showing {filteredCases.length} incident record{filteredCases.length !== 1 ? "s" : ""}
          </div>
        </div>

        {/* Content */}
        {loading ? (
          <div className="flex flex-col items-center justify-center py-20 gap-3 text-muted-foreground">
            <Loader2 className="w-8 h-8 animate-spin text-[#00e07a]" />
            <p className="text-sm font-medium">Resolving case registry...</p>
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center py-16 px-6 bg-red-500/10 border border-red-500/20 rounded-xl text-center space-y-3">
            <AlertCircle className="w-10 h-10 text-red-500" />
            <h3 className="text-lg font-bold text-foreground">Load Error</h3>
            <p className="text-sm text-muted-foreground">{error}</p>
            <button 
              onClick={fetchCases}
              className="mt-2 px-4 py-2 bg-white/5 hover:bg-accent text-foreground rounded-lg text-xs transition-colors border border-border"
            >
              Retry
            </button>
          </div>
        ) : (
          <div className="bg-card text-card-foreground/70 border border-border rounded-xl overflow-hidden shadow-2xl">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead className="bg-black/25 text-gray-500 text-xs font-semibold uppercase tracking-wider">
                  <tr>
                    <th className="py-4 px-6">Case Number</th>
                    <th className="py-4 px-6">Employee ID</th>
                    <th className="py-4 px-6">Department</th>
                    <th className="py-4 px-6">Case Type</th>
                    <th className="py-4 px-6 text-center">Severity</th>
                    <th className="py-4 px-6 text-center">Status</th>
                    <th className="py-4 px-6 text-center">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/[0.03]">
                  {filteredCases.map((c) => (
                    <tr key={c.id} className="hover:bg-white/[0.01] transition-colors">
                      <td className="py-4 px-6">
                        <div className="flex items-center gap-2">
                          <span className="font-semibold text-foreground">{c.case_number}</span>
                          {c.is_confidential === 1 && (
                            <span title="Confidential"><Lock size={12} className="text-red-400" /></span>
                          )}
                        </div>
                      </td>
                      <td className="py-4 px-6 font-mono text-xs text-gray-300">{c.employee_id}</td>
                      <td className="py-4 px-6 text-xs text-gray-300">{c.department}</td>
                      <td className="py-4 px-6 text-xs text-foreground">{c.case_type_name || "General Inquiry"}</td>
                      <td className="py-4 px-6 text-center">
                        <span className={`inline-flex px-2 py-0.5 rounded text-[10px] font-bold ${getSeverityStyle(c.severity)}`}>
                          {c.severity}
                        </span>
                      </td>
                      <td className="py-4 px-6 text-center">
                        <span className={`inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold ${getStatusStyle(c.status)}`}>
                          {c.status}
                        </span>
                      </td>
                      <td className="py-4 px-6 text-center">
                        <button 
                          onClick={() => onSelectCase(c.id)}
                          className="px-3 py-1 bg-white/5 hover:bg-accent text-foreground rounded text-xs font-semibold transition-colors flex items-center gap-1.5 mx-auto cursor-pointer border border-border"
                        >
                          <Eye size={12} /> View Details
                        </button>
                      </td>
                    </tr>
                  ))}
                  {filteredCases.length === 0 && (
                    <tr>
                      <td colSpan={7} className="py-12 text-center text-gray-500 text-sm">
                        No cases found matching the criteria.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

      </div>

      {/* New Case Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/75 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-card text-card-foreground border border-border rounded-xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="p-5 border-b border-border flex justify-between items-center bg-black/10">
              <h3 className="text-base font-bold text-foreground uppercase tracking-wider">
                Open Investigation Case
              </h3>
              <button onClick={() => setShowModal(false)} className="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
            </div>
            
            <form onSubmit={handleSubmit} className="p-5 space-y-4">
              {modalError && (
                <div className="p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-xs flex items-start gap-2">
                  <AlertCircle size={14} className="flex-shrink-0 mt-0.5" />
                  <span>{modalError}</span>
                </div>
              )}

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Employee ID</label>
                  <input 
                    type="text" 
                    required
                    value={employeeId}
                    onChange={(e) => setEmployeeId(e.target.value)}
                    className="w-full bg-background border border-border rounded-lg py-2 px-3 text-foreground text-xs focus:outline-none focus:border-[#00e07a]/50" 
                    placeholder="e.g. EMP-001"
                  />
                </div>
                <div>
                  <label className="block text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Department</label>
                  <input 
                    type="text" 
                    required
                    value={department}
                    onChange={(e) => setDepartment(e.target.value)}
                    className="w-full bg-background border border-border rounded-lg py-2 px-3 text-foreground text-xs focus:outline-none focus:border-[#00e07a]/50" 
                    placeholder="e.g. Engineering"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Case Type</label>
                  <select
                    required
                    value={caseTypeId}
                    onChange={(e) => setCaseTypeId(e.target.value)}
                    className="w-full bg-background border border-border rounded-lg py-2 px-3 text-foreground text-xs focus:outline-none focus:border-[#00e07a]/50"
                  >
                    <option value="">Select Type</option>
                    {caseTypes.map(t => (
                      <option key={t.id} value={t.id}>{t.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Severity</label>
                  <select
                    value={severity}
                    onChange={(e) => setSeverity(e.target.value as any)}
                    className="w-full bg-background border border-border rounded-lg py-2 px-3 text-foreground text-xs focus:outline-none focus:border-[#00e07a]/50"
                  >
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                    <option value="Critical">Critical</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Reported By (Emp ID)</label>
                <input 
                  type="text"
                  value={reportedBy}
                  onChange={(e) => setReportedBy(e.target.value)}
                  className="w-full bg-background border border-border rounded-lg py-2 px-3 text-foreground text-xs focus:outline-none focus:border-[#00e07a]/50" 
                  placeholder="e.g. EMP-010 (Leave empty if none)"
                />
              </div>

              <div>
                <label className="block text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Description</label>
                <textarea 
                  required
                  rows={4}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  className="w-full bg-background border border-border rounded-lg py-2 px-3 text-foreground text-xs focus:outline-none focus:border-[#00e07a]/50 resize-none" 
                  placeholder="Provide objective documentation regarding this investigation..."
                />
              </div>

              <div className="flex gap-6 items-center">
                <label className="flex items-center gap-2 text-xs font-semibold text-muted-foreground cursor-pointer">
                  <input 
                    type="checkbox" 
                    checked={isConfidential}
                    onChange={(e) => setIsConfidential(e.target.checked)}
                    className="rounded bg-background border-border text-[#00e07a] focus:ring-0"
                  />
                  Confidential
                </label>
                <label className="flex items-center gap-2 text-xs font-semibold text-muted-foreground cursor-pointer">
                  <input 
                    type="checkbox" 
                    checked={anonymousReport}
                    onChange={(e) => setAnonymousReport(e.target.checked)}
                    className="rounded bg-background border-border text-[#00e07a] focus:ring-0"
                  />
                  Anonymous Report
                </label>
              </div>

              <div className="pt-2 flex justify-end gap-3 border-t border-border mt-4">
                <button type="button" onClick={() => setShowModal(false)} className="px-3 py-1.5 text-muted-foreground hover:text-foreground text-xs font-semibold cursor-pointer">Cancel</button>
                <button type="submit" disabled={submitting} className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-extrabold rounded-lg text-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                  {submitting && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                  Open Case Registry
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
