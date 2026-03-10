<?php

namespace App\Services;

use App\Models\PayrollRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class PayrollLockService
{
    public function isDateFinalized(int $userId, Carbon|string $date): bool
    {
        return $this->finalizedRecordsForDate($userId, $date)->isNotEmpty();
    }

    public function finalizedRecordsForDate(int $userId, Carbon|string $date): Collection
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return PayrollRecord::query()
            ->where('user_id', $userId)
            ->where('status', 'finalized')
            ->whereDate('pay_period_start', '<=', $dateString)
            ->whereDate('pay_period_end', '>=', $dateString)
            ->get()
            ->filter(fn (PayrollRecord $record) => $this->isMonthContainedPeriod($record));
    }

    public function resetFinalizedRecordsForDate(int $userId, Carbon|string $date, ?string $reason = null): int
    {
        $ids = $this->finalizedRecordsForDate($userId, $date)->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        return PayrollRecord::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => 'generated',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'finalized_by' => null,
                'finalized_at' => null,
                'lock_reason' => $reason,
                'correction_count' => DB::raw('correction_count + 1'),
                'updated_at' => now(),
            ]);
    }

    private function isMonthContainedPeriod(PayrollRecord $record): bool
    {
        if (! $record->pay_period_start || ! $record->pay_period_end) {
            return false;
        }

        return $record->pay_period_start->isSameMonth($record->pay_period_end);
    }
}
