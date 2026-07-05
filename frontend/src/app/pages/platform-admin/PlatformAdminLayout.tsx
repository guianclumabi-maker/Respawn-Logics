import { NavLink, Outlet, useNavigate } from "react-router-dom";
import {
  LayoutDashboard,
  Building2,
  Users,
  HeartPulse,
  UserCog,
  LogOut,
  ShieldCheck,
  Ticket,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";

const navItems = [
  { to: "/platform-admin", label: "Overview", icon: LayoutDashboard, end: true },
  { to: "/platform-admin/tenants", label: "Tenants", icon: Building2 },
  { to: "/platform-admin/staff", label: "Internal Staff", icon: Users },
  { to: "/platform-admin/support", label: "Support Tickets", icon: Ticket },
  { to: "/platform-admin/health", label: "System Health", icon: HeartPulse },
  { to: "/platform-admin/impersonate", label: "Impersonate User", icon: UserCog },
];

export function PlatformAdminLayout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const initials = user?.name
    ? user.name.split(" ").map((n: string) => n[0]).join("").substring(0, 2).toUpperCase()
    : "PA";

  return (
    <div className="flex h-screen bg-[#080b12] text-[#c8d0e0] overflow-hidden">
      {/* ── Sidebar ── */}
      <aside className="w-64 flex-shrink-0 flex flex-col bg-[#0c1018] border-r border-white/[0.05] h-full">
        {/* Brand */}
        <div className="px-5 py-5 border-b border-white/[0.05] flex items-center gap-3">
          <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-fuchsia-600 flex items-center justify-center flex-shrink-0">
            <ShieldCheck size={16} className="text-white" />
          </div>
          <div>
            <p className="text-[0.8rem] font-bold text-white tracking-wide leading-tight">Command Center</p>
            <p className="text-[0.7rem] text-violet-400/80">Platform Admin</p>
          </div>
        </div>

        {/* Nav */}
        <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          <p className="px-3 text-[0.65rem] font-bold text-slate-600 tracking-widest uppercase mb-2">
            Management
          </p>
          {navItems.map(({ to, label, icon: Icon, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2.5 rounded-lg text-[0.85rem] font-medium transition-all cursor-pointer ${
                  isActive
                    ? "bg-violet-500/10 text-violet-300 border border-violet-500/20"
                    : "text-slate-400 hover:text-slate-200 hover:bg-white/[0.03] border border-transparent"
                }`
              }
            >
              <Icon size={16} />
              {label}
            </NavLink>
          ))}
        </nav>

        {/* Go back to workspace */}
        <div className="px-3 pb-2">
          <button
            onClick={() => navigate("/dashboard")}
            className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-[0.85rem] text-emerald-400/80 hover:text-emerald-300 hover:bg-emerald-500/5 border border-transparent transition-all cursor-pointer"
          >
            <LayoutDashboard size={16} />
            Back to Workspace
          </button>
        </div>

        {/* User footer */}
        <div className="px-3 pb-4 border-t border-white/[0.04] pt-3">
          <div className="flex items-center gap-3 px-3 py-2.5 bg-violet-500/5 border border-violet-500/10 rounded-xl">
            <div className="w-8 h-8 rounded-full bg-violet-500/20 border border-violet-500/30 flex items-center justify-center text-xs font-bold text-violet-300 flex-shrink-0">
              {initials}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-xs font-semibold text-slate-200 truncate">{user?.name ?? "Platform Admin"}</p>
              <p className="text-[0.65rem] text-violet-400/70">Platform Admin</p>
            </div>
            <button
              onClick={logout}
              title="Sign out"
              className="text-slate-500 hover:text-red-400 transition-colors cursor-pointer"
            >
              <LogOut size={14} />
            </button>
          </div>
        </div>
      </aside>

      {/* ── Main content ── */}
      <main className="flex-1 overflow-auto bg-[#080b12]">
        <Outlet />
      </main>
    </div>
  );
}
