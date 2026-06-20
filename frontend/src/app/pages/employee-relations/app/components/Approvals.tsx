import { useState } from "react";
import { CheckCircle, Clock } from "lucide-react";

export function Approvals({ onViewChange }: { onViewChange: (v: any) => void }) {
  const [activeTab, setActiveTab] = useState("pending");

  return (
    <div className="flex-1 flex flex-col h-full bg-[#0b0f1a] text-white p-8 relative overflow-hidden font-sans">
      <style>{`
        .blink {
          animation: blink-anim 1.1s step-start infinite;
        }
        @keyframes blink-anim {
          0%, 100% { opacity: 1; }
          50%       { opacity: 0; }
        }
      `}</style>
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-[#10b981] blur-[120px] opacity-[0.06] pointer-events-none z-0" />

      <div className="mb-8 relative z-10 border-b border-white/[0.04] pb-6">
        <h1 className="text-2xl font-bold font-['Space_Grotesk'] tracking-tight text-white flex items-center gap-1.5">
          SYSTEM APPROVALS // ELR CONSOLE
          <span className="inline-block w-2.5 h-5 bg-[#10b981] blink"></span>
        </h1>
        <p className="text-xs font-mono text-slate-400 mt-1 uppercase tracking-wider">Manage active Disciplinary Actions, Terminations, and Resolution approvals.</p>
      </div>

      <div className="flex items-center gap-3 mb-6 relative z-10 font-mono">
        {[
          { id: "pending", label: "PENDING REQUESTS" },
          { id: "history", label: "APPROVAL HISTORY" }
        ].map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`px-4 py-2.5 text-xs font-bold transition-all border rounded-xl cursor-pointer ${
              activeTab === tab.id
                ? "bg-[#10b981]/10 border-[#10b981] text-[#10b981] shadow-[0_0_10px_rgba(16,185,129,0.1)]"
                : "bg-white/[0.02] border-white/[0.06] text-slate-400 hover:text-white"
            }`}
          >
            {`[ ${tab.label} ]`}
          </button>
        ))}
      </div>

      <div className="flex-1 border border-white/[0.04] bg-[#0f1422]/50 rounded-2xl overflow-hidden relative z-10 flex flex-col items-center justify-center py-20 font-mono">
        <CheckCircle size={36} className="mb-4 text-[#10b981] opacity-60" />
        <h2 className="text-sm font-bold text-white uppercase tracking-wider">STATUS: ALL CAUGHT UP</h2>
        <p className="text-xs text-slate-400 mt-2 max-w-sm text-center">No active resolutions or terminations are currently awaiting your signature.</p>
      </div>
    </div>
  );
}
