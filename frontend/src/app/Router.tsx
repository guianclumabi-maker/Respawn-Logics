import { createHashRouter, Navigate, useNavigate, useParams, useSearchParams } from "react-router-dom";
import MainLayout from "./layouts/MainLayout";
import { viewStateToPath } from "./lib/atsNav";
import { LoginPage } from "./pages/LoginPage";
import { useAuth } from "./context/AuthContext";

// AuthGuard — redirects to /login if no session
function AuthGuard({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  if (loading) return null; // wait for session check
  if (!user) return <Navigate to="/login" replace />;
  return <>{children}</>;
}

import React from "react";

// Apps & Fully Ported
import { HomeDashboard } from "./pages/HomeDashboard";
import { EmployeeRelations } from "./pages/EmployeeRelations";
import { OnboardingManager } from "./pages/OnboardingManager";
import { HRDirectory } from "./pages/HRDirectory";
import { LeavesDashboard } from "./pages/LeavesDashboard";
import { PayrollManager } from "./pages/PayrollManager";
import { ServiceDesk } from "./pages/ServiceDesk";
import { SetupModeCards } from "./pages/SetupModeCards";

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
import { CandidateProfile } from "./components/CandidateProfile";
import { PoolDetail } from "./components/PoolDetail";

// Shell Components
import { AttendanceModule } from "./pages/AttendanceModule";
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
import { OrgUnits } from "./pages/OrgUnits";

function AtsRoute({ component: Component }: { component: any }) {
  const navigate = useNavigate();
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  
  const jobIdStr = searchParams.get("jobId");
  const jobId = jobIdStr ? parseInt(jobIdStr, 10) : undefined;
  const paramId = id ? parseInt(id, 10) : undefined;

  return (
    <Component
      onViewChange={(v: any) => navigate(viewStateToPath(v))}
      jobId={jobId}
      candidateId={paramId}
      poolId={paramId}
    />
  );
}

export const router = createHashRouter([
  { path: "/login", element: <LoginPage /> },
  {
    path: "/",
    element: <AuthGuard><MainLayout /></AuthGuard>,
    children: [
      { index: true, element: <Navigate to="/dashboard" replace /> },
      { path: "dashboard", element: <HomeDashboard /> },
      { path: "employee-relations", element: <EmployeeRelations /> },
      { path: "hr-directory", element: <HRDirectory /> },
      { path: "org-chart", element: <OrgChart /> },
      { path: "leaves", element: <LeavesDashboard /> },
      { path: "attendance", element: <AttendanceModule /> },
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
          { index: true, element: <AtsRoute component={ATSDashboard} /> },
          { path: "pipeline", element: <AtsRoute component={PipelineBoard} /> },
          { path: "jobs", element: <AtsRoute component={JobsPage} /> },
          { path: "candidates", element: <AtsRoute component={CandidatesList} /> },
          { path: "candidates/:id", element: <AtsRoute component={CandidateProfile} /> },
          { path: "interviews", element: <AtsRoute component={InterviewsPage} /> },
          { path: "approvals", element: <AtsRoute component={Approvals} /> },
          { path: "pools", element: <AtsRoute component={TalentPools} /> },
          { path: "pools/:id", element: <AtsRoute component={PoolDetail} /> },
          { path: "search", element: <AtsRoute component={TalentSearch} /> },
          { path: "copilot", element: <AtsRoute component={RecruitingCopilot} /> },
          { path: "insights", element: <AtsRoute component={InsightsPage} /> }
        ]
      },
      { path: "analytics", element: <Analytics /> },
      { path: "ai-companion", element: <AICompanion /> },
      {
        path: "admin",
        children: [
          { path: "users", element: <AdminUsers /> },
          { path: "roles", element: <AdminRoles /> },
          { path: "org-units", element: <OrgUnits /> },
          { path: "settings", element: <TenantSettings /> },
          { path: "audit", element: <AuditLogs /> },
        ]
      },
      { path: "onboarding", element: <OnboardingManager /> },
      { path: "*", element: <Navigate to="/dashboard" replace /> }
    ]
  },
  { path: "/setup", element: <SetupModeCards /> }
]);
