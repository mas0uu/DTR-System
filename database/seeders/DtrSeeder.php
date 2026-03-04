<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\DtrMonth;
use App\Models\DtrRow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DtrSeeder extends Seeder
{
    /**
     * Seed the DTR data.
     */
    public function run(): void
    {
        // Create sample intern user
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'intern001@intern.local',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'student_name' => 'John Doe',
            'student_no' => 'INTERN001',
            'school' => 'University of the Philippines',
            'required_hours' => 480,
            'company' => 'Tech Innovations Inc.',
            'department' => 'Software Development',
            'supervisor_name' => 'Maria Santos',
            'supervisor_position' => 'Senior Developer',
        ]);

        // Create multiple months with data
        $months = [
            ['month' => 2, 'year' => 2026, 'days' => 15],
            ['month' => 3, 'year' => 2026, 'days' => 20],
            ['month' => 4, 'year' => 2026, 'days' => 10],
        ];

        foreach ($months as $monthData) {
            $dtrMonth = DtrMonth::create([
                'user_id' => $user->id,
                'month' => $monthData['month'],
                'year' => $monthData['year'],
                'is_fulfilled' => false,
            ]);

            // Create sample attendance records
            $startDate = Carbon::createFromDate($monthData['year'], $monthData['month'], 1);
            $currentDate = $startDate->copy();
            $dayCount = 0;

            while ($dayCount < $monthData['days'] && $currentDate->month == $monthData['month']) {
                // Skip weekends
                if (!in_array($currentDate->dayOfWeek, [0, 6])) {
                    $timeIn = $currentDate->copy()->setTime(8, 30);
                    $timeOut = $currentDate->copy()->setTime(17, 30);
                    $totalMinutes = $timeOut->diffInMinutes($timeIn);

                    DtrRow::create([
                        'dtr_month_id' => $dtrMonth->id,
                        'date' => $currentDate->format('Y-m-d'),
                        'day' => $currentDate->format('l'),
                        'time_in' => $timeIn->format('H:i:s'),
                        'time_out' => $timeOut->format('H:i:s'),
                        'total_minutes' => $totalMinutes,
                        'status' => 'finished',
                    ]);

                    $dayCount++;
                }

                $currentDate->addDay();
            }
        }

        // Create another intern with fewer records
        $user2 = User::create([
            'name' => 'Jane Smith',
            'email' => 'intern002@intern.local',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'student_name' => 'Jane Smith',
            'student_no' => 'INTERN002',
            'school' => 'De La Salle University',
            'required_hours' => 480,
            'company' => 'Digital Solutions Ltd.',
            'department' => 'UI/UX Design',
            'supervisor_name' => 'Alex Johnson',
            'supervisor_position' => 'Design Manager',
        ]);

        // Create current month for second user
        $currentMonth = DtrMonth::create([
            'user_id' => $user2->id,
            'month' => Carbon::now()->month,
            'year' => Carbon::now()->year,
            'is_fulfilled' => false,
        ]);

        // Add some sample records
        for ($i = 1; $i <= 5; $i++) {
            $date = Carbon::now()->subDays($i);
            
            // Skip weekends
            if (!in_array($date->dayOfWeek, [0, 6])) {
                $timeIn = $date->copy()->setTime(9, 0);
                $timeOut = $date->copy()->setTime(18, 0);
                $totalMinutes = $timeOut->diffInMinutes($timeIn);

                DtrRow::create([
                    'dtr_month_id' => $currentMonth->id,
                    'date' => $date->format('Y-m-d'),
                    'day' => $date->format('l'),
                    'time_in' => $timeIn->format('H:i:s'),
                    'time_out' => $timeOut->format('H:i:s'),
                    'total_minutes' => $totalMinutes,
                    'status' => 'finished',
                ]);
            }
        }
    }
}
