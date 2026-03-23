<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class StarterAccountsSeeder extends Seeder
{
    /**
     * Seed starter login accounts for production/testing.
     */
    public function run(): void
    {
        $accounts = [
            [
                'email' => 'admin@example.com',
                'name' => 'System Admin',
                'password' => 'password123',
                'role' => User::ROLE_ADMIN,
                'employment_status' => 'active',
                'company' => 'DTR Web App',
                'department' => 'Administration',
                'supervisor_name' => 'Board',
                'supervisor_position' => 'Owner',
                'starting_date' => '2026-01-01',
                'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                'work_time_in' => '08:00:00',
                'work_time_out' => '17:00:00',
                'salary_type' => 'monthly',
                'salary_amount' => 50000,
                'default_break_minutes' => 60,
                'initial_paid_leave_days' => 0,
                'current_paid_leave_balance' => 0,
            ],
            [
                'email' => 'employee@example.com',
                'name' => 'Regular Employee',
                'password' => 'password123',
                'role' => User::ROLE_EMPLOYEE,
                'employment_status' => 'active',
                'company' => 'DTR Web App',
                'department' => 'Operations',
                'supervisor_name' => 'System Admin',
                'supervisor_position' => 'Admin',
                'starting_date' => '2026-01-01',
                'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                'work_time_in' => '08:00:00',
                'work_time_out' => '17:00:00',
                'salary_type' => 'monthly',
                'salary_amount' => 25000,
                'default_break_minutes' => 60,
                'initial_paid_leave_days' => 12,
                'current_paid_leave_balance' => 12,
            ],
            [
                'email' => 'intern@example.com',
                'name' => 'Intern User',
                'password' => 'password123',
                'role' => User::ROLE_INTERN,
                'student_name' => 'Intern User',
                'student_no' => 'INTERN100',
                'school' => 'Sample University',
                'required_hours' => 480,
                'intern_compensation_enabled' => true,
                'employment_status' => 'active',
                'company' => 'DTR Web App',
                'department' => 'Engineering',
                'supervisor_name' => 'System Admin',
                'supervisor_position' => 'Admin',
                'starting_date' => '2026-01-01',
                'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                'work_time_in' => '08:00:00',
                'work_time_out' => '17:00:00',
                'salary_type' => 'hourly',
                'salary_amount' => 75,
                'default_break_minutes' => 60,
                'initial_paid_leave_days' => 0,
                'current_paid_leave_balance' => 0,
            ],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                $account + ['email_verified_at' => now()]
            );
        }

        if ($this->command) {
            $this->command->info('Starter accounts created/updated:');
            $this->command->line('Admin: admin@example.com / password123');
            $this->command->line('Employee: employee@example.com / password123');
            $this->command->line('Intern: intern@example.com (or INTERN100) / password123');
        }
    }
}
