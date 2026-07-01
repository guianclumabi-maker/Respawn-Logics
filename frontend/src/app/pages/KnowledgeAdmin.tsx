import { useState, useEffect } from "react";
import { useAuth } from "../context/AuthContext";
import { apiFetch } from "../lib/apiClient";
import { 
  BookOpen, 
  Scale, 
  Plus, 
  Check, 
  X, 
  ShieldAlert, 
  Loader2, 
  AlertCircle, 
  ExternalLink,
  Lock,
  Compass
} from "lucide-react";

interface LaborReference {
  id: number;
  category: string;
  title: string;
  summary: string;
  source_type: string;
  official_url?: string;
  effective_date?: string;
  status: "Pending" | "Approved" | "Rejected";
}

interface Precedent {
  id: number;
  case_type: string;
  title: string;
  summary: string;
  key_principles: string;
  source_reference: string;
  risk_level: "Low" | "Medium" | "High" | "Critical";
  recommended_process: string;
}

export function KnowledgeAdmin() {
  const { user } = useAuth();
  const isSuperAdmin = user?.roles?.includes("Super_Admin") || false;

  const [activeTab, setActiveTab] = useState<"references" | "precedents">("references");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [references, setReferences] = useState<LaborReference[]>([]);
  const [precedents, setPrecedents] = useState<Precedent[]>([]);

  // Form Modal States
  const [showAddModal, setShowAddModal] = useState(false);
  const [entryType, setEntryType] = useState<"reference" | "precedent">("reference");
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  // Common Form Fields
  const [title, setTitle] = useState("");
  const [summary, setSummary] = useState("");

  // Reference Specific Form Fields
  const [category, setCategory] = useState("DOLE Advisory");
  const [sourceType, setSourceType] = useState("DOLE");
  const [officialUrl, setOfficialUrl] = useState("");
  const [effectiveDate, setEffectiveDate] = useState("");

  // Precedent Specific Form Fields
  const [caseType, setCaseType] = useState("Jurisprudence");
  const [keyPrinciples, setKeyPrinciples] = useState("");
  const [sourceReference, setSourceReference] = useState("");
  const [riskLevel, setRiskLevel] = useState<"Low" | "Medium" | "High" | "Critical">("Medium");
  const [recommendedProcess, setRecommendedProcess] = useState("");

  const fetchData = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch("/api/index.php?route=elr&action=kb_list");
      if (!res.ok) throw new Error("Failed to load knowledge corpus.");
      const data = await res.json();
      if (data.success) {
        setReferences(data.labor_references || []);
        setPrecedents(data.precedents || []);
      } else {
        setError(data.error || "Failed to retrieve corpus details.");
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || "Unable to load knowledge base logs.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleApproveReject = async (id: number, status: "Approved" | "Rejected") => {
    try {
      const res = await apiFetch("/api/index.php?route=elr&action=kb_approve", {
        method: "POST",
        body: JSON.stringify({ id, status })
      });
      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data = await res.json();
      if (data.success) {
        fetchData();
      } else {
        alert(data.error || "Failed to update entry status.");
      }
    } catch (err: any) {
      alert(err.message || "Failed to contact database.");
    }
  };

  const handleAddEntry = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setFormError(null);

    const payload: any = { type: entryType, title, summary };
    if (entryType === "reference") {
      payload.category = category;
      payload.source_type = sourceType;
      payload.official_url = officialUrl;
      payload.effective_date = effectiveDate;
    } else {
      payload.case_type = caseType;
      payload.key_principles = keyPrinciples;
      payload.source_reference = sourceReference;
      payload.risk_level = riskLevel;
      payload.recommended_process = recommendedProcess;
    }

    try {
      const res = await apiFetch("/api/index.php?route=elr&action=kb_add", {
        method: "POST",
        body: JSON.stringify(payload)
      });
      if (!res.ok) {
        if (res.status === 403) throw new Error("Permission Denied: Only Super_Admins can write to knowledge base.");
        throw new Error(`HTTP error ${res.status}`);
      }
      const data = await res.json();
      if (data.success) {
        setShowAddModal(false);
        // Clear forms
        setTitle("");
        setSummary("");
        setOfficialUrl("");
        setEffectiveDate("");
        setKeyPrinciples("");
        setSourceReference("");
        setRecommendedProcess("");
        fetchData();
      } else {
        setFormError(data.error || "Failed to create entry.");
      }
    } catch (err: any) {
      console.error(err);
      setFormError(err.message || "Failed to add knowledge base entry.");
    } finally {
      setSubmitting(false);
    }
  };

  const getStatusBadgeColor = (status: string) => {
    switch (status) {
      case "Approved": return "bg-[#00e07a]/10 text-[#00e07a] border-[#00e07a]/25";
      case "Rejected": return "bg-red-500/10 text-red-500 border-red-500/25";
      default: return "bg-amber-500/10 text-amber-500 border-amber-500/25";
    }
  };

  const getRiskBadgeColor = (risk: string) => {
    switch (risk) {
      case "Critical": return "bg-red-500/20 text-red-400 border-red-500/30";
      case "High": return "bg-orange-500/20 text-orange-400 border-orange-500/30";
      case "Medium": return "bg-yellow-500/20 text-yellow-400 border-yellow-500/30";
      default: return "bg-blue-500/20 text-blue-400 border-blue-500/30";
    }
  };

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-[#06070a] text-[#c8d0e0]">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-white/5 bg-[#161922]/50 backdrop-blur-md">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-white mb-1 font-['Space_Grotesk']">
              Knowledge Base Review
            </h1>
            <p className="text-sm text-gray-400">Review statutory DOLE advisories and SC labor jurisprudence</p>
          </div>
          {isSuperAdmin && (
            <button 
              onClick={() => {
                setFormError(null);
                setShowAddModal(true);
              }}
              className="px-4 py-2 bg-gradient-to-r from-[#00e07a] to-[#00b8ff] text-black font-bold border-none rounded-lg text-sm hover:opacity-90 transition-opacity shadow-[0_0_15px_rgba(0,224,122,0.3)] flex items-center gap-2"
            >
              <Plus size={16} /> Add Corpus Entry
            </button>
          )}
        </div>
      </div>

      {/* Main Body */}
      <div className="flex-1 overflow-auto p-8 space-y-6">
        
        {/* Permission Info (Non-Super Admins) */}
        {!isSuperAdmin && (
          <div className="p-4 bg-blue-500/5 border border-blue-500/10 rounded-xl text-xs text-gray-400 flex items-center gap-2.5">
            <Lock className="w-4 h-4 text-blue-400 flex-shrink-0" />
            <span>Read-Only View: Only platform administrators can write entries or approve/reject pending references.</span>
          </div>
        )}

        {loading ? (
          <div className="flex flex-col items-center justify-center py-20 gap-3 text-gray-400">
            <Loader2 className="w-8 h-8 animate-spin text-[#00e07a]" />
            <p className="text-sm font-medium">Scanning compliance references...</p>
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center py-16 px-6 bg-red-500/10 border border-red-500/20 rounded-xl max-w-xl mx-auto text-center space-y-3">
            <AlertCircle className="w-10 h-10 text-red-500" />
            <h3 className="text-lg font-bold text-white">Load Error</h3>
            <p className="text-sm text-gray-400">{error}</p>
            <button 
              onClick={fetchData}
              className="mt-2 px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-xs transition-colors border border-white/10"
            >
              Retry
            </button>
          </div>
        ) : (
          <>
            {/* Tabs */}
            <div className="flex gap-4 border-b border-white/5">
              <button 
                onClick={() => setActiveTab("references")}
                className={`pb-3 px-1 text-sm font-medium transition-colors flex items-center gap-2 ${
                  activeTab === "references" ? "text-white border-b-2 border-[#00e07a]" : "text-gray-500 hover:text-gray-300"
                }`}
              >
                <BookOpen size={16} /> Labor References ({references.length})
              </button>
              <button 
                onClick={() => setActiveTab("precedents")}
                className={`pb-3 px-1 text-sm font-medium transition-colors flex items-center gap-2 ${
                  activeTab === "precedents" ? "text-white border-b-2 border-[#00e07a]" : "text-gray-500 hover:text-gray-300"
                }`}
              >
                <Scale size={16} /> Legal Precedents ({precedents.length})
              </button>
            </div>

            {/* TAB CONTENT: REFERENCES */}
            {activeTab === "references" && (
              <div className="bg-[#161922]/70 border border-white/5 rounded-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                  <table className="w-full text-left border-collapse">
                    <thead className="bg-black/25 text-gray-500 text-xs font-semibold uppercase tracking-wider">
                      <tr>
                        <th className="py-4 px-6">Title & Category</th>
                        <th className="py-4 px-6">Summary</th>
                        <th className="py-4 px-6">Type & Date</th>
                        <th className="py-4 px-6">Status</th>
                        {isSuperAdmin && <th className="py-4 px-6 text-right">Actions</th>}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/[0.03]">
                      {references.map((ref) => (
                        <tr key={ref.id} className="hover:bg-white/[0.02] transition-colors">
                          <td className="py-4 px-6 max-w-xs">
                            <div className="text-sm font-bold text-white leading-tight">{ref.title}</div>
                            <div className="text-[10px] text-cyan-400 font-semibold uppercase mt-1">{ref.category}</div>
                          </td>
                          <td className="py-4 px-6 text-sm text-gray-400 max-w-md line-clamp-3" title={ref.summary}>
                            {ref.summary}
                          </td>
                          <td className="py-4 px-6 text-xs text-gray-300">
                            <div>{ref.source_type}</div>
                            {ref.effective_date && <div className="text-gray-500 font-mono mt-0.5">{ref.effective_date}</div>}
                            {ref.official_url && (
                              <a href={ref.official_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-cyan-400 hover:underline mt-1">
                                Link <ExternalLink size={10} />
                              </a>
                            )}
                          </td>
                          <td className="py-4 px-6">
                            <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border ${getStatusBadgeColor(ref.status)}`}>
                              {ref.status}
                            </span>
                          </td>
                          {isSuperAdmin && (
                            <td className="py-4 px-6 text-right">
                              {ref.status === "Pending" ? (
                                <div className="inline-flex gap-2">
                                  <button 
                                    onClick={() => handleApproveReject(ref.id, "Approved")}
                                    className="p-1.5 bg-[#00e07a]/15 text-[#00e07a] border border-[#00e07a]/25 rounded hover:bg-[#00e07a]/25"
                                    title="Approve Reference"
                                  >
                                    <Check size={14} />
                                  </button>
                                  <button 
                                    onClick={() => handleApproveReject(ref.id, "Rejected")}
                                    className="p-1.5 bg-red-500/15 text-red-500 border border-red-500/25 rounded hover:bg-red-500/25"
                                    title="Reject Reference"
                                  >
                                    <X size={14} />
                                  </button>
                                </div>
                              ) : (
                                <span className="text-xs text-gray-600">—</span>
                              )}
                            </td>
                          )}
                        </tr>
                      ))}
                      {references.length === 0 && (
                        <tr>
                          <td colSpan={isSuperAdmin ? 5 : 4} className="py-12 text-center text-gray-500 text-sm">
                            <Compass className="w-10 h-10 text-gray-600 mx-auto mb-2" />
                            No labor references logged.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* TAB CONTENT: PRECEDENTS */}
            {activeTab === "precedents" && (
              <div className="bg-[#161922]/70 border border-white/5 rounded-xl overflow-hidden shadow-2xl">
                <div className="overflow-x-auto">
                  <table className="w-full text-left border-collapse">
                    <thead className="bg-black/25 text-gray-500 text-xs font-semibold uppercase tracking-wider">
                      <tr>
                        <th className="py-4 px-6">Jurisprudence</th>
                        <th className="py-4 px-6">Key Principles</th>
                        <th className="py-4 px-6">Process Recommendations</th>
                        <th className="py-4 px-6">Risk</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-white/[0.03]">
                      {precedents.map((prec) => (
                        <tr key={prec.id} className="hover:bg-white/[0.02] transition-colors">
                          <td className="py-4 px-6 max-w-xs">
                            <div className="text-sm font-bold text-white leading-tight">{prec.title}</div>
                            <div className="text-[10px] text-gray-500 font-mono mt-1">{prec.source_reference}</div>
                            <div className="text-xs text-gray-400 mt-2 line-clamp-3">{prec.summary}</div>
                          </td>
                          <td className="py-4 px-6 text-sm text-gray-300 max-w-xs font-sans" title={prec.key_principles}>
                            <p className="line-clamp-4">{prec.key_principles}</p>
                          </td>
                          <td className="py-4 px-6 text-xs text-gray-400 max-w-xs font-sans" title={prec.recommended_process}>
                            <p className="line-clamp-4">{prec.recommended_process}</p>
                          </td>
                          <td className="py-4 px-6">
                            <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border ${getRiskBadgeColor(prec.risk_level)}`}>
                              {prec.risk_level}
                            </span>
                          </td>
                        </tr>
                      ))}
                      {precedents.length === 0 && (
                        <tr>
                          <td colSpan={4} className="py-12 text-center text-gray-500 text-sm">
                            <Compass className="w-10 h-10 text-gray-600 mx-auto mb-2" />
                            No precedents logged.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {/* Add Modal */}
      {showAddModal && (
        <div className="fixed inset-0 bg-black/75 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-[#161922] border border-white/10 rounded-xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 text-[#c8d0e0]">
            <div className="p-5 border-b border-white/5 flex justify-between items-center bg-black/10">
              <h3 className="text-base font-bold text-white uppercase tracking-wider">Add Knowledge Entry</h3>
              <button 
                onClick={() => setShowAddModal(false)} 
                className="text-gray-400 hover:text-white text-xl leading-none"
              >
                &times;
              </button>
            </div>
            
            <form onSubmit={handleAddEntry} className="p-5 space-y-4 max-h-[75vh] overflow-y-auto scrollbar-thin">
              {formError && (
                <div className="p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-xs flex items-start gap-2">
                  <AlertCircle className="w-4 h-4 mt-0.5 flex-shrink-0" />
                  <span>{formError}</span>
                </div>
              )}

              <div>
                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Entry Type</label>
                <div className="flex gap-4">
                  <label className="flex items-center gap-2 cursor-pointer text-sm text-white">
                    <input 
                      type="radio" 
                      name="entryType"
                      checked={entryType === "reference"}
                      onChange={() => setEntryType("reference")}
                      className="accent-[#00e07a]" 
                    />
                    Labor Reference (DOLE / Statutory)
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer text-sm text-white">
                    <input 
                      type="radio" 
                      name="entryType"
                      checked={entryType === "precedent"}
                      onChange={() => setEntryType("precedent")}
                      className="accent-[#00e07a]" 
                    />
                    Legal Precedent (SC Jurisprudence)
                  </label>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Title</label>
                <input 
                  type="text" 
                  required
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                  placeholder="e.g. DOLE Advisory No. 17-15"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Summary / Context</label>
                <textarea 
                  required
                  value={summary}
                  onChange={(e) => setSummary(e.target.value)}
                  rows={3}
                  className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 resize-none" 
                  placeholder="Summarize the legal parameters of this reference..."
                ></textarea>
              </div>

              {/* REFERENCE SPECIFIC */}
              {entryType === "reference" && (
                <>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Category</label>
                      <input 
                        type="text" 
                        required
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                        className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                        placeholder="e.g. Labor Standard"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Source Type</label>
                      <input 
                        type="text" 
                        required
                        value={sourceType}
                        onChange={(e) => setSourceType(e.target.value)}
                        className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                        placeholder="e.g. DOLE"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Official URL</label>
                      <input 
                        type="url" 
                        value={officialUrl}
                        onChange={(e) => setOfficialUrl(e.target.value)}
                        className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                        placeholder="https://example.gov.ph/advisory"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Effective Date</label>
                      <input 
                        type="date" 
                        value={effectiveDate}
                        onChange={(e) => setEffectiveDate(e.target.value)}
                        className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 [color-scheme:dark]" 
                      />
                    </div>
                  </div>
                </>
              )}

              {/* PRECEDENT SPECIFIC */}
              {entryType === "precedent" && (
                <>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Case Type</label>
                      <input 
                        type="text" 
                        required
                        value={caseType}
                        onChange={(e) => setCaseType(e.target.value)}
                        className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                        placeholder="e.g. SC Jurisprudence"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Risk Level</label>
                      <select 
                        value={riskLevel}
                        onChange={(e) => setRiskLevel(e.target.value as any)}
                        className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50"
                      >
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                        <option value="Critical">Critical</option>
                      </select>
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Source Reference / Case Citation</label>
                    <input 
                      type="text" 
                      required
                      value={sourceReference}
                      onChange={(e) => setSourceReference(e.target.value)}
                      className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                      placeholder="e.g. G.R. No. 123456 (2020)"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Key Principles</label>
                    <textarea 
                      required
                      value={keyPrinciples}
                      onChange={(e) => setKeyPrinciples(e.target.value)}
                      rows={2}
                      className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 resize-none" 
                      placeholder="Core legal standards established by the SC in this case..."
                    ></textarea>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Recommended HR Process</label>
                    <textarea 
                      required
                      value={recommendedProcess}
                      onChange={(e) => setRecommendedProcess(e.target.value)}
                      rows={2}
                      className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 resize-none" 
                      placeholder="What should HR do operationally based on this ruling..."
                    ></textarea>
                  </div>
                </>
              )}

              <div className="pt-2 flex justify-end gap-3 border-t border-white/5 mt-4">
                <button 
                  type="button" 
                  onClick={() => setShowAddModal(false)} 
                  className="px-3 py-1.5 text-gray-400 hover:text-white text-xs font-semibold"
                >
                  Cancel
                </button>
                <button 
                  type="submit" 
                  disabled={submitting}
                  className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-bold rounded-lg text-xs transition-colors flex items-center gap-1.5"
                >
                  {submitting && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                  Submit Entry
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
