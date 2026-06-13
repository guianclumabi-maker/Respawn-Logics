import { useState } from "react";
import { Sidebar } from "./components/Sidebar";
import { PipelineBoard } from "./components/PipelineBoard";
import { ATSDashboard } from "./components/ATSDashboard";
import { JobsPage } from "./components/JobsPage";
import { InsightsPage } from "./components/InsightsPage";
import { AICompanion } from "./components/AICompanion";

export default function App() {
  const [activeView, setActiveView] = useState<"Dashboard" | "AICompanion" | "Cases" | "Incident Reports" | "Investigations" | "Tasks" | "Approvals" | "Daily Reports" | "Analytics" | "Case Types" | "Settings">("Dashboard");

  return (
    <div
      className="flex h-screen w-full overflow-hidden"
      style={{ backgroundColor: "#06070a" }}
    >
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
      
      {activeView === "Analytics" && (
        <InsightsPage onViewChange={setActiveView} />
      )}
    </div>
  );
}
