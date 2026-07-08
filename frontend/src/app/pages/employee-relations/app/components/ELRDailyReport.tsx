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
import { ELRCaseDrawer } from "./ELRCaseDrawer";

interface DailyReportSummary {
  total: number;
  auto: number;
  manual: number;
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
  entered_via: string;
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
  const [filters, setFilters] = useState(() => {
    const today = new Date().toISOString().split('T')[0];
    return { start_date: today, end_date: today };
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

  useEffect(() => {
    fetchReport();
  }, [filters.start_date, filters.end_date]);

  const fetchReport = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch(`/api/index.php?route=elr_pipeline&action=daily_report&start_date=${filters.start_date}&end_date=${filters.end_date}`);
      const data = await res.json();
      
      if (data.success) {
        setSummary(data.summary);
        setCases(data.cards || []);
        
        // Extract unique filters
        const depts = new Set<string>();
        const pipes = new Set<string>();
        (data.cards || []).forEach((c: Card) => {
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
    if (sourceFilter && c.entered_via !== sourceFilter) return false;
    const matchesDept = deptFilter === "" || c.department === deptFilter;
    const matchesPipeline = pipelineFilter === "" || c.pipeline_name === pipelineFilter;
    
    return matchesSearch && matchesDept && matchesPipeline;
  });

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-background text-foreground">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-border bg-card text-card-foreground/50 backdrop-blur-md">
        <div className="flex justify-between items-start">
          <div>
            <h1 className="text-2xl font-bold text-foreground mb-1 font-['Space_Grotesk']">
              Daily Digest Report
            </h1>
            <p className="text-sm text-muted-foreground">Review newly filed cases and automation outcomes.</p>
          </div>
          <div className="flex items-center gap-3">
            <div className="relative flex gap-2">
                <div>
                  <label className="block text-[10px] font-bold text-gray-500 uppercase mb-1">Start Date</label>
                  <input 
                    type="date" 
                    value={filters.start_date}
                    onChange={e => setFilters({...filters, start_date: e.target.value})}
                    className="w-full bg-background border border-border rounded px-3 py-1.5 text-xs focus:outline-none focus:border-[#00e07a] text-foreground"
                  />
                </div>
                <div>
                  <label className="block text-[10px] font-bold text-gray-500 uppercase mb-1">End Date</label>
                  <input 
                    type="date" 
                    value={filters.end_date}
                    onChange={e => setFilters({...filters, end_date: e.target.value})}
                    className="w-full bg-background border border-border rounded px-3 py-1.5 text-xs focus:outline-none focus:border-[#00e07a] text-foreground"
                  />
                </div>
            </div>
            <button 
              onClick={() => window.print()}
              className="p-2.5 bg-white/5 hover:bg-accent rounded-lg text-foreground transition-colors"
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
                <div className="bg-card text-card-foreground/70 border border-border rounded-xl p-5 shadow-lg relative overflow-hidden">
                  <div className="absolute top-0 left-0 w-1 h-full bg-[#00e07a]"></div>
                  <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Total Filed Today</h3>
                  <div className="text-2xl font-bold font-mono text-white mb-1">{summary.total}</div>
                </div>
                
                <div className="bg-card text-card-foreground/70 border border-border rounded-xl p-5 shadow-lg relative overflow-hidden">
                  <div className="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
                  <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Automated (System)</h3>
                  <div className="text-2xl font-bold font-mono text-white mb-1">{summary.auto}</div>
                </div>
                
                <div className="bg-card text-card-foreground/70 border border-border rounded-xl p-5 shadow-lg relative overflow-hidden">
                  <div className="absolute top-0 left-0 w-1 h-full bg-purple-500"></div>
                  <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Manual Filing</h3>
                  <div className="text-2xl font-bold font-mono text-white mb-1">{summary.manual}</div>
                </div>

                <div className="bg-card text-card-foreground/70 border border-border rounded-xl p-4 shadow-lg overflow-hidden flex flex-col justify-center">
                  <h3 className="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2 border-b border-border pb-1">Top Departments</h3>
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
            <div className="bg-card text-card-foreground/40 border border-border p-4 rounded-xl flex flex-wrap gap-4 items-center justify-between shadow-sm">
              <div className="flex flex-wrap gap-3 items-center flex-1">
                <div className="relative w-64">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" size={15} />
                  <input 
                    type="text"
                    placeholder="Search employee..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="w-full bg-background border border-border rounded-lg py-2 pl-9 pr-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50"
                  />
                </div>

                <select
                  value={sourceFilter}
                  onChange={(e) => setSourceFilter(e.target.value)}
                  className="bg-background border border-border rounded-lg py-2 px-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50"
                >
                  <option value="">All Sources</option>
                  <option value="auto">Automated (System)</option>
                  <option value="manual">Manual Entry</option>
                </select>

                <select
                  value={pipelineFilter}
                  onChange={(e) => setPipelineFilter(e.target.value)}
                  className="bg-background border border-border rounded-lg py-2 px-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50"
                >
                  <option value="">All Pipelines</option>
                  {pipelines.map(p => <option key={p} value={p}>{p}</option>)}
                </select>

                <select
                  value={deptFilter}
                  onChange={(e) => setDeptFilter(e.target.value)}
                  className="bg-background border border-border rounded-lg py-2 px-3 text-sm text-white focus:outline-none focus:border-[#00e07a]/50"
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
            <div className="bg-card text-card-foreground/70 border border-border rounded-xl overflow-hidden shadow-2xl">
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
                        onClick={() => setSelectedCardId(c.id)}
                        className="hover:bg-white/[0.02] transition-colors cursor-pointer group"
                      >
                        <td className="py-4 px-6">
                          <div className="font-bold text-white group-hover:text-[#00e07a] transition-colors">{c.full_name}</div>
                          <div className="text-[10px] text-gray-500 font-mono mt-0.5">{c.employee_id}</div>
                        </td>
                        <td className="py-4 px-6 text-xs text-gray-300">{c.department}</td>
                        <td className="py-4 px-6 text-xs font-bold text-gray-300">{c.pipeline_name}</td>
                        <td className="py-4 px-6 text-xs text-muted-foreground">
                          <span className="bg-white/5 px-2 py-1 rounded border border-border">{c.stage_name}</span>
                        </td>
                        <td className="py-4 px-6 text-center">
                          <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${c.entered_via === 'auto' ? 'bg-purple-500/10 text-purple-400 border border-purple-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20'}`}>
                            {c.entered_via === 'auto' ? 'Auto-detected' : 'Manual'}
                          </span>
                        </td>
                        <td className="py-4 px-6 text-center text-[11px] text-gray-500">
                          {new Date(c.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </td>
                        <td className="py-4 px-6 text-center">
                          <div className="flex items-center justify-center gap-1 text-[11px] font-bold text-muted-foreground">
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

      {selectedCardId && (
        <ELRCaseDrawer 
          cardId={selectedCardId} 
          onClose={() => setSelectedCardId(null)} 
        />
      )}

    </div>
  );
}
