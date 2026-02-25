<?php
 
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DtrMonth;
use App\Models\DtrRow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DtrRowController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        // Validate input
        $validated = $request->validate([
            'dtr_month_id' => 'required|integer|exists:dtr_months,id',
            'date' => 'required|date',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'remarks' => 'nullable|string|max:255',
        ]);

        $month = DtrMonth::findOrFail($validated['dtr_month_id']);
        abort_if($month->user_id !== $user->id, 403, 'Unauthorized');
        abort_if($month->is_fulfilled, 400, 'Cannot add rows to a fulfilled month');

        $date = Carbon::parse($validated['date']);
        abort_if(((int)$date->month !== (int)$month->month || (int)$date->year !== (int)$month->year), 400, 'Date must be within the month');

        // Use updateOrCreate to ensure only one row per date per month
        $row = DtrRow::updateOrCreate(
            [
                'dtr_month_id' => $month->id,
                'date' => $validated['date'],
            ],
            [
                'time_in' => $validated['time_in'] ?? null,
                'time_out' => $validated['time_out'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]
        );
        return response()->json([
            'data' => $row,
            'message' => 'DTR row created or updated successfully',
        ], 201);
    }

    // Update method for DTR Row
    public function update(Request $request, DtrRow $row)
    {
        $user = $request->user();
        
        $row->load('dtrMonth');
        abort_if($row->dtrMonth->user_id !== $user->id, 403, 'Unauthorized');
        abort_if($row->dtrMonth->is_fulfilled, 400, 'Cannot update rows in a fulfilled month');

        $validated = $request->validate([
            'date' => 'required|date',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'remarks' => 'nullable|string|max:255',
        ]);

        $row->fill($validated);
        $row->save();

        return response()->json([
            'data' => $row,
            'message' => 'DTR row updated successfully',
        ]);
    }

    // Destroy method for DTR Row
    public function destroy(Request $request, DtrRow $row)
    {
        $user = $request->user();
        
        $row->load('dtrMonth');
        abort_if($row->dtrMonth->user_id !== $user->id, 403, 'Unauthorized');
        abort_if($row->dtrMonth->is_fulfilled, 400, 'Cannot delete rows from a fulfilled month');

        $row->delete();

        return response()->json([
            'message' => 'DTR row deleted successfully',
        ]);
    }
}