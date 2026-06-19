import { useState, useEffect } from "react";
import {
  LayoutDashboard,
  Briefcase,
  Users,
  BarChart2,
  Building2,
  Settings,
  ChevronDown,
  ChevronRight,
  Menu,
  Layers,
  ArrowLeft,
  BotMessageSquare
} from "lucide-react";
import { GamifiedThemeToggle } from "./GamifiedThemeToggle";

type NavItem = {
  label: string;
  icon: React.ReactNode;
  viewName?: string;
  children?: string[];
  hasChevron?: boolean;
};

const navItems: NavItem[] = [
  { label: "Dashboard", viewName: "Dashboard", icon: <LayoutDashboard size={20} /> },
  { label: "AI Companion", viewName: "AICompanion", icon: <BotMessageSquare size={20} className="text-cyan-400" /> },
  
  // CASES
  {
    label: "CASES",
    icon: <Users size={20} />,
    children: ["Cases List", "Incident Reports", "Investigations"],
    hasChevron: true,
  },
  
  // WORKFLOW
  {
    label: "WORKFLOW",
    icon: <Briefcase size={20} />,
    children: ["Tasks", "Approvals"],
    hasChevron: true,
  },
  
  // REPORTS
  {
    label: "REPORTS",
    icon: <BarChart2 size={20} />,
    children: ["Daily Reports", "Monthly Reports", "Analytics"],
    hasChevron: true,
  },
  
  // ADMIN
  {
    label: "ADMIN",
    icon: <Settings size={20} />,
    children: ["Case Types", "Settings"],
    hasChevron: true,
  },
];

const basePath = window.location.hostname === 'localhost' ? '/respawn-logics' : '';

const bottomItems = [
  { label: "Company Directory", icon: <Building2 size={20} />, path: `${basePath}/pages/org-chart.php` },
  { label: "My Profile", icon: <Settings size={20} />, path: `${basePath}/pages/profile.php` },
  { label: "Return to Workspace", icon: <ArrowLeft size={20} />, path: `${basePath}/pages/dashboard.php`, highlight: true },
];

type SidebarProps = {
  activeView: string;
  onViewChange: (view: any) => void;
};

export function Sidebar({ activeView, onViewChange }: SidebarProps) {
  const [collapsed, setCollapsed] = useState(false);
  const [expanded, setExpanded] = useState<string>("Cases");
  const [sessionUser, setSessionUser] = useState<{ full_name: string; role: string; initials: string; department?: string; profile_image?: string } | null>(null);

  useEffect(() => {
    fetch(`${basePath}/api/index.php?route=candidates&action=current_user`)
      .then((res) => res.json())
      .then((data) => {
        if (data.success && data.user) {
          const names = data.user.full_name.split(" ");
          const initials = names.map((n: string) => n[0]).join("").substring(0, 2).toUpperCase();
          
          let roleDesc = data.user.role ? data.user.role.charAt(0).toUpperCase() + data.user.role.slice(1) : "Employee";
          if (data.user.department) {
            roleDesc += ` (${data.user.department})`;
          }
          
          setSessionUser({
            full_name: data.user.full_name,
            role: roleDesc,
            department: data.user.department,
            initials: initials,
            profile_image: data.user.profile_image,
          });
        }
      })
      .catch(() => {});
  }, []);

  const toggle = (label: string) =>
    setExpanded((prev) => (prev === label ? "" : label));

  return (
    <aside
      style={{
        width: collapsed ? 72 : 280,
      }}
      className="h-full bg-white dark:bg-[#0b0f1a] border-r border-gray-200 dark:border-white/[0.06] flex flex-col flex-shrink-0 transition-all duration-300 overflow-hidden font-sans select-none"
    >
      {/* Brand Logo Header */}
      <div className="flex items-center justify-between h-[70px] px-6 border-b border-gray-200 dark:border-white/[0.04] flex-shrink-0">
        {!collapsed && (
          <div className="flex items-center gap-3">
            {/* Gamepad logo icon */}
            <div className="w-8 h-8 rounded-[7px] bg-gradient-to-br from-[#00e07a] to-[#00b8ff] flex items-center justify-center text-black text-base shadow-lg shadow-[#00e07a]/20 flex-shrink-0">
              <i className="fa-solid fa-gamepad"></i>
            </div>
            <span
              className="font-bold text-slate-800 dark:text-white text-[15px] tracking-[-0.5px]"
              style={{ fontFamily: "'JetBrains Mono', monospace" }}
            >
              Employee Relations
            </span>
          </div>
        )}
        
        {/* Toggle icon */}
        <button
          onClick={() => setCollapsed((c) => !c)}
          className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-white/5 transition-colors text-gray-500 dark:text-gray-400 hover:text-slate-800 dark:hover:text-white cursor-pointer ml-auto"
        >
          {collapsed ? <Menu size={16} /> : <Layers size={16} />}
        </button>
      </div>

      {/* Main navigation content */}
      <div className="flex-1 px-4 overflow-y-auto space-y-6 py-6 scrollbar-thin">
        {/* Manage section */}
        <div>
          {!collapsed && (
            <p className="pl-3 text-[0.75rem] font-bold text-gray-500 tracking-[1px] uppercase mb-1.5" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
              Manage
            </p>
          )}
          <nav className="space-y-1.5">
            {navItems.map((item) => {
              const isExpanded = expanded === item.label;
              const hasChildren = !!item.children?.length;
              const isActive = activeView === item.viewName;

              return (
                <div key={item.label}>
                  <button
                    onClick={() => {
                      if (item.viewName) {
                        onViewChange(item.viewName);
                      }
                      if (hasChildren) {
                        toggle(item.label);
                      }
                    }}
                    className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all text-left group cursor-pointer ${
                      isActive
                        ? "bg-[#00e07a]/10 border border-[#00e07a]/20 shadow-[0_0_12px_rgba(0,224,122,0.08)]"
                        : "hover:bg-gray-100 dark:hover:bg-white/[0.03] border border-transparent"
                    }`}
                  >
                    <span
                      className={`transition-colors ${
                        isActive
                          ? "text-[#00e07a]"
                          : "text-slate-500 dark:text-[#9ca3af] group-hover:text-slate-800 dark:group-hover:text-white"
                      }`}
                    >
                      {item.icon}
                    </span>
                    {!collapsed && (
                      <>
                        <span className={`text-[0.9rem] font-medium flex-1 transition-colors ${
                          isActive
                            ? "text-[#00e07a]"
                            : "text-slate-600 dark:text-[#9ca3af] group-hover:text-slate-800 dark:group-hover:text-white"
                        }`}>
                          {item.label}
                        </span>
                        {item.hasChevron && (
                          <span className={isActive ? "text-[#00e07a]" : "text-gray-600 group-hover:text-slate-800 dark:group-hover:text-white"}>
                            {hasChildren && isExpanded ? (
                              <ChevronDown size={14} />
                            ) : (
                              <ChevronRight size={14} />
                            )}
                          </span>
                        )}
                      </>
                    )}
                  </button>
                  
                  {/* Indented submenu */}
                  {!collapsed && hasChildren && isExpanded && (
                    <div className="ml-8 mt-1.5 pl-3 border-l border-white/5 space-y-1">
                      {item.children!.map((child) => {
                        const isSubActive = activeView === "Cases"; // Highlight child
                        return (
                          <button
                            key={child}
                            onClick={() => onViewChange("Cases")}
                            className={`w-full text-left px-4 py-2 rounded-lg text-[0.8rem] transition-all cursor-pointer ${
                              isSubActive 
                                ? "text-[#00e07a] font-medium bg-[#00e07a]/5" 
                                : "text-slate-500 dark:text-gray-500 hover:text-slate-800 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/[0.02]"
                            }`}
                          >
                            {child}
                          </button>
                        );
                      })}
                    </div>
                  )}
                </div>
              );
            })}
          </nav>
        </div>

        {/* Account section */}
        <div>
          {!collapsed && (
            <p className="pl-3 text-[0.75rem] font-bold text-gray-500 tracking-[1px] uppercase mb-1.5" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
              Account
            </p>
          )}
          <nav className="space-y-1.5">
            {bottomItems.map((item) => (
              <button
                key={item.label}
                onClick={() => {
                  window.location.href = item.path;
                }}
                className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-transparent transition-all group cursor-pointer text-left ${
                  item.highlight
                    ? "bg-[#00e07a]/5 hover:bg-[#00e07a]/10 text-[#00e07a] hover:text-[#00e07a] border-[#00e07a]/10"
                    : "hover:bg-gray-100 dark:hover:bg-white/[0.03] text-slate-500 dark:text-[#9ca3af] hover:text-slate-800 dark:hover:text-white"
                }`}
              >
                <span className={`flex-shrink-0 ${item.highlight ? "text-[#00e07a]" : "text-slate-500 dark:text-[#9ca3af] group-hover:text-slate-800 dark:group-hover:text-white"}`}>
                  {item.icon}
                </span>
                {!collapsed && (
                  <span className={`text-[0.9rem] font-medium ${item.highlight ? "text-[#00e07a] font-semibold" : "group-hover:text-slate-800 dark:group-hover:text-white"}`}>
                    {item.label}
                  </span>
                )}
              </button>
            ))}
          </nav>
        </div>
      </div>

      {/* Theme Toggle Area */}
      {!collapsed && (
        <div className="px-4 py-3">
          <GamifiedThemeToggle />
        </div>
      )}

      {/* User Footer Profile Block */}
      {!collapsed && (
        <div className="p-4 border-t border-gray-200 dark:border-white/[0.04] flex-shrink-0">
          <div 
            onClick={() => { window.location.href = `${basePath}/pages/profile.php`; }}
            className="flex items-center gap-3 p-2.5 bg-gray-50 dark:bg-[#8b5cf6]/5 border border-gray-200 dark:border-[#8b5cf6]/10 rounded-xl cursor-pointer hover:bg-gray-100 dark:hover:bg-[#8b5cf6]/10 transition-all"
          >
            {/* User Initials Avatar */}
            <div className="w-10 h-10 rounded-full bg-gray-200 dark:bg-[#a855f7]/20 border border-gray-300 dark:border-[#a855f7]/30 flex items-center justify-center flex-shrink-0 overflow-hidden">
              {sessionUser?.profile_image ? (
                <img src={`${window.location.hostname === 'localhost' ? '/respawn-logics' : ''}/uploads/${sessionUser.profile_image}`} alt="Profile" className="w-full h-full object-cover" />
              ) : (
                <span className="text-slate-600 dark:text-[#00e07a] font-bold text-[0.95rem]">
                  {sessionUser ? sessionUser.initials : "GC"}
                </span>
              )}
            </div>
            
            <div className="min-w-0 flex-1">
              <div className="text-[0.85rem] font-semibold text-slate-800 dark:text-white truncate">
                {sessionUser ? sessionUser.full_name : "Jane Doe"}
              </div>
              <div className="text-[0.75rem] text-gray-500 truncate">
                {sessionUser ? sessionUser.role : "System Administrator"}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Collapsed user avatar */}
      {collapsed && (
        <div className="p-3 border-t border-white/[0.04] flex-shrink-0 flex justify-center">
          <div
            onClick={() => { window.location.href = `${basePath}/pages/profile.php`; }}
            className="w-10 h-10 rounded-full bg-[#a855f7]/20 border border-[#a855f7]/30 flex items-center justify-center cursor-pointer hover:bg-[#a855f7]/30 transition-all overflow-hidden"
            title={sessionUser ? sessionUser.full_name : "Jane Doe"}
          >
            {sessionUser?.profile_image ? (
              <img src={`${window.location.hostname === 'localhost' ? '/respawn-logics' : ''}/uploads/${sessionUser.profile_image}`} alt="Profile" className="w-full h-full object-cover" />
            ) : (
              <span className="text-[#c084fc] font-bold text-[0.85rem]">
                {sessionUser ? sessionUser.initials : "JD"}
              </span>
            )}
          </div>
        </div>
      )}
    </aside>
  );
}
