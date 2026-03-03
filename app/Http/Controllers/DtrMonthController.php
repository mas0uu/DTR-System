<?php

namespace App\Http\Controllers;

use App\Models\DtrMonth;
use App\Models\DtrRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DtrMonthController extends Controller
{
    //Display a listing of DTR months for the user.
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get or create current month
        $now = Carbon::now();
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
            ],
        ]);
    }

    // Show a specific DTR month with its rows.
    public function show(Request $request, DtrMonth $month)
    {
        $this->authorize('view', $month);

        $rows = $month->rows()
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                return [
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
                ];
            });

        $totalHours = $this->calculateTotalHours($month);
        $user = $request->user();
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
            'user' => [
                'student_name' => $user->student_name,
                'student_no' => $user->student_no,
                'school' => $user->school,
                'required_hours' => $user->required_hours,
                'company' => $user->company,
                'department' => $user->department,
                'supervisor_name' => $user->supervisor_name,
                'supervisor_position' => $user->supervisor_position,
            ],
        ]);
    }

    /**
     * Store a new DTR month.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer',
        ]);

        $now = Carbon::now();
        $currentMonth = DtrMonth::firstOrCreate([
            'user_id' => $request->user()->id,
            'month' => $now->month,
            'year' => $now->year,
        ]);

        $isCreatingDifferentMonth = (int) $validated['month'] !== (int) $currentMonth->month
            || (int) $validated['year'] !== (int) $currentMonth->year;
        $selectedMonthDate = Carbon::createFromDate((int) $validated['year'], (int) $validated['month'], 1)->startOfMonth();
        $currentMonthDate = Carbon::createFromDate($currentMonth->year, $currentMonth->month, 1)->startOfMonth();
        $isPastMonth = $selectedMonthDate->lt($currentMonthDate);

        if ($isCreatingDifferentMonth && ! $isPastMonth && ! $currentMonth->is_fulfilled) {
            return response()->json([
                'message' => 'Finish the current month first before adding future months.',
            ], 422);
        }

        $month = DtrMonth::firstOrCreate([
            'user_id' => $request->user()->id,
            'month' => $validated['month'],
            'year' => $validated['year'],
        ]);

        return response()->json($month, $month->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Mark a DTR month as fulfilled.
     */
    public function finish(Request $request, DtrMonth $month)
    {
        $this->authorize('update', $month);

        if ($month->is_fulfilled) {
            return response()->json([
                'message' => 'Month is already finished.',
            ]);
        }

        $month->update(['is_fulfilled' => true]);

        return response()->json([
            'message' => 'Month marked as finished.',
        ]);
    }

    /**
     * Delete a DTR month.
     */
    public function destroy(Request $request, DtrMonth $month)
    {
        $this->authorize('delete', $month);

        $now = Carbon::now();
        if ((int) $month->month === (int) $now->month && (int) $month->year === (int) $now->year) {
            return response()->json([
                'message' => 'Current month cannot be deleted.',
            ], 422);
        }

        $month->delete();

        return response()->json([
            'message' => 'Month deleted successfully.',
        ]);
    }

    /**
     * Calculate total hours for a month
     */
    private function calculateTotalHours(DtrMonth $month)
    {
        return $month->rows()
            ->where('status', 'finished')
            ->sum('total_minutes') / 60;
    }
}
