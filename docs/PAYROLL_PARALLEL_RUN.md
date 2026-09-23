# Payroll Parallel-Run Playbook

For beta clients, running a parallel payroll is a critical validation step before fully migrating off their legacy system. The goal is to run one full payroll cycle (e.g., a semi-monthly cutoff) in both systems concurrently, compare the final payout and deductions, and resolve any discrepancies.

This process establishes trust in the automated calculation engine and highlights any custom edge cases the client might have.

## Phase 1: Setup

1. **Import Master Data**: Import all employees into Respawn Logics via the `Core HR` import tools. Ensure base salaries and standard deductions (SSS, PhilHealth, Pag-IBIG) are configured.
2. **Configure Tenant Payroll Settings**: In `Tenant Settings > Payroll Settings`, ensure that the cutoff cycle, standard pay basis, and statutory basis match the legacy system.
3. **Verify Timesheets**: At the end of the cutoff period, input or import timesheet data exactly as it appears in the legacy system. Ensure hours worked, overtime, night differential, and holiday hours match perfectly.

## Phase 2: Parallel Execution

1. **Run Legacy System**: Execute the payroll run in the legacy system as usual. Lock the run and extract the gross pay, deductions, taxes, and net pay for each employee.
2. **Run Respawn Logics**: Generate the payroll run in Respawn Logics for the exact same cutoff period. Do not click "Approve/Disburse" yet.
3. **Extract Comparison Data**: Export the payroll register from Respawn Logics for side-by-side comparison.

## Phase 3: Variance Analysis

Perform a line-by-line comparison of:
- **Gross Pay**: Check for discrepancies in daily rate conversion and absence deductions.
- **Premium Pay**: Verify holiday overlaps (e.g., Rest Day on a Regular Holiday) and night differential.
- **Statutory Deductions**: Compare SSS, PhilHealth, and Pag-IBIG values. Small variations often point to differences in cutoff splitting (e.g., taking SSS on the 1st vs 2nd cutoff).
- **Withholding Tax**: Compare annualized or tabular tax deductions based on the TRAIN table bracket.

### Common Variances (Beta Limitations)
As noted in the Beta Agreement, differences may arise due to manual interventions required in this beta release:
- Complex premium-pay overlaps.
- Untracked loan balance deductions.
- Unworked holiday eligibility rules.

If the variance is due to a known beta limitation, apply a manual adjustment in Respawn Logics for this cycle. If it highlights a true calculation error in the engine, halt the parallel run and escalate the issue for patching.

## Phase 4: Sign-off

Once net pay matches within an acceptable rounding threshold (e.g., ± 1 PHP per employee), the parallel run is considered successful. 

The client may officially sign-off on the parallel run and transition solely to Respawn Logics for the subsequent cutoff.
