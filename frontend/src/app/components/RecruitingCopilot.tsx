import { useState, useEffect } from "react";
import { Bot, Clock, ClipboardList, AlertTriangle, ChevronRight, UserCheck, CheckCircle } from "lucide-react";
import type { ViewState } from "./Sidebar";

const API_BASE = import.meta.env.VITE_API_BASE_URL;
const API = `${API_BASE}/api/index.php?route=candidates`;

type ActionItem = {
  type: string;
  priority: string;
  icon: string;
  message: string;
  action: string;
  action_view: string;
  job_id?: number;
};

export function RecruitingCopilot({ onViewChange }: { onViewChange: (v: ViewState) => void }) {
  const [actions, setActions] = useState<ActionItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch(`${API}&action=ai_actions`, { credentials: "include" })
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setActions(d.recommendations || []);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const getIcon = (name: string, priority: string) => {
    const colorClass = priority === 'high' ? 'text-red-400' : 'text-[#9b6dff]';
    switch (name) {
      case 'clock': return <Clock size={20} className={colorClass} />;
      case 'clipboard': return <ClipboardList size={20} className={colorClass} />;
      case 'alert': return <AlertTriangle size={20} className={colorClass} />;
      case 'user-check': return <UserCheck size={20} className={colorClass} />;
      default: return <Bot size={20} className={colorClass} />;
    }
  };

  return (
    <div className="flex-1 flex flex-col h-full bg-[#0b0f1a] text-foreground p-8 relative overflow-hidden font-sans">
      <style>{`
        .blink {
          animation: blink-anim 1.1s step-start infinite;
        }
        @keyframes blink-anim {
          0%, 100% { opacity: 1; }
          50%       { opacity: 0; }
        }
      `}</style>
      <div className="absolute top-[-100px] left-[50%] -translate-x-1/2 w-[800px] h-[400px] rounded-[100%] bg-gradient-to-b from-[#00e07a]/10 to-transparent blur-[80px] pointer-events-none" />

      <div className="flex items-center gap-4 mb-10 relative z-10 border-b border-border pb-6">
        <div className="w-12 h-12 rounded bg-background border border-border flex items-center justify-center font-mono text-base font-bold text-primary flex-shrink-0">
          <Bot size={24} />
        </div>
        <div>
          <h1 className="text-2xl font-bold font-['Space_Grotesk'] tracking-tight text-foreground flex items-center gap-1.5">
            RECRUITING COPILOT // TERMINAL PILOT v2.0
            <span className="inline-block w-2.5 h-5 bg-primary blink"></span>
          </h1>
          <p className="text-xs font-mono text-muted-foreground mt-1 uppercase tracking-wider">your autonomous assistant for proactive candidate pipeline calibration.</p>
        </div>
      </div>

      <div className="flex-1 max-w-4xl w-full mx-auto relative z-10 space-y-6 font-mono">
        <div className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-4 border-b border-border pb-2">
          {`// RECOMMENDED ACTION ITEMS (${actions.length})`}
        </div>

        {loading ? (
          <div className="flex justify-center py-12">
            <div className="animate-spin w-8 h-8 border-2 border-[#00e07a] border-t-transparent rounded-full" />
          </div>
        ) : actions.length === 0 ? (
          <div className="bg-background border border-white/5 rounded-2xl p-12 text-center flex flex-col items-center py-20">
            <CheckCircle size={36} className="text-primary opacity-60 mb-4" />
            <h3 className="text-sm font-bold text-foreground uppercase tracking-wider mb-2">STATUS: DIAGNOSTICS CLEAR</h3>
            <p className="text-xs text-muted-foreground max-w-md mx-auto leading-relaxed">
              Recruiting Copilot hasn't detected any stalled candidates or active bottlenecks in your pipeline.
            </p>
          </div>
        ) : (
          <div className="grid gap-4">
            {actions.map((act, i) => (
              <div
                key={i}
                className="group flex items-center p-4 rounded-xl border bg-background hover:bg-background/80 transition-all cursor-pointer relative overflow-hidden"
                style={{
                  borderColor: act.priority === 'high' ? 'rgba(248, 113, 113, 0.2)' : 'rgba(255, 255, 255, 0.06)',
                }}
                onClick={() => onViewChange({ view: act.action_view, jobId: act.job_id })}
              >
                {act.priority === 'high' && (
                  <div className="absolute left-0 top-0 bottom-0 w-1 bg-red-500/50 shadow-[0_0_10px_rgba(248,113,113,0.5)]" />
                )}
                
                <div className={`w-10 h-10 rounded border flex items-center justify-center shrink-0 mr-4 ${
                  act.priority === 'high' ? 'bg-red-500/10 border-red-500/20' : 'bg-[#9b6dff]/10 border-[#9b6dff]/20'
                }`}>
                  {getIcon(act.icon, act.priority)}
                </div>

                <div className="flex-1 min-w-0 pr-6">
                  <div className="text-[9px] font-bold uppercase tracking-wider mb-1" style={{ color: act.priority === 'high' ? '#f87171' : '#9b6dff' }}>
                    {`[ ${act.type.replace('_', ' ').toUpperCase()} ]`}
                  </div>
                  <h3 className="text-sm font-bold text-foreground group-hover:text-primary transition-colors truncate">
                    {act.message}
                  </h3>
                </div>

                <button className="flex items-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-bold bg-muted border border-border hover:border-white/20 hover:bg-accent transition-colors whitespace-nowrap text-foreground shrink-0">
                  {`[ ${act.action.toUpperCase()} ]`}
                  <ChevronRight size={12} className="text-muted-foreground group-hover:text-foreground" />
                </button>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
