<?php

namespace App\Http\Controllers;

use App\Models\DtrMonth;
use App\Models\DtrRow;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DtrRowController extends Controller
{
    /**
     * Store a new DTR row.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'dtr_month_id' => 'required|exists:dtr_months,id',
            'date' => 'required|date',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'break_minutes' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        $dtrMonth = DtrMonth::findOrFail($validated['dtr_month_id']);
        $this->authorize('create', [DtrRow::class, $dtrMonth]);

        if ($dtrMonth->is_fulfilled) {
            return response()->json([
                'error' => 'Cannot add rows to a finished month.',
            ], 422);
        }

        $date = Carbon::parse($validated['date']);
        if ((int) $date->month !== (int) $dtrMonth->month || (int) $date->year !== (int) $dtrMonth->year) {
            return response()->json([
                'error' => 'Date must be within the selected month.',
            ], 422);
        }

        // Check for duplicate date
        $existingRow = $dtrMonth->rows()
            ->where('date', $validated['date'])
            ->first();

        if ($existingRow) {
            return response()->json([
                'error' => 'A row already exists for this date.',
            ], 422);
        }

        $day = Carbon::parse($validated['date'])->format('l');

        $breakMinutes = $validated['break_minutes'] ?? 0;

        $timeIn = \DateTime::createFromFormat('H:i', $validated['time_in']);
        $timeOut = $validated['time_out']
            ? \DateTime::createFromFormat('H:i', $validated['time_out'])
            : null;

        if ($timeOut && $timeOut <= $timeIn) {
            return response()->json([
                'error' => 'Time out must be after time in.',
            ], 422);
        }

        $totalMinutes = 0;
        $status = 'draft';
        if ($timeOut) {
            $diff = $timeOut->diff($timeIn);
            $workedMinutes = ($diff->h * 60) + $diff->i;
            $totalMinutes = max(0, $workedMinutes - $breakMinutes);
            $status = 'finished';
        }

        $row = DtrRow::create([
            'dtr_month_id' => $validated['dtr_month_id'],
            'date' => $validated['date'],
            'day' => $day,
            'time_in' => $validated['time_in'] ?? null,
            'time_out' => $validated['time_out'] ?? null,
            'total_minutes' => $totalMinutes,
            'break_minutes' => $breakMinutes,
            'status' => $status,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return response()->json([
            'id' => $row->id,
            'date' => $row->date->format('Y-m-d'),
            'day' => $row->day,
            'time_in' => $row->time_in,
            'time_out' => $row->time_out,
            'total_hours' => $row->total_minutes ? round($row->total_minutes / 60, 2) : 0,
            'total_minutes' => $row->total_minutes,
            'break_minutes' => $row->break_minutes,
            'status' => $row->status,
            'remarks' => $row->remarks,
        ]);
    }

    // Update a DTR row.
    public function update(Request $request, DtrRow $row)
    {
        $this->authorize('update', $row);

        if ($row->dtrMonth->is_fulfilled) {
            return response()->json([
                'error' => 'Cannot update rows in a finished month.',
            ], 422);
        }

        $validated = $request->validate([
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'break_minutes' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        $timeIn = \DateTime::createFromFormat('H:i', $validated['time_in']);
        $timeOut = $validated['time_out']
            ? \DateTime::createFromFormat('H:i', $validated['time_out'])
            : null;

        if ($timeOut && $timeOut <= $timeIn) {
            return response()->json([
                'error' => 'Time out must be after time in.',
            ], 422);
        }

        $breakMinutes = $validated['break_minutes'] ?? 0;
        $totalMinutes = 0;
        $status = 'draft';
        if ($timeOut) {
            $diff = $timeOut->diff($timeIn);
            $workedMinutes = ($diff->h * 60) + $diff->i;
            $totalMinutes = max(0, $workedMinutes - $breakMinutes);
            $status = 'finished';
        }
        $validated['total_minutes'] = $totalMinutes;
        $validated['status'] = $status;

        $row->update($validated);

        return response()->json([
            'id' => $row->id,
            'date' => $row->date->format('Y-m-d'),
            'day' => $row->day,
            'time_in' => $row->time_in,
            'time_out' => $row->time_out,
            'total_hours' => round($row->total_minutes / 60, 2),
            'total_minutes' => $row->total_minutes,
            'break_minutes' => $row->break_minutes,
            'status' => $row->status,
            'remarks' => $row->remarks,
        ]);
    }

    /**
     * Delete a DTR row.
     */
    public function destroy(Request $request, DtrRow $row)
    {
        $this->authorize('delete', $row);

        if ($row->dtrMonth->is_fulfilled) {
            return response()->json([
                'error' => 'Cannot delete rows from a finished month.',
            ], 422);
        }

        $row->delete();

        return response()->json(['message' => 'Row deleted successfully']);
    }
}
