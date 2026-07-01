import { apiFetch } from "../lib/apiClient";
import { useState, useEffect } from "react";
import { 
  ShieldAlert, 
  Building, 
  Calendar, 
  Lock, 
  Bell, 
  Satellite, 
  Loader2, 
  AlertCircle, 
  CheckCircle,
  Plus
} from "lucide-react";

interface TenantData {
  id: number;
  company_name: string;
  contact_email: string;
  logo: string;
  timezone: string;
  locale: string;
  enforce_2fa: number;
  notification_prefs: any;
}

interface PaySchedule {
  id: number;
  name: string;
  frequency: string;
}

export function TenantSettings() {
  const [activeTab, setActiveTab] = useState("profile");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Tenant / Company profile fields
  const [companyName, setCompanyName] = useState("");
  const [contactEmail, setContactEmail] = useState("");
  const [logo, setLogo] = useState("");
  const [timezone, setTimezone] = useState("Asia/Manila");
  const [locale, setLocale] = useState("en_PH");

  // Security
  const [enforce2fa, setEnforce2fa] = useState(false);

  // Notifications
  const [notifyLeaveApprovals, setNotifyLeaveApprovals] = useState(false);
  const [notifyPayslipRelease, setNotifyPayslipRelease] = useState(false);
  const [notifyExpenses, setNotifyExpenses] = useState(false);

  // Pay Schedules
  const [schedules, setSchedules] = useState<PaySchedule[]>([]);
  const [showAddScheduleModal, setShowAddScheduleModal] = useState(false);
  const [newScheduleName, setNewScheduleName] = useState("");
  const [newScheduleFreq, setNewScheduleFreq] = useState("Semi-Monthly");

  // Actions states
  const [saving, setSaving] = useState(false);
  const [saveSuccess, setSaveSuccess] = useState<string | null>(null);
  const [saveError, setSaveError] = useState<string | null>(null);

  // Support access states
  const [supportGranted, setSupportGranted] = useState(false);
  const [supportLoading, setSupportLoading] = useState(false);

  useEffect(() => {
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    setLoading(true);
    setError(null);
    try {
      // 1. Get settings
      const settingsRes = await apiFetch("/api/index.php?route=iam&action=tenant_settings");
      if (!settingsRes.ok) throw new Error("Could not load workspace settings.");
      const settingsData = await settingsRes.json();
      
      if (settingsData.success && settingsData.data) {
        const t: TenantData = settingsData.data;
        setCompanyName(t.company_name || "");
        setContactEmail(t.contact_email || "");
        setLogo(t.logo || "");
        setTimezone(t.timezone || "Asia/Manila");
        setLocale(t.locale || "en_PH");
        setEnforce2fa(t.enforce_2fa === 1);
        
        // Notifications prefs parse
        const prefs = t.notification_prefs || {};
        setNotifyLeaveApprovals(!!prefs.leave_approvals);
        setNotifyPayslipRelease(!!prefs.payslip_release);
        setNotifyExpenses(!!prefs.expenses);
      } else {
        throw new Error(settingsData.error || "Failed to load settings.");
      }

      // 2. Get pay schedules
      const schedRes = await apiFetch("/api/index.php?route=payroll_engine&action=schedules");
      if (schedRes.ok) {
        const schedData = await schedRes.json();
        if (schedData.success) {
          setSchedules(schedData.data || []);
        }
      }
    } catch (err: any) {
      console.error(err);
      setError(err.message || "Connection timeout while fetching tenant setup.");
    } finally {
      setLoading(false);
    }
  };

  const handleSaveSettings = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setSaveSuccess(null);
    setSaveError(null);

    const payload: any = {};
    if (activeTab === "profile") {
      payload.company_name = companyName;
      payload.contact_email = contactEmail;
      payload.logo = logo;
      payload.timezone = timezone;
      payload.locale = locale;
    } else if (activeTab === "security") {
      payload.enforce_2fa = enforce2fa ? 1 : 0;
    } else if (activeTab === "notifications") {
      payload.notification_prefs = {
        leave_approvals: notifyLeaveApprovals,
        payslip_release: notifyPayslipRelease,
        expenses: notifyExpenses
      };
    }

    try {
      const res = await apiFetch("/api/index.php?route=iam&action=update_tenant_settings", {
        method: "POST",
        body: JSON.stringify(payload)
      });
      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data = await res.json();
      if (data.success) {
        setSaveSuccess("Workspace configuration updated successfully.");
        setTimeout(() => setSaveSuccess(null), 4000);
      } else {
        setSaveError(data.error || "Failed to save configuration.");
      }
    } catch (err: any) {
      console.error(err);
      setSaveError(err.message || "Connection error. Adjustments not saved.");
    } finally {
      setSaving(false);
    }
  };

  const handleCreateSchedule = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setSaveError(null);
    try {
      const res = await apiFetch("/api/index.php?route=payroll_engine&action=create_schedule", {
        method: "POST",
        body: JSON.stringify({
          name: newScheduleName,
          frequency: newScheduleFreq
        })
      });
      if (!res.ok) throw new Error(`HTTP error ${res.status}`);
      const data = await res.json();
      if (data.success) {
        setNewScheduleName("");
        setShowAddScheduleModal(false);
        // Refresh schedules
        const schedRes = await apiFetch("/api/index.php?route=payroll_engine&action=schedules");
        if (schedRes.ok) {
          const schedData = await schedRes.json();
          if (schedData.success) {
            setSchedules(schedData.data || []);
          }
        }
      } else {
        setSaveError(data.error || "Failed to create pay schedule.");
      }
    } catch (err: any) {
      console.error(err);
      setSaveError(err.message || "Failed to add schedule.");
    } finally {
      setSaving(false);
    }
  };

  const grantSupportAccess = async () => {
    setSupportLoading(true);
    try {
      const res = await apiFetch(`/api/index.php?route=iam&action=grant_support_access`, {
        method: 'POST',
      });
      const data = await res.json();
      if (data.success) {
        setSupportGranted(true);
      } else {
        alert("Failed to grant access: " + data.error);
      }
    } catch (e) {
      alert("Error granting access.");
    } finally {
      setSupportLoading(false);
    }
  };

  const tabItems = [
    { id: "profile", name: "Company Profile", icon: <Building size={16} /> },
    { id: "payroll", name: "Pay Schedules", icon: <Calendar size={16} /> },
    { id: "security", name: "Security Policy", icon: <Lock size={16} /> },
    { id: "notifications", name: "Notifications", icon: <Bell size={16} /> },
    { id: "support", name: "Support Access", icon: <Satellite size={16} /> }
  ];

  if (loading) {
    return (
      <div className="flex-1 flex flex-col items-center justify-center bg-[#06070a] text-gray-400">
        <Loader2 className="w-8 h-8 animate-spin text-[#00e07a]" />
        <p className="text-sm font-medium mt-3">Loading tenant configuration...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex-1 flex flex-col items-center justify-center bg-[#06070a] p-8">
        <div className="flex flex-col items-center justify-center py-16 px-6 bg-red-500/10 border border-red-500/20 rounded-xl max-w-xl text-center space-y-3">
          <AlertCircle className="w-10 h-10 text-red-500" />
          <h3 className="text-lg font-bold text-white">Initialization Failed</h3>
          <p className="text-sm text-gray-400">{error}</p>
          <button 
            onClick={fetchSettings}
            className="mt-2 px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-xs transition-colors border border-white/10"
          >
            Retry Connection
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex-1 flex flex-col h-full overflow-hidden bg-[#06070a] text-[#c8d0e0]">
      {/* Header */}
      <div className="flex-none px-8 py-6 border-b border-white/5 bg-[#161922]/50 backdrop-blur-md">
        <div>
          <h1 className="text-2xl font-bold text-white mb-1 font-['Space_Grotesk']">
            Tenant Settings
          </h1>
          <p className="text-sm text-gray-400">Manage workspace-wide configuration and compliance parameters</p>
        </div>
      </div>

      {/* Main Layout: Tabs + Form Content */}
      <div className="flex-1 flex overflow-hidden">
        {/* Vertical Tabs Sidebar */}
        <div className="w-64 border-r border-white/5 bg-[#0f121d]/40 p-4 space-y-1">
          {tabItems.map((tab) => (
            <button
              key={tab.id}
              onClick={() => {
                setActiveTab(tab.id);
                setSaveSuccess(null);
                setSaveError(null);
              }}
              className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-semibold transition-colors ${
                activeTab === tab.id 
                  ? "bg-[#00e07a]/10 text-[#00e07a] border border-[#00e07a]/20" 
                  : "text-gray-400 hover:text-white hover:bg-white/[0.02]"
              }`}
            >
              {tab.icon}
              {tab.name}
            </button>
          ))}
        </div>

        {/* Tab Content Form */}
        <div className="flex-1 overflow-auto p-8 max-w-3xl">
          
          {/* Notifications Success/Error */}
          {saveSuccess && (
            <div className="mb-6 p-4 bg-[#00e07a]/10 border border-[#00e07a]/20 rounded-xl text-[#00e07a] text-sm flex items-start gap-3">
              <CheckCircle className="w-5 h-5 flex-shrink-0" />
              <span>{saveSuccess}</span>
            </div>
          )}
          {saveError && (
            <div className="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm flex items-start gap-3">
              <AlertCircle className="w-5 h-5 flex-shrink-0" />
              <span>{saveError}</span>
            </div>
          )}

          {/* PROFILE TAB */}
          {activeTab === "profile" && (
            <form onSubmit={handleSaveSettings} className="space-y-6">
              <div className="border-b border-white/5 pb-2">
                <h2 className="text-lg font-bold text-white font-['Space_Grotesk']">Company Profile</h2>
                <p className="text-xs text-gray-500 mt-1">Configure company name, branding, timezones, and regional formats.</p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Company Name</label>
                  <input 
                    type="text" 
                    required
                    value={companyName}
                    onChange={(e) => setCompanyName(e.target.value)}
                    className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Contact Email</label>
                  <input 
                    type="email" 
                    required
                    value={contactEmail}
                    onChange={(e) => setContactEmail(e.target.value)}
                    className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                  />
                </div>
                <div className="md:col-span-2">
                  <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Logo URL</label>
                  <input 
                    type="text" 
                    value={logo}
                    onChange={(e) => setLogo(e.target.value)}
                    className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                    placeholder="https://example.com/logo.png"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Timezone</label>
                  <select 
                    value={timezone}
                    onChange={(e) => setTimezone(e.target.value)}
                    className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50"
                  >
                    <option value="Asia/Manila">Asia/Manila (PHT, GMT+8)</option>
                    <option value="Asia/Singapore">Asia/Singapore (SGT, GMT+8)</option>
                    <option value="UTC">Coordinated Universal Time (UTC)</option>
                    <option value="America/New_York">America/New_York (EST/EDT)</option>
                    <option value="Europe/London">Europe/London (GMT/BST)</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Locale / Formats</label>
                  <select 
                    value={locale}
                    onChange={(e) => setLocale(e.target.value)}
                    className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2.5 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50"
                  >
                    <option value="en_PH">en_PH (Philippines)</option>
                    <option value="en_US">en_US (United States)</option>
                    <option value="en_GB">en_GB (United Kingdom)</option>
                  </select>
                </div>
              </div>

              <div className="pt-4 flex justify-end">
                <button 
                  type="submit" 
                  disabled={saving}
                  className="px-6 py-2.5 bg-[#00e07a] hover:bg-[#00c96a] text-black font-bold rounded-lg text-sm shadow-[0_0_15px_rgba(0,224,122,0.3)] flex items-center gap-2"
                >
                  {saving && <Loader2 className="w-4 h-4 animate-spin" />}
                  Save Profile Settings
                </button>
              </div>
            </form>
          )}

          {/* PAY SCHEDULES TAB */}
          {activeTab === "payroll" && (
            <div className="space-y-6">
              <div className="border-b border-white/5 pb-2 flex justify-between items-end">
                <div>
                  <h2 className="text-lg font-bold text-white font-['Space_Grotesk']">Pay Schedules</h2>
                  <p className="text-xs text-gray-500 mt-1">Manage processing frequencies and calendar cycles.</p>
                </div>
                <button
                  onClick={() => setShowAddScheduleModal(true)}
                  className="px-3 py-1.5 bg-[#00e07a]/15 hover:bg-[#00e07a]/25 text-[#00e07a] border border-[#00e07a]/30 rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5"
                >
                  <Plus size={14} /> Add Schedule
                </button>
              </div>

              <div className="bg-[#161922]/70 border border-white/5 rounded-xl overflow-hidden shadow-2xl">
                <table className="w-full text-left border-collapse">
                  <thead className="bg-black/25 text-gray-500 text-xs font-semibold uppercase tracking-wider">
                    <tr>
                      <th className="py-4 px-6">Schedule Name</th>
                      <th className="py-4 px-6">Frequency</th>
                      <th className="py-4 px-6">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-white/[0.03]">
                    {schedules.map((sched) => (
                      <tr key={sched.id} className="hover:bg-white/[0.02] transition-colors">
                        <td className="py-4 px-6 text-sm font-semibold text-white">{sched.name}</td>
                        <td className="py-4 px-6 text-sm text-gray-300">{sched.frequency}</td>
                        <td className="py-4 px-6 text-sm text-[#00e07a]">
                          <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-[#00e07a]/10 border border-[#00e07a]/20">Active</span>
                        </td>
                      </tr>
                    ))}
                    {schedules.length === 0 && (
                      <tr>
                        <td colSpan={3} className="py-8 text-center text-gray-500 text-sm">No pay schedules configured.</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* SECURITY POLICY TAB */}
          {activeTab === "security" && (
            <form onSubmit={handleSaveSettings} className="space-y-6">
              <div className="border-b border-white/5 pb-2">
                <h2 className="text-lg font-bold text-white font-['Space_Grotesk']">Security & Authentication</h2>
                <p className="text-xs text-gray-500 mt-1">Configure identity validation and MFA enforcement metrics.</p>
              </div>

              <div className="bg-[#161922]/40 border border-white/5 p-6 rounded-xl flex items-start justify-between gap-4">
                <div className="space-y-1">
                  <h4 className="text-sm font-semibold text-white">Enforce Two-Factor Authentication (2FA)</h4>
                  <p className="text-xs text-gray-400">When enabled, all users inside your tenant must enroll and verify credentials via TOTP authentication before entering the workspace.</p>
                </div>
                <label className="relative inline-flex items-center cursor-pointer mt-1">
                  <input 
                    type="checkbox" 
                    checked={enforce2fa}
                    onChange={(e) => setEnforce2fa(e.target.checked)}
                    className="sr-only peer" 
                  />
                  <div className="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-gray-400 after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#00e07a] peer-checked:after:bg-black peer-checked:after:border-none"></div>
                </label>
              </div>

              <div className="pt-4 flex justify-end">
                <button 
                  type="submit" 
                  disabled={saving}
                  className="px-6 py-2.5 bg-[#00e07a] hover:bg-[#00c96a] text-black font-bold rounded-lg text-sm shadow-[0_0_15px_rgba(0,224,122,0.3)] flex items-center gap-2"
                >
                  {saving && <Loader2 className="w-4 h-4 animate-spin" />}
                  Save Security Policies
                </button>
              </div>
            </form>
          )}

          {/* NOTIFICATIONS TAB */}
          {activeTab === "notifications" && (
            <form onSubmit={handleSaveSettings} className="space-y-6">
              <div className="border-b border-white/5 pb-2">
                <h2 className="text-lg font-bold text-white font-['Space_Grotesk']">Notifications & Alerts</h2>
                <p className="text-xs text-gray-500 mt-1">Configure subscription events and automated dashboard updates.</p>
              </div>

              <div className="space-y-4">
                <label className="flex items-start gap-3 p-4 bg-[#161922]/30 border border-white/5 rounded-xl cursor-pointer hover:bg-white/[0.01] transition-colors">
                  <input 
                    type="checkbox" 
                    checked={notifyLeaveApprovals}
                    onChange={(e) => setNotifyLeaveApprovals(e.target.checked)}
                    className="mt-1 accent-[#00e07a]" 
                  />
                  <div>
                    <span className="block text-sm font-semibold text-white">Leave Approvals</span>
                    <span className="block text-xs text-gray-400">Trigger alerts when supervisor/manager leaves requires attention or are resolved.</span>
                  </div>
                </label>

                <label className="flex items-start gap-3 p-4 bg-[#161922]/30 border border-white/5 rounded-xl cursor-pointer hover:bg-white/[0.01] transition-colors">
                  <input 
                    type="checkbox" 
                    checked={notifyPayslipRelease}
                    onChange={(e) => setNotifyPayslipRelease(e.target.checked)}
                    className="mt-1 accent-[#00e07a]" 
                  />
                  <div>
                    <span className="block text-sm font-semibold text-white">Payslip Releases</span>
                    <span className="block text-xs text-gray-400">Trigger system messages immediately when a payroll cycle locks and payslips release.</span>
                  </div>
                </label>

                <label className="flex items-start gap-3 p-4 bg-[#161922]/30 border border-white/5 rounded-xl cursor-pointer hover:bg-white/[0.01] transition-colors">
                  <input 
                    type="checkbox" 
                    checked={notifyExpenses}
                    onChange={(e) => setNotifyExpenses(e.target.checked)}
                    className="mt-1 accent-[#00e07a]" 
                  />
                  <div>
                    <span className="block text-sm font-semibold text-white">Expenses & Claims updates</span>
                    <span className="block text-xs text-gray-400">Dispatch system logs when expense reimbursement claims transition in states.</span>
                  </div>
                </label>
              </div>

              <div className="pt-4 flex justify-end">
                <button 
                  type="submit" 
                  disabled={saving}
                  className="px-6 py-2.5 bg-[#00e07a] hover:bg-[#00c96a] text-black font-bold rounded-lg text-sm shadow-[0_0_15px_rgba(0,224,122,0.3)] flex items-center gap-2"
                >
                  {saving && <Loader2 className="w-4 h-4 animate-spin" />}
                  Save Notifications Preferences
                </button>
              </div>
            </form>
          )}

          {/* SUPPORT ACCESS TAB */}
          {activeTab === "support" && (
            <div className="space-y-6">
              <div className="border-b border-white/5 pb-2">
                <h2 className="text-lg font-bold text-white font-['Space_Grotesk']">Platform Support Access</h2>
                <p className="text-xs text-gray-500 mt-1">Manage external support debugging permissions.</p>
              </div>

              <div className="bg-[#141929] p-8 rounded-2xl border border-white/5 text-center space-y-6">
                <div className="w-16 h-16 bg-blue-500/10 rounded-2xl border border-blue-500/20 flex items-center justify-center mx-auto shadow-[0_0_20px_rgba(59,130,246,0.15)]">
                  <ShieldAlert className="w-8 h-8 text-blue-400" />
                </div>
                
                <div className="max-w-md mx-auto">
                  <h3 className="text-lg font-semibold text-white mb-2">Platform Support Operations</h3>
                  <p className="text-xs text-gray-400 leading-relaxed mb-6">
                    By default, Respawn Logics staff cannot access your workspace data. Enable a temporary 24-hour access window for our customer support engineers to access your workspace and troubleshoot configuration issues.
                  </p>
                  
                  <button 
                    onClick={grantSupportAccess} 
                    disabled={supportGranted || supportLoading}
                    className={`w-full py-2.5 px-4 rounded-lg font-semibold transition-colors ${
                      supportGranted 
                        ? 'bg-green-500/20 text-green-400 border border-green-500/50' 
                        : 'bg-blue-600 hover:bg-blue-700 text-white'
                    }`}
                  >
                    {supportLoading ? 'Processing...' : supportGranted ? 'Access Granted (Expires in 24h)' : 'Grant 24h Support Access'}
                  </button>
                </div>
              </div>
            </div>
          )}

        </div>
      </div>

      {/* Add Pay Schedule Modal */}
      {showAddScheduleModal && (
        <div className="fixed inset-0 bg-black/75 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <div className="bg-[#161922] border border-white/10 rounded-xl w-full max-w-sm shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="p-5 border-b border-white/5 flex justify-between items-center bg-black/10">
              <h3 className="text-sm font-bold text-white uppercase tracking-wider">Add Pay Schedule</h3>
              <button 
                onClick={() => setShowAddScheduleModal(false)} 
                className="text-gray-400 hover:text-white text-xl leading-none"
              >
                &times;
              </button>
            </div>
            
            <form onSubmit={handleCreateSchedule} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Schedule Name</label>
                <input 
                  type="text" 
                  required
                  value={newScheduleName}
                  onChange={(e) => setNewScheduleName(e.target.value)}
                  className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50" 
                  placeholder="e.g. Monthly Standard"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Frequency</label>
                <select 
                  value={newScheduleFreq}
                  onChange={(e) => setNewScheduleFreq(e.target.value)}
                  className="w-full bg-[#0b0f1a] border border-white/10 rounded-lg py-2 px-3 text-white text-sm focus:outline-none focus:border-[#00e07a]/50"
                >
                  <option value="Monthly">Monthly</option>
                  <option value="Semi-Monthly">Semi-Monthly</option>
                  <option value="Weekly">Weekly</option>
                  <option value="Bi-Weekly">Bi-Weekly</option>
                </select>
              </div>

              <div className="pt-2 flex justify-end gap-3">
                <button 
                  type="button" 
                  onClick={() => setShowAddScheduleModal(false)} 
                  className="px-3 py-1.5 text-gray-400 hover:text-white text-xs font-semibold"
                >
                  Cancel
                </button>
                <button 
                  type="submit" 
                  disabled={saving}
                  className="px-4 py-2 bg-[#00e07a] hover:bg-[#00c96a] text-black font-bold rounded-lg text-xs transition-colors"
                >
                  Create Schedule
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
