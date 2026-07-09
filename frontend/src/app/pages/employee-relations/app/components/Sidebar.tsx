import { useState, useEffect } from "react";
import {
  LayoutDashboard,
  Briefcase,
  Users,
  BarChart2,
  Settings,
  ChevronDown,
  ChevronRight,
  Menu,
  Layers,
  ArrowLeft,
  BotMessageSquare,
  Gamepad2,
  Clock,
  GitBranch,
  Kanban,
  Bot,
  FileText,
  CheckSquare,
  HelpCircle
} from "lucide-react";
import { GamifiedThemeToggle } from "./GamifiedThemeToggle";

type NavItem = {
  label: string;
  icon: React.ReactNode;
  viewName?: string;
  children?: string[];
  hasChevron?: boolean;
};

const adminNavItems: NavItem[] = [
  { label: "Dashboard", viewName: "Dashboard", icon: <LayoutDashboard size={20} /> },
  { label: "Daily Report", viewName: "DailyReport", icon: <FileText size={20} /> },
  { label: "Cases", viewName: "Cases", icon: <Users size={20} /> },
  { label: "Pipeline Board", viewName: "PipelineBoard", icon: <Kanban size={20} /> },
  { label: "Approvals", viewName: "Approvals", icon: <CheckSquare size={20} /> },
  { label: "Pipelines Settings", viewName: "Pipelines", icon: <GitBranch size={20} /> },
  { label: "Automation", viewName: "Automation", icon: <Bot size={20} /> },
  { label: "Templates", viewName: "Templates", icon: <Layers size={20} /> },
  { label: "AI Companion", viewName: "AICompanion", icon: <BotMessageSquare size={20} className="text-cyan-400" /> },
  { label: "Knowledge Base", viewName: "Knowledge Base", icon: <Settings size={20} /> },
  { label: "Attendance", viewName: "Attendance", icon: <Clock size={20} /> },
  { label: "Analytics", viewName: "Analytics", icon: <BarChart2 size={20} /> },
];

// Employee self-service view: only their own cases + the AI companion.
const employeeNavItems: NavItem[] = [
  { label: "My Cases", viewName: "Cases", icon: <Users size={20} /> },
  { label: "AI Companion", viewName: "AICompanion", icon: <BotMessageSquare size={20} className="text-cyan-400" /> },
];

const basePath = window.location.hostname === 'localhost' ? '/respawn-logics' : '';

const bottomItems = [
  { label: "Return to Workspace", icon: <ArrowLeft size={20} />, path: `${basePath}/frontend/dist/index.html#/dashboard`, highlight: true },
];

type SidebarProps = {
  activeView: string;
  onViewChange: (view: any) => void;
  mode?: "employee" | "admin";
  onStartTour?: () => void;
};

export function Sidebar({ activeView, onViewChange, mode = "admin", onStartTour }: SidebarProps) {
  const navItems = mode === "employee" ? employeeNavItems : adminNavItems;
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
          
          let roleFallback = data.user.role ? (data.user.role.toLowerCase() === 'super_admin' ? 'Employee' : data.user.role.charAt(0).toUpperCase() + data.user.role.slice(1)) : "Employee";
          let roleDesc = data.user.job_title ? data.user.job_title : roleFallback;
          if (data.user.department) {
            roleDesc += ` • ${data.user.department}`;
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
      className="h-full bg-background flex flex-col flex-shrink-0 border-r border-border transition-all duration-300 overflow-hidden font-sans select-none"
    >
      {/* Brand Logo Header */}
      <div className="flex items-center justify-between h-[70px] px-6 border-b border-border flex-shrink-0">
        {!collapsed && (
          <div className="flex items-center gap-3">
            {/* Gamepad logo icon */}
            <div
              className="w-10 h-10 bg-gradient-to-br from-[#00e07a] to-[#00b8ff] flex items-center justify-center flex-shrink-0"
              style={{ borderRadius: '10px', boxShadow: '0 8px 20px rgba(0,224,122,0.25)' }}
            >
              <i className="fa-solid fa-gamepad" style={{ color: '#000', fontSize: '20px' }}></i>
            </div>
            <div className="flex items-baseline gap-1.5">
              <span
                className="font-bold text-slate-800 dark:text-foreground text-[15px] tracking-[-0.5px] whitespace-nowrap"
                style={{ fontFamily: "'JetBrains Mono', monospace" }}
              >
                Employee Relations
              </span>
              <span
                className="font-bold text-primary text-[9px]"
                style={{ fontFamily: "'JetBrains Mono', monospace" }}
              >
                v2.0
              </span>
            </div>
          </div>
        )}
        
        {/* Header actions: replay tour (admin) + collapse toggle */}
        <div className="flex items-center gap-1 ml-auto">
          {!collapsed && onStartTour && (
            <button
              onClick={onStartTour}
              title="Replay the guided tour"
              className="p-1.5 rounded-lg hover:bg-accent transition-colors text-muted-foreground hover:text-primary cursor-pointer"
            >
              <HelpCircle size={16} />
            </button>
          )}
          <button
            onClick={() => setCollapsed((c) => !c)}
            className="p-1.5 rounded-lg hover:bg-accent transition-colors text-muted-foreground hover:text-slate-800 dark:hover:text-foreground cursor-pointer"
          >
            {collapsed ? <Menu size={16} /> : <Layers size={16} />}
          </button>
        </div>
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
                    id={item.viewName ? `tour-elr-nav-${item.viewName}` : undefined}
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
                        ? "bg-primary/10 border border-primary/20 shadow-[0_0_12px_rgba(0,224,122,0.08)]"
                        : "hover:bg-accent border border-transparent"
                    }`}
                  >
                    <span
                      className={`transition-colors ${
                        isActive
                          ? "text-primary"
                          : "text-muted-foreground group-hover:text-slate-800 dark:group-hover:text-foreground"
                      }`}
                    >
                      {item.icon}
                    </span>
                    {!collapsed && (
                      <>
                        <span className={`text-[0.9rem] font-medium flex-1 transition-colors ${
                          isActive
                            ? "text-primary"
                            : "text-muted-foreground group-hover:text-slate-800 dark:group-hover:text-foreground"
                        }`}>
                          {item.label}
                        </span>
                        {item.hasChevron && (
                          <span className={isActive ? "text-primary" : "text-muted-foreground group-hover:text-slate-800 dark:group-hover:text-foreground"}>
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
                    <div className="ml-8 mt-1.5 pl-3 border-l border-border space-y-1">
                      {item.children!.map((child) => {
                        const viewName = child === "Cases List" ? "Cases" : child;
                        const isSubActive = activeView === viewName;
                        return (
                          <button
                            key={child}
                            onClick={() => onViewChange(viewName as any)}
                            className={`w-full text-left px-4 py-2 rounded-lg text-[0.8rem] transition-all cursor-pointer ${
                              isSubActive 
                                ? "text-[#00e07a] font-medium bg-[#00e07a]/5" 
                                : "text-muted-foreground hover:text-foreground hover:bg-accent"
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

          {/* Back to Workspace — directly below the last nav item (Analytics) */}
          <button
            onClick={() => {
              window.location.href = `${basePath}/frontend/dist/index.html#/dashboard`;
            }}
            className="w-full flex items-center gap-3 px-4 py-3 mt-1.5 rounded-xl text-primary hover:bg-accent text-left cursor-pointer transition-all"
          >
            <ArrowLeft size={20} className="text-primary flex-shrink-0" />
            {!collapsed && <span className="text-[0.9rem] font-semibold">Back to Workspace</span>}
          </button>
        </div>
      </div>

      {/* ── mt-auto container (toggle + user) ─────────── */}
      <div className="mt-auto flex-shrink-0">
        {/* Theme toggle, right above the user */}
        <div className="mt-2 px-3">
          <GamifiedThemeToggle collapsed={collapsed} />
        </div>

        {/* User profile footer */}
        <div className="border-t border-border p-3 mt-2">
          {!collapsed ? (
            <div className="flex items-center gap-3 p-2 bg-muted/30 border border-border rounded-xl">
              <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 overflow-hidden">
                {sessionUser?.profile_image ? (
                  <img src={`${window.location.hostname === 'localhost' ? '/respawn-logics' : ''}/api/index.php?route=auth&action=download_avatar&file=${sessionUser.profile_image}`} alt="Profile" className="w-full h-full object-cover" />
                ) : (
                  <span className="text-primary font-bold text-[0.95rem]">
                    {sessionUser ? sessionUser.initials : "GC"}
                  </span>
                )}
              </div>
              <div className="min-w-0 flex-1">
                <div className="text-[0.85rem] font-semibold text-foreground truncate">
                  {sessionUser ? sessionUser.full_name : "Jane Doe"}
                </div>
                <div className="text-[10px] text-muted-foreground font-mono mt-0.5 truncate uppercase">
                  {sessionUser ? sessionUser.role : "Employee"}
                </div>
              </div>
            </div>
          ) : (
            <div className="flex justify-center">
              <div
                className="w-10 h-10 rounded-full bg-primary/10 border border-border flex items-center justify-center overflow-hidden"
                title={sessionUser ? sessionUser.full_name : "Jane Doe"}
              >
                {sessionUser?.profile_image ? (
                  <img src={`${window.location.hostname === 'localhost' ? '/respawn-logics' : ''}/api/index.php?route=auth&action=download_avatar&file=${sessionUser.profile_image}`} alt="Profile" className="w-full h-full object-cover" />
                ) : (
                  <span className="text-primary font-bold text-[0.85rem]">
                    {sessionUser ? sessionUser.initials : "JD"}
                  </span>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </aside>
  );
}
