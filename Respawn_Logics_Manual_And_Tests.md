# Respawn Logics v2.0 
## System Manual & Quality Assurance Test Plan

---

## Part 1: System Manual

### 1. Introduction
Respawn Logics v2.0 is a modern, multi-tenant SaaS Human Resources Information System (HRIS) and Applicant Tracking System (ATS). It utilizes a high-performance PHP backend paired with a dynamic React Single Page Application (SPA) frontend. 

### 2. Role-Based Access Control (RBAC)
Access to features is strictly governed by roles and permissions. The system uses a tiered approach:
*   **Super_Admin / Platform_Admin**: Full system access, tenant management, health diagnostics, and global settings.
*   **Admin**: Tenant-level administrators. Full control over a specific organization's HR, Payroll, and ATS configurations.
*   **HR**: Access to core personnel records, payroll processing, and organizational charts.
*   **Recruiter**: Access isolated to the ATS module (Candidate pipelines, interviews, talent pools).
*   **Employee**: Basic self-service portal access (view payslips, request leaves, submit expenses).

### 3. Core Modules

#### 3.1 Applicant Tracking System (ATS)
*   **Pipeline Board**: Kanban-style drag-and-drop board to track candidates through stages (Screening, Interview, Offer).
*   **Recruiting Copilot**: AI-powered assistant (Gemini) to help summarize resumes and draft communication.
*   **Interviews & Talent Pools**: Schedule interviews and categorize candidates into pools for future roles.

#### 3.2 Core HR & Onboarding
*   **Setup Modes**: Flexible onboarding paths (Solo, Quick, Standard, Enterprise) allowing bulk CSV imports of employee data with dynamic schema mapping.
*   **HR Directory & Org Units**: Visual organization charts and hierarchical department management.
*   **Employee Relations**: Gamified tracking of employee sentiment, surveys, and announcements.

#### 3.3 Time, Attendance & Leaves
*   **Leaves Dashboard**: Multi-stage approval workflows. Team Leads approve stage 1; Department Managers approve stage 2.
*   **Attendance & Shifts**: Clock-in/out tracking tied to dynamic shift schedules and overtime calculations.

#### 3.4 Payroll & Expenses
*   **Payroll Engine**: Automated generation of payslips factoring in gross pay, attendance deductions, and bonuses.
*   **Remittance Reports**: Generation of government statutory reports (SSS, PhilHealth, Pag-IBIG, BIR 1601-C).
*   **Expenses Admin**: Submission and hierarchical approval of employee expense claims, isolated by tenant.

#### 3.5 AI Companion & Global Intelligence
*   **Contextual AI**: Users can chat with an integrated AI. 
*   **Security**: All AI interactions pass through a strict anonymizer that scrubs PII (emails, phone numbers, names) before being sent to Google's Gemini API.

---

## Part 2: Comprehensive Test Plan

### Section 1: Pre-requisite Test Setup (Railway Production)

**Base URL:** `https://respawn-logics-production-eb66.up.railway.app`

**Tool Required:** Postman or any HTTP client (cURL, Insomnia).

**1. Access the Live Application**
*   Navigate to the base URL above to confirm the app is live and loading.

**2. User Role Seeding**
Ensure the following test accounts exist in the system before running tests:

| Email | Password | Role |
| :--- | :--- | :--- |
| `superadmin@respawnlogics.com` | *(set in Railway env)* | Super_Admin |
| `admin@alphacorp.com` | *(set during onboarding)* | Admin |
| `hr@alphacorp.com` | *(set during onboarding)* | HR |
| `manager@alphacorp.com` | *(set during onboarding)* | Manager |
| `employee@alphacorp.com` | *(set during onboarding)* | Employee |

**3. Base Configuration Data**
*   At least **1 Expense Category** must exist (e.g., "Travel", `category_id = 1`).
*   At least **1 Shift Schedule** must exist (e.g., "9 AM - 5 PM").
*   `manager@alphacorp.com` must be set as the immediate supervisor of `employee@alphacorp.com`.

---

### Section 2: Authentication Flow (Required for ALL tests)

> **IMPORTANT:** Every protected endpoint requires a valid session cookie AND a CSRF token. Complete Steps A and B before every test session.

---

#### Step A — Get CSRF Token

**Request:**
```
GET /api/index.php?route=auth&action=csrf
Host: respawn-logics-production-eb66.up.railway.app
```

**Expected Response:**
```json
{
  "success": true,
  "csrf_token": "a3f8c2d1e9b7..."
}
```

**Action:** Copy the value of `csrf_token`. Also copy the `Set-Cookie: PHPSESSID=...` header from the response. You will need both for every subsequent request.

---

#### Step B — Login

**Request:**
```
POST /api/index.php?route=auth&action=login
Content-Type: application/json
X-CSRF-Token: <token_from_step_A>
Cookie: PHPSESSID=<session_from_step_A>
```
**Body:**
```json
{
  "email": "admin@alphacorp.com",
  "password": "yourpassword"
}
```

**Expected Response:**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "full_name": "Alpha Admin",
    "email": "admin@alphacorp.com",
    "roles": ["Admin"]
  }
}
```

**Action:** Keep the same `PHPSESSID` cookie for all tests in this session. Repeat Step A + B whenever the session expires.

---

### Section 3: Module Test Cases

> For ALL requests below, always include these headers:
> ```
> Content-Type: application/json
> X-CSRF-Token: <your_token>
> Cookie: PHPSESSID=<your_session>
> ```

---

#### MODULE 1 — Core HR

---

**TEST HR-01 — List All Employees**

*   **Logged in as:** `admin@alphacorp.com`
*   **Request:** `GET /api/index.php?route=core_hr&action=fetch_employees`
*   **Expected Response:**
```json
{
  "success": true,
  "employees": [
    { "id": 1, "full_name": "...", "email": "...", "department": "..." }
  ]
}
```
*   **Pass Criteria:** Response contains only employees from Alpha Corp. No employees from Beta LLC appear.

---

**TEST HR-02 — Create New Employee**

*   **Logged in as:** `admin@alphacorp.com`
*   **Request:** `POST /api/index.php?route=core_hr&action=create_employee`
*   **Body:**
```json
{
  "full_name": "Jane Doe",
  "email": "jane.doe@alphacorp.com",
  "department": "Engineering",
  "position": "Developer",
  "employment_status": "Active"
}
```
*   **Expected Response:**
```json
{ "success": true, "employee_id": 12 }
```
*   **Pass Criteria:** Employee is created. Tenant ID is auto-scoped to Alpha Corp (no `tenant_id` in the body required). Verify in the DB.

---

**TEST HR-03 — Unauthorized Employee Create (Employee Role)**

*   **Logged in as:** `employee@alphacorp.com`
*   **Request:** Same as HR-02.
*   **Expected Response:**
```json
{ "success": false, "error": "Forbidden" }
```
*   **Pass Criteria:** HTTP 403 returned. Record is NOT created in the database.

---

#### MODULE 2 — Leaves & Attendance

---

**TEST LV-01 — Submit a Leave Request**

*   **Logged in as:** `employee@alphacorp.com`
*   **Request:** `POST /api/index.php?route=leaves&action=submit`
*   **Body:**
```json
{
  "leave_type": "Vacation",
  "start_date": "2026-07-01",
  "end_date": "2026-07-03",
  "reason": "Annual family trip"
}
```
*   **Expected Response:**
```json
{ "success": true, "leave_id": 5, "status": "Pending TL Approval" }
```
*   **Pass Criteria:** Leave record created with status `Pending TL Approval`. Note the `leave_id` for the next tests.

---

**TEST LV-02 — Team Lead Approves (Stage 1)**

*   **Logged in as:** `manager@alphacorp.com` *(must be the actual supervisor of the employee)*
*   **Request:** `POST /api/index.php?route=leaves&action=decide`
*   **Body:**
```json
{
  "leave_id": 5,
  "decision": "Approved",
  "stage": "TL"
}
```
*   **Expected Response:**
```json
{ "success": true, "status": "Pending Manager Approval" }
```
*   **Pass Criteria:** Leave status advances to `Pending Manager Approval`.

---

**TEST LV-03 — Same User Cannot Double-Approve (Stage 2)**

*   **Logged in as:** `manager@alphacorp.com` *(same user who did Stage 1)*
*   **Request:** `POST /api/index.php?route=leaves&action=decide`
*   **Body:**
```json
{
  "leave_id": 5,
  "decision": "Approved",
  "stage": "Manager"
}
```
*   **Expected Response:**
```json
{ "success": false, "error": "Forbidden" }
```
*   **Pass Criteria:** HTTP 403 returned. The system enforces two-reviewer separation — the TL approver cannot also be the Manager approver.

---

**TEST LV-04 — Wrong Supervisor Cannot Approve**

*   **Logged in as:** `hr@alphacorp.com` *(not the employee's supervisor)*
*   **Request:** Same body as LV-02 with `stage: "TL"`.
*   **Expected Response:**
```json
{ "success": false, "error": "Forbidden" }
```
*   **Pass Criteria:** HTTP 403 returned. Only the actual assigned supervisor may act on Stage 1.

---

#### MODULE 3 — Expenses

---

**TEST EX-01 — Submit a Valid Expense Claim**

*   **Logged in as:** `employee@alphacorp.com`
*   **Request:** `POST /api/index.php?route=expenses&action=submit_claim`
*   **Body:**
```json
{
  "amount": 1500.00,
  "category_id": 1,
  "description": "Grab ride to client meeting",
  "receipt_date": "2026-06-28"
}
```
*   **Expected Response:**
```json
{ "success": true, "claim_id": 9 }
```
*   **Pass Criteria:** Claim record created and visible in the manager's approval queue.

---

**TEST EX-02 — Tenant Isolation on Category ID (IDOR)**

*   **Logged in as:** `employee@alphacorp.com`
*   **Request:** Same as EX-01 but use a `category_id` that belongs to **Beta LLC** (e.g., `category_id: 99`).
*   **Expected Response:**
```json
{ "success": false, "error": "Invalid category" }
```
*   **Pass Criteria:** Backend validates `category_id` against the current tenant. Cross-tenant category ID is rejected.

---

**TEST EX-03 — Manager Approves Expense**

*   **Logged in as:** `manager@alphacorp.com`
*   **Request:** `POST /api/index.php?route=expenses&action=decide`
*   **Body:**
```json
{
  "claim_id": 9,
  "decision": "Approved"
}
```
*   **Expected Response:**
```json
{ "success": true, "status": "Approved" }
```
*   **Pass Criteria:** Expense status updates to `Approved`.

---

#### MODULE 4 — Payroll

---

**TEST PAY-01 — Generate Payroll Run (Authorized)**

*   **Logged in as:** `hr@alphacorp.com`
*   **Request:** `POST /api/index.php?route=payroll&action=run`
*   **Body:**
```json
{
  "start_date": "2026-06-01",
  "end_date": "2026-06-15",
  "label": "June 2026 - First Half"
}
```
*   **Expected Response:**
```json
{ "success": true, "payroll_run_id": 3, "payslips_generated": 5 }
```
*   **Pass Criteria:** Payslips generated for all active employees under Alpha Corp only. Note the `payroll_run_id`.

---

**TEST PAY-02 — Generate Payroll (Unauthorized — Employee Role)**

*   **Logged in as:** `employee@alphacorp.com`
*   **Request:** Same as PAY-01.
*   **Expected Response:**
```json
{ "success": false, "error": "Forbidden" }
```
*   **Pass Criteria:** HTTP 403. Payroll run is NOT created.

---

**TEST PAY-03 — Fetch Payslip**

*   **Logged in as:** `employee@alphacorp.com`
*   **Request:** `GET /api/index.php?route=payroll&action=get_payslip&run_id=3`
*   **Expected Response:**
```json
{
  "success": true,
  "payslip": {
    "employee": "Jane Doe",
    "gross_pay": 15000,
    "deductions": { "sss": 450, "philhealth": 200, "pagibig": 100 },
    "net_pay": 14250
  }
}
```
*   **Pass Criteria:** Payslip returns correct deduction breakdown for the logged-in employee only. Employee CANNOT fetch another employee's payslip.

---

**TEST PAY-04 — Generate SSS Remittance Report**

*   **Logged in as:** `hr@alphacorp.com`
*   **Request:** `POST /api/index.php?route=payroll&action=remittance`
*   **Body:**
```json
{
  "payroll_run_id": 3,
  "report_type": "SSS"
}
```
*   **Expected Response:**
```json
{
  "success": true,
  "report": [
    { "employee": "Jane Doe", "employee_share": 450, "employer_share": 900 }
  ]
}
```
*   **Pass Criteria:** Report contains only Alpha Corp employees. Contribution totals match expected statutory rates.

---

#### MODULE 5 — ATS (Applicant Tracking)

---

**TEST ATS-01 — Create a Job Opening**

*   **Logged in as:** `admin@alphacorp.com`
*   **Request:** `POST /api/index.php?route=ats&action=create_job`
*   **Body:**
```json
{
  "title": "Senior PHP Developer",
  "department": "Engineering",
  "location": "Remote",
  "status": "Open"
}
```
*   **Expected Response:**
```json
{ "success": true, "job_id": 7 }
```
*   **Pass Criteria:** Job created and visible on the ATS Pipeline.

---

**TEST ATS-02 — Move Candidate to Next Stage**

*   **Logged in as:** `admin@alphacorp.com`
*   **Request:** `POST /api/index.php?route=ats&action=update_stage`
*   **Body:**
```json
{
  "candidate_id": 12,
  "stage": "Interview"
}
```
*   **Expected Response:**
```json
{ "success": true, "new_stage": "Interview" }
```
*   **Pass Criteria:** Candidate record updated in DB. Stage history logged.

---

**TEST ATS-03 — Employee Cannot Access ATS**

*   **Logged in as:** `employee@alphacorp.com`
*   **Request:** `GET /api/index.php?route=ats&action=list_jobs`
*   **Expected Response:**
```json
{ "success": false, "error": "Forbidden" }
```
*   **Pass Criteria:** HTTP 403. ATS is restricted to Recruiter/Admin/HR roles only.

---

#### MODULE 6 — AI Companion

---

**TEST AI-01 — Send a Clean HR Question**

*   **Logged in as:** `employee@alphacorp.com`
*   **Request:** `POST /api/index.php?route=ai&action=chat`
*   **Body:**
```json
{
  "message": "What is the company's leave policy for sick days?"
}
```
*   **Expected Response:**
```json
{ "success": true, "reply": "Based on company policy, employees are entitled to..." }
```
*   **Pass Criteria:** AI returns a coherent reply. Response time is under 10 seconds (no artificial sleep delay).

---

**TEST AI-02 — PII Scrubbing Verification**

*   **Logged in as:** `employee@alphacorp.com`
*   **Request:** `POST /api/index.php?route=ai&action=chat`
*   **Body:**
```json
{
  "message": "Can you draft an email to John Doe at john.doe@alphacorp.com about his leave?"
}
```
*   **Expected Response:** A coherent reply from the AI.
*   **Pass Criteria:** Check the `global_intelligence_cache` table in the DB. The stored prompt must show `[REDACTED]` replacing the name and email — the raw PII must never appear in the cache or in the Gemini API call payload.

---

### Section 4: Security & Penetration Tests

---

**TEST SEC-01 — CSRF Enforcement**

1.  Complete Step A (get CSRF token).
2.  Complete Step B (log in).
3.  Send a POST request to `?route=expenses&action=submit_claim` but **deliberately omit** the `X-CSRF-Token` header.
*   **Expected:** HTTP `403 Forbidden`.
*   **Pass Criteria:** Request is rejected. No record is created in the database.

---

**TEST SEC-02 — Unauthenticated Access**

1.  Make a fresh request with **no cookie and no CSRF token**.
2.  `GET /api/index.php?route=core_hr&action=fetch_employees`
*   **Expected:** HTTP `401 Unauthorized` or redirect to login.
*   **Pass Criteria:** No employee data is returned.

---

**TEST SEC-03 — RBAC Privilege Escalation**

1.  Log in as `employee@alphacorp.com`.
2.  Attempt `POST /api/index.php?route=payroll&action=run`.
*   **Expected:** HTTP `403 Forbidden`.
*   **Pass Criteria:** Payroll run is not created.

---

**TEST SEC-04 — Exception Masking (No SQL Leakage)**

1.  Log in as `admin@alphacorp.com`.
2.  Send `POST /api/index.php?route=expenses&action=submit_claim` with an invalid payload:
```json
{ "amount": "not_a_number", "category_id": "ABC" }
```
*   **Expected:** HTTP `500` with a generic JSON body:
```json
{ "success": false, "error": "An internal error occurred. Please try again." }
```
*   **Pass Criteria:** No table names, column names, or SQL query fragments appear in the response body. Raw error is written only to the server's `error_log`.

---
*Document generated for Respawn Logics v2.0 Architecture.*
