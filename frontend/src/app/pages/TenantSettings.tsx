import { useState } from "react";
import { Building2, Save, Globe, Paintbrush, Bell } from "lucide-react";

export function TenantSettings() {
  const [loading, setLoading] = useState(false);
  const [successMsg, setSuccessMsg] = useState("");
  
  const [form, setForm] = useState({
    companyName: "Respawn Logics",
    domain: "respawn.logics",
    timezone: "UTC",
    themeColor: "#3b82f6",
    emailNotifications: true
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value, type } = e.target;
    const val = type === "checkbox" ? (e.target as HTMLInputElement).checked : value;
    setForm(prev => ({ ...prev, [name]: val }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    // Mock save
    setTimeout(() => {
      setLoading(false);
      setSuccessMsg("Settings saved successfully.");
      setTimeout(() => setSuccessMsg(""), 3000);
    }, 800);
  };

  return (
    <div className="h-full w-full flex flex-col p-8 overflow-y-auto" style={{ backgroundColor: "#f9fafb" }}>
      <header className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900 tracking-tight mb-2">Tenant Settings</h1>
        <p className="text-gray-500">Manage global preferences, branding, and organization details.</p>
      </header>

      {successMsg && (
        <div className="bg-emerald-100 text-emerald-800 p-4 rounded-lg mb-6 shadow-sm border border-emerald-200 font-medium">
          {successMsg}
        </div>
      )}

      <form onSubmit={handleSubmit} className="max-w-3xl space-y-6">
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <div className="p-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
            <Building2 className="w-5 h-5 text-gray-500" />
            <h2 className="font-semibold text-gray-800">Organization Details</h2>
          </div>
          <div className="p-6 space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                <input 
                  type="text" name="companyName" value={form.companyName} onChange={handleChange}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Primary Domain</label>
                <div className="flex">
                  <span className="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                    <Globe className="w-4 h-4" />
                  </span>
                  <input 
                    type="text" name="domain" value={form.domain} onChange={handleChange}
                    className="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-r-md border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Default Timezone</label>
              <select 
                name="timezone" value={form.timezone} onChange={handleChange}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="UTC">UTC</option>
                <option value="America/New_York">Eastern Time (US & Canada)</option>
                <option value="America/Los_Angeles">Pacific Time (US & Canada)</option>
                <option value="Asia/Manila">Philippine Time</option>
              </select>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <div className="p-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
            <Paintbrush className="w-5 h-5 text-gray-500" />
            <h2 className="font-semibold text-gray-800">Branding</h2>
          </div>
          <div className="p-6">
            <label className="block text-sm font-medium text-gray-700 mb-2">Primary Color</label>
            <div className="flex items-center gap-3">
              <input 
                type="color" name="themeColor" value={form.themeColor} onChange={handleChange}
                className="h-10 w-10 border-0 p-0 rounded cursor-pointer"
              />
              <input 
                type="text" value={form.themeColor} readOnly
                className="w-24 rounded-md border border-gray-300 px-3 py-2 text-sm bg-gray-50 text-gray-500"
              />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <div className="p-4 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
            <Bell className="w-5 h-5 text-gray-500" />
            <h2 className="font-semibold text-gray-800">Preferences</h2>
          </div>
          <div className="p-6">
            <label className="flex items-center gap-3 cursor-pointer">
              <input 
                type="checkbox" name="emailNotifications" checked={form.emailNotifications} onChange={handleChange}
                className="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
              />
              <span className="text-sm font-medium text-gray-700">Enable system-wide email notifications</span>
            </label>
          </div>
        </div>

        <div className="flex justify-end pt-2">
          <button 
            type="submit" 
            disabled={loading}
            className="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition-colors shadow-sm flex items-center gap-2 disabled:opacity-70"
          >
            <Save className="w-4 h-4" />
            {loading ? "Saving..." : "Save Settings"}
          </button>
        </div>
      </form>
    </div>
  );
}
