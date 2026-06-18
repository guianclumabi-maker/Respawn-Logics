import { useState, useEffect, useCallback } from "react";
import {
  ArrowLeft,
  Brain,
  Briefcase,
  Calendar,
  Clock,
  Database,
  Mail,
  MapPin,
  MessageSquare,
  Phone,
  Plus,
  Send,
  Star,
  User,
  UserCheck,
  Users,
  X,
  Archive,
  DollarSign,
  Activity,
  ChevronRight,
  ClipboardList,
} from "lucide-react";

// ─── API ────────────────────────────────────────────────────────────────────────

const API_BASE = import.meta.env.VITE_API_BASE_URL;
const API = `${API_BASE}/api/index.php?route=candidates`;

// ─── Types ──────────────────────────────────────────────────────────────────────

type ViewState = {
  view: string;
  jobId?: number;
  candidateId?: number;
  poolId?: number;
};

type Application = {
  id: number;
  job_title: string;
  stage: string;
  days_in_stage: number;
  formatted_applied: string;
  department: string;
};

type Scorecard = {
  id: number;
  score: number;
};

type Interview = {
  id: number;
  job_title: string;
  interview_type: string;
  formatted_date: string;
  formatted_time: string;
  status: string;
  interviewer_name: string;
  scorecards: Scorecard[];
};

type Note = {
  id: number;
  author_name: string;
  content: string;
  note_type: string;
  created_at: string;
};

type Pool = {
  id: number;
  name: string;
};

type ActivityItem = {
  action: string;
  description: string;
  time_ago: string;
};

type CandidateData = {
  id: number;
  name: string;
  email: string;
  phone: string;
  location: string;
  skills: string;
  experience_years: number;
  salary_expectation: number;
  source: string;
  tags: string[];
  assigned_recruiter: string;
  assigned_hiring_manager: string;
  ai_summary: string;
  status: string;
  applications: Application[];
  interviews: Interview[];
  notes: Note[];
  pools: Pool[];
  activity_log: ActivityItem[];
};

type Props = {
  onViewChange: (v: ViewState) => void;
  candidateId: number;
};

// ─── Helpers ────────────────────────────────────────────────────────────────────

function statusBadge(status: string) {
  const map: Record<string, { bg: string; border: string; text: string }> = {
    Active: { bg: "rgba(0, 224, 122, 0.1)", border: "rgba(0, 224, 122, 0.2)", text: "#00e07a" },
    Archived: { bg: "rgba(107, 114, 128, 0.1)", border: "rgba(107, 114, 128, 0.2)", text: "#9ca3af" },
    Blacklisted: { bg: "rgba(239, 68, 68, 0.1)", border: "rgba(239, 68, 68, 0.2)", text: "#f87171" },
  };
  return map[status] ?? map.Active;
}

function stageBadge(stage: string) {
  const map: Record<string, { bg: string; border: string; text: string }> = {
    Applied: { bg: "rgba(59, 130, 246, 0.1)", border: "rgba(59, 130, 246, 0.2)", text: "#60a5fa" },
    Review: { bg: "rgba(168, 85, 247, 0.1)", border: "rgba(168, 85, 247, 0.2)", text: "#c084fc" },
    "Phone Screen": { bg: "rgba(20, 184, 166, 0.1)", border: "rgba(20, 184, 166, 0.2)", text: "#2dd4bf" },
    Interview: { bg: "rgba(245, 158, 11, 0.1)", border: "rgba(245, 158, 11, 0.2)", text: "#fbbf24" },
    "F2F Interview": { bg: "rgba(245, 158, 11, 0.1)", border: "rgba(245, 158, 11, 0.2)", text: "#fbbf24" },
    Offer: { bg: "rgba(236, 72, 153, 0.1)", border: "rgba(236, 72, 153, 0.2)", text: "#f472b6" },
    Hired: { bg: "rgba(16, 185, 129, 0.1)", border: "rgba(16, 185, 129, 0.2)", text: "#34d399" },
    Rejected: { bg: "rgba(239, 68, 68, 0.1)", border: "rgba(239, 68, 68, 0.2)", text: "#f87171" },
  };
  return map[stage] ?? { bg: "rgba(107, 114, 128, 0.1)", border: "rgba(107, 114, 128, 0.2)", text: "#9ca3af" };
}

function slaColor(days: number): string {
  if (days <= 3) return "#00e07a";
  if (days <= 7) return "#fbbf24";
  return "#f87171";
}

function interviewStatusBadge(status: string) {
  const map: Record<string, { bg: string; border: string; text: string }> = {
    Scheduled: { bg: "rgba(59, 130, 246, 0.1)", border: "rgba(59, 130, 246, 0.2)", text: "#60a5fa" },
    Completed: { bg: "rgba(16, 185, 129, 0.1)", border: "rgba(16, 185, 129, 0.2)", text: "#34d399" },
    Cancelled: { bg: "rgba(239, 68, 68, 0.1)", border: "rgba(239, 68, 68, 0.2)", text: "#f87171" },
    "No Show": { bg: "rgba(245, 158, 11, 0.1)", border: "rgba(245, 158, 11, 0.2)", text: "#fbbf24" },
  };
  return map[status] ?? map.Scheduled;
}

function noteTypeColor(type: string) {
  const map: Record<string, string> = {
    Comment: "#60a5fa",
    Feedback: "#00e07a",
    Internal: "#fbbf24",
  };
  return map[type] ?? "#9ca3af";
}

// ─── Add Note Modal ─────────────────────────────────────────────────────────────

function AddNoteModal({
  onSubmit,
  onClose,
}: {
  onSubmit: (content: string, noteType: string) => void;
  onClose: () => void;
}) {
  const [content, setContent] = useState("");
  const [noteType, setNoteType] = useState("Comment");

  const inputCls = "w-full px-3 py-2.5 rounded-xl border text-xs font-mono outline-none bg-[#161922] border-white/10 text-white focus:border-[#00e07a] focus:shadow-[0_0_10px_rgba(0,224,122,0.15)] transition-all";
  const labelCls = "text-[10px] uppercase font-mono font-bold text-gray-500 block mb-1";

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" onClick={onClose}>
      <div className="bg-[#0f1422] border border-white/10 rounded-2xl shadow-2xl w-full max-w-md p-6 text-white" onClick={e => e.stopPropagation()}>
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-sm font-bold font-['Space_Grotesk'] tracking-wide">// ADD COLLABORATION NOTE_</h3>
          <button
            onClick={onClose}
            className="p-1 rounded bg-white/[0.04] text-gray-400 hover:text-white cursor-pointer border-0"
          >
            <X size={16} />
          </button>
        </div>
        <div className="space-y-4">
          <div>
            <label className={labelCls}>Note Type</label>
            <select
              value={noteType}
              onChange={(e) => setNoteType(e.target.value)}
              className={inputCls}
            >
              <option value="Comment">Comment</option>
              <option value="Feedback">Feedback</option>
              <option value="Internal">Internal</option>
            </select>
          </div>
          <div>
            <label className={labelCls}>Content</label>
            <textarea
              value={content}
              onChange={(e) => setContent(e.target.value)}
              placeholder="Write your note logs..."
              rows={4}
              className={`${inputCls} resize-none`}
            />
          </div>
        </div>
        <div className="flex gap-2 mt-5 font-mono">
          <button
            onClick={onClose}
            className="flex-1 px-4 py-2 rounded-xl border text-xs font-bold bg-transparent hover:bg-white/5 cursor-pointer text-gray-400 border-white/10"
          >
            [ CANCEL ]
          </button>
          <button
            disabled={!content.trim()}
            onClick={() => {
              onSubmit(content.trim(), noteType);
              onClose();
            }}
            className="flex-1 px-4 py-2 rounded-xl text-xs font-bold bg-[#00e07a] text-black hover:opacity-90 disabled:opacity-40 cursor-pointer border-0"
          >
            [ ADD NOTE ]
          </button>
        </div>
      </div>
    </div>
  );
}

// ─── Add to Pool Modal ──────────────────────────────────────────────────────────

function AddToPoolModal({
  onSubmit,
  onClose,
  existingPools,
}: {
  onSubmit: (poolId: number) => void;
  onClose: () => void;
  existingPools: number[];
}) {
  const [pools, setPools] = useState<Pool[]>([]);
  const [selected, setSelected] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch(`${API}&action=talent_pools`)
      .then((r) => r.json())
      .then((d) => {
        if (d.pools) setPools(d.pools.filter((p: Pool) => !existingPools.includes(p.id)));
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [existingPools]);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" onClick={onClose}>
      <div className="bg-[#0f1422] border border-white/10 rounded-2xl shadow-2xl w-full max-w-md p-6 text-white font-mono" onClick={e => e.stopPropagation()}>
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-sm font-bold font-['Space_Grotesk'] tracking-wide text-white">// ADD TO TALENT POOL_</h3>
          <button
            onClick={onClose}
            className="p-1 rounded bg-white/[0.04] text-gray-400 hover:text-white cursor-pointer border-0"
          >
            <X size={16} />
          </button>
        </div>
        {loading ? (
          <div className="py-8 text-center text-xs text-gray-500">Loading pools...</div>
        ) : pools.length === 0 ? (
          <div className="py-8 text-center text-xs text-gray-500">
            No available talent pools found.
          </div>
        ) : (
          <div className="space-y-1.5 max-h-60 overflow-y-auto scrollbar-thin pr-1">
            {pools.map((pool) => (
              <button
                key={pool.id}
                onClick={() => setSelected(pool.id)}
                className="flex items-center justify-between w-full px-3.5 py-2.5 rounded-xl border text-xs transition-all cursor-pointer text-left"
                style={{
                  borderColor: selected === pool.id ? "#00e07a" : "rgba(255,255,255,0.08)",
                  backgroundColor:
                    selected === pool.id ? "rgba(0,224,122,0.12)" : "#161922",
                  color: selected === pool.id ? "#ffffff" : "#9ca3af",
                }}
              >
                <span className="flex items-center gap-2">
                  <Database size={13} />
                  {pool.name}
                </span>
              </button>
            ))}
          </div>
        )}
        <div className="flex gap-2 mt-5">
          <button
            onClick={onClose}
            className="flex-1 px-4 py-2 rounded-xl border text-xs font-bold bg-transparent hover:bg-white/5 cursor-pointer text-gray-400 border-white/10"
          >
            [ CANCEL ]
          </button>
          <button
            disabled={selected === null}
            onClick={() => {
              if (selected !== null) {
                onSubmit(selected);
                onClose();
              }
            }}
            className="flex-1 px-4 py-2 rounded-xl text-xs font-bold bg-[#00e07a] text-black hover:opacity-90 disabled:opacity-40 cursor-pointer border-0"
          >
            [ ADD TO POOL ]
          </button>
        </div>
      </div>
    </div>
  );
}

// ─── Inline Note Form ───────────────────────────────────────────────────────────

function InlineNoteForm({
  onSubmit,
}: {
  onSubmit: (content: string, noteType: string) => void;
}) {
  const [content, setContent] = useState("");
  const [noteType, setNoteType] = useState("Comment");

  const handleSubmit = () => {
    if (!content.trim()) return;
    onSubmit(content.trim(), noteType);
    setContent("");
  };

  return (
    <div className="mt-3 space-y-2 font-mono">
      <textarea
        value={content}
        onChange={(e) => setContent(e.target.value)}
        placeholder="Write a new note log..."
        rows={3}
        className="w-full px-3 py-2.5 rounded-xl border text-xs outline-none bg-[#161922]/50 border-white/[0.08] text-white resize-none placeholder-gray-600 focus:border-[#00e07a] transition-all"
      />
      <div className="flex items-center justify-between">
        <select
          value={noteType}
          onChange={(e) => setNoteType(e.target.value)}
          className="px-2.5 py-1.5 rounded-lg border text-[10px] outline-none bg-[#161922] border-white/[0.08] text-gray-300 cursor-pointer"
        >
          <option value="Comment">Comment</option>
          <option value="Feedback">Feedback</option>
          <option value="Internal">Internal</option>
        </select>
        <button
          disabled={!content.trim()}
          onClick={handleSubmit}
          className="flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-[10px] font-bold bg-[#00e07a] text-black disabled:opacity-40 cursor-pointer hover:bg-[#00c9b1] border-0"
        >
          <Send size={10} />
          [ POST NOTE ]
        </button>
      </div>
    </div>
  );
}

// ─── Loading Skeleton ───────────────────────────────────────────────────────────

function ProfileSkeleton() {
  const shimmer =
    "animate-pulse bg-gradient-to-r from-white/[0.03] via-white/[0.06] to-white/[0.03] rounded-lg";

  return (
    <div className="flex-1 flex flex-col overflow-y-auto px-8 py-6 text-white font-sans relative scrollbar-thin" >
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-[#00e07a] blur-[120px] opacity-10 pointer-events-none z-0" />
      <div className="relative z-10 space-y-6">
        <div className="flex items-center gap-4">
          <div className={`w-8 h-8 rounded-lg ${shimmer}`} />
          <div className="space-y-2 flex-1">
            <div className={`w-48 h-7 ${shimmer}`} />
            <div className={`w-64 h-4 ${shimmer}`} />
          </div>
        </div>
        <div className="grid grid-cols-5 gap-6">
          <div className="col-span-3 space-y-5">
            <div className={`w-full h-32 rounded-xl ${shimmer}`} />
            <div className={`w-full h-24 rounded-xl ${shimmer}`} />
            <div className={`w-full h-40 rounded-xl ${shimmer}`} />
          </div>
          <div className="col-span-2 space-y-5">
            <div className={`w-full h-28 rounded-xl ${shimmer}`} />
            <div className={`w-full h-20 rounded-xl ${shimmer}`} />
            <div className={`w-full h-36 rounded-xl ${shimmer}`} />
          </div>
        </div>
      </div>
    </div>
  );
}

// ─── Main Component ─────────────────────────────────────────────────────────────

export function CandidateProfile({ onViewChange, candidateId }: Props) {
  const [candidate, setCandidate] = useState<CandidateData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showNoteModal, setShowNoteModal] = useState(false);
  const [showPoolModal, setShowPoolModal] = useState(false);

  const fetchCandidate = useCallback(() => {
    setLoading(true);
    setError(null);
    fetch(`${API}&action=candidate&id=${candidateId}`)
      .then((r) => {
        if (!r.ok) throw new Error("Failed to fetch candidate");
        return r.json();
      })
      .then((d) => {
        if (d.candidate) {
          setCandidate(d.candidate);
        } else {
          setError("Candidate record offline or missing");
        }
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [candidateId]);

  useEffect(() => {
    fetchCandidate();
  }, [fetchCandidate]);

  const handleAddNote = (content: string, noteType: string) => {
    fetch(API, {
      method: "POST",
      headers: { 
        "Content-Type": "application/json",
        "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
      },
      body: JSON.stringify({
        action: "add_note",
        candidate_id: candidateId,
        content,
        note_type: noteType,
      }),
    })
      .then((r) => r.json())
      .then(() => fetchCandidate())
      .catch((e) => console.error("Error adding note:", e));
  };

  const handleAddToPool = (poolId: number) => {
    fetch(API, {
      method: "POST",
      headers: { 
        "Content-Type": "application/json",
        "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
      },
      body: JSON.stringify({
        action: "add_to_pool",
        candidate_id: candidateId,
        pool_id: poolId,
      }),
    })
      .then((r) => r.json())
      .then(() => fetchCandidate())
      .catch((e) => console.error("Error adding to pool:", e));
  };

  if (loading) return <ProfileSkeleton />;

  if (error || !candidate) {
    return (
      <div className="flex-1 flex items-center justify-center bg-[#0b0f1a]" style={{ fontFamily: "Courier New, monospace" }}>
        <div className="text-center space-y-4">
          <div className="w-16 h-16 rounded bg-red-500/10 border border-red-500/20 flex items-center justify-center mx-auto text-red-400 font-bold">
            [ ! ]
          </div>
          <p className="text-sm text-gray-500 uppercase font-bold tracking-wider">{error || "CANDIDATE DATA UNAVAILABLE"}</p>
          <button
            onClick={() => onViewChange({ view: "Candidates" })}
            className="px-5 py-2.5 rounded bg-white/[0.02] border border-white/[0.08] text-gray-400 text-xs font-bold hover:text-white transition-all cursor-pointer"
          >
            [ ← RETURN TO DIRECTORY ]
          </button>
        </div>
      </div>
    );
  }

  const c = candidate;
  const sb = statusBadge(c.status);
  const skills = c.skills ? c.skills.split(",").map((s) => s.trim()).filter(Boolean) : [];
  const initials = c.name
    .split(" ")
    .map((w) => w[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);

  return (
    <div
      className="flex-1 flex flex-col overflow-y-auto px-8 py-6 text-white font-sans relative scrollbar-thin"
      
    >
      <style>{`
        .blink {
          animation: blink-anim 1.1s step-start infinite;
        }
        @keyframes blink-anim {
          0%, 100% { opacity: 1; }
          50%       { opacity: 0; }
        }
      `}</style>
      {/* Background glows */}
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-[#00e07a] blur-[120px] opacity-[0.06] pointer-events-none z-0" />
      <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#9b6dff] blur-[140px] opacity-[0.05] pointer-events-none z-0" />

      {/* Modals */}
      {showNoteModal && (
        <AddNoteModal onSubmit={handleAddNote} onClose={() => setShowNoteModal(false)} />
      )}
      {showPoolModal && (
        <AddToPoolModal
          onSubmit={handleAddToPool}
          onClose={() => setShowPoolModal(false)}
          existingPools={c.pools.map((p) => p.id)}
        />
      )}

      {/* ── Header Section ──────────────────────────────────────────────── */}
      <div className="relative z-10 border-b border-white/[0.04] pb-6 mb-6">
        {/* Back button */}
        <button
          onClick={() => onViewChange({ view: "Candidates" })}
          className="flex items-center gap-1.5 text-xs text-gray-400 hover:text-white mb-4 cursor-pointer transition-colors bg-transparent border-0 font-mono"
        >
          <ArrowLeft size={14} />
          [ BACK TO DIRECTORY ]
        </button>

        {/* Name + Badges */}
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded bg-[#0f1422] border border-white/[0.08] flex items-center justify-center font-mono text-base font-bold text-[#9b6dff] flex-shrink-0">
              {`[ ${initials} ]`}
            </div>
            <div>
              <div className="flex items-center gap-3 flex-wrap">
                <h1
                  className="text-2xl font-bold tracking-tight text-white font-['Space_Grotesk'] flex items-center gap-1.5"
                >
                  {c.name}
                  <span className="inline-block w-2.5 h-5 bg-[#00e07a] blink"></span>
                </h1>
                <span
                  className="text-[9px] font-mono font-bold uppercase px-2 py-0.5 rounded border"
                  style={{
                    backgroundColor: sb.bg,
                    borderColor: sb.border,
                    color: sb.text,
                  }}
                >
                  {`[ STATUS: ${c.status.toUpperCase()} ]`}
                </span>
                {c.source && (
                  <span className="text-[9px] font-mono font-bold uppercase px-2 py-0.5 rounded border border-blue-500/20 bg-blue-500/10 text-blue-400">
                    {`[ SOURCE: ${c.source.toUpperCase()} ]`}
                  </span>
                )}
              </div>

              {/* Tags */}
              {c.tags && c.tags.length > 0 && (
                <div className="flex items-center gap-1.5 mt-1.5 font-mono">
                  {c.tags.map((tag, i) => (
                    <span
                      key={i}
                      className="text-[9px] px-2 py-0.5 rounded border border-[#9b6dff]/20 bg-[#9b6dff]/10 text-[#9b6dff]"
                    >
                      {`#${tag.toUpperCase()}`}
                    </span>
                  ))}
                </div>
              )}

              {/* Recruiter / Hiring Manager */}
              <div className="flex items-center gap-4 mt-2 text-[10px] text-gray-500 font-mono">
                {c.assigned_recruiter && (
                  <span className="flex items-center gap-1.5">
                    <UserCheck size={12} className="text-[#00e07a]" />
                    RECRUITER: <span className="text-white font-semibold">{c.assigned_recruiter}</span>
                  </span>
                )}
                {c.assigned_hiring_manager && (
                  <span className="flex items-center gap-1.5">
                    <User size={12} className="text-[#9b6dff]" />
                    HM: <span className="text-white font-semibold">{c.assigned_hiring_manager}</span>
                  </span>
                )}
              </div>
            </div>
          </div>

          {/* Quick action buttons (header) */}
          <div className="flex items-center gap-2 flex-wrap font-mono">
            {[
              { label: "ADD NOTE", action: () => setShowNoteModal(true) },
              { label: "ADD TO POOL", action: () => setShowPoolModal(true) },
            ].map((btn) => (
              <button
                key={btn.label}
                onClick={btn.action}
                className="px-3 py-2 rounded-xl border bg-white/[0.02] border-white/[0.06] hover:border-[#00e07a]/40 text-[10px] font-bold text-gray-400 hover:text-white cursor-pointer transition-all"
              >
                {`[ ${btn.label} ]`}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* ── Two-Column Layout ─────────────────────────────────────────────── */}
      <div className="relative z-10 grid grid-cols-5 gap-6">
        {/* ── Left Column (60%) ──────────────────────────────────── */}
        <div className="col-span-5 lg:col-span-3 space-y-5">
          {/* AI Summary Card */}
          <div
            className="p-5 rounded-xl border bg-[#0f1422]/60 border-white/[0.06] backdrop-blur-md relative overflow-hidden"
          >
            <div className="flex items-center gap-2 mb-3">
              <Brain size={14} className="text-[#00e07a]" />
              <h3 className="text-xs font-bold tracking-wide text-white font-['Space_Grotesk'] uppercase">
                [ AI SYNAPSE PROFILE ANALYSIS ]
              </h3>
            </div>
            <p className="text-xs leading-relaxed text-gray-300 font-mono">
              {c.ai_summary || "AI analysis profiling is currently complete. No anomalies detected in resume parameters."}
            </p>
          </div>

          {/* Skills & Experience */}
          <div
            className="p-5 rounded-xl border bg-[#0f1422] border-white/[0.06] font-mono"
          >
            <h3 className="text-xs font-bold tracking-wide text-gray-400 uppercase mb-3 border-b border-white/[0.04] pb-2">
              // SKILLS & STATS SHEET
            </h3>
            {skills.length > 0 && (
              <div className="flex flex-wrap gap-1.5 mb-4">
                {skills.map((skill, i) => (
                  <span
                    key={i}
                    className="text-[9px] px-2 py-0.5 rounded border border-[#9b6dff]/20 bg-[#9b6dff]/10 text-[#9b6dff]"
                  >
                    {skill}
                  </span>
                ))}
              </div>
            )}
            <div className="grid grid-cols-3 gap-4 border-t border-white/[0.04] pt-3">
              <div>
                <span className="text-[10px] text-gray-500 block">EXPERIENCE</span>
                <span className="text-xs text-white font-bold">{c.experience_years} Years</span>
              </div>
              <div>
                <span className="text-[10px] text-gray-500 block">LOCATION</span>
                <span className="text-xs text-white font-bold truncate block">{c.location || "Unknown"}</span>
              </div>
              <div>
                <span className="text-[10px] text-gray-500 block">EXPECTATION</span>
                <span className="text-xs text-[#00e07a] font-bold">
                  {c.salary_expectation
                    ? `$${c.salary_expectation.toLocaleString()}`
                    : "—"}
                </span>
              </div>
            </div>
          </div>

          {/* Application History */}
          <div
            className="p-5 rounded-xl border bg-[#0f1422] border-white/[0.06]"
          >
            <h3 className="text-xs font-bold tracking-wide text-gray-400 font-['Space_Grotesk'] uppercase mb-3 border-b border-white/[0.04] pb-2">
              // APPLICATION TIMELINE
            </h3>
            {c.applications.length === 0 ? (
              <p className="text-xs text-gray-600 py-4 text-center font-mono">No active job applications.</p>
            ) : (
              <div className="space-y-2 font-mono">
                {c.applications.map((app) => {
                  const sb2 = stageBadge(app.stage);
                  return (
                    <button
                      key={app.id}
                      onClick={() =>
                        onViewChange({ view: "Pipeline", jobId: app.id })
                      }
                      className="w-full flex items-center justify-between p-3 rounded-lg border bg-white/[0.01] border-white/[0.04] hover:border-[#00e07a]/40 hover:bg-[#00e07a]/5 transition-all cursor-pointer text-left"
                    >
                      <div className="flex items-center gap-3 min-w-0">
                        <div className="w-8 h-8 rounded border border-white/[0.06] flex items-center justify-center flex-shrink-0 text-gray-500">
                          <Briefcase size={13} />
                        </div>
                        <div className="min-w-0">
                          <div className="text-xs font-bold text-white truncate">
                            {app.job_title}
                          </div>
                          <div className="text-[9px] text-gray-500 mt-0.5">
                            {app.department} • Applied {app.formatted_applied}
                          </div>
                        </div>
                      </div>
                      <div className="flex items-center gap-3 flex-shrink-0">
                        <span
                          className="text-[9px] font-bold uppercase px-2 py-0.5 rounded border"
                          style={{
                            backgroundColor: sb2.bg,
                            borderColor: sb2.border,
                            color: sb2.text,
                          }}
                        >
                          {`[ ${app.stage.toUpperCase()} ]`}
                        </span>
                        <span
                          className="text-[10px] font-bold"
                          style={{ color: slaColor(app.days_in_stage) }}
                        >
                          {`${app.days_in_stage}d`}
                        </span>
                        <ChevronRight size={14} className="text-gray-600" />
                      </div>
                    </button>
                  );
                })}
              </div>
            )}
          </div>

          {/* Interview Timeline */}
          <div
            className="p-5 rounded-xl border bg-[#0f1422] border-white/[0.06]"
          >
            <h3 className="text-xs font-bold tracking-wide text-gray-400 font-['Space_Grotesk'] uppercase mb-3 border-b border-white/[0.04] pb-2">
              // INTERVIEW SESSIONS
            </h3>
            {c.interviews.length === 0 ? (
              <p className="text-xs text-gray-600 py-4 text-center font-mono">No interview sessions scheduled.</p>
            ) : (
              <div className="relative pl-6 font-mono">
                {/* Vertical timeline line */}
                <div
                  className="absolute left-2 top-2 bottom-2 w-px bg-white/[0.08]"
                />
                <div className="space-y-4">
                  {c.interviews.map((iv) => {
                    const ivb = interviewStatusBadge(iv.status);
                    const avgScore =
                      iv.scorecards.length > 0
                        ? (
                            iv.scorecards.reduce((s, sc) => s + sc.score, 0) /
                            iv.scorecards.length
                          ).toFixed(1)
                        : null;
                    return (
                      <div key={iv.id} className="relative">
                        {/* Timeline dot */}
                        <div
                          className="absolute -left-6 top-3 w-2.5 h-2.5 rounded-full border-2"
                          style={{
                            backgroundColor: ivb.bg,
                            borderColor: ivb.text,
                          }}
                        />
                        <div
                          className="p-3.5 rounded-xl border bg-white/[0.01] border-white/[0.04]"
                        >
                          <div className="flex items-start justify-between">
                            <div>
                              <div className="flex items-center gap-2">
                                <span className="text-xs font-bold text-white">
                                  {iv.interview_type}
                                </span>
                                <span
                                  className="text-[8px] font-bold uppercase px-1.5 py-0.5 rounded border"
                                  style={{
                                    backgroundColor: ivb.bg,
                                    borderColor: ivb.border,
                                    color: ivb.text,
                                  }}
                                >
                                  {`[ ${iv.status.toUpperCase()} ]`}
                                </span>
                              </div>
                              <div className="text-[9px] text-gray-500 mt-1 font-semibold">
                                {iv.job_title}
                              </div>
                            </div>
                            <div className="text-right text-[10px] text-gray-400">
                              <div className="flex items-center gap-1">
                                <Calendar size={10} />
                                {iv.formatted_date}
                              </div>
                              <div className="flex items-center gap-1 mt-0.5">
                                <Clock size={10} />
                                {iv.formatted_time}
                              </div>
                            </div>
                          </div>
                          <div className="flex items-center justify-between mt-3 pt-2.5 border-t border-white/[0.04]">
                            <span className="flex items-center gap-1.5 text-[9px] text-gray-500">
                              <UserCheck size={11} className="text-pink-500" />
                              {iv.interviewer_name}
                            </span>
                            <div className="flex items-center gap-2">
                              {avgScore && (
                                <span className="flex items-center gap-1 text-[10px] font-bold text-emerald-400">
                                  <Star size={10} fill="#00e07a" stroke="none" />
                                  {avgScore}
                                </span>
                              )}
                            </div>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}
          </div>

          {/* Activity Log */}
          <div
            className="p-5 rounded-xl border bg-[#0f1422] border-white/[0.06] font-mono"
          >
            <h3 className="text-xs font-bold tracking-wide text-gray-400 uppercase mb-3 border-b border-white/[0.04] pb-2">
              // ACTIVITY LOGS
            </h3>
            {c.activity_log.length === 0 ? (
              <p className="text-xs text-gray-600 py-4 text-center">No system logs recorded.</p>
            ) : (
              <div className="space-y-1">
                {c.activity_log.map((act, i) => (
                  <div
                    key={i}
                    className="flex items-center justify-between px-3 py-2 rounded hover:bg-white/[0.02] transition-colors"
                  >
                    <div className="flex items-center gap-2">
                      <Activity size={12} className="text-[#9b6dff] flex-shrink-0" />
                      <span className="text-xs text-gray-300">{act.description}</span>
                    </div>
                    <span className="text-[10px] text-gray-600 flex-shrink-0 ml-3">
                      {act.time_ago}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* ── Right Column (40%) ─────────────────────────────────── */}
        <div className="col-span-5 lg:col-span-2 space-y-5 font-mono">
          {/* Quick Actions */}
          <div
            className="p-5 rounded-xl border bg-[#0f1422] border-white/[0.06]"
          >
            <h3 className="text-xs font-bold tracking-wide text-gray-400 uppercase mb-3 border-b border-white/[0.04] pb-2">
              // CONSOLE CONTROLS
            </h3>
            <div className="space-y-2">
              {[
                { label: "ADD COLLABORATION NOTE", action: () => setShowNoteModal(true) },
                { label: "ADD TO TALENT POOL", action: () => setShowPoolModal(true) },
              ].map((btn) => (
                <button
                  key={btn.label}
                  onClick={btn.action}
                  className="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-white/[0.06] bg-white/[0.02] text-xs font-bold text-gray-400 hover:text-white hover:border-[#00e07a]/40 hover:bg-[#00e07a]/5 transition-all cursor-pointer text-center"
                >
                  {`[ ${btn.label} ]`}
                </button>
              ))}
            </div>
          </div>

          {/* Talent Pool Memberships */}
          <div
            className="p-5 rounded-xl border bg-[#0f1422] border-white/[0.06]"
          >
            <div className="flex items-center justify-between mb-3 border-b border-white/[0.04] pb-2">
              <h3 className="text-xs font-bold tracking-wide text-gray-400 uppercase">
                // TALENT POOLS
              </h3>
              <button
                onClick={() => setShowPoolModal(true)}
                className="flex items-center gap-1 text-[10px] font-bold text-[#00e07a] hover:text-[#00c9b1] cursor-pointer bg-transparent border-0"
              >
                [ + ADD ]
              </button>
            </div>
            {c.pools.length === 0 ? (
              <p className="text-xs text-gray-600 py-3 text-center">Not enrolled in any talent pools.</p>
            ) : (
              <div className="space-y-1.5">
                {c.pools.map((pool) => (
                  <button
                    key={pool.id}
                    onClick={() =>
                      onViewChange({ view: "Pool Detail", poolId: pool.id })
                    }
                    className="w-full flex items-center gap-2 px-3 py-2 rounded border border-white/[0.04] bg-white/[0.01] hover:border-white/10 hover:bg-white/[0.03] transition-all cursor-pointer group text-left"
                  >
                    <Database size={13} className="text-[#9b6dff] flex-shrink-0" />
                    <span className="text-xs text-gray-300 group-hover:text-white transition-colors truncate">
                      {pool.name}
                    </span>
                    <ChevronRight
                      size={12}
                      className="text-gray-500 group-hover:text-white ml-auto"
                    />
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Collaboration Panel - Notes */}
          <div
            className="p-5 rounded-xl border bg-[#0f1422] border-white/[0.06]"
          >
            <h3 className="text-xs font-bold tracking-wide text-gray-400 uppercase mb-3 border-b border-white/[0.04] pb-2">
              // COLLABORATION NOTES
            </h3>
            {c.notes.length === 0 ? (
              <p className="text-xs text-gray-600 py-3 text-center">No discussion logs recorded.</p>
            ) : (
              <div className="space-y-2 max-h-64 overflow-y-auto scrollbar-thin pr-1 mb-4">
                {c.notes.map((note) => (
                  <div
                    key={note.id}
                    className="p-3 rounded border border-white/[0.04] bg-white/[0.01]"
                  >
                    <div className="flex items-center justify-between mb-1.5">
                      <div className="flex items-center gap-1.5 flex-wrap">
                        <span className="text-[10px] font-bold text-white">
                          {note.author_name}
                        </span>
                        <span
                          className="text-[8px] font-bold uppercase px-1.5 py-0.5 rounded"
                          style={{
                            color: noteTypeColor(note.note_type),
                            backgroundColor: `${noteTypeColor(note.note_type)}15`,
                          }}
                        >
                          {`[ ${note.note_type.toUpperCase()} ]`}
                        </span>
                      </div>
                      <span className="text-[9px] text-gray-600">
                        {note.created_at}
                      </span>
                    </div>
                    <p className="text-[11px] text-gray-400 leading-relaxed">
                      {note.content}
                    </p>
                  </div>
                ))}
              </div>
            )}
            {/* Inline add note form */}
            <InlineNoteForm onSubmit={handleAddNote} />
          </div>

          {/* Contact Info */}
          <div
            className="p-5 rounded-xl border bg-[#0f1422] border-white/[0.06]"
          >
            <h3 className="text-xs font-bold tracking-wide text-gray-400 uppercase mb-3 border-b border-white/[0.04] pb-2">
              // CONTACT REGISTRY
            </h3>
            <div className="space-y-3 font-sans">
              {c.email && (
                <div className="flex items-center gap-2.5">
                  <div className="w-7 h-7 rounded bg-[#0b0f1a] border border-white/[0.06] flex items-center justify-center">
                    <Mail size={13} className="text-[#00e07a]" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="text-[9px] font-mono text-gray-500 uppercase tracking-wider">EMAIL</div>
                    <a
                      href={`mailto:${c.email}`}
                      className="text-xs text-white hover:text-[#00e07a] transition-colors truncate block"
                    >
                      {c.email}
                    </a>
                  </div>
                </div>
              )}
              {c.phone && (
                <div className="flex items-center gap-2.5">
                  <div className="w-7 h-7 rounded bg-[#0b0f1a] border border-white/[0.06] flex items-center justify-center">
                    <Phone size={13} className="text-[#00e07a]" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="text-[9px] font-mono text-gray-500 uppercase tracking-wider">PHONE</div>
                    <a
                      href={`tel:${c.phone}`}
                      className="text-xs text-white hover:text-[#00e07a] transition-colors truncate block"
                    >
                      {c.phone}
                    </a>
                  </div>
                </div>
              )}
              {c.location && (
                <div className="flex items-center gap-2.5">
                  <div className="w-7 h-7 rounded bg-[#0b0f1a] border border-white/[0.06] flex items-center justify-center">
                    <MapPin size={13} className="text-[#00e07a]" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="text-[9px] font-mono text-gray-500 uppercase tracking-wider">LOCATION</div>
                    <span className="text-xs text-white truncate block">{c.location}</span>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
