<?php

namespace App\Http\Controllers;

use App\Models\DtrMonth;
use App\Models\DtrRow;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PayrollLockService;
use App\Support\AttendanceTime;
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
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhere('role', '!=', User::ROLE_ADMIN);
            })
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

    public function logs(Request $request): Response
    {
        $days = max(7, min(365, (int) $request->query('days', 90)));
        $sinceDate = Carbon::now('Asia/Manila')->subDays($days)->toDateString();

        return Inertia::render('Admin/Attendance/Logs', [
            'attendance_logs' => $this->attendanceLogsPayload($sinceDate),
            'since_days' => $days,
        ]);
    }

    public function show(Request $request, User $employee): Response|RedirectResponse
    {
        abort_if($employee->isAdmin(), 404);

        $months = DtrMonth::query()
            ->where('user_id', $employee->id)
            ->withCount([
                'rows as finished_rows' => function ($query) {
                    $query->where('status', 'finished');
                },
            ])
            ->withSum([
                'rows as finished_total_minutes' => function ($query) {
                    $query->where('status', 'finished');
                },
            ], 'total_minutes')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(function (DtrMonth $month) {
                $finishedMinutes = (float) ($month->finished_total_minutes ?? 0);

                return [
                    'id' => $month->id,
                    'month' => $month->month,
                    'year' => $month->year,
                    'month_name' => Carbon::createFromDate($month->year, $month->month, 1)->format('F Y'),
                    'total_hours' => round($finishedMinutes / 60, 2),
                    'finished_rows' => (int) $month->finished_rows,
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
        $finalizedPeriods = collect();
        if ($selectedMonth) {
            $monthStart = Carbon::createFromDate($selectedMonth->year, $selectedMonth->month, 1, 'Asia/Manila')->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $finalizedPeriods = \App\Models\PayrollRecord::query()
                ->where('user_id', $employee->id)
                ->where('status', 'finalized')
                ->whereDate('pay_period_start', '<=', $monthEnd->toDateString())
                ->whereDate('pay_period_end', '>=', $monthStart->toDateString())
                ->get(['pay_period_start', 'pay_period_end'])
                ->map(function (\App\Models\PayrollRecord $record) {
                    return [
                        'start' => $record->pay_period_start->format('Y-m-d'),
                        'end' => $record->pay_period_end->format('Y-m-d'),
                    ];
                })
                ->values();
        }
        $rows = $selectedMonth
            ? $selectedMonth->rows()->orderBy('date')->get()->map(function (DtrRow $row) use ($expectedDailyMinutes, $finalizedPeriods) {
                $rowDate = $row->date->format('Y-m-d');
                $isLockedByPayroll = $finalizedPeriods->contains(function (array $period) use ($rowDate) {
                    return $rowDate >= $period['start'] && $rowDate <= $period['end'];
                });
                $flags = $this->detectFlags($row, $expectedDailyMinutes);
                return [
                    'id' => $row->id,
                    'date' => $rowDate,
                    'day' => $row->day,
                    'time_in' => $row->time_in,
                    'time_out' => $row->time_out,
                    'break_minutes' => (int) $row->break_minutes,
                    'late_minutes' => (int) $row->late_minutes,
                    'total_minutes' => (int) $row->total_minutes,
                    'total_hours' => round(((int) $row->total_minutes) / 60, 2),
                    'status' => $row->status,
                    'is_locked_by_payroll' => $isLockedByPayroll,
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
        abort_if($employee->isAdmin(), 404);
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
        abort_if($employee->isAdmin(), 404);
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
        return AttendanceTime::expectedDailyMinutes($timeIn, $timeOut, $defaultBreakMinutes);
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
        return AttendanceTime::normalizeToHi($time);
    }

    private function normalizeToHis(?string $time): ?string
    {
        return AttendanceTime::normalizeToHis($time);
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

    private function attendanceLogsPayload(?string $sinceDate = null)
    {
        $query = DtrRow::query()
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
                $query->where('is_admin', false)
                    ->where(function ($roleQuery) {
                        $roleQuery->whereNull('role')
                            ->orWhere('role', '!=', User::ROLE_ADMIN);
                    });
            })
            ->with([
                'dtrMonth.user:id,name,email,employee_type,work_time_in,work_time_out,default_break_minutes',
                'leaveRequest:id,dtr_row_id,status,request_type',
            ])
            ->orderByDesc('date')
            ->orderByDesc('updated_at')
            ->limit(2000);
        if ($sinceDate) {
            $query->whereDate('date', '>=', $sinceDate);
        }

        return $query
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
