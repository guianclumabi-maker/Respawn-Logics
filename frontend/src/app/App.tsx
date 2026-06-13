import { useState, useEffect } from "react";
import { Sidebar } from "./components/Sidebar";
import type { ViewState, SidebarBadges } from "./components/Sidebar";

// ── Existing page components ───────────────────────────────
import { ATSDashboard } from "./components/ATSDashboard";
import { JobsPage } from "./components/JobsPage";
import { PipelineBoard } from "./components/PipelineBoard";
import { InterviewsPage } from "./components/InterviewsPage";
import { InsightsPage } from "./components/InsightsPage";
import { CandidateProfile } from "./components/CandidateProfile";
import { CandidatesList } from "./components/CandidatesList";
import { TalentPools } from "./components/TalentPools";
import { PoolDetail } from "./components/PoolDetail";
import { TalentSearch } from "./components/TalentSearch";
import { RecruitingCopilot } from "./components/RecruitingCopilot";
import { Approvals } from "./components/Approvals";
import { Permissions } from "./components/Permissions";
import { Settings } from "./components/Settings";

// ── Attendance components ─────────────────────────────────
import { AttendanceDashboard } from "./attendance/AttendanceDashboard";
import { ManagerApprovals } from "./attendance/ManagerApprovals";

// ── API base ───────────────────────────────────────────────
const API_BASE = import.meta.env.VITE_API_BASE_URL;
const API = `${API_BASE}/api/index.php?route=candidates`;

// ── Placeholder component for unbuilt pages ────────────────
function PlaceholderPage({
  title,
  onViewChange,
}: {
  title: string;
  onViewChange: (v: ViewState) => void;
}) {
  return (
    <div className="flex-1 flex items-center justify-center overflow-hidden">
      <div className="text-center space-y-4">
        <div className="w-16 h-16 rounded-2xl bg-[#8b5cf6]/10 border border-[#8b5cf6]/20 flex items-center justify-center mx-auto">
          <span className="text-[#a855f7] text-2xl font-bold">⚡</span>
        </div>
        <h2 className="text-xl font-semibold text-white" style={{ fontFamily: "'Outfit', 'Inter', sans-serif" }}>
          {title}
        </h2>
        <p className="text-gray-500 text-sm max-w-xs">
          This module is coming soon. We're building something amazing.
        </p>
        <button
          onClick={() => onViewChange({ view: "Dashboard" })}
          className="mt-4 px-5 py-2 rounded-lg bg-[#8b5cf6]/10 border border-[#8b5cf6]/20 text-[#c084fc] text-sm font-medium hover:bg-[#8b5cf6]/20 transition-all cursor-pointer"
        >
          ← Back to Dashboard
        </button>
      </div>
    </div>
  );
}

// ── Main App ───────────────────────────────────────────────
export default function App() {
  const [activeView, setActiveView] = useState<ViewState>({ view: "Dashboard" });
  const [badges, setBadges] = useState<SidebarBadges>({});

  // ── Browser History Sync ─────────────────────────────────
  useEffect(() => {
    const handlePopState = (e: PopStateEvent) => {
      if (e.state && e.state.view) {
        setActiveView(e.state);
      }
    };
    window.addEventListener("popstate", handlePopState);
    
    // Read initial hash to determine starting view
    if (!window.history.state) {
      const hash = window.location.hash.toLowerCase();
      let initialView = "Dashboard"; // Default to ATS Dashboard
      
      if (hash.includes("attendance")) {
        initialView = "Attendance";
      } else if (hash.includes("approvals")) {
        initialView = "Timesheet Approvals";
      }
      
      window.history.replaceState({ view: initialView }, "", `#${initialView.replace(/\s+/g, '')}`);
      setActiveView({ view: initialView });
    } else if (window.history.state.view) {
      setActiveView(window.history.state);
    }
    
    return () => window.removeEventListener("popstate", handlePopState);
  }, []);

  useEffect(() => {
    if (window.history.state?.view !== activeView.view ||
        window.history.state?.jobId !== activeView.jobId ||
        window.history.state?.candidateId !== activeView.candidateId ||
        window.history.state?.poolId !== activeView.poolId) {
        
        // Push state, changing hash for clarity in URL
        const hash = `#${activeView.view.replace(/\s+/g, '')}`;
        window.history.pushState(activeView, "", hash);
    }
  }, [activeView]);

  // Fetch badge counts on mount
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
        // Silently fail — badges are non-critical
      }
    };
    fetchBadges();
  }, []);

  // Helper to handle view changes from child components that pass a string
  const handleViewChange = (viewOrState: ViewState | string) => {
    if (typeof viewOrState === "string") {
      setActiveView({ view: viewOrState });
    } else {
      setActiveView(viewOrState);
    }
  };

  // ── Render active view ──────────────────────────────────
  const renderView = () => {
    const { view, jobId, candidateId, poolId } = activeView;

    switch (view) {
      case "Dashboard":
        return <ATSDashboard onViewChange={handleViewChange} />;
      case "Jobs":
        return <JobsPage onViewChange={handleViewChange} />;
      case "Pipeline":
        return <PipelineBoard onViewChange={handleViewChange} {...(jobId !== undefined ? { jobId } : {})} />;
      case "Interviews":
        return <InterviewsPage onViewChange={handleViewChange} />;
      case "Analytics":
        return <InsightsPage onViewChange={handleViewChange} />;
      case "Approvals":
        return <Approvals onViewChange={handleViewChange} />;
      case "Candidates":
        return <CandidatesList onViewChange={handleViewChange} />;
      case "Candidate Profile":
        return <CandidateProfile onViewChange={handleViewChange} candidateId={candidateId || 1} />;
      case "Talent Pools":
        return <TalentPools onViewChange={handleViewChange} />;
      case "Pool Detail":
        return <PoolDetail onViewChange={handleViewChange} poolId={poolId || 1} />;
      case "Talent Search":
        return <TalentSearch onViewChange={handleViewChange} />;
      case "Recruiting Copilot":
        return <RecruitingCopilot onViewChange={handleViewChange} />;
      case "Permissions":
        return <Permissions onViewChange={handleViewChange} />;
      case "Settings":
        return <Settings onViewChange={handleViewChange} />;
        
      // ── Attendance Modules ──
      case "Attendance":
        return <AttendanceDashboard />;
      case "Timesheet Approvals":
        return <ManagerApprovals />;

      default:
        return <PlaceholderPage title={view} onViewChange={handleViewChange} />;
    }
  };

  return (
    <div
      className="flex h-screen w-full overflow-hidden"
      style={{ backgroundColor: "#06070a" }}
    >
      <Sidebar
        activeView={activeView}
        onViewChange={setActiveView}
        badges={badges}
      />
      {renderView()}
    </div>
  );
}
