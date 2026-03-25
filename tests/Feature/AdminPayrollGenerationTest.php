<?php

namespace Tests\Feature;

use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPayrollGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_generate_requires_single_calendar_month_period(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'is_admin' => false,
            'employee_type' => 'regular',
            'starting_date' => '2026-03-01',
            'working_days' => [1, 2, 3, 4, 5],
            'work_time_in' => '09:00',
            'work_time_out' => '18:00',
            'salary_type' => 'monthly',
            'salary_amount' => 30000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payroll.generate'), [
                'employee_id' => $employee->id,
                'pay_period_start' => '2026-03-25',
                'pay_period_end' => '2026-04-10',
            ])
            ->assertRedirect(route('admin.payroll.index'))
            ->assertSessionHasErrors([
                'payroll' => 'Payroll period must be within a single calendar month.',
            ]);
    }

    public function test_admin_generate_all_requires_single_calendar_month_period(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.payroll.generate_all'), [
                'pay_period_start' => '2026-03-25',
                'pay_period_end' => '2026-04-10',
            ])
            ->assertRedirect(route('admin.payroll.index'))
            ->assertSessionHasErrors([
                'payroll' => 'Payroll period must be within a single calendar month.',
            ]);
    }

    public function test_admin_generate_blocks_overlapping_payroll_periods(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'is_admin' => false,
            'employee_type' => 'regular',
            'salary_type' => 'monthly',
            'salary_amount' => 30000,
            'working_days' => [1, 2, 3, 4, 5],
            'work_time_in' => '09:00',
            'work_time_out' => '18:00',
        ]);

        PayrollRecord::query()->create([
            'user_id' => $employee->id,
            'pay_period_start' => '2026-03-01',
            'pay_period_end' => '2026-03-15',
            'salary_type' => 'monthly',
            'salary_amount' => 30000,
            'days_worked' => 10,
            'hours_worked' => 80,
            'absences' => 0,
            'undertime_minutes' => 0,
            'half_days' => 0,
            'base_pay' => 10000,
            'paid_leave_pay' => 0,
            'paid_holiday_base_pay' => 0,
            'holiday_attendance_bonus' => 0,
            'leave_deductions' => 0,
            'other_deductions' => 0,
            'total_deductions' => 0,
            'net_pay' => 10000,
            'total_salary' => 10000,
            'status' => 'generated',
            'source' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payroll.generate'), [
                'employee_id' => $employee->id,
                'pay_period_start' => '2026-03-10',
                'pay_period_end' => '2026-03-20',
            ])
            ->assertRedirect(route('admin.payroll.index'))
            ->assertSessionHasErrors([
                'payroll' => 'This payroll period overlaps with existing period 2026-03-01 to 2026-03-15 (status: generated).',
            ]);
    }
}
