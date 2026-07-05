import { useState } from "react";
import { Search, UserSwitch, AlertCircle } from "lucide-react";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));

export function PlatformAdminImpersonate() {
  const [tenantId, setTenantId] = useState("");
  const [error, setError] = useState("");

  const handleImpersonate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!tenantId.trim()) {
      setError("Please enter a Tenant ID");
      return;
    }
    setError("");
    
    // Open in new tab
    const url = `${API_BASE}/pages/impersonate.php?action=start&tenant_id=${encodeURIComponent(tenantId.trim())}`;
    window.open(url, "_blank", "noopener,noreferrer");
  };

  return (
    <div className="p-8 max-w-3xl mx-auto mt-10">
      <div className="bg-[#0c1018] border border-white/[0.05] rounded-2xl p-8 relative overflow-hidden">
        {/* Decorative background element */}
        <div className="absolute top-0 right-0 w-64 h-64 bg-violet-500/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 pointer-events-none" />
        
        <div className="relative z-10">
          <div className="w-12 h-12 bg-violet-500/10 border border-violet-500/20 rounded-xl flex items-center justify-center mb-6">
            <UserSwitch size={24} className="text-violet-400" />
          </div>
          
          <h1 className="text-2xl font-bold text-white mb-2" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
            Impersonate Tenant
          </h1>
          <p className="text-slate-400 text-sm mb-8 max-w-lg leading-relaxed">
            Directly log in as the primary Super Admin of any tenant. 
            The tenant must have explicitly granted you support access in their settings. 
            All actions taken while impersonating will be logged in the audit trail under your name.
          </p>

          <form onSubmit={handleImpersonate} className="max-w-md">
            {error && (
              <div className="flex items-center gap-2 text-red-400 text-sm bg-red-500/10 border border-red-500/20 rounded-lg p-3 mb-4">
                <AlertCircle size={16} /> {error}
              </div>
            )}
            
            <div className="mb-6">
              <label className="block text-xs font-medium tracking-wide text-slate-500 uppercase mb-2">
                Tenant ID
              </label>
              <div className="relative">
                <Search size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
                <input
                  type="text"
                  value={tenantId}
                  onChange={(e) => setTenantId(e.target.value)}
                  placeholder="e.g. t_6a48f93feeb07"
                  className="w-full pl-10 pr-4 py-3 bg-[#080b12] border border-white/[0.07] rounded-xl text-slate-200 placeholder-slate-600 focus:outline-none focus:border-violet-500/50 transition-colors"
                />
              </div>
            </div>

            <button
              type="submit"
              className="w-full flex items-center justify-center gap-2 bg-violet-600 hover:bg-violet-500 text-white font-medium py-3 rounded-xl transition-all shadow-[0_0_20px_rgba(124,58,237,0.15)] hover:shadow-[0_0_25px_rgba(124,58,237,0.25)] cursor-pointer"
            >
              <UserSwitch size={18} />
              Start Impersonation Session
            </button>
          </form>
        </div>
      </div>
      
      <div className="mt-8 flex items-start gap-3 p-4 bg-amber-500/5 border border-amber-500/10 rounded-xl">
        <AlertCircle size={18} className="text-amber-400 flex-shrink-0 mt-0.5" />
        <div className="text-sm text-amber-200/70 leading-relaxed">
          <strong>Security Note:</strong> Impersonation sessions open in a new tab. When you are finished, you must click "Stop Impersonating" in the banner of the tenant dashboard, or manually close the tab and log back in to your admin account.
        </div>
      </div>
    </div>
  );
}
