# DTR System Overview

Daily Time Record (DTR) web application built with Laravel 12 + Inertia React.

## Current Product State

- Public self-registration is disabled.
- Accounts are created and managed by admins from `Admin > Users`.
- Authentication uses email or student number plus password.
- Attendance is generated from employee setup (start date + working days).
- Payroll supports generated -> reviewed -> finalized lifecycle.
- Finalized payroll locks overlapping attendance dates.

## Core Modules

- Attendance:
  - `app/Http/Controllers/DtrMonthController.php`
  - `app/Http/Controllers/DtrRowController.php`
  - `app/Http/Controllers/AdminAttendanceController.php`
- Payroll:
  - `app/Http/Controllers/PayrollController.php`
  - `app/Http/Controllers/AdminPayrollController.php`
  - `app/Services/PayrollCalculator.php`
  - `app/Services/PayrollLockService.php`
- Leave:
  - `app/Http/Controllers/EmployeeLeaveController.php`
  - `app/Http/Controllers/AdminLeaveController.php`
  - `app/Services/LeaveBalanceService.php`
- Holiday:
  - `app/Http/Controllers/EmployeeHolidayController.php`
  - `app/Http/Controllers/AdminHolidayController.php`
- Audit:
  - `app/Services/AuditLogger.php`
  - `app/Http/Controllers/AdminAuditController.php`

## Key Routes

- Entry: `/` (redirects authenticated users by role)
- User DTR: `/dtr`
- User payroll: `/payroll`
- Admin users: `/admin/employees`
- Admin attendance: `/admin/attendance`
- Admin payroll: `/admin/payroll`
- Admin leave: `/admin/leaves`
- Admin holidays: `/admin/holidays`

## Data Model Notes

- `users`: role, lifecycle status, schedule, salary, leave balances, profile photo.
- `dtr_months`: month container per user.
- `dtr_rows`: day-level attendance row, break/late/status fields.
- `leave_requests`: pending/approved/rejected/cancelled leave and intern absence requests.
- `payroll_records`: computed payroll snapshots, lifecycle metadata, payslip file refs.
- `holidays`: paid/unpaid holiday config + attendance bonus config.
- `audit_logs`: actor/action/entity before/after snapshots.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
npm run dev
```

## Tests

```bash
php artisan test
```

- Default test config uses SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).
- Ensure `pdo_sqlite` is installed in your PHP runtime before running feature tests.
