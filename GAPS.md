# Respawn Logics — Gap Analysis

**What this is:** an honest, prioritized list of what's missing across the whole product — backend, frontend, UX, compliance, and ops. Ordered by how much each will actually bite you. Some you already know; a couple are easy to overlook. Companion to `PRELAUNCH_CHECKLIST.md` and `P0_RUNBOOKS.md`.

**One-line read:** the *tech* is further along than the *discipline and safety nets* around it. Almost everything below is predictable hardening, not invention — the hard, creative part is already built.

---

## Tier 1 — Highest leverage (fix these and most other pain shrinks)

### 1. Almost no automated tests  ·  *biggest technical gap*
Everything ships protected only by `php -l` (syntax) and `tsc` (types). Nothing checks **behavior**. Every bug this cycle — CSRF login, dead SPA, the entire ELR frontend/backend contract drift — would have been caught by ~20 integration tests on the critical paths.
**Do:** a small integration suite for login → dashboard, one CRUD per module, a payroll run, and one ELR case flow. Wire it into CI so it must pass to merge.
**Effort:** 1–2 days. **ROI:** compounds forever.

### 2. No shared contract between backend and frontend
The ELR debug proved this: the two sides drifted on field names on nearly every screen (`cards` vs `cases`, `enabled` vs `is_active`, `current_stage_id` vs `stage_id`, …). You're hand-maintaining the mapping in your head.
**Do:** a shared `types.ts` (or generated types) that both sides import, and treat backend responses as the single source of truth.
**Effort:** ongoing habit + half a day to seed. **ROI:** kills this whole bug class permanently.

---

## Tier 2 — Legal / compliance (the stuff nobody warns you about, and it's PH-specific)

### 3. Data Privacy Act (RA 10173) compliance
You store Filipino employees' PII, salaries, and disciplinary records **for other companies** — that makes you a personal-information processor under the law. The NPC expects a Data Privacy Officer, a privacy policy, consent handling, a breach protocol, and possibly registration.
**Do:** talk to someone who knows RA 10173 before real client data lands. Draft a privacy policy + DPA for business clients.
**Why it's Tier 2 not Tier 3:** this can sink a paid HRIS, not just embarrass it.

### 4. Proof of service on ELR notices
Your compliance story is "we generate the RTWN/NTE/NOD and log due process." But legally, half the battle is **proving the employee received it**. You have `served_at`/`acknowledged_at` timestamps, but no real delivery proof or e-signature.
**Do:** capture genuine acknowledgment — email receipt, e-sign, or at minimum a defensible audit record of delivery.
**Why it matters:** it's the missing piece of your single best feature.

### 5. CPA / labor-lawyer sign-off on statutory math
13th-month, SIL, night differential, OT, separation/retirement pay, SSS/PhilHealth/Pag-IBIG/tax. You're liable for what the engine computes and the documents it generates.
**Do:** get a professional to validate the numbers against a hand-calculated sample before go-live. *(Already scheduled around your July 10 domain work — bundle it.)*

---

## Tier 3 — Frontend / UX (turns "prototype" into "product")

### 6. Mobile
Employees will open payslips and leave requests on **phones**, not desktops. If you haven't verified the SPA at ~375px wide, half your audience may be using a broken experience.
**Do:** test ESS pages on a phone; fix the worst breakpoints.

### 7. Consistent empty / loading / error states
New tenants start on a blank canvas (good) — but a blank canvas with no guidance *reads as broken*. Every list needs a friendly "nothing here yet — start here," every fetch a spinner and an error fallback.
**Do:** a standard EmptyState + error + skeleton pattern applied across modules.

### 8. Help / onboarding guidance
A powerful blank-canvas admin console is intimidating with zero direction.
**Do:** a per-tenant "getting started" checklist, a few tooltips, and a one-page admin guide. Dramatically lowers the "what do I even do here" wall.

---

## Tier 4 — Backend / scale (fine now, painful at real volume)

### 9. Performance at real data volumes
Some queries run a per-row subquery (the ELR board/report do a `COUNT` per card). Fine for 10 rows, ugly at 10,000. Large lists have **no pagination**.
**Do:** add pagination to big lists; replace per-row subqueries with joins/aggregates; confirm indexes on hot paths (already partly done for ATS/attendance).

### 10. Bulk import robustness
Onboarding a company = importing hundreds of employees. The first import must forgive messy CSVs (duplicates, bad dates, missing fields) with clear per-row errors.
**Do:** harden the import with validation + a dry-run/preview + a downloadable error report.

### 11. Notification delivery — actually wired end to end?
You have a notifications system and email (Resend), but is delivery **proven**? Does a leave approval ping the employee? Does the ELR digest actually send?
**Do:** test each notification path end to end; confirm Resend on your own domain (July 10).

---

## Tier 5 — Ops / production (from the checklists, restated for completeness)

- **Monitoring / alerting** — no Sentry/error alerts; you learn about breakage from clients. *(P1)*
- **Backups** — manual `mysqldump` only on the free tier; upgrade to managed daily backups when you can. *(P0, method in place)*
- **Staging environment** — you test deploys directly on production. *(P2)*
- **CI runs only lint** — the PHPUnit job is stubbed (`echo "Skipping tests"`); re-enable once tests exist. *(P1)*
- **Security review** — no OWASP scan yet. *(P1)*

---

## What's genuinely solid (so you know where NOT to spend time)

- **Tenant isolation** — audited across 28 controllers + the ELR debug found zero leaks. Strong.
- **Architecture** — consistent controller pattern, coherent migration/deploy chain, RBAC + CSRF + 2FA + rate limiting in place.
- **The ELR pipeline concept** — genuinely novel; due process as a Kanban that auto-generates the legally-required document and logs the twin-notice trail. This is your moat.
- **Issued-document integrity** — generated documents are frozen snapshots, so template revisions never rewrite history. Compliance-correct by design.

---

## If you only do three things next
1. **Write ~20 integration tests + require them in CI** (Tier 1.1) — stops the firefighting.
2. **Shared TS types from the backend contract** (Tier 1.2) — stops the drift.
3. **Kick off RA 10173 + CPA sign-off** (Tier 2.3, 2.5) — long lead time, external, start early.

Everything else is steady, unglamorous hardening — and you're already past the hard part.
