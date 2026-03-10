<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminHolidayController extends Controller
{
    public function index(): Response
    {
        $holidays = Holiday::query()
            ->with('creator:id,name')
            ->orderByDesc('date_start')
            ->orderByDesc('id')
            ->get()
            ->map(function (Holiday $holiday) {
                $holidayType = $this->normalizeHolidayType((string) $holiday->holiday_type);

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
                    'is_active' => (bool) $holiday->is_active,
                    'created_by' => $holiday->creator?->name,
                ];
            });

        return Inertia::render('Admin/Holidays/Index', [
            'holidays' => $holidays,
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date_start' => 'required|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'holiday_type' => 'required|in:regular,special',
            'is_paid' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);
        $validated = [
            ...$validated,
            ...$this->deriveAttendanceBonusConfig((string) $validated['holiday_type'], (bool) $validated['is_paid']),
        ];

        $holiday = Holiday::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $auditLogger->log(
            $request->user(),
            'holiday.created',
            'holiday',
            $holiday->id,
            null,
            $holiday->only([
                'name',
                'date_start',
                'date_end',
                'holiday_type',
                'is_paid',
                'has_attendance_bonus',
                'attendance_bonus_type',
                'attendance_bonus_value',
                'is_active',
            ]),
            'Created holiday.',
            $request
        );

        return redirect()->route('admin.holidays.index')->with('success', 'Holiday created.');
    }

    public function update(Request $request, Holiday $holiday, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date_start' => 'required|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'holiday_type' => 'required|in:regular,special',
            'is_paid' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);
        $validated = [
            ...$validated,
            ...$this->deriveAttendanceBonusConfig((string) $validated['holiday_type'], (bool) $validated['is_paid']),
        ];
        $before = $holiday->only([
            'name',
            'date_start',
            'date_end',
            'holiday_type',
            'is_paid',
            'has_attendance_bonus',
            'attendance_bonus_type',
            'attendance_bonus_value',
            'is_active',
        ]);

        $holiday->update($validated);
        $auditLogger->log(
            $request->user(),
            'holiday.updated',
            'holiday',
            $holiday->id,
            $before,
            $holiday->only(array_keys($before)),
            'Updated holiday settings.',
            $request
        );

        return redirect()->route('admin.holidays.index')->with('success', 'Holiday updated.');
    }

    public function destroy(Request $request, Holiday $holiday, AuditLogger $auditLogger): RedirectResponse
    {
        $before = $holiday->only([
            'name',
            'date_start',
            'date_end',
            'holiday_type',
            'is_paid',
            'has_attendance_bonus',
            'attendance_bonus_type',
            'attendance_bonus_value',
            'is_active',
            'created_by',
        ]);
        $holidayId = $holiday->id;
        $holiday->delete();
        $auditLogger->log(
            $request->user(),
            'holiday.deleted',
            'holiday',
            $holidayId,
            $before,
            null,
            'Deleted holiday.',
            $request
        );

        return redirect()->route('admin.holidays.index')->with('success', 'Holiday deleted.');
    }

    private function deriveAttendanceBonusConfig(string $holidayType, bool $isPaid): array
    {
        if (! $isPaid) {
            return [
                'has_attendance_bonus' => false,
                'attendance_bonus_type' => null,
                'attendance_bonus_value' => null,
            ];
        }

        return match ($holidayType) {
            'regular' => [
                'has_attendance_bonus' => true,
                'attendance_bonus_type' => 'percent_of_daily_rate',
                'attendance_bonus_value' => 100,
            ],
            'special' => [
                'has_attendance_bonus' => true,
                'attendance_bonus_type' => 'percent_of_daily_rate',
                'attendance_bonus_value' => 30,
            ],
            default => [
                'has_attendance_bonus' => false,
                'attendance_bonus_type' => null,
                'attendance_bonus_value' => null,
            ],
        };
    }

    private function normalizeHolidayType(string $holidayType): string
    {
        return $holidayType === 'company' ? 'special' : $holidayType;
    }
}
