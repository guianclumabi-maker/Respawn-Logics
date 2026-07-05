# P0 Runbooks & Audit Results

Companion to `PRELAUNCH_CHECKLIST.md`. This covers the P0 (launch-blocker) items:
what I audited in the code, and the exact steps for the operational pieces I can't click for you.

---

## Part 1 — Code audit results (done)

| Check | Result | Notes / action |
|---|---|---|
| **Destructive SQL in deploy chain** | ✅ Clean | No `DROP`/`TRUNCATE` in `database_scripts`. The one destructive script, `backend/migrate_intelligence.php`, is **not** wired into `migrate_all.php`/`run_migrations.php`. I also added a hard safety guard so it refuses to run without `ALLOW_DESTRUCTIVE=1`. |
| **Migrations idempotent** | ✅ Good | Deploy chain uses `CREATE TABLE IF NOT EXISTS` + guarded `ALTER`s. Safe to re-run every deploy. |
| **Hardcoded secrets in git** | ✅ Clean | No Gemini/Resend/OpenAI-style keys found in PHP. Only `password123` appears — in test files and `seed_admin.php`, which runs **only** from `tests/bootstrap.php`, never in production. |
| **Default/weak admin in prod** | ⚠️ Verify | `seed_admin.php` isn't in the deploy chain, but **manually confirm no production admin account uses a default/weak password** (see step below). |
| **Session cookie flags** | ✅ Good, 1 tweak default | `httponly=true`, `samesite=Lax`. `secure` auto-enables when HTTPS is detected (Railway proxy). **Recommend** explicitly setting `SESSION_SECURE=true` in prod env so it never depends on header detection. |
| **Debug leakage** | ⚠️ Verify | `APP_DEBUG` defaults to false — **confirm `APP_DEBUG` is NOT set to true** in Railway prod (would leak stack traces). |
| **Tenant isolation** | ✅ Consistent in code | All 28 controllers scope by `tenant_id` (446 references); verified Payroll scopes `users` by tenant. Still do the **live tamper test** below. |
| **CI checks exist** | ✅ Yes | `backend-tests` runs `php -l` on all backend PHP; `typecheck-and-lint` runs `tsc`. These already caught real bugs. They are **not yet required** to merge — fix that below. |

**Two quick things for you to verify manually:**
1. In prod DB: `SELECT email, role FROM users WHERE role IN ('Super_Admin','Platform_Admin');` — make sure each is an account you control with a strong password.
2. In Railway env vars: `APP_DEBUG` unset or `false`, and add `SESSION_SECURE=true`.

---

## Part 2 — Deploy smoke test (run after EVERY deploy, ~10 min)

Do this in a **fresh incognito window** each time. If any step fails, roll back (Part 3) before investigating.

1. [ ] Load the site root — marketing/login page renders (not a blank page or raw JSON).
2. [ ] **Log in** as a known test admin — lands on the dashboard with the **full** sidebar (no employee/admin flip).
3. [ ] Hard-refresh the dashboard — still logged in (session persists), no bounce to `#/login`.
4. [ ] Open **ATS** → dashboard loads. Open **ELR Admin Console** → loads with a single sidebar.
5. [ ] Open **Payroll**, **Attendance**, **Leave**, **Employee Directory** — each lists data without a 500.
6. [ ] Run **one ELR Copilot** question — returns an answer with sources (not "AI engine could not generate").
7. [ ] Create + delete one throwaway record (e.g. an ELR template) — write path works.
8. [ ] **Log out** — returns to login; confirm you can't hit a protected route after logout.
9. [ ] Open browser devtools console — no red errors on the main pages.

If all 9 pass, the deploy is good. Keep this list next to your deploy button.

---

## Part 3 — Rollback (target: under 5 minutes)

Railway keeps previous deployments. To roll back:
1. Railway dashboard → your service → **Deployments** tab.
2. Find the last **known-good** deployment (before the bad one).
3. Click its `⋯` menu → **Redeploy** (or "Rollback to this deployment").
4. Wait for it to go green, then run the Part 2 smoke test.

**Notes:**
- Rolling back the app does **not** roll back database migrations. Because your migrations are additive/idempotent (no destructive ops), this is safe — old code ignores new columns/tables.
- If a bad **migration** is the problem (not app code), restore from backup (Part 4) instead.
- Write the name of your current known-good deployment somewhere before each deploy, so you know exactly what to roll back to.

---

## Part 4 — Database backups (turn on + prove restore)

**Enable:**
1. Railway → your MySQL database service → **Backups** (or Settings → Backups).
2. Enable **daily automated backups**, retention **≥ 7 days**.
3. If your plan doesn't include managed backups, add a scheduled `mysqldump` (daily) to off-instance storage (e.g. an object store).

**Prove it (this is the part everyone skips — don't):**
1. Take a manual backup now.
2. Spin up a scratch database, restore the backup into it.
3. Confirm a few tables have data (`users`, `payroll_runs`, `elr_cases`).
4. Delete the scratch DB. You now *know* restore works — not just that backups exist.

**Recurring:** put a quarterly calendar reminder to repeat the restore drill.

---

## Part 5 — Branch protection + required CI (15 min, stops broken merges)

GitHub → your repo → **Settings** → **Branches** → **Add branch ruleset / protection rule** for `main`:
1. [ ] **Require a pull request before merging.**
2. [ ] **Require status checks to pass before merging** → select `backend-tests` and `typecheck-and-lint`.
3. [ ] **Require branches to be up to date before merging** (forces conflicts to be resolved first — this is why you kept hitting `dist` conflicts late).
4. [ ] (Optional) Require the smoke test as a manual checkbox in your PR template.

Result: a PHP parse error or TypeScript error can **no longer reach `main`** — CI blocks the merge.

---

## Part 6 — Environment hardening (Railway env vars)

Confirm/set in production:
- [ ] `APP_DEBUG=false` (or unset) — no stack traces to users.
- [ ] `SESSION_SECURE=true` — cookies only over HTTPS.
- [ ] `SESSION_SAMESITE=Lax` (current default is fine).
- [ ] All secrets present as env vars: `GEMINI_API_KEY`, `RESEND_API_KEY`, `DB_*`. None in git.
- [ ] `RAILWAY_PUBLIC_DOMAIN` set so app URLs resolve to HTTPS.

---

## Part 7 — Live tenant-isolation test (do once, ~2 hrs)

Static code check passed; now prove it at runtime:
1. Create two tenants (A and B) each with an employee + an ELR case + a payroll record.
2. Log in as tenant A admin. In devtools/network, take a real request (e.g. `route=elr&action=case&id=<A's case id>`).
3. Replay it substituting **tenant B's** record id.
4. **Expected:** 403/404/"not found" — never B's data. Repeat for employees, payroll, export.
5. If any request returns another tenant's data, that's a P0 stop-ship — fix the missing `tenant_id` filter before launch.

---

### P0 exit criteria
When Parts 1–7 are all checked, you're at ~75% — safe to run a **supervised** pilot. Then move to P1 in `PRELAUNCH_CHECKLIST.md`.
