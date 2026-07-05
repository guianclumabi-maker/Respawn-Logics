# Respawn Logics — Pre-Launch Checklist

**Goal:** take the platform from ~60% ("works in a demo") to ~90% ("a business can bet payroll on it").
**How to use:** work top-down. Don't start P1 until P0 is done — the P0 items are what turn firefighting into calm. Each item has **what**, **why**, and a rough **effort**. Check them off as you go.

Current honest estimate: **~60–65%** production-grade. Finishing **P0** gets you to ~75% (safe supervised pilot). Finishing **P1** gets you to ~90% (real paying clients). **P2** is scale/maturity beyond that.

---

## P0 — Launch Blockers (do before ANY real client data touches the system)

These exist because, in practice, broken code has been reaching the live site. P0 is mostly about **stopping that** and **being able to recover** if something goes wrong.

### Release safety
- [ ] **Deploy smoke-test checklist.** A fixed 10-minute manual pass you run after every deploy: login (fresh browser), load dashboard, open one record in each major module (ATS, ELR, Payroll, Attendance, Leave), run one ELR Copilot query, log out. If any step fails, roll back. *Why: every production outage this cycle (dead login, dead SPA, parse error) would have been caught here.* **Effort: 1 hr to write, 10 min/deploy.**
- [ ] **Branch protection + green CI required to merge.** Require the `php -l` + `tsc` checks to pass before a PR can merge to `main`. *Why: a merged PHP parse error took down the ELR module; this makes that structurally impossible.* **Effort: 15 min (GitHub settings).**
- [ ] **One-command rollback.** Confirm you can redeploy the previous Railway build in <5 min, and write the exact steps down. *Why: recovery speed matters more than prevention when it's live.* **Effort: 30 min.**
- [ ] **Kill the mount/merge foot-guns.** Standardize: build `frontend/dist` fresh on every deploy (never hand-edit it), and never keep `dist` in a state that can drift from the bundle. *Why: the "landing page just refreshes" dead-SPA bug came from a stale/mismatched dist.* **Effort: already mostly done — verify the build step is authoritative.**

### Data safety
- [ ] **Automated daily database backups** with a tested restore. Railway/managed MySQL usually offers this — turn it on and **actually restore once** to prove it works. *Why: no backups = one bad migration or corruption from losing every client's payroll and case history.* **Effort: 1–2 hrs.**
- [ ] **Backup retention ≥ 7 days** and stored off the primary instance. **Effort: config only.**
- [ ] **Migration safety review.** Confirm every migration in `migrate_all.php` is idempotent and non-destructive (no silent `DROP`/`DELETE` on existing data). `migrate_intelligence.php` does `DROP TABLE` — make sure it is NOT in the deploy chain. *Why: migrations run on every deploy via `run_migrations.php`.* **Effort: 1 hr.**

### Security (the non-negotiable minimum)
- [ ] **Confirm all secrets are in env vars, none in git.** GEMINI, RESEND, DB password, session secret. (You rotated the exposed ones — verify none remain in the repo history that matters.) **Effort: 30 min.**
- [ ] **Tenant isolation spot-check.** Pick 2 tenants; confirm tenant A literally cannot read tenant B's employees, payroll, or ELR cases via any endpoint (try tampering with IDs). *Why: multi-tenant leak = company-ending.* **Effort: 2 hrs.**
- [ ] **Force HTTPS + secure/httponly/samesite session cookies** in production (verify `session.secure` is on behind Railway's proxy). **Effort: 30 min (verify).**
- [ ] **Verify the login/CSRF + permission fixes are live** (the `bootstrap` CSRF-after-session fix and the `is_super` sidebar fix). **Effort: part of smoke test.**

---

## P1 — Pilot Hardening (before / during your first paying pilot)

### Compliance & correctness (HRIS-specific — this is where trust is won or lost)
- [ ] **CPA / labor-lawyer sign-off on statutory math.** 13th-month, SIL, night differential, overtime, separation/retirement pay, tax/SSS/PhilHealth/Pag-IBIG. Get a professional to validate the numbers the engine computes. *Why: you are liable for what it generates.* **Effort: external, schedule it (you mentioned ~July 10 for domain — bundle this).**
- [ ] **Payroll reconciliation test.** Run a full payroll cycle against a hand-calculated sample of 5–10 employees; every peso must match. **Effort: half a day.**
- [ ] **Document generation accuracy** (ELR templates / notices) — confirm merge fields fill correctly and dates/names are never wrong on a legal document. **Effort: ongoing as you build the pipeline.**

### Reliability & observability
- [ ] **Error monitoring/alerting.** Wire Sentry (or similar) to the PHP backend + React frontend so you get pinged on 500s and JS crashes instead of hearing it from a client. *Why: you currently find out about breakage manually.* **Effort: 2–3 hrs.**
- [ ] **Uptime monitoring** on the login page + `/api/index.php?route=health`. **Effort: 30 min (UptimeRobot etc.).**
- [ ] **Structured error logging** — confirm server errors log with enough context (already partially done via `error_log`); make sure they're retrievable. **Effort: 1 hr.**

### Security review
- [ ] **Run OWASP ZAP (or similar) against a staging copy.** Baseline scan for XSS, injection, auth bypass, insecure headers. Fix criticals/highs. **Effort: half a day.**
- [ ] **Authorization audit on write endpoints.** Confirm every state-changing action re-checks permission server-side (never trust the hidden UI). Spot-check ELR, payroll, IAM, export. **Effort: half a day.**
- [ ] **PII/data-privacy pass.** Know what personal data you store, ensure it's access-controlled, and have a basic data-handling/retention statement for pilot clients. **Effort: 2–3 hrs.**

### Testing safety net
- [ ] **Smoke/integration tests for the critical paths:** login → dashboard, create/read one record per module, payroll run, ELR case flow. Even 15–20 automated tests changes everything. *Why: `php -l`/`tsc` catch syntax, not behavior.* **Effort: 1–2 days, high ROI.**
- [ ] **Regression test for each production bug you've already hit** (login/CSRF, dead SPA, permission flip). Lock them so they can't come back. **Effort: within the above.**

### Pilot operations
- [ ] **Onboarding runbook** for a new tenant (create tenant → seed roles → import employees → verify). **Effort: 2 hrs.**
- [ ] **Support channel + SLA expectation** set with the pilot client (even "email me, I reply within a day"). **Effort: conversation.**
- [ ] **Feedback loop** — the "Give us Feedback" button should reach you reliably. **Effort: verify.**
- [ ] **Custom domain + branded email** (Resend on your own domain) so notices/notifications aren't from a generic sender. *(You scheduled ~July 10.)* **Effort: 1–2 hrs.**

---

## P2 — Scale & Maturity (before multiple, unattended clients)

- [ ] **Staging environment** separate from production, so you test deploys before they hit clients. **Effort: half a day.**
- [ ] **CI runs the test suite** (not just lint) on every PR. **Effort: 2 hrs once tests exist.**
- [ ] **Performance pass** — load-test the heaviest queries (payroll runs, org chart, attendance report) with realistic data volumes; add indexes where needed. **Effort: 1 day.**
- [ ] **Rate limiting + abuse protection** reviewed for public endpoints (login, register, password reset). **Effort: half a day.**
- [ ] **Audit trail completeness** — confirm sensitive actions (payroll approval, role changes, case closure, data export) are logged with who/when. **Effort: half a day.**
- [ ] **Documentation** — admin manual + a short user guide per role. **Effort: 1–2 days.**
- [ ] **Backup/restore drill on a schedule** (quarterly), not just once. **Effort: recurring.**
- [ ] **Incident playbook** — what you do when X breaks (DB down, deploy bad, data leak suspected). **Effort: half a day.**
- [ ] **Legal:** Terms of Service + Privacy Policy + a Data Processing Agreement for business clients. **Effort: external / template.**

---

## Suggested order (the 60% → 90% path)

1. Branch protection + required CI + smoke-test checklist *(stops the bleeding — do this first, it's cheap)*
2. Automated backups + one tested restore
3. Tenant-isolation spot-check + secrets/HTTPS verification
4. Error + uptime monitoring
5. CPA statutory sign-off *(external, so kick it off early — long lead time)*
6. Critical-path smoke/integration tests
7. OWASP ZAP scan + fix criticals
8. Custom domain/email + pilot onboarding runbook
9. Staging environment
10. Everything else in P2 as you grow

---

### Reality check
The creative, hard part — the product and its architecture — is largely built. Almost everything above is **predictable hardening work**, not invention. Knock out P0 (mostly a few hours to a couple of days total) and you're genuinely safe to run a supervised pilot. That's a much shorter distance than "40% left" sounds.
