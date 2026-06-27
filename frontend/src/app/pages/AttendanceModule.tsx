import { useState } from "react";
import { useAuth } from "../context/AuthContext";
import { AttendanceDashboard } from "../attendance/AttendanceDashboard";
import { ManagerApprovals } from "../attendance/ManagerApprovals";

export function AttendanceModule() {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState("my_logs");

  const isManager = user?.permissions?.includes("attendance.manage") || user?.roles?.includes("Super_Admin");

  return (
    <div className="flex flex-col h-full w-full bg-[#06070a]">
      {isManager && (
        <div className="px-8 pt-8 flex gap-4 border-b border-white/5">
          <button 
            onClick={() => setActiveTab("my_logs")}
            className={`pb-3 px-1 text-sm font-medium transition-colors ${activeTab === "my_logs" ? "text-white border-b-2 border-[#8b5cf6]" : "text-gray-500 hover:text-gray-300"}`}
          >
            My Logs
          </button>
          <button 
            onClick={() => setActiveTab("manager_approvals")}
            className={`pb-3 px-1 text-sm font-medium transition-colors flex items-center gap-2 ${activeTab === "manager_approvals" ? "text-white border-b-2 border-[#8b5cf6]" : "text-gray-500 hover:text-gray-300"}`}
          >
            Manager Approvals
          </button>
        </div>
      )}

      {/* Content wrapper handles its own scrolling inside the components, 
          so we just render the active one filling the remaining space */}
      {activeTab === "my_logs" ? <AttendanceDashboard /> : <ManagerApprovals />}
    </div>
  );
}
