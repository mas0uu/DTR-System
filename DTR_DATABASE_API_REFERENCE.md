# DTR System: Database and API Reference

This reference reflects the current implementation in this repository.

## Stack

- Backend: Laravel 12
- Frontend: Inertia + React + TypeScript
- Auth: Session-based (Laravel auth)
- Timezone baseline: `Asia/Manila`

## Core Data Model

### users

Primary account table used for admins, regular employees, and interns.

Key fields used by business logic:
- Identity and auth: `id`, `name`, `email`, `password`, `email_verified_at`
- Role model: `role` (`admin|employee|intern`), `is_admin` (legacy compatibility)
- Employee profile: `employee_type`, `department`, `company`, `employment_status`
- Intern profile: `student_no`, `school`, `required_hours`, `supervisor_name`
- Attendance defaults: `starting_date`, `working_days` (JSON), `work_time_in`, `work_time_out`, `default_break_minutes`
- Compensation: `salary_type`, `salary_amount`, `intern_compensation_enabled`
- Leave balances: `initial_paid_leave_days`, `current_paid_leave_balance`, `leave_reset_month`, `leave_reset_day`

### dtr_months

Month container per user.

Important fields:
- `user_id`, `month`, `year`, `is_fulfilled`

Constraints:
- Unique `(user_id, month, year)`

### dtr_rows

Day-level attendance records.

Important fields:
- `dtr_month_id`, `date`, `day`
- `time_in`, `time_out`
- `break_minutes`, `late_minutes`, `total_minutes`
- `status` (`draft|in_progress|finished|leave|missed`)
- Break state: `on_break`, `break_started_at`, `break_target_minutes`

Constraints:
- Unique `(dtr_month_id, date)`

### leave_requests

Tracks leave and intern absence requests tied to attendance rows.

### payroll_records

Payroll snapshots with lifecycle state.

Important fields:
- `user_id`
- `pay_period_start`, `pay_period_end`
- Computed totals (`days_worked`, `hours_worked`, `absences`, `undertime_minutes`, `half_days`, `total_salary`)
- Lifecycle: `status` (`generated|reviewed|finalized`), review/finalization metadata

Behavior note:
- Finalized payroll records lock overlapping attendance dates.

### holidays

Configurable holiday rules used in attendance/payroll computations.

### audit_logs

Tracks actor/action/entity changes with before/after snapshots and context.

## Main Relationships

- `User` has many `DtrMonth`
- `DtrMonth` has many `DtrRow`
- `DtrRow` belongs to `DtrMonth`
- `DtrRow` has one `LeaveRequest` (when filed)
- `User` has many `PayrollRecord`

## Auth and Access Rules

- Public registration is disabled.
- Login routes are available for guests (`/login`, password reset flow).
- Account provisioning is admin-only via `/admin/employees`.
- Admin pages are protected by `auth` + `admin` middleware.
- Employee pages are protected by `auth` + `active_employee` middleware.

## HTTP Routes (Current)

### Public / Auth
- `GET /` -> welcome page or role-based redirect when authenticated
- `GET /login`, `POST /login`
- Password reset endpoints (`/forgot-password`, `/reset-password/{token}`, etc.)
- `POST /logout`

### Employee Area
- `GET /dtr`
- `GET /dtr/months`, `POST /dtr/months`
- `GET /dtr/months/{month}`
- `PATCH /dtr/months/{month}/finish`, `DELETE /dtr/months/{month}`
- `POST /dtr/rows`
- `PATCH /dtr/rows/{row}`
- `PATCH /dtr/rows/{row}/clock-in`
- `PATCH /dtr/rows/{row}/clock-out`
- `PATCH /dtr/rows/{row}/break/start`
- `PATCH /dtr/rows/{row}/break/finish`
- `PATCH /dtr/rows/{row}/leave`
- `DELETE /dtr/rows/{row}`
- `GET /leaves`
- `GET /holidays`
- `GET /payroll`
- `POST /payroll/generate`
- Payslip view/download endpoints under `/payroll/{payrollRecord}/...`

### Admin Area (`/admin`)
- Employee management: CRUD + deactivate/archive/reactivate
- Attendance: index, logs, employee detail, row update/correction
- Payroll: index, generate single/all, review/finalize/delete, payslip view/download
- Leaves: list, decision, balance adjustment
- Holidays: list/create/update/delete
- Anomalies, intern progress, audit logs

## Important Business Rules

- Payroll generation is restricted to periods within a single calendar month.
- Finalized payroll locks all overlapping attendance dates (including cross-month periods).
- Correcting a locked attendance row resets overlapping finalized payroll record(s) back to `generated`.
- Clocking out while on break accrues the ongoing break duration before computing total work minutes.

## Validation Highlights

- Role-specific employee creation/update validation:
  - `admin`: no attendance/payroll profile required
  - `employee`: salary and leave baseline required
  - `intern`: school/student fields + required hours required
- Attendance row updates enforce format and logical constraints (`time_out` after `time_in`, break bounds, valid status values).

## Testing Notes

- Feature tests are configured for SQLite in-memory by default.
- `pdo_sqlite` must be enabled in the PHP runtime to execute feature tests.

## Source of Truth

For exact behavior, refer to:
- `routes/web.php`
- `routes/auth.php`
- Controllers under `app/Http/Controllers`
- Services under `app/Services`
- Migrations under `database/migrations`

