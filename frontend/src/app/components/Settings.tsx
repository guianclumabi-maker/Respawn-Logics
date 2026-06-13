import { useState } from "react";
import { Settings as SettingsIcon, Building, Bell, Link as LinkIcon, ShieldAlert } from "lucide-react";
import type { ViewState } from "./Sidebar";

export function Settings({ onViewChange }: { onViewChange: (v: ViewState) => void }) {
  const [activeTab, setActiveTab] = useState("general");

  return (
    <div className="flex-1 flex flex-col h-full bg-[#0d0f19] text-white p-8 relative overflow-hidden font-sans">
      <div className="mb-8 relative z-10">
        <h1 className="text-2xl font-bold font-outfit tracking-tight text-white mb-2">Company Settings</h1>
        <p className="text-sm text-gray-400">Manage your ATS configuration and preferences</p>
      </div>

      <div className="flex items-center gap-2 border-b border-white/10 mb-6 relative z-10">
        {[
          { id: "general", label: "General", icon: Building },
          { id: "notifications", label: "Notifications", icon: Bell },
          { id: "integrations", label: "Integrations", icon: LinkIcon },
          { id: "security", label: "Security", icon: ShieldAlert }
        ].map(tab => {
          const Icon = tab.icon;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`flex items-center gap-2 px-4 py-3 text-sm font-semibold transition-colors border-b-2 relative -bottom-px ${
                activeTab === tab.id
                  ? "text-[#c084fc] border-[#8b5cf6]"
                  : "text-gray-400 border-transparent hover:text-white"
              }`}
            >
              <Icon size={16} />
              {tab.label}
            </button>
          );
        })}
      </div>

      <div className="flex-1 border border-white/5 bg-[#161922]/50 rounded-2xl overflow-hidden relative z-10 flex flex-col items-center justify-center">
        <SettingsIcon size={48} className="mb-4 text-gray-600 opacity-50" />
        <h2 className="text-lg font-bold text-gray-300">Settings Configuration Active</h2>
        <p className="text-sm text-gray-500 mt-2 max-w-sm text-center">Platform configurations are synced globally via Respawn Logic Core HRIS.</p>
      </div>
    </div>
  );
}
