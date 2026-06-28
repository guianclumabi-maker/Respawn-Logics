# M5 #18: Attendance-Driven Gross Pay Implementation Plan

## Goal
Replace the "perfect attendance assumption" in the payroll engine with a configurable, attendance-driven gross pay calculation based on approved timesheets. Seed DOLE multipliers into configuration.

## Proposed Changes

### 1. Database Migrations
- **Table Creation**: Create a `timesheets` table to store daily approved hours:
  - `id`, `tenant_id`, `employee_id`, `timesheet_date`, `status`
  - `regular_hours`, `overtime_hours`, `rest_day_hours`, `special_day_hours`, `regular_holiday_hours`, `night_diff_hours`
- **Config Seeding**: Insert DOLE default multipliers into `statutory_parameters`:
  - `working_days_per_year`: 313.00
  - `hours_per_day`: 8.00
  - `ordinary_ot_multiplier`: 1.25
  - `night_diff_multiplier`: 0.10
  - `rest_day_or_special_multiplier`: 1.30
  - `rest_day_special_ot_multiplier`: 1.69
  - `regular_holiday_multiplier`: 2.00
  - `regular_holiday_ot_multiplier`: 2.60

### 2. `PayrollService.php` Updates
- **Config Loading**: Read the new DOLE parameters into `$this->statutoryParams` so they can be managed by a CPA.
- **Gross Pay Computation**:
  - For each employee, query `timesheets` where `status = 'Approved'` within the payroll run's date range (`$start` to `$end`).
  - Calculate the hourly rate: `(base_salary * 12 / working_days_per_year) / hours_per_day`.
  - Build `grossPay` by multiplying hours by the correct multiplier and hourly rate.
  - Record each component (Basic, Overtime, Night Diff, etc.) explicitly in `payroll_earnings` instead of a monolithic "Basic Salary".
  - If no timesheet is found, set gross Basic Pay to 0 and generate a warning.

### 3. `PayrollController.php` Updates
- **Exceptions List**: Add logic to check the active run period. If an active employee has absolutely no approved timesheets in the `timesheets` table for that period, flag them as an exception (`Unapproved / Missing Timesheets`).

## Verification Plan
- Run the schema and config seeding directly on the local database.
- Review `php -l` checks for both files.
- Verify `PayrollService` successfully pulls values from config instead of inline constants.
