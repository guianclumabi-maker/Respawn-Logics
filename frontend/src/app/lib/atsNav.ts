import type { ViewState } from "../components/Sidebar";

export function viewStateToPath(viewState: ViewState): string {
  switch (viewState.view) {
    // Apps
    case "Dashboard": return "/dashboard";
    case "Employee Relations": return "/employee-relations";
    case "ELR Admin Console": return "/employee-relations";
    case "My HR Cases": return "/employee-relations";
    case "Onboarding": return "/onboarding";
    
    // Core HR
    case "HR Directory": return "/hr-directory";
    case "Org Chart": return "/org-chart";
    case "Leaves": return "/leaves";
    case "Attendance": return "/attendance";
    case "Scheduling": return "/scheduling";
    
    // Finance & Services
    case "Payroll Engine": return "/payroll";
    case "Benefits": return "/benefits";
    case "Compensation": return "/compensation";
    case "Expenses": return "/expenses";
    case "IT / HR Service Desk": return "/service-desk";
    
    // Talent
    case "Performance": return "/performance";
    case "Knowledge Base": return "/knowledge";
    case "Surveys": return "/surveys";
    
    // ATS Parameter Routes
    case "Pipeline": 
      return viewState.jobId ? `/ats/pipeline?jobId=${viewState.jobId}` : "/ats/pipeline";
    case "Candidate Profile":
      return viewState.candidateId ? `/ats/candidates/${viewState.candidateId}` : "/ats/candidates";
    case "Pool Detail":
      return viewState.poolId ? `/ats/pools/${viewState.poolId}` : "/ats/pools";

    // ATS
    case "ATS Dashboard": return "/ats";
    case "Jobs": return "/ats/jobs";
    case "Candidates": return "/ats/candidates";
    case "Interviews": return "/ats/interviews";
    case "Approvals": return "/ats/approvals";
    case "Talent Pools": return "/ats/pools";
    case "Talent Search": return "/ats/search";
    case "Recruiting Copilot": return "/ats/copilot";
    case "Insights": return "/ats/insights";
    
    // Analytics & System
    case "AI Companion": return "/ai-companion";
    case "Analytics": return "/analytics";
    case "Admin Users": return "/admin/users";
    case "Admin Roles": return "/admin/roles";
    case "Org Units": return "/admin/org-units";
    case "Tenant Settings": return "/admin/settings";
    case "Audit Logs": return "/admin/audit";
    
    default: return "/dashboard"; // fallback
  }
}
