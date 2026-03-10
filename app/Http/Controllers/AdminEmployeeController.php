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
use Inertia\Inertia;
use Inertia\Response;

class AdminEmployeeController extends Controller
{
    public function index(): Response
    {
        $employees = User::query()
            ->where('is_admin', false)
            ->orderBy('name')
            ->get()
            ->map(function (User $employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
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
        $validated = $this->validateEmployeePayload($request);

        $employee = User::create($this->employeeAttributes($validated, true));

        $this->generateDtrRowsFromStartDate($employee);
        $auditLogger->log(
            $request->user(),
            'employee.created',
            'user',
            $employee->id,
            null,
            $employee->only([
                'name',
                'email',
                'employee_type',
                'intern_compensation_enabled',
                'department',
                'company',
                'salary_type',
                'salary_amount',
                'employment_status',
            ]),
            'Created employee account.',
            $request
        );

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Employee account created successfully.');
    }

    public function edit(User $employee): Response
    {
        abort_if($employee->is_admin, 404);

        return Inertia::render('Admin/Employees/Edit', [
            'employee' => [
                'id' => $employee->id,
                'student_name' => $employee->student_name ?? $employee->name,
                'student_no' => $employee->student_no,
                'email' => $employee->email,
                'school' => $employee->school,
                'required_hours' => $employee->required_hours,
                'company' => $employee->company,
                'department' => $employee->department,
                'supervisor_name' => $employee->supervisor_name,
                'supervisor_position' => $employee->supervisor_position,
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
        abort_if($employee->is_admin, 404);
        $validated = $this->validateEmployeePayload($request, $employee);

        $before = $employee->only([
            'name',
            'email',
            'student_name',
            'student_no',
            'school',
            'required_hours',
            'company',
            'department',
            'supervisor_name',
            'supervisor_position',
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
        $employee->fill($this->employeeAttributes($validated, false));
        $employee->save();
        $this->generateDtrRowsFromStartDate($employee);
        $auditLogger->log(
            $request->user(),
            'employee.updated',
            'user',
            $employee->id,
            $before,
            $employee->only(array_keys($before)),
            'Updated employee profile/settings.',
            $request
        );

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(Request $request, User $employee, AuditLogger $auditLogger): RedirectResponse
    {
        // Legacy endpoint now archives by default to preserve history.
        return $this->archive($request, $employee, $auditLogger);
    }

    public function deactivate(Request $request, User $employee, AuditLogger $auditLogger): RedirectResponse
    {
        abort_if($employee->is_admin, 404);

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
        abort_if($employee->is_admin, 404);

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
        abort_if($employee->is_admin, 404);
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

    private function validateEmployeePayload(Request $request, ?User $employee = null): array
    {
        $employeeId = $employee?->id;
        $passwordRules = $employee
            ? ['nullable', 'confirmed', Rules\Password::defaults()]
            : ['required', 'confirmed', Rules\Password::defaults()];

        return $request->validate([
            'student_name' => 'required|string|max:255',
            'student_no' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => $request->input('employee_type') === 'intern'),
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
                Rule::requiredIf(fn () => $request->input('employee_type') === 'intern'),
            ],
            'required_hours' => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(fn () => $request->input('employee_type') === 'intern'),
            ],
            'intern_compensation_enabled' => 'nullable|boolean',
            'company' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'supervisor_name' => 'required|string|max:255',
            'supervisor_position' => 'required|string|max:255',
            'employee_type' => 'required|in:intern,regular',
            'starting_date' => 'required|date|before_or_equal:today',
            'working_days' => 'required|array|min:1',
            'working_days.*' => 'integer|between:0,6',
            'work_time_in' => 'required|date_format:H:i',
            'work_time_out' => 'required|date_format:H:i|after:work_time_in',
            'default_break_minutes' => 'required|integer|in:5,10,15,30,45,60',
            'salary_type' => [
                'nullable',
                'in:monthly,daily,hourly',
                Rule::requiredIf(function () use ($request) {
                    $type = $request->input('employee_type');
                    $internCompensation = filter_var($request->input('intern_compensation_enabled'), FILTER_VALIDATE_BOOL);

                    return $type === 'regular' || ($type === 'intern' && $internCompensation);
                }),
            ],
            'salary_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                Rule::requiredIf(function () use ($request) {
                    $type = $request->input('employee_type');
                    $internCompensation = filter_var($request->input('intern_compensation_enabled'), FILTER_VALIDATE_BOOL);

                    return $type === 'regular' || ($type === 'intern' && $internCompensation);
                }),
            ],
            'initial_paid_leave_days' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn () => $request->input('employee_type') === 'regular'),
            ],
            'current_paid_leave_balance' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'leave_reset_month' => 'nullable|integer|between:1,12',
            'leave_reset_day' => 'nullable|integer|between:1,31',
        ]);
    }

    private function employeeAttributes(array $validated, bool $isCreate): array
    {
        $isIntern = ($validated['employee_type'] ?? null) === 'intern';
        $internCompensationEnabled = $isIntern
            ? (bool) ($validated['intern_compensation_enabled'] ?? false)
            : true;

        $attributes = [
            'name' => $validated['student_name'],
            'email' => $validated['email'],
            'student_name' => $validated['student_name'],
            'student_no' => $isIntern ? ($validated['student_no'] ?? null) : null,
            'school' => $isIntern ? ($validated['school'] ?? null) : null,
            'required_hours' => $isIntern ? ($validated['required_hours'] ?? 0) : 0,
            'company' => $validated['company'],
            'department' => $validated['department'],
            'supervisor_name' => $validated['supervisor_name'],
            'supervisor_position' => $validated['supervisor_position'],
            'employee_type' => $validated['employee_type'],
            'intern_compensation_enabled' => $internCompensationEnabled,
            'starting_date' => $validated['starting_date'],
            'working_days' => $validated['working_days'],
            'work_time_in' => $validated['work_time_in'],
            'work_time_out' => $validated['work_time_out'],
            'default_break_minutes' => (int) ($validated['default_break_minutes'] ?? 60),
            'salary_type' => $internCompensationEnabled ? ($validated['salary_type'] ?? null) : null,
            'salary_amount' => $internCompensationEnabled ? ($validated['salary_amount'] ?? null) : null,
            'initial_paid_leave_days' => $isIntern
                ? 0
                : round((float) ($validated['initial_paid_leave_days'] ?? 0), 2),
            'current_paid_leave_balance' => $isIntern
                ? 0
                : round(
                    (float) (
                        array_key_exists('current_paid_leave_balance', $validated)
                            ? ($validated['current_paid_leave_balance'] !== null && $validated['current_paid_leave_balance'] !== ''
                                ? $validated['current_paid_leave_balance']
                                : ($validated['initial_paid_leave_days'] ?? 0))
                            : ($validated['initial_paid_leave_days'] ?? 0)
                    ),
                    2
                ),
            'leave_reset_month' => (int) ($validated['leave_reset_month'] ?? 1),
            'leave_reset_day' => (int) ($validated['leave_reset_day'] ?? 1),
            'employment_status' => 'active',
        ];

        if ($isCreate) {
            $attributes['password'] = Hash::make($validated['password']);
            $attributes['email_verified_at'] = now();
            $attributes['is_admin'] = false;
        } elseif (! empty($validated['password'])) {
            $attributes['password'] = Hash::make($validated['password']);
        }

        return $attributes;
    }

    private function generateDtrRowsFromStartDate(User $user): void
    {
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
