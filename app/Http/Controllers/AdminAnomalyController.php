<?php

namespace App\Http\Controllers;

use App\Models\DtrRow;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdminAnomalyController extends Controller
{
    public function index(): Response
    {
        $timezone = 'Asia/Manila';
        $today = Carbon::now($timezone)->toDateString();

        $rows = DtrRow::query()
            ->whereHas('dtrMonth.user', function ($query) {
                $query->where('is_admin', false);
            })
            ->with('dtrMonth.user:id,name,email,employee_type,work_time_in,work_time_out,default_break_minutes')
            ->whereDate('date', '<=', $today)
            ->orderByDesc('date')
            ->orderByDesc('updated_at')
            ->get();

        $anomalies = [];
        foreach ($rows as $row) {
            $employee = $row->dtrMonth->user;
            if (! $employee) {
                continue;
            }

            $expectedDailyMinutes = $this->expectedDailyMinutes(
                $employee->work_time_in,
                $employee->work_time_out,
                (int) ($employee->default_break_minutes ?? 60)
            );
            $minutes = (int) $row->total_minutes;
            $lateMinutes = (int) $row->late_minutes;

            if ($lateMinutes > 0) {
                $anomalies[] = $this->payload($row, $employee->name, $employee->email, 'late', $lateMinutes.' minutes late');
            }

            if ($row->status === 'finished' && $expectedDailyMinutes > 0 && $minutes < $expectedDailyMinutes) {
                $anomalies[] = $this->payload(
                    $row,
                    $employee->name,
                    $employee->email,
                    'undertime',
                    ($expectedDailyMinutes - $minutes).' minutes undertime'
                );
            }

            if ($row->status === 'finished' && $expectedDailyMinutes > 0 && $minutes > $expectedDailyMinutes) {
                $anomalies[] = $this->payload(
                    $row,
                    $employee->name,
                    $employee->email,
                    'overtime',
                    ($minutes - $expectedDailyMinutes).' minutes overtime'
                );
            }

            $hasMissingLogs = ($row->status === 'finished' && (! $row->time_in || ! $row->time_out))
                || (in_array($row->status, ['draft', 'missed'], true) && ! $row->time_in && ! $row->time_out && $row->date->lt(Carbon::parse($today, $timezone)));
            if ($hasMissingLogs) {
                $anomalies[] = $this->payload($row, $employee->name, $employee->email, 'missing_logs', 'Missing IN/OUT logs');
            }
        }

        return Inertia::render('Admin/Anomalies/Index', [
            'anomalies' => collect($anomalies)->values(),
        ]);
    }

    private function payload(DtrRow $row, string $employeeName, string $employeeEmail, string $type, string $details): array
    {
        return [
            'row_id' => $row->id,
            'employee_id' => $row->dtrMonth->user_id,
            'employee_name' => $employeeName,
            'employee_email' => $employeeEmail,
            'date' => $row->date->format('Y-m-d'),
            'day' => $row->day,
            'type' => $type,
            'details' => $details,
            'time_in' => $row->time_in,
            'time_out' => $row->time_out,
            'status' => $row->status,
        ];
    }

    private function expectedDailyMinutes(?string $timeIn, ?string $timeOut, int $defaultBreakMinutes = 60): int
    {
        $normalizedIn = $this->normalizeTime($timeIn);
        $normalizedOut = $this->normalizeTime($timeOut);

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
}
