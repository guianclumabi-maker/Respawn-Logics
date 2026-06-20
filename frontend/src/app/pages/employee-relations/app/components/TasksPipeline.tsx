import { useState, useEffect } from "react";
import { 
  Plus, Search, Filter, AlertCircle, Clock, ShieldAlert, FileText, Scale, UserMinus, CheckCircle2, 
  ChevronRight, Briefcase, Users, Zap, Award, MapPin, User, ChevronLeft 
} from "lucide-react";

const STAGES = ["Incident Report", "Under Review", "Investigation", "AWOL Notice", "Hearing", "Resolution"] as const;
type Stage = typeof STAGES[number];

type Task = {
  id: string;
  title: string;
  employee: string;
  stage: Stage;
  priority: "High" | "Medium" | "Low";
  daysInStage: number;
  assignee: string;
};

const INITIAL_TASKS: Task[] = [
  { id: "TSK-001", title: "Insubordination Report", employee: "John Doe", stage: "Incident Report", priority: "High", daysInStage: 1, assignee: "HR Admin" },
  { id: "TSK-002", title: "Habitual Tardiness", employee: "Jane Smith", stage: "Under Review", priority: "Medium", daysInStage: 2, assignee: "HR Admin" },
  { id: "TSK-003", title: "Theft Allegation", employee: "Robert Fox", stage: "Investigation", priority: "High", daysInStage: 5, assignee: "Legal Team" },
  { id: "TSK-004", title: "3 Days No Call No Show", employee: "Alice Johnson", stage: "AWOL Notice", priority: "High", daysInStage: 1, assignee: "HR Admin" },
  { id: "TSK-005", title: "Harassment Claim", employee: "Michael Chen", stage: "Hearing", priority: "High", daysInStage: 7, assignee: "External Counsel" },
  { id: "TSK-006", title: "Performance PIP Failure", employee: "Sarah Wilson", stage: "Resolution", priority: "Medium", daysInStage: 14, assignee: "HR Admin" },
];

export function TasksPipeline({ onViewChange }: { onViewChange: (v: string) => void }) {
  const [tasks, setTasks] = useState<Task[]>(INITIAL_TASKS);
  const [search, setSearch] = useState("");
  const [selectedCaseId, setSelectedCaseId] = useState<string | null>(null);
  
  const [cases, setCases] = useState<any[]>([]);
  const [dashboardSearch, setDashboardSearch] = useState("");
  const [loadingCases, setLoadingCases] = useState(true);

  useEffect(() => {
    const basePath = window.location.hostname === 'localhost' ? '/respawn-logics' : '';
    fetch(`${basePath}/api/index.php?route=esm&action=agent_queue`)
      .then(res => res.json())
      .then(data => {
        if (data.success && Array.isArray(data.data)) {
          const elrCases = data.data.filter((t: any) => t.team_name === 'Employee Relations' || t.is_confidential == 1);
          setCases(elrCases);
        }
        setLoadingCases(false);
      })
      .catch(err => {
        console.error("Error fetching cases:", err);
        setLoadingCases(false);
      });
  }, []);

  const getStageIcon = (stage: string) => {
    switch (stage) {
      case "Incident Report": return <FileText size={16} className="text-blue-500" />;
      case "Under Review": return <Search size={16} className="text-purple-500" />;
      case "Investigation": return <ShieldAlert size={16} className="text-orange-500" />;
      case "AWOL Notice": return <UserMinus size={16} className="text-red-500" />;
      case "Hearing": return <Scale size={16} className="text-yellow-500" />;
      case "Resolution": return <CheckCircle2 size={16} className="text-green-500" />;
      default: return <AlertCircle size={16} />;
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case "High": return "bg-red-500/10 text-red-500 border-red-500/20";
      case "Medium": return "bg-yellow-500/10 text-yellow-600 dark:text-yellow-500 border-yellow-500/20";
      case "Low": return "bg-green-500/10 text-green-500 border-green-500/20";
      default: return "bg-gray-500/10 text-gray-500 border-gray-500/20";
    }
  };

  const handleDragStart = (e: React.DragEvent, id: string) => {
    e.dataTransfer.setData("taskId", id);
    e.dataTransfer.effectAllowed = "move";
  };

  const handleDrop = (e: React.DragEvent, newStage: Stage) => {
    e.preventDefault();
    const taskId = e.dataTransfer.getData("taskId");
    if (taskId) {
      setTasks(prev => prev.map(t => t.id === taskId ? { ...t, stage: newStage, daysInStage: 0 } : t));
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.dataTransfer.dropEffect = "move";
  };

  if (!selectedCaseId) {
    const filteredCases = cases.filter(c => 
      !dashboardSearch || 
      (c.ticket_number && c.ticket_number.toLowerCase().includes(dashboardSearch.toLowerCase())) || 
      (c.type_name && c.type_name.toLowerCase().includes(dashboardSearch.toLowerCase())) ||
      (c.employee_name && c.employee_name.toLowerCase().includes(dashboardSearch.toLowerCase()))
    );

    const activeCases = cases.filter(c => c.status !== 'Closed' && c.status !== 'Resolved').length;
    const resolvedCases = cases.filter(c => c.status === 'Closed' || c.status === 'Resolved').length;
    const confidentialCases = cases.filter(c => c.is_confidential == 1).length;

    return (
      <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#0b0f1a] text-slate-900 dark:text-white overflow-hidden transition-colors duration-300">
        <div className="flex-1 flex flex-col items-center justify-start p-6 md:p-10 overflow-y-auto custom-scrollbar w-full">
          <div className="w-full space-y-8 mt-2 max-w-[1600px]">
            
            <div className="text-center">
              <div className="w-16 h-16 rounded-full bg-[#10b981]/10 flex items-center justify-center mx-auto mb-4">
                <ShieldAlert size={28} className="text-[#10b981]" />
              </div>
              <h2 className="text-xl font-bold mb-2 tracking-widest text-[#10b981] uppercase">[ WORKFLOW SELECTION ]</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400">Select an active case to view its resolution pipeline</p>
            </div>

            {/* Mini Dashboard */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div className="bg-white/40 dark:bg-[#1a2035]/40 border border-gray-200 dark:border-white/[0.04] rounded-xl p-5 shadow-sm flex flex-col items-center justify-center relative overflow-hidden group">
                <div className="absolute top-0 right-0 w-16 h-16 bg-[#10b981]/5 rounded-bl-full -z-10 group-hover:bg-[#10b981]/10 transition-colors"></div>
                <Briefcase size={20} className="text-[#10b981] mb-2 opacity-80" />
                <span className="text-2xl font-bold mb-1 font-sans">{cases.length}</span>
                <span className="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-widest font-sans font-bold">Total Cases</span>
              </div>
              <div className="bg-white/40 dark:bg-[#1a2035]/40 border border-gray-200 dark:border-white/[0.04] rounded-xl p-5 shadow-sm flex flex-col items-center justify-center relative overflow-hidden group">
                <div className="absolute top-0 right-0 w-16 h-16 bg-[#0ea5e9]/5 rounded-bl-full -z-10 group-hover:bg-[#0ea5e9]/10 transition-colors"></div>
                <Clock size={20} className="text-[#0ea5e9] mb-2 opacity-80" />
                <span className="text-2xl font-bold mb-1 font-sans">{activeCases}</span>
                <span className="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-widest font-sans font-bold">Active Cases</span>
              </div>
              <div className="bg-white/40 dark:bg-[#1a2035]/40 border border-gray-200 dark:border-white/[0.04] rounded-xl p-5 shadow-sm flex flex-col items-center justify-center relative overflow-hidden group">
                <div className="absolute top-0 right-0 w-16 h-16 bg-amber-500/5 rounded-bl-full -z-10 group-hover:bg-amber-500/10 transition-colors"></div>
                <ShieldAlert size={20} className="text-amber-500 mb-2 opacity-80" />
                <span className="text-2xl font-bold mb-1 font-sans">{confidentialCases}</span>
                <span className="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-widest font-sans font-bold">Confidential</span>
              </div>
              <div className="bg-white/40 dark:bg-[#1a2035]/40 border border-gray-200 dark:border-white/[0.04] rounded-xl p-5 shadow-sm flex flex-col items-center justify-center relative overflow-hidden group">
                <div className="absolute top-0 right-0 w-16 h-16 bg-green-500/5 rounded-bl-full -z-10 group-hover:bg-green-500/10 transition-colors"></div>
                <CheckCircle2 size={20} className="text-green-500 mb-2 opacity-80" />
                <span className="text-2xl font-bold mb-1 font-sans">{resolvedCases}</span>
                <span className="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-widest font-sans font-bold">Resolved</span>
              </div>
            </div>

            <div className="bg-white dark:bg-[#1a2035] border border-gray-200 dark:border-white/[0.04] rounded-xl p-6 shadow-sm w-full">
              <div className="w-full relative mb-6">
                <Search size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  value={dashboardSearch}
                  onChange={(e) => setDashboardSearch(e.target.value)}
                  placeholder="Search active cases by ticket number, type, or employee..."
                  className="w-full bg-gray-50 dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.06] rounded-lg pl-12 pr-4 py-3 text-sm focus:outline-none focus:border-[#10b981]/50 transition-colors"
                />
              </div>

              <div className="space-y-4 w-full">
                {loadingCases ? (
                  <div className="text-center py-10 text-sm text-slate-500 font-sans animate-pulse">Loading cases...</div>
                ) : filteredCases.length === 0 ? (
                  <div className="text-center py-10 text-sm text-slate-500 font-sans">No cases match your filter.</div>
                ) : (
                  filteredCases.map((c, i) => (
                    <button key={i} onClick={() => setSelectedCaseId(c.ticket_number || c.id)}
                      className="w-full flex flex-col lg:flex-row items-start lg:items-center justify-between p-5 rounded-xl border border-gray-200 dark:border-white/[0.04] bg-white dark:bg-[#0b0f1a]/50 hover:bg-gray-50 dark:hover:bg-white/[0.02] text-left hover:border-[#10b981]/40 transition-all cursor-pointer group gap-4 relative overflow-hidden">
                      
                      <div className="flex-1">
                        <div className="flex flex-wrap items-center gap-3 mb-2">
                          <span className="text-lg font-bold group-hover:text-[#10b981] transition-colors">{c.type_name || c.subject}</span>
                          {c.is_confidential == 1 && <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-red-500/10 text-red-500 border border-red-500/20 uppercase tracking-wider font-sans">Confidential</span>}
                          <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider font-sans border ${
                            c.status === 'Open' ? 'bg-[#0ea5e9]/10 text-[#0ea5e9] border-[#0ea5e9]/20' : 'bg-gray-200 dark:bg-white/[0.1] text-slate-600 dark:text-slate-300 border-transparent'
                          }`}>{c.status || 'Open'}</span>
                        </div>
                        
                        <div className="flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400 font-sans mt-2">
                          <span className="flex items-center gap-1.5"><Briefcase size={14}/> Case #{c.ticket_number}</span>
                          <span className="flex items-center gap-1.5"><User size={14}/> {c.employee_name || 'Unknown'}</span>
                          <span className="flex items-center gap-1.5"><Clock size={14}/> Opened {new Date(c.created_at).toLocaleDateString()}</span>
                        </div>
                      </div>

                      <div className="flex items-center gap-2 lg:gap-4 w-full lg:w-auto mt-2 lg:mt-0 pt-4 lg:pt-0 border-t border-gray-200 dark:border-white/[0.04] lg:border-t-0">
                        <div className="flex items-center bg-gray-50 dark:bg-[#0f1422] rounded-lg border border-gray-200 dark:border-white/[0.04] divide-x divide-gray-200 dark:divide-white/[0.04] overflow-hidden font-sans shadow-sm">
                           <div className="flex flex-col items-center px-4 py-1.5">
                            <span className="font-bold text-[15px]">{Math.floor(Math.random() * 3) + 1}</span>
                            <span className="text-[9px] uppercase text-slate-500 font-bold tracking-wider">Tasks</span>
                          </div>
                          <div className="flex flex-col items-center px-4 py-1.5">
                            <span className="text-amber-500 font-bold text-[15px]">{Math.floor(Math.random() * 2)}</span>
                            <span className="text-[9px] uppercase text-amber-500/70 font-bold tracking-wider">Review</span>
                          </div>
                        </div>
                        
                        <div className="h-10 w-10 ml-2 rounded-full bg-[#10b981]/10 flex items-center justify-center group-hover:bg-[#10b981] transition-colors shrink-0">
                          <ChevronRight size={20} className="text-[#10b981] group-hover:text-white transition-colors" />
                        </div>
                      </div>
                    </button>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      </main>
    );
  }

  const filteredTasks = tasks.filter(t => 
    t.title.toLowerCase().includes(search.toLowerCase()) || 
    t.employee.toLowerCase().includes(search.toLowerCase()) ||
    t.id.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <main className="flex-1 flex flex-col h-full bg-[#f4f6f8] dark:bg-[#0b0f1a] text-slate-900 dark:text-white overflow-hidden transition-colors duration-300">
      <div className="p-8 border-b border-gray-200 dark:border-white/[0.04] shrink-0">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-4">
            <button 
              onClick={() => setSelectedCaseId(null)}
              className="w-10 h-10 rounded-full bg-white dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.06] flex items-center justify-center hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors"
            >
              <ChevronLeft size={20} className="text-slate-600 dark:text-slate-300" />
            </button>
            <div>
              <h1 className="text-3xl font-bold tracking-tight mb-1 bg-gradient-to-r from-[#10b981] to-[#0ea5e9] bg-clip-text text-transparent drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]">
                Case Pipeline
              </h1>
              <p className="text-slate-500 dark:text-slate-400 text-sm font-mono flex items-center gap-2">
                <ShieldAlert size={14} className="text-[#10b981]" />
                Viewing Tasks for Case: {selectedCaseId}
              </p>
            </div>
          </div>
          <button className="h-10 px-4 bg-[#10b981] text-white rounded-lg font-bold text-sm flex items-center gap-2 hover:bg-[#10b981]/90 transition-all shadow-lg shadow-[#10b981]/20 border border-[#10b981]">
            <Plus size={16} />
            New Task
          </button>
        </div>

        <div className="flex gap-4">
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" size={16} />
            <input 
              type="text" 
              placeholder="Search by task ID, employee, or title..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full h-10 bg-white dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.06] rounded-lg pl-10 pr-4 text-sm focus:outline-none focus:border-[#10b981]/50 focus:bg-white dark:focus:bg-white/[0.04] transition-all text-slate-800 dark:text-white"
            />
          </div>
          <button className="h-10 px-4 bg-white dark:bg-white/[0.02] border border-gray-200 dark:border-white/[0.06] rounded-lg font-medium text-sm flex items-center gap-2 hover:bg-gray-50 dark:hover:bg-white/[0.06] transition-colors shadow-sm dark:shadow-none text-slate-700 dark:text-white">
            <Filter size={16} />
            Filters
          </button>
        </div>
      </div>

      <div className="flex-1 overflow-x-auto overflow-y-hidden p-8">
        <div className="flex gap-6 h-full min-w-max pb-4">
          {STAGES.map(stage => {
            const stageTasks = filteredTasks.filter(t => t.stage === stage);
            return (
              <div 
                key={stage} 
                className="w-[320px] flex flex-col bg-gray-50 dark:bg-[#0f1422]/50 border border-gray-200 dark:border-white/[0.04] rounded-xl overflow-hidden shrink-0"
                onDrop={(e) => handleDrop(e, stage)}
                onDragOver={handleDragOver}
              >
                <div className="p-4 border-b border-gray-200 dark:border-white/[0.04] flex items-center justify-between bg-white dark:bg-white/[0.02]">
                  <div className="flex items-center gap-2 font-semibold text-sm">
                    {getStageIcon(stage)}
                    {stage}
                  </div>
                  <span className="bg-gray-200 dark:bg-white/[0.1] text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded-full text-xs font-bold">
                    {stageTasks.length}
                  </span>
                </div>
                
                <div className="flex-1 overflow-y-auto p-4 space-y-4">
                  {stageTasks.map(task => (
                    <div 
                      key={task.id}
                      draggable
                      onDragStart={(e) => handleDragStart(e, task.id)}
                      className="bg-white dark:bg-[#1a2035] border border-gray-200 dark:border-white/[0.06] p-4 rounded-xl cursor-grab active:cursor-grabbing hover:border-[#10b981]/50 hover:shadow-lg hover:shadow-[#10b981]/5 transition-all group"
                    >
                      <div className="flex items-start justify-between mb-2">
                        <span className="text-xs font-mono text-slate-500">{task.id}</span>
                        <span className={`text-[10px] uppercase font-bold px-2 py-0.5 rounded border ${getPriorityColor(task.priority)}`}>
                          {task.priority}
                        </span>
                      </div>
                      
                      <h3 className="font-bold text-sm mb-1 group-hover:text-[#10b981] transition-colors">{task.title}</h3>
                      <p className="text-xs text-slate-600 dark:text-slate-400 mb-4">{task.employee}</p>
                      
                      <div className="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-white/[0.04]">
                        <div className="flex items-center gap-1.5 text-xs text-slate-500">
                          <Clock size={12} />
                          {task.daysInStage} {task.daysInStage === 1 ? 'day' : 'days'}
                        </div>
                        <div className="w-6 h-6 rounded-full bg-[#10b981]/20 text-[#10b981] flex items-center justify-center text-[10px] font-bold border border-[#10b981]/30" title={task.assignee}>
                          {task.assignee.split(' ').map((n: string) => n[0]).join('').substring(0, 2)}
                        </div>
                      </div>
                    </div>
                  ))}
                  
                  {stageTasks.length === 0 && (
                    <div className="h-24 border-2 border-dashed border-gray-200 dark:border-white/[0.05] rounded-xl flex items-center justify-center text-xs text-slate-500 font-medium">
                      Drop tasks here
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </main>
  );
}
