import { useState, useEffect } from "react";
import { useLocation } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import { GamifiedThemeToggle } from "./GamifiedThemeToggle";
import {
  LayoutGrid,
  BarChart2,
  Sparkles,
  Clock,
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
  LogOut
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
  items: NavEntry[];
  hide?: boolean;
};

// ── Navigation config ──────────────────────────────────────

const getSections = (hasPermission: (p: string) => boolean, hasRole: (r: string | string[]) => boolean, tenantId: number | null, isAtsContext: boolean): NavSection[] => [
  {
    title: "Workspace",
    hide: isAtsContext,
    items: [
      { label: "Dashboard", view: "Dashboard", icon: <LayoutGrid size={19} /> },
      // Surveys
      { label: "Engagement Surveys", view: "Surveys", icon: <BarChart2 size={19} /> },
      { label: "AI Companion", view: "AI Companion", icon: <Sparkles size={19} /> },
      ...(hasPermission("attendance.view") ? [{ label: "Attendance Tracking", view: "Attendance", icon: <Clock size={19} /> }] : []),
      ...(hasPermission("shifts.manage") ? [{ label: "Shift Scheduler", view: "Scheduling", icon: <Calendar size={19} /> }] : []),
      ...(hasPermission("leave.view") || hasPermission("leave.request") ? [{ label: "Leave Requests", view: "Leaves", icon: <CalendarCheck size={19} /> }] : []),
      { label: "Org Chart Directory", view: "Org Chart", icon: <Network size={19} /> },
      ...(hasPermission("users.manage") ? [{ label: "Onboarding", view: "Onboarding", icon: <Zap size={19} /> }] : []),
      ...(hasPermission("intelligence.view") ? [{ label: "Predictive AI", view: "Analytics", icon: <Brain size={19} />, color: "#f59e0b" }] : []),
      ...(hasPermission("ats.view") ? [{ label: "Recruitment / ATS", view: "ATS Dashboard", icon: <Crosshair size={19} /> }] : []),
      { label: "My HR Cases", view: "Employee Relations", icon: <ShieldHalf size={19} /> },
      ...(tenantId !== null ? [{ 
        label: "IT/HR Service Desk", 
        view: "IT / HR Service Desk", 
        icon: <Headphones size={19} />,
        externalLink: hasPermission("esm.manage") ? "/pages/esm_admin.php" : "/pages/esm_employee.php"
      }] : []),
    ],
  },
  {
    title: "Administration",
    hide: isAtsContext || !(hasPermission("users.view") || hasPermission("settings.manage")),
    items: [
      ...(hasPermission("analytics.view") ? [{ label: "Workforce Analytics", view: "Analytics", icon: <PieChart size={19} /> }] : []),
      ...(hasPermission("users.manage") || hasPermission("shifts.manage") ? [{ label: "Employee Directory", view: "HR Directory", icon: <Users size={19} /> }] : []),
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
      ...(hasPermission("users.manage") ? [{ label: "Roles & Permissions", view: "Admin Roles", icon: <ShieldHalf size={19} /> }] : []),
      ...(hasPermission("settings.manage") ? [{ label: "Tenant Settings", view: "Tenant Settings", icon: <Settings size={19} /> }] : []),
      ...(hasPermission("settings.manage") ? [{ label: "Knowledge Base Review", view: "Knowledge Base", icon: <BookOpen size={19} /> }] : []),
      ...(tenantId !== null ? [{ 
        label: "Platform Support", 
        view: "Platform Support", 
        icon: <Satellite size={19} />, 
        color: "#00e07a", 
        externalLink: "/pages/admin_platform_support.php" 
      }] : []),
    ]
  },
  {
    title: "Vendor Universe",
    hide: isAtsContext || !hasRole(["Platform_Admin", "Support_Agent", "Implementation_Specialist"]),
    items: [
      { label: "SaaS Headquarters", view: "SaaS Headquarters", icon: <Globe size={19} />, externalLink: "/pages/saas_admin.php" },
      ...(hasRole("Platform_Admin") ? [{ label: "Vendor Staff", view: "Vendor Staff", icon: <BadgeInfo size={19} />, externalLink: "/pages/saas_staff.php" }] : []),
      { label: "Global Support Inbox", view: "Global Support Inbox", icon: <Inbox size={19} />, externalLink: "/pages/saas_support.php" },
      { label: "Feedback Corner", view: "Feedback Corner", icon: <StarHalf size={19} />, externalLink: "/pages/saas_feedback.php" },
    ]
  },
  {
    title: "System",
    hide: isAtsContext || !hasPermission("audit.view"),
    items: [
      { label: "Audit Trail", view: "Audit Logs", icon: <Scroll size={19} /> }
    ]
  },
  ...(hasPermission("ats.view") || hasPermission("ats.edit") || hasPermission("ats.edit_job") || hasPermission("ats.create_job") ? [{
    title: "Hiring (ATS)",
    hide: !isAtsContext,
    items: [
      { label: "Back to Workspace", view: "Dashboard", icon: <ArrowLeft size={19} />, color: "#00b8ff" },
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
    items: [
      ...(tenantId !== null ? [{
        label: "Give us Feedback",
        view: "Feedback",
        icon: <MessageCircle size={19} />,
        onClick: () => {
          if (typeof window !== "undefined" && (window as any).openGlobalFeedbackModal) {
            (window as any).openGlobalFeedbackModal();
          } else {
            alert("Feedback module is only available from within the legacy wrapper.");
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
  const location = useLocation();
  const isAtsContext = location.pathname.startsWith("/ats");

  const sections = getSections(hasPermission, hasRole, user?.tenant_id || null, isAtsContext).filter(s => !s.hide);

  const isActive = (view: string) => activeView.view === view;

  return (
    <aside
      style={{
        width: collapsed ? 72 : 280,
      }}
      className="h-full bg-white dark:bg-[#0f172a] flex flex-col flex-shrink-0 border-r border-gray-200 dark:border-border transition-all duration-300 overflow-hidden select-none"
    >
      {/* ── Brand header ──────────────────────────────── */}
      <div className="flex items-center justify-between h-[70px] px-5 border-b border-gray-200 dark:border-border flex-shrink-0">
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
            className="w-10 h-10 bg-gradient-to-br from-[#00e07a] to-[#00b8ff] flex items-center justify-center flex-shrink-0 mx-auto"
            style={{ borderRadius: '10px', boxShadow: '0 8px 20px rgba(0,224,122,0.25)' }}
          >
            <i className="fa-solid fa-gamepad" style={{ color: '#000', fontSize: '20px' }}></i>
          </div>
        )}
        <button
          onClick={() => setCollapsed((c) => !c)}
          className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-white/5 transition-colors text-muted-foreground hover:text-slate-800 dark:hover:text-foreground cursor-pointer ml-auto"
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
                className="pl-[12px] text-[0.75rem] font-bold text-muted-foreground dark:text-muted-foreground tracking-[1px] uppercase mb-1.5"
                style={{ fontFamily: "'Space Grotesk', sans-serif" }}
              >
                {section.title}
              </p>
            )}

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
                      w-full flex items-center gap-3 px-[12px] py-2.5 rounded-lg transition-all duration-200
                      ${collapsed ? "justify-center" : "justify-start"}
                      ${
                        active
                          ? "bg-primary/10 text-primary font-semibold dark:bg-[#00e07a]/10 dark:text-[#00e07a]"
                          : "text-slate-600 dark:text-slate-400 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-white/5 dark:hover:text-white"
                      }
                    `}
                  >
                    <div className="flex-shrink-0 transition-transform duration-200 group-hover:scale-110" style={{ color: item.color || (active ? 'inherit' : '') }}>
                      {item.icon}
                    </div>

                    {!collapsed && (
                      <>
                        <span className="text-[13px] leading-tight truncate flex-1 text-left" style={{ color: item.color || (active ? 'inherit' : '') }}>
                          {item.label}
                        </span>
                        {badgeCount > 0 && <Badge count={badgeCount} />}
                      </>
                    )}
                  </button>
                );
              })}
            </div>
          </div>
        ))}

        {/* ── Gamified Theme Toggle ─────────────────────── */}
        <div className="mt-4 pt-4 border-t border-gray-200 dark:border-border">
          <GamifiedThemeToggle collapsed={collapsed} />
        </div>
      </div>

      {/* ── Profile Footer ────────────────────────────── */}
      <div className="border-t border-gray-200 dark:border-border p-3 flex-shrink-0 bg-slate-50/50 dark:bg-slate-900/50">
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
    </aside>
  );
}
