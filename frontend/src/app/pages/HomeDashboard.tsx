import { useState, useEffect } from "react";
import { ThemeProvider } from "next-themes";
import { CheckCircle2, Circle, Plus, Clock, Calendar, CheckSquare, Briefcase } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=dashboard`;

type Todo = {
  id: number;
  task_name: string;
  task_description: string;
  is_completed: number;
};

type DashboardStats = {
  clocked_in_today: boolean;
  clock_time: string;
  total_hours: number;
  pending_leaves: number;
  active_tasks_count: number;
  todo_list: Todo[];
};

export function HomeDashboard() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [newTaskName, setNewTaskName] = useState("");

  const fetchStats = async () => {
    try {
      const res = await fetch(`${API}&action=get_stats`, { credentials: "include" });
      const data = await res.json();
      if (data.success) {
        setStats(data.data);
      }
    } catch (err) {
      console.error("Failed to load dashboard stats", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchStats();
  }, []);

  const toggleTask = async (taskId: number) => {
    if (!stats) return;
    
    // Optimistic update
    setStats({
      ...stats,
      todo_list: stats.todo_list.map(t => t.id === taskId ? { ...t, is_completed: t.is_completed ? 0 : 1 } : t)
    });

    try {
      await fetch(`${API}&action=toggle_task`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: taskId }),
        credentials: 'include'
      });
      fetchStats();
    } catch (err) {
      console.error(err);
      fetchStats(); // Revert
    }
  };

  const addTask = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newTaskName.trim()) return;

    try {
      await fetch(`${API}&action=add_task`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_name: newTaskName }),
        credentials: 'include'
      });
      setNewTaskName("");
      fetchStats();
    } catch (err) {
      console.error(err);
    }
  };

  if (loading) {
    return (
      <div className="flex-1 flex items-center justify-center bg-[#0a0f1a] h-full w-full">
        <div className="text-[#00e07a] font-mono text-sm animate-pulse">LOADING_DATA...</div>
      </div>
    );
  }

  return (
    <ThemeProvider attribute="data-theme" defaultTheme="dark">
      <div className="h-full w-full flex-1 overflow-y-auto bg-[#0b0f1a] text-slate-200 p-8">
        <div className="max-w-6xl mx-auto space-y-8">
          
          {/* Header */}
          <div>
            <h1 className="text-3xl font-bold text-white mb-2" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>Main Workspace</h1>
            <p className="text-slate-400">Your HR overview and daily checklist.</p>
          </div>

          {/* Metrics Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <MetricCard 
              title="Weekly Working Hours" 
              value={`${stats?.total_hours.toFixed(1)}h`} 
              subtext="Calculated from past 7 days"
              icon={<Clock className="text-[#00e07a] opacity-80" size={24} />}
            />
            <MetricCard 
              title="Today's Clock State" 
              value={stats?.clocked_in_today ? "In Since" : "Not Active"} 
              subtext={stats?.clocked_in_today ? stats.clock_time : "No shift active today"}
              icon={<Briefcase className="text-[#00b8ff] opacity-80" size={24} />}
            />
            <MetricCard 
              title="Pending Leave Requests" 
              value={stats?.pending_leaves.toString() || "0"} 
              subtext="Awaiting management approval"
              icon={<Calendar className="text-[#f5a623] opacity-80" size={24} />}
            />
            <MetricCard 
              title="Tasks Pending" 
              value={stats?.active_tasks_count.toString() || "0"} 
              subtext="Checklist items left to complete"
              icon={<CheckSquare className="text-[#9b6dff] opacity-80" size={24} />}
            />
          </div>

          {/* Main Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            {/* Realtime Clock Widget */}
            <div className="bg-[#141929] border border-white/5 rounded-xl p-6 flex flex-col relative overflow-hidden">
              <div className="flex items-center justify-between mb-8">
                <h3 className="font-semibold text-white">Real-Time Shift Clock</h3>
                <span className={`px-3 py-1 rounded-full text-xs font-mono font-bold ${stats?.clocked_in_today ? 'bg-[#00e07a]/10 text-[#00e07a] border border-[#00e07a]/30' : 'bg-red-500/10 text-red-500 border border-red-500/30'}`}>
                  {stats?.clocked_in_today ? 'Clocked In' : 'Clocked Out'}
                </span>
              </div>
              
              <div className="flex-1 flex flex-col items-center justify-center py-12 z-10">
                <RealtimeClockDisplay />
                <p className="text-sm text-slate-400 mt-6">
                  {stats?.clocked_in_today 
                    ? "You are clocked in. Work hard, stay focused!" 
                    : "Tap the Attendance tab to clock in."}
                </p>
              </div>

              {/* Background Glow */}
              <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-[#00e07a] rounded-full blur-[100px] opacity-[0.03] pointer-events-none" />
            </div>

            {/* Todo List Widget */}
            <div className="bg-[#141929] border border-white/5 rounded-xl p-6 flex flex-col max-h-[500px]">
              <div className="flex items-center justify-between mb-6">
                <h3 className="font-semibold text-white">My Tasks Checklist</h3>
                <span className="px-3 py-1 rounded-full text-xs font-mono font-bold bg-[#9b6dff]/10 text-[#9b6dff] border border-[#9b6dff]/30">
                  {stats?.active_tasks_count} Active
                </span>
              </div>

              <div className="flex-1 overflow-y-auto space-y-2 pr-2 custom-scrollbar">
                {!stats?.todo_list?.length ? (
                  <div className="text-center text-slate-500 py-8 text-sm">
                    No tasks found. Create one below to begin.
                  </div>
                ) : (
                  stats.todo_list.map(task => (
                    <div 
                      key={task.id} 
                      onClick={() => toggleTask(task.id)}
                      className={`flex items-center justify-between p-3 rounded-lg border cursor-pointer transition-colors ${
                        task.is_completed 
                          ? 'bg-[#00e07a]/5 border-[#00e07a]/30' 
                          : 'bg-white/5 border-white/5 hover:border-white/10'
                      }`}
                    >
                      <div className="flex items-center gap-3">
                        {task.is_completed ? (
                          <CheckCircle2 className="text-[#00e07a]" size={20} />
                        ) : (
                          <Circle className="text-slate-500" size={20} />
                        )}
                        <span className={`text-sm ${task.is_completed ? 'line-through text-slate-500' : 'text-slate-200'}`}>
                          {task.task_name}
                        </span>
                      </div>
                      <span className={`text-[10px] uppercase font-bold tracking-wider ${task.is_completed ? 'text-[#00e07a]' : 'text-slate-500'}`}>
                        {task.is_completed ? 'Done' : 'Active'}
                      </span>
                    </div>
                  ))
                )}
              </div>

              <form onSubmit={addTask} className="mt-4 pt-4 border-t border-white/5 flex gap-2">
                <input
                  type="text"
                  value={newTaskName}
                  onChange={(e) => setNewTaskName(e.target.value)}
                  placeholder="New task name..."
                  className="flex-1 bg-black/20 border border-white/10 rounded-md px-3 py-2 text-sm focus:outline-none focus:border-[#00e07a]/50 text-white"
                />
                <button 
                  type="submit"
                  disabled={!newTaskName.trim()}
                  className="bg-[#00e07a] text-black px-4 py-2 rounded-md font-bold text-sm hover:bg-white transition-colors disabled:opacity-50 flex items-center justify-center"
                >
                  <Plus size={18} />
                </button>
              </form>
            </div>

          </div>
        </div>
      </div>

      <style>{`
        .custom-scrollbar::-webkit-scrollbar {
          width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
          background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
          background: rgba(255, 255, 255, 0.1);
          border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
          background: rgba(255, 255, 255, 0.2);
        }
      `}</style>
    </ThemeProvider>
  );
}

// Subcomponents

function MetricCard({ title, value, subtext, icon }: { title: string, value: string, subtext: string, icon: React.ReactNode }) {
  return (
    <div className="bg-[#141929] border border-white/5 rounded-xl p-5 flex flex-col relative overflow-hidden group hover:border-white/10 transition-colors">
      <div className="flex items-start justify-between mb-4">
        <h4 className="text-slate-400 text-sm font-medium">{title}</h4>
        <div className="p-2 bg-white/5 rounded-lg group-hover:scale-110 transition-transform">
          {icon}
        </div>
      </div>
      <div className="text-3xl font-bold text-white mb-1">{value}</div>
      <div className="text-xs text-slate-500">{subtext}</div>
    </div>
  );
}

function RealtimeClockDisplay() {
  const [time, setTime] = useState(new Date());

  useEffect(() => {
    const timer = setInterval(() => setTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  const options: Intl.DateTimeFormatOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  
  return (
    <div className="text-center">
      <div className="text-slate-400 mb-2 font-medium tracking-wide">
        {time.toLocaleDateString('en-US', options)}
      </div>
      <div 
        className="text-5xl md:text-6xl font-black tracking-tight"
        style={{ 
          fontFamily: "'Space Grotesk', sans-serif",
          background: "linear-gradient(90deg, #00e07a, #00b8ff)",
          WebkitBackgroundClip: "text",
          WebkitTextFillColor: "transparent"
        }}
      >
        {time.toLocaleTimeString('en-US')}
      </div>
    </div>
  );
}
