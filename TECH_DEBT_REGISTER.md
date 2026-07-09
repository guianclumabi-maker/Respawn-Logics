# Respawn Logics — THE Checklist (single source of truth)

**This is the only checklist. No new phases, no new frameworks. We update statuses here and nowhere else.**

Legend: ✅ done · 🔶 in progress · ⬜ not started · 👤 = your action (not code) · 🚫 POST-BETA (do not start now)

_Last updated: this session._

---

## 🎯 What's actually left (the short list)

1. 👤 **Merge PR #122** (platform-admin security fix)
2. 👤 **Set up automated DB backups**
3. **Merge the green branches in order** (see "Ship it" below)
4. **Smoke-test merged `main`** (tests + click-through, light & dark)
5. **Launch controlled beta**

Everything below is either already done or explicitly deferred. If it's not in this list of 5, it's not blocking you.

---

## Phase 0 — Production & Security
- [x] 0.1 ELR core case tables migration (fixes "Database error")
- [ ] 0.2 Platform-admin privilege leak (#122) — code done + **verified complete** (impersonate, vendor_dashboard, PlatformSupport, Health all gated; typecheck green) · 👤 **merge #122 — last step**
- [x] 0.3 Remove PII debug logs (AuthController + api/index.php)
- [x] 0.4 Automated DB backups — script + runbook ready (`backup_db.bat` → `backups/`, `BACKUP_RUNBOOK.md`) · 👤 **schedule it (Task Scheduler) OR enable Railway Pro backups + do one test restore**

## Phase 1 — Cheap wins ✅ ALL DONE (51 tests green)
- [x] 1.1 `react`/`react-dom` → dependencies
- [x] 1.2 raw `fetch` → `apiFetch`
- [x] 1.3 de-duped migration lists (`schema_migrations.php`)
- [x] 1.4 central `logAudit()` wired (login/logout/payroll/ELR/settings)
- [x] 1.5 `error_log` sweep across controllers
- [x] 1.6 canonical `buildUserPayload()` (login == current_user)

## Phase 2 — Structural
- [x] 2.2 Auth centralization (`isPlatformStaff()`; `is_super` leaks removed)
- [ ] 2.4 Consolidate duplicate UI — 🔶 in progress (theme toggle done)
- [ ] 2.5 Theme universal (light/dark all pages) — 🔶 in progress
- [ ] 2.1 Real migration framework — 🚫 POST-BETA
- [ ] 2.6 Remove `@ts-nocheck` + split PayrollManager — 🚫 POST-BETA
- [ ] 2.7 Tenant-scoped data-access layer — 🚫 POST-BETA
- [ ] 2.8 Retire legacy `pages/*.php` — 🚫 POST-BETA
- [ ] 2.9 Split fat controllers into services — 🚫 POST-BETA

## Phase 3 — Hygiene (optional, deferrable)
- [ ] 3.1 Tenant seeding/provisioning strategy (replace ad-hoc `ensureDefault*`)
- [ ] 3.2 Delete dead code (`_legacy_reference/`, `old_attendance.php`, orphaned CSS)
- [ ] 3.3 Mailer provider abstraction + config hardening

---

## 🚢 Ship it (do after Phase 0 is closed)
- [ ] S1 List open branches/PRs (`git branch -a` + open PRs)
- [ ] S2 Merge in order: **#122 security → `chore/backend-debt-phase1` → `chore/frontend-debt` → feature branches**
- [ ] S3 Resolve conflicts on shared files (migration list, `PayrollManager.tsx`, sidebars)
- [ ] S4 Smoke-test merged `main` (full `phpunit` + click-through, light & dark)
- [ ] S5 Launch controlled beta (1–2 tenants; payroll = "preview, verify before paying")

## 🔒 External — you own these (not code)
- [ ] CPA / labor-lawyer sign-off on payroll statutory rates + 13th-month definition
- [ ] Email sending-domain verification (SPF/DKIM)

---

**Working rule:** one agent owns the working tree at a time; commit early/often; `main` is the source of truth, not any local copy.
