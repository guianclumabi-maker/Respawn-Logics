import { useState, useEffect } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import { HoverCard, HoverCardContent, HoverCardTrigger } from "./ui/hover-card";
import { GamifiedThemeToggle } from "./GamifiedThemeToggle";
import {
  LayoutGrid,
  BarChart2,
  Sparkles,
  Clock,
  ShieldCheck,
  Calendar,
  CalendarCheck,
  Network,
  Zap,
  Brain,
  Crosshair,
  ShieldHalf,
  Headphones,
  PieChart,
  Users,
  Banknote,
  Scale,
  Star,
  Receipt,
  Gift,
  Gavel,
  UserCog,
  Settings,
  BookOpen,
  Satellite,
  Globe,
  BadgeInfo,
  Inbox,
  StarHalf,
  Scroll,
  MessageCircle,
  Menu,
  Layers,
  ArrowLeft,
  Briefcase,
  GitBranch,
  CheckCircle,
  Database,
  Search,
  LogOut,
  ChevronDown,
  ChevronRight,
  User,
  Server
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
  onViewChange: (view: ViewState | string) => void;
  badges?: SidebarBadges;
};

// ── Nav item shape ─────────────────────────────────────────

type NavEntry = {
  label: string;
  view: string;
  icon: React.ReactNode;
  badgeKey?: string | keyof SidebarBadges;
  externalLink?: string; // If set, it will do a full page load
  color?: string; // Custom text color
  onClick?: () => void;
};

type NavSection = {
  title: string;
  icon?: React.ReactNode;
  items: NavEntry[];
  hide?: boolean;
};

// ── Navigation config ──────────────────────────────────────

const getSections = (hasPermission: (p: string) => boolean, hasRole: (r: string | string[]) => boolean, tenantId: number | null, isAtsContext: boolean, tierConfig: any): NavSection[] => [
  {
    title: "Workspace",
    icon: <Layers size={20} />,
    hide: isAtsContext,
    items: [
      { label: "Dashboard", view: "Dashboard", icon: <LayoutGrid size={19} /> },
      // Surveys
      { label: "Engagement Surveys", view: "Surveys", icon: <BarChart2 size={19} /> },
      { label: "AI Companion", view: "AI Companion", icon: <Sparkles size={19} /> },
      ...(hasPermission("attendance.view") ? [{ label: "Attendance Tracking", view: "Attendance", icon: <Clock size={19} /> }] : []),
      ...(hasPermission("leave.view") || hasPermission("leave.request") ? [{ label: "Leave Requests", view: "Leaves", icon: <CalendarCheck size={19} /> }] : []),
      { label: "Org Chart Directory", view: "Org Chart", icon: <Network size={19} /> },
      { label: "My HR Cases", view: "My HR Cases", icon: <ShieldHalf size={19} /> },
      { label: "ELR Copilot", view: "ELR Copilot", icon: <Sparkles size={19} />, color: "#00b8ff" },
      { label: "IT / HR Service Desk", view: "IT / HR Service Desk", icon: <Headphones size={19} /> },
    ],
  },
  {
    title: "My Space",
    icon: <User size={20} />,
    hide: isAtsContext,
    items: [
      { label: "My Profile", view: "My Profile", icon: <UserCog size={19} /> },
      { label: "My Leave", view: "My Leave", icon: <CalendarCheck size={19} /> },
      { label: "My Payslips", view: "My Payslips", icon: <Banknote size={19} /> },
      { label: "My Compensation", view: "My Compensation", icon: <Scale size={19} /> },
    ]
  },
  {
    title: "Administration",
    icon: <Settings size={20} />,
    hide: isAtsContext || !(hasPermission("users.view") || hasPermission("settings.manage")),
    items: [
      ...(hasPermission("analytics.view") ? [{ label: "Workforce Analytics", view: "Analytics", icon: <PieChart size={19} /> }] : []),
      ...(hasPermission("users.manage") || hasPermission("shifts.manage") ? [{ label: "Employee Directory", view: "HR Directory", icon: <Users size={19} /> }] : []),
      ...(hasPermission("shifts.manage") ? [{ label: "Shift Scheduler", view: "Scheduling", icon: <Calendar size={19} /> }] : []),
      ...(hasPermission("ats.view") ? [{ label: "Recruitment / ATS", view: "ATS Dashboard", icon: <Crosshair size={19} /> }] : []),
      ...(hasPermission("payroll.manage") ? [{ label: "Payroll Engine", view: "Payroll Engine", icon: <Banknote size={19} /> }] : []),
      ...(hasPermission("compensation.manage") ? [{ label: "Compensation & Equity", view: "Compensation", icon: <Scale size={19} /> }] : []),
      ...(hasPermission("performance.manage") ? [{ label: "Performance", view: "Performance", icon: <Star size={19} /> }] : []),
      ...(hasPermission("expenses.manage") ? [{ label: "Expenses & Claims", view: "Expenses", icon: <Receipt size={19} /> }] : []),
      ...(hasPermission("benefits.manage") ? [{ label: "Benefits & HMO", view: "Benefits", icon: <Gift size={19} /> }] : []),
      ...(hasPermission("elr.view") ? [{ 
        label: "ELR Admin Console", 
        view: "ELR Admin Console", 
        icon: <Gavel size={19} />, 
        color: "#ef4444", 
      }] : []),
      ...(hasPermission("users.view") ? [{ label: "Users", view: "Admin Users", icon: <UserCog size={19} /> }] : []),
      ...(tierConfig?.org_units && hasPermission("users.manage") ? [{ label: "Org Units", view: "Org Units", icon: <Network size={19} /> }] : []),
      ...(hasPermission("users.manage") ? [{ label: "Roles & Permissions", view: "Admin Roles", icon: <ShieldHalf size={19} /> }] : []),
      ...(hasPermission("settings.manage") ? [{ label: "Tenant Settings", view: "Tenant Settings", icon: <Settings size={19} /> }] : []),
      ...(hasPermission("settings.manage") ? [{ label: "Knowledge Base Review", view: "Knowledge Base", icon: <BookOpen size={19} /> }] : []),
      ...(tenantId !== null ? [{ 
        label: "Platform Support", 
        view: "Platform Support", 
        icon: <Satellite size={19} />, 
        color: "#00e07a", 
        externalLink: window.location.href.includes('demo=true') ? undefined : "/pages/admin_platform_support.php",
        onClick: window.location.href.includes('demo=true') ? () => { window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: 'Platform Support is disabled in Live Preview.' } })); } : undefined
      }] : []),
    ]
  },

  {
    title: "System",
    icon: <Server size={20} />,
    hide: isAtsContext || !hasPermission("audit.view"),
    items: [
      { label: "Audit Trail", view: "Audit Logs", icon: <Scroll size={19} /> }
    ]
  },
  ...(hasPermission("ats.view") || hasPermission("ats.edit") || hasPermission("ats.edit_job") || hasPermission("ats.create_job") ? [{
    title: "Hiring (ATS)",
    icon: <Users size={20} />,
    hide: !isAtsContext,
    items: [
      { label: "ATS Dashboard", view: "ATS Dashboard", icon: <LayoutGrid size={19} /> },
      { label: "Jobs", view: "Jobs", icon: <Briefcase size={19} />, badgeKey: "urgentJobs" },
      { label: "Pipeline", view: "Pipeline", icon: <GitBranch size={19} /> },
      { label: "Interviews", view: "Interviews", icon: <Calendar size={19} />, badgeKey: "todayInterviews" },
      { label: "Approvals", view: "Approvals", icon: <CheckCircle size={19} />, badgeKey: "pendingApprovals" },
      { label: "Candidates", view: "Candidates", icon: <Users size={19} /> },
      { label: "Talent Pools", view: "Talent Pools", icon: <Database size={19} /> },
      { label: "Talent Search", view: "Talent Search", icon: <Search size={19} /> },
      { label: "Insights", view: "Insights", icon: <BarChart2 size={19} /> },
    ],
  }] : []),
  {
    title: "Account",
    icon: <UserCog size={20} />,
    items: [
      ...(tenantId !== null ? [{
        label: "Give us Feedback",
        view: "Feedback",
        icon: <MessageCircle size={19} />,
        onClick: () => {
          if (window.location.href.includes('demo=true')) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'info', message: 'Feedback forms are disabled in Live Preview.' } }));
          } else {
            window.location.href = "mailto:support@respawn-logics.com?subject=Respawn%20Logics%20Feedback";
          }
        }
      }] : []),
    ]
  }
];

// ── Badge component ────────────────────────────────────────

function Badge({ count }: { count: number }) {
  if (!count || count <= 0) return null;
  return (
    <span className="min-w-[18px] h-[18px] flex items-center justify-center rounded-full bg-primary text-primary border border-[#00e07a]/30 text-[0.65rem] font-mono font-bold px-1.5 leading-none">
      {count > 99 ? "99+" : count}
    </span>
  );
}

// ── Sidebar ────────────────────────────────────────────────

export function Sidebar({ activeView, onViewChange, badges = {} }: SidebarProps) {
  const [collapsed, setCollapsed] = useState(false);
  const { user, hasPermission, hasRole, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const isAtsContext = location.pathname.startsWith("/ats");

  const sections = getSections(hasPermission, hasRole, user?.tenant_id || null, isAtsContext, user?.tier_config || null).filter(s => !s.hide && s.items.length > 0);

  const isActive = (view: string) => activeView.view === view;

  const [collapsedSections, setCollapsedSections] = useState<Record<string, boolean>>(() => {
    try {
      const saved = localStorage.getItem("sidebarCollapsedSections");
      if (saved) return JSON.parse(saved);
    } catch (e) {}
    return {};
  });

  useEffect(() => {
    const activeSection = sections.find(s => s.items.some(i => i.view === activeView.view));
    if (activeSection && collapsedSections[activeSection.title]) {
      setCollapsedSections(prev => {
        const next = { ...prev, [activeSection.title]: false };
        localStorage.setItem("sidebarCollapsedSections", JSON.stringify(next));
        return next;
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeView.view]);

  const toggleSection = (title: string) => {
    if (!title) return;
    setCollapsedSections(prev => {
      const next = { ...prev, [title]: !prev[title] };
      localStorage.setItem("sidebarCollapsedSections", JSON.stringify(next));
      return next;
    });
  };

  return (
    <aside
      style={{
        width: collapsed ? 72 : 280,
      }}
      className="h-full bg-card flex flex-col flex-shrink-0 border-r border-border transition-all duration-300 overflow-hidden select-none"
    >
      {/* ── Brand header ──────────────────────────────── */}
      <div 
        className={`flex ${
          collapsed 
            ? "flex-col items-center justify-center py-5 gap-4" 
            : "items-center justify-between h-[70px] px-5"
        } border-b border-border flex-shrink-0`}
      >
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
                className="text-foreground tracking-tight"
                style={{
                  fontFamily: "'JetBrains Mono', monospace",
                  fontSize: "15px",
                  fontWeight: 700,
                  letterSpacing: "-0.5px",
                }}
              >
                Respawn Logics
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
        {collapsed && (
          <div
            className="w-10 h-10 bg-gradient-to-br from-[#00e07a] to-[#00b8ff] flex items-center justify-center flex-shrink-0"
            style={{ borderRadius: '10px', boxShadow: '0 8px 20px rgba(0,224,122,0.25)' }}
          >
            <i className="fa-solid fa-gamepad" style={{ color: '#000', fontSize: '20px' }}></i>
          </div>
        )}
        <button
          onClick={() => setCollapsed((c) => !c)}
          className={`p-1.5 rounded-lg hover:bg-accent transition-colors text-muted-foreground hover:text-slate-800 dark:hover:text-foreground cursor-pointer ${
            collapsed ? "" : "ml-auto"
          }`}
          title={collapsed ? "Expand sidebar" : "Collapse sidebar"}
        >
          {collapsed ? <Menu size={16} /> : <Layers size={16} />}
        </button>
      </div>

      {/* ── Navigation ────────────────────────────────── */}
      <div className="flex-1 min-h-0 px-3 overflow-y-auto py-5 space-y-5 scrollbar-thin">
        {sections.map((section) => {
          const isSectionCollapsed = !!collapsedSections[section.title];
          return (
          <div key={section.title || "_top"}>
            
            {/* Collapsed Section Icon with HoverCard */}
            {section.title && collapsed && (
              <HoverCard openDelay={0} closeDelay={0}>
                <HoverCardTrigger asChild>
                  <button className="w-10 h-10 mx-auto flex items-center justify-center text-muted-foreground hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-card/10 dark:hover:text-foreground rounded-lg transition-colors cursor-pointer mb-3">
                    {section.icon}
                  </button>
                </HoverCardTrigger>
                <HoverCardContent 
                  side="right" 
                  sideOffset={20} 
                  align="start"
                  className="bg-card border border-border shadow-2xl p-2 w-56 rounded-xl z-50"
                >
                  <h4 className="text-[0.75rem] font-bold text-muted-foreground tracking-[1px] uppercase mb-2 px-2" style={{ fontFamily: "'Space Grotesk', sans-serif" }}>
                    {section.title}
                  </h4>
                  <div className="space-y-0.5">
                    {section.items.map((item) => {
                      const active = isActive(item.view);
                      const badgeCount = item.badgeKey ? (badges[item.badgeKey as keyof SidebarBadges] || 0) : 0;
                      return (
                        <button
                          key={item.label}
                          onClick={() => {
                            if (item.onClick) {
                              item.onClick();
                            } else if (item.externalLink) {
                              window.location.href = item.externalLink;
                            } else {
                              onViewChange({ view: item.view });
                            }
                          }}
                          className={`
                            w-full flex items-center gap-3 px-[12px] py-2.5 rounded-lg transition-all duration-200 justify-start
                            ${active ? "bg-primary/10 text-primary font-semibold dark:bg-[#00e07a]/10 dark:text-[#00e07a]" : "text-slate-600 dark:text-muted-foreground hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-card/50 dark:hover:text-foreground"}
                          `}
                        >
                          <div className="flex-shrink-0 transition-transform duration-200 hover:scale-110" style={{ color: item.color || (active ? 'inherit' : '') }}>
                            {item.icon}
                          </div>
                          <span className="text-[13px] leading-tight truncate flex-1 text-left" style={{ color: item.color || (active ? 'inherit' : '') }}>
                            {item.label}
                          </span>
                          {badgeCount > 0 && <Badge count={badgeCount} />}
                        </button>
                      );
                    })}
                  </div>
                </HoverCardContent>
              </HoverCard>
            )}

            {/* Section title (Expanded) */}
            {section.title && !collapsed && (
              <button
                onClick={() => toggleSection(section.title)}
                className="w-full flex items-center justify-between pl-[12px] pr-2 mb-1.5 cursor-pointer group"
              >
                <p
                  className="text-[0.75rem] font-bold text-muted-foreground dark:text-muted-foreground tracking-[1px] uppercase group-hover:text-slate-700 dark:group-hover:text-foreground transition-colors"
                  style={{ fontFamily: "'Space Grotesk', sans-serif" }}
                >
                  {section.title}
                </p>
                <span className="text-muted-foreground group-hover:text-slate-700 dark:group-hover:text-foreground transition-colors">
                  {isSectionCollapsed ? <ChevronRight size={14} /> : <ChevronDown size={14} />}
                </span>
              </button>
            )}

            {/* Section Items (Expanded) */}
            {!collapsed && (
              <div
                className={`grid transition-[grid-template-rows,opacity,margin] duration-300 ease-in-out ${
                  !isSectionCollapsed
                    ? "grid-rows-[1fr] opacity-100 mb-5"
                    : "grid-rows-[0fr] opacity-0 mb-0 pointer-events-none"
                }`}
              >
                <div className="overflow-hidden">
                  <div className="space-y-0.5">
                    {section.items.map((item) => {
                      const active = isActive(item.view);
                      const badgeCount = item.badgeKey ? (badges[item.badgeKey as keyof SidebarBadges] || 0) : 0;
                      
                      return (
                        <button
                          key={item.label}
                          onClick={() => {
                            if (item.onClick) {
                              item.onClick();
                            } else if (item.externalLink) {
                              window.location.href = item.externalLink;
                            } else {
                              onViewChange({ view: item.view });
                            }
                          }}
                          className={`
                            w-full flex items-center gap-3 px-[12px] py-2.5 rounded-lg transition-all duration-200 justify-start
                            ${
                              active
                                ? "bg-primary/10 text-primary font-semibold dark:bg-[#00e07a]/10 dark:text-[#00e07a]"
                                : "text-muted-foreground hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-card/50 dark:hover:text-foreground"
                            }
                          `}
                        >
                          <div className="flex-shrink-0 transition-transform duration-200 group-hover:scale-110" style={{ color: item.color || (active ? 'inherit' : '') }}>
                            {item.icon}
                          </div>
                          <span className="text-[13px] leading-tight truncate flex-1 text-left" style={{ color: item.color || (active ? 'inherit' : '') }}>
                            {item.label}
                          </span>
                          {badgeCount > 0 && <Badge count={badgeCount} />}
                        </button>
                      );
                    })}
                  </div>
                </div>
              </div>
            )}
          </div>
        )})}
        {/* ── Platform Admin Command Center (Platform_Admin only) ── */}
        {(user?.role === "Platform_Admin" || user?.roles?.includes("Platform_Admin")) && (
          <div className="mt-3">
            <button
              onClick={() => navigate("/platform-admin")}
              title="Command Center"
              className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all cursor-pointer
                bg-gradient-to-r from-violet-500/10 to-fuchsia-500/10 hover:from-violet-500/20 hover:to-fuchsia-500/20
                text-violet-400 hover:text-violet-300 border border-violet-500/20 hover:border-violet-500/30
                ${collapsed ? "justify-center" : ""}`}
            >
              <ShieldCheck size={15} className="flex-shrink-0" />
              {!collapsed && <span>Command Center</span>}
            </button>
          </div>
        )}

        {/* ── Back to Workspace (ATS context only) ── */}
        {isAtsContext && (
          <div className="mt-3 px-3">
            <button
              onClick={() => onViewChange({ view: "Dashboard" })}
              className={`w-full flex items-center gap-3 px-[12px] py-2.5 rounded-lg transition-all duration-200 justify-start
                text-[#00b8ff] hover:bg-slate-100 hover:text-[#00b8ff] dark:hover:bg-card/50 cursor-pointer border-0 bg-transparent
                ${collapsed ? "justify-center" : ""}`}
              title="Back to Workspace"
            >
              <ArrowLeft size={19} className="text-[#00b8ff] flex-shrink-0" />
              {!collapsed && <span className="text-[13px] font-semibold">Back to Workspace</span>}
            </button>
          </div>
        )}
      </div>

      {/* ── mt-auto container ─────────────────────────── */}
      <div className="mt-auto flex-shrink-0">
        {/* Theme toggle, right above the user */}
        <div className="mt-2 px-3">
          <GamifiedThemeToggle collapsed={collapsed} />
        </div>

        {/* User profile footer */}
        <div className="border-t border-border p-3 mt-2 bg-muted/50">
          <div className={`flex items-center ${collapsed ? "justify-center flex-col gap-3" : "justify-between"}`}>
            <div className="flex items-center gap-3 min-w-0">
              <div className="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 text-primary font-bold text-sm uppercase">
                {user?.name?.charAt(0) || "U"}
              </div>
              {!collapsed && (
                <div className="min-w-0 flex-1">
                  <div className="text-[13px] font-bold text-slate-900 dark:text-slate-100 truncate">
                    {user?.name || "User"}
                  </div>
                  <div className="text-[11px] text-slate-500 truncate">
                    {user?.email || "user@example.com"}
                  </div>
                </div>
              )}
            </div>
            <button
              onClick={() => logout()}
              className="p-2 text-slate-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors flex-shrink-0"
              title="Log out"
            >
              <LogOut size={16} />
            </button>
          </div>
        </div>
      </div>
    </aside>
  );
}
