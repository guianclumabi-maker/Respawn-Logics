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
  Compass,
  Globe,
  Search,
  Sparkles
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

  const [activeTab, setActiveTab] = useState<"references" | "precedents" | "search_sources">("references");
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

  // External Search States
  const [sourcesList, setSourcesList] = useState<{ id: string; label: string; domain: string }[]>([]);
  const [selectedSources, setSelectedSources] = useState<string[]>([]);
  const [searchQuery, setSearchQuery] = useState("");
  const [searchCategory, setSearchCategory] = useState("");
  const [searching, setSearching] = useState(false);
  const [candidates, setCandidates] = useState<any[]>([]);
  const [searchError, setSearchError] = useState<string | null>(null);
  const [searchSuccess, setSearchSuccess] = useState<string | null>(null);
  const [ingestingIndex, setIngestingIndex] = useState<number | null>(null);

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

  const fetchSources = async () => {
    try {
      const res = await apiFetch("/api/index.php?route=elr&action=kb_sources");
      if (res.ok) {
        const data = await res.json();
        if (data.success) {
          setSourcesList(data.sources || []);
          setSelectedSources((data.sources || []).map((s: any) => s.id));
        }
      }
    } catch (err) {
      console.error("Failed to load sources whitelist:", err);
    }
  };

  useEffect(() => {
    fetchData();
    if (isSuperAdmin) {
      fetchSources();
    }
  }, [isSuperAdmin]);

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

  const handleSearchSources = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!searchQuery.trim()) return;

    setSearching(true);
    setSearchError(null);
    setSearchSuccess(null);
    setCandidates([]);

    try {
      const res = await apiFetch("/api/index.php?route=elr&action=kb_search", {
        method: "POST",
        body: JSON.stringify({
          query: searchQuery,
          sources: selectedSources,
          category: searchCategory
        })
      });

      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data = await res.json();

      if (data.success) {
        setCandidates(data.candidates || []);
        if ((data.candidates || []).length === 0) {
          setSearchError("No candidates returned. Gemini response: " + (data.raw || "No matches."));
        } else {
          setSearchSuccess(`Located ${data.candidates.length} authoritative candidate document(s).`);
        }
      } else {
        setSearchError(data.error || "Failed to complete external search.");
      }
    } catch (err: any) {
      console.error(err);
      setSearchError(err.message || "An error occurred during search query resolution.");
    } finally {
      setSearching(false);
    }
  };

  const handleIngest = async (cand: any, index: number) => {
    setIngestingIndex(index);
    setSearchError(null);
    setSearchSuccess(null);

    const payload = {
      entry_type: cand.entry_type || "reference",
      title: cand.title,
      summary: cand.summary,
      suggested_category: cand.suggested_category || cand.category || "General",
      source_type: cand.source_type || "Web Ingest",
      official_url: cand.official_url,
      key_principles: cand.key_principles,
      risk_level: cand.risk_level || "Medium",
      recommended_process: cand.recommended_process
    };

    try {
      const res = await apiFetch("/api/index.php?route=elr&action=kb_ingest", {
        method: "POST",
        body: JSON.stringify(payload)
      });

      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data = await res.json();

      if (data.success) {
        setSearchSuccess(`Successfully ingested "${cand.title}" as PENDING. Approved items will be used by AI.`);
        setCandidates(prev => prev.map((c, i) => i === index ? { ...c, ingested: true } : c));
        fetchData();
      } else {
        setSearchError(data.error || "Failed to stage candidate.");
      }
    } catch (err: any) {
      console.error(err);
      setSearchError(err.message || "An error occurred during staging operations.");
    } finally {
      setIngestingIndex(null);
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

  const toggleSourceSelection = (sid: string) => {
    setSelectedSources(prev => 
      prev.includes(sid) ? prev.filter(x => x !== sid) : [...prev, sid]
    );
  };

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-background text-foreground">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-border bg-card text-card-foreground/50 backdrop-blur-md">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-foreground mb-1 font-['Space_Grotesk']">
              Knowledge Base Review
            </h1>
            <p className="text-sm text-muted-foreground">Review statutory DOLE advisories and SC labor jurisprudence</p>
          </div>
          {isSuperAdmin && (
            <button 
              onClick={() => {
                setFormError(null);
                setShowAddModal(true);
              }}
              className="px-4 py-2 bg-gradient-to-r from-[#00e07a] to-[#00b8ff] text-black font-bold border-none rounded-lg text-sm hover:opacity-90 transition-opacity shadow-[0_0_15px_rgba(0,224,122,0.3)] flex items-center gap-2 cursor-pointer"
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
          <div className="p-4 bg-blue-500/5 border border-blue-500/10 rounded-xl text-xs text-muted-foreground flex items-center gap-2.5">
            <Lock className="w-4 h-4 text-blue-400 flex-shrink-0" />
            <span>Read-Only View: Only platform administrators can write entries or approve/reject pending references.</span>
          </div>
        )}

        {loading ? (
          <div className="flex flex-col items-center justify-center py-20 gap-3 text-muted-foreground">
            <Loader2 className="w-8 h-8 animate-spin text-[#00e07a]" />
            <p className="text-sm font-medium">Scanning compliance references...</p>
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center py-16 px-6 bg-red-500/10 border border-red-500/20 rounded-xl max-w-xl mx-auto text-center space-y-3">
            <AlertCircle className="w-10 h-10 text-red-500" />
            <h3 className="text-lg font-bold text-white">Load Error</h3>
            <p className="text-sm text-muted-foreground">{error}</p>
            <button 
              onClick={fetchData}
              className="mt-2 px-4 py-2 bg-white/5 hover:bg-accent text-white rounded-lg text-xs transition-colors border border-border cursor-pointer"
            >
              Retry
            </button>
          </div>
        ) : (
          <>
            {/* Tabs */}
            <div className="flex gap-4 border-b border-border">
              <button 
                onClick={() => setActiveTab("references")}
                className={`pb-3 px-1 text-sm font-medium transition-colors flex items-center gap-2 cursor-pointer ${
                  activeTab === "references" ? "text-foreground border-b-2 border-[#00e07a]" : "text-gray-500 hover:text-gray-300"
                }`}
              >
                <BookOpen size={16} /> Labor References ({references.length})
              </button>
              <button 
                onClick={() => setActiveTab("precedents")}
                className={`pb-3 px-1 text-sm font-medium transition-colors flex items-center gap-2 cursor-pointer ${
                  activeTab === "precedents" ? "text-foreground border-b-2 border-[#00e07a]" : "text-gray-500 hover:text-gray-300"
                }`}
              >
                <Scale size={16} /> Legal Precedents ({precedents.length})
              </button>
              {isSuperAdmin && (
                <button 
                  onClick={() => setActiveTab("search_sources")}
                  className={`pb-3 px-1 text-sm font-medium transition-colors flex items-center gap-2 cursor-pointer ${
                    activeTab === "search_sources" ? "text-foreground border-b-2 border-[#00e07a]" : "text-gray-500 hover:text-gray-300"
                  }`}
                >
                  <Compass size={16} /> Search Sources
                </button>
              )}
            </div>

            {/* TAB CONTENT: REFERENCES */}
            {activeTab === "references" && (
              <div className="bg-card text-card-foreground/70 border border-border rounded-xl overflow-hidden shadow-2xl">
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
                          <td className="py-4 px-6 text-sm text-muted-foreground max-w-md line-clamp-3" title={ref.summary}>
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
                                    className="p-1.5 bg-[#00e07a]/15 text-[#00e07a] border border-[#00e07a]/25 rounded hover:bg-[#00e07a]/25 cursor-pointer"
                                    title="Approve Reference"
                                  >
                                    <Check size={14} />
                                  </button>
                                  <button 
                                    onClick={() => handleApproveReject(ref.id, "Rejected")}
                                    className="p-1.5 bg-red-500/15 text-red-500 border border-red-500/25 rounded hover:bg-red-500/25 cursor-pointer"
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
              <div className="bg-card text-card-foreground/70 border border-border rounded-xl overflow-hidden shadow-2xl">
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
                            <div className="text-xs text-muted-foreground mt-2 line-clamp-3">{prec.summary}</div>
                          </td>
                          <td className="py-4 px-6 text-sm text-gray-300 max-w-xs font-sans" title={prec.key_principles}>
                            <p className="line-clamp-4">{prec.key_principles}</p>
                          </td>
                          <td className="py-4 px-6 text-xs text-muted-foreground max-w-xs font-sans" title={prec.recommended_process}>
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

            {/* TAB CONTENT: SEARCH SOURCES */}
            {activeTab === "search_sources" && isSuperAdmin && (
              <div className="space-y-6">
                
                {/* Search Form Panel */}
                <div className="bg-card text-card-foreground/70 border border-border p-6 rounded-2xl shadow-xl space-y-4">
                  <div className="flex items-center gap-2 mb-2">
                    <Globe size={18} className="text-[#00e07a]" />
                    <h3 className="text-sm font-bold text-white uppercase tracking-wider">Search Authoritative Web Corpus</h3>
                  </div>

                  <form onSubmit={handleSearchSources} className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Query / Topic</label>
                        <div className="relative">
                          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" size={14} />
                          <input 
                            type="text"
                            required
                            placeholder="e.g. Twin notice rule termination procedure..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full bg-background border border-border rounded-lg py-2.5 pl-9 pr-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50"
                          />
                        </div>
                      </div>
                      <div>
                        <label className="block text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Focus Category (Optional)</label>
                        <input 
                          type="text"
                          placeholder="e.g. Dismissals, Maternity, Overtime Pay..."
                          value={searchCategory}
                          onChange={(e) => setSearchCategory(e.target.value)}
                          className="w-full bg-background border border-border rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50"
                        />
                      </div>
                    </div>

                    {/* Checkboxes Whitelist */}
                    <div>
                      <label className="block text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1.5">Search Restrictions (Curated Official Domains)</label>
                      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 pt-1">
                        {sourcesList.map(src => {
                          const checked = selectedSources.includes(src.id);
                          return (
                            <label 
                              key={src.id} 
                              className={`flex items-center gap-2 px-3 py-2 border rounded-xl cursor-pointer select-none transition-all text-xs ${
                                checked 
                                  ? "bg-[#00e07a]/10 border-[#00e07a]/35 text-[#00e07a]" 
                                  : "bg-black/10 border-border text-muted-foreground hover:text-foreground"
                              }`}
                            >
                              <input 
                                type="checkbox"
                                checked={checked}
                                onChange={() => toggleSourceSelection(src.id)}
                                className="hidden"
                              />
                              <span>{src.label}</span>
                            </label>
                          );
                        })}
                      </div>
                    </div>

                    <div className="pt-2 flex justify-end">
                      <button
                        type="submit"
                        disabled={searching}
                        className="px-5 py-2.5 bg-gradient-to-r from-[#00e07a] to-[#00b8ff] text-black font-extrabold rounded-lg text-xs hover:opacity-95 transition-opacity shadow-[0_0_15px_rgba(0,224,122,0.25)] flex items-center gap-2 cursor-pointer disabled:opacity-50"
                      >
                        {searching ? (
                          <>
                            <Loader2 size={14} className="animate-spin" /> Querying Grounded LLM...
                          </>
                        ) : (
                          <>
                            <Sparkles size={14} /> Run Source Search
                          </>
                        )}
                      </button>
                    </div>
                  </form>
                </div>

                {/* Feedback notes */}
                {searchError && (
                  <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm flex items-start gap-3">
                    <AlertCircle className="w-5 h-5 flex-shrink-0" />
                    <span>{searchError}</span>
                  </div>
                )}
                {searchSuccess && (
                  <div className="p-4 bg-[#00e07a]/10 border border-[#00e07a]/20 rounded-xl text-[#00e07a] text-sm flex items-start gap-3">
                    <Check className="w-5 h-5 flex-shrink-0" />
                    <span>{searchSuccess}</span>
                  </div>
                )}

                {/* Candidate List Cards */}
                {candidates.length > 0 && (
                  <div className="space-y-4">
                    <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider">Candidate Documents Retrieved</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                      {candidates.map((cand, idx) => (
                        <div key={idx} className="bg-card text-card-foreground/70 border border-border rounded-2xl p-5 flex flex-col justify-between hover:border-border transition-all relative overflow-hidden">
                          {cand.ingested && (
                            <div className="absolute top-3 right-3 px-2 py-0.5 bg-[#00e07a]/15 text-[#00e07a] border border-[#00e07a]/25 rounded text-[9px] font-bold uppercase">
                              Staged
                            </div>
                          )}
                          <div className="space-y-3">
                            <div className="flex flex-wrap gap-2 items-center">
                              <span className="px-2 py-0.5 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded text-[9px] font-bold uppercase">
                                {cand.source_type || "Web Resource"}
                              </span>
                              <span className="px-2 py-0.5 bg-purple-500/10 text-purple-400 border border-purple-500/20 rounded text-[9px] font-bold uppercase">
                                {cand.suggested_category || cand.category || "General"}
                              </span>
                              <span className="text-[10px] font-bold text-gray-500 font-mono uppercase">{cand.entry_type || "reference"}</span>
                            </div>
                            <h4 className="text-sm font-bold text-white leading-snug">{cand.title}</h4>
                            <p className="text-xs text-muted-foreground leading-relaxed font-sans">{cand.summary}</p>
                          </div>

                          <div className="pt-4 border-t border-border mt-4 flex items-center justify-between gap-4">
                            {cand.official_url ? (
                              <a 
                                href={cand.official_url} 
                                target="_blank" 
                                rel="noreferrer" 
                                className="inline-flex items-center gap-1.5 text-xs text-cyan-400 hover:text-cyan-300 font-semibold"
                              >
                                Source Link <ExternalLink size={12} />
                              </a>
                            ) : (
                              <span className="text-xs text-gray-600">No URL</span>
                            )}

                            <button
                              disabled={cand.ingested || ingestingIndex !== null}
                              onClick={() => handleIngest(cand, idx)}
                              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer ${
                                cand.ingested 
                                  ? "bg-[#00e07a]/5 text-[#00e07a]/50 border border-[#00e07a]/10 cursor-default" 
                                  : "bg-[#00e07a]/15 text-[#00e07a] hover:bg-[#00e07a]/25 border border-[#00e07a]/30"
                              }`}
                            >
                              {ingestingIndex === idx && <Loader2 size={12} className="animate-spin" />}
                              {cand.ingested ? "Staged in Pending" : "Ingest as Pending"}
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

              </div>
            )}
          </>
        )}
      </div>

      {/* Add Modal */}
      {showAddModal && (
        <div className="fixed inset-0 bg-black/75 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-card text-card-foreground border border-border rounded-xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 text-foreground">
            <div className="p-5 border-b border-border flex justify-between items-center bg-black/10">
              <h3 className="text-base font-bold text-white uppercase tracking-wider">Add Knowledge Entry</h3>
              <button 
                onClick={() => setShowAddModal(false)} 
                className="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer"
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
                <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Entry Type</label>
                <div className="flex gap-4">
                  <label className="flex items-center gap-2 cursor-pointer text-sm text-foreground">
                    <input 
                      type="radio" 
                      name="entryType"
                      checked={entryType === "reference"}
                      onChange={() => setEntryType("reference")}
                      className="accent-[#00e07a]" 
                    />
                    Labor Reference (DOLE / Statutory)
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer text-sm text-foreground">
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
                <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Title</label>
                <input 
                  type="text" 
                  required
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  className="w-full bg-background border border-border rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                  placeholder="e.g. DOLE Advisory No. 17-15"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Summary / Context</label>
                <textarea 
                  required
                  value={summary}
                  onChange={(e) => setSummary(e.target.value)}
                  rows={4}
                  className="w-full bg-background border border-border rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 resize-none font-sans" 
                  placeholder="Summarize the core guidelines or implications of this advisory..."
                ></textarea>
              </div>

              {/* REFERENCE SPECIFIC */}
              {entryType === "reference" && (
                <>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Category</label>
                      <input 
                        type="text" 
                        required
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                        className="w-full bg-background border border-border rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                        placeholder="e.g. Contracting / Subcontracting"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Source Type</label>
                      <input 
                        type="text" 
                        required
                        value={sourceType}
                        onChange={(e) => setSourceType(e.target.value)}
                        className="w-full bg-background border border-border rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                        placeholder="e.g. DOLE Advisory"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Official Reference URL</label>
                      <input 
                        type="url" 
                        value={officialUrl}
                        onChange={(e) => setOfficialUrl(e.target.value)}
                        className="w-full bg-background border border-border rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                        placeholder="https://..."
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Effective Date</label>
                      <input 
                        type="date" 
                        value={effectiveDate}
                        onChange={(e) => setEffectiveDate(e.target.value)}
                        className="w-full bg-background border border-border rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 [color-scheme:dark]" 
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
                      <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Case Type</label>
                      <input 
                        type="text" 
                        required
                        value={caseType}
                        onChange={(e) => setCaseType(e.target.value)}
                        className="w-full bg-background border border-border rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                        placeholder="e.g. SC Jurisprudence"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Risk Level</label>
                      <select 
                        value={riskLevel}
                        onChange={(e) => setRiskLevel(e.target.value as any)}
                        className="w-full bg-background border border-border rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50"
                      >
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                        <option value="Critical">Critical</option>
                      </select>
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Source Reference / Case Citation</label>
                    <input 
                      type="text" 
                      required
                      value={sourceReference}
                      onChange={(e) => setSourceReference(e.target.value)}
                      className="w-full bg-background border border-border rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                      placeholder="e.g. G.R. No. 123456 (2020)"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Key Principles</label>
                    <textarea 
                      required
                      value={keyPrinciples}
                      onChange={(e) => setKeyPrinciples(e.target.value)}
                      rows={2}
                      className="w-full bg-background border border-border rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 resize-none font-sans" 
                      placeholder="Core legal standards established by the SC in this case..."
                    ></textarea>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">Recommended HR Process</label>
                    <textarea 
                      required
                      value={recommendedProcess}
                      onChange={(e) => setRecommendedProcess(e.target.value)}
                      rows={2}
                      className="w-full bg-background border border-border rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50 resize-none font-sans" 
                      placeholder="What should HR do operationally based on this ruling..."
                    ></textarea>
                  </div>
                </>
              )}

              <div className="pt-2 flex justify-end gap-3 border-t border-border mt-4">
                <button 
                  type="button" 
                  onClick={() => setShowAddModal(false)} 
                  className="px-3 py-1.5 text-muted-foreground hover:text-foreground text-xs font-semibold cursor-pointer"
                >
                  Cancel
                </button>
                <button 
                  type="submit" 
                  disabled={submitting}
                  className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-bold rounded-lg text-xs transition-colors flex items-center gap-1.5 cursor-pointer"
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
