<?php

namespace App\Http\Controllers;

use App\Models\DtrMonth;
use App\Models\DtrRow;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminEmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $adminCount = $this->adminCount();
        $currentAdmin = $request->user();

        $employees = User::query()
            ->orderByRaw("
                CASE
                    WHEN role = 'admin' THEN 0
                    WHEN role = 'employee' THEN 1
                    ELSE 2
                END
            ")
            ->orderBy('name')
            ->get()
            ->map(function (User $employee) use ($currentAdmin, $adminCount) {
                $isAdmin = $employee->isAdmin();
                $isSelf = $currentAdmin?->is($employee) ?? false;

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'role' => $employee->role,
                    'employee_type' => $employee->employee_type,
                    'intern_compensation_enabled' => (bool) $employee->intern_compensation_enabled,
                    'department' => $employee->department,
                    'company' => $employee->company,
                    'salary_type' => $employee->salary_type,
                    'salary_amount' => $employee->salary_amount !== null ? (float) $employee->salary_amount : null,
                    'initial_paid_leave_days' => (float) ($employee->initial_paid_leave_days ?? 0),
                    'current_paid_leave_balance' => (float) ($employee->current_paid_leave_balance ?? 0),
                    'leave_reset_month' => (int) ($employee->leave_reset_month ?? 1),
                    'leave_reset_day' => (int) ($employee->leave_reset_day ?? 1),
                    'starting_date' => optional($employee->starting_date)->format('Y-m-d'),
                    'default_break_minutes' => (int) ($employee->default_break_minutes ?? 60),
                    'employment_status' => $employee->employment_status,
                    'deactivated_at' => optional($employee->deactivated_at)?->toDateTimeString(),
                    'archived_at' => optional($employee->archived_at)?->toDateTimeString(),
                    'status_reason' => $employee->status_reason,
                    'is_self' => $isSelf,
                    'can_delete_admin' => $isAdmin && ! $isSelf && $adminCount > 1,
                ];
            });

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $employees,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Employees/Create');
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validateUserPayload($request);
        $user = User::create($this->userAttributes($validated, true));

        if (! $user->isAdmin()) {
            $this->generateDtrRowsFromStartDate($user);
        }

        $auditLogger->log(
            $request->user(),
            'user.created',
            'user',
            $user->id,
            null,
            $user->only([
                'name',
                'email',
                'role',
                'employee_type',
                'department',
                'salary_type',
                'salary_amount',
                'initial_paid_leave_days',
                'required_hours',
                'employment_status',
            ]),
            'Created user account.',
            $request
        );

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'User account created successfully.');
    }

    public function edit(User $employee): Response
    {
        return Inertia::render('Admin/Employees/Edit', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'student_no' => $employee->student_no,
                'email' => $employee->email,
                'role' => $employee->role,
                'school' => $employee->school,
                'required_hours' => $employee->required_hours,
                'company' => $employee->company,
                'department' => $employee->department,
                'supervisor_name' => $employee->supervisor_name,
                'employee_type' => $employee->employee_type,
                'intern_compensation_enabled' => (bool) $employee->intern_compensation_enabled,
                'starting_date' => optional($employee->starting_date)->format('Y-m-d'),
                'working_days' => $employee->working_days ?? [1, 2, 3, 4, 5],
                'work_time_in' => $this->normalizeTimeToHi($employee->work_time_in),
                'work_time_out' => $this->normalizeTimeToHi($employee->work_time_out),
                'default_break_minutes' => (int) ($employee->default_break_minutes ?? 60),
                'salary_type' => $employee->salary_type,
                'salary_amount' => $employee->salary_amount !== null ? (float) $employee->salary_amount : null,
                'initial_paid_leave_days' => (float) ($employee->initial_paid_leave_days ?? 0),
                'current_paid_leave_balance' => (float) ($employee->current_paid_leave_balance ?? 0),
                'leave_reset_month' => (int) ($employee->leave_reset_month ?? 1),
                'leave_reset_day' => (int) ($employee->leave_reset_day ?? 1),
            ],
        ]);
    }

    public function update(Request $request, User $employee, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validateUserPayload($request, $employee);
        $targetRole = $validated['role'];
        $this->ensureAdminRoleTransitionIsSafe($request->user(), $employee, $targetRole);

        $before = $employee->only([
            'name',
            'email',
            'role',
            'student_no',
            'school',
            'required_hours',
            'company',
            'department',
            'supervisor_name',
            'employee_type',
            'intern_compensation_enabled',
            'starting_date',
            'working_days',
            'work_time_in',
            'work_time_out',
            'default_break_minutes',
            'salary_type',
            'salary_amount',
            'initial_paid_leave_days',
            'current_paid_leave_balance',
            'leave_reset_month',
            'leave_reset_day',
            'employment_status',
        ]);

        $employee->fill($this->userAttributes($validated, false, $employee));
        $employee->save();

        if (! $employee->isAdmin()) {
            $this->generateDtrRowsFromStartDate($employee);
        }

        $auditLogger->log(
            $request->user(),
            'user.updated',
            'user',
            $employee->id,
            $before,
            $employee->only(array_keys($before)),
            'Updated user account.',
            $request
        );

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $employee, AuditLogger $auditLogger): RedirectResponse
    {
        if (! $employee->isAdmin()) {
            // Legacy endpoint now archives by default to preserve employee history.
            return $this->archive($request, $employee, $auditLogger);
        }

        $this->ensureAdminDeleteIsSafe($request->user(), $employee);
        $before = $employee->only(['name', 'email', 'role']);

        $employeeId = $employee->id;
        $employee->delete();

        $auditLogger->log(
            $request->user(),
            'user.deleted',
            'user',
            $employeeId,
            $before,
            null,
            'Deleted admin account.',
            $request
        );

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Admin account deleted successfully.');
    }

    public function deactivate(Request $request, User $employee, AuditLogger $auditLogger): RedirectResponse
    {
        abort_if($employee->isAdmin(), 404);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);
        $reason = $validated['reason'] ?? 'Deactivated by administrator.';
        $before = $employee->only(['employment_status', 'deactivated_at', 'deactivated_by', 'status_reason']);

        $employee->update([
            'employment_status' => 'inactive',
            'deactivated_at' => now(),
            'deactivated_by' => $request->user()->id,
            'status_reason' => $reason,
        ]);
        $auditLogger->log(
            $request->user(),
            'employee.deactivated',
            'user',
            $employee->id,
            $before,
            $employee->only(['employment_status', 'deactivated_at', 'deactivated_by', 'status_reason']),
            $reason,
            $request
        );

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Employee deactivated successfully.');
    }

    public function archive(Request $request, User $employee, AuditLogger $auditLogger): RedirectResponse
    {
        abort_if($employee->isAdmin(), 404);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);
        $reason = $validated['reason'] ?? 'Archived by administrator.';
        $before = $employee->only(['employment_status', 'archived_at', 'archived_by', 'status_reason']);

        $employee->update([
            'employment_status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
            'status_reason' => $reason,
        ]);
        $auditLogger->log(
            $request->user(),
            'employee.archived',
            'user',
            $employee->id,
            $before,
            $employee->only(['employment_status', 'archived_at', 'archived_by', 'status_reason']),
            $reason,
            $request
        );

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Employee archived successfully.');
    }

    public function reactivate(Request $request, User $employee, AuditLogger $auditLogger): RedirectResponse
    {
        abort_if($employee->isAdmin(), 404);
        $before = $employee->only([
            'employment_status',
            'deactivated_at',
            'deactivated_by',
            'archived_at',
            'archived_by',
            'status_reason',
        ]);

        $employee->update([
            'employment_status' => 'active',
            'deactivated_at' => null,
            'deactivated_by' => null,
            'archived_at' => null,
            'archived_by' => null,
            'status_reason' => null,
        ]);
        $auditLogger->log(
            $request->user(),
            'employee.reactivated',
            'user',
            $employee->id,
            $before,
            $employee->only(array_keys($before)),
            'Reactivated employee account.',
            $request
        );

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Employee reactivated successfully.');
    }

    private function validateUserPayload(Request $request, ?User $employee = null): array
    {
        $employeeId = $employee?->id;
        $passwordRules = $employee
            ? ['nullable', 'confirmed', Rules\Password::defaults()]
            : ['required', 'confirmed', Rules\Password::defaults()];

        return $request->validate([
            'name' => 'required|string|max:255',
            'role' => ['required', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_EMPLOYEE,
                User::ROLE_INTERN,
            ])],
            'student_no' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_INTERN),
                Rule::unique(User::class)->ignore($employeeId),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($employeeId),
            ],
            'password' => $passwordRules,
            'school' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_INTERN),
            ],
            'required_hours' => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_INTERN),
            ],
            'company' => 'nullable|string|max:255',
            'department' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_EMPLOYEE),
            ],
            'supervisor_name' => 'nullable|string|max:255',
            'starting_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
                Rule::requiredIf(fn () => $request->input('role') !== User::ROLE_ADMIN),
            ],
            'working_days' => [
                'nullable',
                'array',
                'min:1',
                Rule::requiredIf(fn () => $request->input('role') !== User::ROLE_ADMIN),
            ],
            'working_days.*' => 'integer|between:0,6',
            'work_time_in' => [
                'nullable',
                'date_format:H:i',
                Rule::requiredIf(fn () => $request->input('role') !== User::ROLE_ADMIN),
            ],
            'work_time_out' => [
                'nullable',
                'date_format:H:i',
                'after:work_time_in',
                Rule::requiredIf(fn () => $request->input('role') !== User::ROLE_ADMIN),
            ],
            'default_break_minutes' => [
                'nullable',
                'integer',
                'in:5,10,15,30,45,60',
                Rule::requiredIf(fn () => $request->input('role') !== User::ROLE_ADMIN),
            ],
            'salary_type' => [
                'nullable',
                'in:monthly,daily,hourly',
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_EMPLOYEE),
            ],
            'salary_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_EMPLOYEE),
            ],
            'initial_paid_leave_days' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_EMPLOYEE),
            ],
            'current_paid_leave_balance' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'leave_reset_month' => 'nullable|integer|between:1,12',
            'leave_reset_day' => 'nullable|integer|between:1,31',
            'intern_compensation_enabled' => [
                'nullable',
                'boolean',
                Rule::requiredIf(fn () => $request->input('role') === User::ROLE_INTERN),
            ],
        ]);
    }

    private function userAttributes(array $validated, bool $isCreate, ?User $existing = null): array
    {
        $role = $validated['role'];
        $isAdmin = $role === User::ROLE_ADMIN;
        $isIntern = $role === User::ROLE_INTERN;
        $isEmployee = $role === User::ROLE_EMPLOYEE;
        $initialLeave = round((float) ($validated['initial_paid_leave_days'] ?? 0), 2);
        $currentLeave = round(
            (float) (
                array_key_exists('current_paid_leave_balance', $validated)
                    ? ($validated['current_paid_leave_balance'] !== null && $validated['current_paid_leave_balance'] !== ''
                        ? $validated['current_paid_leave_balance']
                        : $initialLeave)
                    : $initialLeave
            ),
            2
        );

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'student_name' => $isIntern ? $validated['name'] : null,
            'student_no' => $isIntern ? ($validated['student_no'] ?? null) : null,
            'school' => $isIntern ? ($validated['school'] ?? null) : null,
            'required_hours' => $isIntern ? (int) ($validated['required_hours'] ?? 0) : 0,
            'company' => $isAdmin ? null : ($validated['company'] ?? null),
            'department' => $isEmployee ? ($validated['department'] ?? null) : null,
            'supervisor_name' => $isIntern ? ($validated['supervisor_name'] ?? null) : null,
            'supervisor_position' => null,
            'role' => $role,
            'employee_type' => $isAdmin ? null : ($isIntern ? 'intern' : 'regular'),
            'intern_compensation_enabled' => $isIntern
                ? (bool) ($validated['intern_compensation_enabled'] ?? $existing?->intern_compensation_enabled ?? false)
                : ($isEmployee ? true : false),
            'starting_date' => $isAdmin ? null : $validated['starting_date'],
            'working_days' => $isAdmin ? null : $validated['working_days'],
            'work_time_in' => $isAdmin ? null : $validated['work_time_in'],
            'work_time_out' => $isAdmin ? null : $validated['work_time_out'],
            'default_break_minutes' => (int) ($validated['default_break_minutes'] ?? 60),
            'salary_type' => $isEmployee ? ($validated['salary_type'] ?? null) : null,
            'salary_amount' => $isEmployee ? ($validated['salary_amount'] ?? null) : null,
            'initial_paid_leave_days' => $isEmployee ? $initialLeave : 0,
            'current_paid_leave_balance' => $isEmployee ? $currentLeave : 0,
            'leave_reset_month' => (int) ($validated['leave_reset_month'] ?? 1),
            'leave_reset_day' => (int) ($validated['leave_reset_day'] ?? 1),
            'employment_status' => $existing?->employment_status ?? 'active',
        ];

        if ($isCreate) {
            $attributes['password'] = Hash::make($validated['password']);
            $attributes['email_verified_at'] = now();
        } elseif (! empty($validated['password'])) {
            $attributes['password'] = Hash::make($validated['password']);
        }

        return $attributes;
    }

    private function ensureAdminRoleTransitionIsSafe(User $actor, User $target, string $targetRole): void
    {
        if (! $target->isAdmin() || $targetRole === User::ROLE_ADMIN) {
            return;
        }

        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                'role' => 'You cannot remove your own admin role.',
            ]);
        }

        if ($this->adminCount() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'At least one admin account must remain in the system.',
            ]);
        }
    }

    private function ensureAdminDeleteIsSafe(User $actor, User $target): void
    {
        if (! $target->isAdmin()) {
            return;
        }

        if ($this->adminCount() <= 1) {
            throw ValidationException::withMessages([
                'email' => 'You cannot delete the last admin account.',
            ]);
        }

        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                'email' => 'You cannot delete your own admin account.',
            ]);
        }
    }

    private function adminCount(): int
    {
        return User::query()
            ->where(function ($query) {
                $query->where('role', User::ROLE_ADMIN)
                    ->orWhere('is_admin', true);
            })
            ->count();
    }

    private function generateDtrRowsFromStartDate(User $user): void
    {
        if (! $user->starting_date || empty($user->working_days)) {
            return;
        }

        $timezone = 'Asia/Manila';
        $startDate = Carbon::parse($user->starting_date, $timezone)->startOfDay();
        $today = Carbon::now($timezone)->startOfDay();
        $workingDays = collect($user->working_days ?? [])->map(fn ($day) => (int) $day)->all();

        for ($date = $startDate->copy(); $date->lte($today); $date->addDay()) {
            if (! in_array($date->dayOfWeek, $workingDays, true)) {
                continue;
            }

            $month = DtrMonth::firstOrCreate([
                'user_id' => $user->id,
                'month' => $date->month,
                'year' => $date->year,
            ]);

            DtrRow::firstOrCreate(
                [
                    'dtr_month_id' => $month->id,
                    'date' => $date->format('Y-m-d'),
                ],
                [
                    'day' => $date->format('l'),
                    'time_in' => null,
                    'time_out' => null,
                    'total_minutes' => 0,
                    'break_minutes' => 0,
                    'late_minutes' => 0,
                    'on_break' => false,
                    'break_started_at' => null,
                    'break_target_minutes' => null,
                    'status' => 'draft',
                ]
            );
        }
    }

    private function normalizeTimeToHi(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i:s', $time)->format('H:i');
        } catch (\Throwable $e) {
            try {
                return Carbon::createFromFormat('H:i', $time)->format('H:i');
            } catch (\Throwable $e) {
                return null;
            }
        }
    }
}
