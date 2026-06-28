import { apiFetch } from "../lib/apiClient";
import { useState, useEffect, useCallback } from "react";
import {
  Calendar,
  Clock,
  Video,
  MapPin,
  UserCheck,
  Search,
  Plus,
  X,
  ClipboardList,
  Filter,
  Loader2,
  ExternalLink,
  ChevronDown,
} from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=candidates`;

type ViewState = { view: string; jobId?: number; candidateId?: number; poolId?: number };

type Interview = {
  id: number;
  candidate_id: number;
  job_id: number;
  application_id: number;
  candidate_name: string;
  candidate_email?: string;
  job_title: string;
  interview_type: string;
  formatted_date: string;
  formatted_time: string;
  scheduled_at: string;
  duration_minutes: number;
  interviewer_name: string;
  location: string;
  meeting_link: string;
  status: string;
  is_today: boolean;
  is_past: boolean;
  scorecard_count: number;
  score?: number;
  notes?: string;
};

type Job = { id: number; title: string };
type CandidateOption = { id: number; name: string; email: string };

type Props = { onViewChange: (v: ViewState) => void };

// ── Sub-components ───────────────────────────────────────

function SkeletonCard() {
  return (
    <div className="p-6 rounded-xl border border-border bg-muted animate-pulse">
      <div className="flex gap-4">
        <div className="w-10 h-10 rounded bg-accent" />
        <div className="flex-1 space-y-2">
          <div className="h-4 w-32 bg-accent rounded" />
          <div className="h-3 w-48 bg-secondary rounded" />
        </div>
        <div className="h-6 w-20 bg-accent rounded" />
      </div>
    </div>
  );
}

function StatusBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    Scheduled: "border-blue-500/20 bg-blue-500/10 text-blue-400",
    Completed: "border-emerald-500/20 bg-emerald-500/10 text-emerald-400",
    Cancelled: "border-red-500/20 bg-red-500/10 text-red-400",
  };
  return (
    <span className={`text-[9px] font-mono font-bold uppercase px-2 py-0.5 rounded border ${styles[status] || styles.Scheduled} whitespace-nowrap tracking-wider`}>
      {`[ ${status.toUpperCase()} ]`}
    </span>
  );
}

function TypeBadge({ type }: { type: string }) {
  return (
    <span className="text-[9px] font-mono font-bold uppercase px-2 py-0.5 rounded border border-[#9b6dff]/20 bg-[#9b6dff]/10 text-[#9b6dff] tracking-wider whitespace-nowrap">
      {type}
    </span>
  );
}

function getInitials(name: string) {
  return name.split(" ").map((w) => w[0]).join("").toUpperCase().slice(0, 2);
}

// ── Schedule Interview Modal ─────────────────────────────

function ScheduleModal({
  onClose,
  onSaved,
}: {
  onClose: () => void;
  onSaved: () => void;
}) {
  const [candidates, setCandidates] = useState<CandidateOption[]>([]);
  const [jobs, setJobs] = useState<Job[]>([]);
  const [candidateSearch, setCandidateSearch] = useState("");
  const [showCandidateDropdown, setShowCandidateDropdown] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    candidate_id: 0,
    job_id: 0,
    interview_type: "Technical",
    date: "",
    time: "10:00",
    duration_minutes: 60,
    interviewer_name: "",
    location: "",
    meeting_link: "",
  });

  const selectedCandidate = candidates.find((c) => c.id === form.candidate_id);

  useEffect(() => {
    apiFetch(`${API.replace(API_BASE, "")}&action=candidates&limit=200`, { })
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setCandidates(d.candidates.map((c: any) => ({ id: c.id, name: c.name, email: c.email })));
      })
      .catch(() => {});

    apiFetch(`${API.replace(API_BASE, "")}&action=jobs`, { })
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setJobs(d.jobs.map((j: any) => ({ id: j.id, title: j.title })));
      })
      .catch(() => {});
  }, []);

  const filteredCandidates = candidates.filter((c) =>
    c.name.toLowerCase().includes(candidateSearch.toLowerCase()) ||
    c.email.toLowerCase().includes(candidateSearch.toLowerCase())
  );

  const handleSubmit = async () => {
    if (!form.candidate_id || !form.job_id || !form.date) return;
    setSaving(true);

    let applicationId = 0;
    try {
      const jobRes = await apiFetch(`${API.replace(API_BASE, "")}&action=job&id=${form.job_id}`, { });
      const jobData = await jobRes.json();
      if (jobData.success) {
        const existing = jobData.job.applications?.find((a: any) => a.candidate_id === form.candidate_id);
        if (existing) {
          applicationId = existing.id;
        } else {
          const appRes = await apiFetch(API.replace(API_BASE, ""), {
            method: "POST",
            headers: { 
              "Content-Type": "application/json",

            },
            body: JSON.stringify({ action: "add_application", candidate_id: form.candidate_id, job_id: form.job_id }),
          });
          const appData = await appRes.json();
          if (appData.success) applicationId = appData.application_id;
        }
      }
    } catch { /* fall through */ }

    if (!applicationId) { setSaving(false); return; }

    const scheduled_at = `${form.date} ${form.time}:00`;
    try {
      await apiFetch(API.replace(API_BASE, ""), {
        method: "POST",
        headers: { 
          "Content-Type": "application/json",

        },
        body: JSON.stringify({
          action: "add_interview",
          application_id: applicationId,
          candidate_id: form.candidate_id,
          job_id: form.job_id,
          interview_type: form.interview_type,
          scheduled_at,
          duration_minutes: form.duration_minutes,
          interviewer_name: form.interviewer_name,
          location: form.location,
          meeting_link: form.meeting_link,
        }),
      });
    } catch (err) {
      console.error(err);
    } finally {
      setSaving(false);
      onSaved();
    }
  };

  const inputCls = "w-full px-3 py-2 rounded-lg bg-muted border border-border text-foreground text-xs font-mono placeholder-gray-600 outline-none focus:border-[#00e07a] focus:shadow-[0_0_10px_rgba(0,224,122,0.15)] transition-all";
  const labelCls = "block text-[10px] font-mono font-bold text-muted-foreground mb-1 uppercase tracking-wider";

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" onClick={onClose}>
      <div className="w-full max-w-lg bg-background border border-border rounded-2xl p-6 shadow-2xl space-y-4" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-bold font-['Space_Grotesk'] text-foreground uppercase tracking-wider">// INITIALIZE INTERVIEW SESSION_</h2>
          <button onClick={onClose} className="p-1 rounded bg-secondary text-muted-foreground hover:text-foreground cursor-pointer border-0"><X size={16} /></button>
        </div>

        {/* Candidate search */}
        <div className="relative">
          <label className={labelCls}>Candidate *</label>
          <div className="relative">
            <input
              value={selectedCandidate ? selectedCandidate.name : candidateSearch}
              onChange={(e) => { setCandidateSearch(e.target.value); setForm((f) => ({ ...f, candidate_id: 0 })); setShowCandidateDropdown(true); }}
              onFocus={() => setShowCandidateDropdown(true)}
              className={inputCls}
              placeholder="Type to search candidate records..."
            />
            <Search size={14} className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          </div>
          {showCandidateDropdown && filteredCandidates.length > 0 && (
            <div className="absolute z-10 mt-1 w-full max-h-40 overflow-y-auto bg-background border border-border rounded-lg shadow-xl font-mono text-xs">
              {filteredCandidates.slice(0, 8).map((c) => (
                <button key={c.id} onClick={() => { setForm((f) => ({ ...f, candidate_id: c.id })); setCandidateSearch(c.name); setShowCandidateDropdown(false); }}
                  className="w-full px-3 py-2 text-left text-foreground hover:bg-accent hover:text-primary cursor-pointer bg-transparent border-0">
                  {c.name} <span className="text-muted-foreground text-[10px] ml-1">{c.email}</span>
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Job */}
        <div>
          <label className={labelCls}>Job *</label>
          <select value={form.job_id} onChange={(e) => setForm((f) => ({ ...f, job_id: +e.target.value }))} className={inputCls + " cursor-pointer"}>
            <option value={0} className="bg-background text-muted-foreground">Select active job...</option>
            {jobs.map((j) => <option key={j.id} value={j.id} className="bg-background text-foreground">{j.title}</option>)}
          </select>
        </div>

        {/* Type + Duration */}
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className={labelCls}>Type</label>
            <select value={form.interview_type} onChange={(e) => setForm((f) => ({ ...f, interview_type: e.target.value }))} className={inputCls + " cursor-pointer"}>
              {["Technical", "Behavioral", "Culture Fit", "Phone Screen", "Panel", "Final Round", "General"].map((t) => <option key={t} value={t} className="bg-background text-foreground">{t}</option>)}
            </select>
          </div>
          <div>
            <label className={labelCls}>Duration (min)</label>
            <input type="number" value={form.duration_minutes} onChange={(e) => setForm((f) => ({ ...f, duration_minutes: +e.target.value }))} className={inputCls} />
          </div>
        </div>

        {/* Date + Time */}
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className={labelCls}>Date *</label>
            <input type="date" value={form.date} onChange={(e) => setForm((f) => ({ ...f, date: e.target.value }))} className={inputCls} />
          </div>
          <div>
            <label className={labelCls}>Time</label>
            <input type="time" value={form.time} onChange={(e) => setForm((f) => ({ ...f, time: e.target.value }))} className={inputCls} />
          </div>
        </div>

        {/* Interviewer */}
        <div>
          <label className={labelCls}>Interviewer</label>
          <input value={form.interviewer_name} onChange={(e) => setForm((f) => ({ ...f, interviewer_name: e.target.value }))} className={inputCls} placeholder="Interviewer name..." />
        </div>

        {/* Location + Meeting Link */}
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className={labelCls}>Location</label>
            <input value={form.location} onChange={(e) => setForm((f) => ({ ...f, location: e.target.value }))} className={inputCls} placeholder="e.g. Zoom, Team Office..." />
          </div>
          <div>
            <label className={labelCls}>Meeting Link</label>
            <input value={form.meeting_link} onChange={(e) => setForm((f) => ({ ...f, meeting_link: e.target.value }))} className={inputCls} placeholder="https://..." />
          </div>
        </div>

        <div className="flex justify-end gap-3 pt-2 font-mono">
          <button onClick={onClose} className="px-4 py-2 rounded-lg border border-border text-muted-foreground text-xs hover:bg-secondary cursor-pointer bg-transparent">[ CANCEL ]</button>
          <button onClick={handleSubmit} disabled={saving || !form.candidate_id || !form.job_id || !form.date}
            className="px-5 py-2 rounded-lg bg-primary text-primary-foreground text-xs font-bold hover:opacity-90 disabled:opacity-40 cursor-pointer flex items-center gap-2 border-0">
            {saving && <Loader2 size={12} className="animate-spin" />} [ SCHEDULE ]
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Scorecard Modal ──────────────────────────────────────

function ScorecardModal({
  interview,
  onClose,
  onSaved,
}: {
  interview: Interview;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [form, setForm] = useState({
    technical_score: 7,
    communication_score: 7,
    culture_score: 7,
    overall_score: 7,
    recommendation: "Maybe" as string,
    strengths: "",
    concerns: "",
    notes: "",
    evaluator_name: "",
  });
  const [saving, setSaving] = useState(false);

  const handleSubmit = async () => {
    setSaving(true);
    try {
      await apiFetch(API.replace(API_BASE, ""), {
        method: "POST",
        headers: { 
          "Content-Type": "application/json",

        },
        body: JSON.stringify({ action: "add_scorecard", interview_id: interview.id, ...form }),
      });
    } catch (err) {
      console.error(err);
    } finally {
      setSaving(false);
      onSaved();
    }
  };

  const inputCls = "w-full px-3 py-2 rounded-lg bg-muted border border-border text-foreground text-xs font-mono placeholder-gray-600 outline-none focus:border-[#00e07a] focus:shadow-[0_0_10px_rgba(0,224,122,0.15)] transition-all";
  const labelCls = "block text-[10px] font-mono font-bold text-muted-foreground mb-1 uppercase tracking-wider";

  const ScoreSlider = ({ label, field }: { label: string; field: string }) => (
    <div className="font-mono">
      <div className="flex justify-between text-[10px] mb-1">
        <span className="text-muted-foreground uppercase">{label}</span>
        <span className="text-foreground font-bold">{(form as any)[field]}/10</span>
      </div>
      <input type="range" min={1} max={10} value={(form as any)[field]}
        onChange={(e) => setForm((f) => ({ ...f, [field]: +e.target.value }))}
        className="w-full accent-[#00e07a] cursor-pointer" />
    </div>
  );

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" onClick={onClose}>
      <div className="w-full max-w-lg max-h-[90vh] overflow-y-auto bg-background border border-border rounded-2xl p-6 shadow-2xl space-y-4" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-sm font-bold font-['Space_Grotesk'] text-foreground uppercase tracking-wider">// SUBMIT SCORECARD FEEDBACK_</h2>
            <p className="text-[10px] text-muted-foreground font-mono mt-0.5 uppercase">{interview.candidate_name} · {interview.interview_type}</p>
          </div>
          <button onClick={onClose} className="p-1 rounded bg-secondary text-muted-foreground hover:text-foreground cursor-pointer border-0"><X size={16} /></button>
        </div>

        <div>
          <label className={labelCls}>Evaluator Name</label>
          <input value={form.evaluator_name} onChange={(e) => setForm((f) => ({ ...f, evaluator_name: e.target.value }))} className={inputCls} placeholder="Evaluator name..." />
        </div>

        <div className="space-y-3 bg-white/[0.01] rounded-xl p-4 border border-border">
          <ScoreSlider label="Technical Skills" field="technical_score" />
          <ScoreSlider label="Communication" field="communication_score" />
          <ScoreSlider label="Culture Fit" field="culture_score" />
          <ScoreSlider label="Overall Score" field="overall_score" />
        </div>

        <div>
          <label className={labelCls}>Recommendation</label>
          <select value={form.recommendation} onChange={(e) => setForm((f) => ({ ...f, recommendation: e.target.value }))} className={inputCls + " cursor-pointer"}>
            {["Strong Yes", "Yes", "Maybe", "No", "Strong No"].map((r) => <option key={r} value={r} className="bg-background text-foreground">{r}</option>)}
          </select>
        </div>

        <div>
          <label className={labelCls}>Strengths</label>
          <textarea value={form.strengths} onChange={(e) => setForm((f) => ({ ...f, strengths: e.target.value }))} className={inputCls + " h-16 resize-none"} placeholder="Observed strengths..." />
        </div>
        <div>
          <label className={labelCls}>Concerns</label>
          <textarea value={form.concerns} onChange={(e) => setForm((f) => ({ ...f, concerns: e.target.value }))} className={inputCls + " h-16 resize-none"} placeholder="Observed concerns..." />
        </div>
        <div>
          <label className={labelCls}>Additional Notes</label>
          <textarea value={form.notes} onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))} className={inputCls + " h-16 resize-none"} placeholder="Evaluation logs..." />
        </div>

        <div className="flex justify-end gap-3 pt-2 font-mono">
          <button onClick={onClose} className="px-4 py-2 rounded-lg border border-border text-muted-foreground text-xs hover:bg-secondary cursor-pointer bg-transparent">[ CANCEL ]</button>
          <button onClick={handleSubmit} disabled={saving}
            className="px-5 py-2 rounded-lg bg-primary text-primary-foreground text-xs font-bold hover:opacity-90 disabled:opacity-40 cursor-pointer flex items-center gap-2 border-0">
            {saving && <Loader2 size={12} className="animate-spin" />} [ SUBMIT ]
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Interview Card ───────────────────────────────────────

function InterviewCard({
  interview,
  onViewChange,
  onScorecard,
}: {
  interview: Interview;
  onViewChange: (v: ViewState) => void;
  onScorecard: (iv: Interview) => void;
}) {
  const isPhysical = interview.location && (interview.location.toLowerCase().includes("office") || interview.location.toLowerCase().includes("hq") || interview.location.toLowerCase().includes("room"));
  const methodIcon = isPhysical
    ? <MapPin size={13} className="text-cyan-400" />
    : <Video size={13} className="text-[#9b6dff]" />;

  return (
    <div className={`p-4 rounded-xl border transition-all hover:border-[#9b6dff]/40 flex flex-col md:flex-row gap-4 justify-between items-start md:items-center ${
      interview.is_today
        ? "bg-[#9b6dff]/5 border-[#9b6dff]/20 shadow-[0_0_15px_rgba(155,109,255,0.08)]"
        : "bg-background border-border"
    }`}>
      {/* Left: Avatar + Info */}
      <div className="flex gap-4 items-center flex-1 min-w-0">
        <div className="w-10 h-10 rounded-lg bg-white/[0.03] border border-border flex items-center justify-center flex-shrink-0 font-mono text-xs font-bold text-foreground">
          {`[ ${getInitials(interview.candidate_name)} ]`}
        </div>
        <div className="min-w-0">
          <button onClick={() => onViewChange({ view: "Candidate Profile", candidateId: interview.candidate_id })}
            className="text-sm font-semibold text-foreground hover:text-primary transition-colors cursor-pointer bg-transparent border-0 p-0 text-left">
            {interview.candidate_name}
          </button>
          <div className="flex items-center gap-2 mt-0.5 flex-wrap">
            <span className="text-xs text-cyan-400 font-medium font-mono">{interview.job_title}</span>
            <TypeBadge type={interview.interview_type} />
          </div>
          <div className="flex items-center gap-4 mt-2 text-[10px] text-muted-foreground flex-wrap font-mono">
            <span className="flex items-center gap-1.5">
              <Clock size={11} className="text-[#9b6dff]" />
              {interview.formatted_time} · {interview.duration_minutes}min
            </span>
            {interview.interviewer_name && (
              <span className="flex items-center gap-1.5">
                <UserCheck size={11} className="text-pink-500" />
                {interview.interviewer_name}
              </span>
            )}
            {interview.location && (
              <span className="flex items-center gap-1.5">
                {methodIcon}
                {interview.location}
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Right: Status + Actions */}
      <div className="flex md:flex-col items-end gap-3 justify-between w-full md:w-auto border-t md:border-t-0 border-border pt-3 md:pt-0 mt-1 md:mt-0 font-mono">
        <StatusBadge status={interview.status} />
        <div className="flex gap-2">
          {interview.is_today && interview.meeting_link && (
            <a href={interview.meeting_link} target="_blank" rel="noopener noreferrer"
              className="px-3 py-1.5 bg-primary hover:opacity-90 text-[9px] font-bold uppercase rounded hover:opacity-90 cursor-pointer text-primary-foreground flex items-center gap-1.5 no-underline border-0">
              <ExternalLink size={10} /> [ JOIN SESSION ]
            </a>
          )}
          {interview.status === "Completed" && interview.scorecard_count === 0 && (
            <button onClick={() => onScorecard(interview)}
              className="px-3 py-1.5 bg-[#9b6dff]/10 border border-[#9b6dff]/20 text-[#9b6dff] text-[9px] font-bold uppercase rounded hover:bg-[#9b6dff]/20 cursor-pointer flex items-center gap-1.5">
              <ClipboardList size={10} /> [ SCORECARD ]
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

// ── Main Component ───────────────────────────────────────

export function InterviewsPage({ onViewChange }: Props) {
  const [interviews, setInterviews] = useState<Interview[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("");
  const [jobFilter, setJobFilter] = useState(0);
  const [jobs, setJobs] = useState<Job[]>([]);
  const [showSchedule, setShowSchedule] = useState(false);
  const [scorecardInterview, setScorecardInterview] = useState<Interview | null>(null);

  const fetchInterviews = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ action: "interviews" });
      if (statusFilter) params.set("status", statusFilter);
      if (jobFilter) params.set("job_id", String(jobFilter));
      const res = await apiFetch(`${API.replace(API_BASE, "")}&${params}`, { });
      const data = await res.json();
      if (data.success) setInterviews(data.interviews);
    } catch { /* silent */ }
    setLoading(false);
  }, [statusFilter, jobFilter]);

  useEffect(() => {
    fetchInterviews();
  }, [fetchInterviews]);

  useEffect(() => {
    apiFetch(`${API.replace(API_BASE, "")}&action=jobs`, { })
      .then((r) => r.json())
      .then((d) => { if (d.success) setJobs(d.jobs.map((j: any) => ({ id: j.id, title: j.title }))); })
      .catch(() => {});
  }, []);

  const groupInterviews = () => {
    const today: Interview[] = [];
    const tomorrow: Interview[] = [];
    const thisWeek: Interview[] = [];
    const later: Interview[] = [];

    const now = new Date();
    const todayStr = now.toISOString().slice(0, 10);
    const tomorrowDate = new Date(now);
    tomorrowDate.setDate(tomorrowDate.getDate() + 1);
    const tomorrowStr = tomorrowDate.toISOString().slice(0, 10);
    const weekEnd = new Date(now);
    weekEnd.setDate(weekEnd.getDate() + (7 - weekEnd.getDay()));

    for (const iv of interviews) {
      const ivDate = iv.scheduled_at?.slice(0, 10) || "";
      if (iv.is_today || ivDate === todayStr) today.push(iv);
      else if (ivDate === tomorrowStr) tomorrow.push(iv);
      else if (new Date(ivDate) <= weekEnd) thisWeek.push(iv);
      else later.push(iv);
    }

    return [
      { label: "Today", items: today, icon: "🔴" },
      { label: "Tomorrow", items: tomorrow, icon: "🟡" },
      { label: "This Week", items: thisWeek, icon: "🔵" },
      { label: "Later", items: later, icon: "⚪" },
    ].filter((g) => g.items.length > 0);
  };

  const groups = groupInterviews();
  const selectCls = "px-3 py-2 rounded-lg bg-muted border border-border text-foreground text-xs font-mono focus:outline-none focus:border-[#00e07a] cursor-pointer appearance-none";

  return (
    <div className="flex-1 flex flex-col overflow-y-auto px-8 py-6 text-foreground font-sans relative" >
      {/* Background glows */}
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-primary blur-[120px] opacity-[0.06] pointer-events-none z-0" />
      <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#9b6dff] blur-[140px] opacity-[0.05] pointer-events-none z-0" />

      {/* Header */}
      <div className="relative z-10 flex items-center justify-between mb-8 border-b border-border pb-6">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground font-['Space_Grotesk'] flex items-center gap-1.5">
            // SCHEDULED SESSIONS // CANDIDATE INTERVIEWS
            <span className="inline-block w-2.5 h-5 bg-primary blink"></span>
          </h1>
          <p className="text-xs font-mono text-muted-foreground mt-1 uppercase tracking-wider">
            {interviews.length} interview{interviews.length !== 1 ? "s" : ""} · {interviews.filter((i) => i.is_today).length} today
          </p>
        </div>
        <button onClick={() => setShowSchedule(true)}
          className="px-4 py-2.5 rounded-xl bg-primary hover:opacity-90 text-primary-foreground font-mono font-bold text-xs transition-all cursor-pointer flex items-center gap-2 shadow-lg shadow-green-500/10 border-0">
          <Plus size={16} /> [ SCHEDULE INTERVIEW ]
        </button>
      </div>

      {/* Filters */}
      <div className="relative z-10 flex items-center gap-3 mb-6 flex-wrap font-mono">
        <Filter size={14} className="text-muted-foreground" />
        <div className="relative">
          <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className={selectCls}>
            <option value="" className="bg-background">All Statuses</option>
            <option value="Scheduled" className="bg-background">Scheduled</option>
            <option value="Completed" className="bg-background">Completed</option>
            <option value="Cancelled" className="bg-background">Cancelled</option>
          </select>
        </div>
        <div className="relative">
          <select value={jobFilter} onChange={(e) => setJobFilter(+e.target.value)} className={selectCls}>
            <option value={0} className="bg-background">All Jobs</option>
            {jobs.map((j) => <option key={j.id} value={j.id} className="bg-background">{j.title}</option>)}
          </select>
        </div>
      </div>

      {/* Content */}
      {loading ? (
        <div className="relative z-10 space-y-8 max-w-4xl pb-8">
          <div className="space-y-4">
            {[1, 2, 3].map((i) => <SkeletonCard key={i} />)}
          </div>
        </div>
      ) : interviews.length === 0 ? (
        <div className="relative z-10 flex-1 flex flex-col items-center justify-center py-20 text-center font-mono">
          <div className="w-16 h-16 rounded-2xl bg-primary border border-[#00e07a]/15 flex items-center justify-center mb-4">
            <Calendar size={28} className="text-primary" />
          </div>
          <h3 className="text-sm font-bold text-foreground mb-2 font-['Space_Grotesk']">NO INTERVIEWS SCHEDULED</h3>
          <p className="text-xs text-muted-foreground max-w-xs mb-4">Schedule your first interview session to start tracking your recruitment pipeline.</p>
          <button onClick={() => setShowSchedule(true)}
            className="px-4 py-2.5 rounded-xl bg-primary border border-[#00e07a]/20 text-primary hover:bg-primary cursor-pointer text-xs font-bold transition-all">
            [ SCHEDULE INTERVIEW ]
          </button>
        </div>
      ) : (
        <div className="relative z-10 space-y-8 max-w-4xl pb-8">
          {groups.map((group) => (
            <div key={group.label}>
              <div className="flex items-center gap-2 mb-3 font-mono">
                <span className="text-sm">{group.icon}</span>
                <h3 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">{`// ${group.label.toUpperCase()}`}</h3>
                <span className="text-[10px] text-muted-foreground bg-muted border border-border px-2 py-0.5 rounded-full font-bold">{group.items.length}</span>
              </div>
              <div className="space-y-3">
                {group.items.map((iv) => (
                  <InterviewCard key={iv.id} interview={iv} onViewChange={onViewChange} onScorecard={setScorecardInterview} />
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modals */}
      {showSchedule && (
        <ScheduleModal onClose={() => setShowSchedule(false)} onSaved={() => { setShowSchedule(false); fetchInterviews(); }} />
      )}
      {scorecardInterview && (
        <ScorecardModal interview={scorecardInterview} onClose={() => setScorecardInterview(null)} onSaved={() => { setScorecardInterview(null); fetchInterviews(); }} />
      )}
    </div>
  );
}
