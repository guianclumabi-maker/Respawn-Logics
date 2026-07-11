# Respawn Logics — Production Readiness Checklist

A prioritized plan to take the platform (ATS + HR modules) from "working beta" to
production-grade multi-tenant SaaS. Grounded in the code scan of `api/index.php`,
`backend/controllers/*`, `bootstrap/app.php`, and the React frontend.

Priority key:
- **P0** — Blockers. Security, data-loss, or tenant-isolation risks. Fix before onboarding paying tenants.
- **P1** — Reliability & quality. Needed for a dependable production service.
- **P2** — Cleanup & polish. Reduces long-term risk and maintenance cost.

---

## P0 — Blockers (security, tenancy, data integrity)

- [x] **Multi-tenant isolation audit across ALL 31 controllers.** Every SQL read/write must be
  scoped by the authenticated user's `tenant_id`. Confirmed gap: `CandidatesController::jobs()`
  departments query had no tenant filter. Audit every controller for the same pattern.
- [ ] **Never trust a client-supplied `tenant_id`.** Resolve tenant strictly from the session/user,
  never from `$_GET`/`$_POST`/`$input`. Audit result: only `PlatformSupportController` reads
  `$_GET['tenant_id']`, and it IS gated behind a platform-staff role check (403 guard) — acceptable.
  Keep this rule for any future controller; no other controller currently does this.
- [ ] **Fail closed on tenant resolution.** `CandidatesController` silently defaults the tenant to
  `'1'` when none resolves — a cross-tenant leak. Replace with a 403 when no tenant is resolvable,
  and apply the same pattern in every controller.
- [x] **Server-side authorization — verified closed (July 2026 audit, 31 controllers).**
  Authorization convention: controllers gate via local wrapper helpers (`requireManage()`,
  `isAgent()`, `hasRole()`, `isESMAgent()`, `canManagePerformance()`) rather than raw
  `requirePermission()`/`hasPermission()` calls everywhere — a grep for those strings alone
  will produce false negatives. Read the wrapper, not the call-site count.

  Verified status by controller:
  | Controller | Gate | Status |
  |---|---|---|
  | SaaSStaffController | `hasRole('Platform_Admin')` at top of `handleRequest()` | ✅ Closed |
  | EmployeeRelationsController | `elr.view` (reads) / `elr.investigate` (writes) + `is_super` | ✅ Fixed Jul 2026 |
  | ELRPipelineController | `requireManage()` → `elr.investigate` on all 17 mutating methods | ✅ Closed |
  | ESMSupportController | `isESMAgent()` → `hasRole(['Admin','HR','Super_Admin'])` on all agent actions | ✅ Closed |
  | ELRController | `requirePermission('elr.*')` per action | ✅ Closed |
  | CandidatesController | `requirePermission` + 200 tenant refs | ✅ Closed |
  | CoreHRController | 11 `hasPermission` gates | ✅ Closed |
  | PayrollController | per-action `canRun`/`canView`/`canApprove` + cross-tenant ownership | ✅ Closed |
  | IAMController | `hasPermission` per action | ✅ Closed |
  | PlatformSupportController | `isPlatformStaff()` + 25× 403 | ✅ Closed |
  | BenefitsController | `hasPermission('hr_*')` gates | ✅ Closed |
  | CompensationController | `hasPermission` + `is_super` bypass | ✅ Closed |
  | ExpensesController | `hasPermission('approve_claim')` | ✅ Closed |
  | ExportController | per-export `hasPermission` | ✅ Closed |
  | LeavesController | `hasPermission('approve_reject')` | ✅ Closed |
  | OnboardingController | `hasPermission` per action | ✅ Closed |
  | ShiftController | `hasPermission` per action | ✅ Closed |
  | SurveyController | `hasPermission` per action | ✅ Closed |
  | TimesheetController | `hasPermission('approve'/'delete')` | ✅ Closed |
  | AttendanceController | `hasPermission('approve_timesheet'/'import_punches')` | ✅ Closed |
  | AuditController | `hasPermission` on both actions | ✅ Closed |
  | AnnouncementsController | `hasPermission('create_post')` | ✅ Closed |
  | PerformanceController | `hasPermission('performance.manage')` / `canManagePerformance()` | ✅ Closed |
  | AnalyticsController | `hasPermission('analytics.view')` gates ALL actions incl. `payroll_trend` | ✅ Closed |
  | ESMController | `isAgent()` → `hasPermission('esm.manage')` on `agent_queue`/`update_ticket` | ✅ Closed |
  | DashboardController | No role gate on `get_stats` — **intentional**: all queries scoped by `employee_email = ? AND tenant_id = ?` (returns only the calling user's own data, no company-wide aggregates) | ✅ By design |
  | AICompanionController | No gate — any-authenticated by design; consider rate-limit for LLM cost | 🟡 Low risk |
  | NotificationController | No gate — scoped to current user by design | ✅ By design |
  | TourController | No gate — per-user by design | ✅ By design |
  | AuthController | No gate — login endpoint | ✅ By design |
  | HealthController | No gate — health check; 0 tenant refs is correct | ✅ By design |

  **Remaining open item:** `AICompanionController` has no rate-limit; an authenticated user can
  run unlimited LLM requests. Add a per-user/per-tenant rate-limit before production.
- [ ] **Remove debug/scratch files from the web root.** `scratch*.php`, `check_perms.php`,
  `check_tables.php`, `create_sandbox.php`, `test_user_perms.php`, and the one-off `*.js` style
  scripts are web-reachable under XAMPP/Railway and must be deleted or moved out of the served path.
- [ ] **Production config hygiene.** Set a correct `VITE_API_BASE_URL` in `.env.production` (currently
  empty), ensure `APP_DEBUG=false` in production, and confirm no secrets/credentials are committed.
- [ ] **Wrap multi-step writes in DB transactions.** Flows like `addCandidate` → create application →
  set AI score can partially fail and orphan rows. Use transactions for any multi-statement mutation.
- [ ] **Enforce integrity at the database.** `NOT NULL tenant_id` on tenant-scoped tables, foreign
  keys, and composite indexes like `(tenant_id, id)` so a row can never be written without a tenant.

## P1 — Reliability & quality

- [ ] **Type-checking + CI gate.** Add `tsconfig.json` + a `typecheck` script (in progress), and a CI
  pipeline that blocks merges on typecheck + lint + tests. Nothing currently type-checks the frontend.
- [ ] **Clear the ~45 real type errors** surfaced by `tsc` (Router missing props, JobsPage
  `requirements` shape, PipelineBoard `JobListItem` fields, onboarding `apiUrl` ReferenceError, etc.).
- [ ] **Automated tests for critical flows.** Unit/integration tests for stage transitions,
  permission enforcement, and tenant scoping; e2e smoke tests for the main ATS journeys.
- [ ] **Observability.** Install and wire error tracking (`@sentry/react` is imported but not
  installed), add structured server logging, and add React error boundaries so failures are visible.
- [ ] **Fix known logic bugs.** Stale `hired_at`/`rejected_at` on stage moves; dead pagination code in
  `candidates()`. (Sidebar badges already work in the live `MainLayout` shell.)
- [ ] **Wire in-ATS navigation.** ATS routes pass no-op `onViewChange` handlers and the router is
  missing detail routes (Candidate Profile, Pool Detail) — in-page drill-downs currently do nothing.
- [ ] **Resolve N+1 queries** in bulk operations (per-id SELECT for activity logging).

## P2 — Cleanup & polish

- [ ] **Delete dead code.** Unused `src/app/App.tsx` (superseded by `MainLayout.tsx`) and the
  abandoned Laravel scaffold in `backend/` (app/Http, routes, vendor) that nothing uses.
- [ ] **De-duplicate the frontend.** Each sub-app (employee-relations, onboarding, dashboard-app,
  service-desk) carries its own full copy of the shadcn `ui/` component set — consolidate to shared.
- [ ] **Replace mocked data with real implementations** where applicable (e.g. dashboard `activities`
  returns an empty mock; the "AI match" score is a heuristic, not a model).
- [ ] **Performance & UX consistency.** Ensure pagination on heavy list endpoints, consistent
  loading/empty/error states, and basic accessibility passes.
- [ ] **Migration story.** Replace manually-run permission seed scripts with a repeatable migration
  process so schema/permission changes deploy reliably to Railway.

---

## Suggested order of attack

1. **P0 security sweep** — controller-by-controller audit (authorization + tenant scoping + no
   client `tenant_id`), then remove scratch files and fix prod config.
2. **P0 data integrity** — transactions + DB constraints.
3. **P1 safety net** — tsconfig/CI + tests, so fixes stop regressing.
4. **P1 functional** — clear type errors, wire navigation, fix remaining logic bugs.
5. **P2 cleanup** — delete dead code, de-dupe, polish.
