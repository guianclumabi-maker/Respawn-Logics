import { useState, useEffect } from "react";
import { Outlet, useNavigate, useLocation } from "react-router-dom";
import { Sidebar } from "../components/Sidebar";
import type { ViewState, SidebarBadges } from "../components/Sidebar";
import { viewStateToPath } from "../lib/atsNav";

const API_BASE = import.meta.env.VITE_API_BASE_URL || (window.location.origin + (window.location.hostname === "localhost" ? "/respawn-logics" : ""));
const API = `${API_BASE}/api/index.php?route=candidates`;

export default function MainLayout() {
  const navigate = useNavigate();
  const location = useLocation();

  // Map react-router location to the old ViewState for the Sidebar compatibility
  const getActiveViewFromPath = (): ViewState => {
    const path = location.pathname;
    
    // Apps
    if (path === "/dashboard" || path === "/") return { view: "Dashboard" };
    if (path.includes("/employee-relations")) return { view: "Employee Relations" };
    if (path.includes("/onboarding")) return { view: "Onboarding" };
    
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
        const res = await fetch(`${API}&action=dashboard`);
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

  return (
    <div className="flex h-screen bg-background text-foreground overflow-hidden">
      <main className="flex-1 flex flex-col min-w-0 overflow-hidden relative z-10 bg-[#0f1422]">
        <Outlet context={{ setBadges }} />
      </main>
    </div>
  );
}
