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

- [ ] **Multi-tenant isolation audit across ALL 23 controllers.** Every SQL read/write must be
  scoped by the authenticated user's `tenant_id`. Confirmed gap: `CandidatesController::jobs()`
  departments query had no tenant filter. Audit every controller for the same pattern.
- [ ] **Never trust a client-supplied `tenant_id`.** Resolve tenant strictly from the session/user,
  never from `$_GET`/`$_POST`/`$input`. Audit result: only `PlatformSupportController` reads
  `$_GET['tenant_id']`, and it IS gated behind a platform-staff role check (403 guard) — acceptable.
  Keep this rule for any future controller; no other controller currently does this.
- [ ] **Fail closed on tenant resolution.** `CandidatesController` silently defaults the tenant to
  `'1'` when none resolves — a cross-tenant leak. Replace with a 403 when no tenant is resolvable,
  and apply the same pattern in every controller.
- [ ] **Server-side authorization on EVERY mutating action.** AUDIT RESULT: only 2 of 23 controllers
  (`CandidatesController`, `ELRController`) call `requirePermission` at all, and 5 controllers have
  ZERO authorization of any kind: `AICompanionController`, `AuthController`, `EmployeeRelationsController`,
  `LeavesController`, `NotificationController`. Sensitive modules are thinly gated server-side —
  `PayrollController`, `IAMController`, `BenefitsController`, `ExpensesController` rely mostly on
  `isLoggedIn()` + frontend hiding, so any authenticated user could invoke their actions directly.
  Gate every create/update/delete server-side, per module, with `requirePermission` — do not rely on
  frontend role flags. (`PlatformSupportController` and `Candidates/CoreHR/Performance` are the better
  examples to follow.) Recommend a per-action authorization matrix as the deliverable.
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
