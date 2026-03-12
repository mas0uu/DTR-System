<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeHolidayController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return redirect()->route('admin.holidays.index');
        }

        $holidays = Holiday::query()
            ->where('is_active', true)
            ->orderBy('date_start')
            ->orderBy('name')
            ->get()
            ->map(function (Holiday $holiday) {
                $holidayType = $holiday->holiday_type === 'company' ? 'special' : $holiday->holiday_type;

                return [
                    'id' => $holiday->id,
                    'name' => $holiday->name,
                    'date_start' => optional($holiday->date_start)->format('Y-m-d'),
                    'date_end' => optional($holiday->date_end)->format('Y-m-d'),
                    'holiday_type' => $holidayType,
                    'is_paid' => (bool) $holiday->is_paid,
                    'has_attendance_bonus' => (bool) $holiday->has_attendance_bonus,
                    'attendance_bonus_type' => $holiday->attendance_bonus_type,
                    'attendance_bonus_value' => $holiday->attendance_bonus_value !== null ? (float) $holiday->attendance_bonus_value : null,
                ];
            });

        return Inertia::render('Holidays/Index', [
            'holidays' => $holidays,
        ]);
    }
}
