# Respawn Logics — Weekend QA Test Plan

**Scope:** Everything shipped in the recent hardening + Phase 1 + Phase 2 work — onboarding, auth, password reset, Employee Self-Service, Tenant Settings, and the ELR Copilot. Plus core security/multi-tenancy checks.

**How to use:** Run top to bottom on the **live Railway site**. Mark each row Pass / Fail and note anything odd. Anything that fails, capture: the URL, the DevTools **Network** tab status + response of the failing request, and any red **Console** errors.

**Before you start — set up test accounts:**
- **Tenant A – Owner/Admin** (a Super_Admin, created via `register.php`).
- **Tenant A – Employee** (a regular imported employee, activated).
- **Tenant B – Owner/Admin** (a *second* company, for tenant-isolation checks).

> Note: password-reset and invite **emails** will only actually arrive once the Resend sending domain + `MAIL_FROM` are configured. Until then, the *flows/pages* work but the email won't land — that's expected, not a failure.

---

## 1. Onboarding & Tenant Creation

| # | Step | Expected result | Pass/Fail |
|---|------|-----------------|-----------|
| 1.1 | Landing page → **Initialize Setup** | Shows the 4 setup-mode cards (Solo / Co-op / Guild / MMO) | |
| 1.2 | Click **Co-op Mode** → register a new company | Lands on "Register Your Company" with the tier preselected | |
| 1.3 | Complete registration (company, name, email, password) | Creates a **new** tenant (not tenant 1); logs you in | |
| 1.4 | Non-solo tier after register | Lands on the **CSV onboarding** flow (not dashboard) | |
| 1.5 | Upload a CSV → map columns → **INITIALIZE_IMPORT** | "System Initialized"; nodes connected = your row count; `activation_links.csv` downloads | |
| 1.6 | Click **ENTER_SYSTEM** on results | Goes to the **login** page | |
| 1.7 | Register a **Solo** company | After register, lands **straight in the dashboard** (no CSV step) | |
| 1.8 | DB check (optional) | New rows in `users` carry the new tenant's `tenant_id`, never `1` | |

## 2. Authentication & Password Reset

| # | Step | Expected result | Pass/Fail |
|---|------|-----------------|-----------|
| 2.1 | Log in with valid credentials | Lands on dashboard; no bounce back to login | |
| 2.2 | Log in with wrong password | Clear error, stays on login | |
| 2.3 | On login page, click **Forgot your password?** | Opens `forgot-password.php` | |
| 2.4 | Enter your email → **Send reset link** | Generic message: "If an account exists… link has been sent" | |
| 2.5 | (Once email domain set) open the reset link | `reset-password.php` opens with a new-password form | |
| 2.6 | Set a new password (matching, ≥8 chars) | Success screen → can log in with the new password | |
| 2.7 | Open an expired/garbage reset token | "Invalid or expired link — request a new one" | |

## 3. Employee Self-Service (log in as the **Employee** account)

| # | Step | Expected result | Pass/Fail |
|---|------|-----------------|-----------|
| 3.1 | Sidebar shows **My Space** section | My Payslips / My Leave / My Compensation / My Profile visible | |
| 3.2 | **My Payslips** | Shows only *your* payslips; download works | |
| 3.3 | **My Leave** — view | Balance cards + your request history load | |
| 3.4 | **My Leave** — file a request | Submits; appears in your history with correct status | |
| 3.5 | **My Compensation** | Shows only *your* comp history (read-only) | |
| 3.6 | **My Profile** — edit phone / emergency contact / bio → Save | Success; reload → changes persisted | |
| 3.7 | **Security tamper check** (DevTools → Network on Save) | Request body contains **only** phone/emergency/bio — no role/salary | |
| 3.8 | Try to open an admin page (e.g. `/admin/users`) as employee | Denied / not visible (RBAC) | |

## 4. Tenant Settings (log in as **Owner/Admin**)

| # | Step | Expected result | Pass/Fail |
|---|------|-----------------|-----------|
| 4.1 | Open Tenant Settings | Loads company profile, pay schedules, 2FA toggle, support access | |
| 4.2 | Edit company name / timezone / locale → Save | Success; reload → persisted | |
| 4.3 | Add a pay schedule (name, frequency) | Appears in the pay-schedule list | |
| 4.4 | Toggle **2FA enforcement** → Save | Saves (note: policy stored; login-enforcement wiring is a later task) | |
| 4.5 | **Grant 24h Support Access** | Button confirms "Access Granted (Expires in 24h)" | |
| 4.6 | Confirm it can't change tier/status/billing | No such fields exposed here | |

## 5. ELR Copilot & Knowledge Base

| # | Step | Expected result | Pass/Fail |
|---|------|-----------------|-----------|
| 5.1 | ELR module → **Copilot** | Question box + disclaimer visible | |
| 5.2 | Ask: *"What is the twin-notice rule for terminating an employee?"* | Grounded answer citing **King of Kings Transport v. Mamac**; Sources listed | |
| 5.3 | Ask: *"Do we owe separation pay for redundancy?"* | Cites Art. 298 reference + Jaka precedent; practical steps | |
| 5.4 | Ask: *"Difference between AWOL and abandonment?"* | Cites the AWOL/Abandonment precedent (two-element test) | |
| 5.5 | Ask something off-topic: *"What's the weather today?"* | "No matching sources — general guidance only" note; **no invented law** | |
| 5.6 | Open **Knowledge Base** (as Super_Admin) | ~24 entries list (references + precedents) | |
| 5.7 | Add a test entry (reference or precedent) | Saves; appears in the list | |
| 5.8 | Approve a **Pending** reference | Status → Approved; now usable by the Copilot | |
| 5.9 | Open Knowledge Base as a **non-super** ELR user | Lists are read-only; no add/approve controls | |

## 6. Security & Multi-Tenancy (the critical ones)

| # | Step | Expected result | Pass/Fail |
|---|------|-----------------|-----------|
| 6.1 | Log in as **Tenant B** admin | See only Tenant B's employees/data — never Tenant A's | |
| 6.2 | Employee tries an admin-only API action | 403 / denied | |
| 6.3 | Log out → hit an authenticated URL directly (e.g. `#/my/payslips`) | Bounced to login | |
| 6.4 | Log out → `POST api.php?...route=onboarding&action=import` | 401 Unauthorized | |
| 6.5 | Non-super tries `kb_add` (ELR corpus write) | 403 "Only Super_Admins can write" | |
| 6.6 | Confirm no error responses leak raw DB/SQL text | Generic "internal error" messages only | |

---

## Sign-off

| Area | Result | Notes |
|------|--------|-------|
| Onboarding & tenant creation | | |
| Auth & password reset | | |
| Employee Self-Service | | |
| Tenant Settings | | |
| ELR Copilot & Knowledge Base | | |
| Security & multi-tenancy | | |

**Tester:** ______________  **Date:** ____________  **Overall:** ☐ Ready for pilot ☐ Needs fixes

> Known/expected limitations (not failures): reset & invite emails require the Resend domain; 2FA-enforcement and notification-preference toggles are stored but not yet enforced at login/dispatch; ELR Copilot corpus is intentionally small (~24 entries) and grows via the Knowledge Base admin.
