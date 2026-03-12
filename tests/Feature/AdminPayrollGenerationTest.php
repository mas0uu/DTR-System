<?php

namespace Tests\Feature;

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
}
