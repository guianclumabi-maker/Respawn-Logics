import React, { useState, useEffect } from "react";
import { apiFetch } from "../../../../lib/apiClient";
import { 
  Calendar,
  Search,
  Filter,
  FileText,
  Clock,
  Printer,
  X,
  AlertCircle,
  Eye,
  Bot,
  User,
  LayoutDashboard
} from "lucide-react";

interface DailyReportSummary {
  total_filed: number;
  auto_count: number;
  manual_count: number;
  by_pipeline: Record<string, number>;
  by_department: Record<string, number>;
}

interface Card {
  id: number;
  pipeline_id: number;
  stage_id: number;
  employee_id: string;
  full_name: string;
  department: string;
  doc_count: number;
  created_at: string;
  source: string;
  pipeline_name: string;
  stage_name: string;
}

interface GeneratedDoc {
  id: number;
  card_id: number;
  template_name: string;
  doc_type: string;
  body: string;
  created_at: string;
}

interface Transition {
  id: number;
  from_stage: string | null;
  to_stage: string;
  created_at: string;
  user_name: string;
}

export function ELRDailyReport() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [summary, setSummary] = useState<DailyReportSummary | null>(null);
  const [cases, setCases] = useState<Card[]>([]);

  // Filters
  const [dateRange, setDateRange] = useState(() => {
    const today = new Date().toISOString().split('T')[0];
    return today;
  });
  const [search, setSearch] = useState("");
  const [sourceFilter, setSourceFilter] = useState("");
  const [deptFilter, setDeptFilter] = useState("");
  const [pipelineFilter, setPipelineFilter] = useState("");

  // Options for dropdowns derived from data
  const [departments, setDepartments] = useState<string[]>([]);
  const [pipelines, setPipelines] = useState<string[]>([]);

  // Card Details Modal
  const [selectedCardId, setSelectedCardId] = useState<number | null>(null);
  const [cardDetails, setCardDetails] = useState<{
    card: Card,
    documents: GeneratedDoc[],
    transitions: Transition[]
  } | null>(null);
  const [detailsLoading, setDetailsLoading] = useState(false);

  useEffect(() => {
    fetchReport();
  }, [dateRange]);

  const fetchReport = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch(`/api/index.php?route=elr_pipeline&action=daily_report&date=${dateRange}`);
      const data = await res.json();
      
      if (data.success) {
        setSummary(data.summary);
        setCases(data.cases || []);
        
        // Extract unique filters
        const depts = new Set<string>();
        const pipes = new Set<string>();
        (data.cases || []).forEach((c: Card) => {
          if (c.department) depts.add(c.department);
          if (c.pipeline_name) pipes.add(c.pipeline_name);
        });
        setDepartments(Array.from(depts));
        setPipelines(Array.from(pipes));
        
      } else {
        setError(data.error || "Failed to load report");
      }
    } catch (err) {
      setError("Unable to load daily report.");
    } finally {
      setLoading(false);
    }
  };

  const fetchCardDetails = async (id: number) => {
    setDetailsLoading(true);
    setSelectedCardId(id);
    setCardDetails(null);
    try {
      const res = await apiFetch(`/api/index.php?route=elr_pipeline&action=card&id=${id}`);
      const data = await res.json();
      if (data.success) {
        setCardDetails(data.data);
      } else {
        setError(data.error || "Failed to fetch card details");
        setSelectedCardId(null);
      }
    } catch (err) {
      setError("Unable to load card details");
      setSelectedCardId(null);
    } finally {
      setDetailsLoading(false);
    }
  };

  const handlePrint = (body: string, title: string) => {
    const printWindow = window.open('', '_blank');
    if (printWindow) {
      printWindow.document.write(`
        <html>
          <head>
            <title>${title}</title>
            <style>
              body { font-family: Arial, sans-serif; padding: 40px; line-height: 1.6; white-space: pre-wrap; }
            </style>
          </head>
          <body>
            ${body}
            <script>window.onload = function() { window.print(); window.close(); }</script>
          </body>
        </html>
      `);
      printWindow.document.close();
    }
  };

  // Filtered cases
  const filteredCases = cases.filter(c => {
    const matchesSearch = c.full_name.toLowerCase().includes(search.toLowerCase()) || 
                          c.employee_id.toLowerCase().includes(search.toLowerCase());
    const matchesSource = sourceFilter === "" || c.source === sourceFilter;
    const matchesDept = deptFilter === "" || c.department === deptFilter;
    const matchesPipeline = pipelineFilter === "" || c.pipeline_name === pipelineFilter;
    
    return matchesSearch && matchesSource && matchesDept && matchesPipeline;
  });

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-[#06070a] text-[#c8d0e0]">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-white/5 bg-[#161922]/50 backdrop-blur-md">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-white mb-1 font-['Space_Grotesk']">
              Daily Digest Report
            </h1>
            <p className="text-sm text-gray-400">Review newly filed cases and automation outcomes.</p>
          </div>
          <div className="flex items-center gap-3">
            <div className="relative">
              <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" size={15} />
              <input 
                type="date"
                value={dateRange}
                onChange={(e) => setDateRange(e.target.value)}
                className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 pl-9 pr-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50"
              />
            </div>
            <button 
              onClick={() => window.print()}
              className="p-2.5 bg-white/5 hover:bg-white/10 rounded-lg text-white transition-colors"
              title="Print Digest"
            >
              <Printer size={16} />
            </button>
          </div>
        </div>
      </div>

      {/* Main Container */}
      <div className="flex-1 overflow-auto p-8 space-y-6 scrollbar-thin">
        
        {loading ? (
          <div className="flex justify-center py-20"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00e07a]"></div></div>
        ) : error ? (
          <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm flex items-start gap-3">
            <AlertCircle className="w-5 h-5 flex-shrink-0 mt-0.5" />
            <span>{error}</span>
          </div>
        ) : (
          <>
            {/* Summary Header */}
            {summary && (
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div className="bg-[#161922]/70 border border-white/5 rounded-xl p-5 shadow-lg relative overflow-hidden">
                  <div className="absolute top-0 left-0 w-1 h-full bg-[#00e07a]"></div>
                  <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Total Filed Today</h3>
                  <div className="text-3xl font-bold text-white font-mono">{summary.total_filed}</div>
                </div>
                
                <div className="bg-[#161922]/70 border border-white/5 rounded-xl p-5 shadow-lg relative overflow-hidden">
                  <div className="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
                  <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Automated (System)</h3>
                  <div className="text-3xl font-bold text-blue-400 font-mono">{summary.auto_count}</div>
                </div>
                
                <div className="bg-[#161922]/70 border border-white/5 rounded-xl p-5 shadow-lg relative overflow-hidden">
                  <div className="absolute top-0 left-0 w-1 h-full bg-purple-500"></div>
                  <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Manual Filing</h3>
                  <div className="text-3xl font-bold text-purple-400 font-mono">{summary.manual_count}</div>
                </div>

                <div className="bg-[#161922]/70 border border-white/5 rounded-xl p-4 shadow-lg overflow-hidden flex flex-col justify-center">
                  <h3 className="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2 border-b border-white/5 pb-1">Top Departments</h3>
                  <div className="space-y-1.5 text-xs">
                    {Object.entries(summary.by_department).slice(0, 3).map(([dept, count]) => (
                      <div key={dept} className="flex justify-between text-gray-300">
                        <span className="truncate pr-2">{dept}</span>
                        <span className="font-bold text-white bg-white/10 px-1.5 rounded">{count}</span>
                      </div>
                    ))}
                    {Object.keys(summary.by_department).length === 0 && (
                      <span className="text-gray-500 italic">No data</span>
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* Filters */}
            <div className="bg-[#161922]/40 border border-white/5 p-4 rounded-xl flex flex-wrap gap-4 items-center justify-between shadow-sm">
              <div className="flex flex-wrap gap-3 items-center flex-1">
                <div className="relative w-64">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" size={15} />
                  <input 
                    type="text"
                    placeholder="Search employee..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 pl-9 pr-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50"
                  />
                </div>

                <select
                  value={sourceFilter}
                  onChange={(e) => setSourceFilter(e.target.value)}
                  className="bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50"
                >
                  <option value="">All Sources</option>
                  <option value="system_auto">Automated (System)</option>
                  <option value="manual">Manual Entry</option>
                </select>

                <select
                  value={pipelineFilter}
                  onChange={(e) => setPipelineFilter(e.target.value)}
                  className="bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50"
                >
                  <option value="">All Pipelines</option>
                  {pipelines.map(p => <option key={p} value={p}>{p}</option>)}
                </select>

                <select
                  value={deptFilter}
                  onChange={(e) => setDeptFilter(e.target.value)}
                  className="bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50"
                >
                  <option value="">All Departments</option>
                  {departments.map(d => <option key={d} value={d}>{d}</option>)}
                </select>
              </div>
              <div className="text-xs text-gray-500 font-sans flex items-center gap-2">
                <Filter size={12} /> Showing {filteredCases.length} case{filteredCases.length !== 1 ? "s" : ""}
              </div>
            </div>

            {/* Table */}
            <div className="bg-[#161922]/70 border border-white/5 rounded-xl overflow-hidden shadow-2xl">
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead className="bg-black/25 text-gray-500 text-xs font-semibold uppercase tracking-wider">
                    <tr>
                      <th className="py-4 px-6">Employee</th>
                      <th className="py-4 px-6">Department</th>
                      <th className="py-4 px-6">Pipeline / Incident Type</th>
                      <th className="py-4 px-6">Current Stage</th>
                      <th className="py-4 px-6 text-center">Source</th>
                      <th className="py-4 px-6 text-center">Time</th>
                      <th className="py-4 px-6 text-center">Docs</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-white/[0.03]">
                    {filteredCases.map((c) => (
                      <tr 
                        key={c.id} 
                        onClick={() => fetchCardDetails(c.id)}
                        className="hover:bg-white/[0.02] transition-colors cursor-pointer group"
                      >
                        <td className="py-4 px-6">
                          <div className="font-bold text-white group-hover:text-[#00e07a] transition-colors">{c.full_name}</div>
                          <div className="text-[10px] text-gray-500 font-mono mt-0.5">{c.employee_id}</div>
                        </td>
                        <td className="py-4 px-6 text-xs text-gray-300">{c.department}</td>
                        <td className="py-4 px-6 text-xs font-bold text-gray-300">{c.pipeline_name}</td>
                        <td className="py-4 px-6 text-xs text-gray-400">
                          <span className="bg-white/5 px-2 py-1 rounded border border-white/10">{c.stage_name}</span>
                        </td>
                        <td className="py-4 px-6 text-center">
                          {c.source === 'system_auto' ? (
                            <span className="inline-flex items-center gap-1.5 px-2 py-1 rounded text-[10px] font-bold uppercase border bg-blue-500/10 text-blue-400 border-blue-500/20">
                              <Bot size={12} /> Auto
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1.5 px-2 py-1 rounded text-[10px] font-bold uppercase border bg-purple-500/10 text-purple-400 border-purple-500/20">
                              <User size={12} /> Manual
                            </span>
                          )}
                        </td>
                        <td className="py-4 px-6 text-center text-[11px] text-gray-500">
                          {new Date(c.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </td>
                        <td className="py-4 px-6 text-center">
                          <div className="flex items-center justify-center gap-1 text-[11px] font-bold text-gray-400">
                            <FileText size={12} /> {c.doc_count || 0}
                          </div>
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
          </>
        )}
      </div>

      {/* Card Detail Drawer */}
      {selectedCardId && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex justify-end">
          <div className="w-[500px] bg-[#0f1422] h-full shadow-2xl border-l border-[#2a2d36] flex flex-col animate-in slide-in-from-right">
            <div className="p-5 border-b border-[#2a2d36] flex justify-between items-center bg-[#0b0f1a]">
              <h2 className="text-lg font-bold font-['Space_Grotesk'] text-white">Case Details</h2>
              <button onClick={() => setSelectedCardId(null)} className="text-gray-400 hover:text-white p-1"><X size={20} /></button>
            </div>
            
            <div className="flex-1 overflow-y-auto p-6 space-y-8 scrollbar-thin">
              {detailsLoading || !cardDetails ? (
                <div className="flex justify-center py-10"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00e07a]"></div></div>
              ) : (
                <>
                  {/* Employee Info */}
                  <div>
                    <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3 border-b border-white/5 pb-2">Employee Information</h3>
                    <div className="flex items-center gap-4 mb-4">
                      <div className="w-12 h-12 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center font-bold text-lg">
                        {cardDetails.card.full_name.charAt(0)}
                      </div>
                      <div>
                        <div className="font-bold text-white text-lg">{cardDetails.card.full_name}</div>
                        <div className="text-sm font-mono text-gray-500">{cardDetails.card.employee_id} • {cardDetails.card.department}</div>
                      </div>
                    </div>
                  </div>

                  {/* Documents */}
                  <div>
                    <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3 border-b border-white/5 pb-2 flex items-center gap-2"><FileText size={14}/> Generated Documents ({cardDetails.documents.length})</h3>
                    {cardDetails.documents.length === 0 ? (
                      <p className="text-sm text-gray-400 italic">No documents generated yet.</p>
                    ) : (
                      <div className="space-y-3">
                        {cardDetails.documents.map(doc => (
                          <div key={doc.id} className="bg-[#161922] border border-[#2a2d36] rounded-xl overflow-hidden">
                            <div className="px-4 py-3 border-b border-[#2a2d36] flex justify-between items-center bg-[#1a1f2e]">
                              <div>
                                <div className="font-bold text-sm text-white">{doc.template_name}</div>
                                <div className="text-[10px] text-gray-400 font-mono mt-0.5">{new Date(doc.created_at).toLocaleString()} • {doc.doc_type}</div>
                              </div>
                              <button 
                                onClick={() => handlePrint(doc.body, doc.template_name)}
                                className="p-1.5 bg-white/5 hover:bg-white/10 rounded text-gray-300 transition-colors"
                                title="Print / PDF"
                              >
                                <Printer size={16} />
                              </button>
                            </div>
                            <div className="p-4 max-h-[200px] overflow-y-auto scrollbar-thin bg-[#0b0f1a] font-mono text-[11px] text-gray-300 whitespace-pre-wrap leading-relaxed">
                              {doc.body}
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>

                  {/* Timeline */}
                  <div>
                    <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3 border-b border-white/5 pb-2 flex items-center gap-2"><Clock size={14}/> Transition Timeline</h3>
                    <div className="relative pl-3 border-l-2 border-[#2a2d36] space-y-4 mt-4 ml-2">
                      {cardDetails.transitions.map(trx => (
                        <div key={trx.id} className="relative">
                          <div className="absolute -left-[17px] top-1 w-3 h-3 bg-[#0f1422] border-2 border-[#00e07a] rounded-full"></div>
                          <div className="text-sm font-medium text-white">
                            Moved to <span className="text-[#00e07a]">{trx.to_stage}</span>
                          </div>
                          <div className="text-[11px] text-gray-500 mt-1">
                            {new Date(trx.created_at).toLocaleString()} • by {trx.user_name}
                          </div>
                        </div>
                      ))}
                      <div className="relative">
                        <div className="absolute -left-[17px] top-1 w-3 h-3 bg-[#0f1422] border-2 border-blue-400 rounded-full"></div>
                        <div className="text-sm font-medium text-white">Case Created ({cardDetails.card.source === 'system_auto' ? 'Automated' : 'Manual'})</div>
                        <div className="text-[11px] text-gray-500 mt-1">
                          {new Date(cardDetails.card.created_at).toLocaleString()}
                        </div>
                      </div>
                    </div>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      )}

    </div>
  );
}
