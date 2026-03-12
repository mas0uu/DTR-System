<?php

namespace Tests\Feature;

use App\Models\DtrMonth;
use App\Models\DtrRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_generate_monthly_payroll_from_dtr_rows(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'employee_type' => 'regular',
            'starting_date' => '2026-03-01',
            'working_days' => [1, 2, 3, 4, 5],
            'work_time_in' => '09:00',
            'work_time_out' => '18:00',
            'salary_type' => 'monthly',
            'salary_amount' => 50000,
        ]);

        $month = DtrMonth::create([
            'user_id' => $user->id,
            'month' => 3,
            'year' => 2026,
            'is_fulfilled' => false,
        ]);

        DtrRow::create([
            'dtr_month_id' => $month->id,
            'date' => '2026-03-02',
            'day' => 'Monday',
            'time_in' => '09:00:00',
            'time_out' => '18:00:00',
            'total_minutes' => 540,
            'break_minutes' => 0,
            'late_minutes' => 0,
            'on_break' => false,
            'status' => 'finished',
        ]);

        DtrRow::create([
            'dtr_month_id' => $month->id,
            'date' => '2026-03-03',
            'day' => 'Tuesday',
            'time_in' => '09:00:00',
            'time_out' => '13:30:00',
            'total_minutes' => 270,
            'break_minutes' => 0,
            'late_minutes' => 0,
            'on_break' => false,
            'status' => 'finished',
        ]);

        $response = $this->actingAs($user)->post(route('payroll.generate'), [
            'pay_period_start' => '2026-03-02',
            'pay_period_end' => '2026-03-03',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.salary_type', 'monthly');
        $response->assertJsonPath('data.days_worked', 1.5);
        $response->assertJsonPath('data.hours_worked', 13.5);
        $response->assertJsonPath('data.undertime_minutes', 270);
        $response->assertJsonPath('data.half_days', 0);
        $response->assertJsonPath('data.total_salary', 3409.09);

        $this->assertDatabaseHas('payroll_records', [
            'user_id' => $user->id,
            'pay_period_start' => '2026-03-02',
            'pay_period_end' => '2026-03-03',
            'salary_type' => 'monthly',
            'salary_amount' => '50000.00',
            'days_worked' => '1.50',
            'hours_worked' => '13.50',
            'absences' => 0,
            'undertime_minutes' => 270,
            'total_salary' => '3409.09',
        ]);
    }

    public function test_payroll_period_must_be_within_single_calendar_month(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'employee_type' => 'regular',
            'starting_date' => '2026-03-01',
            'working_days' => [1, 2, 3, 4, 5],
            'work_time_in' => '09:00',
            'work_time_out' => '18:00',
            'salary_type' => 'monthly',
            'salary_amount' => 50000,
        ]);

        $response = $this->actingAs($user)->post(route('payroll.generate'), [
            'pay_period_start' => '2026-03-20',
            'pay_period_end' => '2026-04-05',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Payroll period must be within a single calendar month.');
    }
}
