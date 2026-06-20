import { useState } from "react";
import { CheckCircle, Clock } from "lucide-react";
import type { ViewState } from "./Sidebar";

export function Approvals({ onViewChange }: { onViewChange: (v: ViewState) => void }) {
  const [activeTab, setActiveTab] = useState("pending");

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
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-primary blur-[120px] opacity-[0.06] pointer-events-none z-0" />

      <div className="mb-8 relative z-10 border-b border-border pb-6">
        <h1 className="text-2xl font-bold font-['Space_Grotesk'] tracking-tight text-foreground flex items-center gap-1.5">
          SYSTEM APPROVALS // WORKFLOW CONSOLE
          <span className="inline-block w-2.5 h-5 bg-primary blink"></span>
        </h1>
        <p className="text-xs font-mono text-muted-foreground mt-1 uppercase tracking-wider">Manage active Job Requisitions, salary increases, and Offer Letter approval workflows.</p>
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
                ? "bg-primary border-[#00e07a] text-primary shadow-[0_0_10px_rgba(0,224,122,0.1)]"
                : "bg-muted border-border text-muted-foreground hover:text-foreground"
            }`}
          >
            {`[ ${tab.label} ]`}
          </button>
        ))}
      </div>

      <div className="flex-1 border border-border bg-background/60 rounded-2xl overflow-hidden relative z-10 flex flex-col items-center justify-center py-20 font-mono">
        <CheckCircle size={36} className="mb-4 text-primary opacity-60" />
        <h2 className="text-sm font-bold text-foreground uppercase tracking-wider">STATUS: ALL CAUGHT UP</h2>
        <p className="text-xs text-muted-foreground mt-2 max-w-sm text-center">No active approvals or requisition requests are currently awaiting your signature.</p>
      </div>
    </div>
  );
}
