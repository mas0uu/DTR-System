<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DtrRowSeeder extends Seeder
{
    public function run(): void
    {
        $targetTotal = 25000;
        $chunk = 1000;
        $rows = [];

        $totalUsers = DB::table('users')->count();
        $perUserTarget = ceil($targetTotal / max(1, $totalUsers));

        $users = DB::table('users')->pluck('id');

        foreach ($users as $userId) {

            $userCount = DB::table('dtr_rows')
                ->join('dtr_months', 'dtr_rows.dtr_month_id', '=', 'dtr_months.id')
                ->where('dtr_months.user_id', $userId)
                ->count();

            if ($userCount >= $perUserTarget) continue;

            $months = DB::table('dtr_months')
                ->where('user_id', $userId)
                ->whereBetween('year', [2010, 2020])
                ->orderBy('year')
                ->orderBy('month')
                ->get();

            foreach ($months as $m) {

                $start = Carbon::create($m->year, $m->month, 1)->startOfMonth();
                $end = $start->copy()->endOfMonth();

                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {

                    if ($userCount >= $perUserTarget) break 2;

                    // skip Sundays
                    if ($date->isSunday()) continue;

                    // prevent duplicates
                    $exists = DB::table('dtr_rows')
                        ->where('dtr_month_id', $m->id)
                        ->where('date', $date->toDateString())
                        ->exists();

                    if ($exists) continue;

                    $rows[] = [
                        'dtr_month_id' => $m->id,
                        'date' => $date->toDateString(),
                        'time_in' => '09:00:00',
                        'time_out' => '18:00:00',
                        'created_at' => now(),
                        'updated_at' => now(),
                        'day' => $date->format('l'),
                        'total_minutes' => 480,
                        'break_minutes' => 60,
                        'late_minutes' => 0,
                        'on_break' => 0,
                        'break_started_at' => null,
                        'break_target_minutes' => null,
                        'status' => 'finished',
                    ];

                    $userCount++;

                    if (count($rows) >= $chunk) {
                        DB::table('dtr_rows')->insert($rows);
                        $rows = [];
                    }
                }
            }
        }

        if (!empty($rows)) {
            DB::table('dtr_rows')->insert($rows);
        }

        echo "Balanced DTR rows seeded successfully\n";
    }
}