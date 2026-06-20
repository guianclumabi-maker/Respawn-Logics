import { useState } from "react";
import { Search, MapPin, Star, Filter, Users, X, ChevronRight } from "lucide-react";
import type { ViewState } from "./Sidebar";

const API_BASE = import.meta.env.VITE_API_BASE_URL;
const API = `${API_BASE}/api/index.php?route=candidates`;

type SearchResult = {
  id: number;
  name: string;
  email: string;
  location: string;
  skills_array: string[];
  experience_years: number;
  status: string;
  source: string;
  application_count: number;
};

export function TalentSearch({ onViewChange }: { onViewChange: (v: ViewState) => void }) {
  const [query, setQuery] = useState({ skills: "", location: "", min_experience: "", source: "" });
  const [results, setResults] = useState<SearchResult[]>([]);
  const [hasSearched, setHasSearched] = useState(false);
  const [loading, setLoading] = useState(false);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setHasSearched(true);
    
    let url = `${API}&action=search`;
    if (query.skills) url += `&skills=${encodeURIComponent(query.skills)}`;
    if (query.location) url += `&location=${encodeURIComponent(query.location)}`;
    if (query.min_experience) url += `&min_experience=${encodeURIComponent(query.min_experience)}`;
    if (query.source) url += `&source=${encodeURIComponent(query.source)}`;

    fetch(url)
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setResults(d.results);
      })
      .catch((err) => console.error("Error executing search:", err))
      .finally(() => setLoading(false));
  };

  const handleClear = () => {
    setQuery({ skills: "", location: "", min_experience: "", source: "" });
    setResults([]);
    setHasSearched(false);
  };

  const inputCls = "w-full bg-card border border-white/10 rounded-xl pl-9 pr-3 py-2 text-xs font-mono outline-none focus:border-[#00e07a] focus:shadow-[0_0_10px_rgba(0,224,122,0.15)] transition-all";
  const labelCls = "text-[9px] font-mono font-bold uppercase text-muted-foreground tracking-wide block mb-1";

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
      <div className="absolute top-[10%] right-[20%] w-[600px] h-[600px] rounded-full bg-primary blur-[150px] opacity-[0.03] pointer-events-none" />

      <div className="mb-8 relative z-10 border-b border-border pb-6">
        <h1 className="text-2xl font-bold font-['Space_Grotesk'] tracking-tight text-foreground flex items-center gap-1.5">
          CANDIDATE DISCOVERY // QUERY ENGINE
          <span className="inline-block w-2.5 h-5 bg-primary blink"></span>
        </h1>
        <p className="text-xs font-mono text-muted-foreground mt-1 uppercase tracking-wider">Candidate Rediscovery Engine - scan historic records to match active roles.</p>
      </div>

      <div className="bg-background border border-white/10 rounded-2xl p-5 mb-8 relative z-10">
        <form onSubmit={handleSearch} className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="space-y-1.5">
            <label className={labelCls}>Skills (comma separated)</label>
            <div className="relative">
              <Search size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <input
                type="text"
                placeholder="React, PHP, Sales..."
                value={query.skills}
                onChange={(e) => setQuery({ ...query, skills: e.target.value })}
                className={inputCls}
              />
            </div>
          </div>
          <div className="space-y-1.5">
            <label className={labelCls}>Location</label>
            <div className="relative">
              <MapPin size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <input
                type="text"
                placeholder="New York, Remote..."
                value={query.location}
                onChange={(e) => setQuery({ ...query, location: e.target.value })}
                className={inputCls}
              />
            </div>
          </div>
          <div className="space-y-1.5">
            <label className={labelCls}>Min Experience (Yrs)</label>
            <div className="relative">
              <Star size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <input
                type="number"
                min="0"
                placeholder="e.g. 3"
                value={query.min_experience}
                onChange={(e) => setQuery({ ...query, min_experience: e.target.value })}
                className={inputCls}
              />
            </div>
          </div>
          <div className="space-y-1.5">
            <label className={labelCls}>Source</label>
            <div className="relative font-mono">
              <Filter size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <select
                value={query.source}
                onChange={(e) => setQuery({ ...query, source: e.target.value })}
                className="w-full bg-card border border-white/10 rounded-xl pl-9 pr-3 py-2 text-xs text-gray-300 outline-none focus:border-[#00e07a] appearance-none cursor-pointer"
              >
                <option value="" className="bg-background">Any Source</option>
                <option value="Direct" className="bg-background">Direct</option>
                <option value="LinkedIn" className="bg-background">LinkedIn</option>
                <option value="Referral" className="bg-background">Referral</option>
                <option value="Agency" className="bg-background">Agency</option>
              </select>
            </div>
          </div>
          
          <div className="lg:col-span-4 flex items-center justify-end gap-3 pt-2 font-mono">
            {hasSearched && (
              <button
                type="button"
                onClick={handleClear}
                className="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-muted-foreground hover:text-foreground hover:bg-white/5 transition-colors bg-transparent border-0"
              >
                <X size={14} /> [ CLEAR QUERY ]
              </button>
            )}
            <button
              type="submit"
              disabled={loading}
              className="flex items-center gap-2 px-6 py-2.5 rounded-xl text-xs font-bold bg-primary hover:opacity-90 text-primary-foreground hover:opacity-90 disabled:opacity-50 transition-opacity border-0 shadow-lg shadow-green-500/10"
            >
              {loading ? <div className="animate-spin w-4 h-4 border-2 border-black/20 border-t-black rounded-full" /> : <Search size={14} />}
              [ INITIATE SCAN ]
            </button>
          </div>
        </form>
      </div>

      <div className="flex-1 border border-white/5 bg-background/40 rounded-2xl overflow-hidden relative z-10 flex flex-col font-mono">
        {!hasSearched ? (
          <div className="flex-1 flex flex-col items-center justify-center text-muted-foreground p-8 text-center py-20">
            <Search size={36} className="mb-4 opacity-30 text-primary" />
            <p className="text-xs uppercase font-bold tracking-wider text-foreground mb-2">Awaiting System Input</p>
            <p className="text-xs max-w-sm leading-relaxed">Enter queries above to filter previous candidate profiles in your database.</p>
          </div>
        ) : loading ? (
          <div className="flex-1 flex items-center justify-center">
            <div className="animate-spin w-8 h-8 border-2 border-[#00e07a] border-t-transparent rounded-full" />
          </div>
        ) : results.length === 0 ? (
          <div className="flex-1 flex flex-col items-center justify-center text-muted-foreground p-8 text-center py-20">
            <Users size={36} className="mb-4 opacity-30 text-red-400" />
            <p className="text-xs uppercase font-bold tracking-wider text-foreground">0 MATCHES DETECTED</p>
            <p className="text-xs mt-1">Try broadening your scanning criteria.</p>
          </div>
        ) : (
          <div className="flex-1 overflow-y-auto scrollbar-thin p-6">
            <div className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-4 border-b border-border pb-2">
              Found {results.length} Match{results.length !== 1 && 'es'}
            </div>
            <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
              {results.map((r) => (
                <div
                  key={r.id}
                  onClick={() => onViewChange({ view: "Candidate Profile", candidateId: r.id })}
                  className="flex p-4 rounded-xl border border-white/10 bg-background hover:border-[#00e07a]/40 hover:bg-background/80 transition-all cursor-pointer group"
                >
                  <div className="w-10 h-10 rounded bg-muted border border-border flex items-center justify-center text-xs font-bold text-[#9b6dff] shrink-0 mr-4">
                    {`[ ${r.name.substring(0, 2).toUpperCase()} ]`}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between mb-1">
                      <h3 className="font-bold text-foreground group-hover:text-primary transition-colors truncate text-sm">{r.name}</h3>
                      <span className="text-[9px] font-bold uppercase px-2 py-0.5 rounded border border-gray-500/20 text-muted-foreground bg-gray-500/10">
                        {`[ ${r.status.toUpperCase()} ]`}
                      </span>
                    </div>
                    <div className="flex items-center gap-3 text-xs text-muted-foreground mb-2.5 font-mono">
                      <span className="flex items-center gap-1 truncate"><MapPin size={11} className="text-primary" /> {r.location || "Unknown"}</span>
                      <span className="flex items-center gap-1 shrink-0"><Star size={11} className="text-primary" /> {r.experience_years} yrs</span>
                    </div>
                    <div className="flex flex-wrap gap-1.5 font-mono">
                      {r.skills_array.slice(0, 4).map((s, i) => (
                        <span key={i} className="text-[9px] px-2 py-0.5 rounded bg-[#9b6dff]/10 text-[#9b6dff] border border-[#9b6dff]/20">
                          {s}
                        </span>
                      ))}
                      {r.skills_array.length > 4 && (
                        <span className="text-[9px] px-2 py-0.5 rounded bg-muted text-muted-foreground border border-border">
                          {`+${r.skills_array.length - 4}`}
                        </span>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center justify-center pl-4 border-l border-white/5 ml-4 text-muted-foreground group-hover:text-foreground transition-colors shrink-0">
                    <ChevronRight size={20} />
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
