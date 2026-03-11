<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_admin_employee_and_intern_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), [
                'name' => 'New Admin',
                'email' => 'new-admin@example.com',
                'role' => User::ROLE_ADMIN,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), [
                'name' => 'Regular User',
                'email' => 'employee@example.com',
                'role' => User::ROLE_EMPLOYEE,
                'department' => 'Engineering',
                'salary_type' => 'monthly',
                'salary_amount' => 30000,
                'initial_paid_leave_days' => 10,
                'starting_date' => now()->toDateString(),
                'working_days' => [1, 2, 3, 4, 5],
                'work_time_in' => '09:00',
                'work_time_out' => '18:00',
                'default_break_minutes' => 60,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), [
                'name' => 'Intern User',
                'email' => 'intern@example.com',
                'role' => User::ROLE_INTERN,
                'student_no' => '202600001',
                'school' => 'Test University',
                'required_hours' => 480,
                'supervisor_name' => 'Supervisor',
                'starting_date' => now()->toDateString(),
                'working_days' => [1, 2, 3, 4, 5],
                'work_time_in' => '09:00',
                'work_time_out' => '18:00',
                'default_break_minutes' => 60,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'new-admin@example.com',
            'role' => User::ROLE_ADMIN,
            'is_admin' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'employee@example.com',
            'role' => User::ROLE_EMPLOYEE,
            'employee_type' => 'regular',
            'salary_type' => 'monthly',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'intern@example.com',
            'role' => User::ROLE_INTERN,
            'employee_type' => 'intern',
            'required_hours' => 480,
        ]);
    }

    public function test_non_admin_cannot_access_admin_user_creation_routes(): void
    {
        $employee = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'is_admin' => false,
        ]);

        $this->actingAs($employee)
            ->get(route('admin.employees.create'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('admin.employees.store'), [
                'name' => 'Unauthorized',
                'email' => 'unauthorized@example.com',
                'role' => User::ROLE_ADMIN,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_remove_their_own_admin_role(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'owner@example.com',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.employees.update', $admin->id), [
                'name' => 'Owner',
                'email' => 'owner@example.com',
                'role' => User::ROLE_EMPLOYEE,
                'department' => 'Operations',
                'salary_type' => 'monthly',
                'salary_amount' => 10000,
                'initial_paid_leave_days' => 5,
                'starting_date' => now()->toDateString(),
                'working_days' => [1, 2, 3, 4, 5],
                'work_time_in' => '09:00',
                'work_time_out' => '18:00',
                'default_break_minutes' => 60,
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => User::ROLE_ADMIN,
            'is_admin' => true,
        ]);
    }

    public function test_last_admin_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'solo-admin@example.com',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.employees.destroy', $admin->id))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => User::ROLE_ADMIN,
        ]);
    }
}
