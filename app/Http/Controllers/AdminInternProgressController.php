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
        $interns = User::query()
            ->where('is_admin', false)
            ->where('employee_type', 'intern')
            ->orderBy('name')
            ->get()
            ->map(function (User $intern) {
                $loggedMinutes = DtrRow::query()
                    ->whereHas('dtrMonth', function ($query) use ($intern) {
                        $query->where('user_id', $intern->id);
                    })
                    ->where('status', 'finished')
                    ->sum('total_minutes');
                $loggedHours = round($loggedMinutes / 60, 2);
                $requiredHours = (float) ($intern->required_hours ?? 0);
                $remainingHours = max(0, round($requiredHours - $loggedHours, 2));
                $finishedRows = DtrRow::query()
                    ->whereHas('dtrMonth', function ($query) use ($intern) {
                        $query->where('user_id', $intern->id);
                    })
                    ->where('status', 'finished')
                    ->where('total_minutes', '>', 0)
                    ->count();
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
