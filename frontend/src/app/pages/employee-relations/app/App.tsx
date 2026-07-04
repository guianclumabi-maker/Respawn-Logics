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

export default function App({ mode = "admin" }: { mode?: "employee" | "admin" }) {
  const [activeView, setActiveView] = useState<string>(mode === "employee" ? "Cases" : "Dashboard");
  const [selectedCaseId, setSelectedCaseId] = useState<number | null>(null);

  const handleViewChange = (v: string) => {
    setActiveView(v);
    setSelectedCaseId(null);
  };

  return (
    <div className="flex h-full w-full bg-[#06070a] text-[#c8d0e0] overflow-hidden relative">
      {/* ELR Left-Nav Sidebar */}
      <Sidebar mode={mode} activeView={activeView} onViewChange={handleViewChange} />

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
      </div>
    </div>
  );
}
