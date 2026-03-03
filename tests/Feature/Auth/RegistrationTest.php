<?php

namespace Tests\Feature\Auth;

use App\Models\DtrMonth;
use App\Models\DtrRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        Carbon::setTestNow(Carbon::parse('2026-03-03 10:00:00', 'Asia/Manila'));

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
            'employee_type' => 'intern',
            'starting_date' => '2026-03-01',
            'working_days' => [1, 2, 3, 4, 5],
            'work_time_in' => '09:00',
            'work_time_out' => '18:00',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'employee_type' => 'intern',
            'starting_date' => '2026-03-01',
        ]);

        $userId = auth()->id();
        $this->assertNotNull($userId);

        $month = DtrMonth::where('user_id', $userId)
            ->where('month', 3)
            ->where('year', 2026)
            ->first();

        $this->assertNotNull($month);

        $rows = DtrRow::where('dtr_month_id', $month->id)->orderBy('date')->get();
        $this->assertCount(3, $rows);
        $this->assertSame('2026-03-01', $rows[0]->date->format('Y-m-d'));
        $this->assertSame('draft', $rows[0]->status);
        $this->assertNull($rows[0]->time_in);
        $this->assertNull($rows[0]->time_out);
        $this->assertSame('2026-03-02', $rows[1]->date->format('Y-m-d'));
        $this->assertSame('finished', $rows[1]->status);
        $this->assertNotNull($rows[1]->time_in);
        $this->assertNotNull($rows[1]->time_out);

        Carbon::setTestNow();
    }
}
