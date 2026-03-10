<?php

namespace App\Http\Controllers;

use App\Models\DtrMonth;
use App\Models\DtrRow;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PayrollLockService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAttendanceController extends Controller
{
    private const DEFAULT_SHIFT_START = '08:00:00';
    private const DEFAULT_GRACE_MINUTES = 30;

    public function index(): Response
    {
        $employees = User::query()
            ->where('is_admin', false)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department', 'company', 'employee_type'])
            ->map(function (User $employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'department' => $employee->department,
                    'company' => $employee->company,
                    'employee_type' => $employee->employee_type,
                ];
            });

        return Inertia::render('Admin/Attendance/Index', [
            'employees' => $employees,
        ]);
    }

    public function logs(): Response
    {
        return Inertia::render('Admin/Attendance/Logs', [
            'attendance_logs' => $this->attendanceLogsPayload(),
        ]);
    }

    public function show(Request $request, User $employee, PayrollLockService $payrollLockService): Response|RedirectResponse
    {
        abort_if($employee->is_admin, 404);

        $months = DtrMonth::query()
            ->where('user_id', $employee->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(function (DtrMonth $month) {
                return [
                    'id' => $month->id,
                    'month' => $month->month,
                    'year' => $month->year,
                    'month_name' => Carbon::createFromDate($month->year, $month->month, 1)->format('F Y'),
                    'total_hours' => round(
                        $month->rows()->where('status', 'finished')->sum('total_minutes') / 60,
                        2
                    ),
                    'finished_rows' => $month->rows()->where('status', 'finished')->count(),
                ];
            });

        if ($months->isEmpty()) {
            return Inertia::render('Admin/Attendance/Show', [
                'employee' => $this->employeePayload($employee),
                'months' => [],
                'selected_month' => null,
                'rows' => [],
            ]);
        }

        $requestedMonthId = (int) $request->query('month_id', 0);
        $selectedMonth = $requestedMonthId > 0
            ? DtrMonth::query()->where('id', $requestedMonthId)->where('user_id', $employee->id)->first()
            : null;

        if (! $selectedMonth) {
            $selectedMonth = DtrMonth::query()
                ->where('user_id', $employee->id)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->first();
        }

        $expectedDailyMinutes = $this->expectedDailyMinutes(
            $employee->work_time_in,
            $employee->work_time_out,
            (int) ($employee->default_break_minutes ?? 60)
        );
        $rows = $selectedMonth
            ? $selectedMonth->rows()->orderBy('date')->get()->map(function (DtrRow $row) use ($expectedDailyMinutes, $employee, $payrollLockService) {
                $flags = $this->detectFlags($row, $expectedDailyMinutes);
                return [
                    'id' => $row->id,
                    'date' => $row->date->format('Y-m-d'),
                    'day' => $row->day,
                    'time_in' => $row->time_in,
                    'time_out' => $row->time_out,
                    'break_minutes' => (int) $row->break_minutes,
                    'late_minutes' => (int) $row->late_minutes,
                    'total_minutes' => (int) $row->total_minutes,
                    'total_hours' => round(((int) $row->total_minutes) / 60, 2),
                    'status' => $row->status,
                    'is_locked_by_payroll' => $payrollLockService->isDateFinalized($employee->id, $row->date),
                    'attendance_statuses' => $this->attendanceStatuses($row, $expectedDailyMinutes),
                    'flags' => $flags,
                ];
            })->values()
            : collect();

        return Inertia::render('Admin/Attendance/Show', [
            'employee' => $this->employeePayload($employee),
            'months' => $months,
            'selected_month' => $selectedMonth ? [
                'id' => $selectedMonth->id,
                'month' => $selectedMonth->month,
                'year' => $selectedMonth->year,
                'month_name' => Carbon::createFromDate($selectedMonth->year, $selectedMonth->month, 1)->format('F Y'),
            ] : null,
            'rows' => $rows,
        ]);
    }

    public function updateRow(
        Request $request,
        DtrRow $row,
        PayrollLockService $payrollLockService,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $row->load('dtrMonth.user');
        $employee = $row->dtrMonth->user;
        abort_if($employee->is_admin, 404);
        if ($payrollLockService->isDateFinalized($employee->id, $row->date)) {
            return redirect()->back()->withErrors([
                'status' => 'This attendance date belongs to a finalized payroll. Use correction workflow with a reason.',
            ]);
        }

        $validated = $request->validate([
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'break_minutes' => 'nullable|integer|min:0|max:480',
            'status' => 'required|in:draft,finished,leave,missed',
        ]);
        $before = $row->only([
            'time_in',
            'time_out',
            'break_minutes',
            'late_minutes',
            'total_minutes',
            'status',
        ]);

        $error = $this->applyAttendanceUpdate($row, $employee, $validated);
        if ($error) {
            return redirect()->back()->withErrors(['status' => $error]);
        }
        $auditLogger->log(
            $request->user(),
            'attendance.admin_updated',
            'dtr_row',
            $row->id,
            $before,
            $row->fresh()->only(array_keys($before)),
            'Admin updated attendance row.',
            $request
        );

        return redirect()
            ->route('admin.attendance.show', [
                'employee' => $employee->id,
                'month_id' => $row->dtrMonth->id,
            ])
            ->with('success', 'Attendance row updated.');
    }

    public function correctRow(
        Request $request,
        DtrRow $row,
        PayrollLockService $payrollLockService,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $row->load('dtrMonth.user');
        $employee = $row->dtrMonth->user;
        abort_if($employee->is_admin, 404);
        if (! $payrollLockService->isDateFinalized($employee->id, $row->date)) {
            return redirect()->back()->withErrors([
                'status' => 'This row is not in a finalized payroll period. Use normal update.',
            ]);
        }

        $validated = $request->validate([
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'break_minutes' => 'nullable|integer|min:0|max:480',
            'status' => 'required|in:draft,finished,leave,missed',
            'correction_reason' => 'required|string|max:1000',
        ]);
        $before = $row->only([
            'time_in',
            'time_out',
            'break_minutes',
            'late_minutes',
            'total_minutes',
            'status',
        ]);

        $error = $this->applyAttendanceUpdate($row, $employee, $validated);
        if ($error) {
            return redirect()->back()->withErrors(['status' => $error]);
        }

        $unlockedCount = $payrollLockService->resetFinalizedRecordsForDate(
            $employee->id,
            $row->date,
            $validated['correction_reason']
        );
        $auditLogger->log(
            $request->user(),
            'attendance.admin_corrected',
            'dtr_row',
            $row->id,
            $before,
            $row->fresh()->only(array_keys($before)),
            $validated['correction_reason'],
            $request
        );

        return redirect()
            ->route('admin.attendance.show', [
                'employee' => $employee->id,
                'month_id' => $row->dtrMonth->id,
            ])
            ->with('success', 'Attendance corrected. '.$unlockedCount.' finalized payroll record(s) reset to generated.');
    }

    private function applyAttendanceUpdate(DtrRow $row, User $employee, array $validated): ?string
    {
        $status = $validated['status'];
        $timeIn = $validated['time_in'] ?? null;
        $timeOut = $validated['time_out'] ?? null;
        $breakMinutes = (int) ($validated['break_minutes'] ?? 0);

        if ($status === 'finished') {
            if (! $timeIn || ! $timeOut) {
                return 'Finished status requires both time in and time out.';
            }

            $in = Carbon::createFromFormat('H:i', $timeIn);
            $out = Carbon::createFromFormat('H:i', $timeOut);
            $workedMinutes = max(0, $in->diffInMinutes($out) - $breakMinutes);
            $lateMinutes = $this->calculateLateMinutes(
                $row->date,
                $employee->work_time_in,
                $timeIn
            );

            $row->update([
                'time_in' => $in->format('H:i:s'),
                'time_out' => $out->format('H:i:s'),
                'break_minutes' => $breakMinutes,
                'late_minutes' => $lateMinutes,
                'total_minutes' => $workedMinutes,
                'status' => 'finished',
                'on_break' => false,
                'break_started_at' => null,
                'break_target_minutes' => null,
            ]);

            return null;
        }

        $row->update([
            'time_in' => null,
            'time_out' => null,
            'break_minutes' => 0,
            'late_minutes' => 0,
            'total_minutes' => 0,
            'status' => $status,
            'on_break' => false,
            'break_started_at' => null,
            'break_target_minutes' => null,
        ]);

        return null;
    }

    private function employeePayload(User $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'employee_type' => $employee->employee_type,
            'department' => $employee->department,
            'company' => $employee->company,
            'salary_type' => $employee->salary_type,
            'salary_amount' => $employee->salary_amount !== null ? (float) $employee->salary_amount : null,
            'work_time_in' => $this->normalizeToHi($employee->work_time_in),
            'work_time_out' => $this->normalizeToHi($employee->work_time_out),
        ];
    }

    private function expectedDailyMinutes(?string $timeIn, ?string $timeOut, int $defaultBreakMinutes = 60): int
    {
        $normalizedIn = $this->normalizeToHis($timeIn);
        $normalizedOut = $this->normalizeToHis($timeOut);

        if (! $normalizedIn || ! $normalizedOut) {
            return 0;
        }

        $in = Carbon::createFromFormat('H:i:s', $normalizedIn);
        $out = Carbon::createFromFormat('H:i:s', $normalizedOut);
        if (! $out->gt($in)) {
            return 0;
        }

        return max(0, $in->diffInMinutes($out) - max(0, $defaultBreakMinutes));
    }

    private function detectFlags(DtrRow $row, int $expectedDailyMinutes): array
    {
        $flags = [];
        $minutes = (int) $row->total_minutes;

        if ($row->status === 'finished' && (! $row->time_in || ! $row->time_out)) {
            $flags[] = 'Missing in/out';
        }

        if ($row->status === 'finished' && $minutes <= 0) {
            $flags[] = 'Zero worked minutes';
        }

        if ($row->status === 'finished' && $expectedDailyMinutes > 0) {
            if ($minutes < (int) floor($expectedDailyMinutes * 0.5)) {
                $flags[] = 'Very short day';
            }

            if ($minutes > $expectedDailyMinutes + 180) {
                $flags[] = 'Long overtime';
            }
        }

        if ((int) $row->break_minutes > 120) {
            $flags[] = 'Long break';
        }

        if ((int) $row->late_minutes > 120) {
            $flags[] = 'Extreme late';
        }

        return $flags;
    }

    private function attendanceStatuses(DtrRow $row, int $expectedDailyMinutes): array
    {
        $statuses = [];
        $minutes = (int) $row->total_minutes;
        $lateMinutes = (int) $row->late_minutes;
        $breakMinutes = (int) $row->break_minutes;

        if ($row->time_in) {
            $statuses[] = 'Timed In';
        }

        if ($row->time_out) {
            $statuses[] = 'Timed Out';
        }

        if ($lateMinutes > 0) {
            $statuses[] = 'Late';
        }

        if ($row->status === 'finished' && $expectedDailyMinutes > 0 && $minutes > 0 && $minutes <= (int) floor($expectedDailyMinutes * 0.5)) {
            $statuses[] = 'Half Day';
        }

        if ($row->status === 'finished' && $expectedDailyMinutes > 0 && $minutes > 0 && $minutes < $expectedDailyMinutes) {
            $statuses[] = 'Undertime';
        }

        if ($row->status === 'finished' && $expectedDailyMinutes > 0 && $minutes > $expectedDailyMinutes) {
            $statuses[] = 'Overtime';
        }

        if ($breakMinutes > 120) {
            $statuses[] = 'Long Break';
        }

        if ($lateMinutes > 120) {
            $statuses[] = 'Extreme Late';
        }

        return $statuses;
    }

    private function normalizeToHi(?string $time): ?string
    {
        $normalized = $this->normalizeToHis($time);
        return $normalized ? Carbon::createFromFormat('H:i:s', $normalized)->format('H:i') : null;
    }

    private function normalizeToHis(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i:s', $time)->format('H:i:s');
        } catch (\Throwable $e) {
            try {
                return Carbon::createFromFormat('H:i', $time)->format('H:i:s');
            } catch (\Throwable $e) {
                return null;
            }
        }
    }

    private function calculateLateMinutes(Carbon $date, ?string $shiftStart, string $actualTimeIn): int
    {
        $timezone = 'Asia/Manila';
        $normalizedShiftStart = $this->normalizeToHis($shiftStart ?: self::DEFAULT_SHIFT_START)
            ?? self::DEFAULT_SHIFT_START;

        $shiftStartAt = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d').' '.$normalizedShiftStart,
            $timezone
        );

        $graceCutoff = $shiftStartAt->copy()->addMinutes($this->resolveGraceMinutes());
        $actualClockIn = Carbon::createFromFormat(
            'Y-m-d H:i',
            $date->format('Y-m-d').' '.$actualTimeIn,
            $timezone
        );

        return $actualClockIn->greaterThan($graceCutoff)
            ? $graceCutoff->diffInMinutes($actualClockIn)
            : 0;
    }

    private function resolveGraceMinutes(): int
    {
        $configured = (int) config('app.dtr_grace_minutes', self::DEFAULT_GRACE_MINUTES);

        return $configured >= 0 ? $configured : self::DEFAULT_GRACE_MINUTES;
    }

    private function attendanceLogsPayload()
    {
        return DtrRow::query()
            ->where(function ($query) {
                $query->whereNotNull('time_in')
                    ->orWhereNotNull('time_out')
                    ->orWhere('status', 'leave')
                    ->orWhereHas('leaveRequest', function ($leaveQuery) {
                        $leaveQuery->where('status', 'approved')
                            ->whereIn('request_type', ['leave', 'intern_absence']);
                    });
            })
            ->whereHas('dtrMonth.user', function ($query) {
                $query->where('is_admin', false);
            })
            ->with([
                'dtrMonth.user:id,name,email,employee_type,work_time_in,work_time_out,default_break_minutes',
                'leaveRequest:id,dtr_row_id,status,request_type',
            ])
            ->orderByDesc('date')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (DtrRow $row) {
                $employee = $row->dtrMonth->user;
                $expectedDailyMinutes = $this->expectedDailyMinutes(
                    $employee->work_time_in,
                    $employee->work_time_out,
                    (int) ($employee->default_break_minutes ?? 60)
                );
                $attendanceStatuses = $this->attendanceStatuses($row, $expectedDailyMinutes);
                if ($row->status === 'leave') {
                    $attendanceStatuses[] = 'Leave';
                }

                if ($row->leaveRequest?->status === 'approved' && $row->leaveRequest?->request_type === 'intern_absence') {
                    $attendanceStatuses[] = 'Approved Absence';
                }

                return [
                    'id' => $row->id,
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'employee_email' => $employee->email,
                    'employee_type' => $employee->employee_type,
                    'date' => $row->date->format('Y-m-d'),
                    'day' => $row->day,
                    'time_in' => $row->time_in,
                    'time_out' => $row->time_out,
                    'late_minutes' => (int) $row->late_minutes,
                    'break_minutes' => (int) $row->break_minutes,
                    'total_hours' => round(((int) $row->total_minutes) / 60, 2),
                    'status' => $row->status,
                    'leave_request_id' => $row->leaveRequest?->id,
                    'leave_request_status' => $row->leaveRequest?->status,
                    'leave_request_type' => $row->leaveRequest?->request_type,
                    'attendance_statuses' => array_values(array_unique($attendanceStatuses)),
                ];
            });
    }
}
