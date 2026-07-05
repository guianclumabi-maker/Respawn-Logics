import { useState, useEffect, useRef, useCallback } from "react";
import {
  Clock,
  Calendar,
  CheckSquare,
  Briefcase,
  CheckCircle2,
  Circle,
  Plus,
  Loader2,
  AlertCircle,
  Check,
  HelpCircle,
} from "lucide-react";
import { useAuth } from "../context/AuthContext";
import { useTour } from "../lib/useTour";

const API_BASE =
  import.meta.env.VITE_API_BASE_URL ||
  window.location.origin +
    (window.location.hostname === "localhost" ? "/respawn-logics" : "");
const API = `${API_BASE}/api/index.php?route=dashboard`;

type Todo = {
  id: number;
  task_name: string;
  task_description: string;
  is_completed: number;
};

type Stats = {
  clocked_in_today: boolean;
  clock_time: string;
  total_hours: number;
  pending_leaves: number;
  active_tasks_count: number;
  todo_list: Todo[];
};

// ── Live clock ──────────────────────────────────────────────────
function useLiveClock() {
  const [time, setTime] = useState({ date: "", clock: "00:00:00 AM" });

  useEffect(() => {
    function tick() {
      const now = new Date();
      let h = now.getHours();
      const ampm = h >= 12 ? "PM" : "AM";
      h = h % 12 || 12;
      const hh = String(h).padStart(2, "0");
      const mm = String(now.getMinutes()).padStart(2, "0");
      const ss = String(now.getSeconds()).padStart(2, "0");
      setTime({
        clock: `${hh}:${mm}:${ss} ${ampm}`,
        date: now.toLocaleDateString("en-US", {
          weekday: "long",
          year: "numeric",
          month: "long",
          day: "numeric",
        }),
      });
    }
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, []);

  return time;
}

// ── Metric card ──────────────────────────────────────────────────
function MetricCard({
  label,
  value,
  sub,
  icon,
  accent,
}: {
  label: string;
  value: string;
  sub: string;
  icon: React.ReactNode;
  accent: string;
}) {
  return (
    <div className="relative bg-card text-card-foreground border border-border rounded-xl p-5 flex flex-col gap-3 overflow-hidden group hover:border-slate-300 dark:hover:border-white/10 transition-colors">
      <div className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity"
           style={{ background: `radial-gradient(circle at top left, ${accent}08 0%, transparent 70%)` }} />
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider">{label}</span>
        <span style={{ color: accent }} className="opacity-70">{icon}</span>
      </div>
      <div className="text-3xl font-bold text-foreground font-mono tracking-tight">{value}</div>
      <div className="text-xs text-muted-foreground">{sub}</div>
    </div>
  );
}

// ── Main component ───────────────────────────────────────────────
export function HomeDashboard() {
  const { user } = useAuth();
  const { startTour } = useTour("dashboard_tour", [
    { element: "#tour-metric-cards", popover: { title: "Dashboard Metrics", description: "Get an at-a-glance overview of your hours, clock state, pending leaves, and active tasks." } },
    { element: "#tour-shift-clock", popover: { title: "Real-Time Shift Clock", description: "Monitor your current attendance status and exact clock time here." } },
    { element: "#tour-tasks", popover: { title: "My Tasks Checklist", description: "Stay organized by managing your daily tasks. Add new items and cross them off as you complete them!" } }
  ]);
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [newTask, setNewTask] = useState("");
  const [addingTask, setAddingTask] = useState(false);
  const [togglingId, setTogglingId] = useState<number | null>(null);
  const { date, clock } = useLiveClock();
  const inputRef = useRef<HTMLInputElement>(null);

  const fetchStats = useCallback(async () => {
    try {
      const res = await fetch(`${API}&action=get_stats`, {
        credentials: "include",
      });
      if (res.status === 401 || res.status === 403) {
        setError("Access denied. Please log in.");
        setLoading(false);
        return;
      }
      const json = await res.json();
      if (json.success) {
        setStats(json.data);
        setError("");
      } else {
        setError(json.error || "Failed to load dashboard.");
      }
    } catch {
      setError("Unable to reach the server.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  const toggleTask = async (taskId: number) => {
    setTogglingId(taskId);
    // Optimistic
    setStats((prev) =>
      prev
        ? {
            ...prev,
            todo_list: prev.todo_list.map((t) =>
              t.id === taskId ? { ...t, is_completed: t.is_completed ? 0 : 1 } : t
            ),
            active_tasks_count: prev.todo_list.find((t) => t.id === taskId)
              ?.is_completed
              ? prev.active_tasks_count + 1
              : prev.active_tasks_count - 1,
          }
        : prev
    );
    try {
      await fetch(`${API}&action=toggle_task`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ task_id: taskId }),
      });
      await fetchStats();
    } finally {
      setTogglingId(null);
    }
  };

  const addTask = async (e: React.FormEvent) => {
    e.preventDefault();
    const name = newTask.trim();
    if (!name) return;
    setAddingTask(true);
    try {
      await fetch(`${API}&action=add_task`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ task_name: name }),
      });
      setNewTask("");
      await fetchStats();
      inputRef.current?.focus();
    } finally {
      setAddingTask(false);
    }
  };

  // ── Loading ──
  if (loading) {
    return (
      <div className="flex-1 flex items-center justify-center bg-background h-full w-full">
        <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  // ── Error ──
  if (error) {
    return (
      <div className="flex-1 flex items-center justify-center bg-background h-full w-full p-8">
        <div className="bg-destructive/10 text-destructive p-6 rounded-xl border border-destructive/20 text-center max-w-md">
          <p className="font-bold mb-2">Failed to load dashboard</p>
          <p className="text-sm opacity-90">{error}</p>
        </div>
      </div>
    );
  }

  const firstName = user?.name?.split(" ")[0] || "there";
  const d = stats!;

  return (
    <div className="flex-1 overflow-y-auto bg-background text-foreground p-8 h-full">
      <div className="w-full max-w-none space-y-8 relative">

        {/* ── Header ── */}
        <div className="flex items-center justify-between">
          <div>
            <h1
              className="text-3xl font-bold text-foreground mb-1"
              style={{ fontFamily: "'Space Grotesk', sans-serif" }}
            >
              Welcome back,{" "}
              <span className="text-primary">{firstName}!</span>
            </h1>
            <p className="text-muted-foreground text-sm">
              Viewing portal with active configurations.
            </p>
          </div>
          <button 
            onClick={startTour} 
            className="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-muted-foreground bg-muted/30 hover:bg-muted rounded-full transition-colors"
            title="Take a Tour"
          >
            <HelpCircle size={16} />
            <span>Help Tour</span>
          </button>
        </div>

        {/* ── Metric Cards ── */}
        <div id="tour-metric-cards" className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <MetricCard
            label="Weekly Working Hours"
            value={`${d.total_hours.toFixed(1)}h`}
            sub="Calculated from past 7 days"
            icon={<Clock size={20} />}
            accent="#00e07a"
          />
          <MetricCard
            label="Today's Clock State"
            value={d.clocked_in_today ? "In Since" : "Not Active"}
            sub={d.clocked_in_today ? d.clock_time : "No shift active today"}
            icon={<Briefcase size={20} />}
            accent="#00b8ff"
          />
          <MetricCard
            label="Pending Leave Requests"
            value={String(d.pending_leaves)}
            sub="Awaiting management approval"
            icon={<Calendar size={20} />}
            accent="#f5a623"
          />
          <MetricCard
            label="Tasks Pending"
            value={String(d.active_tasks_count)}
            sub="Checklist items left to complete"
            icon={<CheckSquare size={20} />}
            accent="#9b6dff"
          />
        </div>

        {/* ── Main Grid ── */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">

          {/* Panel A — Real-Time Shift Clock */}
          <div id="tour-shift-clock" className="bg-card text-card-foreground border border-border rounded-xl p-6 flex flex-col">
            <div className="flex items-center justify-between mb-6">
              <h3 className="font-semibold text-foreground">Real-Time Shift Clock</h3>
              <span
                className={`px-3 py-1 rounded-full text-xs font-mono font-bold border ${
                  d.clocked_in_today
                    ? "bg-emerald-500/10 text-emerald-500 border-emerald-500/30"
                    : "bg-red-500/10 text-red-500 border-red-500/30"
                }`}
              >
                {d.clocked_in_today ? "Clocked In" : "Clocked Out"}
              </span>
            </div>

            <div className="flex-1 flex flex-col items-center justify-center py-10">
              <div className="text-muted-foreground text-sm mb-2 font-mono">{date}</div>
              <div
                className="text-5xl font-extrabold tracking-tight font-mono text-primary"
                style={{
                  textShadow: "0 0 40px rgba(0,224,122,0.3)",
                }}
              >
                {clock}
              </div>
              <p className="text-muted-foreground text-sm mt-6 text-center max-w-[240px] leading-relaxed">
                {d.clocked_in_today
                  ? "You are clocked in. Work hard, stay focused!"
                  : "Tap the Attendance tab in the sidebar to clock in."}
              </p>
            </div>
          </div>

          {/* Panel B — My Tasks Checklist */}
          <div id="tour-tasks" className="bg-card text-card-foreground border border-border rounded-xl p-6 flex flex-col">
            <div className="flex items-center justify-between mb-4">
              <h3 className="font-semibold text-foreground">My Tasks Checklist</h3>
              <span
                className="px-3 py-1 rounded-full text-xs font-bold border border-primary/20 bg-primary/10 text-primary"
              >
                {d.active_tasks_count} Active
              </span>
            </div>

            <div className="flex-1 overflow-y-auto space-y-2 min-h-[180px]">
              {d.todo_list.length === 0 ? (
                <div className="text-center text-muted-foreground text-sm py-8">
                  No tasks found. Create one below to begin.
                </div>
              ) : (
                d.todo_list.map((todo) => {
                  const done = !!todo.is_completed;
                  const toggling = togglingId === todo.id;
                  return (
                    <div
                      key={todo.id}
                      className={`flex items-center justify-between px-4 py-3 rounded-lg border transition-all ${
                        done
                          ? "border-primary/30 bg-primary/5"
                          : "border-border bg-card hover:border-border/80"
                      }`}
                    >
                      <div className="flex items-center gap-3 min-w-0">
                        <button
                          onClick={() => toggleTask(todo.id)}
                          disabled={toggling}
                          className={`w-[18px] h-[18px] flex-shrink-0 rounded border flex items-center justify-center transition-all ${
                            done
                              ? "bg-primary border-primary text-primary-foreground"
                              : "border-muted-foreground hover:border-primary"
                          } ${toggling ? "opacity-50" : ""}`}
                        >
                          {done && <Check size={11} strokeWidth={3} />}
                        </button>
                        <span
                          className={`text-sm font-medium truncate ${
                            done ? "line-through text-muted-foreground" : "text-foreground"
                          }`}
                        >
                          {todo.task_name}
                        </span>
                      </div>
                      <span
                        className={`text-xs flex-shrink-0 ml-2 ${
                          done ? "text-primary" : "text-muted-foreground"
                        }`}
                      >
                        {done ? "Done" : "Active"}
                      </span>
                    </div>
                  );
                })
              )}
            </div>

            <form
              onSubmit={addTask}
              className="flex gap-2 mt-4 pt-4 border-t border-border"
            >
              <input
                ref={inputRef}
                type="text"
                value={newTask}
                onChange={(e) => setNewTask(e.target.value)}
                placeholder="New task name..."
                className="flex-1 bg-background border border-input rounded-lg px-3 py-2 text-sm text-foreground placeholder-muted-foreground outline-none focus:border-primary/50 transition-colors"
              />
              <button
                type="submit"
                disabled={!newTask.trim() || addingTask}
                className="px-4 py-2 bg-primary/10 hover:bg-primary/20 border border-primary/30 text-primary text-sm font-semibold rounded-lg transition-all disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1"
              >
                {addingTask ? (
                  <Loader2 size={14} className="animate-spin" />
                ) : (
                  <Plus size={14} />
                )}
                Add
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
}
