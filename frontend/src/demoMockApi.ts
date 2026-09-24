export function getMockResponse(url: string): any | null {
  // ELR
  if (url.includes("route=elr&action=cases")) {
    return {
      success: true,
      data: [
        { id: 101, employee_name: "Sarah Chen", department: "HR", issue_category: "Grievance", status: "Investigation", priority: "High", created_at: "2026-07-10 09:00:00" },
        { id: 102, employee_name: "David Kim", department: "Operations", issue_category: "Policy Violation", status: "Open", priority: "Medium", created_at: "2026-07-11 14:30:00" },
        { id: 103, employee_name: "Maria Reyes", department: "Finance", issue_category: "Dispute", status: "Resolved", priority: "Low", created_at: "2026-07-01 10:15:00" }
      ]
    };
  }

  // Performance
  if (url.includes("route=performance&action=get_team_reviews")) {
    return {
      success: true,
      data: [
        { id: 1, employee_name: "Sarah Chen", job_title: "HR Manager", cycle_name: "Q2 2026 Review", status: "completed", score: "4.5 / 5.0", actions: "View" },
        { id: 2, employee_name: "David Kim", job_title: "Operations Lead", cycle_name: "Q2 2026 Review", status: "pending", score: "Pending", actions: "Remind" },
        { id: 3, employee_name: "Maria Reyes", job_title: "Finance Analyst", cycle_name: "Q2 2026 Review", status: "in_progress", score: "Draft", actions: "Continue" }
      ]
    };
  }

  // Payroll Runs
  if (url.includes("route=payroll_engine&action=runs")) {
    return {
      success: true,
      data: [
        { id: 10, run_name: "July 2026 Cycle 1", period_start: "2026-07-01", period_end: "2026-07-15", status: "Draft", type: "Regular", created_at: "2026-07-11" },
        { id: 9, run_name: "June 2026 Cycle 2", period_start: "2026-06-16", period_end: "2026-06-30", status: "Completed", type: "Regular", created_at: "2026-06-25" }
      ]
    };
  }
  
  if (url.includes("route=payroll_engine&action=dashboard_kpis")) {
    return {
      success: true,
      data: { nextDate: 'Jul 15, 2026', estimatedCost: 2847500, costIncrease: 2.4, readiness: '85%', activeRunName: 'July 2026 Cycle 1', activeRunTotalEmployees: 142, activeRunProcessed: 120 }
    };
  }

  // ATS
  if (url.includes("route=candidates&action=pipeline")) {
    return {
      success: true,
      data: {
        "Sourced": [{ id: 1, name: "Elena Rodriguez", role: "Senior Frontend Engineer" }],
        "Applied": [{ id: 2, name: "James Wilson", role: "Product Manager" }],
        "Interview": [{ id: 3, name: "Anita Patel", role: "DevOps Engineer" }],
        "Offer": [{ id: 4, name: "Marcus Johnson", role: "Data Scientist" }]
      }
    };
  }
  
  if (url.includes("route=candidates&action=jobs")) {
    return {
      success: true,
      data: [
        { id: 1, title: "Senior Frontend Engineer", department: "Engineering", location: "Manila (Hybrid)", status: "Active", applicants: 12 },
        { id: 2, title: "Product Manager", department: "Product", location: "Remote", status: "Active", applicants: 45 },
        { id: 3, title: "HR Business Partner", department: "HR", location: "Cebu", status: "Draft", applicants: 0 }
      ]
    };
  }

  // IAM / HR Directory
  if (url.includes("route=iam&action=users")) {
    return {
      success: true,
      data: [
        { id: 999, name: "Peter Parker", email: "demo@respawn.logics", roles: "Super_Admin", is_active: 1, created_at: "2026-01-01" },
        { id: 1, name: "Sarah Chen", email: "sarah@respawn.logics", roles: "HR_Admin", is_active: 1, created_at: "2026-01-15" },
        { id: 2, name: "David Kim", email: "david@respawn.logics", roles: "Employee", is_active: 1, created_at: "2026-02-01" }
      ]
    };
  }
  
  // Dashboard Action Summary (badges)
  if (url.includes("action=dashboard")) {
    return {
      success: true,
      action_summary: { awaiting_review: 3, interviews_today: 2, pending_approvals: 5 }
    };
  }

  // Dashboard Stats & Tasks (HomeDashboard)
  if (url.includes("action=get_stats") || url.includes("route=dashboard")) {
    const isEmployee = url.includes("employee") || window.location.href.includes("employee");
    const isManager = url.includes("manager") || window.location.href.includes("manager");

    if (isEmployee) {
      return {
        success: true,
        data: {
          clocked_in_today: true,
          clock_time: "09:02:00 AM",
          total_hours: 38.5,
          pending_leaves: 1,
          active_tasks_count: 2,
          todo_list: [
            { id: 1, task_name: "Submit medical certificate for sick leave", task_description: "HR health compliance", is_completed: 0 },
            { id: 2, task_name: "Review latest July 2026 payslip breakdown", task_description: "ESS verification", is_completed: 0 },
            { id: 3, task_name: "Biometric clock-in verification", task_description: "Attendance logging", is_completed: 1 }
          ]
        }
      };
    }

    if (isManager) {
      return {
        success: true,
        data: {
          clocked_in_today: true,
          clock_time: "08:30:00 AM",
          total_hours: 44.0,
          pending_leaves: 3,
          active_tasks_count: 3,
          todo_list: [
            { id: 1, task_name: "Approve David Kim's Vacation Leave (3 days)", task_description: "Engineering team roster", is_completed: 0 },
            { id: 2, task_name: "Sign off Alex Mercer's overtime submission", task_description: "Project sprint delivery", is_completed: 0 },
            { id: 3, task_name: "Verify team weekly attendance radar", task_description: "8 direct reports", is_completed: 1 }
          ]
        }
      };
    }

    // Default: Super Admin
    return {
      success: true,
      data: {
        clocked_in_today: true,
        clock_time: "08:15:00 AM",
        total_hours: 42.5,
        pending_leaves: 2,
        active_tasks_count: 2,
        todo_list: [
          { id: 1, task_name: "Approve July 2026 Payroll Run", task_description: "Statutory remittance & bank export", is_completed: 0 },
          { id: 2, task_name: "Finalize ATS Pipeline for Senior Engineer", task_description: "4 candidates in review", is_completed: 0 },
          { id: 3, task_name: "Sign off BIR Form 1601-C Remittance", task_description: "Compliance verified", is_completed: 1 }
        ]
      }
    };
  }

  if (url.includes("action=toggle_task") || url.includes("action=add_task")) {
    return { success: true };
  }

  return null;
}
