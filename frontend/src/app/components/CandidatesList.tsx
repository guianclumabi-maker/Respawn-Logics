import { useState, useEffect } from "react";
import { Users, Search, Filter, ChevronRight, MapPin, Star } from "lucide-react";
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
  status: string;
  source: string;
  application_count: number;
  pool_count: number;
};

export function CandidatesList({ onViewChange }: { onViewChange: (v: ViewState) => void }) {
  const [candidates, setCandidates] = useState<Candidate[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");

  useEffect(() => {
    setLoading(true);
    let url = `${API}&action=candidates&limit=100`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    if (statusFilter) url += `&status=${encodeURIComponent(statusFilter)}`;

    fetch(url, { credentials: "include" })
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setCandidates(d.candidates);
      })
      .catch((err) => console.error("Error fetching candidates:", err))
      .finally(() => setLoading(false));
  }, [search, statusFilter]);

  const statusBadge = (status: string) => {
    const map: Record<string, string> = {
      Active: "bg-emerald-500/10 border-emerald-500/20 text-emerald-400",
      Archived: "bg-gray-500/10 border-gray-500/20 text-muted-foreground",
      Blacklisted: "bg-red-500/10 border-red-500/20 text-red-400",
    };
    return map[status] || map.Active;
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
      <div className="absolute top-[-100px] right-[-100px] w-[500px] h-[500px] rounded-full bg-[#9b6dff] blur-[120px] opacity-[0.06] pointer-events-none z-0" />

      <div className="flex items-center justify-between mb-8 relative z-10 border-b border-border pb-6">
        <div>
          <h1 className="text-2xl font-bold font-['Space_Grotesk'] tracking-tight text-foreground flex items-center gap-1.5">
            CANDIDATES REGISTRY // CENTRAL CRM CONSOLE
            <span className="inline-block w-2.5 h-5 bg-primary blink"></span>
          </h1>
          <p className="text-xs font-mono text-muted-foreground mt-1 uppercase tracking-wider">centralized directory of previous applicants, direct submissions, and passive talent pools.</p>
        </div>
      </div>

      <div className="flex gap-4 mb-6 relative z-10 font-mono">
        <div className="relative flex-1 max-w-md">
          <div className="flex items-center gap-2 px-3 py-2 rounded-xl border bg-card backdrop-blur-md border-border focus-within:border-[#00e07a]/40">
            <Search size={14} className="text-muted-foreground" />
            <input
              type="text"
              placeholder="Search candidate records..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="flex-1 bg-transparent outline-none text-xs text-foreground placeholder-gray-600"
            />
          </div>
        </div>
        <div className="relative">
          <div className="flex items-center gap-2 px-3 py-2 rounded-xl border bg-card backdrop-blur-md border-border focus-within:border-[#00e07a]/40">
            <Filter size={14} className="text-muted-foreground" />
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="appearance-none bg-transparent text-xs text-gray-300 outline-none cursor-pointer pr-4"
            >
              <option value="" className="bg-background">All Statuses</option>
              <option value="Active" className="bg-background">Active</option>
              <option value="Archived" className="bg-background">Archived</option>
              <option value="Blacklisted" className="bg-background">Blacklisted</option>
            </select>
          </div>
        </div>
      </div>

      <div className="flex-1 border border-border bg-background/40 rounded-2xl overflow-hidden relative z-10 flex flex-col font-mono">
        {loading ? (
          <div className="flex-1 flex items-center justify-center">
            <div className="animate-spin w-8 h-8 border-2 border-[#00e07a] border-t-transparent rounded-full" />
          </div>
        ) : candidates.length === 0 ? (
          <div className="flex-1 flex items-center justify-center flex-col text-muted-foreground py-20">
            <Users size={36} className="mb-4 opacity-30 text-primary" />
            <p className="text-xs uppercase font-bold tracking-wider">NO CANDIDATE RECORDS FOUND</p>
          </div>
        ) : (
          <div className="flex-1 overflow-y-auto scrollbar-thin">
            <table className="w-full text-left border-collapse">
              <thead className="sticky top-0 bg-[#121625] z-10 text-[9px] uppercase font-bold text-muted-foreground tracking-wider border-b border-border shadow-md shadow-black/10">
                <tr>
                  <th className="px-6 py-4">// CANDIDATE</th>
                  <th className="px-6 py-4">// DETAILS</th>
                  <th className="px-6 py-4">// KEY SKILLS</th>
                  <th className="px-6 py-4">// STATUS</th>
                  <th className="px-6 py-4 text-right">// ACTIONS</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/[0.04] text-xs">
                {candidates.map((c) => (
                  <tr
                    key={c.id}
                    className="hover:bg-muted transition-colors group cursor-pointer"
                    onClick={() => onViewChange({ view: "Candidate Profile", candidateId: c.id })}
                  >
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded bg-muted border border-border flex items-center justify-center text-xs font-bold text-[#9b6dff]">
                          {`[ ${c.name.substring(0, 2).toUpperCase()} ]`}
                        </div>
                        <div>
                          <div className="font-semibold text-foreground group-hover:text-primary transition-colors">
                            {c.name}
                          </div>
                          <div className="text-[10px] text-muted-foreground mt-0.5">{c.email || "No email"}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="space-y-1 text-[11px] text-muted-foreground">
                        <div className="flex items-center gap-1.5">
                          <MapPin size={11} className="text-primary" />
                          {c.location || "Unknown"}
                        </div>
                        <div className="flex items-center gap-1.5">
                          <Star size={11} className="text-primary" />
                          {`${c.experience_years} years exp`}
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex flex-wrap gap-1.5 max-w-[240px]">
                        {c.skills_array.slice(0, 3).map((s, i) => (
                          <span
                            key={i}
                            className="text-[9px] px-2 py-0.5 rounded border border-[#9b6dff]/20 bg-[#9b6dff]/10 text-[#9b6dff]"
                          >
                            {s}
                          </span>
                        ))}
                        {c.skills_array.length > 3 && (
                          <span className="text-[9px] px-2 py-0.5 rounded border border-border bg-muted text-muted-foreground">
                            {`+${c.skills_array.length - 3}`}
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`text-[9px] font-bold uppercase px-2.5 py-0.5 rounded border ${statusBadge(c.status)}`}>
                        {`[ ${c.status.toUpperCase()} ]`}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <button className="px-2.5 py-1 text-[9px] font-bold text-muted-foreground hover:text-foreground rounded bg-muted border border-border hover:bg-accent transition-colors">
                        [ PROFILE ]
                      </button>
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
