import { useState, useEffect } from "react";
import { Outlet, useNavigate, useLocation } from "react-router-dom";
import { Sidebar } from "../components/Sidebar";
import type { ViewState, SidebarBadges } from "../components/Sidebar";

const API_BASE = import.meta.env.VITE_API_BASE_URL;
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

    // Map old view names to new routes
    switch (viewState.view) {
      // Apps
      case "Dashboard": navigate("/dashboard"); break;
      case "Employee Relations": navigate("/employee-relations"); break;
      case "Onboarding": navigate("/onboarding"); break;
      
      // Core HR
      case "HR Directory": navigate("/hr-directory"); break;
      case "Org Chart": navigate("/org-chart"); break;
      case "Leaves": navigate("/leaves"); break;
      case "Attendance": navigate("/attendance"); break;
      case "Scheduling": navigate("/scheduling"); break;
      
      // Finance & Services
      case "Payroll Engine": navigate("/payroll"); break;
      case "Benefits": navigate("/benefits"); break;
      case "Compensation": navigate("/compensation"); break;
      case "Expenses": navigate("/expenses"); break;
      case "IT / HR Service Desk": navigate("/service-desk"); break;
      
      // Talent
      case "Performance": navigate("/performance"); break;
      case "Knowledge Base": navigate("/knowledge"); break;
      case "Surveys": navigate("/surveys"); break;
      
      // ATS
      case "ATS Dashboard": navigate("/ats"); break;
      case "Pipeline": navigate("/ats/pipeline"); break;
      case "Jobs": navigate("/ats/jobs"); break;
      case "Candidates": navigate("/ats/candidates"); break;
      case "Interviews": navigate("/ats/interviews"); break;
      case "Approvals": navigate("/ats/approvals"); break;
      case "Talent Pools": navigate("/ats/pools"); break;
      case "Talent Search": navigate("/ats/search"); break;
      case "Recruiting Copilot": navigate("/ats/copilot"); break;
      case "Insights": navigate("/ats/insights"); break;
      
      // Analytics & System
      case "AI Companion": navigate("/ai-companion"); break;
      case "Analytics": navigate("/analytics"); break;
      case "Admin Users": navigate("/admin/users"); break;
      case "Admin Roles": navigate("/admin/roles"); break;
      case "Tenant Settings": navigate("/admin/settings"); break;
      case "Audit Logs": navigate("/admin/audit"); break;
      
      default: navigate("/dashboard"); break; // fallback
    }
  };

  // Fetch badges
  useEffect(() => {
    const fetchBadges = async () => {
      try {
        const res = await fetch(`${API}&action=dashboard`);
        if (!res.ok) return;
        const data = await res.json();
        if (data.success && data.data) {
          const d = data.data;
          setBadges({
            actions: d.action_items ?? d.actions ?? 0,
            urgentJobs: d.urgent_jobs ?? 0,
            todayInterviews: d.today_interviews ?? d.interviews_today ?? 0,
            pendingApprovals: d.pending_approvals ?? 0,
            copilotAlerts: d.copilot_alerts ?? 0,
          });
        }
      } catch {
        // fail silently
      }
    };
    fetchBadges();
  }, []);

  return (
    <div className="flex h-screen w-full overflow-hidden bg-slate-100 dark:bg-[#06070a] relative z-0">
      {/* Global Background Glow Effects */}
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-[#00e07a] blur-[120px] opacity-[0.05] dark:opacity-[0.06] pointer-events-none z-[-1]" />
      <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#9b6dff] blur-[140px] opacity-[0.03] dark:opacity-[0.05] pointer-events-none z-[-1]" />
      
      <Sidebar
        activeView={activeView}
        onViewChange={handleViewChange}
        badges={badges}
      />
      
      {/* This renders whatever matched the current route! */}
      <Outlet />
    </div>
  );
}
