<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // helper to avoid duplicates by email
        function userExists($email) {
            return DB::table('users')->where('email', $email)->exists();
        }

        // ADMIN
        if (!userExists('admin@test.com')) {
            DB::table('users')->insert([
                'name' => 'Admin User',
                'email' => 'admin@test.com',
                'profile_photo_path' => null,
                'email_verified_at' => now(),
                'is_admin' => 1,
                'role' => 'admin',
                'employment_status' => 'active',
                'password' => Hash::make('password'),
                'must_change_password' => 0,
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // EMPLOYEES
        for ($i = 1; $i <= 20; $i++) {
            $email = "employee{$i}@test.com";

            if (userExists($email)) continue;

            DB::table('users')->insert([
                'name' => "Employee {$i}",
                'email' => $email,
                'profile_photo_path' => null,
                'email_verified_at' => now(),
                'is_admin' => 0,
                'role' => 'employee',
                'employment_status' => 'active',

                'password' => Hash::make('password'),
                'must_change_password' => 0,
                'remember_token' => Str::random(10),

                'student_name' => "Employee {$i}",
                'required_hours' => 0,

                'company' => 'Test Company',
                'department' => 'Operations',
                'supervisor_name' => 'Supervisor X',
                'supervisor_position' => 'Manager',

                'employee_type' => 'regular',
                'intern_compensation_enabled' => 0,

                'starting_date' => now()->subYears(rand(1,5))->format('Y-m-d'),
                'working_days' => json_encode([1,2,3,4,5]),
                'work_time_in' => '09:00:00',
                'work_time_out' => '18:00:00',
                'default_break_minutes' => 60,

                'salary_type' => 'monthly',
                'salary_amount' => rand(20000, 40000),

                'initial_paid_leave_days' => 5,
                'current_paid_leave_balance' => 5,
                'leave_reset_month' => 1,
                'leave_reset_day' => 1,
                'last_leave_refresh_year' => now()->year,

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // INTERNS
        for ($i = 1; $i <= 10; $i++) {
            $email = "intern{$i}@test.com";

            if (userExists($email)) continue;

            DB::table('users')->insert([
                'name' => "Intern {$i}",
                'email' => $email,
                'profile_photo_path' => null,
                'email_verified_at' => now(),
                'is_admin' => 0,
                'role' => 'intern',
                'employment_status' => 'active',

                'password' => Hash::make('password'),
                'must_change_password' => 0,
                'remember_token' => Str::random(10),

                'student_name' => "Intern {$i}",
                'student_no' => "INT-2026-" . str_pad($i, 3, '0', STR_PAD_LEFT),
                'school' => 'Pampanga State University',
                'required_hours' => 480,

                'company' => 'Test Company',
                'department' => 'IT',
                'supervisor_name' => 'Supervisor Y',
                'supervisor_position' => 'Team Lead',

                'employee_type' => 'intern',
                'intern_compensation_enabled' => rand(0,1),

                'starting_date' => now()->subMonths(rand(1,3))->format('Y-m-d'),
                'working_days' => json_encode([1,2,3,4,5]),
                'work_time_in' => '08:30:00',
                'work_time_out' => '17:30:00',
                'default_break_minutes' => 15,

                'salary_type' => 'hourly',
                'salary_amount' => 25,

                'initial_paid_leave_days' => 0,
                'current_paid_leave_balance' => 0,
                'leave_reset_month' => 1,
                'leave_reset_day' => 1,
                'last_leave_refresh_year' => now()->year,

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        echo "Users seeded (no duplicates)\n";
    }
}