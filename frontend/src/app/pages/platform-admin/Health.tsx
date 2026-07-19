import { apiFetch } from "../../lib/apiClient";
import { useEffect, useState } from "react";
import { CheckCircle2, XCircle, RefreshCw, HeartPulse } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));

type Check = {
  name: string;
  status: "pass" | "fail";
  detail: string;
};

export function PlatformAdminHealth() {
  const [checks, setChecks] = useState<Check[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [lastRun, setLastRun] = useState<Date | null>(null);

  const run = () => {
    setLoading(true);
    setError("");
    apiFetch(`${API_BASE}/api/index.php?route=health&action=check`, { credentials: "include" })
      .then((r) => r.json())
      .then((d) => {
        if (d.success) { setChecks(d.checks ?? []); setLastRun(new Date()); }
        else setError(d.error ?? "Failed to run checks.");
      })
      .catch(() => setError("Could not reach server."))
      .finally(() => setLoading(false));
  };

  useEffect(() => { run(); }, []);

  const passing = checks.filter((c) => c.status === "pass").length;
  const failing = checks.filter((c) => c.status === "fail").length;
  const allGreen = failing === 0 && checks.length > 0;

  return (
    <div className="p-8 max-w-4xl mx-auto">
      <div className="mb-8 flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-foreground" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
            System Health
          </h1>
          <p className="text-slate-500 text-sm mt-1">
            Verifying environment prerequisites and system configuration.
            {lastRun && <span className="ml-2 text-slate-600">Last run: {lastRun.toLocaleTimeString()}</span>}
          </p>
        </div>
        <button
          onClick={run}
          className="flex items-center gap-2 px-4 py-2 rounded-lg bg-card/[0.03] border border-white/[0.07] text-muted-foreground hover:text-foreground text-sm transition-colors"
        >
          <RefreshCw size={14} className={loading ? "animate-spin" : ""} /> Re-run Checks
        </button>
      </div>

      {/* Summary bar */}
      {!loading && checks.length > 0 && (
        <div className={`mb-6 rounded-2xl border px-6 py-4 flex items-center gap-4 ${
          allGreen
            ? "bg-emerald-500/5 border-emerald-500/20"
            : "bg-red-500/5 border-red-500/20"
        }`}>
          <HeartPulse size={22} className={allGreen ? "text-emerald-400" : "text-red-400"} />
          <div>
            <p className={`font-semibold text-sm ${allGreen ? "text-emerald-300" : "text-red-300"}`}>
              {allGreen ? "All systems operational" : `${failing} check${failing > 1 ? "s" : ""} failing`}
            </p>
            <p className="text-xs text-slate-500 mt-0.5">
              {passing} / {checks.length} checks passing
            </p>
          </div>
          {/* Mini progress bar */}
          <div className="flex-1 h-1.5 bg-card/50 rounded-full ml-4 overflow-hidden">
            <div
              className={`h-full rounded-full transition-all ${allGreen ? "bg-emerald-500" : "bg-red-500"}`}
              style={{ width: `${(passing / checks.length) * 100}%` }}
            />
          </div>
        </div>
      )}

      {error && <div className="bg-red-500/10 border border-red-500/20 rounded-xl p-4 text-red-400 text-sm mb-6">{error}</div>}

      {loading && (
        <div className="flex items-center gap-2 text-slate-500 py-16">
          <div className="w-5 h-5 border-2 border-violet-500/40 border-t-violet-500 rounded-full animate-spin" />
          Running diagnostics…
        </div>
      )}

      <div className="space-y-2">
        {!loading && checks.map((c, i) => (
          <div
            key={i}
            className={`flex items-start gap-4 px-5 py-4 rounded-xl border transition-all ${
              c.status === "pass"
                ? "bg-background border-border hover:border-white/[0.08]"
                : "bg-red-500/5 border-red-500/20"
            }`}
          >
            {c.status === "pass" ? (
              <CheckCircle2 size={18} className="text-emerald-400 flex-shrink-0 mt-0.5" />
            ) : (
              <XCircle size={18} className="text-red-400 flex-shrink-0 mt-0.5" />
            )}
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-foreground">{c.name}</p>
              <p className={`text-xs mt-0.5 ${c.status === "pass" ? "text-slate-500" : "text-red-400/80"}`}>{c.detail}</p>
            </div>
            <span className={`text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-full border flex-shrink-0 ${
              c.status === "pass"
                ? "text-emerald-400 bg-emerald-500/10 border-emerald-500/20"
                : "text-red-400 bg-red-500/10 border-red-500/20"
            }`}>
              {c.status}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}
