<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DtrMonth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DtrMonthController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $months = DtrMonth::where('user_id', $user->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get(['id', 'month', 'year', 'is_fulfilled'])
            ->map(function ($month) {
                $month->month_name = Carbon::create()->month($month->month)->format('F');
                return $month;
            });
            return response()->json($months);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $month = DtrMonth::firstOrCreate([
            'user_id' => $user->id,
            'month' => (int)$validated['month'],
            'year' => (int)$validated['year'],
            ],
            [
            'is_fulfilled' => false,
            ]
        );
        return response()->json($month, 201);
    }

    public function show(Request $request, DtrMonth $month)
    {
        $user = $request->user();
        abort_if($month->user_id !== $user->id, 403, 'Unauthorized');
        $month->load([
            'rows' => function ($query) {
                $query->orderBy('date');
            }
        ]);

        return response()->json([
            'data' => $month,
        ]);
    }
}