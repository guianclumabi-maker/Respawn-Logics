import { useState } from "react";
import { Sidebar } from "./components/Sidebar";
import { ELRDashboard } from "./components/ELRDashboard";
import { ELRCasesList } from "./components/ELRCasesList";
import { CaseDetail } from "./components/CaseDetail";
import { ELRAnalytics } from "./components/ELRAnalytics";
import { AICompanion } from "./components/AICompanion";
import { KnowledgeAdmin } from "../../KnowledgeAdmin";
import { AttendanceReport } from "./components/AttendanceReport";
import { ELRTemplates } from "./components/ELRTemplates";
import { ELRPipelines } from "./components/ELRPipelines";
import { ELRPipelineBoard } from "./components/ELRPipelineBoard";
import { ELRAutomation } from "./components/ELRAutomation";
import { ELRDailyReport } from "./components/ELRDailyReport";
import { ELRApprovals } from "./components/ELRApprovals";
import { useTour } from "../../../lib/useTour";

// First-run guided tour of the ELR Admin Console. Walks the key sidebar workflow:
// overview -> cases -> pipeline board -> templates -> automation -> approvals.
// Steps target the sidebar nav ids (#tour-elr-nav-<viewName>). Admin only.
const elrAdminTourSteps = [
  {
    element: "#tour-elr-nav-Dashboard",
    popover: {
      title: "ELR Admin Console",
      description: "This is your command center for employee-relations cases — like an applicant tracker, but for disciplinary and due-process workflows.",
      side: "right",
      align: "start",
    },
  },
  {
    element: "#tour-elr-nav-Cases",
    popover: {
      title: "Cases",
      description: "Every employee-relations matter lives here as a case card, with its full timeline and generated documents.",
      side: "right",
      align: "start",
    },
  },
  {
    element: "#tour-elr-nav-PipelineBoard",
    popover: {
      title: "Pipeline Board",
      description: "Drag cases through stages (e.g. AWOL → Return-to-Work → NTE → Decision). Moving a card auto-generates that stage's document — your due-process audit trail.",
      side: "right",
      align: "start",
    },
  },
  {
    element: "#tour-elr-nav-Templates",
    popover: {
      title: "Templates",
      description: "Define the document templates (with {{merge_fields}}) that stages generate automatically. Set these up once and every case reuses them.",
      side: "right",
      align: "start",
    },
  },
  {
    element: "#tour-elr-nav-Automation",
    popover: {
      title: "Automation",
      description: "Rules that scan attendance and flag issues (AWOL, tardiness) automatically, so cases open themselves instead of waiting on manual review.",
      side: "right",
      align: "start",
    },
  },
  {
    element: "#tour-elr-nav-Approvals",
    popover: {
      title: "Approvals",
      description: "Where generated documents wait for sign-off before they're issued — the human checkpoint that keeps the process defensible.",
      side: "right",
      align: "start",
    },
  },
];

export default function App({ mode = "admin" }: { mode?: "employee" | "admin" }) {
  const [activeView, setActiveView] = useState<string>(mode === "employee" ? "Cases" : "Dashboard");
  const [selectedCaseId, setSelectedCaseId] = useState<number | null>(null);

  // Admin-only guided tour (employees get the simpler two-item nav, no tour).
  const { startTour: startElrTour } = useTour("elr_admin", elrAdminTourSteps, {
    enabled: mode === "admin",
  });

  const handleViewChange = (v: string) => {
    setActiveView(v);
    setSelectedCaseId(null);
  };

  return (
    <div className="flex h-full w-full bg-background text-foreground overflow-hidden relative">
      {/* ELR Left-Nav Sidebar */}
      <Sidebar mode={mode} activeView={activeView} onViewChange={handleViewChange} onStartTour={mode === "admin" ? startElrTour : undefined} />

      {/* Main Content Pane */}
      <div className="flex-1 flex flex-col h-full overflow-hidden relative">
        {/* Global Background Glow Effects */}
        <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-[#00e07a] blur-[120px] opacity-[0.04] pointer-events-none z-[-1]" />
        <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#9b6dff] blur-[140px] opacity-[0.03] pointer-events-none z-[-1]" />

        {activeView === "Dashboard" && (
          <ELRDashboard onViewChange={handleViewChange} />
        )}
        
        {activeView === "Cases" && !selectedCaseId && (
          <ELRCasesList mine={mode === "employee"} onViewChange={handleViewChange} onSelectCase={setSelectedCaseId} />
        )}

        {activeView === "Cases" && selectedCaseId !== null && (
          <CaseDetail caseId={selectedCaseId} onBack={() => setSelectedCaseId(null)} />
        )}

        {activeView === "AICompanion" && (
          <AICompanion />
        )}

        {activeView === "Knowledge Base" && (
          <KnowledgeAdmin />
        )}

        {activeView === "Attendance" && (
          <AttendanceReport />
        )}

        {activeView === "Analytics" && (
          <ELRAnalytics />
        )}

        {activeView === "Templates" && (
          <ELRTemplates />
        )}

        {activeView === "Pipelines" && (
          <ELRPipelines />
        )}

        {activeView === "PipelineBoard" && (
          <ELRPipelineBoard />
        )}

        {activeView === "Automation" && (
          <ELRAutomation />
        )}

        {activeView === "DailyReport" && (
          <ELRDailyReport />
        )}

        {activeView === "Approvals" && (
          <ELRApprovals />
        )}
      </div>
    </div>
  );
}
