import { useState, useEffect } from "react";
import {
  Users,
  Calendar,
  Mail,
  ClipboardList,
  AlertTriangle,
  CheckCircle2,
  ArrowRight,
  Clock,
  Eye,
  UserPlus,
  Pause,
  Activity,
  Briefcase,
  FileText,
  UserCheck,
  XCircle,
  ChevronRight,
  Zap,
  Timer,
} from "lucide-react";

// ── Types ──────────────────────────────────────────────────────────────────────

type ViewState = {
  view: string;
  jobId?: number;
  candidateId?: number;
  poolId?: number;
};

type Props = {
  onViewChange: (v: ViewState) => void;
};

type ActionSummary = {
  awaiting_review: number;
  interviews_today: number;
  pending_offers: number;
  missing_scorecards: number;
  pending_approvals: number;
};

type SLAAlert = {
  type: string;
  severity: "critical" | "warning" | "info";
  message: string;
  job_title?: string;
  application_id?: number;
  candidate_id?: number;
  job_id?: number;
  interview_id?: number;
  days?: number;
};

type PipelineHealth = {
  total: number;
  applied: number;
  review: number;
  phone_screen: number;
  interview: number;
  offer: number;
  hired: number;
  stuck: number;
  score: number;
  status: "Healthy" | "Needs Attention" | "Critical";
  velocity: number;
};

type JobHealth = {
  id: number;
  title: string;
  department: string;
  location: string;
  employment_type: string;
  priority: string;
  hiring_manager: string;
  assigned_recruiter: string;
  days_open: number;
  days_since_activity: number;
  health: PipelineHealth;
};

type ActivityItem = {
  id: number;
  candidate_id: number | null;
  job_id: number | null;
  action: string;
  description: string;
  actor_name: string;
  candidate_name: string | null;
  job_title: string | null;
  time_ago: string;
  created_at: string;
};

type UpcomingInterview = {
  id: number;
  candidate_id: number;
  job_id: number;
  candidate_name: string;
  job_title: string;
  interview_type: string;
  status: string;
  formatted_date: string;
  formatted_time: string;
  scheduled_at: string;
};

type DashboardData = {
  action_summary: ActionSummary;
  sla_alerts: SLAAlert[];
  jobs_health: JobHealth[];
  activities: ActivityItem[];
  upcoming_interviews: UpcomingInterview[];
  totals: { candidates: number; open_jobs: number; hired: number };
};

const API_BASE = import.meta.env.VITE_API_BASE_URL;
const API = `${API_BASE}/api/index.php?route=candidates`;

// ── Skeleton Loader ────────────────────────────────────────────────────────────

function Skeleton({ className = "" }: { className?: string }) {
  return (
    <div
      className={`animate-pulse rounded-lg bg-secondary ${className}`}
    />
  );
}

function CardSkeleton() {
  return (
    <div className="bg-card border border-border rounded-xl p-5 space-y-3">
      <div className="flex items-center justify-between">
        <Skeleton className="w-10 h-10 rounded-full" />
        <Skeleton className="w-16 h-4" />
      </div>
      <Skeleton className="w-12 h-7" />
      <Skeleton className="w-24 h-3" />
    </div>
  );
}

// ── Metric Card ────────────────────────────────────────────────────────────────

function MetricCard({
  icon,
  value,
  label,
  accentColor,
  onClick,
  delay,
}: {
  icon: React.ReactNode;
  value: number;
  label: string;
  accentColor: string;
  onClick: () => void;
  delay: number;
}) {
  return (
    <button
      onClick={onClick}
      className="hud-card rounded-xl p-5 text-left cursor-pointer group"
      style={{
        animation: `fadeSlideUp 0.5s ease-out ${delay}ms both`,
        '--accent-color': accentColor,
        '--accent-glow': `${accentColor}25`,
      } as React.CSSProperties}
    >
      <div className="flex items-center justify-between mb-3">
        <div
          className="w-10 h-10 rounded-xl flex items-center justify-center transition-all group-hover:scale-110 group-hover:shadow-[0_0_12px_rgba(var(--accent-glow))]"
          style={{ backgroundColor: `${accentColor}15`, color: accentColor }}
        >
          {icon}
        </div>
        <ChevronRight
          size={14}
          className="text-muted-foreground group-hover:text-muted-foreground transition-colors"
        />
      </div>
      <div className="text-2xl font-bold text-foreground mb-1 font-mono tracking-tight">{value}</div>
      <div className="text-[10px] font-mono text-muted-foreground uppercase tracking-wider">{`// ${label}`}</div>
    </button>
  );
}

// ── SLA Alert Item ─────────────────────────────────────────────────────────────

function AlertItem({
  alert,
  onViewChange,
}: {
  alert: SLAAlert;
  onViewChange: (v: ViewState) => void;
}) {
  const severityConfig = {
    critical: {
      bg: "bg-red-500/10",
      border: "border-red-500/20",
      text: "text-red-400",
      label: "Critical",
    },
    warning: {
      bg: "bg-amber-500/10",
      border: "border-amber-500/20",
      text: "text-amber-400",
      label: "Warning",
    },
    info: {
      bg: "bg-blue-500/10",
      border: "border-blue-500/20",
      text: "text-blue-400",
      label: "Info",
    },
  };

  const sev = severityConfig[alert.severity] || severityConfig.info;

  const iconMap: Record<string, React.ReactNode> = {
    stuck_candidate: <Clock size={16} />,
    missing_scorecard: <ClipboardList size={16} />,
    critical_pipeline: <AlertTriangle size={16} />,
  };

  function handleAction() {
    if (alert.type === "stuck_candidate" && alert.candidate_id) {
      onViewChange({
        view: "Candidates",
        candidateId: alert.candidate_id,
        jobId: alert.job_id,
      });
    } else if (alert.type === "missing_scorecard") {
      onViewChange({ view: "Interviews" });
    } else if (alert.type === "critical_pipeline" && alert.job_id) {
      onViewChange({ view: "Jobs", jobId: alert.job_id });
    } else {
      onViewChange({ view: "Candidates" });
    }
  }

  return (
    <div
      className={`flex items-center gap-4 p-3.5 rounded-lg ${sev.bg} border ${sev.border} transition-all hover:brightness-110`}
    >
      <div className={`flex-shrink-0 ${sev.text}`}>
        {iconMap[alert.type] || <AlertTriangle size={16} />}
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-sm text-foreground/90 leading-snug">{alert.message}</p>
        {alert.job_title && (
          <p className="text-xs text-muted-foreground mt-0.5 font-mono">{`[ JOB: ${alert.job_title} ]`}</p>
        )}
      </div>
      <span
        className={`text-[9px] font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded ${sev.bg} ${sev.text} border ${sev.border} flex-shrink-0`}
      >
        {`[ ${sev.label} ]`}
      </span>
      <button
        onClick={handleAction}
        className="flex-shrink-0 text-xs font-mono font-semibold text-purple-400 hover:text-purple-300 transition-colors cursor-pointer bg-transparent border-0 whitespace-nowrap"
      >
        {`[ RESOLVE ]`}
      </button>
    </div>
  );
}

// ── Pipeline Health Card ───────────────────────────────────────────────────────

function JobHealthCard({
  job,
  onViewChange,
}: {
  job: JobHealth;
  onViewChange: (v: ViewState) => void;
}) {
  const statusConfig = {
    Healthy: {
      bg: "bg-emerald-500/10",
      text: "text-emerald-400",
      border: "border-emerald-500/20",
    },
    "Needs Attention": {
      bg: "bg-amber-500/10",
      text: "text-amber-400",
      border: "border-amber-500/20",
    },
    Critical: {
      bg: "bg-red-500/10",
      text: "text-red-400",
      border: "border-red-500/20",
    },
  };

  const priorityConfig: Record<string, string> = {
    High: "bg-red-500/10 text-red-400 border-red-500/20",
    Medium: "bg-amber-500/10 text-amber-400 border-amber-500/20",
    Low: "bg-blue-500/10 text-blue-400 border-blue-500/20",
    Urgent: "bg-pink-500/10 text-pink-400 border-pink-500/20",
  };

  const hs =
    statusConfig[job.health.status] || statusConfig["Needs Attention"];

  const pipelineSteps = [
    { label: "Applied", count: job.health.applied, color: "#8b5cf6" },
    { label: "Review", count: job.health.review, color: "#a855f7" },
    { label: "Interview", count: job.health.interview, color: "#3b82f6" },
    { label: "Offer", count: job.health.offer, color: "#10b981" },
  ];

  return (
    <div className="bg-card border border-border rounded-xl p-5 flex flex-col justify-between transition-all hover:border-[#9b6dff]/40 hover:bg-purple-500/5 dark:hover:bg-[#141929] hover:shadow-[0_0_15px_rgba(155,109,255,0.1)]">
      {/* Header */}
      <div className="mb-4">
        <div className="flex items-start justify-between mb-2">
          <h4 className="text-sm font-semibold text-foreground leading-tight font-['Space_Grotesk']">
            {job.title}
          </h4>
          <span
            className={`text-[9px] font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded border flex-shrink-0 ml-2 ${hs.bg} ${hs.text} ${hs.border}`}
          >
            {`[ ${job.health.status} ]`}
          </span>
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          {job.department && (
            <span className="text-[10px] font-mono px-2 py-0.5 rounded bg-purple-500/10 text-purple-400 border border-purple-500/20">
              {`DEPARTMENT: ${job.department}`}
            </span>
          )}
          {job.priority && (
            <span
              className={`text-[10px] font-mono px-2 py-0.5 rounded border ${priorityConfig[job.priority] || "bg-white/5 text-muted-foreground border-white/10"}`}
            >
              {`PRIORITY: ${job.priority}`}
            </span>
          )}
        </div>
      </div>

      {/* Mini Pipeline */}
      <div className="space-y-2 mb-4">
        <div className="flex justify-between text-[9px] text-muted-foreground font-mono">
          <span>PIPELINE CAPACITY</span>
          <span>{job.health.total} CANDIDATES</span>
        </div>
        <div className="h-1.5 w-full bg-secondary rounded-full overflow-hidden flex">
          {pipelineSteps.map((step) => {
            const pct = job.health.total > 0 ? (step.count / job.health.total) * 100 : 0;
            if (pct === 0) return null;
            return (
              <div
                key={step.label}
                className="h-full transition-all"
                style={{
                  width: `${pct}%`,
                  backgroundColor: step.color,
                  boxShadow: `0 0 6px ${step.color}80`
                }}
                title={`${step.label}: ${step.count}`}
              />
            );
          })}
        </div>
        <div className="grid grid-cols-4 gap-1 text-[8px] text-muted-foreground font-mono text-center">
          {pipelineSteps.map((step) => (
            <div key={step.label} className="truncate" style={{ color: step.color }}>
              {step.count} {step.label}
            </div>
          ))}
        </div>
      </div>

      {/* Metrics */}
      <div className="flex items-center justify-between text-[10px] text-muted-foreground mb-4 font-mono">
        <div className="flex items-center gap-1">
          <Timer size={11} className="text-amber-400" />
          <span>OPEN: {job.days_open}D</span>
        </div>
        <div className="flex items-center gap-1">
          <Zap size={11} className="text-primary" />
          <span>VELOCITY: +{job.health.velocity}/WK</span>
        </div>
      </div>

      {/* Actions */}
      <div className="flex items-center gap-2 pt-3 border-t border-border font-mono">
        <button
          onClick={() => onViewChange({ view: "Jobs", jobId: job.id })}
          className="flex items-center gap-1 text-[10px] font-semibold text-purple-400 hover:text-purple-300 transition-colors cursor-pointer bg-transparent border-0 px-0"
        >
          [ BOARD ]
        </button>
        <span className="text-gray-700">·</span>
        <button
          onClick={() =>
            onViewChange({ view: "Candidates", jobId: job.id })
          }
          className="flex items-center gap-1 text-[10px] font-semibold text-blue-400 hover:text-blue-300 transition-colors cursor-pointer bg-transparent border-0 px-0"
        >
          [ + CANDIDATE ]
        </button>
        <span className="text-gray-700">·</span>
        <button className="flex items-center gap-1 text-[10px] font-semibold text-muted-foreground hover:text-muted-foreground transition-colors cursor-pointer bg-transparent border-0 px-0">
          [ PAUSE ]
        </button>
      </div>
    </div>
  );
}

// ── Activity Icon ──────────────────────────────────────────────────────────────

function getActivityIcon(action: string) {
  const lower = action.toLowerCase();
  if (lower.includes("applied") || lower.includes("application"))
    return <FileText size={14} className="text-purple-400" />;
  if (lower.includes("interview") || lower.includes("scheduled"))
    return <Calendar size={14} className="text-blue-400" />;
  if (lower.includes("hire") || lower.includes("offer"))
    return <UserCheck size={14} className="text-emerald-400" />;
  if (lower.includes("reject") || lower.includes("declined"))
    return <XCircle size={14} className="text-red-400" />;
  if (lower.includes("stage") || lower.includes("moved") || lower.includes("advanced"))
    return <ArrowRight size={14} className="text-cyan-400" />;
  if (lower.includes("note") || lower.includes("comment"))
    return <ClipboardList size={14} className="text-amber-400" />;
  return <Activity size={14} className="text-muted-foreground" />;
}

// ── Main Component ─────────────────────────────────────────────────────────────

export function ATSDashboard({ onViewChange }: Props) {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch(`${API}&action=dashboard`)
      .then((res) => res.json())
      .then((json) => {
        if (json.success) {
          setData(json);
        } else {
          setError(json.error || "Failed to load dashboard data");
        }
        setLoading(false);
      })
      .catch((err) => {
        console.error("Dashboard fetch error:", err);
        setError("Unable to connect to API");
        setLoading(false);
      });
  }, []);

  const today = new Date().toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });

  return (
    <div
      className="flex-1 overflow-y-auto"
      style={{
        scrollbarWidth: "none",
        msOverflowStyle: "none",
      }}
    >
      <style>{`
        @keyframes fadeSlideUp {
          from { opacity: 0; transform: translateY(12px); }
          to { opacity: 1; transform: translateY(0); }
        }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hud-card {
          background: var(--card);
          border: 1px solid var(--border);
          position: relative;
          overflow: hidden;
          transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .hud-card::before {
          content: '';
          position: absolute;
          top: 0; left: 0; right: 0;
          height: 2px;
          background: var(--accent-color, #00e07a);
          opacity: 0;
          transition: opacity 0.3s;
        }
        .hud-card:hover {
          background: var(--accent-glow, rgba(255, 255, 255, 0.04));
          border-color: var(--accent-color, #00e07a);
          box-shadow: 0 0 20px var(--accent-glow, rgba(0, 224, 122, 0.15));
          transform: translateY(-2px);
        }
        .hud-card:hover::before {
          opacity: 1;
        }
        .blink {
          animation: blink-anim 1.1s step-start infinite;
        }
        @keyframes blink-anim {
          0%, 100% { opacity: 1; }
          50%       { opacity: 0; }
        }
      `}</style>

      <div className="p-8 w-full hide-scrollbar">
        {/* ── Welcome Header ─────────────────────────────────── */}
        <div
          className="mb-8 flex flex-col md:flex-row md:items-end md:justify-between border-b border-border pb-6"
          style={{ animation: "fadeSlideUp 0.4s ease-out both" }}
        >
          <div>
            <h1
              className="text-2xl font-bold tracking-tight text-foreground font-['Space_Grotesk'] flex items-center gap-3"
            >
              ATS CONTROLLER v2.0
              <span className="inline-block w-2.5 h-5 bg-emerald-500 blink"></span>
            </h1>
            <p className="text-xs font-mono text-muted-foreground mt-1 uppercase tracking-wider">
              System initialization complete. Operator authenticated.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-3 mt-4 md:mt-0 font-mono text-[10px]">
            <span className="px-2.5 py-1 rounded bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 flex items-center gap-1.5 font-bold">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping" />
              STATUS: SECURE
            </span>
            <span className="px-2.5 py-1 rounded bg-muted text-muted-foreground border border-border">
              LOCATION: RESPAWN-HQ
            </span>
            <span className="px-2.5 py-1 rounded bg-muted text-muted-foreground border border-border">
              {today}
            </span>
          </div>
        </div>

        {loading ? (
          /* ── Loading Skeleton ──────────────────────────────────────────── */
          <div className="space-y-8">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {[0, 1, 2, 3].map((i) => (
                <CardSkeleton key={i} />
              ))}
            </div>
            <div className="bg-background border border-border rounded-xl p-6 space-y-4">
              <Skeleton className="w-40 h-5" />
              <Skeleton className="w-full h-12" />
              <Skeleton className="w-full h-12" />
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {[0, 1, 2, 3].map((i) => (
                <div
                  key={i}
                  className="bg-background border border-border rounded-xl p-5 space-y-3"
                >
                  <Skeleton className="w-32 h-4" />
                  <Skeleton className="w-full h-3" />
                  <Skeleton className="w-20 h-3" />
                </div>
              ))}
            </div>
          </div>
        ) : error ? (
          /* ── Error State ──────────────────────────────────────────────── */
          <div className="flex flex-col items-center justify-center py-24 text-center">
            <AlertTriangle size={40} className="text-[#f5a623] mb-4" />
            <p className="text-gray-900 dark:text-foreground font-medium mb-1" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
              Unable to load dashboard
            </p>
            <p className="text-sm text-muted-foreground dark:text-muted-foreground font-mono">{error}</p>
            <button
              onClick={() => window.location.reload()}
              className="mt-4 px-4 py-2 rounded-lg text-xs font-mono font-semibold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20 hover:bg-purple-500/20 cursor-pointer transition-all"
            >
              [ RETRY SESSION ]
            </button>
          </div>
        ) : data ? (
          <>
            {/* ── Action Summary Cards ───────────────────────── */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
              <MetricCard
                icon={<Users size={20} />}
                value={data.action_summary.awaiting_review}
                label="NEW CANDIDATES"
                accentColor="#9b6dff"
                onClick={() => onViewChange({ view: "Candidates" })}
                delay={0}
              />
              <MetricCard
                icon={<Calendar size={20} />}
                value={data.action_summary.interviews_today}
                label="INTERVIEWS TODAY"
                accentColor="#4f8ef7"
                onClick={() => onViewChange({ view: "Interviews" })}
                delay={60}
              />
              <MetricCard
                icon={<Mail size={20} />}
                value={data.action_summary.pending_offers}
                label="PENDING OFFERS"
                accentColor="#00e07a"
                onClick={() => onViewChange({ view: "Candidates" })}
                delay={120}
              />
              <MetricCard
                icon={<ClipboardList size={20} />}
                value={data.action_summary.missing_scorecards}
                label="MISSING FEEDBACK"
                accentColor="#f5a623"
                onClick={() => onViewChange({ view: "Interviews" })}
                delay={180}
              />
            </div>

            {/* ── Action Center ────────────────────── */}
            <div
              className="bg-card border border-border rounded-xl p-6 mb-8 relative overflow-hidden"
              style={{ animation: "fadeSlideUp 0.5s ease-out 200ms both" }}
            >
              <div className="flex items-center gap-2 mb-5 justify-between">
                <div className="flex items-center gap-2">
                  <AlertTriangle size={18} className="text-[#f5a623]" />
                  <h2
                    className="text-base font-bold text-foreground font-['Space_Grotesk']"
                  >
                    SYSTEM ALERTS // ACTION REQUIRED
                  </h2>
                </div>
                {data.sla_alerts.length > 0 && (
                  <span className="text-[10px] font-mono font-bold text-[#f5a623] ml-2">
                    {`[ WARNINGS: ${data.sla_alerts.length} ]`}
                  </span>
                )}
              </div>

              {data.sla_alerts.length > 0 ? (
                <div className="space-y-3">
                  {data.sla_alerts.map((alert, idx) => (
                    <AlertItem
                      key={idx}
                      alert={alert}
                      onViewChange={onViewChange}
                    />
                  ))}
                </div>
              ) : (
                <div className="flex items-center gap-3 p-4 rounded-lg bg-[#00e07a]/10 border border-[#00e07a]/15 font-mono">
                  <CheckCircle2 size={20} className="text-[#00e07a]" />
                  <div>
                    <p className="text-sm font-medium text-[#00e07a]">
                      All caught up!
                    </p>
                    <p className="text-xs text-muted-foreground">
                      No pending actions.
                    </p>
                  </div>
                </div>
              )}
            </div>

            {/* ── Pipeline Health ────────────────────────────── */}
            <div
              className="mb-8"
              style={{ animation: "fadeSlideUp 0.5s ease-out 300ms both" }}
            >
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-2">
                  <Briefcase size={18} className="text-[#9b6dff]" />
                  <h2
                    className="text-base font-bold text-foreground font-['Space_Grotesk']"
                  >
                    JOB PIPELINES // ACTIVE STATUS
                  </h2>
                </div>
                <button
                  onClick={() => onViewChange({ view: "Jobs" })}
                  className="text-xs font-mono font-semibold text-[#9b6dff] hover:text-foreground cursor-pointer bg-transparent border-0 transition-colors"
                >
                  [ ALL ACTIVE JOBS ]
                </button>
              </div>

              {data.jobs_health.length > 0 ? (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {data.jobs_health.map((job) => (
                    <JobHealthCard
                      key={job.id}
                      job={job}
                      onViewChange={onViewChange}
                    />
                  ))}
                </div>
              ) : (
                <div className="bg-card border border-border rounded-xl p-10 text-center">
                  <Briefcase
                    size={32}
                    className="text-muted-foreground mx-auto mb-3"
                  />
                  <p className="text-sm text-muted-foreground mb-1 font-mono">
                    No active jobs
                  </p>
                  <p className="text-xs text-muted-foreground font-mono">
                    Create your first job posting.
                  </p>
                  <button
                    onClick={() => onViewChange({ view: "Jobs" })}
                    className="mt-4 px-4 py-2 rounded-lg text-xs font-mono font-semibold bg-gradient-to-r from-[#9b6dff] to-[#00e07a] text-primary-foreground cursor-pointer border-0 hover:opacity-90 transition-opacity"
                  >
                    [ CREATE JOB ]
                  </button>
                </div>
              )}
            </div>

            {/* ── Split Layout ───────────────────────────────── */}
            <div
              className="grid grid-cols-1 lg:grid-cols-5 gap-6"
              style={{ animation: "fadeSlideUp 0.5s ease-out 400ms both" }}
            >
              {/* Left: Recent Activity Feed (60%) */}
              <div className="lg:col-span-3 bg-card border border-border rounded-xl p-6">
                <div className="flex items-center gap-2 mb-5">
                  <Activity size={18} className="text-[#9b6dff]" />
                  <h2
                    className="text-base font-bold text-foreground font-['Space_Grotesk']"
                  >
                    SYSTEM LOGS // RECENT ACTIVITY
                  </h2>
                </div>

                {data.activities.length > 0 ? (
                  <div className="space-y-1">
                    {data.activities.map((act, idx) => (
                      <div
                        key={act.id || idx}
                        className="flex items-start gap-3 p-3 rounded-lg hover:bg-muted transition-colors group"
                      >
                        <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-white/[0.03] border border-border flex items-center justify-center mt-0.5">
                          {getActivityIcon(act.action)}
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="text-sm text-foreground/90 leading-snug">
                            {act.description}
                          </p>
                          <div className="flex items-center gap-2 mt-1">
                            {act.candidate_name && (
                              <span className="text-xs font-mono font-semibold text-[#9b6dff]">
                                {act.candidate_name}
                              </span>
                            )}
                            {act.candidate_name && act.time_ago && (
                              <span className="text-muted-foreground">·</span>
                            )}
                            <span className="text-[11px] font-mono text-muted-foreground">
                              {act.time_ago}
                            </span>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="py-10 text-center font-mono">
                    <Activity
                      size={28}
                      className="text-muted-foreground mx-auto mb-2"
                    />
                    <p className="text-sm text-muted-foreground">
                      No recent activity
                    </p>
                  </div>
                )}
              </div>

              {/* Right: Upcoming Interviews (40%) */}
              <div className="lg:col-span-2 bg-card border border-border rounded-xl p-6">
                <div className="flex items-center gap-2 mb-5">
                  <Calendar size={18} className="text-[#4f8ef7]" />
                  <h2
                    className="text-base font-bold text-foreground font-['Space_Grotesk']"
                  >
                    UPCOMING SESSIONS // CANDIDATE INTERVIEWS
                  </h2>
                </div>

                {data.upcoming_interviews.length > 0 ? (
                  <div className="space-y-3">
                    {data.upcoming_interviews.map((interview) => (
                      <button
                        key={interview.id}
                        onClick={() =>
                          onViewChange({
                            view: "Interviews",
                            candidateId: interview.candidate_id,
                          })
                        }
                        className="w-full text-left p-3.5 rounded-lg bg-white/[0.01] border border-border hover:border-blue-500/20 hover:bg-blue-500/5 transition-all cursor-pointer group"
                      >
                        <div className="flex items-start justify-between mb-1.5">
                          <span className="text-sm font-medium text-foreground group-hover:text-blue-300 transition-colors">
                            {interview.candidate_name}
                          </span>
                          <span className="text-[10px] font-mono font-medium px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 border border-blue-500/20 flex-shrink-0 ml-2">
                            {`[ ${interview.status} ]`}
                          </span>
                        </div>
                        <p className="text-xs text-muted-foreground mb-1.5 font-mono">
                          {interview.job_title}
                        </p>
                        <div className="flex items-center gap-2 text-[10px] text-muted-foreground font-mono">
                          <Clock size={11} />
                          <span>
                            {interview.formatted_date} at{" "}
                            {interview.formatted_time}
                          </span>
                          {interview.interview_type && (
                            <>
                              <span className="text-gray-700">·</span>
                              <span>{interview.interview_type}</span>
                            </>
                          )}
                        </div>
                      </button>
                    ))}
                  </div>
                ) : (
                  <div className="py-10 text-center font-mono">
                    <Calendar
                      size={28}
                      className="text-muted-foreground mx-auto mb-2"
                    />
                    <p className="text-sm text-muted-foreground">
                      No upcoming interviews
                    </p>
                  </div>
                )}
              </div>
            </div>
          </>
        ) : null}
      </div>
    </div>
  );
}
