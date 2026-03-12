<?php

namespace App\Support;

use Carbon\Carbon;

final class AttendanceTime
{
    public static function expectedDailyMinutes(?string $timeIn, ?string $timeOut, int $defaultBreakMinutes = 60): int
    {
        $normalizedIn = self::normalizeToHis($timeIn);
        $normalizedOut = self::normalizeToHis($timeOut);

        if (! $normalizedIn || ! $normalizedOut) {
            return 0;
        }

        $in = Carbon::createFromFormat('H:i:s', $normalizedIn);
        $out = Carbon::createFromFormat('H:i:s', $normalizedOut);
        if (! $out->gt($in)) {
            return 0;
        }

        return max(0, $in->diffInMinutes($out) - max(0, $defaultBreakMinutes));
    }

    public static function normalizeToHi(?string $time): ?string
    {
        $normalized = self::normalizeToHis($time);

        return $normalized
            ? Carbon::createFromFormat('H:i:s', $normalized)->format('H:i')
            : null;
    }

    public static function normalizeToHis(?string $time): ?string
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
}
