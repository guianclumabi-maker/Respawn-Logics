import { useState, useEffect, useCallback } from "react";
import {
  Briefcase,
  MapPin,
  Users,
  Plus,
  Search,
  ChevronDown,
  Clock,
  Zap,
  Copy,
  Pause,
  Play,
  X,
  Loader2,
} from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL;
const API = `${API_BASE}/api/index.php?route=candidates`;

// ── Types ──────────────────────────────────────────────────────
type ViewState = {
  view: string;
  jobId?: number;
  candidateId?: number;
  poolId?: number;
};

type Props = {
  onViewChange: (v: ViewState | string) => void;
};

type PipelineHealth = {
  total: number;
  applied: number;
  review: number;
  phone_screen: number;
  interview: number;
  offer: number;
  hired: number;
  score: number;
  status: string;
  velocity: number;
};

type Job = {
  id: number;
  title: string;
  department: string;
  location: string;
  employment_type: string;
  status: string;
  priority: string;
  hiring_manager: string;
  assigned_recruiter: string;
  description: string;
  requirements: string;
  salary_min: number | null;
  salary_max: number | null;
  days_open: number;
  days_since_activity: number;
  formatted_date: string;
  health: PipelineHealth;
};

type JobForm = {
  title: string;
  department: string;
  location: string;
  employment_type: string;
  salary_min: string;
  salary_max: string;
  description: string;
  requirements: string;
  priority: string;
  hiring_manager: string;
  assigned_recruiter: string;
};

type CandidateForm = {
  name: string;
  email: string;
  phone: string;
  location: string;
  skills: string;
  experience_years: string;
  source: string;
};

const emptyJobForm: JobForm = {
  title: "",
  department: "",
  location: "",
  employment_type: "Full-Time",
  salary_min: "",
  salary_max: "",
  description: "",
  requirements: "",
  priority: "Normal",
  hiring_manager: "",
  assigned_recruiter: "",
};

const emptyCandidateForm: CandidateForm = {
  name: "",
  email: "",
  phone: "",
  location: "",
  skills: "",
  experience_years: "",
  source: "Direct",
};

// ── Sub-components ─────────────────────────────────────────────

function LoadingSkeleton() {
  return (
    <div className="relative z-10 grid md:grid-cols-2 lg:grid-cols-3 gap-5">
      {[1, 2, 3, 4, 5, 6].map((i) => (
        <div
          key={i}
          className="p-5 rounded-2xl border animate-pulse"
          style={{
            borderColor: "rgba(255,255,255,0.06)",
            backgroundColor: "rgba(22,25,34,0.2)",
          }}
        >
          <div className="flex justify-between mb-4">
            <div className="h-4 w-16 rounded bg-white/[0.06]" />
            <div className="h-4 w-12 rounded bg-white/[0.06]" />
          </div>
          <div className="h-5 w-40 rounded bg-white/[0.06] mb-2" />
          <div className="h-3 w-28 rounded bg-white/[0.04] mb-6" />
          <div className="h-3 w-full rounded bg-white/[0.04] mb-3" />
          <div className="flex gap-3 mt-4">
            <div className="h-8 flex-1 rounded-lg bg-white/[0.04]" />
            <div className="h-8 flex-1 rounded-lg bg-white/[0.04]" />
          </div>
        </div>
      ))}
    </div>
  );
}

function HealthBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    Healthy: "bg-emerald-500/10 text-emerald-400 border-emerald-500/20",
    "Needs Attention": "bg-amber-500/10 text-amber-400 border-amber-500/20",
    Critical: "bg-red-500/10 text-red-400 border-red-500/20",
  };
  return (
    <span
      className={`text-[9px] font-mono font-bold uppercase px-2 py-0.5 rounded border ${styles[status] || styles["Healthy"]}`}
    >
      {`[ ${status} ]`}
    </span>
  );
}

function PriorityBadge({ priority }: { priority: string }) {
  const styles: Record<string, string> = {
    Normal: "bg-white/[0.05] text-gray-400 border-white/[0.08]",
    Urgent: "bg-amber-500/10 text-amber-400 border-amber-500/20",
    Critical: "bg-red-500/10 text-red-400 border-red-500/20",
  };
  return (
    <span
      className={`text-[9px] font-mono font-bold uppercase px-2 py-0.5 rounded border ${styles[priority] || styles["Normal"]}`}
    >
      {`[ ${priority} ]`}
    </span>
  );
}

function StatusBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    Open: "border-[#00e07a]/20 bg-[#00e07a]/10 text-[#00e07a]",
    Paused: "border-amber-500/20 bg-amber-500/10 text-amber-400",
    Closed: "border-red-500/20 bg-red-500/10 text-red-400",
    Draft: "border-white/[0.1] bg-white/[0.05] text-gray-400",
  };
  return (
    <span
      className={`text-[9px] font-mono font-bold uppercase px-2 py-0.5 rounded border ${styles[status] || styles["Draft"]}`}
    >
      {`[ ${status} ]`}
    </span>
  );
}

function PipelineMiniStats({ health }: { health: PipelineHealth }) {
  const stages = [
    { label: "Applied", count: health.applied, color: "#8b5cf6" },
    { label: "Review", count: health.review, color: "#a855f7" },
    { label: "Interview", count: health.interview, color: "#06b6d4" },
    { label: "Offer", count: health.offer, color: "#10b981" },
  ];

  return (
    <div className="space-y-2 font-mono mt-3">
      <div className="flex justify-between text-[9px] text-gray-500">
        <span>PIPELINE CANDIDATE CAPACITY</span>
        <span>{health.total} CANDIDATES</span>
      </div>
      <div className="h-1.5 w-full bg-white/[0.04] rounded-full overflow-hidden flex">
        {stages.map((s) => {
          const pct = health.total > 0 ? (s.count / health.total) * 100 : 0;
          if (pct === 0) return null;
          return (
            <div
              key={s.label}
              className="h-full transition-all"
              style={{
                width: `${pct}%`,
                backgroundColor: s.color,
                boxShadow: `0 0 6px ${s.color}80`
              }}
              title={`${s.label}: ${s.count}`}
            />
          );
        })}
      </div>
      <div className="grid grid-cols-4 gap-1 text-[8px] text-gray-400 text-center">
        {stages.map((s) => (
          <div key={s.label} style={{ color: s.color }}>
            {s.count} {s.label}
          </div>
        ))}
      </div>
    </div>
  );
}

function FilterDropdown({
  label,
  value,
  options,
  onChange,
}: {
  label: string;
  value: string;
  options: string[];
  onChange: (v: string) => void;
}) {
  const [open, setOpen] = useState(false);

  return (
    <div className="relative font-mono">
      <button
        onClick={() => setOpen(!open)}
        className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium border transition-all cursor-pointer whitespace-nowrap"
        style={{
          backgroundColor: value
            ? "rgba(0, 224, 122, 0.12)"
            : "rgba(15, 20, 34, 0.5)",
          borderColor: value ? "#00e07a" : "rgba(255,255,255,0.06)",
          color: value ? "#00e07a" : "#8b95a8",
        }}
      >
        {value ? `[ ${label}: ${value} ]` : `[ ${label}: All ]`}
        <ChevronDown size={12} />
      </button>
      {open && (
        <>
          <div
            className="fixed inset-0 z-40"
            onClick={() => setOpen(false)}
          />
          <div
            className="absolute right-0 top-full mt-1 z-50 min-w-[140px] rounded-lg border py-1 shadow-xl bg-[#0f1422] border-white/[0.08]"
          >
            {options.map((opt) => (
              <button
                key={opt}
                onClick={() => {
                  onChange(opt === "All" ? "" : opt);
                  setOpen(false);
                }}
                className="w-full text-left px-3 py-1.5 text-xs cursor-pointer hover:bg-[#141929] hover:text-[#00e07a] transition-colors border-0 bg-transparent"
                style={{
                  color:
                    (opt === "All" && !value) || opt === value
                      ? "#00e07a"
                      : "#8b95a8",
                }}
              >
                {opt}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}

// ── Main Component ─────────────────────────────────────────────

export function JobsPage({ onViewChange }: Props) {
  const [jobs, setJobs] = useState<Job[]>([]);
  const [departments, setDepartments] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  // Filters
  const [search, setSearch] = useState("");
  const [selectedDept, setSelectedDept] = useState("");
  const [selectedStatus, setSelectedStatus] = useState("");
  const [selectedPriority, setSelectedPriority] = useState("");

  // Modals
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showCandidateModal, setShowCandidateModal] = useState(false);
  const [activeJobId, setActiveJobId] = useState<number | null>(null);
  const [jobForm, setJobForm] = useState<JobForm>(emptyJobForm);
  const [candidateForm, setCandidateForm] =
    useState<CandidateForm>(emptyCandidateForm);

  // ── Data fetching ──
  const fetchJobs = useCallback(() => {
    setLoading(true);
    const params = new URLSearchParams({ action: "jobs" });
    if (search) params.set("search", search);
    if (selectedDept) params.set("department", selectedDept);
    if (selectedStatus) params.set("status", selectedStatus);
    if (selectedPriority) params.set("priority", selectedPriority);

    fetch(`${API}&${params.toString()}`)
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          setJobs(data.jobs || []);
          setDepartments(data.departments || []);
        }
        setLoading(false);
      })
      .catch((err) => {
        console.error("Failed to fetch jobs:", err);
        setLoading(false);
      });
  }, [search, selectedDept, selectedStatus, selectedPriority]);

  useEffect(() => {
    fetchJobs();
  }, [fetchJobs]);

  // Debounced search
  const [searchInput, setSearchInput] = useState("");
  useEffect(() => {
    const timer = setTimeout(() => setSearch(searchInput), 350);
    return () => clearTimeout(timer);
  }, [searchInput]);

  // ── Handlers ──
  const handleCreateJob = () => {
    if (!jobForm.title.trim()) return;
    setSubmitting(true);

    fetch(API, {
      method: "POST",
      headers: { 
        "Content-Type": "application/json",
        "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
      },
      body: JSON.stringify({
        action: "add_job",
        ...jobForm,
        salary_min: jobForm.salary_min ? parseFloat(jobForm.salary_min) : null,
        salary_max: jobForm.salary_max ? parseFloat(jobForm.salary_max) : null,
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          setShowCreateModal(false);
          setJobForm(emptyJobForm);
          fetchJobs();
        }
        setSubmitting(false);
      })
      .catch(() => setSubmitting(false));
  };

  const handleDuplicate = (jobId: number) => {
    fetch(API, {
      method: "POST",
      headers: { 
        "Content-Type": "application/json",
        "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
      },
      body: JSON.stringify({ action: "duplicate_job", id: jobId }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) fetchJobs();
      });
  };

  const handleTogglePause = (job: Job) => {
    const newStatus = job.status === "Paused" ? "Open" : "Paused";
    fetch(API, {
      method: "POST",
      headers: { 
        "Content-Type": "application/json",
        "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
      },
      body: JSON.stringify({
        action: "update_job",
        id: job.id,
        status: newStatus,
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) fetchJobs();
      });
  };

  const handleAddCandidate = () => {
    if (!candidateForm.name.trim() || !activeJobId) return;
    setSubmitting(true);

    fetch(API, {
      method: "POST",
      headers: { 
        "Content-Type": "application/json",
        "X-CSRF-Token": (window as any).__CSRF_TOKEN__ || ""
      },
      body: JSON.stringify({
        action: "add_candidate",
        ...candidateForm,
        job_id: activeJobId,
        experience_years: candidateForm.experience_years
          ? parseInt(candidateForm.experience_years)
          : 0,
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          setShowCandidateModal(false);
          setCandidateForm(emptyCandidateForm);
          setActiveJobId(null);
          fetchJobs();
        }
        setSubmitting(false);
      })
      .catch(() => setSubmitting(false));
  };

  // ── Render ──
  return (
    <div
      className="flex-1 flex flex-col overflow-y-auto px-8 py-6 text-white font-sans relative scrollbar-thin"
      
    >
      {/* Background glows */}
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-[#00e07a] blur-[120px] opacity-[0.07] pointer-events-none z-0" />
      <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#9b6dff] blur-[140px] opacity-[0.05] pointer-events-none z-0" />

      {/* Header */}
      <div className="relative z-10 flex items-center justify-between mb-8 border-b border-white/[0.04] pb-6">
        <div>
          <h1
            className="text-2xl font-bold tracking-tight text-white font-['Space_Grotesk'] flex items-center gap-2"
          >
            JOBS BOARD // ACTIVE JOBS
            <span className="inline-block w-2.5 h-5 bg-[#00e07a] blink"></span>
          </h1>
          <p className="text-xs font-mono text-gray-500 mt-1 uppercase tracking-wider">
            Operator Interface. Select an active job to view candidate progression logs.
          </p>
        </div>
        <button
          onClick={() => setShowCreateModal(true)}
          className="flex items-center gap-1.5 bg-[#00e07a] hover:bg-[#00c9b1] rounded-lg px-4 py-2.5 text-black font-mono font-bold text-sm transition-all cursor-pointer border-0 shadow-lg shadow-green-500/15"
        >
          <Plus size={16} />
          [ CREATE JOB ]
        </button>
      </div>

      {/* Filters Bar */}
      <div className="relative z-10 flex flex-col sm:flex-row gap-3 mb-6 items-stretch sm:items-center">
        {/* Search */}
        <div
          className="flex-1 flex items-center gap-2 px-3 py-2 rounded-lg border bg-white/[0.03] transition-all focus-within:border-[#00e07a]/40"
          style={{ borderColor: "rgba(255,255,255,0.06)" }}
        >
          <Search size={14} className="text-gray-500" />
          <input
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Search active jobs by title, department, or location..."
            className="flex-1 bg-transparent outline-none text-xs text-white placeholder-gray-600 font-mono"
          />
        </div>

        {/* Department pills */}
        <div className="flex gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-thin font-mono">
          {["All", ...departments].map((dept) => {
            const isSelected = (dept === "All" && !selectedDept) || dept === selectedDept;
            return (
              <button
                key={dept}
                onClick={() => setSelectedDept(dept === "All" ? "" : dept)}
                className="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all cursor-pointer whitespace-nowrap"
                style={{
                  backgroundColor: isSelected ? "rgba(0,224,122,0.12)" : "rgba(15,20,34,0.5)",
                  borderColor: isSelected ? "#00e07a" : "rgba(255,255,255,0.06)",
                  color: isSelected ? "#00e07a" : "#8b95a8",
                }}
              >
                {isSelected ? `[[ ${dept} ]]` : `[ ${dept} ]`}
              </button>
            );
          })}
        </div>

        {/* Status & Priority dropdowns */}
        <FilterDropdown
          label="Status"
          value={selectedStatus}
          options={["All", "Open", "Paused", "Closed", "Draft"]}
          onChange={setSelectedStatus}
        />
        <FilterDropdown
          label="Priority"
          value={selectedPriority}
          options={["All", "Normal", "Urgent", "Critical"]}
          onChange={setSelectedPriority}
        />
      </div>

      {/* Content */}
      {loading ? (
        <LoadingSkeleton />
      ) : jobs.length === 0 ? (
        /* Empty State */
        <div className="relative z-10 flex-1 flex flex-col items-center justify-center py-20 font-mono">
          <div
            className="w-20 h-20 rounded-2xl flex items-center justify-center mb-5 bg-[#00e07a]/5 border border-[#00e07a]/10"
          >
            <Briefcase size={36} className="text-[#00e07a]" />
          </div>
          <h3 className="text-lg font-bold text-white mb-1 font-['Space_Grotesk']">
            CREATE YOUR FIRST JOB
          </h3>
          <p className="text-sm text-gray-500 mb-6">
            Create a job posting to view recruitment metrics.
          </p>
          <button
            onClick={() => setShowCreateModal(true)}
            className="flex items-center gap-1.5 bg-[#00e07a] hover:bg-[#00c9b1] text-black font-bold rounded-lg px-5 py-2.5 text-sm transition-all cursor-pointer border-0"
          >
            <Plus size={16} />
            [ CREATE JOB ]
          </button>
        </div>
      ) : (
        /* Jobs Grid */
        <div className="relative z-10 grid md:grid-cols-2 lg:grid-cols-3 gap-5">
          {jobs.map((job) => (
            <JobCard
              key={job.id}
              job={job}
              onViewPipeline={() =>
                onViewChange({ view: "Pipeline", jobId: job.id })
              }
              onAddCandidate={() => {
                setActiveJobId(job.id);
                setShowCandidateModal(true);
              }}
              onDuplicate={() => handleDuplicate(job.id)}
              onTogglePause={() => handleTogglePause(job)}
            />
          ))}
        </div>
      )}

      {/* Create Job Modal */}
      {showCreateModal && (
        <CreateJobModal
          form={jobForm}
          setForm={setJobForm}
          submitting={submitting}
          departments={departments}
          onClose={() => {
            setShowCreateModal(false);
            setJobForm(emptyJobForm);
          }}
          onSubmit={handleCreateJob}
        />
      )}

      {/* Add Candidate Modal */}
      {showCandidateModal && activeJobId && (
        <AddCandidateModal
          jobId={activeJobId}
          form={candidateForm}
          setForm={setCandidateForm}
          submitting={submitting}
          onClose={() => {
            setShowCandidateModal(false);
            setCandidateForm(emptyCandidateForm);
            setActiveJobId(null);
          }}
          onSubmit={handleAddCandidate}
        />
      )}
    </div>
  );
}

// ── Job Card ───────────────────────────────────────────────────

function JobCard({
  job,
  onViewPipeline,
  onAddCandidate,
  onDuplicate,
  onTogglePause,
}: {
  job: Job;
  onViewPipeline: () => void;
  onAddCandidate: () => void;
  onDuplicate: () => void;
  onTogglePause: () => void;
}) {
  return (
    <div
      className="bg-[#0f1422] border border-white/[0.07] rounded-xl p-5 hover:border-[#9b6dff]/40 hover:bg-[#141929] hover:shadow-[0_0_15px_rgba(155,109,255,0.1)] transition-all flex flex-col justify-between group"
    >
      {/* Top badges */}
      <div>
        <div className="flex items-center justify-between mb-3 flex-wrap gap-1.5 font-mono">
          <span className="text-[9px] font-bold tracking-wider text-[#9b6dff] uppercase bg-[#9b6dff]/10 px-2 py-0.5 rounded border border-[#9b6dff]/20">
            {`DEPARTMENT: ${job.department || "General"}`}
          </span>
          <div className="flex items-center gap-1.5">
            <StatusBadge status={job.status} />
            {job.priority !== "Normal" && (
              <PriorityBadge priority={job.priority} />
            )}
          </div>
        </div>

        {/* Title */}
        <h3
          onClick={onViewPipeline}
          className="text-lg font-bold text-white cursor-pointer hover:text-[#00e07a] transition-colors truncate mb-1 font-['Space_Grotesk']"
        >
          {job.title}
        </h3>

        {/* Location + type */}
        <div className="flex items-center gap-2 text-xs text-gray-400 mb-3 font-mono">
          <MapPin size={11} className="text-[#00e07a]" />
          <span>{`LOCATION: ${job.location || "Remote"}`}</span>
          {job.employment_type && (
            <>
              <span className="text-gray-600">·</span>
              <span>{`TYPE: ${job.employment_type}`}</span>
            </>
          )}
        </div>

        {/* Ownership */}
        <div className="flex flex-col gap-0.5 mb-3 font-mono text-[10px] text-gray-500">
          {job.assigned_recruiter && (
            <span>
              {`RECRUITER: ${job.assigned_recruiter}`}
            </span>
          )}
          {job.hiring_manager && (
            <span>
              {`HIRING_MANAGER: ${job.hiring_manager}`}
            </span>
          )}
        </div>

        {/* Pipeline mini-stats */}
        <PipelineMiniStats health={job.health} />
      </div>

      {/* Bottom row: metrics + health */}
      <div className="border-t border-white/[0.04] pt-3 mt-4">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-3 text-[9px] text-gray-500 font-mono">
            <span className="flex items-center gap-1">
              <Clock size={10} className="text-amber-400" />
              {job.days_open}d open
            </span>
            <span className="flex items-center gap-1">
              <Zap size={10} className="text-[#00e07a]" />
              +{job.health.velocity}/wk
            </span>
            <span className="flex items-center gap-1">
              <Users size={10} className="text-cyan-400" />
              Total: {job.health.total}
            </span>
          </div>
          <HealthBadge status={job.health.status} />
        </div>

        {/* Quick actions */}
        <div className="flex gap-2 font-mono">
          <button
            onClick={onViewPipeline}
            className="flex-1 flex items-center justify-center gap-1 px-2 py-1.5 rounded-lg text-[9px] font-bold bg-white/[0.02] hover:bg-white/[0.06] border border-white/[0.05] text-gray-400 hover:text-white transition-all cursor-pointer"
          >
            [ PIPELINE ]
          </button>
          <button
            onClick={onAddCandidate}
            className="flex-1 flex items-center justify-center gap-1 px-2 py-1.5 rounded-lg text-[9px] font-bold bg-[#00e07a]/10 hover:bg-[#00e07a]/20 border border-[#00e07a]/25 text-[#00e07a] hover:text-white transition-all cursor-pointer"
          >
            [ + CANDIDATE ]
          </button>
          <button
            onClick={onDuplicate}
            className="flex items-center justify-center px-2 py-1.5 rounded-lg text-[9px] font-bold bg-white/[0.02] hover:bg-white/[0.06] border border-white/[0.05] text-gray-400 hover:text-white transition-all cursor-pointer"
            title="Duplicate Job"
          >
            <Copy size={10} />
          </button>
          <button
            onClick={onTogglePause}
            className="flex items-center justify-center px-2 py-1.5 rounded-lg text-[9px] font-bold bg-white/[0.02] hover:bg-white/[0.06] border border-white/[0.05] text-gray-400 hover:text-white transition-all cursor-pointer"
            title={job.status === "Paused" ? "Resume Job" : "Pause Job"}
          >
            {job.status === "Paused" ? (
              <Play size={10} className="text-[#00e07a]" />
            ) : (
              <Pause size={10} className="text-amber-500" />
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Create Job Modal ───────────────────────────────────────────

function CreateJobModal({
  form,
  setForm,
  submitting,
  departments,
  onClose,
  onSubmit,
}: {
  form: JobForm;
  setForm: (f: JobForm) => void;
  submitting: boolean;
  departments: string[];
  onClose: () => void;
  onSubmit: () => void;
}) {
  const update = (field: keyof JobForm, value: string) =>
    setForm({ ...form, [field]: value });

  const inputClass =
    "w-full px-3 py-2 rounded-lg bg-white/[0.02] border border-white/[0.06] text-xs font-mono text-white placeholder-gray-600 outline-none focus:border-[#00e07a] focus:shadow-[0_0_10px_rgba(0,224,122,0.15)] transition-all";
  const labelClass = "block text-xs font-mono font-medium text-gray-400 mb-1.5 uppercase tracking-wider";

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="absolute inset-0 bg-black/70 backdrop-blur-sm" onClick={onClose} />
      <div
        className="relative bg-[#0f1422] border border-white/[0.07] rounded-2xl p-6 w-full max-w-xl max-h-[90vh] overflow-y-auto scrollbar-thin"
        style={{ boxShadow: "0 0 30px rgba(155, 109, 255, 0.15)" }}
      >
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-lg font-bold text-white font-['Space_Grotesk']">CREATE NEW JOB</h2>
            <p className="text-xs text-gray-500 mt-0.5 font-mono">
              Specify features and constraints for the recruitment campaign
            </p>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-lg bg-white/[0.04] hover:bg-white/[0.08] flex items-center justify-center text-gray-400 hover:text-white transition-all cursor-pointer border-0"
          >
            <X size={16} />
          </button>
        </div>

        <div className="space-y-4">
          {/* Title */}
          <div>
            <label className={labelClass}>
              Job Title <span className="text-red-400">*</span>
            </label>
            <input
              value={form.title}
              onChange={(e) => update("title", e.target.value)}
              placeholder="e.g. Senior Frontend Engineer"
              className={inputClass}
            />
          </div>

          {/* Department + Location */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className={labelClass}>Department</label>
              <input
                list="department-options"
                value={form.department}
                onChange={(e) => update("department", e.target.value)}
                placeholder="e.g. Engineering"
                className={inputClass}
              />
              <datalist id="department-options">
                {departments.map((dept) => (
                  <option key={dept} value={dept} />
                ))}
              </datalist>
            </div>
            <div>
              <label className={labelClass}>Location</label>
              <input
                value={form.location}
                onChange={(e) => update("location", e.target.value)}
                placeholder="e.g. Remote"
                className={inputClass}
              />
            </div>
          </div>

          {/* Employment Type + Priority */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className={labelClass}>Job Type</label>
              <select
                value={form.employment_type}
                onChange={(e) => update("employment_type", e.target.value)}
                className={inputClass}
              >
                <option value="Full-Time">Full-Time</option>
                <option value="Part-Time">Part-Time</option>
                <option value="Contract">Contract</option>
                <option value="Intern">Intern</option>
                <option value="Freelance">Freelance</option>
              </select>
            </div>
            <div>
              <label className={labelClass}>Priority</label>
              <select
                value={form.priority}
                onChange={(e) => update("priority", e.target.value)}
                className={inputClass}
              >
                <option value="Normal">Normal</option>
                <option value="Urgent">Urgent</option>
                <option value="Critical">Critical</option>
              </select>
            </div>
          </div>

          {/* Salary Range */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className={labelClass}>Salary Min</label>
              <input
                type="number"
                value={form.salary_min}
                onChange={(e) => update("salary_min", e.target.value)}
                placeholder="e.g. 80000"
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Salary Max</label>
              <input
                type="number"
                value={form.salary_max}
                onChange={(e) => update("salary_max", e.target.value)}
                placeholder="e.g. 120000"
                className={inputClass}
              />
            </div>
          </div>

          {/* Hiring Manager + Recruiter */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className={labelClass}>Hiring Manager (HM)</label>
              <input
                value={form.hiring_manager}
                onChange={(e) => update("hiring_manager", e.target.value)}
                placeholder="e.g. Mike Wilson"
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Recruiter</label>
              <input
                value={form.assigned_recruiter}
                onChange={(e) => update("assigned_recruiter", e.target.value)}
                placeholder="e.g. Sarah Jones"
                className={inputClass}
              />
            </div>
          </div>

          {/* Description */}
          <div>
            <label className={labelClass}>Job Objectives</label>
            <textarea
              value={form.description}
              onChange={(e) => update("description", e.target.value)}
              placeholder="Job description..."
              rows={3}
              className={`${inputClass} resize-none`}
            />
          </div>

          {/* Requirements */}
          <div>
            <label className={labelClass}>Requirements</label>
            <textarea
              value={form.requirements}
              onChange={(e) => update("requirements", e.target.value)}
              placeholder="Key requirements..."
              rows={3}
              className={`${inputClass} resize-none`}
            />
          </div>
        </div>

        {/* Submit */}
        <div className="flex justify-end gap-3 mt-6 pt-4 border-t border-white/[0.06] font-mono">
          <button
            onClick={onClose}
            className="px-4 py-2 rounded-lg text-xs font-semibold text-gray-400 hover:text-white bg-white/[0.04] hover:bg-white/[0.08] border border-white/[0.06] transition-all cursor-pointer"
          >
            [ CANCEL ]
          </button>
          <button
            onClick={onSubmit}
            disabled={submitting || !form.title.trim()}
            className="flex items-center gap-2 px-5 py-2 rounded-lg text-xs font-semibold bg-[#00e07a] hover:bg-[#00c9b1] text-black transition-all cursor-pointer border-0 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {submitting && (
              <Loader2 size={14} className="animate-spin" />
            )}
            [ CREATE JOB ]
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Add Candidate Modal ────────────────────────────────────────

function AddCandidateModal({
  jobId,
  form,
  setForm,
  submitting,
  onClose,
  onSubmit,
}: {
  jobId: number;
  form: CandidateForm;
  setForm: (f: CandidateForm) => void;
  submitting: boolean;
  onClose: () => void;
  onSubmit: () => void;
}) {
  const update = (field: keyof CandidateForm, value: string) =>
    setForm({ ...form, [field]: value });

  const inputClass =
    "w-full px-3 py-2 rounded-lg bg-white/[0.02] border border-white/[0.06] text-xs font-mono text-white placeholder-gray-600 outline-none focus:border-[#00e07a] focus:shadow-[0_0_10px_rgba(0,224,122,0.15)] transition-all";
  const labelClass = "block text-[10px] font-mono font-bold text-gray-400 mb-1.5 uppercase tracking-wider";

  const fieldInput = (label: string, key: keyof CandidateForm, placeholder: string, required = false) => (
    <div>
      <label className={labelClass}>{label}{required && <span className="text-red-400"> *</span>}</label>
      <input value={form[key]} onChange={e => update(key, e.target.value)} placeholder={placeholder} className={inputClass} />
    </div>
  );

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="absolute inset-0 bg-black/70 backdrop-blur-sm" onClick={onClose} />
      <div
        className="relative bg-[#0f1422] border border-white/[0.07] rounded-2xl p-6 w-full max-w-md max-h-[90vh] overflow-y-auto scrollbar-thin"
        style={{ boxShadow: "0 0 30px rgba(155, 109, 255, 0.15)" }}
      >
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-lg font-bold text-white font-['Space_Grotesk']">ADD CANDIDATE</h2>
            <p className="text-xs text-gray-500 mt-0.5 font-mono">
              Add candidate to job #{jobId}
            </p>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-lg bg-white/[0.04] hover:bg-white/[0.08] flex items-center justify-center text-gray-400 hover:text-white transition-all cursor-pointer border-0"
          >
            <X size={16} />
          </button>
        </div>

        <div className="space-y-3">
          {fieldInput("Name", "name", "Full name...", true)}
          <div className="grid grid-cols-2 gap-3">
            {fieldInput("Email", "email", "email@example.com")}
            {fieldInput("Phone", "phone", "+1 234 567 890")}
          </div>
          {fieldInput("Location", "location", "City, Country")}
          {fieldInput("Skills", "skills", "React, TypeScript, Node.js (comma-separated)")}
          <div className="grid grid-cols-2 gap-3">
            {fieldInput("Experience (years)", "experience_years", "5")}
            <div>
              <label className={labelClass}>Source</label>
              <select
                value={form.source}
                onChange={e => update("source", e.target.value)}
                className="w-full px-3 py-2 rounded-lg bg-white/[0.02] border border-white/[0.06] text-xs font-mono text-white outline-none focus:border-[#00e07a]"
              >
                <option value="Direct">Direct</option>
                <option value="LinkedIn">LinkedIn</option>
                <option value="Careers Site">Careers Site</option>
                <option value="Referral">Referral</option>
                <option value="Indeed">Indeed</option>
                <option value="Agency">Agency</option>
              </select>
            </div>
          </div>
        </div>

        {/* Submit */}
        <div className="flex justify-end gap-3 mt-6 pt-4 border-t border-white/[0.06] font-mono">
          <button
            onClick={onClose}
            className="px-4 py-2 rounded-lg text-xs font-semibold text-gray-400 hover:text-white bg-white/[0.04] hover:bg-white/[0.08] border border-white/[0.06] transition-all cursor-pointer"
          >
            [ CANCEL ]
          </button>
          <button
            onClick={onSubmit}
            disabled={submitting || !form.name.trim()}
            className="flex items-center gap-2 px-5 py-2 rounded-lg text-xs font-semibold bg-[#00e07a] hover:bg-[#00c9b1] text-black transition-all cursor-pointer border-0 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {submitting && (
              <Loader2 size={14} className="animate-spin" />
            )}
            [ ADD CANDIDATE ]
          </button>
        </div>
      </div>
    </div>
  );
}
