# Respawn Logics — Payroll Formula Register & CPA Sign-Off Package

**Purpose.** This document is the single walkthrough a CPA needs to validate the payroll engine. Every formula lists: what it computes, its legal basis, where it lives in code, what automated test proves it, and what still requires professional judgment. Nothing in this document claims legal certification — it exists so a CPA can grant or withhold it efficiently.

**Engine:** `backend/services/PayrollService.php` · **Statutory tables (versioned):** `backend/migrations/migrate_statutory_rates.php` · **Tests:** `tests/Integration/` (PayrollServiceTest, PayrollReferenceOracleTest, PayrollHardeningTest)

---

## PART 1 — Open questions requiring CPA decision (ranked)

### Q1 (RESOLVED IN CODE — CPA to CONFIRM): Monthly-paid basic pay
**Implemented (default `monthly_pay_mode='fixed_salary'`):** monthly staff receive their fixed cutoff salary MINUS absence deductions (absent scheduled workday × daily rate, Mon–Fri workweek, calendar holidays neither paid extra nor deducted). Under the 313-day divisor the salary already includes holidays, so worked-holiday premiums pay only the EXCESS on top (regular holiday +100%, special day +30%); OT, rest-day work, and night diff are fully on top. The legacy hours-proxy survives as opt-in `monthly_pay_mode='hours_proxy'`.
**This resolution enabled the full Reference Oracle:** tax and net for ₱18k/30k/50k/90k are now exact-value asserted against the TRAIN monthly table (see `PayrollReferenceOracleTest` — 20 assertions).
**CPA confirms:** (a) excess-only holiday premiums are correct for the 313 divisor and must switch to full 200%/130% + special-day absence deduction under a 261 divisor; (b) Mon–Fri workweek assumption; (c) absence valuation at straight daily rate.

### Q2: Statutory contribution basis for non-monthly staff
`tenant_payroll_settings.statutory_basis` = `monthly_base` (default; MSC = fixed monthly salary) or `actual_period_equivalent` (MSC = actual period pay scaled to monthly). Daily/hourly pay basis combined with `monthly_base` emits a run warning. **Decision needed per client:** which basis matches their remittance practice for no-work-no-pay staff.

### Q3: Premium-pay simplifications (documented in `calculatePremiumPay()`)
The timesheet schema cannot express overlapping conditions, so: (a) rest day that is ALSO a special day pays 1.30 not 1.50; (b) OT on rest days/holidays pays ordinary 1.25 instead of compounding; (c) night-diff hours do not compound with OT/holiday premiums. All three UNDER-pay edge cases (never over-pay). **Decision needed:** acceptable interim policy, or prioritize schema work (overlap hour columns).

### Q4: Divisor & hours defaults
313 working days/year and 8 hours/day come from `statutory_parameters` (rates ≤ 0 throw). Employers using 365/261/26-day factors (policy or CBA) must configure them. **Confirm per client.**

### Q5: Unworked regular-holiday eligibility
Unworked regular holidays are auto-drafted at 100% base (Labor Code Art. 94) for all active scheduled employees. The Art. 94 eligibility condition (present or on paid leave the workday before) is NOT modeled — reviewers un-approve exceptions manually. Holiday-falls-on-rest-day is skipped (separate rule, not modeled).

### Q6: Paid-leave default
Approved leave feeds timesheets as paid regular hours unless the type name matches /unpaid|lwop/i. **Confirm each client's leave-type taxonomy.**

### Q7: De-minimis "Days"-frequency items
Monetized unused VL ("Days" frequency) is treated fully taxable pending rate context. **Confirm intended handling.**

### Q8: 13th-month basic-salary scope
Accrual = regular-hours pay / 12 (excludes OT, premiums, allowances) per PD 851's "basic salary" reading. **Confirm against company policy/CBA integration rules.**

---

## PART 2 — Formula register (implemented & tested)

| # | Computation | Legal basis | Implementation | Test evidence |
|---|---|---|---|---|
| 1 | **SSS** EE 5% / ER 10% of MSC; floor ₱5,000, ceiling ₱35,000, ₱500 brackets; MSC > ₱20,000 → MPF/WISP; EC ₱10/₱30 | RA 11199 (2025–26 schedule) | Versioned brackets table; `calculateSSS()` | Oracle: 18k→900, 30k→1,500, 50k/90k→1,750 (exact, 2026-verified) |
| 2 | **PhilHealth** 5% of monthly basic split 50/50; floor ₱10,000 / ceiling ₱100,000 | RA 11223 (2026 rate) | `philhealth_config`; `calculatePhilHealth()` | Oracle: 450 / 750 / 1,250 / 2,250 (exact) |
| 3 | **Pag-IBIG** EE 2% (1% ≤ ₱1,500), ER 2%, fund-salary cap ₱10,000 | HDMF Circular (2026) | `pagibig_config`; `calculatePagIbig()` | Oracle: 200 for all cases ≥ 10k (exact) |
| 4 | **BIR withholding** per-frequency bracket tables (Monthly/Semi/Weekly/Daily); taxable = gross work pay + taxable benefits − EE statutory | TRAIN (RA 10963) revised withholding tables | `bir_withholding_brackets`; `calculateTax()` | Bracket lookup tested via reconciliation; exact-value cases blocked on Q1 |
| 5 | **MWE exemption** — statutory-minimum earners: work income untaxed; only taxable benefits taxed | RA 9504 | `is_mwe` flag branch | PayrollServiceTest fixtures (is_mwe=0 controls) |
| 6 | **₱90k cap** for 13th month + other benefits; exempt de minimis does NOT consume the cap; de-minimis EXCESS flows into the cap | NIRC §32(B)(7)(e); RR 11-2018 | `getRemaining90kExemption()` (de-minimis rows excluded), `getDeMinimisExemption()` per-item ceilings | PayrollHardeningTest::testExemptDeMinimisDoesNotConsume90kBucket |
| 7 | **13th-month pay** = Σ(year's basic accruals) = annual basic /12; separate run type pays it exclusively; split non-taxable/taxable vs remaining cap | PD 851 | `getThirteenthMonthAccrued()` | PayrollServiceTest (accrual + reconciliation); HardeningTest (cap interaction) |
| 8 | **Premium pay**: OT 1.25 · rest/special 1.30 · regular holiday 2.00 · ND +10% | Labor Code Arts. 87/91–94; configurable multipliers | `calculatePremiumPay()` (pure function) | Extraction is result-identical; simplifications = Q3 |
| 9 | **Pay basis**: monthly_fixed / daily / hourly rate derivation; unknown basis throws | — (engineering guard) | `resolvePayBasis()`, `calculateHourlyRates()` | HardeningTest daily (10d×₱1,000→₱10,000), hourly (40h×₱150→₱6,000) |
| 10 | **Unworked regular holiday** 100% base auto-draft | Labor Code Art. 94 | `TimesheetController::generateDraft` | Manual smoke (eligibility = Q5) |
| 11 | **Paid leave → payroll** | Labor Code / company policy | `generateDraft` leave block | Response counters; policy default = Q6 |
| 12 | **Loans** — fixed amortization deduction, warning that balance tracking is absent | — | component loop | HardeningTest::testLoanAmortization… |
| 13 | **Payslip reconciliation**: Σ earnings = gross; gross − Σ deductions = net; ER shares stored separately (never reduce net) | — (accounting invariant) | insert paths | PayrollServiceTest (core invariant suite) |

## PART 3 — Fail-loud inventory (what refuses to compute)

Missing/expired statutory config for the pay date · unknown pay basis · unknown statutory basis · invalid divisors (≤0) · tax annualization enabled (unimplemented — year-end adjustment is manual until built) · unimplemented component calc types (`statutory`, `attendance_derived`, `formula`) · foreign-tenant schedules · no approved timesheets on a Regular run · suspended employees excluded (SuspensionRegressionTest). Every case returns a specific error; nothing silently computes wrong pay.
Tests: PayrollHardeningTest (annualization, component types), PayrollServiceTest (no-timesheets, foreign schedule).

## PART 4 — Rate-change procedure (versioning)

Statutory rates are data, not code: each table carries `effective_from/effective_to`; the seeder versions by natural key (per BIR frequency, per de-minimis item, per param key) and is idempotent — re-running never duplicates active rows and never closes unrelated ones. Historical runs stay computed under their period's rates. When SSS/PhilHealth/BIR change: add a new versioned row set, deploy, done.

## PART 5 — Sign-off checklist

- [ ] Q1–Q8 decisions recorded (attach memo)
- [ ] Oracle contribution values re-verified against current official tables at sign-off date
- [ ] One real-client parallel run compared against incumbent payroll (1 cycle minimum)
- [ ] Tax/net oracle values filled and green after Q1 resolution
- [ ] Multipliers/divisors configured per client policy

Reviewed by: ______________________ CPA · License no.: __________ · Date: __________
