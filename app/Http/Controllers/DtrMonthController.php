<?php

namespace App\Http\Controllers;

use App\Models\DtrMonth;
use App\Models\DtrRow;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DtrMonthController extends Controller
{
    //Display a listing of DTR months for the user.
    public function index(Request $request)
    {
        $user = $request->user();
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
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($month) {
                return [
                    'id' => $month->id,
                    'month' => $month->month,
                    'year' => $month->year,
                    'monthName' => Carbon::createFromDate($month->year, $month->month, 1)->format('F Y'),
                    'is_fulfilled' => $month->is_fulfilled,
                    'total_hours' => $this->calculateTotalHours($month),
                    'finished_rows' => $month->rows()->where('status', 'finished')->count(),
                ];
            });

        return Inertia::render('Dtr/Index', [
            'months' => $months,
            'current_month_id' => $currentMonth->id,
            'initial_tab' => $request->query('tab') === '2' ? '2' : '1',
            'can_add_month' => true,
            'add_month_block_reason' => null,
            'user' => [
                'student_name' => $user->student_name,
                'student_no' => $user->student_no,
                'school' => $user->school,
                'required_hours' => $user->required_hours,
                'company' => $user->company,
                'department' => $user->department,
                'supervisor_name' => $user->supervisor_name,
                'supervisor_position' => $user->supervisor_position,
                'employee_type' => $user->employee_type,
                'starting_date' => optional($user->starting_date)->format('Y-m-d'),
                'working_days' => $user->working_days,
                'work_time_in' => $user->work_time_in,
                'work_time_out' => $user->work_time_out,
            ],
        ]);
    }

    // Show a specific DTR month with its rows.
    public function show(Request $request, DtrMonth $month)
    {
        $this->authorize('view', $month);
        $user = $request->user();
        $this->ensureUserRowsUpToToday($user);
        $timezone = 'Asia/Manila';
        $todayDate = Carbon::now($timezone)->toDateString();
        $startingDate = optional($user->starting_date)->format('Y-m-d');

        $rows = $month->rows()
            ->orderBy('date')
            ->get()
            ->map(function ($row) use ($todayDate, $startingDate) {
                $rowDate = $row->date->format('Y-m-d');
                $canEdit = (! $startingDate || $rowDate >= $startingDate)
                    && $rowDate < $todayDate
                    && in_array($row->status, ['draft', 'missed'], true)
                    && ! $row->time_in
                    && ! $row->time_out;

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
                    'remarks' => $row->remarks,
                    'can_edit' => $canEdit,
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
            'shift_start' => '08:00',
            'grace_minutes' => 30,
            'user' => [
                'student_name' => $user->student_name,
                'student_no' => $user->student_no,
                'school' => $user->school,
                'required_hours' => $user->required_hours,
                'company' => $user->company,
                'department' => $user->department,
                'supervisor_name' => $user->supervisor_name,
                'supervisor_position' => $user->supervisor_position,
                'employee_type' => $user->employee_type,
                'starting_date' => optional($user->starting_date)->format('Y-m-d'),
                'working_days' => $user->working_days,
                'work_time_in' => $user->work_time_in,
                'work_time_out' => $user->work_time_out,
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

    private function ensureUserRowsUpToToday(User $user): void
    {
        if (! $user->starting_date) {
            return;
        }

        $timezone = 'Asia/Manila';
        $startDate = Carbon::parse($user->starting_date, $timezone)->startOfDay();
        $today = Carbon::now($timezone)->startOfDay();
        $workingDays = collect($user->working_days ?? [])->map(fn ($day) => (int) $day)->all();
        $normalizedWorkTimeIn = $this->normalizeTimeString($user->work_time_in);
        $normalizedWorkTimeOut = $this->normalizeTimeString($user->work_time_out);

        $existingRows = DtrRow::whereHas('dtrMonth', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $today->toDateString())
            ->get();

        foreach ($existingRows as $existingRow) {
            $rowDate = Carbon::parse($existingRow->date, $timezone);
            $isWorkingDay = in_array($rowDate->dayOfWeek, $workingDays, true);
            if ($isWorkingDay) {
                continue;
            }

            $isLegacyAutofill = $existingRow->status === 'finished'
                && $existingRow->remarks === null
                && (int) $existingRow->late_minutes === 0
                && (int) $existingRow->break_minutes === 0
                && $normalizedWorkTimeIn
                && $normalizedWorkTimeOut
                && $this->normalizeTimeString($existingRow->time_in) === $normalizedWorkTimeIn
                && $this->normalizeTimeString($existingRow->time_out) === $normalizedWorkTimeOut;

            $hasNoRealInput = ! $existingRow->time_in
                && ! $existingRow->time_out
                && in_array($existingRow->status, ['draft', 'missed'], true);

            if ($isLegacyAutofill || $hasNoRealInput) {
                $existingRow->delete();
            }
        }

        for ($date = $startDate->copy(); $date->lte($today); $date->addDay()) {
            if (! in_array($date->dayOfWeek, $workingDays, true)) {
                continue;
            }

            $month = DtrMonth::firstOrCreate([
                'user_id' => $user->id,
                'month' => $date->month,
                'year' => $date->year,
            ]);

            $row = DtrRow::firstOrCreate(
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
                    'remarks' => null,
                ]
            );

            // Normalize legacy auto-filled rows that were created as "finished"
            // without actual user input (template work hours only).
            $isLegacyAutofill = $row->status === 'finished'
                && $row->remarks === null
                && (int) $row->late_minutes === 0
                && (int) $row->break_minutes === 0
                && $normalizedWorkTimeIn
                && $normalizedWorkTimeOut
                && $this->normalizeTimeString($row->time_in) === $normalizedWorkTimeIn
                && $this->normalizeTimeString($row->time_out) === $normalizedWorkTimeOut;

            if ($isLegacyAutofill) {
                $row->update([
                    'time_in' => null,
                    'time_out' => null,
                    'total_minutes' => 0,
                    'status' => 'draft',
                ]);
                $row->refresh();
            }

            $shouldBeMissed = $date->lt($today)
                && ! $row->time_in
                && ! $row->time_out
                && in_array($row->status, ['draft', 'in_progress'], true);
            if ($shouldBeMissed) {
                $row->update([
                    'status' => 'missed',
                    'on_break' => false,
                    'break_started_at' => null,
                    'break_target_minutes' => null,
                ]);
            }
        }
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
}
