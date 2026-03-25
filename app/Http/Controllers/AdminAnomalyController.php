<?php

namespace App\Http\Controllers;

use App\Models\DtrRow;
use App\Models\User;
use App\Support\AttendanceTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAnomalyController extends Controller
{
    public function index(Request $request): Response
    {
        $timezone = 'Asia/Manila';
        $today = Carbon::now($timezone)->toDateString();
        $days = max(7, min(365, (int) $request->query('days', 90)));
        $sinceDate = Carbon::now($timezone)->subDays($days)->toDateString();

        $rows = DtrRow::query()
            ->whereHas('dtrMonth.user', function ($query) {
                $query->where('is_admin', false)
                    ->where(function ($roleQuery) {
                        $roleQuery->whereNull('role')
                            ->orWhere('role', '!=', User::ROLE_ADMIN);
                    });
            })
            ->whereDate('date', '>=', $sinceDate)
            ->with('dtrMonth.user:id,name,email,employee_type,work_time_in,work_time_out,default_break_minutes')
            ->whereDate('date', '<=', $today)
            ->orderByDesc('date')
            ->orderByDesc('updated_at')
            ->limit(3000)
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

            $isPastRow = $row->date->lt(Carbon::parse($today, $timezone));
            $hasIncompletePair = ($row->time_in && ! $row->time_out) || (! $row->time_in && $row->time_out);
            $hasMissingLogs = ($row->status === 'finished' && (! $row->time_in || ! $row->time_out))
                || ($isPastRow && $hasIncompletePair)
                || ($isPastRow && in_array($row->status, ['draft', 'missed', 'in_progress'], true) && ! $row->time_in && ! $row->time_out);
            if ($hasMissingLogs) {
                $anomalies[] = $this->payload($row, $employee->name, $employee->email, 'missing_logs', 'Missing IN/OUT logs');
            }
        }

        return Inertia::render('Admin/Anomalies/Index', [
            'anomalies' => collect($anomalies)->values(),
            'since_days' => $days,
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
        return AttendanceTime::expectedDailyMinutes($timeIn, $timeOut, $defaultBreakMinutes);
    }
}
