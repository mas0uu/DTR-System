<?php

namespace Tests\Feature;

use App\Models\DtrMonth;
use App\Models\DtrRow;
use App\Models\Holiday;
use App\Models\PayrollRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_month_finalized_payroll_locks_attendance_row_updates(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 12, 12, 0, 0, 'Asia/Manila'));

        try {
            $user = User::factory()->create([
                'employee_type' => 'regular',
                'starting_date' => '2026-03-01',
                'working_days' => [1, 2, 3, 4, 5],
                'work_time_in' => '09:00',
                'work_time_out' => '18:00',
                'salary_type' => 'monthly',
                'salary_amount' => 30000,
            ]);

            $month = DtrMonth::create([
                'user_id' => $user->id,
                'month' => 3,
                'year' => 2026,
                'is_fulfilled' => false,
            ]);

            $row = DtrRow::create([
                'dtr_month_id' => $month->id,
                'date' => '2026-03-10',
                'day' => 'Tuesday',
                'time_in' => null,
                'time_out' => null,
                'total_minutes' => 0,
                'break_minutes' => 0,
                'late_minutes' => 0,
                'on_break' => false,
                'status' => 'missed',
            ]);

            PayrollRecord::create([
                'user_id' => $user->id,
                'pay_period_start' => '2026-02-25',
                'pay_period_end' => '2026-03-15',
                'salary_type' => 'monthly',
                'salary_amount' => 30000,
                'days_worked' => 10,
                'hours_worked' => 80,
                'absences' => 0,
                'undertime_minutes' => 0,
                'half_days' => 0,
                'total_salary' => 15000,
                'status' => 'finalized',
                'finalized_by' => $user->id,
                'finalized_at' => now(),
            ]);

            $response = $this->actingAs($user)->patch(route('dtr.rows.update', $row->id), [
                'time_in' => '09:00',
                'time_out' => '18:00',
                'break_minutes' => 60,
            ]);

            $response->assertStatus(422);
            $response->assertJsonPath('error', 'This row is locked because it belongs to a finalized payroll period.');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_clock_out_while_on_break_accrues_current_break_minutes(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 12, 12, 0, 0, 'Asia/Manila'));

        try {
            $user = User::factory()->create([
                'employee_type' => 'regular',
                'starting_date' => '2026-03-01',
                'working_days' => [1, 2, 3, 4, 5],
                'work_time_in' => '09:00',
                'work_time_out' => '18:00',
                'salary_type' => 'monthly',
                'salary_amount' => 30000,
            ]);

            $month = DtrMonth::create([
                'user_id' => $user->id,
                'month' => 3,
                'year' => 2026,
                'is_fulfilled' => false,
            ]);

            $row = DtrRow::create([
                'dtr_month_id' => $month->id,
                'date' => '2026-03-12',
                'day' => 'Thursday',
                'time_in' => '09:00:00',
                'time_out' => null,
                'total_minutes' => 0,
                'break_minutes' => 10,
                'late_minutes' => 0,
                'on_break' => true,
                'break_started_at' => Carbon::create(2026, 3, 12, 11, 30, 0, 'Asia/Manila'),
                'break_target_minutes' => 30,
                'status' => 'in_progress',
            ]);

            $response = $this->actingAs($user)->patch(route('dtr.rows.clock_out', $row->id));

            $response->assertOk();
            $response->assertJsonPath('break_minutes', 40);
            $response->assertJsonPath('total_minutes', 140);

            $row->refresh();
            $this->assertSame(40, (int) $row->break_minutes);
            $this->assertSame(140, (int) $row->total_minutes);
            $this->assertSame('finished', $row->status);
            $this->assertFalse((bool) $row->on_break);
            $this->assertNull($row->break_started_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_holiday_rows_are_not_kept_as_missed_when_refreshing_dtr(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 10, 12, 0, 0, 'Asia/Manila'));

        try {
            $user = User::factory()->create([
                'employee_type' => 'regular',
                'starting_date' => '2026-04-01',
                'working_days' => [1, 2, 3, 4, 5],
                'work_time_in' => '09:00',
                'work_time_out' => '18:00',
                'salary_type' => 'monthly',
                'salary_amount' => 30000,
            ]);

            $month = DtrMonth::create([
                'user_id' => $user->id,
                'month' => 4,
                'year' => 2026,
                'is_fulfilled' => false,
            ]);

            $holidayRow = DtrRow::create([
                'dtr_month_id' => $month->id,
                'date' => '2026-04-02',
                'day' => 'Thursday',
                'time_in' => null,
                'time_out' => null,
                'total_minutes' => 0,
                'break_minutes' => 0,
                'late_minutes' => 0,
                'on_break' => false,
                'status' => 'missed',
            ]);

            $regularWorkdayRow = DtrRow::create([
                'dtr_month_id' => $month->id,
                'date' => '2026-04-01',
                'day' => 'Wednesday',
                'time_in' => null,
                'time_out' => null,
                'total_minutes' => 0,
                'break_minutes' => 0,
                'late_minutes' => 0,
                'on_break' => false,
                'status' => 'draft',
            ]);

            Holiday::create([
                'name' => 'Maundy Thursday',
                'date_start' => '2026-04-02',
                'date_end' => null,
                'holiday_type' => 'regular',
                'is_paid' => true,
                'has_attendance_bonus' => true,
                'attendance_bonus_type' => 'percent',
                'attendance_bonus_value' => 100,
                'is_active' => true,
                'created_by' => $user->id,
            ]);

            $response = $this->actingAs($user)->get(route('dtr.index'));

            $response->assertOk();

            $holidayRow->refresh();
            $regularWorkdayRow->refresh();

            $this->assertSame('draft', $holidayRow->status);
            $this->assertSame('missed', $regularWorkdayRow->status);
        } finally {
            Carbon::setTestNow();
        }
    }
}
