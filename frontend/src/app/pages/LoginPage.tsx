import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { Eye, EyeOff, Loader2, AlertCircle, Zap, Sparkles, User, Shield, Briefcase, ArrowRight } from "lucide-react";
import { useAuth } from "../context/AuthContext";

export function LoginPage() {
  const { user, loading, login, switchPersona } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState("demo@respawn.logics");
  const [password, setPassword] = useState("demo123");
  const [showPw, setShowPw] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");

  // Already logged in → bounce to dashboard
  useEffect(() => {
    if (!loading && user) navigate("/dashboard", { replace: true });
  }, [user, loading, navigate]);

  const handleQuickPersona = (persona: 'employee' | 'manager' | 'admin') => {
    switchPersona(persona);
    navigate("/dashboard", { replace: true });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setSubmitting(true);
    const targetEmail = email.trim() || "demo@respawn.logics";
    const targetPassword = password || "demo123";
    const result = await login(targetEmail, targetPassword);
    setSubmitting(false);
    if (result.success) {
      if (result.redirect) {
        window.location.href = result.redirect;
      } else {
        navigate("/dashboard", { replace: true });
      }
    } else {
      setError(result.error || "Login failed.");
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#070a12] flex items-center justify-center">
        <Loader2 className="w-8 h-8 text-[#00e07a] animate-spin" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#070a12] flex items-center justify-center p-4 relative overflow-hidden">
      {/* Background glows */}
      <div className="absolute top-[-200px] left-[-150px] w-[700px] h-[700px] rounded-full bg-[#00e07a] blur-[160px] opacity-[0.05] pointer-events-none" />
      <div className="absolute bottom-[-200px] right-[-150px] w-[600px] h-[600px] rounded-full bg-[#9b6dff] blur-[140px] opacity-[0.06] pointer-events-none" />

      {/* Grid overlay */}
      <div
        className="absolute inset-0 opacity-[0.025] pointer-events-none"
        style={{
          backgroundImage:
            "linear-gradient(rgba(0,224,122,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(0,224,122,0.5) 1px, transparent 1px)",
          backgroundSize: "60px 60px",
        }}
      />

      <div className="relative w-full max-w-lg">
        {/* Logo */}
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-[#00e07a] to-[#00b8ff] shadow-[0_0_40px_rgba(0,224,122,0.4)] mb-3">
            <i className="fa-solid fa-gamepad text-black text-2xl" />
          </div>
          <h1
            className="text-2xl font-bold text-white tracking-tight"
            style={{ fontFamily: "'Space Grotesk', sans-serif" }}
          >
            Respawn Logics
          </h1>
          <p className="text-slate-400 text-sm mt-1">Presentation & Demo Workspace</p>
        </div>

        {/* Card */}
        <div
          className="bg-white/[0.03] border border-white/[0.08] rounded-2xl p-6 md:p-8 shadow-2xl backdrop-blur-sm"
          style={{ boxShadow: "0 0 60px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.05)" }}
        >
          {/* ⚡ INSTANT DEMO ROLES (NO PASSWORD) */}
          <div className="mb-6 p-4 rounded-xl bg-gradient-to-b from-white/[0.05] to-transparent border border-[#00e07a]/30">
            <div className="flex items-center justify-between mb-3">
              <span className="text-xs font-bold text-[#00e07a] tracking-wider uppercase flex items-center gap-1.5 font-mono">
                <Sparkles size={14} className="text-[#00e07a]" /> 1-Click Instant Access
              </span>
              <span className="text-[10px] text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded font-mono font-bold">
                Zero Password Required
              </span>
            </div>

            <div className="space-y-2">
              <button
                type="button"
                onClick={() => handleQuickPersona('employee')}
                className="w-full py-2.5 px-3 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-left flex items-center justify-between transition group cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <div className="w-8 h-8 rounded-lg bg-emerald-500/20 text-[#00e07a] flex items-center justify-center font-bold text-sm">
                    👤
                  </div>
                  <div>
                    <div className="text-xs font-bold text-white group-hover:text-[#00e07a] transition">David Kim (Regular Employee)</div>
                    <div className="text-[10px] text-slate-400">Employee Self-Service: Leaves, Attendance, Payslips</div>
                  </div>
                </div>
                <div className="flex items-center gap-1 text-[11px] font-mono font-bold text-[#00e07a] group-hover:translate-x-1 transition">
                  Enter <ArrowRight size={12} />
                </div>
              </button>

              <button
                type="button"
                onClick={() => handleQuickPersona('manager')}
                className="w-full py-2.5 px-3 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 text-left flex items-center justify-between transition group cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <div className="w-8 h-8 rounded-lg bg-blue-500/20 text-[#4f8ef7] flex items-center justify-center font-bold text-sm">
                    👔
                  </div>
                  <div>
                    <div className="text-xs font-bold text-white group-hover:text-[#4f8ef7] transition">Sarah Chen (Manager / Lead)</div>
                    <div className="text-[10px] text-slate-400">Manager Approvals, Team Timesheets, Shifts & Roster</div>
                  </div>
                </div>
                <div className="flex items-center gap-1 text-[11px] font-mono font-bold text-[#4f8ef7] group-hover:translate-x-1 transition">
                  Enter <ArrowRight size={12} />
                </div>
              </button>

              <button
                type="button"
                onClick={() => handleQuickPersona('admin')}
                className="w-full py-2.5 px-3 rounded-lg bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 text-left flex items-center justify-between transition group cursor-pointer"
              >
                <div className="flex items-center gap-2.5">
                  <div className="w-8 h-8 rounded-lg bg-purple-500/20 text-[#9b6dff] flex items-center justify-center font-bold text-sm">
                    🛡️
                  </div>
                  <div>
                    <div className="text-xs font-bold text-white group-hover:text-[#9b6dff] transition">Peter Parker (Platform Super Admin)</div>
                    <div className="text-[10px] text-slate-400">Full Access: ATS, Global Payroll, Settings, Audit Logs</div>
                  </div>
                </div>
                <div className="flex items-center gap-1 text-[11px] font-mono font-bold text-[#9b6dff] group-hover:translate-x-1 transition">
                  Enter <ArrowRight size={12} />
                </div>
              </button>
            </div>
          </div>

          <div className="relative flex items-center my-5">
            <div className="flex-grow border-t border-white/[0.08]"></div>
            <span className="flex-shrink-0 mx-3 text-slate-500 text-[10px] font-mono tracking-wider uppercase">Or sign in with any credentials</span>
            <div className="flex-grow border-t border-white/[0.08]"></div>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Error */}
            {error && (
              <div className="flex items-center gap-2 p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-sm">
                <AlertCircle size={15} className="flex-shrink-0" />
                {error}
              </div>
            )}

            {/* Email */}
            <div className="space-y-1">
              <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                Email address
              </label>
              <input
                type="text"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="demo@respawn.logics"
                autoComplete="email"
                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 text-sm outline-none focus:border-[#00e07a]/50 focus:ring-1 focus:ring-[#00e07a]/20 transition-all font-mono"
              />
            </div>

            {/* Password */}
            <div className="space-y-1">
              <div className="flex justify-between items-center">
                <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                  Password
                </label>
                <span className="text-[10px] text-slate-500 font-mono">Any password accepted</span>
              </div>
              <div className="relative">
                <input
                  type={showPw ? "text" : "password"}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Any password or leave default"
                  autoComplete="current-password"
                  className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 pr-11 text-white placeholder-slate-500 text-sm outline-none focus:border-[#00e07a]/50 focus:ring-1 focus:ring-[#00e07a]/20 transition-all font-mono"
                />
                <button
                  type="button"
                  onClick={() => setShowPw((v) => !v)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors"
                  tabIndex={-1}
                >
                  {showPw ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
            </div>

            {/* Submit */}
            <button
              type="submit"
              disabled={submitting}
              className="w-full py-3 rounded-xl font-bold text-sm text-black transition-all flex items-center justify-center gap-2 mt-3 cursor-pointer"
              style={{
                background: "linear-gradient(135deg, #00e07a 0%, #00b8ff 100%)",
                boxShadow: submitting ? "none" : "0 0 25px rgba(0,224,122,0.3)",
              }}
            >
              {submitting ? (
                <>
                  <Loader2 size={16} className="animate-spin" />
                  Entering Workspace...
                </>
              ) : (
                <>
                  <Zap size={16} />
                  Sign In (Instant Access)
                </>
              )}
            </button>
          </form>

          <div className="mt-4 pt-3 border-t border-white/[0.06] flex items-center justify-between text-xs text-slate-400">
            <a
              href="presentation.html"
              target="_blank"
              rel="noreferrer"
              className="text-[#00e07a] hover:underline flex items-center gap-1 font-mono font-medium"
            >
              ★ Open Slide Deck
            </a>
            <a
              href="index.php"
              className="text-slate-400 hover:text-white transition-colors"
            >
              ← Back to Homepage
            </a>
          </div>
        </div>

        <p className="text-center text-xs text-slate-600 mt-6">
          © {new Date().getFullYear()} Respawn Logics · HR Platform v2.0
        </p>
      </div>
    </div>
  );
}
