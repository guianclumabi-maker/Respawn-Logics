import { useState, useEffect } from "react";
import { Database, Plus, Users, Calendar, ChevronRight } from "lucide-react";
import type { ViewState } from "./Sidebar";

const API_BASE = import.meta.env.VITE_API_BASE_URL;
const API = `${API_BASE}/api/index.php?route=candidates`;

type Pool = {
  id: number;
  name: string;
  description: string;
  created_by: string;
  formatted_date: string;
  member_count: number;
};

export function TalentPools({ onViewChange }: { onViewChange: (v: ViewState) => void }) {
  const [pools, setPools] = useState<Pool[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch(`${API}&action=talent_pools`)
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setPools(d.pools);
      })
      .catch((err) => console.error("Error fetching talent pools:", err))
      .finally(() => setLoading(false));
  }, []);

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

      <div className="flex items-center justify-between mb-8 relative z-10 border-b border-border pb-6 font-mono">
        <div>
          <h1 className="text-2xl font-bold font-['Space_Grotesk'] tracking-tight text-foreground flex items-center gap-1.5">
            TALENT REGISTRY // POOL DIRECTORY
            <span className="inline-block w-2.5 h-5 bg-primary blink"></span>
          </h1>
          <p className="text-xs text-muted-foreground mt-1 uppercase tracking-wider font-mono">Curate groups of candidates for future hiring and passive sourcing campaigns.</p>
        </div>
        <button className="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold bg-primary hover:opacity-90 text-primary-foreground cursor-pointer border-0 shadow-lg shadow-green-500/10 transition-all">
          <Plus size={16} />
          [ CREATE POOL ]
        </button>
      </div>

      <div className="flex-1 relative z-10 font-mono">
        {loading ? (
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin w-8 h-8 border-2 border-[#00e07a] border-t-transparent rounded-full" />
          </div>
        ) : pools.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-64 text-muted-foreground border border-border bg-background/40 rounded-2xl">
            <Database size={36} className="mb-4 opacity-30 text-primary" />
            <p className="text-xs uppercase font-bold tracking-wider">NO TALENT POOLS CREATED YET</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {pools.map((p) => (
              <div
                key={p.id}
                onClick={() => onViewChange({ view: "Pool Detail", poolId: p.id })}
                className="p-5 rounded-xl border border-border bg-background hover:border-[#00e07a]/40 hover:bg-background/80 transition-all cursor-pointer group flex flex-col justify-between"
              >
                <div>
                  <div className="flex items-center justify-between mb-4">
                    <div className="w-8 h-8 rounded bg-muted border border-border flex items-center justify-center text-primary">
                      <Database size={14} />
                    </div>
                    <span className="text-[10px] text-muted-foreground group-hover:text-foreground transition-colors">[ VIEW POOL ]</span>
                  </div>
                  <h3 className="text-sm font-bold text-foreground mb-2 group-hover:text-primary transition-colors">{p.name}</h3>
                  <p className="text-xs text-muted-foreground line-clamp-2 mb-6 leading-relaxed font-sans">{p.description || "No description provided."}</p>
                </div>
                <div className="flex items-center justify-between pt-3 border-t border-border text-[10px] text-muted-foreground">
                  <div className="flex items-center gap-1.5 font-bold text-[#9b6dff]">
                    <Users size={12} />
                    {`[ MEMBERS: ${p.member_count} ]`}
                  </div>
                  <div className="flex items-center gap-1">
                    <Calendar size={11} />
                    {p.formatted_date.split(' ')[0]}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
