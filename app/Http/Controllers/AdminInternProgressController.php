<?php

namespace App\Http\Controllers;

use App\Models\DtrRow;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdminInternProgressController extends Controller
{
    public function index(): Response
    {
        $internUsers = User::query()
            ->where('is_admin', false)
            ->where('role', User::ROLE_INTERN)
            ->orderBy('name')
            ->get();
        $internIds = $internUsers->pluck('id');
        $aggregates = DtrRow::query()
            ->join('dtr_months', 'dtr_months.id', '=', 'dtr_rows.dtr_month_id')
            ->whereIn('dtr_months.user_id', $internIds)
            ->selectRaw('dtr_months.user_id as user_id')
            ->selectRaw("SUM(CASE WHEN dtr_rows.status = 'finished' THEN dtr_rows.total_minutes ELSE 0 END) AS logged_minutes")
            ->selectRaw("SUM(CASE WHEN dtr_rows.status = 'finished' AND dtr_rows.total_minutes > 0 THEN 1 ELSE 0 END) AS finished_rows")
            ->groupBy('dtr_months.user_id')
            ->get()
            ->keyBy('user_id');
        $interns = $internUsers->map(function (User $intern) use ($aggregates) {
                $aggregate = $aggregates->get($intern->id);
                $loggedMinutes = (float) ($aggregate->logged_minutes ?? 0);
                $loggedHours = round($loggedMinutes / 60, 2);
                $requiredHours = (float) ($intern->required_hours ?? 0);
                $remainingHours = max(0, round($requiredHours - $loggedHours, 2));
                $finishedRows = (int) ($aggregate->finished_rows ?? 0);
                $averageDailyHours = $finishedRows > 0 ? round($loggedHours / $finishedRows, 2) : 0.0;
                $completionPercent = $requiredHours > 0
                    ? min(100, round(($loggedHours / $requiredHours) * 100, 2))
                    : 0;
                $status = $completionPercent >= 100 ? 'completed' : ($completionPercent >= 80 ? 'near_completion' : 'in_progress');
                $estimatedCompletionDate = $this->estimateCompletionDate(
                    $intern,
                    $remainingHours,
                    $averageDailyHours
                );

                return [
                    'id' => $intern->id,
                    'name' => $intern->name,
                    'email' => $intern->email,
                    'school' => $intern->school,
                    'intern_compensation_enabled' => (bool) $intern->intern_compensation_enabled,
                    'required_hours' => $requiredHours,
                    'logged_hours' => $loggedHours,
                    'remaining_hours' => $remainingHours,
                    'estimated_completion_date' => $estimatedCompletionDate,
                    'completion_percent' => $completionPercent,
                    'progress_status' => $status,
                ];
            });

        return Inertia::render('Admin/InternProgress/Index', [
            'interns' => $interns,
        ]);
    }

    private function estimateCompletionDate(User $intern, float $remainingHours, float $averageDailyHours): ?string
    {
        if ($remainingHours <= 0) {
            return Carbon::now('Asia/Manila')->toDateString();
        }
        if ($averageDailyHours <= 0) {
            return null;
        }

        $workingDays = collect($intern->working_days ?? [])->map(fn ($day) => (int) $day)->all();
        if (empty($workingDays)) {
            return null;
        }

        $neededDays = (int) ceil($remainingHours / $averageDailyHours);
        $cursor = Carbon::now('Asia/Manila')->startOfDay();
        $count = 0;

        while ($count < $neededDays) {
            $cursor->addDay();
            if (in_array($cursor->dayOfWeek, $workingDays, true)) {
                $count++;
            }
        }

        return $cursor->toDateString();
    }
}
