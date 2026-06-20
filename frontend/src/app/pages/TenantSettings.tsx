import { useState } from "react";
import { ShieldAlert } from "lucide-react";

export function TenantSettings() {
  const [granted, setGranted] = useState(false);
  const [loading, setLoading] = useState(false);

  const grantSupportAccess = async () => {
    setLoading(true);
    try {
      const API_BASE = import.meta.env.VITE_API_BASE_URL || '';
      const csrfToken = (window as any).__CSRF_TOKEN__ || '';
      const res = await fetch(`${API_BASE}/api/index.php?route=iam&action=grant_support_access`, {
        method: 'POST',
        headers: {
          'X-CSRF-Token': csrfToken,
          'Content-Type': 'application/json'
        }
      });
      const data = await res.json();
      if (data.success) {
        setGranted(true);
      } else {
        alert("Failed to grant access: " + data.error);
      }
    } catch (e) {
      alert("Error granting access.");
    }
    setLoading(false);
  };

  return (
    <div className="h-full w-full flex flex-col items-center justify-center p-8 text-center" style={{ backgroundColor: '#0b0f19', color: '#8899b4' }}>
      <div className="max-w-md space-y-6 bg-[#141929] p-8 rounded-2xl border border-white/5">
        <div className="w-16 h-16 bg-blue-500/10 rounded-2xl border border-blue-500/20 flex items-center justify-center mx-auto shadow-[0_0_20px_rgba(59,130,246,0.15)]">
          <ShieldAlert className="w-8 h-8 text-blue-400" />
        </div>
        <div>
          <h2 className="text-2xl font-bold text-white tracking-tight">Tenant Settings</h2>
          <p className="text-sm mt-2 text-gray-400">Manage your workspace settings and data privacy compliance (RA 10173).</p>
        </div>
        
        <div className="pt-6 border-t border-white/10 text-left">
          <h3 className="text-lg font-semibold text-white mb-2">Platform Support Access</h3>
          <p className="text-xs text-gray-400 mb-4">By default, Respawn Logics staff cannot access your data. Click below to generate a temporary 24-hour access window for our support team to troubleshoot your workspace.</p>
          
          <button 
            onClick={grantSupportAccess} 
            disabled={granted || loading}
            className={`w-full py-2 px-4 rounded-lg font-semibold transition-colors ${granted ? 'bg-green-500/20 text-green-400 border border-green-500/50' : 'bg-blue-600 hover:bg-blue-700 text-white'}`}
          >
            {loading ? 'Processing...' : granted ? 'Access Granted (Expires in 24h)' : 'Grant 24h Support Access'}
          </button>
        </div>
      </div>
    </div>
  );
}
