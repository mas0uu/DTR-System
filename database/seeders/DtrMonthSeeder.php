<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DtrMonthSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];
        $chunkSize = 1000;

        $userIds = DB::table('users')->pluck('id')->toArray();

        foreach ($userIds as $userId) {
            for ($year = 2010; $year <= 2020; $year++) {
                for ($month = 1; $month <= 12; $month++) {

                    $exists = DB::table('dtr_months')
                        ->where('user_id', $userId)
                        ->where('month', $month)
                        ->where('year', $year)
                        ->exists();

                    if ($exists) continue;

                    $rows[] = [
                        'user_id' => $userId,
                        'month' => $month,
                        'year' => $year,
                        'is_fulfilled' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($rows) >= $chunkSize) {
                        DB::table('dtr_months')->insert($rows);
                        $rows = [];
                    }
                }
            }
        }

        if (!empty($rows)) {
            DB::table('dtr_months')->insert($rows);
        }
    }
}