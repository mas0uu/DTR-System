<?php

namespace App\Services;

use App\Models\LeaveBalanceRefreshLog;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveBalanceService
{
    public function requestedDaysForLeave(LeaveRequest $leaveRequest): float
    {
        $value = (float) ($leaveRequest->requested_days ?? 1);

        return max(0.0, round($value, 2));
    }

    public function hasSufficientPaidLeaveBalance(User $user, float $days): bool
    {
        if (! $user->isPaidLeaveEligible()) {
            return false;
        }

        return (float) $user->current_paid_leave_balance >= $days;
    }

    public function applyApprovedLeave(LeaveRequest $leaveRequest, User $reviewer): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $reviewer) {
            /** @var LeaveRequest $lockedRequest */
            $lockedRequest = LeaveRequest::query()
                ->lockForUpdate()
                ->findOrFail($leaveRequest->id);

            /** @var User $employee */
            $employee = User::query()->lockForUpdate()->findOrFail($lockedRequest->user_id);
            $days = $this->requestedDaysForLeave($lockedRequest);
            $approvedPaidDays = 0.0;
            $approvedUnpaidDays = $days;
            $deductedDays = 0.0;
            $balanceBefore = null;
            $balanceAfter = null;

            if ($lockedRequest->request_type === 'leave' && (bool) $lockedRequest->is_paid && $employee->isPaidLeaveEligible()) {
                $balanceBefore = round((float) $employee->current_paid_leave_balance, 2);
                if ($balanceBefore < $days) {
                    throw ValidationException::withMessages([
                        'status' => 'Insufficient paid leave balance for this request.',
                    ]);
                }

                $approvedPaidDays = $days;
                $approvedUnpaidDays = 0.0;
                $deductedDays = $days;
                $balanceAfter = round($balanceBefore - $days, 2);

                $employee->current_paid_leave_balance = $balanceAfter;
                $employee->save();
            }

            $lockedRequest->approved_paid_days = $approvedPaidDays;
            $lockedRequest->approved_unpaid_days = $approvedUnpaidDays;
            $lockedRequest->deducted_days = $deductedDays;
            $lockedRequest->balance_before = $balanceBefore;
            $lockedRequest->balance_after = $balanceAfter;
            $lockedRequest->reviewed_by = $reviewer->id;
            $lockedRequest->reviewed_at = now();
            $lockedRequest->save();

            return $lockedRequest->fresh();
        });
    }

    public function markLeaveRejected(LeaveRequest $leaveRequest, User $reviewer): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $reviewer) {
            /** @var LeaveRequest $lockedRequest */
            $lockedRequest = LeaveRequest::query()
                ->lockForUpdate()
                ->findOrFail($leaveRequest->id);

            $lockedRequest->approved_paid_days = 0;
            $lockedRequest->approved_unpaid_days = 0;
            $lockedRequest->deducted_days = 0;
            $lockedRequest->balance_before = null;
            $lockedRequest->balance_after = null;
            $lockedRequest->reviewed_by = $reviewer->id;
            $lockedRequest->reviewed_at = now();
            $lockedRequest->save();

            return $lockedRequest->fresh();
        });
    }

    public function refreshAnnualBalances(?Carbon $referenceDate = null, ?User $actor = null): int
    {
        $userIds = User::query()
            ->where('is_admin', false)
            ->where('employee_type', 'regular')
            ->pluck('id');
        $refreshed = 0;

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if ($user && $this->refreshAnnualBalanceForUser($user, $referenceDate, $actor)) {
                $refreshed++;
            }
        }

        return $refreshed;
    }

    public function refreshAnnualBalanceForUser(User $user, ?Carbon $referenceDate = null, ?User $actor = null): bool
    {
        $today = ($referenceDate ?: now('Asia/Manila'))->copy()->startOfDay();
        $year = (int) $today->year;

        return DB::transaction(function () use ($actor, $today, $user, $year) {
            /** @var User|null $lockedUser */
            $lockedUser = User::query()->lockForUpdate()->find($user->id);
            if (! $lockedUser || ! $lockedUser->isPaidLeaveEligible()) {
                return false;
            }

            if ((int) ($lockedUser->last_leave_refresh_year ?? 0) >= $year) {
                return false;
            }

            if (! $this->isRefreshDateReached($lockedUser, $today)) {
                return false;
            }

            $existingLog = LeaveBalanceRefreshLog::query()
                ->where('user_id', $lockedUser->id)
                ->where('refresh_year', $year)
                ->exists();
            if ($existingLog) {
                $lockedUser->last_leave_refresh_year = $year;
                $lockedUser->save();

                return false;
            }

            $balanceBefore = round((float) $lockedUser->current_paid_leave_balance, 2);
            $allocation = max(0.0, round((float) $lockedUser->initial_paid_leave_days, 2));
            $balanceAfter = round($balanceBefore + $allocation, 2);

            $lockedUser->current_paid_leave_balance = $balanceAfter;
            $lockedUser->last_leave_refresh_year = $year;
            $lockedUser->save();

            LeaveBalanceRefreshLog::query()->create([
                'user_id' => $lockedUser->id,
                'refresh_year' => $year,
                'balance_before' => $balanceBefore,
                'allocation_added' => $allocation,
                'balance_after' => $balanceAfter,
                'refreshed_by' => $actor?->id,
                'source' => 'annual',
                'reason' => 'Annual paid leave allocation refresh with carry-over.',
            ]);

            return true;
        });
    }

    private function isRefreshDateReached(User $user, Carbon $today): bool
    {
        $year = (int) $today->year;
        $month = (int) ($user->leave_reset_month ?: 1);
        $month = max(1, min(12, $month));

        $firstOfMonth = Carbon::create($year, $month, 1, 0, 0, 0, $today->getTimezone());
        $maxDay = (int) $firstOfMonth->daysInMonth;
        $day = (int) ($user->leave_reset_day ?: 1);
        $day = max(1, min($maxDay, $day));
        $refreshDate = Carbon::create($year, $month, $day, 0, 0, 0, $today->getTimezone());

        return $today->greaterThanOrEqualTo($refreshDate);
    }
}
