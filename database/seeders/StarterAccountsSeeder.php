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
        $forceReset = filter_var((string) env('STARTER_ACCOUNTS_FORCE_RESET', false), FILTER_VALIDATE_BOOLEAN);
        $allowOnNonEmptyDatabase = filter_var((string) env('STARTER_ACCOUNTS_ALLOW_ON_NON_EMPTY_DB', false), FILTER_VALIDATE_BOOLEAN);
        $defaultPassword = (string) env('STARTER_ACCOUNTS_PASSWORD', 'password123');

        if (! $forceReset && ! $allowOnNonEmptyDatabase && User::query()->exists()) {
            if ($this->command) {
                $this->command->info('Starter accounts skipped: database already contains users.');
            }

            return;
        }

        $accounts = [
            [
                'email' => 'admin@example.com',
                'name' => 'System Admin',
                'password' => $defaultPassword,
                'role' => User::ROLE_ADMIN,
                'employment_status' => 'active',
                'company' => 'DTR Web App',
                'department' => 'Administration',
                'supervisor_name' => 'Board',
                'supervisor_position' => 'Owner',
                'starting_date' => '2026-01-01',
                'working_days' => [1, 2, 3, 4, 5],
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
                'password' => $defaultPassword,
                'role' => User::ROLE_EMPLOYEE,
                'employment_status' => 'active',
                'company' => 'DTR Web App',
                'department' => 'Operations',
                'supervisor_name' => 'System Admin',
                'supervisor_position' => 'Admin',
                'starting_date' => '2026-01-01',
                'working_days' => [1, 2, 3, 4, 5],
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
                'password' => $defaultPassword,
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
                'working_days' => [1, 2, 3, 4, 5],
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
            if ($forceReset) {
                User::updateOrCreate(
                    ['email' => $account['email']],
                    $account + ['email_verified_at' => now()]
                );

                continue;
            }

            User::firstOrCreate(
                ['email' => $account['email']],
                $account + ['email_verified_at' => now()]
            );
        }

        if ($this->command) {
            $this->command->info($forceReset ? 'Starter accounts created/updated:' : 'Starter accounts ensured:');
            $maskedPassword = str_repeat('*', max(8, strlen($defaultPassword)));
            $this->command->line("Admin: admin@example.com / {$maskedPassword}");
            $this->command->line("Employee: employee@example.com / {$maskedPassword}");
            $this->command->line("Intern: intern@example.com (or INTERN100) / {$maskedPassword}");
            if (! $forceReset) {
                $this->command->line('Existing accounts keep their current password unless STARTER_ACCOUNTS_FORCE_RESET=true.');
            }
        }
    }
}
