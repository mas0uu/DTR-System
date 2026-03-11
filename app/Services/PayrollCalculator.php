<?php

namespace App\Services;

use App\Models\DtrRow;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;

class PayrollCalculator
{
    public function calculate(User $user, Carbon $periodStart, Carbon $periodEnd): array
    {
        $timezone = 'Asia/Manila';
        $start = $periodStart->copy()->startOfDay();
        $end = $periodEnd->copy()->startOfDay();
        $monthRangeStart = $start->copy()->startOfMonth();
        $monthRangeEnd = $end->copy()->endOfMonth();

        $workingDays = collect($user->working_days ?? [])
            ->map(fn ($day) => (int) $day)
            ->all();

        $expectedDailyMinutes = $this->expectedDailyMinutes(
            $user->work_time_in,
            $user->work_time_out,
            (int) ($user->default_break_minutes ?? 60)
        );

        $rowsByDate = DtrRow::query()
            ->whereHas('dtrMonth', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn ($row) => $row->date->format('Y-m-d'));
        $approvedLeavesByDate = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('leave_date', '>=', $start->toDateString())
            ->whereDate('leave_date', '<=', $end->toDateString())
            ->get(['leave_date', 'request_type', 'is_paid', 'requested_days', 'approved_paid_days', 'approved_unpaid_days'])
            ->keyBy(fn ($leaveRequest) => Carbon::parse($leaveRequest->leave_date)->format('Y-m-d'));
        $holidays = Holiday::query()
            ->where('is_active', true)
            ->whereDate('date_start', '<=', $monthRangeEnd->toDateString())
            ->where(function ($query) use ($monthRangeStart) {
                $query->whereNull('date_end')
                    ->orWhereDate('date_end', '>=', $monthRangeStart->toDateString());
            })
            ->get([
                'date_start',
                'date_end',
                'holiday_type',
                'is_paid',
                'has_attendance_bonus',
                'attendance_bonus_type',
                'attendance_bonus_value',
            ]);

        $scheduledDays = 0;
        $dayEquivalentWorked = 0.0;
        $dayEquivalentWorkedForBase = 0.0;
        $workedMinutes = 0;
        $workedMinutesForBase = 0;
        $absences = 0;
        $undertimeMinutes = 0;
        $halfDays = 0;
        $workedEquivalentByMonth = [];
        $paidLeavePay = 0.0;
        $paidHolidayBasePay = 0.0;
        $holidayAttendanceBonus = 0.0;
        $paidLeaveDays = 0.0;
        $unpaidLeaveDays = 0.0;
        $paidHolidayDays = 0;
        $salaryType = (string) $user->salary_type;
        $salaryAmount = round((float) $user->salary_amount, 2);
        $isRegularEmployee = $user->employee_type === 'regular';
        $scheduledDaysByMonth = [];

        $effectiveStart = $user->starting_date
            ? Carbon::parse($user->starting_date, $timezone)->startOfDay()
            : $start->copy();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($date->lt($effectiveStart)) {
                continue;
            }

            if (! in_array($date->dayOfWeek, $workingDays, true)) {
                continue;
            }

            $holiday = $this->holidayForDate($date, $holidays);
            if ($holiday && $isRegularEmployee && (bool) $holiday->is_paid) {
                $key = $date->format('Y-m-d');
                $row = $rowsByDate->get($key);
                $dailyRate = $this->resolveDailyRate(
                    $salaryType,
                    $salaryAmount,
                    $expectedDailyMinutes,
                    $date,
                    $workingDays,
                    $holidays,
                    $effectiveStart,
                    $scheduledDaysByMonth
                );
                // Monthly salary already covers paid holidays via month proration.
                // Only daily/hourly employees receive an added paid-holiday base line.
                if ($salaryType !== 'monthly') {
                    $paidHolidayBasePay += $dailyRate;
                }
                $paidHolidayDays++;

                $isWorked = $row && $row->status === 'finished' && (int) $row->total_minutes > 0;
                if ($isWorked) {
                    $rowMinutes = (int) $row->total_minutes;
                    $workedMinutes += $rowMinutes;
                    $equivalent = $expectedDailyMinutes > 0
                        ? min(1, $rowMinutes / $expectedDailyMinutes)
                        : 1.0;
                    $dayEquivalentWorked += $equivalent;

                    $holidayAttendanceBonus += $this->resolveHolidayAttendanceBonus($holiday, $dailyRate);
                }

                continue;
            }

            $scheduledDays++;
            $key = $date->format('Y-m-d');
            $monthKey = $date->format('Y-m');
            $row = $rowsByDate->get($key);
            $approvedLeave = $approvedLeavesByDate->get($key);
            if ($approvedLeave && $approvedLeave->request_type === 'leave') {
                $approvedPaidDays = (float) ($approvedLeave->approved_paid_days ?: 0);
                $requestedDays = (float) ($approvedLeave->requested_days ?: 1);
                $effectivePaidDays = max(0.0, round($approvedPaidDays > 0 ? $approvedPaidDays : ((bool) $approvedLeave->is_paid ? $requestedDays : 0.0), 2));
                $dailyRate = $this->resolveDailyRate(
                    $salaryType,
                    $salaryAmount,
                    $expectedDailyMinutes,
                    $date,
                    $workingDays,
                    $holidays,
                    $effectiveStart,
                    $scheduledDaysByMonth
                );

                if ($isRegularEmployee && $effectivePaidDays > 0) {
                    $paidLeavePay += ($dailyRate * $effectivePaidDays);
                    $paidLeaveDays += $effectivePaidDays;
                    continue;
                }

                $unpaidLeaveDays += max(0.0, round($requestedDays, 2));
                continue;
            }

            if (! $row || $row->status !== 'finished' || (int) $row->total_minutes <= 0) {
                $absences++;
                continue;
            }

            $rowMinutes = (int) $row->total_minutes;
            $workedMinutes += $rowMinutes;
            $workedMinutesForBase += $rowMinutes;

            if ($expectedDailyMinutes > 0) {
                $equivalent = min(1, $rowMinutes / $expectedDailyMinutes);
                $dayEquivalentWorked += $equivalent;
                $dayEquivalentWorkedForBase += $equivalent;
                $workedEquivalentByMonth[$monthKey] = ($workedEquivalentByMonth[$monthKey] ?? 0) + $equivalent;

                if ($rowMinutes < $expectedDailyMinutes) {
                    $undertimeMinutes += $expectedDailyMinutes - $rowMinutes;
                }

                if ($rowMinutes < ($expectedDailyMinutes / 2)) {
                    $halfDays++;
                }
            } else {
                $dayEquivalentWorked += 1;
                $dayEquivalentWorkedForBase += 1;
                $workedEquivalentByMonth[$monthKey] = ($workedEquivalentByMonth[$monthKey] ?? 0) + 1;
            }
        }

        $daysWorked = round($dayEquivalentWorked, 2);
        $hoursWorked = round($workedMinutes / 60, 2);
        $basePay = $salaryType === 'monthly'
            ? $this->computeMonthlySalary(
                $salaryAmount,
                $workedEquivalentByMonth,
                $workingDays,
                $holidays,
                $effectiveStart,
                $start,
                $end
            )
            : $this->computeSalary(
                $salaryType,
                $salaryAmount,
                round($dayEquivalentWorkedForBase, 2),
                round($workedMinutesForBase / 60, 2),
                $scheduledDays
            );
        $basePay = round($basePay, 2);
        $paidLeavePay = round($paidLeavePay, 2);
        $paidHolidayBasePay = round($paidHolidayBasePay, 2);
        $holidayAttendanceBonus = round($holidayAttendanceBonus, 2);
        $leaveDeductions = 0.0;
        $otherDeductions = 0.0;
        $totalDeductions = round($leaveDeductions + $otherDeductions, 2);
        $totalSalary = round($basePay + $paidLeavePay + $paidHolidayBasePay + $holidayAttendanceBonus, 2);
        $netPay = round($totalSalary - $totalDeductions, 2);

        return [
            'pay_period_start' => $start->toDateString(),
            'pay_period_end' => $end->toDateString(),
            'salary_type' => $salaryType,
            'salary_amount' => $salaryAmount,
            'days_worked' => $daysWorked,
            'hours_worked' => $hoursWorked,
            'absences' => max(0, $absences),
            'undertime_minutes' => max(0, $undertimeMinutes),
            'half_days' => max(0, $halfDays),
            'base_pay' => $basePay,
            'paid_leave_pay' => $paidLeavePay,
            'paid_holiday_base_pay' => $paidHolidayBasePay,
            'holiday_attendance_bonus' => $holidayAttendanceBonus,
            'leave_deductions' => $leaveDeductions,
            'other_deductions' => $otherDeductions,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'total_salary' => $totalSalary,
            'scheduled_days' => $scheduledDays,
            'paid_leave_days' => round($paidLeaveDays, 2),
            'unpaid_leave_days' => round($unpaidLeaveDays, 2),
            'paid_holiday_days' => $paidHolidayDays,
        ];
    }

    private function resolveDailyRate(
        string $salaryType,
        float $salaryAmount,
        int $expectedDailyMinutes,
        Carbon $date,
        array $workingDays,
        $holidays,
        Carbon $effectiveStart,
        array &$scheduledDaysByMonth
    ): float {
        if ($salaryAmount <= 0) {
            return 0.0;
        }

        if ($salaryType === 'daily') {
            return round($salaryAmount, 2);
        }

        if ($salaryType === 'hourly') {
            return round($salaryAmount * (max(0, $expectedDailyMinutes) / 60), 2);
        }

        if ($salaryType !== 'monthly') {
            return 0.0;
        }

        $monthKey = $date->format('Y-m');
        if (! array_key_exists($monthKey, $scheduledDaysByMonth)) {
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            $scheduledDaysByMonth[$monthKey] = $this->countScheduledDays(
                $monthStart,
                $monthEnd,
                $workingDays,
                $holidays,
                $effectiveStart
            );
        }

        $scheduledInMonth = max(0, (int) $scheduledDaysByMonth[$monthKey]);

        return $scheduledInMonth > 0
            ? round($salaryAmount / $scheduledInMonth, 2)
            : 0.0;
    }

    private function expectedDailyMinutes(?string $timeIn, ?string $timeOut, int $defaultBreakMinutes = 60): int
    {
        $timezone = 'Asia/Manila';
        $normalizedIn = $this->normalizeTime($timeIn);
        $normalizedOut = $this->normalizeTime($timeOut);

        if (! $normalizedIn || ! $normalizedOut) {
            return 0;
        }

        $in = Carbon::createFromFormat('H:i:s', $normalizedIn, $timezone);
        $out = Carbon::createFromFormat('H:i:s', $normalizedOut, $timezone);

        if (! $out->gt($in)) {
            return 0;
        }

        return max(0, $in->diffInMinutes($out) - max(0, $defaultBreakMinutes));
    }

    private function normalizeTime(?string $time): ?string
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

    private function computeSalary(
        string $salaryType,
        float $salaryAmount,
        float $daysWorked,
        float $hoursWorked,
        int $scheduledDays
    ): float
    {
        $computed = match ($salaryType) {
            // Monthly pay is prorated against scheduled workdays within the selected period.
            'monthly' => $scheduledDays > 0 ? ($salaryAmount * ($daysWorked / $scheduledDays)) : 0,
            'daily' => $salaryAmount * $daysWorked,
            'hourly' => $salaryAmount * $hoursWorked,
            default => 0,
        };

        return round($computed, 2);
    }

    private function computeMonthlySalary(
        float $salaryAmount,
        array $workedEquivalentByMonth,
        array $workingDays,
        $holidays,
        Carbon $effectiveStart,
        Carbon $periodStart,
        Carbon $periodEnd
    ): float {
        if ($salaryAmount <= 0) {
            return 0.0;
        }

        $total = 0.0;
        $cursor = $periodStart->copy()->startOfMonth();
        $lastMonth = $periodEnd->copy()->endOfMonth();

        while ($cursor->lte($lastMonth)) {
            $monthKey = $cursor->format('Y-m');
            $workedEquivalent = (float) ($workedEquivalentByMonth[$monthKey] ?? 0);
            if ($workedEquivalent <= 0) {
                $cursor->addMonthNoOverflow();
                continue;
            }

            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $scheduledDaysInMonth = $this->countScheduledDays(
                $monthStart,
                $monthEnd,
                $workingDays,
                $holidays,
                $effectiveStart
            );

            if ($scheduledDaysInMonth > 0) {
                $total += $salaryAmount * ($workedEquivalent / $scheduledDaysInMonth);
            }

            $cursor->addMonthNoOverflow();
        }

        return round($total, 2);
    }

    private function countScheduledDays(
        Carbon $start,
        Carbon $end,
        array $workingDays,
        $holidays,
        Carbon $effectiveStart
    ): int {
        $count = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($date->lt($effectiveStart)) {
                continue;
            }

            if (! in_array($date->dayOfWeek, $workingDays, true)) {
                continue;
            }

            if ($this->isHoliday($date, $holidays)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function isHoliday(Carbon $date, $holidays): bool
    {
        return $this->holidayForDate($date, $holidays) !== null;
    }

    private function holidayForDate(Carbon $date, $holidays): ?Holiday
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

        return $matched;
    }

    private function holidayPremiumRate(string $holidayType): float
    {
        return match ($holidayType) {
            'regular' => 1.0,
            'special' => 0.3,
            default => 0.0,
        };
    }

    private function resolveHolidayAttendanceBonus(Holiday $holiday, float $dailyRate): float
    {
        if (! (bool) $holiday->has_attendance_bonus) {
            return 0.0;
        }

        $bonusType = (string) ($holiday->attendance_bonus_type ?? '');
        $bonusValue = round((float) ($holiday->attendance_bonus_value ?? 0), 2);
        if ($bonusType === 'fixed_amount') {
            return max(0.0, $bonusValue);
        }

        if ($bonusType === 'percent_of_daily_rate') {
            return round(max(0.0, $dailyRate) * (max(0.0, $bonusValue) / 100), 2);
        }

        $legacyRate = $this->holidayPremiumRate((string) $holiday->holiday_type);

        return round(max(0.0, $dailyRate) * max(0.0, $legacyRate), 2);
    }
}
