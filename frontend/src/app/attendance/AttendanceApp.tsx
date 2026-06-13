import { useState, useEffect } from "react";
import { Sidebar } from "../components/Sidebar";
import type { ViewState } from "../components/Sidebar";

import { AttendanceDashboard } from "./AttendanceDashboard";
import { ManagerApprovals } from "./ManagerApprovals";

export default function AttendanceApp() {
  const [activeView, setActiveView] = useState<ViewState>({ view: "Attendance" });

  useEffect(() => {
    const handlePopState = (e: PopStateEvent) => {
      if (e.state && e.state.view) {
        setActiveView(e.state);
      }
    };
    window.addEventListener("popstate", handlePopState);
    
    if (!window.history.state) {
      window.history.replaceState({ view: "Attendance" }, "", "#Attendance");
    } else if (window.history.state.view) {
      setActiveView(window.history.state);
    }
    
    return () => window.removeEventListener("popstate", handlePopState);
  }, []);

  useEffect(() => {
    if (window.history.state?.view !== activeView.view) {
        const hash = `#${activeView.view.replace(/\s+/g, '')}`;
        window.history.pushState(activeView, "", hash);
    }
  }, [activeView]);

  const renderView = () => {
    switch (activeView.view) {
      case "Attendance":
        return <AttendanceDashboard />;
      case "Timesheet Approvals":
        return <ManagerApprovals />;
      default:
        return <AttendanceDashboard />;
    }
  };

  return (
    <div className="flex h-screen w-full overflow-hidden" style={{ backgroundColor: "#06070a" }}>
      {/* For now, reuse the ATS sidebar since it handles permissions, or we could pass a custom config to Sidebar. 
          To keep it simple and unified, we render the same global Sidebar and let the user navigate seamlessly! */}
      <Sidebar
        activeView={activeView}
        onViewChange={setActiveView}
      />
      {renderView()}
    </div>
  );
}
