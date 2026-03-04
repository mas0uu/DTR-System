<?php

namespace App\Http\Controllers;

use App\Models\DtrRow;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Carbon\Carbon;

class DtrRowController extends Controller
{
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
    public function update(Request $request, DtrRow $row)
    {
        $this->authorize('update', $row);

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

        if (in_array($row->status, ['finished', 'leave'], true)) {
            return response()->json([
                'error' => 'This row is already recorded and locked.',
            ], 422);
        }

        if ($row->time_in || $row->time_out) {
            return response()->json([
                'error' => 'This row already has attendance and is locked.',
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
        $lateMinutes = $row->late_minutes ?? 0;
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

        $row->update($validated);

        return response()->json($this->rowPayload($row->refresh()));
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

    public function clockIn(Request $request, DtrRow $row)
    {
        $this->authorize('update', $row);
        $this->assertTodayRow($row);

        if ($row->time_in) {
            return response()->json(['error' => 'Already clocked in for today.'], 422);
        }

        $now = Carbon::now('Asia/Manila');
        $graceTime = $now->copy()->setTime(8, 30, 0);
        $lateMinutes = $now->greaterThan($graceTime)
            ? $graceTime->diffInMinutes($now)
            : 0;

        $row->update([
            'time_in' => $now->format('H:i:s'),
            'status' => 'in_progress',
            'late_minutes' => $lateMinutes,
        ]);

        return response()->json($this->rowPayload($row->refresh()));
    }

    public function clockOut(Request $request, DtrRow $row)
    {
        $this->authorize('update', $row);
        $this->assertTodayRow($row);

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

        $row->update([
            'time_out' => $now->format('H:i:s'),
            'total_minutes' => $totalMinutes,
            'status' => 'finished',
            'on_break' => false,
            'break_started_at' => null,
            'break_target_minutes' => null,
        ]);

        $this->generateNextWeekdayRow($request->user(), Carbon::parse($row->date, 'Asia/Manila'));

        return response()->json($this->rowPayload($row->refresh()));
    }

    public function startBreak(Request $request, DtrRow $row)
    {
        $this->authorize('update', $row);
        $this->assertTodayRow($row);

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

        $row->update([
            'on_break' => true,
            'break_started_at' => Carbon::now('Asia/Manila'),
            'break_target_minutes' => (int) $validated['minutes'],
            'status' => 'in_progress',
        ]);

        return response()->json($this->rowPayload($row->refresh()));
    }

    public function finishBreak(Request $request, DtrRow $row)
    {
        $this->authorize('update', $row);
        $this->assertTodayRow($row);

        if (! $row->on_break || ! $row->break_started_at) {
            return response()->json(['error' => 'No active break to finish.'], 422);
        }

        $now = Carbon::now('Asia/Manila');
        $elapsed = max(1, $row->break_started_at->diffInMinutes($now));

        $row->update([
            'break_minutes' => (int) $row->break_minutes + $elapsed,
            'on_break' => false,
            'break_started_at' => null,
            'break_target_minutes' => null,
            'status' => $row->time_out ? 'finished' : 'in_progress',
        ]);

        return response()->json($this->rowPayload($row->refresh()));
    }

    public function markLeave(Request $request, DtrRow $row)
    {
        $this->authorize('update', $row);

        $timezone = 'Asia/Manila';
        $today = Carbon::now($timezone)->startOfDay();
        $rowDate = Carbon::parse($row->date, $timezone)->startOfDay();

        if ($rowDate->gte($today)) {
            return response()->json(['error' => 'Leave can only be set for skipped past rows.'], 422);
        }

        if ($row->time_in || $row->time_out) {
            return response()->json(['error' => 'Leave is only allowed for blank rows.'], 422);
        }

        if (! in_array($row->status, ['draft', 'missed'], true)) {
            return response()->json(['error' => 'This row is already recorded and locked.'], 422);
        }

        $row->update([
            'status' => 'leave',
            'on_break' => false,
            'break_started_at' => null,
            'break_target_minutes' => null,
            'total_minutes' => 0,
        ]);

        return response()->json($this->rowPayload($row->refresh()));
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

    private function rowPayload(DtrRow $row): array
    {
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
        ];
    }
}
