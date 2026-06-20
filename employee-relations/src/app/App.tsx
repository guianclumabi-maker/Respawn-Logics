import { useState } from "react";
import { Sidebar } from "./components/Sidebar";
import { PipelineBoard } from "./components/PipelineBoard";
import { ATSDashboard } from "./components/ATSDashboard";
import { JobsPage } from "./components/JobsPage";
import { InsightsPage } from "./components/InsightsPage";
import { AICompanion } from "./components/AICompanion";
import { TasksPipeline } from "./components/TasksPipeline";
import { Approvals } from "./components/Approvals";
import { DailyReports } from "./components/DailyReports";

export default function App() {
  const [activeView, setActiveView] = useState<"Dashboard" | "AICompanion" | "Cases" | "Incident Reports" | "Investigations" | "Tasks" | "Approvals" | "Daily Reports" | "Analytics" | "Case Types" | "Settings">("Dashboard");

  return (
    <div
      className="flex h-screen w-full overflow-hidden bg-slate-100 dark:bg-[#06070a] relative z-0"
    >
      {/* Global Background Glow Effects */}
      <div className="absolute top-[-100px] left-[-100px] w-[500px] h-[500px] rounded-full bg-[#00e07a] blur-[120px] opacity-[0.05] dark:opacity-[0.06] pointer-events-none z-[-1]" />
      <div className="absolute bottom-[-150px] right-[-100px] w-[600px] h-[600px] rounded-full bg-[#9b6dff] blur-[140px] opacity-[0.03] dark:opacity-[0.05] pointer-events-none z-[-1]" />
      <Sidebar activeView={activeView} onViewChange={setActiveView} />
      
      {activeView === "Dashboard" && (
        <ATSDashboard onViewChange={setActiveView} />
      )}
      
      {activeView === "AICompanion" && (
        <AICompanion />
      )}
      
      {activeView === "Cases" && (
        <PipelineBoard onViewChange={setActiveView} />
      )}
      
      {activeView === "Case Types" && (
        <JobsPage onViewChange={setActiveView} />
      )}
      
      {activeView === "Tasks" && (
        <TasksPipeline onViewChange={setActiveView} />
      )}

      {activeView === "Approvals" && (
        <Approvals onViewChange={setActiveView} />
      )}

      {activeView === "Daily Reports" && (
        <DailyReports />
      )}

      {activeView === "Analytics" && (
        <InsightsPage onViewChange={setActiveView} />
      )}
    </div>
  );
}
