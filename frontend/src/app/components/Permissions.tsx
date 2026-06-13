import { useState } from "react";
import { Shield, User, UserPlus, Search } from "lucide-react";
import type { ViewState } from "./Sidebar";

export function Permissions({ onViewChange }: { onViewChange: (v: ViewState) => void }) {
  const roles = [
    { name: "Admin", desc: "Full access to all platform settings, billing, and data" },
    { name: "Recruiter", desc: "Can manage jobs, candidates, and pipelines" },
    { name: "Hiring Manager", desc: "Can view assigned jobs and candidates, submit feedback" },
    { name: "Interviewer", desc: "Can only view assigned interviews and submit scorecards" },
    { name: "Viewer", desc: "Read-only access to non-sensitive data" }
  ];

  return (
    <div className="flex-1 flex flex-col h-full bg-[#0d0f19] text-white p-8 relative overflow-hidden font-sans">
      <div className="mb-8 relative z-10 flex justify-between items-start">
        <div>
          <h1 className="text-2xl font-bold font-outfit tracking-tight text-white mb-2">Users & Permissions</h1>
          <p className="text-sm text-gray-400">Manage access control and user roles for the ATS module</p>
        </div>
        <button className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-gradient-to-r from-[#8b5cf6] to-[#ec4899] hover:opacity-90 text-white shadow-lg shadow-purple-500/20">
          <UserPlus size={16} />
          Invite User
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 relative z-10 h-full overflow-hidden">
        <div className="col-span-1 border border-white/5 bg-[#161922] rounded-2xl overflow-y-auto p-6 scrollbar-thin">
          <h2 className="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4 flex items-center gap-2">
            <Shield size={16} /> Roles
          </h2>
          <div className="space-y-3">
            {roles.map(r => (
              <div key={r.name} className="p-4 rounded-xl border border-white/10 bg-[#1a1d27] hover:border-[#8b5cf6]/50 cursor-pointer transition-colors group">
                <h3 className="text-sm font-bold text-white group-hover:text-[#c084fc] transition-colors">{r.name}</h3>
                <p className="text-[10px] text-gray-500 mt-1 leading-relaxed">{r.desc}</p>
              </div>
            ))}
          </div>
        </div>
        
        <div className="col-span-2 border border-white/5 bg-[#161922]/50 rounded-2xl p-6 flex flex-col overflow-hidden">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-sm font-bold text-white">Users in System</h2>
            <div className="relative">
              <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
              <input type="text" placeholder="Search users..." className="bg-[#1a1d27] border border-white/5 rounded-xl pl-9 pr-3 py-1.5 text-xs outline-none focus:border-[#8b5cf6]/50 w-64" />
            </div>
          </div>
          
          <div className="flex-1 flex flex-col items-center justify-center text-gray-500 text-center">
            <User size={48} className="mb-4 opacity-20" />
            <p className="text-sm font-medium">User directory sync active.</p>
            <p className="text-xs mt-1 max-w-sm">Users are managed via the Core HRIS module. Changes here affect only ATS permissions.</p>
          </div>
        </div>
      </div>
    </div>
  );
}
