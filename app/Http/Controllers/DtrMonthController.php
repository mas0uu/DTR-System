<?php

namespace App\Http\Controllers;

use App\Models\DtrMonth;
use App\Models\Holiday;
use App\Models\PayrollRecord;
use App\Models\DtrRow;
use App\Models\User;
use App\Support\AttendanceTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DtrMonthController extends Controller
{
    private const DEFAULT_SHIFT_START = '08:00:00';
    private const DEFAULT_GRACE_MINUTES = 30;

    //Display a listing of DTR months for the user.
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.employees.index');
        }

        $this->ensureUserRowsUpToToday($user);
        
        // Get or create current month
        $now = Carbon::now('Asia/Manila');
        $currentMonth = DtrMonth::firstOrCreate(
            [
                'user_id' => $user->id,
                'month' => $now->month,
                'year' => $now->year,
            ]
        );

        // Get all months for this user
        $months = DtrMonth::where('user_id', $user->id)
            ->withCount([
                'rows as finished_rows' => function ($query) {
                    $query->where('status', 'finished');
                },
            ])
            ->withSum([
                'rows as finished_total_minutes' => function ($query) {
                    $query->where('status', 'finished')
                        ->where('total_minutes', '>', 0);
                },
            ], 'total_minutes')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(function ($month) {
                $finishedMinutes = (int) round((float) ($month->finished_total_minutes ?? 0));

                return [
                    'id' => $month->id,
                    'month' => $month->month,
                    'year' => $month->year,
                    'monthName' => Carbon::createFromDate($month->year, $month->month, 1)->format('F Y'),
                    'is_fulfilled' => $month->is_fulfilled,
                    'total_hours' => $finishedMinutes / 60,
                    'finished_rows' => (int) $month->finished_rows,
                ];
            });

        $todayDate = $now->toDateString();
        $payrollAccessEnabled = $this->payrollAccessEnabled($user);
        $todayRow = DtrRow::query()
            ->whereHas('dtrMonth', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereDate('date', $todayDate)
            ->with('leaveRequest')
            ->orderByDesc('id')
            ->first();

        $payrollRecords = collect();
        if ($payrollAccessEnabled) {
            $payrollRecords = PayrollRecord::query()
                ->where('user_id', $user->id)
                ->orderByDesc('pay_period_end')
                ->orderByDesc('pay_period_start')
                ->limit(12)
                ->get()
                ->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'pay_period_start' => $record->pay_period_start->format('Y-m-d'),
                        'pay_period_end' => $record->pay_period_end->format('Y-m-d'),
                        'salary_type' => $record->salary_type,
                        'salary_amount' => (float) $record->salary_amount,
                        'days_worked' => (float) $record->days_worked,
                        'hours_worked' => (float) $record->hours_worked,
                        'absences' => (int) $record->absences,
                        'undertime_minutes' => (int) $record->undertime_minutes,
                        'half_days' => (int) $record->half_days,
                        'total_salary' => (float) $record->total_salary,
                        'status' => $record->status,
                        'payslip_available' => ! empty($record->payslip_path),
                    ];
                });
        }
        $expectedDailyMinutes = $this->expectedDailyMinutes(
            $user->work_time_in,
            $user->work_time_out,
            (int) ($user->default_break_minutes ?? 60)
        );
        $internshipSummary = $user->employee_type === 'intern'
            ? $this->buildInternshipSummary($user, $todayDate)
            : null;
        $requestedTab = (string) $request->query('tab', '1');
        $allowedTabs = ['1', '2', '3', '4'];
        if (! in_array($requestedTab, $allowedTabs, true)) {
            $requestedTab = '1';
        }
        if (! $payrollAccessEnabled && $requestedTab === '4') {
            $requestedTab = '1';
        }

        return Inertia::render('Dtr/Index', [
            'months' => $months,
            'current_month_id' => $currentMonth->id,
            'initial_tab' => $requestedTab,
            'can_add_month' => true,
            'add_month_block_reason' => null,
            'payroll_access_enabled' => $payrollAccessEnabled,
            'internship_summary' => $internshipSummary,
            'user' => [
                'name' => $user->name,
                'student_name' => $user->student_name,
                'student_no' => $user->student_no,
                'school' => $user->school,
                'required_hours' => $user->required_hours,
                'company' => $user->company,
                'department' => $user->department,
                'supervisor_name' => $user->supervisor_name,
                'supervisor_position' => $user->supervisor_position,
                'employee_type' => $user->employee_type,
                'intern_compensation_enabled' => (bool) $user->intern_compensation_enabled,
                'starting_date' => optional($user->starting_date)->format('Y-m-d'),
                'working_days' => $user->working_days,
                'work_time_in' => $user->work_time_in,
                'work_time_out' => $user->work_time_out,
                'default_break_minutes' => (int) ($user->default_break_minutes ?? 60),
                'salary_type' => $user->salary_type,
                'salary_amount' => $user->salary_amount !== null ? (float) $user->salary_amount : null,
                'initial_paid_leave_days' => (float) ($user->initial_paid_leave_days ?? 0),
                'current_paid_leave_balance' => (float) ($user->current_paid_leave_balance ?? 0),
                'is_paid_leave_eligible' => $user->isPaidLeaveEligible(),
            ],
            'payroll_records' => $payrollRecords,
            'today_row' => $todayRow ? [
                'id' => $todayRow->id,
                'dtr_month_id' => $todayRow->dtr_month_id,
                'date' => $todayRow->date->format('Y-m-d'),
                'time_in' => $todayRow->time_in,
                'time_out' => $todayRow->time_out,
                'on_break' => (bool) $todayRow->on_break,
                'break_minutes' => (int) $todayRow->break_minutes,
                'break_target_minutes' => $todayRow->break_target_minutes,
                'break_started_at' => optional($todayRow->break_started_at)?->toIso8601String(),
                'late_minutes' => (int) $todayRow->late_minutes,
                'status' => $todayRow->status,
                'leave_request_status' => $todayRow->leaveRequest?->status,
                'leave_request_type' => $todayRow->leaveRequest?->request_type,
                'leave_request_is_paid' => $todayRow->leaveRequest?->is_paid,
                'attendance_statuses' => $this->attendanceStatuses($todayRow, $expectedDailyMinutes),
                'warnings' => $this->attendanceWarnings($todayRow, $expectedDailyMinutes, $todayDate),
            ] : null,
            'shift_start' => $this->resolveShiftStart($user->work_time_in),
            'grace_minutes' => $this->resolveGraceMinutes(),
        ]);
    }

    // Show a specific DTR month with its rows.
    public function show(Request $request, DtrMonth $month)
    {
        $this->authorize('view', $month);
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.employees.index');
        }

        $this->ensureUserRowsUpToToday($user);
        $timezone = 'Asia/Manila';
        $todayDate = Carbon::now($timezone)->toDateString();
        $startingDate = optional($user->starting_date)->format('Y-m-d');
        $expectedDailyMinutes = $this->expectedDailyMinutes(
            $user->work_time_in,
            $user->work_time_out,
            (int) ($user->default_break_minutes ?? 60)
        );
        $monthStart = Carbon::createFromDate($month->year, $month->month, 1, $timezone)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $holidays = Holiday::query()
            ->where('is_active', true)
            ->whereDate('date_start', '<=', $monthEnd->toDateString())
            ->where(function ($query) use ($monthStart) {
                $query->whereNull('date_end')
                    ->orWhereDate('date_end', '>=', $monthStart->toDateString());
            })
            ->get(['name', 'date_start', 'date_end', 'holiday_type']);
        $finalizedPeriods = PayrollRecord::query()
            ->where('user_id', $user->id)
            ->where('status', 'finalized')
            ->whereDate('pay_period_start', '<=', $monthEnd->toDateString())
            ->whereDate('pay_period_end', '>=', $monthStart->toDateString())
            ->get(['pay_period_start', 'pay_period_end'])
            ->map(function (PayrollRecord $record) {
                return [
                    'start' => $record->pay_period_start->format('Y-m-d'),
                    'end' => $record->pay_period_end->format('Y-m-d'),
                ];
            })
            ->values();

        $rows = $month->rows()
            ->with('leaveRequest')
            ->orderBy('date')
            ->get()
            ->map(function ($row) use ($todayDate, $startingDate, $expectedDailyMinutes, $finalizedPeriods, $holidays, $timezone) {
                $rowDate = $row->date->format('Y-m-d');
                $isLockedByPayroll = $finalizedPeriods->contains(function (array $period) use ($rowDate) {
                    return $rowDate >= $period['start'] && $rowDate <= $period['end'];
                });
                $canEdit = (! $startingDate || $rowDate >= $startingDate)
                    && $rowDate < $todayDate
                    && $row->status === 'missed'
                    && ! $row->time_in
                    && ! $row->time_out
                    && ! $isLockedByPayroll;
                // Rejected requests should allow the employee to edit missed attendance.
                $hasLockedLeaveRequest = in_array($row->leaveRequest?->status, ['pending', 'approved'], true);

                return [
                    'id' => $row->id,
                    'date' => $rowDate,
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
                    'holiday' => $this->holidayLabelForDate(Carbon::parse($rowDate, $timezone)->startOfDay(), $holidays),
                    'leave_request_status' => $row->leaveRequest?->status,
                    'leave_request_type' => $row->leaveRequest?->request_type,
                    'leave_request_is_paid' => $row->leaveRequest?->is_paid,
                    'is_locked_by_payroll' => $isLockedByPayroll,
                    'attendance_statuses' => $this->attendanceStatuses($row, $expectedDailyMinutes),
                    'warnings' => $this->attendanceWarnings($row, $expectedDailyMinutes, $todayDate),
                    'can_edit' => $canEdit && ! $hasLockedLeaveRequest,
                ];
            });

        $totalHours = $this->calculateTotalHours($month);
        $requiredHours = $user->required_hours;
        $loggedHoursUntilSelectedMonth = DtrRow::whereHas('dtrMonth', function ($query) use ($user, $month) {
            $query->where('user_id', $user->id)
                ->where(function ($dateQuery) use ($month) {
                    $dateQuery->where('year', '<', $month->year)
                        ->orWhere(function ($sameYearQuery) use ($month) {
                            $sameYearQuery->where('year', $month->year)
                                ->where('month', '<=', $month->month);
                        });
                });
        })
            ->where('status', 'finished')
            ->where('total_minutes', '>', 0)
            ->sum('total_minutes') / 60;
        $remainingHours = max(0, $requiredHours - $loggedHoursUntilSelectedMonth);

        return Inertia::render('Dtr/Show', [
            'month' => [
                'id' => $month->id,
                'month' => $month->month,
                'year' => $month->year,
                'monthName' => Carbon::createFromDate($month->year, $month->month, 1)->format('F Y'),
                'is_fulfilled' => $month->is_fulfilled,
            ],
            'rows' => $rows,
            'total_hours' => $totalHours,
            'required_hours' => $requiredHours,
            'remaining_hours' => $remainingHours,
            'today_date' => $todayDate,
            'shift_start' => $this->resolveShiftStart($user->work_time_in),
            'grace_minutes' => $this->resolveGraceMinutes(),
            'user' => [
                'name' => $user->name,
                'student_name' => $user->student_name,
                'student_no' => $user->student_no,
                'school' => $user->school,
                'required_hours' => $user->required_hours,
                'company' => $user->company,
                'department' => $user->department,
                'supervisor_name' => $user->supervisor_name,
                'supervisor_position' => $user->supervisor_position,
                'employee_type' => $user->employee_type,
                'intern_compensation_enabled' => (bool) $user->intern_compensation_enabled,
                'starting_date' => optional($user->starting_date)->format('Y-m-d'),
                'working_days' => $user->working_days,
                'work_time_in' => $user->work_time_in,
                'work_time_out' => $user->work_time_out,
                'default_break_minutes' => (int) ($user->default_break_minutes ?? 60),
                'salary_type' => $user->salary_type,
                'salary_amount' => $user->salary_amount !== null ? (float) $user->salary_amount : null,
                'initial_paid_leave_days' => (float) ($user->initial_paid_leave_days ?? 0),
                'current_paid_leave_balance' => (float) ($user->current_paid_leave_balance ?? 0),
                'is_paid_leave_eligible' => $user->isPaidLeaveEligible(),
            ],
        ]);
    }

    /**
     * Store a new DTR month.
     */
    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Manual month creation is disabled.',
        ], 403);
    }

    /**
     * Mark a DTR month as fulfilled.
     */
    public function finish(Request $request, DtrMonth $month)
    {
        return response()->json([
            'message' => 'Manual month finishing is disabled.',
        ], 403);
    }

    /**
     * Delete a DTR month.
     */
    public function destroy(Request $request, DtrMonth $month)
    {
        return response()->json([
            'message' => 'Month deletion is disabled.',
        ], 403);
    }

    /**
     * Calculate total hours for a month
     */
    private function calculateTotalHours(DtrMonth $month)
    {
        return $month->rows()
            ->where('status', 'finished')
            ->where('total_minutes', '>', 0)
            ->sum('total_minutes') / 60;
    }

    private function expectedDailyMinutes(?string $timeIn, ?string $timeOut, int $defaultBreakMinutes = 60): int
    {
        return AttendanceTime::expectedDailyMinutes($timeIn, $timeOut, $defaultBreakMinutes);
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

    private function ensureUserRowsUpToToday(User $user): void
    {
        if (! $user->starting_date) {
            return;
        }

        $timezone = 'Asia/Manila';
        $startDate = Carbon::parse($user->starting_date, $timezone)->startOfDay();
        $today = Carbon::now($timezone)->startOfDay();
        $workingDays = collect($user->working_days ?? [])->map(fn ($day) => (int) $day)->all();
        if (empty($workingDays)) {
            return;
        }

        $rowsQuery = DtrRow::whereHas('dtrMonth', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $today->toDateString());

        $latestDate = (clone $rowsQuery)->max('date');
        $generationStart = $latestDate
            ? Carbon::parse($latestDate, $timezone)->addDay()->startOfDay()
            : $startDate->copy();

        if ($generationStart->lte($today)) {
            $monthIds = [];
            $rowsToInsert = [];

            for ($date = $generationStart->copy(); $date->lte($today); $date->addDay()) {
                if (! in_array($date->dayOfWeek, $workingDays, true)) {
                    continue;
                }

                $monthKey = $date->format('Y-m');
                if (! isset($monthIds[$monthKey])) {
                    $month = DtrMonth::firstOrCreate([
                        'user_id' => $user->id,
                        'month' => $date->month,
                        'year' => $date->year,
                    ]);
                    $monthIds[$monthKey] = $month->id;
                }

                $rowsToInsert[] = [
                    'dtr_month_id' => $monthIds[$monthKey],
                    'date' => $date->format('Y-m-d'),
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
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($rowsToInsert)) {
                DtrRow::upsert(
                    $rowsToInsert,
                    ['dtr_month_id', 'date'],
                    ['day', 'updated_at']
                );
            }
        }

        (clone $rowsQuery)
            ->whereDate('date', '<', $today->toDateString())
            ->whereNull('time_in')
            ->whereNull('time_out')
            ->whereIn('status', ['draft', 'in_progress'])
            ->whereNotExists(function ($query) use ($user) {
                $query->select(DB::raw(1))
                    ->from('payroll_records')
                    ->where('payroll_records.user_id', $user->id)
                    ->where('payroll_records.status', 'finalized')
                    ->whereColumn('payroll_records.pay_period_start', '<=', 'dtr_rows.date')
                    ->whereColumn('payroll_records.pay_period_end', '>=', 'dtr_rows.date');
            })
            ->update([
                'status' => 'missed',
                'on_break' => false,
                'break_started_at' => null,
                'break_target_minutes' => null,
                'updated_at' => now(),
            ]);
    }

    private function resolveShiftStart(?string $workTimeIn): string
    {
        $normalized = AttendanceTime::normalizeToHis($workTimeIn ?: self::DEFAULT_SHIFT_START)
            ?? self::DEFAULT_SHIFT_START;

        return Carbon::createFromFormat('H:i:s', $normalized)->format('H:i');
    }

    private function resolveGraceMinutes(): int
    {
        $configured = (int) config('app.dtr_grace_minutes', self::DEFAULT_GRACE_MINUTES);

        return $configured >= 0 ? $configured : self::DEFAULT_GRACE_MINUTES;
    }

    private function holidayLabelForDate(Carbon $date, $holidays): ?string
    {
        $matched = null;
        $bestPriority = -1;

        foreach ($holidays as $holiday) {
            $start = Carbon::parse($holiday->date_start)->startOfDay();
            $end = $holiday->date_end
                ? Carbon::parse($holiday->date_end)->startOfDay()
                : $start->copy();

            if (! $date->betweenIncluded($start, $end)) {
                continue;
            }

            $priority = match ((string) $holiday->holiday_type) {
                'regular' => 3,
                'special' => 2,
                default => 0,
            };

            if ($priority > $bestPriority) {
                $matched = $holiday;
                $bestPriority = $priority;
            }
        }

        return $matched?->name;
    }

    private function payrollAccessEnabled(User $user): bool
    {
        if ($user->employee_type !== 'intern') {
            return true;
        }

        return (bool) $user->intern_compensation_enabled;
    }

    private function buildInternshipSummary(User $user, string $todayDate): array
    {
        $aggregates = DtrRow::query()
            ->join('dtr_months', 'dtr_months.id', '=', 'dtr_rows.dtr_month_id')
            ->where('dtr_months.user_id', $user->id)
            ->selectRaw("SUM(CASE WHEN dtr_rows.status = 'finished' AND dtr_rows.total_minutes > 0 THEN dtr_rows.total_minutes ELSE 0 END) AS rendered_minutes")
            ->selectRaw("SUM(CASE WHEN dtr_rows.status = 'finished' AND dtr_rows.total_minutes > 0 THEN 1 ELSE 0 END) AS finished_rows")
            ->selectRaw("SUM(CASE WHEN dtr_rows.late_minutes > 0 THEN 1 ELSE 0 END) AS late_count")
            ->selectRaw("SUM(CASE WHEN dtr_rows.status IN ('missed', 'leave') THEN 1 ELSE 0 END) AS absence_count")
            ->selectRaw("SUM(CASE WHEN (dtr_rows.time_in IS NOT NULL AND dtr_rows.time_out IS NULL) OR (dtr_rows.time_in IS NULL AND dtr_rows.time_out IS NOT NULL) THEN 1 ELSE 0 END) AS incomplete_count")
            ->first();
        $renderedMinutes = (float) ($aggregates?->rendered_minutes ?? 0);
        $totalRenderedHours = $renderedMinutes / 60;
        $requiredHours = (float) ($user->required_hours ?? 0);
        $remainingHours = max(0, round($requiredHours - $totalRenderedHours, 2));
        $finishedRows = (int) ($aggregates?->finished_rows ?? 0);
        $averageDailyHours = $finishedRows > 0 ? round($totalRenderedHours / $finishedRows, 2) : 0.0;
        $lateCount = (int) ($aggregates?->late_count ?? 0);
        $absenceCount = (int) ($aggregates?->absence_count ?? 0);
        $incompleteCount = (int) ($aggregates?->incomplete_count ?? 0);

        $estimatedCompletionDate = $this->estimateCompletionDate(
            $user,
            $todayDate,
            $remainingHours,
            $averageDailyHours
        );

        return [
            'total_rendered_hours' => round($totalRenderedHours, 2),
            'required_hours' => $requiredHours,
            'remaining_hours' => $remainingHours,
            'average_daily_hours' => $averageDailyHours,
            'estimated_completion_date' => $estimatedCompletionDate,
            'late_count' => $lateCount,
            'absence_count' => $absenceCount,
            'incomplete_count' => $incompleteCount,
            'school' => $user->school,
            'company' => $user->company,
            'supervisor_name' => $user->supervisor_name,
        ];
    }

    private function estimateCompletionDate(
        User $user,
        string $todayDate,
        float $remainingHours,
        float $averageDailyHours
    ): ?string {
        if ($remainingHours <= 0) {
            return $todayDate;
        }
        if ($averageDailyHours <= 0) {
            return null;
        }

        $workingDays = collect($user->working_days ?? [])->map(fn ($day) => (int) $day)->all();
        if (empty($workingDays)) {
            return null;
        }

        $neededDays = (int) ceil($remainingHours / $averageDailyHours);
        $cursor = Carbon::parse($todayDate, 'Asia/Manila')->startOfDay();
        $count = 0;

        while ($count < $neededDays) {
            $cursor->addDay();
            if (in_array($cursor->dayOfWeek, $workingDays, true)) {
                $count++;
            }
        }

        return $cursor->format('Y-m-d');
    }
}
