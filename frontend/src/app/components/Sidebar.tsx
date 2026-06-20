import { useState, useEffect } from "react";
import { useAuth } from "../context/AuthContext";
import { GamifiedThemeToggle } from "./GamifiedThemeToggle";
import {
  LayoutDashboard,
  Briefcase,
  GitBranch,
  Calendar,
  CheckCircle,
  Users,
  Database,
  Search,
  BarChart3,
  Bot,
  Shield,
  Settings,
  ArrowLeft,
  Menu,
  Layers,
  Clock,
  Gamepad2,
} from "lucide-react";

// ── Types ──────────────────────────────────────────────────

export type ViewState = {
  view: string;
  jobId?: number;
  candidateId?: number;
  poolId?: number;
};

export type SidebarBadges = {
  actions?: number;
  urgentJobs?: number;
  todayInterviews?: number;
  pendingApprovals?: number;
  copilotAlerts?: number;
};

type SidebarProps = {
  activeView: ViewState;
  onViewChange: (view: ViewState) => void;
  badges?: SidebarBadges;
};

// ── Nav item shape ─────────────────────────────────────────

type NavEntry = {
  label: string;
  view: string;
  icon: React.ReactNode;
  badgeKey?: keyof SidebarBadges;
};

type NavSection = {
  title: string;
  items: NavEntry[];
};

// ── Navigation config ──────────────────────────────────────

// Config is now a function so we can pass useAuth checks
const getSections = (hasPermission: (p: string) => boolean): NavSection[] => [
  {
    title: "",
    items: [
      { label: "Dashboard", view: "Dashboard", icon: <LayoutDashboard size={19} />, badgeKey: "actions" },
    ],
  },
  ...(hasPermission("ats.edit_job") || hasPermission("ats.create_job") ? [{
    title: "Hiring",
    items: [
      { label: "Jobs", view: "Jobs", icon: <Briefcase size={19} />, badgeKey: "urgentJobs" },
      { label: "Pipeline", view: "Pipeline", icon: <GitBranch size={19} /> },
      { label: "Interviews", view: "Interviews", icon: <Calendar size={19} />, badgeKey: "todayInterviews" },
      { label: "Approvals", view: "Approvals", icon: <CheckCircle size={19} />, badgeKey: "pendingApprovals" },
    ],
  }] : []),
  ...(hasPermission("ats.edit") ? [{
    title: "Talent",
    items: [
      { label: "Candidates", view: "Candidates", icon: <Users size={19} /> },
      { label: "Talent Pools", view: "Talent Pools", icon: <Database size={19} /> },
      { label: "Talent Search", view: "Talent Search", icon: <Search size={19} /> },
    ],
  }] : []),
  ...(hasPermission("analytics.view") ? [{
    title: "Intelligence",
    items: [
      { label: "Analytics", view: "Analytics", icon: <BarChart3 size={19} /> },
      { label: "Recruiting Copilot", view: "Recruiting Copilot", icon: <Bot size={19} />, badgeKey: "copilotAlerts" },
    ],
  }] : []),
  // We'll hide the Admin sections from ATS since we moved them to Core Platform
];

// ── Badge component ────────────────────────────────────────

function Badge({ count }: { count: number }) {
  if (!count || count <= 0) return null;
  return (
    <span className="min-w-[18px] h-[18px] flex items-center justify-center rounded-full bg-[#00e07a]/15 text-[#00e07a] border border-[#00e07a]/30 text-[0.65rem] font-mono font-bold px-1.5 leading-none">
      {count > 99 ? "99+" : count}
    </span>
  );
}

// ── Sidebar ────────────────────────────────────────────────

export function Sidebar({ activeView, onViewChange, badges = {} }: SidebarProps) {
  const [collapsed, setCollapsed] = useState(false);
  const { user, hasPermission } = useAuth();
  const sections = getSections(hasPermission);



  const isActive = (view: string) => activeView.view === view;

  const userInitials = user
    ? user.name.split(" ").map(n => n[0]).join("").substring(0, 2).toUpperCase()
    : "AU";

  return (
    <aside
      style={{
        width: collapsed ? 72 : 260,
        fontFamily: "'Space Grotesk', sans-serif",
      }}
      className="h-full bg-white dark:bg-[#0b0f1a] flex flex-col flex-shrink-0 border-r border-gray-200 dark:border-white/[0.06] transition-all duration-300 overflow-hidden select-none"
    >
      {/* ── Brand header ──────────────────────────────── */}
      <div className="flex items-center justify-between h-[70px] px-5 border-b border-gray-200 dark:border-white/[0.06] flex-shrink-0">
        {!collapsed && (
          <div className="flex items-center gap-3">
            <div
              className="w-10 h-10 bg-gradient-to-br from-[#00e07a] to-[#00b8ff] flex items-center justify-center flex-shrink-0 shadow-lg"
              style={{ borderRadius: '10px', boxShadow: '0 8px 20px rgba(0,224,122,0.25)' }}
            >
              <i className="fa-solid fa-gamepad" style={{ color: '#000', fontSize: '20px' }}></i>
            </div>
            <div className="flex items-baseline gap-1.5">
              <span
                className="font-bold text-slate-800 dark:text-white whitespace-nowrap tracking-[-0.5px] text-[15px]"
                style={{ fontFamily: "'JetBrains Mono', monospace" }}
              >
                Respawn Logics
              </span>
              <span 
                className="font-bold text-[#00e07a] text-[9px]"
                style={{ fontFamily: "'JetBrains Mono', monospace" }}
              >
                v2.0
              </span>
            </div>
          </div>
        )}
        {collapsed && (
          <div
            className="w-10 h-10 bg-gradient-to-br from-[#00e07a] to-[#00b8ff] flex items-center justify-center flex-shrink-0 mx-auto"
            style={{ borderRadius: '10px', boxShadow: '0 8px 20px rgba(0,224,122,0.25)' }}
          >
            <i className="fa-solid fa-gamepad" style={{ color: '#000', fontSize: '20px' }}></i>
          </div>
        )}
        <button
          onClick={() => setCollapsed((c) => !c)}
          className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-white/5 transition-colors text-gray-500 hover:text-slate-800 dark:hover:text-white cursor-pointer ml-auto"
          title={collapsed ? "Expand sidebar" : "Collapse sidebar"}
        >
          {collapsed ? <Menu size={16} /> : <Layers size={16} />}
        </button>
      </div>

      {/* ── Navigation ────────────────────────────────── */}
      <div className="flex-1 px-3 overflow-y-auto py-5 space-y-5 scrollbar-thin">
        {sections.map((section) => (
          <div key={section.title || "_top"}>
            {/* Section title */}
            {section.title && !collapsed && (
              <p
                className="pl-3 text-[10px] font-bold text-gray-500 dark:text-[#5e6a82] tracking-[1.5px] uppercase mb-2"
              >
                {section.title}
              </p>
            )}
            {section.title && collapsed && (
              <div className="mx-auto w-6 border-t border-gray-200 dark:border-white/[0.06] mb-3 mt-1" />
            )}

            <nav className="space-y-1">
              {section.items.map((item) => {
                const active = isActive(item.view);
                const badgeCount = item.badgeKey ? badges[item.badgeKey] : undefined;

                return (
                  <button
                    key={item.view}
                    onClick={() => onViewChange({ view: item.view })}
                    title={collapsed ? item.label : undefined}
                    className={`w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg border transition-all text-left group cursor-pointer ${
                      active
                        ? "bg-[#00e07a]/10 border-[#00e07a]/20 shadow-[0_0_12px_rgba(0,224,122,0.08)]"
                        : "hover:bg-gray-100 dark:hover:bg-white/[0.03] border-transparent"
                    }`}
                  >
                    <span
                      className={`flex-shrink-0 transition-colors ${
                        active ? "text-[#00e07a]" : "text-slate-500 dark:text-[#8b95a8] group-hover:text-slate-800 dark:group-hover:text-white"
                      }`}
                    >
                      {item.icon}
                    </span>

                    {!collapsed && (
                      <>
                        <span
                          className={`text-[0.85rem] font-medium flex-1 truncate ${
                            active ? "text-[#00e07a]" : "text-slate-600 dark:text-[#8b95a8] group-hover:text-slate-800 dark:group-hover:text-white"
                          }`}
                        >
                          {item.label}
                        </span>
                        {badgeCount !== undefined && <Badge count={badgeCount} />}
                      </>
                    )}
                  </button>
                );
              })}
            </nav>
          </div>
        ))}

        {/* ── Return to Core HRIS ─────────────────────── */}
        <div className="px-1">
          {!collapsed && (
            <div className="mx-3 border-t border-gray-200 dark:border-white/[0.04] mb-3" />
          )}
          <button
            onClick={() => { window.location.href = `${import.meta.env.VITE_API_BASE_URL}/pages/dashboard.php`; }}
            className="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg border border-transparent hover:bg-gray-100 dark:hover:bg-white/[0.03] text-slate-500 dark:text-[#8b95a8] hover:text-slate-800 dark:hover:text-white transition-all group cursor-pointer font-mono text-xs uppercase tracking-wider"
            title={collapsed ? "Return to Core HRIS" : undefined}
          >
            <span className="flex-shrink-0 text-slate-500 dark:text-[#8b95a8] group-hover:text-[#00e07a] transition-colors">
              <ArrowLeft size={15} />
            </span>
            {!collapsed && (
              <span className="flex-1 truncate text-left">
                [ return_core ]
              </span>
            )}
          </button>
        </div>
      </div>

      {/* ── Gamified Theme Toggle ─────────────────────── */}
      {!collapsed && (
        <div className="px-4 pb-2 border-t border-gray-200 dark:border-white/[0.04] pt-4 flex-shrink-0">
          <GamifiedThemeToggle />
        </div>
      )}

      {/* ── User footer ───────────────────────────────── */}
      {!collapsed && (
        <div className="p-4 border-t border-gray-200 dark:border-white/[0.04] flex-shrink-0">
          <div
            onClick={() => { window.location.href = `${import.meta.env.VITE_API_BASE_URL}/pages/profile.php`; }}
            className="flex items-center gap-3 p-2.5 border border-transparent rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-white/[0.04] transition-all"
          >
            {user?.profile_image ? (
              <div className="w-10 h-10 rounded-full overflow-hidden flex-shrink-0 border border-gray-200 dark:border-[#00e07a]/20">
                <img src={`${import.meta.env.VITE_API_BASE_URL}/uploads/${user.profile_image}`} alt="Profile" className="w-full h-full object-cover" />
              </div>
            ) : (
              <div className="w-10 h-10 rounded-full flex-shrink-0 bg-gray-100 dark:bg-[#0f1422] border border-gray-200 dark:border-[#00e07a]/20 flex items-center justify-center">
                <span className="text-slate-600 dark:text-[#00e07a] font-bold text-[0.95rem]">
                  {userInitials}
                </span>
              </div>
            )}
            <div className="min-w-0 flex-1">
              <div className="text-[0.85rem] font-semibold text-slate-800 dark:text-white truncate">
                {user ? user.name : "Admin User"}
              </div>
              <div className="text-[0.75rem] font-mono text-gray-500 uppercase tracking-wider truncate">
                {user?.job_title || (user && user.roles.length > 0 ? user.roles.join(', ') : "Employee")}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Collapsed user avatar */}
      {collapsed && (
        <div className="p-3 border-t border-gray-200 dark:border-white/[0.04] flex-shrink-0 flex justify-center">
          <div
            onClick={() => { window.location.href = `${import.meta.env.VITE_API_BASE_URL}/pages/profile.php`; }}
            className="w-10 h-10 rounded-full flex-shrink-0 border border-gray-200 dark:border-[#00e07a]/20 bg-gray-100 dark:bg-[#0f1422] flex items-center justify-center cursor-pointer hover:bg-gray-200 dark:hover:bg-white/[0.06] transition-all overflow-hidden font-mono text-xs"
            title={user ? user.name : "Admin User"}
          >
            {user?.profile_image ? (
              <img src={`${import.meta.env.VITE_API_BASE_URL}/uploads/${user.profile_image}`} alt="Profile" className="w-full h-full object-cover" />
            ) : (
              <span className="text-slate-600 dark:text-[#00e07a] font-bold text-[0.95rem]">
                {userInitials}
              </span>
            )}
          </div>
        </div>
      )}
    </aside>
  );
}
