<?php

namespace App\Http\Controllers;

use App\Models\DtrRow;
use App\Models\LeaveRequest;
use App\Services\AuditLogger;
use App\Services\LeaveBalanceService;
use App\Services\PayrollLockService;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Carbon\Carbon;

class DtrRowController extends Controller
{
    private const DEFAULT_SHIFT_START = '08:00:00';
    private const DEFAULT_GRACE_MINUTES = 30;

    /**
     * Store a new DTR row.
     */
    public function store(Request $request)
    {
        return response()->json([
            'error' => 'Manual attendance row creation is disabled.',
        ], 403);
    }

    // Update a DTR row.
    public function update(
        Request $request,
        DtrRow $row,
        PayrollLockService $payrollLockService,
        AuditLogger $auditLogger
    )
    {
        $this->authorize('update', $row);
        if ($payrollLockService->isDateFinalized($request->user()->id, $row->date)) {
            return response()->json([
                'error' => 'This row is locked because it belongs to a finalized payroll period.',
            ], 422);
        }

        $timezone = 'Asia/Manila';
        $today = Carbon::now($timezone)->startOfDay();
        $yesterday = $today->copy()->subDay();
        $rowDate = Carbon::parse($row->date, $timezone)->startOfDay();
        $startingDate = $request->user()->starting_date
            ? Carbon::parse($request->user()->starting_date, $timezone)->startOfDay()
            : null;

        if ($startingDate && $rowDate->lt($startingDate)) {
            return response()->json([
                'error' => 'Only rows from your starting date can be updated.',
            ], 422);
        }

        if ($rowDate->gt($yesterday)) {
            return response()->json([
                'error' => 'Only past attendance (up to yesterday) can be edited.',
            ], 422);
        }

        if ($row->status !== 'missed') {
            return response()->json([
                'error' => 'Only missed rows can be edited in monthly records.',
            ], 422);
        }

        if ($row->time_in || $row->time_out) {
            return response()->json([
                'error' => 'This row already has attendance and is locked.',
            ], 422);
        }

        $existingLeaveRequest = LeaveRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('leave_date', $row->date->format('Y-m-d'))
            ->first(['status']);
        if ($existingLeaveRequest && in_array($existingLeaveRequest->status, ['pending', 'approved'], true)) {
            return response()->json([
                'error' => 'This row is locked because a leave/absence request is pending or already approved for this date.',
            ], 422);
        }

        $validated = $request->validate([
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'required|date_format:H:i|after:time_in',
            'break_minutes' => 'nullable|integer|min:0',
        ]);

        $timeIn = isset($validated['time_in'])
            ? \DateTime::createFromFormat('H:i', $validated['time_in'])
            : null;
        $timeOut = isset($validated['time_out'])
            ? \DateTime::createFromFormat('H:i', $validated['time_out'])
            : null;

        if ($timeIn && $timeOut && $timeOut <= $timeIn) {
            return response()->json([
                'error' => 'Time out must be after time in.',
            ], 422);
        }

        $breakMinutes = $validated['break_minutes'] ?? 0;
        $lateMinutes = $this->calculateLateMinutesForRow(
            $request,
            $row,
            (string) $validated['time_in']
        );
        $totalMinutes = 0;
        $diff = $timeOut->diff($timeIn);
        $workedMinutes = ($diff->h * 60) + $diff->i;
        $totalMinutes = max(0, $workedMinutes - $breakMinutes);
        $status = 'finished';
        $validated['total_minutes'] = $totalMinutes;
        $validated['status'] = $status;
        $validated['late_minutes'] = $lateMinutes;
        $validated['on_break'] = false;
        $validated['break_started_at'] = null;
        $validated['break_target_minutes'] = null;
        $before = $this->attendanceSnapshot($row);

        $row->update($validated);
        $after = $this->attendanceSnapshot($row->refresh());
        $auditLogger->log(
            $request->user(),
            'attendance.self_updated',
            'dtr_row',
            $row->id,
            $before,
            $after,
            'Employee edited a past attendance row.',
            $request
        );

        return response()->json($this->rowPayload($row, $request->user()));
    }

    /**
     * Delete a DTR row.
     */
    public function destroy(Request $request, DtrRow $row)
    {
        return response()->json([
            'error' => 'Deleting DTR rows is disabled.',
        ], 403);
    }

    public function clockIn(
        Request $request,
        DtrRow $row,
        PayrollLockService $payrollLockService,
        AuditLogger $auditLogger
    )
    {
        $this->authorize('update', $row);
        $this->assertTodayRow($row);
        if ($payrollLockService->isDateFinalized($request->user()->id, $row->date)) {
            return response()->json(['error' => 'This row is locked by finalized payroll.'], 422);
        }

        if ($row->time_in) {
            return response()->json(['error' => 'Already clocked in for today.'], 422);
        }

        $now = Carbon::now('Asia/Manila');
        $shiftStart = $this->resolveShiftStartForDate($request, $now);
        $graceTime = $shiftStart->copy()->addMinutes($this->resolveGraceMinutes());
        $lateMinutes = $now->greaterThan($graceTime)
            ? $graceTime->diffInMinutes($now)
            : 0;
        $before = $this->attendanceSnapshot($row);

        $row->update([
            'time_in' => $now->format('H:i:s'),
            'status' => 'in_progress',
            'late_minutes' => $lateMinutes,
        ]);
        $after = $this->attendanceSnapshot($row->refresh());
        $auditLogger->log(
            $request->user(),
            'attendance.self_clock_in',
            'dtr_row',
            $row->id,
            $before,
            $after,
            'Employee clocked in.',
            $request
        );

        return response()->json($this->rowPayload($row, $request->user()));
    }

    public function clockOut(
        Request $request,
        DtrRow $row,
        PayrollLockService $payrollLockService,
        AuditLogger $auditLogger
    )
    {
        $this->authorize('update', $row);
        $this->assertTodayRow($row);
        if ($payrollLockService->isDateFinalized($request->user()->id, $row->date)) {
            return response()->json(['error' => 'This row is locked by finalized payroll.'], 422);
        }

        if (! $row->time_in) {
            return response()->json(['error' => 'Press IN first before OUT.'], 422);
        }

        if ($row->time_out) {
            return response()->json(['error' => 'Already clocked out for today.'], 422);
        }

        $now = Carbon::now('Asia/Manila');
        $timeIn = Carbon::createFromFormat('H:i:s', $row->time_in, 'Asia/Manila');
        $workedMinutes = $timeIn->diffInMinutes($now);
        $totalMinutes = max(0, $workedMinutes - (int) $row->break_minutes);
        $before = $this->attendanceSnapshot($row);

        $row->update([
            'time_out' => $now->format('H:i:s'),
            'total_minutes' => $totalMinutes,
            'status' => 'finished',
            'on_break' => false,
            'break_started_at' => null,
            'break_target_minutes' => null,
        ]);
        $after = $this->attendanceSnapshot($row->refresh());
        $auditLogger->log(
            $request->user(),
            'attendance.self_clock_out',
            'dtr_row',
            $row->id,
            $before,
            $after,
            'Employee clocked out.',
            $request
        );

        $this->generateNextWeekdayRow($request->user(), Carbon::parse($row->date, 'Asia/Manila'));

        return response()->json($this->rowPayload($row, $request->user()));
    }

    public function startBreak(
        Request $request,
        DtrRow $row,
        PayrollLockService $payrollLockService,
        AuditLogger $auditLogger
    )
    {
        $this->authorize('update', $row);
        $this->assertTodayRow($row);
        if ($payrollLockService->isDateFinalized($request->user()->id, $row->date)) {
            return response()->json(['error' => 'This row is locked by finalized payroll.'], 422);
        }

        $validated = $request->validate([
            'minutes' => 'required|integer|in:5,10,15,30,45,60',
        ]);

        if (! $row->time_in) {
            return response()->json(['error' => 'Press IN before taking a break.'], 422);
        }

        if ($row->time_out) {
            return response()->json(['error' => 'Cannot start break after OUT.'], 422);
        }

        if ($row->on_break) {
            return response()->json(['error' => 'Break is already running.'], 422);
        }
        $before = $this->attendanceSnapshot($row);

        $row->update([
            'on_break' => true,
            'break_started_at' => Carbon::now('Asia/Manila'),
            'break_target_minutes' => (int) $validated['minutes'],
            'status' => 'in_progress',
        ]);
        $after = $this->attendanceSnapshot($row->refresh());
        $auditLogger->log(
            $request->user(),
            'attendance.self_break_started',
            'dtr_row',
            $row->id,
            $before,
            $after,
            'Employee started a break.',
            $request
        );

        return response()->json($this->rowPayload($row, $request->user()));
    }

    public function finishBreak(
        Request $request,
        DtrRow $row,
        PayrollLockService $payrollLockService,
        AuditLogger $auditLogger
    )
    {
        $this->authorize('update', $row);
        $this->assertTodayRow($row);
        if ($payrollLockService->isDateFinalized($request->user()->id, $row->date)) {
            return response()->json(['error' => 'This row is locked by finalized payroll.'], 422);
        }

        if (! $row->on_break || ! $row->break_started_at) {
            return response()->json(['error' => 'No active break to finish.'], 422);
        }

        $now = Carbon::now('Asia/Manila');
        $elapsed = max(1, $row->break_started_at->diffInMinutes($now));
        $before = $this->attendanceSnapshot($row);

        $row->update([
            'break_minutes' => (int) $row->break_minutes + $elapsed,
            'on_break' => false,
            'break_started_at' => null,
            'break_target_minutes' => null,
            'status' => $row->time_out ? 'finished' : 'in_progress',
        ]);
        $after = $this->attendanceSnapshot($row->refresh());
        $auditLogger->log(
            $request->user(),
            'attendance.self_break_finished',
            'dtr_row',
            $row->id,
            $before,
            $after,
            'Employee finished a break.',
            $request
        );

        return response()->json($this->rowPayload($row, $request->user()));
    }

    public function markLeave(
        Request $request,
        DtrRow $row,
        LeaveBalanceService $leaveBalanceService,
        PayrollLockService $payrollLockService,
        AuditLogger $auditLogger
    )
    {
        $this->authorize('update', $row);
        if ($payrollLockService->isDateFinalized($request->user()->id, $row->date)) {
            return response()->json(['error' => 'This row is locked by finalized payroll.'], 422);
        }

        $timezone = 'Asia/Manila';
        $today = Carbon::now($timezone)->startOfDay();
        $rowDate = Carbon::parse($row->date, $timezone)->startOfDay();
        $isIntern = $request->user()->employee_type === 'intern';
        $requestLabel = $isIntern ? 'Absence request' : 'Leave request';

        if ($rowDate->gte($today)) {
            return response()->json(['error' => $requestLabel.' can only be set for skipped past rows.'], 422);
        }

        if ($row->time_in || $row->time_out) {
            return response()->json(['error' => $requestLabel.' is only allowed for blank rows.'], 422);
        }

        if (! in_array($row->status, ['draft', 'missed'], true)) {
            return response()->json(['error' => 'This row is already recorded and locked.'], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
            'is_paid' => 'nullable|boolean',
        ]);
        $requestType = $request->user()->employee_type === 'intern' ? 'intern_absence' : 'leave';
        $leaveBalanceService->refreshAnnualBalanceForUser($request->user());
        $request->user()->refresh();
        $isPaidRequest = false;
        if ($requestType === 'leave') {
            $isPaidRequest = (bool) ($validated['is_paid'] ?? true);
            if ($isPaidRequest && ! $request->user()->isPaidLeaveEligible()) {
                return response()->json(['error' => 'Paid leave is only available for regular employees.'], 422);
            }

            if ($isPaidRequest && (float) $request->user()->current_paid_leave_balance < 1) {
                return response()->json(['error' => 'Insufficient paid leave balance. Submit as unpaid leave or ask admin for adjustment.'], 422);
            }
        }
        $leaveDate = $row->date->format('Y-m-d');
        $existing = LeaveRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('leave_date', $leaveDate)
            ->first();
        if ($existing && in_array($existing->status, ['pending', 'approved'], true)) {
            return response()->json(['error' => 'A request already exists for this date.'], 422);
        }

        $before = $existing?->only([
            'status',
            'reason',
            'reviewed_by',
            'reviewed_at',
            'decision_note',
        ]);
        $leaveRequest = LeaveRequest::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'leave_date' => $leaveDate,
            ],
            [
                'dtr_row_id' => $row->id,
                'request_type' => $requestType,
                'requested_days' => 1,
                'is_paid' => $isPaidRequest,
                'approved_paid_days' => 0,
                'approved_unpaid_days' => 0,
                'deducted_days' => 0,
                'balance_before' => null,
                'balance_after' => null,
                'reason' => $validated['reason'] ?? null,
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'decision_note' => null,
            ]
        );
        $auditLogger->log(
            $request->user(),
            $existing ? 'leave_request.resubmitted' : 'leave_request.submitted',
            'leave_request',
            $leaveRequest->id,
            $before,
            $leaveRequest->fresh()->only([
                'status',
                'request_type',
                'reason',
                'reviewed_by',
                'reviewed_at',
                'decision_note',
            ]),
            $validated['reason'] ?? null,
            $request
        );

        return response()->json([
            ...$this->rowPayload($row->refresh(), $request->user()),
            'leave_request_status' => 'pending',
            'leave_request_type' => $requestType,
            'leave_request_is_paid' => $isPaidRequest,
        ]);
    }

    private function assertTodayRow(DtrRow $row): void
    {
        $today = Carbon::now('Asia/Manila')->toDateString();
        if ($row->date->format('Y-m-d') !== $today) {
            throw new HttpResponseException(response()->json([
                'error' => 'This action is only available for today\'s row.',
            ], 422));
        }
    }

    private function generateNextWeekdayRow($user, Carbon $baseDate): void
    {
        $workingDays = collect($user->working_days ?? [])->map(fn ($day) => (int) $day)->all();
        if (empty($workingDays)) {
            return;
        }

        $nextDate = $baseDate->copy()->addDay();
        while (! in_array($nextDate->dayOfWeek, $workingDays, true)) {
            $nextDate->addDay();
        }

        $month = \App\Models\DtrMonth::firstOrCreate([
            'user_id' => $user->id,
            'month' => $nextDate->month,
            'year' => $nextDate->year,
        ]);

        DtrRow::firstOrCreate(
            [
                'dtr_month_id' => $month->id,
                'date' => $nextDate->format('Y-m-d'),
            ],
            [
                'day' => $nextDate->format('l'),
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

    private function rowPayload(DtrRow $row, $user): array
    {
        $expectedDailyMinutes = $this->expectedDailyMinutes(
            $user?->work_time_in,
            $user?->work_time_out,
            (int) ($user?->default_break_minutes ?? 60)
        );
        $todayDate = Carbon::now('Asia/Manila')->toDateString();
        $leaveRequest = LeaveRequest::query()
            ->where('user_id', $row->dtrMonth->user_id)
            ->whereDate('leave_date', $row->date->format('Y-m-d'))
            ->first(['status', 'request_type', 'is_paid']);

        return [
            'id' => $row->id,
            'date' => $row->date->format('Y-m-d'),
            'day' => $row->day,
            'time_in' => $row->time_in,
            'time_out' => $row->time_out,
            'total_hours' => round($row->total_minutes / 60, 2),
            'total_minutes' => $row->total_minutes,
            'break_minutes' => $row->break_minutes,
            'late_minutes' => $row->late_minutes,
            'on_break' => (bool) $row->on_break,
            'break_target_minutes' => $row->break_target_minutes,
            'break_started_at' => optional($row->break_started_at)?->toIso8601String(),
            'status' => $row->status,
            'leave_request_status' => $leaveRequest?->status,
            'leave_request_type' => $leaveRequest?->request_type,
            'leave_request_is_paid' => $leaveRequest?->is_paid,
            'attendance_statuses' => $this->attendanceStatuses($row, $expectedDailyMinutes),
            'warnings' => $this->attendanceWarnings($row, $expectedDailyMinutes, $todayDate),
        ];
    }

    private function calculateLateMinutesForRow(Request $request, DtrRow $row, string $actualTimeIn): int
    {
        $timezone = 'Asia/Manila';
        $rowDate = Carbon::parse($row->date, $timezone);
        $shiftStart = $this->resolveShiftStartForDate($request, $rowDate);
        $graceCutoff = $shiftStart->copy()->addMinutes($this->resolveGraceMinutes());
        $actualClockIn = Carbon::createFromFormat(
            'Y-m-d H:i',
            $rowDate->format('Y-m-d').' '.$actualTimeIn,
            $timezone
        );

        return $actualClockIn->greaterThan($graceCutoff)
            ? $graceCutoff->diffInMinutes($actualClockIn)
            : 0;
    }

    private function expectedDailyMinutes(?string $timeIn, ?string $timeOut, int $defaultBreakMinutes = 60): int
    {
        $normalizedIn = $this->normalizeTimeString($timeIn);
        $normalizedOut = $this->normalizeTimeString($timeOut);

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

    private function attendanceWarnings(DtrRow $row, int $expectedDailyMinutes, string $todayDate): array
    {
        $warnings = [];
        $minutes = (int) $row->total_minutes;
        $lateMinutes = (int) $row->late_minutes;
        $rowDate = $row->date->format('Y-m-d');

        if ($lateMinutes > 0) {
            $warnings[] = 'Late by '.$lateMinutes.' minutes';
        }

        if ($row->status === 'finished' && $expectedDailyMinutes > 0 && $minutes > 0 && $minutes < $expectedDailyMinutes) {
            $warnings[] = 'Undertime by '.($expectedDailyMinutes - $minutes).' minutes';
        }

        $isIncompletePair = ($row->time_in && ! $row->time_out) || (! $row->time_in && $row->time_out);
        $isIncompleteFinished = $row->status === 'finished' && (! $row->time_in || ! $row->time_out);
        $isIncompleteBlankPast = $rowDate < $todayDate
            && in_array($row->status, ['draft', 'missed', 'in_progress'], true)
            && ! $row->time_in
            && ! $row->time_out;
        if ($isIncompletePair || $isIncompleteFinished || $isIncompleteBlankPast) {
            $warnings[] = 'Incomplete Row';
        }

        return array_values(array_unique($warnings));
    }

    private function resolveShiftStartForDate(Request $request, Carbon $reference): Carbon
    {
        $timezone = 'Asia/Manila';
        $rawTime = $request->user()?->work_time_in ?: self::DEFAULT_SHIFT_START;
        $normalized = $this->normalizeTimeString($rawTime) ?? self::DEFAULT_SHIFT_START;

        return Carbon::createFromFormat('Y-m-d H:i:s', $reference->format('Y-m-d').' '.$normalized, $timezone);
    }

    private function resolveGraceMinutes(): int
    {
        $configured = (int) config('app.dtr_grace_minutes', self::DEFAULT_GRACE_MINUTES);

        return $configured >= 0 ? $configured : self::DEFAULT_GRACE_MINUTES;
    }

    private function normalizeTimeString(?string $time): ?string
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

    private function attendanceSnapshot(DtrRow $row): array
    {
        return $row->only([
            'time_in',
            'time_out',
            'break_minutes',
            'late_minutes',
            'total_minutes',
            'status',
            'on_break',
            'break_started_at',
            'break_target_minutes',
        ]);
    }
}
