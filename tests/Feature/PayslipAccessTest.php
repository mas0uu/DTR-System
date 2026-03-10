<?php

namespace Tests\Feature;

use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_view_own_finalized_payslip(): void
    {
        $employee = User::factory()->create([
            'is_admin' => false,
            'employee_type' => 'regular',
            'employment_status' => 'active',
        ]);

        $record = PayrollRecord::create([
            'user_id' => $employee->id,
            'pay_period_start' => '2026-03-01',
            'pay_period_end' => '2026-03-15',
            'salary_type' => 'monthly',
            'salary_amount' => 32000,
            'days_worked' => 10,
            'hours_worked' => 80,
            'absences' => 0,
            'undertime_minutes' => 0,
            'half_days' => 0,
            'total_salary' => 16000,
            'status' => 'finalized',
            'source' => 'self',
            'finalized_by' => $employee->id,
            'finalized_at' => now(),
        ]);

        $response = $this->actingAs($employee)->get(route('payroll.payslip.view', $record->id));

        $response->assertOk();
    }

    public function test_employee_cannot_view_other_employee_payslip(): void
    {
        $owner = User::factory()->create([
            'is_admin' => false,
            'employee_type' => 'regular',
            'employment_status' => 'active',
        ]);
        $otherEmployee = User::factory()->create([
            'is_admin' => false,
            'employee_type' => 'regular',
            'employment_status' => 'active',
        ]);

        $record = PayrollRecord::create([
            'user_id' => $owner->id,
            'pay_period_start' => '2026-03-01',
            'pay_period_end' => '2026-03-15',
            'salary_type' => 'monthly',
            'salary_amount' => 32000,
            'days_worked' => 10,
            'hours_worked' => 80,
            'absences' => 0,
            'undertime_minutes' => 0,
            'half_days' => 0,
            'total_salary' => 16000,
            'status' => 'finalized',
            'source' => 'self',
            'finalized_by' => $owner->id,
            'finalized_at' => now(),
        ]);

        $response = $this->actingAs($otherEmployee)->get(route('payroll.payslip.view', $record->id));

        $response->assertForbidden();
    }

    public function test_employee_cannot_view_non_finalized_payslip(): void
    {
        $employee = User::factory()->create([
            'is_admin' => false,
            'employee_type' => 'regular',
            'employment_status' => 'active',
        ]);

        $record = PayrollRecord::create([
            'user_id' => $employee->id,
            'pay_period_start' => '2026-03-01',
            'pay_period_end' => '2026-03-15',
            'salary_type' => 'monthly',
            'salary_amount' => 32000,
            'days_worked' => 10,
            'hours_worked' => 80,
            'absences' => 0,
            'undertime_minutes' => 0,
            'half_days' => 0,
            'total_salary' => 16000,
            'status' => 'generated',
            'source' => 'self',
        ]);

        $response = $this->actingAs($employee)->get(route('payroll.payslip.view', $record->id));

        $response->assertForbidden();
    }

    public function test_admin_can_view_employee_finalized_payslip(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'employment_status' => 'active',
        ]);
        $employee = User::factory()->create([
            'is_admin' => false,
            'employee_type' => 'regular',
            'employment_status' => 'active',
        ]);

        $record = PayrollRecord::create([
            'user_id' => $employee->id,
            'pay_period_start' => '2026-03-01',
            'pay_period_end' => '2026-03-15',
            'salary_type' => 'monthly',
            'salary_amount' => 32000,
            'days_worked' => 10,
            'hours_worked' => 80,
            'absences' => 0,
            'undertime_minutes' => 0,
            'half_days' => 0,
            'total_salary' => 16000,
            'status' => 'finalized',
            'source' => 'admin',
            'finalized_by' => $admin->id,
            'finalized_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payroll.payslip.view', $record->id));

        $response->assertOk();
    }
}
