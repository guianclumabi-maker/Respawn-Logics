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
    if (path.includes("/ats/pipeline")) return { view: "Pipeline" };
    if (path.includes("/ats/jobs")) return { view: "Jobs" };
    if (path.includes("/ats/candidates")) return { view: "Candidates" };
    if (path.includes("/ats")) return { view: "Dashboard" };
    if (path.includes("/attendance")) return { view: "Attendance" };
    if (path.includes("/approvals")) return { view: "Timesheet Approvals" };
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
      case "Dashboard": navigate("/ats"); break;
      case "Pipeline": navigate("/ats/pipeline"); break;
      case "Jobs": navigate("/ats/jobs"); break;
      case "Candidates": navigate("/ats/candidates"); break;
      case "Interviews": navigate("/ats/interviews"); break;
      case "Analytics": navigate("/ats/analytics"); break;
      case "Approvals": navigate("/ats/approvals"); break;
      case "Attendance": navigate("/attendance"); break;
      case "Timesheet Approvals": navigate("/attendance/approvals"); break;
      default: navigate("/"); break; // fallback
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
