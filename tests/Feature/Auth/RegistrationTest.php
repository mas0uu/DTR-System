<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'student_name' => 'Test User',
            'student_no' => '2026123456',
            'email' => 'test@example.com',
            'school' => 'Test University',
            'required_hours' => 480,
            'company' => 'Test Company',
            'department' => 'IT Department',
            'supervisor_name' => 'Supervisor Name',
            'supervisor_position' => 'Supervisor Position',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice'));
    }
}
