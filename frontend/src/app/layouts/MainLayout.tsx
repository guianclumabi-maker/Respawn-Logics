import { apiFetch } from "../lib/apiClient";
import { useState, useEffect } from "react";
import { Outlet, useNavigate, useLocation } from "react-router-dom";
import { Sidebar } from "../components/Sidebar";
import type { ViewState, SidebarBadges } from "../components/Sidebar";
import { viewStateToPath } from "../lib/atsNav";
import { useAuth } from "../context/AuthContext";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=candidates`;

export default function MainLayout() {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, switchPersona } = useAuth();

  // Map react-router location to the old ViewState for the Sidebar compatibility
  const getActiveViewFromPath = (): ViewState => {
    const path = location.pathname;
    
    // Apps
    if (path === "/dashboard" || path === "/") return { view: "Dashboard" };
    if (path.includes("/my-hr-cases")) return { view: "My HR Cases" };
    if (path.includes("/employee-relations")) return { view: "Employee Relations" };
    if (path.includes("/elr-copilot")) return { view: "ELR Copilot" };
    if (path.includes("/onboarding")) return { view: "Onboarding" };
    
    // Self-Service (ESS)
    if (path.includes("/my/payslips")) return { view: "My Payslips" };
    if (path.includes("/my/leave")) return { view: "My Leave" };
    if (path.includes("/my/compensation")) return { view: "My Compensation" };
    if (path.includes("/my/profile")) return { view: "My Profile" };
    
    // Core HR
    if (path.includes("/hr-directory")) return { view: "HR Directory" };
    if (path.includes("/org-chart")) return { view: "Org Chart" };
    if (path.includes("/leaves")) return { view: "Leaves" };
    if (path.includes("/attendance")) return { view: "Attendance" };
    if (path.includes("/scheduling")) return { view: "Scheduling" };
    
    // Finance & Services
    if (path.includes("/payroll")) return { view: "Payroll Engine" };
    if (path.includes("/benefits")) return { view: "Benefits" };
    if (path.includes("/compensation")) return { view: "Compensation" };
    if (path.includes("/expenses")) return { view: "Expenses" };
    if (path.includes("/service-desk")) return { view: "IT / HR Service Desk" };
    
    // Talent & Performance
    if (path.includes("/performance")) return { view: "Performance" };
    if (path.includes("/knowledge")) return { view: "Knowledge Base" };
    if (path.includes("/surveys")) return { view: "Surveys" };
    
    // ATS System
    if (path.includes("/ats/pipeline")) return { view: "Pipeline" };
    if (path.includes("/ats/jobs")) return { view: "Jobs" };
    if (path.includes("/ats/candidates")) return { view: "Candidates" };
    if (path.includes("/ats/interviews")) return { view: "Interviews" };
    if (path.includes("/ats/approvals")) return { view: "Approvals" };
    if (path.includes("/ats/pools")) return { view: "Talent Pools" };
    if (path.includes("/ats/search")) return { view: "Talent Search" };
    if (path.includes("/ats/copilot")) return { view: "Recruiting Copilot" };
    if (path.includes("/ats/insights")) return { view: "Insights" };
    if (path === "/ats") return { view: "ATS Dashboard" };
    
    // Admin & System
    if (path.includes("/ai-companion")) return { view: "AI Companion" };
    if (path.includes("/analytics")) return { view: "Analytics" };
    if (path.includes("/admin/users")) return { view: "Admin Users" };
    if (path.includes("/admin/roles")) return { view: "Admin Roles" };
    if (path.includes("/admin/org-units")) return { view: "Org Units" };
    if (path.includes("/admin/settings")) return { view: "Tenant Settings" };
    if (path.includes("/admin/audit")) return { view: "Audit Logs" };

    return { view: "Dashboard" };
  };

  const [activeView, setActiveView] = useState<ViewState>(getActiveViewFromPath());
  const [badges, setBadges] = useState<SidebarBadges>({});

  // Sync route changes to Sidebar's activeView
  useEffect(() => {
    setActiveView(getActiveViewFromPath());
  }, [location.pathname]);

  // Handle clicks from Sidebar
  const handleViewChange = (viewOrState: ViewState | string) => {
    const viewState = typeof viewOrState === "string" ? { view: viewOrState } : viewOrState;
    setActiveView(viewState);


    navigate(viewStateToPath(viewState));
  };

  // Fetch badges
  useEffect(() => {
    const fetchBadges = async () => {
      try {
        const res = await apiFetch(`${API}&action=dashboard`);
        if (!res.ok) return;
        const data = await res.json();
        if (data.success && data.action_summary) {
          const s = data.action_summary;
          setBadges({
            actions: s.awaiting_review ?? 0,
            urgentJobs: 0,
            todayInterviews: s.interviews_today ?? 0,
            pendingApprovals: s.pending_approvals ?? 0,
            copilotAlerts: 0,
          });
        }
      } catch {
        // fail silently
      }
    };
    fetchBadges();
  }, []);

  // Demo Auto-Tour Logic
  useEffect(() => {
    if (!window.location.href.includes("demo=true")) return;

    let tourTimeout: ReturnType<typeof setTimeout>;
    let idleTimeout: ReturnType<typeof setTimeout>;

    const DEMO_TOUR_PATHS = [
      "/dashboard",
      "/surveys",
      "/hr-directory",
      "/leaves",
      "/payroll",
      "/ats",
      "/admin/users",
    ];

    const startTourTimer = () => {
      clearTimeout(tourTimeout);
      tourTimeout = setTimeout(() => {
        let currentPath = window.location.hash.replace('#', '').split('?')[0];
        let currentIndex = DEMO_TOUR_PATHS.indexOf(currentPath);
        if (currentIndex === -1) currentIndex = 0;
        const nextIndex = (currentIndex + 1) % DEMO_TOUR_PATHS.length;
        navigate(DEMO_TOUR_PATHS[nextIndex]);
        startTourTimer();
      }, 5000);
    };

    const handleInteraction = () => {
      clearTimeout(tourTimeout);
      clearTimeout(idleTimeout);
      
      idleTimeout = setTimeout(() => {
        startTourTimer();
      }, 10000);
    };

    startTourTimer();

    const events = ['mousedown', 'keydown', 'touchstart', 'wheel'];
    events.forEach(e => window.addEventListener(e, handleInteraction, { passive: true }));

    return () => {
      clearTimeout(tourTimeout);
      clearTimeout(idleTimeout);
      events.forEach(e => window.removeEventListener(e, handleInteraction));
    };
  }, [navigate]);

  // The ELR module (My HR Cases + ELR Admin Console) is a full-screen sub-app that renders
  // its OWN sidebar, so hide the main platform sidebar on those routes to avoid a double sidebar.
  const isElrSubApp =
    location.pathname.includes("/employee-relations") ||
    location.pathname.includes("/my-hr-cases");

  return (
    <div className="flex h-screen bg-background text-foreground overflow-hidden">
      {!isElrSubApp && (
        <Sidebar
          activeView={activeView}
          onViewChange={handleViewChange}
          badges={badges}
        />
      )}
      <main className="flex-1 flex flex-col min-w-0 overflow-hidden relative z-10 bg-background">
        {/* Top Presentation / Persona HUD Banner */}
        <div className="bg-[#0b0f19] border-b border-white/[0.08] px-4 py-2 flex flex-wrap items-center justify-between gap-2 text-xs flex-shrink-0 z-20">
          <div className="flex items-center gap-2">
            <span className="w-2 h-2 rounded-full bg-[#00e07a] animate-pulse"></span>
            <span className="font-mono text-[11px] text-slate-300">
              Active Persona: <strong className="text-white">{user?.name || 'Peter Parker'}</strong>
              <span className="text-slate-400 ml-1">({user?.job_title || 'Chief People Officer'})</span>
            </span>
            <span className="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-[#00e07a]/10 text-[#00e07a] border border-[#00e07a]/30">
              {user?.role || (user?.roles && user?.roles[0]) || 'Admin'}
            </span>
          </div>
          <div className="flex items-center gap-1.5 flex-wrap">
            <span className="text-[11px] text-slate-400 font-mono hidden md:inline">Switch Role (No Password):</span>
            <button
              type="button"
              onClick={() => switchPersona('employee')}
              title="Switch to Regular Employee View"
              className={`px-2.5 py-1 rounded text-[11px] font-mono font-medium transition cursor-pointer ${
                user?.name === 'David Kim' || user?.role === 'Employee'
                  ? 'bg-[#00e07a] text-black font-bold shadow-[0_0_12px_rgba(0,224,122,0.4)]'
                  : 'bg-white/5 text-slate-300 hover:bg-white/10 hover:text-white border border-white/5'
              }`}
            >
              👤 Employee (David Kim)
            </button>
            <button
              type="button"
              onClick={() => switchPersona('manager')}
              title="Switch to Manager View"
              className={`px-2.5 py-1 rounded text-[11px] font-mono font-medium transition cursor-pointer ${
                user?.name === 'Sarah Chen' || user?.role === 'Manager'
                  ? 'bg-[#4f8ef7] text-white font-bold shadow-[0_0_12px_rgba(79,142,247,0.4)]'
                  : 'bg-white/5 text-slate-300 hover:bg-white/10 hover:text-white border border-white/5'
              }`}
            >
              👔 Manager (Sarah Chen)
            </button>
            <button
              type="button"
              onClick={() => switchPersona('admin')}
              title="Switch to Super Admin View"
              className={`px-2.5 py-1 rounded text-[11px] font-mono font-medium transition cursor-pointer ${
                user?.name === 'Peter Parker' || user?.role === 'Super_Admin'
                  ? 'bg-[#9b6dff] text-white font-bold shadow-[0_0_12px_rgba(155,109,255,0.4)]'
                  : 'bg-white/5 text-slate-300 hover:bg-white/10 hover:text-white border border-white/5'
              }`}
            >
              🛡️ Admin (Peter Parker)
            </button>
            <a
              href={`${API_BASE}/presentation.html`}
              target="_blank"
              rel="noreferrer"
              className="ml-1 px-2.5 py-1 rounded text-[11px] font-mono font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30 hover:bg-amber-500 hover:text-black transition flex items-center gap-1"
            >
              ★ Slide Deck
            </a>
          </div>
        </div>
        <Outlet context={{ setBadges }} />
      </main>
    </div>
  );
}
