export type Priority = "critical" | "high" | "medium" | "low";
export type Status = "open" | "in_progress" | "waiting" | "resolved" | "closed";

export interface Ticket {
  id: string;
  title: string;
  description: string;
  status: Status;
  priority: Priority;
  category: string;
  assignee: { name: string; initials: string; color: string } | null;
  reporter: { name: string; initials: string; color: string };
  created: string;
  updated: string;
  messages: Message[];
  tags: string[];
}

export interface Message {
  id: string;
  author: { name: string; initials: string; color: string; role: "agent" | "user" };
  body: string;
  timestamp: string;
  internal?: boolean;
}

export const TICKETS: Ticket[] = [
  {
    id: "TKT-1041",
    title: "SSO login fails for SAML users after last deployment",
    description: "Since the deployment on June 9th, users authenticating via SAML SSO are getting a 401 error after the IdP handshake. OIDC users are unaffected. Impacts ~340 accounts in the enterprise tier.",
    status: "in_progress",
    priority: "critical",
    category: "Authentication",
    assignee: { name: "Priya Nair", initials: "PN", color: "#6366f1" },
    reporter: { name: "Dev Ops", initials: "DO", color: "#3b82f6" },
    created: "2026-06-09T14:22:00Z",
    updated: "2026-06-11T09:41:00Z",
    tags: ["SSO", "SAML", "regression"],
    messages: [
      {
        id: "m1",
        author: { name: "Dev Ops", initials: "DO", color: "#3b82f6", role: "user" },
        body: "This started right after the 2.14.0 release. The SAML assertion is being sent correctly from our IdP (Okta), but the callback returns 401. The error log shows: `InvalidSignatureException: assertion signature does not match expected`.",
        timestamp: "2026-06-09T14:22:00Z",
      },
      {
        id: "m2",
        author: { name: "Priya Nair", initials: "PN", color: "#6366f1", role: "agent" },
        body: "Thanks — I'm looking into this now. Pulling the 2.14.0 diff for the auth module. Can you share the raw SAML response from Okta (with any sensitive values redacted)?",
        timestamp: "2026-06-09T15:05:00Z",
      },
      {
        id: "m3",
        author: { name: "Priya Nair", initials: "PN", color: "#6366f1", role: "agent" },
        body: "Found it — a certificate rotation in the 2.14.0 deploy wasn't propagated to the SAML validation config. We have a fix ready and it's going through review now. ETA for hotfix: ~2 hours.",
        timestamp: "2026-06-11T09:41:00Z",
        internal: false,
      },
    ],
  },
  {
    id: "TKT-1038",
    title: "Export to CSV truncating rows beyond 1,000",
    description: "When exporting employee reports with more than 1,000 rows, the CSV download silently cuts off at row 1,000. No error is shown to the user.",
    status: "open",
    priority: "high",
    category: "Data Export",
    assignee: null,
    reporter: { name: "Yusuf Al-Amin", initials: "YA", color: "#ec4899" },
    created: "2026-06-10T08:14:00Z",
    updated: "2026-06-10T08:14:00Z",
    tags: ["CSV", "export", "pagination"],
    messages: [
      {
        id: "m1",
        author: { name: "Yusuf Al-Amin", initials: "YA", color: "#ec4899", role: "user" },
        body: "Reproducible on any report with >1000 employees. We have 2,400 employees and the exported file always has exactly 1,000 rows. Tested on Chrome and Firefox.",
        timestamp: "2026-06-10T08:14:00Z",
      },
    ],
  },
  {
    id: "TKT-1035",
    title: "Bulk email notifications not sending after org migration",
    description: "After migrating from Workspace A to Workspace B, automated email triggers (onboarding, policy updates) have stopped sending entirely.",
    status: "waiting",
    priority: "high",
    category: "Notifications",
    assignee: { name: "Sam Okafor", initials: "SO", color: "#10b981" },
    reporter: { name: "Rachel Kim", initials: "RK", color: "#f59e0b" },
    created: "2026-06-08T11:30:00Z",
    updated: "2026-06-10T16:20:00Z",
    tags: ["email", "migration", "SMTP"],
    messages: [
      {
        id: "m1",
        author: { name: "Rachel Kim", initials: "RK", color: "#f59e0b", role: "user" },
        body: "The migration completed on June 7. Since then, zero automated emails are going out. Manual sends from the admin panel work fine.",
        timestamp: "2026-06-08T11:30:00Z",
      },
      {
        id: "m2",
        author: { name: "Sam Okafor", initials: "SO", color: "#10b981", role: "agent" },
        body: "The SMTP credentials in your new workspace weren't migrated — they need to be re-entered. Can you go to Settings → Integrations → Email and re-save your SMTP config? I'll wait for confirmation before closing.",
        timestamp: "2026-06-10T16:20:00Z",
      },
    ],
  },
  {
    id: "TKT-1029",
    title: "Dashboard load time degraded — P95 at 8.4s",
    description: "The main dashboard is loading significantly slower than our 2s SLA. Monitoring shows P95 latency spiked on June 5th and hasn't recovered.",
    status: "in_progress",
    priority: "medium",
    category: "Performance",
    assignee: { name: "Priya Nair", initials: "PN", color: "#6366f1" },
    reporter: { name: "Site Reliability", initials: "SR", color: "#8899b4" },
    created: "2026-06-05T17:00:00Z",
    updated: "2026-06-09T12:00:00Z",
    tags: ["performance", "latency", "dashboard"],
    messages: [
      {
        id: "m1",
        author: { name: "Site Reliability", initials: "SR", color: "#8899b4", role: "user" },
        body: "Correlated with a DB index change in commit a7f3c9e. Rollback is possible but would revert a needed fix.",
        timestamp: "2026-06-05T17:00:00Z",
      },
    ],
  },
  {
    id: "TKT-1022",
    title: "Add SCIM provisioning support for Okta",
    description: "Request to add SCIM 2.0 support to automate user provisioning and deprovisioning via Okta. Currently all user management is manual.",
    status: "open",
    priority: "medium",
    category: "Feature Request",
    assignee: null,
    reporter: { name: "IT Admin", initials: "IA", color: "#3b82f6" },
    created: "2026-06-01T09:00:00Z",
    updated: "2026-06-01T09:00:00Z",
    tags: ["SCIM", "Okta", "provisioning", "feature"],
    messages: [
      {
        id: "m1",
        author: { name: "IT Admin", initials: "IA", color: "#3b82f6", role: "user" },
        body: "We manage 900+ employees and onboarding/offboarding is entirely manual. SCIM would save ~5 hours/week of IT time.",
        timestamp: "2026-06-01T09:00:00Z",
      },
    ],
  },
  {
    id: "TKT-1018",
    title: "Two-factor authentication reset broken for mobile users",
    description: "Users on iOS attempting to reset 2FA receive no SMS code. The reset flow shows 'Code sent' but nothing arrives.",
    status: "resolved",
    priority: "high",
    category: "Authentication",
    assignee: { name: "Sam Okafor", initials: "SO", color: "#10b981" },
    reporter: { name: "Support Queue", initials: "SQ", color: "#8899b4" },
    created: "2026-05-29T10:00:00Z",
    updated: "2026-06-03T14:00:00Z",
    tags: ["2FA", "SMS", "iOS"],
    messages: [
      {
        id: "m1",
        author: { name: "Support Queue", initials: "SQ", color: "#8899b4", role: "user" },
        body: "Reported by 12 separate users. All on iOS. Android users are unaffected.",
        timestamp: "2026-05-29T10:00:00Z",
      },
      {
        id: "m2",
        author: { name: "Sam Okafor", initials: "SO", color: "#10b981", role: "agent" },
        body: "Identified a carrier filtering issue with our SMS provider for numbers on certain US carriers. Switched to a fallback provider for SMS. Issue is resolved — deployed in 2.13.4.",
        timestamp: "2026-06-03T14:00:00Z",
      },
    ],
  },
  {
    id: "TKT-1011",
    title: "Payroll integration sync fails on weekends",
    description: "The nightly sync with ADP fails every Saturday and Sunday with a timeout. Weekday syncs complete successfully.",
    status: "closed",
    priority: "low",
    category: "Integrations",
    assignee: { name: "Priya Nair", initials: "PN", color: "#6366f1" },
    reporter: { name: "Finance Ops", initials: "FO", color: "#f59e0b" },
    created: "2026-05-20T08:00:00Z",
    updated: "2026-05-25T11:00:00Z",
    tags: ["ADP", "payroll", "cron"],
    messages: [
      {
        id: "m1",
        author: { name: "Finance Ops", initials: "FO", color: "#f59e0b", role: "user" },
        body: "Has been happening for 3 weeks. No weekend payroll data means manual reconciliation every Monday morning.",
        timestamp: "2026-05-20T08:00:00Z",
      },
      {
        id: "m2",
        author: { name: "Priya Nair", initials: "PN", color: "#6366f1", role: "agent" },
        body: "ADP's sandbox environment has reduced rate limits on weekends. Updated our sync job to use exponential backoff + retry. Fixed in 2.13.2.",
        timestamp: "2026-05-25T11:00:00Z",
      },
    ],
  },
];

export const STATUS_META: Record<Status, { label: string; color: string; bg: string; border: string }> = {
  open:        { label: "Open",        color: "#3b82f6", bg: "rgba(59,130,246,0.12)",  border: "rgba(59,130,246,0.25)" },
  in_progress: { label: "In Progress", color: "#f59e0b", bg: "rgba(245,158,11,0.12)",  border: "rgba(245,158,11,0.25)" },
  waiting:     { label: "Waiting",     color: "#8b5cf6", bg: "rgba(139,92,246,0.12)",  border: "rgba(139,92,246,0.25)" },
  resolved:    { label: "Resolved",    color: "#10b981", bg: "rgba(16,185,129,0.12)",  border: "rgba(16,185,129,0.25)" },
  closed:      { label: "Closed",      color: "#4b5a6e", bg: "rgba(75,90,110,0.12)",   border: "rgba(75,90,110,0.25)" },
};

export const PRIORITY_META: Record<Priority, { label: string; color: string; dot: string }> = {
  critical: { label: "Critical", color: "#ef4444", dot: "#ef4444" },
  high:     { label: "High",     color: "#f97316", dot: "#f97316" },
  medium:   { label: "Medium",   color: "#f59e0b", dot: "#f59e0b" },
  low:      { label: "Low",      color: "#8899b4", dot: "#8899b4" },
};
