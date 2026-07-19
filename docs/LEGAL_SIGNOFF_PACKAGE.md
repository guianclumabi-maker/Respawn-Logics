# Respawn Logics — Data Privacy & Legal Review Package (RA 10173 / DPA)

**Purpose.** A walkthrough for legal counsel / a DPO reviewing the platform's compliance posture under the Data Privacy Act of 2012 (RA 10173), its IRR, and NPC issuances. Statements below describe what is IMPLEMENTED and TESTED versus what remains an organizational (non-code) obligation. This is not a claim of compliance — it is the evidence file for counsel to assess it.

---

## 1. Personal data inventory

| Category | Fields | Where stored | Protection |
|---|---|---|---|
| Identity | full_name, email, phone | `users`, `candidate_profiles` | Tenant-isolated, RBAC |
| **Sensitive: government IDs** | SSS, PhilHealth, Pag-IBIG, TIN | `employee_statutory` | **AES-256-GCM encrypted at rest** (`enc:v1:` envelope); TIN also has an HMAC blind index for equality checks without decryption |
| Employment | salary, role, department, status, suspension history | `users`, `employee_suspensions` | Tenant-isolated, RBAC, audit-logged |
| **Sensitive: 201-file documents** | contracts, IDs, uploads | `employee_documents` + private storage dir | **AES-256-GCM encrypted at rest**; MIME whitelist; 5MB cap; random filenames; storage refuses web-root paths (fail-loud check) |
| Payslips | pay amounts, deductions | `payroll_payslips` + PDFs | **PDFs AES-256-GCM encrypted at rest**; download restricted to owner or payroll role |
| Recruitment | resumes, applications, scores | `candidate_profiles`, `candidate_applications` | Tenant-isolated; candidate anonymization flow exists (erasure) |
| Attendance/leave | punches, schedules, leave reasons | `attendance`, `timesheets`, `leave_requests` | Tenant-isolated, RBAC |

**Key management:** `APP_ENCRYPTION_KEY` + blind-index key live ONLY in environment variables (Railway Variables / local env), never in code or DB. GCM authentication means tampered ciphertext fails loudly. Decryption tolerates legacy plaintext rows (gradual rollout); uploads without a configured key log an unmissable warning.

## 2. Technical & organizational measures (§ 25–29 IRR) — implemented

- **Tenant isolation:** every query scoped by tenant_id; cross-tenant access covered by automated tests (RbacScopeIsolationTest, SecurityRegressionTest, SuspensionRegressionTest cross-tenant cases, TenantScopeTest).
- **Access control:** RBAC with per-permission gates on all 31 route controllers (authorization matrix audit completed); scoped role assignment (self/team/department/branch/tenant).
- **Authentication:** password hashing (bcrypt via password_hash), forced change on first login, 2FA support, CSRF protection, session-based rate limiting on the AI endpoint.
- **Audit trail:** logins, record changes, payroll runs, file access recorded in `audit_logs` (tamper-resistant append pattern).
- **Storage:** uploaded files kept OUTSIDE the web root; the storage resolver throws if a configured path falls inside a public directory.
- **Erasure (candidate right):** anonymization flow for candidate profiles.
- **Suspension/lifecycle:** no hard-delete of employees (audit continuity); Suspend/Reinstate with reason capture and 6-month constructive-dismissal warning (Labor Code Art. 301 guardrail).
- **CI security tests:** authorization + isolation regression tests run in GitHub Actions on every PR.

## 3. Data-subject rights (§ 34) — implementation status

| Right | Status |
|---|---|
| To be informed | Privacy policy page exists (`privacy.php`) — **counsel to review text** |
| Access | Self-service profile, own payslips, own statutory numbers (decrypted only for the owner/HR) |
| Rectification | Self-service profile edit (whitelisted fields) + HR master-record flow |
| Erasure/blocking | Candidate anonymization implemented; **employee-record retention/erasure schedule = open item (see §5)** |
| Damages / complaint | Organizational — not a code feature |
| Portability | CSV export endpoints (employees, payroll) |

## 4. Breach readiness (NPC 72-hour rule)

Implemented: audit logs to reconstruct scope; encryption at rest reduces notifiable-breach surface (ciphertext without the key). **Open (organizational):** written incident-response plan naming the DPO, NPC notification templates, breach drill. The DPA starter kit (`DPA_RA10173_STARTER_KIT.md`) contains drafts — counsel to finalize.

## 5. Open items for counsel (ranked)

1. **NPC registration** — determine registrability (processing sensitive personal information of >250 individuals across tenants ⇒ registration + DPO designation likely required).
2. **DPO designation** and publication in the privacy notice.
3. **Retention & disposal schedule** — define per record class (201 files, payroll: BIR requires 10-year books retention; candidates: define post-rejection window) and implement automated disposal jobs.
4. **Privacy notice & consent points** — review `privacy.php`, `terms.php`, candidate-application consent wording, cookie usage.
5. **Data Processing / Sharing Agreements** — tenant contracts must define controller (client) vs processor (Respawn Logics) roles; subprocessor list (Railway/hosting, any AI/LLM provider for the companion + resume scoring — flag AI processing in the notice).
6. **Backups** — confirm DB backups are encrypted and access-controlled (host-level; outside app code).
7. **TLS/transport** — enforced by hosting (Railway) — confirm HSTS and no plaintext endpoints.
8. **Cross-border transfer** — hosting region vs PH data → assess adequacy/contractual safeguards.
9. **Incident-response plan** — finalize from starter kit; assign roles; schedule an annual drill.
10. **Employee monitoring disclosures** — attendance/geolocation (if shift geodata used) must appear in the internal privacy notice.

## 6. Evidence index

| Claim | Where to verify |
|---|---|
| Encryption at rest (IDs/docs/payslips) | `backend/utils/Crypto.php`; call sites in BenefitsController, CoreHRController (upload/download), PayslipGenerator, PayrollController::downloadPayslip; `database_scripts/migrate_encryption_columns.php` |
| Tenant isolation | tests: RbacScopeIsolationTest, TenantScopeTest, Unit/SecurityRegressionTest (cross-tenant ELR/leave), SuspensionRegressionTest |
| RBAC gates | route map `api/index.php`; per-controller permission checks; PlatformAdminSecurityTest |
| Audit trail | `logAudit()`/`logAction()` call sites; `audit_logs` table |
| Private storage | `backend/utils/Storage.php` (web-root refusal); upload paths |
| Payroll correctness controls | `docs/CPA_SIGNOFF_PACKAGE.md` |

Reviewed by: ______________________ Counsel/DPO · Date: __________
