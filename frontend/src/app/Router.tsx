import { createBrowserRouter } from "react-router-dom";
import MainLayout from "./layouts/MainLayout";
import { ATSDashboard } from "./components/ATSDashboard";
import { PipelineBoard } from "./components/PipelineBoard";
import { JobsPage } from "./components/JobsPage";
import { CandidatesList } from "./components/CandidatesList";

// Placeholder for unmigrated modules
function Placeholder() {
  return <div className="p-8 text-white w-full">This module is being migrated...</div>;
}

export const router = createBrowserRouter([
  {
    path: "/",
    element: <MainLayout />,
    children: [
      {
        path: "dashboard",
        element: <Placeholder />,
      },
      {
        path: "ats",
        children: [
          { index: true, element: <ATSDashboard onViewChange={() => {}} /> },
          { path: "pipeline", element: <PipelineBoard onViewChange={() => {}} /> },
          { path: "jobs", element: <JobsPage onViewChange={() => {}} /> },
          { path: "candidates", element: <CandidatesList onViewChange={() => {}} /> }
        ]
      },
      {
        path: "hr-directory",
        element: <Placeholder />,
      },
      {
        path: "payroll",
        element: <Placeholder />,
      },
      {
        path: "leaves",
        element: <Placeholder />,
      },
      {
        path: "esm",
        element: <Placeholder />,
      },
      {
        path: "employee-relations",
        element: <Placeholder />,
      }
    ]
  }
]);
