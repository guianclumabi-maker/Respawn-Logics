import { useState, useEffect, useCallback } from "react";
import {
  Search, Plus, Star, ChevronDown, ChevronUp, X, Check, Trash2,
  ArrowRight, ChevronRight, LayoutList, Columns3, Globe, Link,
  UserPlus, Briefcase, Clock, Users, Zap, Award, MapPin, User
} from "lucide-react";

// ─── API ──────────────────────────────────────────────────────────────────────
const API_BASE = import.meta.env.VITE_API_BASE_URL;
const API = `${API_BASE}/api/index.php?route=candidates`;

// ─── Types ────────────────────────────────────────────────────────────────────
type ViewState = { view: string; jobId?: number; candidateId?: number; poolId?: number };
type Props = { onViewChange: (v: ViewState) => void; jobId?: number };

type Application = {
  id: number; candidate_id: number; name: string; email: string;
  stage: string; rating: number; ai_match_score: number | null;
  score_breakdown?: { skill: number, experience: number, location: number, salary: number } | null;
  source: string; days_in_stage: number; tags: string[];
  formatted_applied: string; skills: string;
  assigned_recruiter?: string; recruiter?: string;
  candidate_source?: string; phone?: string;
  candidate_location?: string; experience_years?: number;
};

type HealthData = {
  total: number; applied: number; review: number; phone_screen: number;
  interview: number; offer: number; hired: number; stuck: number;
  score: number; status: string; velocity: number;
};

type Job = {
  id: number; title: string; department: string; status: string;
  health: HealthData; applications: Application[];
};

type JobListItem = { id: number; title: string; department: string; status: string };

type CandidateForm = {
  name: string;
  email: string;
  phone: string;
  location: string;
  skills: string;
  experience_years: string;
  source: string;
};

// ─── Constants ────────────────────────────────────────────────────────────────
const STAGES = ["Applied", "Review", "Phone Screen", "Interview", "Offer", "Hired"] as const;
const SOURCES = ["Direct", "LinkedIn", "Careers Site", "Referral", "Indeed", "Agency"];

type SortKey = "name" | "stage" | "source" | "ai_match_score" | "days_in_stage" | "formatted_applied" | "rating";

// ─── Sub-components ───────────────────────────────────────────────────────────

function StarRating({ value, onChange }: { value: number; onChange?: (r: number) => void }) {
  return (
    <div className="flex gap-0.5">
      {[1, 2, 3, 4, 5].map(i => (
        <button key={i} type="button" onClick={() => onChange?.(i)}
          className={`p-0 bg-transparent border-0 outline-none ${onChange ? "cursor-pointer" : ""}`}>
          <Star size={11} fill={i <= value ? "#f5a623" : "none"}
            stroke={i <= value ? "#f5a623" : "rgba(255,255,255,0.15)"} />
        </button>
      ))}
    </div>
  );
}

function AiScoreBadge({ score, breakdown }: { score: number | null, breakdown?: { skill: number, experience: number, location: number, salary: number } | null }) {
  if (score === null || score === undefined) return <span className="text-[10px] text-muted-foreground font-mono">—</span>;
  const color = score > 70 ? "#00e07a" : score >= 40 ? "#f5a623" : "#ff4d6a";
  return (
    <div className="group relative flex items-center gap-1.5 font-mono">
      <span className="w-1.5 h-1.5 rounded-full animate-pulse" style={{ backgroundColor: color }} />
      <span className="text-[10px] font-bold cursor-help" style={{ color }}>{`SCORE: ${score}%`}</span>
      
      {breakdown && (
        <div className="absolute left-0 bottom-full mb-2 hidden group-hover:block z-50 bg-popover/95 border border-white/10 rounded-lg p-3 text-xs w-48 shadow-xl backdrop-blur-xl pointer-events-none">
          <div className="font-bold mb-2 text-white/90 pb-2 border-b border-white/10">Score Breakdown</div>
          <div className="space-y-1.5">
            <div className="flex justify-between"><span>Skill Match</span><span className="text-white/80">{breakdown.skill}%</span></div>
            <div className="flex justify-between"><span>Experience</span><span className="text-white/80">{breakdown.experience}%</span></div>
            <div className="flex justify-between"><span>Location</span><span className="text-white/80">{breakdown.location}%</span></div>
            <div className="flex justify-between"><span>Salary Req</span><span className="text-white/80">{breakdown.salary}%</span></div>
          </div>
        </div>
      )}
    </div>
  );
}

function SlaDays({ days }: { days: number }) {
  const color = days < 3 ? "#00e07a" : days <= 7 ? "#f5a623" : "#ff4d6a";
  return <span className="text-[10px] font-mono font-bold" style={{ color }}>{`${days}d`}</span>;
}

function SourceIcon({ source }: { source: string }) {
  if (source?.toLowerCase().includes("linkedin")) return <Link size={11} className="text-blue-400" />;
  if (source?.toLowerCase().includes("careers") || source?.toLowerCase().includes("site")) return <Globe size={11} className="text-cyan-400" />;
  if (source?.toLowerCase().includes("referral")) return <UserPlus size={11} className="text-primary" />;
  if (source?.toLowerCase().includes("indeed")) return <Briefcase size={11} className="text-amber-400" />;
  return <Globe size={11} className="text-muted-foreground" />;
}

function LoadingSkeleton() {
  return (
    <div className="space-y-4 p-8 animate-pulse">
      <div className="h-8 bg-secondary rounded-xl w-64" />
      <div className="h-5 bg-white/[0.03] rounded-lg w-44" />
      <div className="flex gap-3 mt-6">{[...Array(7)].map((_, i) => <div key={i} className="h-16 w-20 bg-white/[0.03] rounded-xl" />)}</div>
      <div className="h-[400px] bg-muted rounded-2xl mt-4 border border-border" />
    </div>
  );
}

// ─── Add Candidate Modal ──────────────────────────────────────────────────────
function AddCandidateModal({ jobId, onClose, onSuccess }: { jobId: number; onClose: () => void; onSuccess: () => void }) {
  const [form, setForm] = useState({ name: "", email: "", phone: "", location: "", skills: "", experience_years: "", source: "Direct", salary_expectation: "", tags: "" });
  const [saving, setSaving] = useState(false);
  const set = (k: string, v: string) => setForm(p => ({ ...p, [k]: v }));

  const submit = async () => {
    if (!form.name.trim()) return;
    setSaving(true);
    try {
      const res = await fetch(API, { credentials: "include", method: "POST", headers: { 
        "Content-Type": "application/json",
        "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
      },
        body: JSON.stringify({ action: "add_candidate", job_id: jobId, ...form, experience_years: parseInt(form.experience_years) || 0, salary_expectation: parseFloat(form.salary_expectation) || 0 }) });
      const data = await res.json();
      if (data.success) { onSuccess(); onClose(); }
    } catch (e) { console.error(e); }
    setSaving(false);
  };

  const field = (label: string, key: string, placeholder: string, required = false) => (
    <div>
      <label className="text-[10px] uppercase font-mono font-bold text-muted-foreground block mb-1">{label}{required && " *"}</label>
      <input value={(form as Record<string, string>)[key]} onChange={e => set(key, e.target.value)} placeholder={placeholder}
        className="w-full px-3 py-2 rounded-xl border text-xs font-mono outline-none bg-card border-white/10 text-foreground placeholder-gray-600 focus:border-[#00e07a]" />
    </div>
  );

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
      <div className="bg-background border border-white/10 rounded-2xl shadow-2xl w-full max-w-md p-6 text-foreground max-h-[90vh] overflow-y-auto scrollbar-thin">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-sm font-bold font-['Space_Grotesk'] tracking-wide">ADD NEW CANDIDATE</h3>
          <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-white/5 text-muted-foreground hover:text-foreground cursor-pointer border-0 bg-transparent"><X size={16} /></button>
        </div>
        <div className="space-y-3">
          {field("Name", "name", "Full name...", true)}
          <div className="grid grid-cols-2 gap-3">
            {field("Email", "email", "email@example.com")}
            {field("Phone", "phone", "+1 234 567 890")}
          </div>
          {field("Location", "location", "City, Country")}
          {field("Skills", "skills", "React, TypeScript, Node.js (comma-separated)")}
          <div className="grid grid-cols-2 gap-3">
            {field("Experience (years)", "experience_years", "5")}
            {field("Salary Expectation", "salary_expectation", "80000")}
          </div>
          <div>
            <label className="text-[10px] uppercase font-mono font-bold text-muted-foreground block mb-1">Source</label>
            <select value={form.source} onChange={e => set("source", e.target.value)}
              className="w-full px-3 py-2 rounded-xl border text-xs font-mono outline-none bg-card border-white/10 text-foreground focus:border-[#00e07a]">
              {SOURCES.map(s => <option key={s} value={s}>{s}</option>)}
            </select>
          </div>
          {field("Tags", "tags", "Senior, Remote (comma-separated)")}
        </div>
        <div className="flex gap-2 mt-5 font-mono">
          <button onClick={onClose} className="flex-1 px-4 py-2 rounded-xl border text-xs font-bold bg-transparent hover:bg-white/5 cursor-pointer text-muted-foreground border-white/10">[ CANCEL ]</button>
          <button disabled={!form.name.trim() || saving} onClick={submit}
            className="flex-1 px-4 py-2 rounded-xl text-xs font-bold bg-primary hover:opacity-90 text-primary-foreground hover:opacity-90 disabled:opacity-40 cursor-pointer border-0">
            {saving ? "[ SAVING... ]" : "[ ADD CANDIDATE ]"}
          </button>
        </div>
      </div>
    </div>
  );
}

// ─── Kanban Card ──────────────────────────────────────────────────────────────
function KanbanCard({ app, onClick }: { app: Application; onClick: () => void }) {
  return (
    <div draggable onDragStart={e => { e.dataTransfer.setData("text/plain", app.id.toString()); e.dataTransfer.effectAllowed = "move"; }}
      onClick={onClick}
      className="bg-card border border-border rounded-xl p-3.5 cursor-grab hover:border-[#9b6dff]/40 hover:bg-purple-500/5 dark:hover:bg-[#141929] hover:shadow-[0_0_12px_rgba(155,109,255,0.08)] transition-all group">
      <div className="flex items-start justify-between mb-1.5">
        <span className="text-xs font-bold text-foreground group-hover:text-primary transition-colors truncate flex-1">{app.name}</span>
        <div className="flex items-center gap-1 ml-1.5"><SourceIcon source={app.source || app.candidate_source || ""} /></div>
      </div>
      <div className="flex items-center justify-between mb-2">
        <AiScoreBadge score={app.ai_match_score} breakdown={app.score_breakdown} />
        <SlaDays days={app.days_in_stage} />
      </div>
      <div className="flex items-center justify-between mt-2 pt-2 border-t border-border">
        <StarRating value={app.rating} />
        <span className="text-[8px] font-mono text-muted-foreground">{app.formatted_applied}</span>
      </div>
      {app.tags?.length > 0 && (
        <div className="flex gap-1 flex-wrap mt-2">
          {app.tags.slice(0, 2).map(t => (
            <span key={t} className="px-1.5 py-0.5 rounded text-[8px] font-mono font-bold bg-[#9b6dff]/10 border border-[#9b6dff]/20 text-[#9b6dff] truncate max-w-[80px]">{`#${t.toUpperCase()}`}</span>
          ))}
        </div>
      )}
    </div>
  );
}

// ─── Kanban Column ────────────────────────────────────────────────────────────
function KanbanColumn({ stage, apps, onDrop, onCardClick }: {
  stage: string; apps: Application[]; onDrop: (appId: number, stage: string) => void; onCardClick: (candidateId: number) => void;
}) {
  const [over, setOver] = useState(false);
  const displayStages: Record<string, string> = {
    "Applied": "APPLIED",
    "Review": "REVIEW",
    "Phone Screen": "PHONE SCREEN",
    "Interview": "INTERVIEW",
    "Offer": "OFFER",
    "Hired": "HIRED"
  };
  const label = displayStages[stage] || stage;

  return (
    <div className={`bg-background/60 border border-border rounded-xl min-h-[450px] p-3 flex flex-col gap-2.5 flex-1 min-w-[220px] transition-all ${over ? "border-[#9b6dff] bg-[#9b6dff]/[0.02] shadow-[0_0_15px_rgba(155,109,255,0.08)]" : ""}`}
      onDragOver={e => { e.preventDefault(); e.dataTransfer.dropEffect = "move"; setOver(true); }}
      onDragLeave={() => setOver(false)}
      onDrop={e => { e.preventDefault(); setOver(false); try { const idStr = e.dataTransfer.getData("text/plain"); if (idStr) onDrop(parseInt(idStr, 10), stage); } catch (err) { console.error("Drop error", err); } }}>
      <div className="flex items-center justify-between px-1 py-1 border-b border-border pb-2 font-mono">
        <span className="text-[10px] font-bold text-muted-foreground tracking-wider">{`// ${label}`}</span>
        <span className="text-[9px] font-bold px-2 py-0.5 rounded bg-secondary text-[#9b6dff]">{apps.length}</span>
      </div>
      <div className="flex flex-col gap-2.5 overflow-y-auto max-h-[60vh] pr-0.5 scrollbar-thin mt-2.5">
        {apps.map(a => <KanbanCard key={a.id} app={a} onClick={() => onCardClick(a.candidate_id)} />)}
      </div>
    </div>
  );
}

// ─── Bulk Action Bar ──────────────────────────────────────────────────────────
function BulkBar({ count, onAdvance, onReject, onDelete }: {
  count: number; onAdvance: (stage: string) => void; onReject: () => void; onDelete: () => void;
}) {
  const [showStages, setShowStages] = useState(false);
  return (
    <div className="fixed bottom-10 left-1/2 -translate-x-1/2 z-50 flex items-center gap-6 px-8 py-4 rounded-[2rem] border border-[#00e07a]/30 bg-background/95 backdrop-blur-2xl shadow-[0_20px_50px_rgba(0,224,122,0.15)] font-mono text-sm">
      <span className="text-muted-foreground whitespace-nowrap"><span className="font-bold text-primary text-base">{count}</span> CANDIDATES SELECTED</span>
      <div className="w-px h-6 bg-white/10" />
      <div className="relative">
        <button onClick={() => setShowStages(s => !s)} className="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold bg-[#9b6dff]/10 border border-[#9b6dff]/20 text-[#9b6dff] hover:bg-[#9b6dff]/20 hover:shadow-[0_0_15px_rgba(155,109,255,0.2)] transition-all cursor-pointer whitespace-nowrap border-0">
          [ ADVANCE STAGE ] <ChevronDown size={14} />
        </button>
        {showStages && (
          <div className="absolute bottom-full mb-3 left-0 w-48 bg-[#0d0f19] border border-white/10 rounded-xl shadow-2xl z-50 py-2">
            {STAGES.map(s => (
              <button key={s} onClick={() => { onAdvance(s); setShowStages(false); }}
                className="w-full text-left px-4 py-2.5 text-xs font-bold text-gray-300 hover:bg-white/5 transition-colors cursor-pointer border-0 bg-transparent">{s}</button>
            ))}
          </div>
        )}
      </div>
      <button onClick={onReject} className="px-4 py-2.5 rounded-xl text-xs font-bold border border-rose-500/20 bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 hover:shadow-[0_0_15px_rgba(244,63,94,0.2)] transition-all cursor-pointer whitespace-nowrap bg-transparent">[ REJECT ]</button>
      <button onClick={onDelete} className="px-4 py-2.5 rounded-xl text-xs font-bold border border-red-500/20 bg-red-500/10 text-red-400 hover:bg-red-500/20 hover:shadow-[0_0_15px_rgba(239,68,68,0.2)] transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap bg-transparent"><Trash2 size={14} /> [ ARCHIVE ]</button>
    </div>
  );
}

// ─── Stage Filter Bar ─────────────────────────────────────────────────────────
function StageFilterBar({ health, active, onSelect }: { health: HealthData; active: string; onSelect: (s: string) => void }) {
  const displayStages: Record<string, string> = {
    "All": "ALL STATUS",
    "Applied": "APPLIED",
    "Review": "REVIEW",
    "Phone Screen": "PHONE SCREEN",
    "Interview": "INTERVIEW",
    "Offer": "OFFER",
    "Hired": "HIRED"
  };

  const stages = [
    { key: "All", count: health.total },
    { key: "Applied", count: health.applied },
    { key: "Review", count: health.review },
    { key: "Phone Screen", count: health.phone_screen },
    { key: "Interview", count: health.interview },
    { key: "Offer", count: health.offer },
    { key: "Hired", count: health.hired },
  ];
  return (
    <div className="flex gap-2 overflow-x-auto pb-1 items-center scrollbar-thin font-mono">
      {stages.map((s, i) => {
        const isActive = active === s.key;
        const next = stages[i + 1];
        const convPct = s.key !== "All" && next && s.count > 0 ? Math.round((next.count / s.count) * 100) : null;
        const displayLabel = displayStages[s.key] || s.key;
        return (
          <div key={s.key} className="flex items-center gap-1.5 flex-shrink-0">
            <button onClick={() => onSelect(s.key)}
              className="flex flex-col items-center px-6 py-4 rounded-2xl border transition-all cursor-pointer min-w-[100px]"
              style={{
                backgroundColor: isActive ? "rgba(0, 224, 122, 0.15)" : "rgba(15, 20, 34, 0.5)",
                borderColor: isActive ? "#00e07a" : "rgba(255,255,255,0.06)",
                borderWidth: isActive ? 2 : 1,
              }}>
              <span className="text-2xl font-bold leading-none mb-2 text-foreground">{s.count}</span>
              <span className="text-[11px] font-bold tracking-wide text-center leading-tight" style={{ color: isActive ? "#00e07a" : "#8b95a8" }}>{displayLabel}</span>
            </button>
            {convPct !== null && s.key !== "Hired" && (
              <div className="flex flex-col items-center text-muted-foreground flex-shrink-0 font-mono px-2">
                <ArrowRight size={16} className="text-primary/40" />
                <span className="text-[10px] font-bold text-muted-foreground mt-0.5">{`${convPct}%`}</span>
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}

// ─── Main Component ───────────────────────────────────────────────────────────
export function PipelineBoard({ onViewChange, jobId }: Props) {
  const [job, setJob] = useState<Job | null>(null);
  const [jobs, setJobs] = useState<JobListItem[]>([]);
  const [selectedJobId, setSelectedJobId] = useState<number | undefined>(jobId);
  const [loading, setLoading] = useState(true);
  const [viewMode, setViewMode] = useState<"table" | "kanban">("table");
  const [activeStage, setActiveStage] = useState("All");
  const [search, setSearch] = useState("");
  const [jobSearch, setJobSearch] = useState("");
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [sortKey, setSortKey] = useState<SortKey>("name");
  const [sortDir, setSortDir] = useState<"asc" | "desc">("asc");
  const [showAdd, setShowAdd] = useState(false);
  const [toast, setToast] = useState("");

  const showToast = (msg: string) => { setToast(msg); setTimeout(() => setToast(""), 3500); };

  // Sync jobId prop to state when it changes (e.g. on redirection from Job creation)
  useEffect(() => {
    if (jobId !== undefined) {
      setSelectedJobId(jobId);
    }
  }, [jobId]);

  // Fetch job list if no jobId
  useEffect(() => {
    if (!jobId) {
      fetch(`${API}&action=jobs`, { credentials: "include" }).then(r => r.json()).then(d => {
        if (d.success && d.jobs?.length) {
          setJobs(d.jobs);
          // Do NOT auto-select the first job, let the user choose from the list
        }
        setLoading(false);
      }).catch(() => setLoading(false));
    }
  }, [jobId]);

  // Fetch job detail
  const loadJob = useCallback(async () => {
    const id = selectedJobId;
    if (!id) return;
    setLoading(true);
    try {
      const res = await fetch(`${API}&action=job&id=${id}&t=${Date.now()}`, { credentials: "include" });
      const data = await res.json();
      if (data.success) setJob(data.job);
    } catch (e) { console.error(e); }
    setLoading(false);
  }, [selectedJobId]);

  useEffect(() => { if (selectedJobId) loadJob(); }, [selectedJobId, loadJob]);

  // ── Filtering + Sorting ──
  const apps = job?.applications ?? [];
  const filtered = apps.filter(a => {
    const matchStage = activeStage === "All" || a.stage === activeStage;
    const matchSearch = !search || a.name.toLowerCase().includes(search.toLowerCase()) || (a.email || "").toLowerCase().includes(search.toLowerCase());
    return matchStage && matchSearch;
  });

  const sorted = [...filtered].sort((a, b) => {
    let cmp = 0;
    const av = a[sortKey], bv = b[sortKey];
    if (typeof av === "number" && typeof bv === "number") cmp = (av ?? 0) - (bv ?? 0);
    else cmp = String(av ?? "").localeCompare(String(bv ?? ""));
    return sortDir === "asc" ? cmp : -cmp;
  });

  const toggleSort = (key: SortKey) => {
    if (sortKey === key) setSortDir(d => d === "asc" ? "desc" : "asc");
    else { setSortKey(key); setSortDir("asc"); }
  };

  const SortIcon = ({ col }: { col: SortKey }) => {
    if (sortKey !== col) return <ChevronDown size={11} className="text-muted-foreground" />;
    return sortDir === "asc" ? <ChevronUp size={11} className="text-primary" /> : <ChevronDown size={11} className="text-primary" />;
  };

  // ── Bulk selection ──
  const allChecked = sorted.length > 0 && sorted.every(a => selected.has(a.id));
  const toggleAll = () => { if (allChecked) setSelected(new Set()); else setSelected(new Set(sorted.map(a => a.id))); };
  const toggleOne = (id: number) => { const n = new Set(selected); n.has(id) ? n.delete(id) : n.add(id); setSelected(n); };

  // ── API mutations ──
  const updateStage = async (appId: number, stage: string) => {
    try {
      const res = await fetch(API, { method: "POST", headers: { 
        "Content-Type": "application/json",
        "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
      }, body: JSON.stringify({ action: "update_stage", id: appId, stage }), credentials: "include" });
      if (!res.ok) {
        const text = await res.text();
        showToast(`Failed: ${res.status} ${text}`);
      }
      loadJob();
    } catch (err) {
      console.error(err);
    }
  };

  const updateRating = async (appId: number, rating: number) => {
    setJob(prev => prev ? { ...prev, applications: prev.applications.map(a => a.id === appId ? { ...a, rating } : a) } : prev);
    try {
      await fetch(API, { credentials: "include", method: "POST", headers: { 
        "Content-Type": "application/json",
        "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
      }, body: JSON.stringify({ action: "update_rating", id: appId, rating }) });
    } catch (err) {
      console.error(err);
    }
  };

  const bulkAdvance = async (stage: string) => {
    const ids = Array.from(selected);
    try {
      const res = await fetch(API, { method: "POST", headers: { 
        "Content-Type": "application/json",
        "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
      }, body: JSON.stringify({ action: "bulk_advance", ids, stage }), credentials: "include" });
      if (!res.ok) {
        const text = await res.text();
        showToast(`Failed: ${res.status} ${text}`);
      } else {
        setSelected(new Set()); showToast(`${ids.length} candidate(s) moved to ${stage}`); 
      }
      loadJob();
    } catch (err) { console.error(err); }
  };

  const bulkReject = async () => {
    const ids = Array.from(selected);
    if (!confirm(`Reject ${ids.length} candidate(s)?`)) return;
    try {
      await fetch(API, { credentials: "include", method: "POST", headers: { 
        "Content-Type": "application/json",
        "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
      }, body: JSON.stringify({ action: "bulk_reject", ids }) });
      setSelected(new Set()); showToast(`${ids.length} candidate(s) rejected`); loadJob();
    } catch (err) { console.error(err); }
  };

  const bulkDelete = async () => {
    const ids = Array.from(selected);
    if (!confirm(`Archive ${ids.length} candidate(s)? This cannot be undone.`)) return;
    try {
      await fetch(API, { credentials: "include", method: "POST", headers: { 
        "Content-Type": "application/json",
        "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
      }, body: JSON.stringify({ action: "bulk_delete", ids }) });
      setSelected(new Set()); showToast(`${ids.length} candidate(s) archived`); loadJob();
    } catch (err) { console.error(err); }
  };

  const handleDrop = (appId: number, stage: string) => { updateStage(appId, stage); showToast(`Moved to ${stage}`); };

  // ── Health badge ──
  const healthBadge = (h: HealthData) => {
    const colors: Record<string, string> = { Healthy: "emerald", "Needs Attention": "amber", Critical: "rose" };
    const c = colors[h.status] || "gray";
    return (
      <span className={`text-[10px] font-mono font-bold px-2 py-0.5 rounded-full tracking-wider border border-${c}-500/20 bg-${c}-500/10 text-${c}-400`}>
        {`[ HEALTH: ${h.status.toUpperCase()} (${h.score}%) ]`}
      </span>
    );
  };

  if (loading) return <div className="flex-1 overflow-hidden animate-pulse" ><LoadingSkeleton /></div>;

  // Job selector if no specific job
  if (!job && jobs.length > 0) {
    const filteredJobs = jobs.filter(j => 
      !jobSearch || 
      j.title.toLowerCase().includes(jobSearch.toLowerCase()) || 
      (j.department || "").toLowerCase().includes(jobSearch.toLowerCase()) || 
      (j.location || "").toLowerCase().includes(jobSearch.toLowerCase())
    );

    // Compute mini dashboard metrics
    const totalCandidates = jobs.reduce((sum, j) => sum + (j.health?.total || 0), 0);
    const totalVelocity = jobs.reduce((sum, j) => sum + (j.health?.velocity || 0), 0);
    const totalOffers = jobs.reduce((sum, j) => sum + (j.health?.offer || 0), 0);
    
    return (
      <div className="flex-1 flex flex-col items-center justify-start text-foreground font-mono p-6 md:p-10 overflow-y-auto custom-scrollbar" >
        <div className="w-full space-y-8 mt-2 max-w-[1600px]">
          
          <div className="text-center">
            <div className="w-16 h-16 rounded-full bg-[#00e07a]/10 flex items-center justify-center mx-auto mb-4">
              <Briefcase size={28} className="text-[#00e07a]" />
            </div>
            <h2 className="text-xl font-bold mb-2 tracking-widest text-[#00e07a] uppercase">[ PIPELINE SELECTION ]</h2>
            <p className="text-sm text-muted-foreground">Select an active job to view its recruitment pipeline</p>
          </div>

          {/* Mini Dashboard */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="bg-background/40 border border-border/50 rounded-xl p-5 shadow-lg flex flex-col items-center justify-center relative overflow-hidden group">
              <div className="absolute top-0 right-0 w-16 h-16 bg-[#00e07a]/5 rounded-bl-full -z-10 group-hover:bg-[#00e07a]/10 transition-colors"></div>
              <Briefcase size={20} className="text-[#00e07a] mb-2 opacity-80" />
              <span className="text-foreground text-3xl font-bold mb-1 font-sans">{jobs.length}</span>
              <span className="text-[10px] text-muted-foreground uppercase tracking-widest font-sans font-bold">Active Jobs</span>
            </div>
            <div className="bg-background/40 border border-border/50 rounded-xl p-5 shadow-lg flex flex-col items-center justify-center relative overflow-hidden group">
              <div className="absolute top-0 right-0 w-16 h-16 bg-[#9b6dff]/5 rounded-bl-full -z-10 group-hover:bg-[#9b6dff]/10 transition-colors"></div>
              <Users size={20} className="text-[#9b6dff] mb-2 opacity-80" />
              <span className="text-foreground text-3xl font-bold mb-1 font-sans">{totalCandidates}</span>
              <span className="text-[10px] text-muted-foreground uppercase tracking-widest font-sans font-bold">Total Candidates</span>
            </div>
            <div className="bg-background/40 border border-border/50 rounded-xl p-5 shadow-lg flex flex-col items-center justify-center relative overflow-hidden group">
              <div className="absolute top-0 right-0 w-16 h-16 bg-amber-400/5 rounded-bl-full -z-10 group-hover:bg-amber-400/10 transition-colors"></div>
              <Zap size={20} className="text-amber-400 mb-2 opacity-80" />
              <span className="text-foreground text-3xl font-bold mb-1 font-sans">{totalVelocity}</span>
              <span className="text-[10px] text-muted-foreground uppercase tracking-widest font-sans font-bold">New This Week</span>
            </div>
            <div className="bg-background/40 border border-border/50 rounded-xl p-5 shadow-lg flex flex-col items-center justify-center relative overflow-hidden group">
              <div className="absolute top-0 right-0 w-16 h-16 bg-emerald-400/5 rounded-bl-full -z-10 group-hover:bg-emerald-400/10 transition-colors"></div>
              <Award size={20} className="text-emerald-400 mb-2 opacity-80" />
              <span className="text-foreground text-3xl font-bold mb-1 font-sans">{totalOffers}</span>
              <span className="text-[10px] text-muted-foreground uppercase tracking-widest font-sans font-bold">Pending Offers</span>
            </div>
          </div>

          <div className="bg-card border border-border rounded-xl p-6 shadow-xl w-full">
            <div className="w-full relative mb-6">
              <Search size={16} className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <input
                type="text"
                value={jobSearch}
                onChange={(e) => setJobSearch(e.target.value)}
                placeholder="Search active jobs by title, department, or location..."
                className="w-full bg-background border border-border rounded-lg pl-12 pr-4 py-3 text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:border-[#00e07a]/40 transition-colors shadow-inner"
              />
            </div>
            <div className="space-y-4 w-full">
              {filteredJobs.length === 0 ? (
                <div className="text-center py-10 text-sm text-muted-foreground font-sans">No jobs match your filter.</div>
              ) : (
                filteredJobs.map(j => (
                  <button key={j.id} onClick={() => setSelectedJobId(j.id)}
                    className="w-full flex flex-col lg:flex-row items-start lg:items-center justify-between p-5 rounded-xl border border-border bg-background hover:bg-card/80 text-left hover:border-[#00e07a]/40 hover:shadow-[0_8px_30px_rgba(0,224,122,0.1)] transition-all cursor-pointer group gap-4 relative overflow-hidden">
                    
                    {/* Left: Job Info */}
                    <div className="flex-1">
                      <div className="flex flex-wrap items-center gap-3 mb-2">
                        <span className="text-lg font-bold text-foreground group-hover:text-[#00e07a] transition-colors">{j.title}</span>
                        {j.priority === 'High' && <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-red-500/10 text-red-400 border border-red-500/20 uppercase tracking-wider font-sans">High Priority</span>}
                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider font-sans border ${
                          j.status === 'Open' ? 'bg-[#00e07a]/10 text-[#00e07a] border-[#00e07a]/20' : 'bg-muted text-muted-foreground border-border'
                        }`}>{j.status || 'Open'}</span>
                      </div>
                      
                      <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground font-sans mt-2">
                        <span className="flex items-center gap-1.5"><Briefcase size={14} className="text-muted-foreground/70"/> {j.department || 'N/A'}</span>
                        <span className="flex items-center gap-1.5"><MapPin size={14} className="text-muted-foreground/70"/> {j.location || 'Remote'}</span>
                        <span className="flex items-center gap-1.5"><Clock size={14} className="text-muted-foreground/70"/> {j.days_open}d open</span>
                        {j.hiring_manager && <span className="flex items-center gap-1.5"><User size={14} className="text-muted-foreground/70"/> {j.hiring_manager}</span>}
                      </div>
                    </div>

                    {/* Right: Pipeline Health Breakdown */}
                    <div className="flex items-center gap-2 lg:gap-4 w-full lg:w-auto mt-2 lg:mt-0 pt-4 lg:pt-0 border-t lg:border-t-0 border-border/50">
                      
                      <div className="flex items-center bg-card/50 rounded-lg border border-border/50 divide-x divide-border/50 overflow-hidden font-sans shadow-sm">
                        <div className="flex flex-col items-center px-4 py-1.5 hover:bg-background/50 transition-colors">
                          <span className="text-foreground font-bold text-[15px]">{j.health?.applied || 0}</span>
                          <span className="text-[9px] uppercase text-muted-foreground font-bold tracking-wider">Applied</span>
                        </div>
                        <div className="flex flex-col items-center px-4 py-1.5 hover:bg-background/50 transition-colors">
                          <span className="text-amber-400 font-bold text-[15px]">{j.health?.review || 0}</span>
                          <span className="text-[9px] uppercase text-amber-400/70 font-bold tracking-wider">Review</span>
                        </div>
                        <div className="flex flex-col items-center px-4 py-1.5 hover:bg-background/50 transition-colors">
                          <span className="text-purple-400 font-bold text-[15px]">{j.health?.interview || 0}</span>
                          <span className="text-[9px] uppercase text-purple-400/70 font-bold tracking-wider">Interview</span>
                        </div>
                        <div className="flex flex-col items-center px-4 py-1.5 hover:bg-[#00e07a]/5 transition-colors">
                          <span className="text-[#00e07a] font-bold text-[15px]">{j.health?.offer || 0}</span>
                          <span className="text-[9px] uppercase text-[#00e07a]/70 font-bold tracking-wider">Offer</span>
                        </div>
                      </div>
                      
                      <div className="h-10 w-10 ml-2 rounded-full bg-primary/10 flex items-center justify-center group-hover:bg-primary transition-colors shrink-0">
                        <ChevronRight size={20} className="text-primary group-hover:text-primary-foreground transition-colors" />
                      </div>
                    </div>
                  </button>
                ))
              )}
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (!job) return (
    <div className="flex-1 flex items-center justify-center text-muted-foreground text-sm font-mono" >
      Job pipeline offline. Create a job first.
    </div>
  );

  const h = job.health;

  return (
    <div className="flex-1 flex flex-col overflow-hidden text-foreground font-sans relative" >
      <style>{`
        .blink {
          animation: blink-anim 1.1s step-start infinite;
        }
        @keyframes blink-anim {
          0%, 100% { opacity: 1; }
          50%       { opacity: 0; }
        }
      `}</style>
      {/* Glow */}
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-primary blur-[120px] opacity-[0.07] pointer-events-none z-0" />
      <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#9b6dff] blur-[140px] opacity-[0.05] pointer-events-none z-0" />

      {/* Modals */}
      {showAdd && <AddCandidateModal jobId={job.id} onClose={() => setShowAdd(false)} onSuccess={() => { loadJob(); showToast("Candidate added!"); }} />}

      {/* Toast */}
      {toast && (
        <div className="fixed bottom-6 right-6 z-50 flex items-center gap-2 px-4 py-3 rounded-xl shadow-2xl text-xs font-mono font-bold border border-[#00e07a]/20 bg-background text-primary">
          <Check size={14} />{toast}
        </div>
      )}

      {/* Bulk bar */}
      {selected.size > 0 && <BulkBar count={selected.size} onAdvance={bulkAdvance} onReject={bulkReject} onDelete={bulkDelete} />}

      {/* Header area */}
      <div className="px-8 pt-5 relative z-10 border-b border-border pb-4">
        {/* Breadcrumb */}
        <div className="flex items-center gap-1.5 text-[9px] uppercase font-bold tracking-wider text-muted-foreground mb-3 font-mono">
          <span className="hover:text-foreground cursor-pointer transition-colors" onClick={() => onViewChange({ view: "Jobs" })}>JOBS</span>
          <span>/</span>
          <span className="hover:text-foreground cursor-pointer transition-colors truncate max-w-[150px]">{job.title}</span>
          <span>/</span>
          <span className="text-primary">PIPELINE</span>
        </div>

        {/* Title row */}
        <div className="flex items-start justify-between mb-4">
          <div>
            <div className="flex items-center gap-3 flex-wrap">
              <h1 className="text-2xl font-bold tracking-tight text-foreground font-['Space_Grotesk'] flex items-center gap-1.5">
                {job.title}
                <span className="inline-block w-2 h-4 bg-primary blink"></span>
              </h1>
              <span className="text-[9px] font-mono font-bold px-2 py-0.5 rounded-full tracking-wider border border-[#9b6dff]/20 bg-[#9b6dff]/10 text-[#9b6dff]">{`DEPARTMENT: ${job.department}`}</span>
              <span className={`text-[9px] font-mono font-bold px-2 py-0.5 rounded-full tracking-wider border ${job.status === "Open" ? "border-[#00e07a]/30 bg-[#00e07a]/10 text-primary" : "border-rose-500/20 bg-rose-500/10 text-rose-400"}`}>{`[ STATUS: ${job.status} ]`}</span>
              {healthBadge(h)}
            </div>
          </div>
          <div className="flex items-center gap-2 font-mono flex-shrink-0">
            {/* View toggle */}
            <div className="flex items-center rounded-xl border border-border overflow-hidden bg-white/[0.01]">
              <button onClick={() => setViewMode("table")}
                className={`flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold transition-all cursor-pointer border-0 ${viewMode === "table" ? "bg-[#00e07a]/10 border-r border-[#00e07a]/20 text-primary" : "text-muted-foreground hover:text-gray-300 border-r border-border bg-transparent"}`}>
                <LayoutList size={14} /> [ HUD TABLE ]
              </button>
              <button onClick={() => setViewMode("kanban")}
                className={`flex items-center gap-1.5 px-3 py-2 text-[10px] font-bold transition-all cursor-pointer border-0 ${viewMode === "kanban" ? "bg-[#00e07a]/10 text-primary" : "text-muted-foreground hover:text-gray-300 bg-transparent"}`}>
                <Columns3 size={14} /> [ HUD KANBAN ]
              </button>
            </div>
            <button onClick={() => setShowAdd(true)}
              className="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold bg-primary hover:opacity-90 text-primary-foreground hover:opacity-90 cursor-pointer shadow-lg shadow-green-500/10 border-0">
              <Plus size={14} /> [ ADD CANDIDATE ]
            </button>
          </div>
        </div>

        {/* Stage filter */}
        <StageFilterBar health={h} active={activeStage} onSelect={setActiveStage} />

        {/* Search */}
        <div className="flex items-center gap-2 mt-4 mb-2 font-mono">
          <div className="flex-1 flex items-center gap-2 px-3 py-2 rounded-xl border bg-card backdrop-blur-md transition-all focus-within:border-[#00e07a]/40"
            style={{ borderColor: "var(--border)" }}>
            <Search size={14} className="text-muted-foreground" />
            <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search candidate records..."
              className="flex-1 bg-transparent outline-none text-xs text-foreground placeholder-gray-600" />
          </div>
          <span className="text-[10px] text-muted-foreground flex-shrink-0"><span className="font-bold text-foreground">{filtered.length}</span> CANDIDATES FOUND</span>
        </div>
      </div>

      {/* Content area */}
      <div className="flex-1 overflow-y-auto px-8 pb-8 relative z-10 scrollbar-thin mt-4">
        {apps.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-center font-mono">
            <div className="w-16 h-16 rounded-2xl bg-[#9b6dff]/10 border border-[#9b6dff]/20 flex items-center justify-center mb-4">
              <UserPlus size={28} className="text-[#9b6dff]" />
            </div>
            <h3 className="text-sm font-bold text-foreground mb-1 font-['Space_Grotesk']">NO CANDIDATES ADDED</h3>
            <p className="text-xs text-muted-foreground max-w-xs mb-4">Add candidate profiles to begin evaluation.</p>
            <button onClick={() => setShowAdd(true)} className="px-4 py-2.5 rounded-xl text-xs font-bold bg-primary hover:opacity-90 text-primary-foreground cursor-pointer border-0">
              <Plus size={12} className="inline mr-1" />[ ADD CANDIDATE ]
            </button>
          </div>
        ) : viewMode === "table" ? (
          /* ─── Table View ─── */
          <div className="rounded-2xl border overflow-hidden bg-card backdrop-blur-md shadow-2xl" style={{ borderColor: "var(--border)" }}>
            <table className="w-full">
              <thead>
                <tr className="text-[9px] font-mono font-bold uppercase tracking-wider border-b"
                  style={{ backgroundColor: "var(--card)", color: "#8b95a8", borderColor: "var(--border)" }}>
                  <th className="w-10 px-4 py-3 text-center">
                    <input type="checkbox" checked={allChecked} onChange={toggleAll} className="rounded accent-[#00e07a]" />
                  </th>
                  {([["name", "// NAME"], ["stage", "// STAGE"], ["source", "// SOURCE"], ["ai_match_score", "// MATCH SCORE"], ["days_in_stage", "// TIME IN STAGE"], ["formatted_applied", "// SUMMONED"], ["rating", "// RATING"]] as [SortKey, string][]).map(([key, label]) => (
                    <th key={key} className="px-4 py-3 text-left cursor-pointer hover:text-primary transition-colors" onClick={() => toggleSort(key)}>
                      <div className="flex items-center gap-1">{label} <SortIcon col={key} /></div>
                    </th>
                  ))}
                  <th className="px-4 py-3 text-left">// ACTIONS</th>
                </tr>
              </thead>
              <tbody>
                {sorted.length === 0 ? (
                  <tr><td colSpan={9} className="px-4 py-12 text-center text-xs text-muted-foreground font-mono">No candidates match the target filters.</td></tr>
                ) : sorted.map(a => (
                  <tr key={a.id} className="border-b transition-colors hover:bg-muted"
                    style={{ borderColor: "var(--border)", backgroundColor: selected.has(a.id) ? "rgba(0,224,122,0.04)" : undefined }}>
                    <td className="px-4 py-3 text-center">
                      <input type="checkbox" checked={selected.has(a.id)} onChange={() => toggleOne(a.id)} className="rounded accent-[#00e07a]" />
                    </td>
                    <td className="px-4 py-3">
                      <button onClick={() => onViewChange({ view: "Candidate Profile", candidateId: a.candidate_id })}
                        className="text-xs font-bold text-cyan-400 hover:text-cyan-300 hover:underline decoration-dotted underline-offset-2 cursor-pointer text-left border-0 bg-transparent p-0">{a.name}</button>
                    </td>
                    <td className="px-4 py-3">
                      <select value={a.stage} onChange={e => updateStage(a.id, e.target.value)}
                        className="text-[9px] font-mono font-bold rounded px-2.5 py-1 bg-[#9b6dff]/10 border border-[#9b6dff]/20 text-[#9b6dff] outline-none cursor-pointer">
                        {STAGES.map(s => <option key={s} value={s} className="bg-card text-foreground">{s}</option>)}
                      </select>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-1.5 text-xs text-muted-foreground font-mono">
                        <SourceIcon source={a.source || a.candidate_source || ""} />
                        <span>{a.source || a.candidate_source || "—"}</span>
                      </div>
                    </td>
                    <td className="px-4 py-3"><AiScoreBadge score={a.ai_match_score} breakdown={a.score_breakdown} /></td>
                    <td className="px-4 py-3"><SlaDays days={a.days_in_stage} /></td>
                    <td className="px-4 py-3 text-xs text-muted-foreground font-mono">{a.formatted_applied}</td>
                    <td className="px-4 py-3"><StarRating value={a.rating} onChange={r => updateRating(a.id, r)} /></td>
                    <td className="px-4 py-3 font-mono">
                      <button onClick={() => onViewChange({ view: "Candidate Profile", candidateId: a.candidate_id })}
                        className="text-[9px] text-muted-foreground hover:text-foreground px-2.5 py-1 rounded bg-muted border border-border hover:bg-accent cursor-pointer transition-colors">[ VIEW ]</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div className="px-4 py-3 border-t border-border text-[10px] text-muted-foreground font-mono">
              COMPILING {sorted.length} OF {apps.length} CANDIDATES
            </div>
          </div>
        ) : (
          /* ─── Kanban View ─── */
          <div className="flex gap-4 overflow-x-auto pb-4 scrollbar-thin">
            {STAGES.map(stage => (
              <KanbanColumn key={stage} stage={stage}
                apps={apps.filter(a => a.stage === stage).filter(a => !search || a.name.toLowerCase().includes(search.toLowerCase()))}
                onDrop={handleDrop}
                onCardClick={cid => onViewChange({ view: "Candidate Profile", candidateId: cid })} />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
