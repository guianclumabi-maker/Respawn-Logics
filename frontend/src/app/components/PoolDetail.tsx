import { useState, useEffect } from "react";
import { ArrowLeft, Users, Mail, MapPin, Star } from "lucide-react";
import type { ViewState } from "./Sidebar";

const API_BASE = import.meta.env.VITE_API_BASE_URL;
const API = `${API_BASE}/api/index.php?route=candidates`;

type Candidate = {
  id: number;
  name: string;
  email: string;
  location: string;
  skills_array: string[];
  experience_years: number;
  application_count: number;
  added_at: string;
  added_by: string;
};

type PoolData = {
  id: number;
  name: string;
  description: string;
  members: Candidate[];
};

export function PoolDetail({ onViewChange, poolId }: { onViewChange: (v: ViewState) => void; poolId: number }) {
  const [pool, setPool] = useState<PoolData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch(`${API}&action=pool&id=${poolId}`)
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setPool(d.pool);
      })
      .catch((err) => console.error("Error fetching pool details:", err))
      .finally(() => setLoading(false));
  }, [poolId]);

  if (loading) {
    return (
      <div className="flex-1 flex items-center justify-center bg-[#0b0f1a]">
        <div className="animate-spin w-8 h-8 border-2 border-[#00e07a] border-t-transparent rounded-full" />
      </div>
    );
  }

  if (!pool) return <div className="text-foreground p-8 font-mono bg-[#0b0f1a]">[ ERROR: TALENT POOL RECORD OFFLINE ]</div>;

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
      <div className="absolute top-[-100px] right-[-100px] w-[500px] h-[500px] rounded-full bg-[#9b6dff] blur-[120px] opacity-[0.06] pointer-events-none z-0" />

      <button
        onClick={() => onViewChange({ view: "Talent Pools" })}
        className="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground mb-6 w-fit transition-colors relative z-10 bg-transparent border-0 font-mono"
      >
        <ArrowLeft size={14} />
        [ BACK TO POOLS ]
      </button>

      <div className="mb-8 relative z-10 border-b border-border pb-6 font-mono">
        <h1 className="text-2xl font-bold font-['Space_Grotesk'] tracking-tight text-foreground flex items-center gap-1.5 mb-2">
          {`TALENT POOL // ${pool.name.toUpperCase()}`}
          <span className="inline-block w-2.5 h-5 bg-primary blink"></span>
        </h1>
        <p className="text-xs text-muted-foreground max-w-2xl font-sans">{pool.description || "No description provided."}</p>
        <div className="flex items-center gap-2 mt-4 text-[9px] font-bold text-primary bg-primary border border-[#00e07a]/20 px-3 py-1 rounded w-fit">
          <Users size={12} />
          {`[ MEMBERS: ${pool.members.length} ]`}
        </div>
      </div>

      <div className="flex-1 border border-border bg-background/40 rounded-2xl overflow-hidden relative z-10 flex flex-col font-mono">
        {pool.members.length === 0 ? (
          <div className="flex-1 flex flex-col items-center justify-center text-muted-foreground p-8 text-center py-20">
            <Users size={36} className="mb-4 opacity-30 text-primary" />
            <p className="text-xs uppercase font-bold tracking-wider">POOL DIRECTORY EMPTY</p>
          </div>
        ) : (
          <div className="flex-1 overflow-y-auto scrollbar-thin">
            <table className="w-full text-left border-collapse">
              <thead className="sticky top-0 bg-[#121625] z-10 text-[9px] uppercase font-bold text-muted-foreground tracking-wider border-b border-border shadow-md shadow-black/10">
                <tr>
                  <th className="px-6 py-4">// CANDIDATE</th>
                  <th className="px-6 py-4">// DETAILS</th>
                  <th className="px-6 py-4">// KEY SKILLS</th>
                  <th className="px-6 py-4">// RECORD CREATED</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/[0.04] text-xs">
                {pool.members.map((m) => (
                  <tr
                    key={m.id}
                    className="hover:bg-muted transition-colors cursor-pointer group"
                    onClick={() => onViewChange({ view: "Candidate Profile", candidateId: m.id })}
                  >
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded bg-muted border border-border flex items-center justify-center text-xs font-bold text-[#9b6dff]">
                          {`[ ${m.name.substring(0, 2).toUpperCase()} ]`}
                        </div>
                        <div>
                          <div className="font-semibold text-foreground group-hover:text-primary transition-colors">{m.name}</div>
                          <div className="text-[10px] text-muted-foreground flex items-center gap-1 mt-0.5"><Mail size={10} /> {m.email || "No email"}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="space-y-1 text-[11px] text-muted-foreground">
                        <div className="flex items-center gap-1.5">
                          <MapPin size={11} className="text-primary" />
                          {m.location || "Unknown"}
                        </div>
                        <div className="flex items-center gap-1.5">
                          <Star size={11} className="text-primary" />
                          {m.experience_years} years exp
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex flex-wrap gap-1.5 max-w-[200px]">
                        {m.skills_array.slice(0, 3).map((s, i) => (
                          <span key={i} className="text-[9px] px-2 py-0.5 rounded border border-[#9b6dff]/20 bg-[#9b6dff]/10 text-[#9b6dff]">{s}</span>
                        ))}
                      </div>
                    </td>
                    <td className="px-6 py-4 text-[11px] text-muted-foreground">
                      <div>{m.added_at.split(' ')[0]}</div>
                      <div className="text-[10px] text-muted-foreground mt-0.5">by {m.added_by || "System"}</div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
