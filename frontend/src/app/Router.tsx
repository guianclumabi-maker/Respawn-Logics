import { createBrowserRouter } from "react-router-dom";
import MainLayout from "./layouts/MainLayout";

// Apps & Fully Ported
import { HomeDashboard } from "./pages/HomeDashboard";
import { EmployeeRelations } from "./pages/EmployeeRelations";
import { OnboardingManager } from "./pages/OnboardingManager";
import { HRDirectory } from "./pages/HRDirectory";
import { LeavesDashboard } from "./pages/LeavesDashboard";
import { PayrollManager } from "./pages/PayrollManager";
import { ServiceDesk } from "./pages/ServiceDesk";

// ATS Components
import { ATSDashboard } from "./components/ATSDashboard";
import { PipelineBoard } from "./components/PipelineBoard";
import { JobsPage } from "./components/JobsPage";
import { CandidatesList } from "./components/CandidatesList";
import { InterviewsPage } from "./components/InterviewsPage";
import { Approvals } from "./components/Approvals";
import { TalentPools } from "./components/TalentPools";
import { TalentSearch } from "./components/TalentSearch";
import { RecruitingCopilot } from "./components/RecruitingCopilot";
import { InsightsPage } from "./components/InsightsPage";

// Shell Components
import { AttendanceDashboard } from "./pages/AttendanceDashboard";
import { Scheduling } from "./pages/Scheduling";
import { BenefitsAdmin } from "./pages/BenefitsAdmin";
import { CompensationAdmin } from "./pages/CompensationAdmin";
import { ExpensesAdmin } from "./pages/ExpensesAdmin";
import { PerformanceAdmin } from "./pages/PerformanceAdmin";
import { KnowledgeAdmin } from "./pages/KnowledgeAdmin";
import { Surveys } from "./pages/Surveys";
import { OrgChart } from "./pages/OrgChart";
import { AdminUsers } from "./pages/AdminUsers";
import { AdminRoles } from "./pages/AdminRoles";
import { TenantSettings } from "./pages/TenantSettings";
import { AuditLogs } from "./pages/AuditLogs";
import { AICompanion } from "./pages/AICompanion";
import { Analytics } from "./pages/Analytics";

export const router = createBrowserRouter([
  {
    path: "/",
    element: <MainLayout />,
    children: [
      { path: "dashboard", element: <HomeDashboard /> },
      { path: "onboarding", element: <OnboardingManager /> },
      { path: "employee-relations", element: <EmployeeRelations /> },
      { path: "hr-directory", element: <HRDirectory /> },
      { path: "org-chart", element: <OrgChart /> },
      { path: "leaves", element: <LeavesDashboard /> },
      { path: "attendance", element: <AttendanceDashboard /> },
      { path: "scheduling", element: <Scheduling /> },
      { path: "payroll", element: <PayrollManager /> },
      { path: "benefits", element: <BenefitsAdmin /> },
      { path: "compensation", element: <CompensationAdmin /> },
      { path: "expenses", element: <ExpensesAdmin /> },
      { path: "service-desk", element: <ServiceDesk /> },
      { path: "performance", element: <PerformanceAdmin /> },
      { path: "knowledge", element: <KnowledgeAdmin /> },
      { path: "surveys", element: <Surveys /> },
      {
        path: "ats",
        children: [
          { index: true, element: <ATSDashboard onViewChange={() => {}} /> },
          { path: "pipeline", element: <PipelineBoard onViewChange={() => {}} /> },
          { path: "jobs", element: <JobsPage onViewChange={() => {}} /> },
          { path: "candidates", element: <CandidatesList onViewChange={() => {}} /> },
          { path: "interviews", element: <InterviewsPage onViewChange={() => {}} /> },
          { path: "approvals", element: <Approvals onViewChange={() => {}} /> },
          { path: "pools", element: <TalentPools onViewChange={() => {}} /> },
          { path: "search", element: <TalentSearch onViewChange={() => {}} /> },
          { path: "copilot", element: <RecruitingCopilot onViewChange={() => {}} /> },
          { path: "insights", element: <InsightsPage /> }
        ]
      },
      { path: "analytics", element: <Analytics /> },
      { path: "ai-companion", element: <AICompanion /> },
      {
        path: "admin",
        children: [
          { path: "users", element: <AdminUsers /> },
          { path: "roles", element: <AdminRoles /> },
          { path: "settings", element: <TenantSettings /> },
          { path: "audit", element: <AuditLogs /> },
        ]
      }
    ]
  }
]);
