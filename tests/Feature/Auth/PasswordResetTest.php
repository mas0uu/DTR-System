<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@doxsys.com',
        ]);

        $response = $this->post('/forgot-password', ['credential' => $user->email]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => $user->id,
            'status' => PasswordResetRequest::STATUS_PENDING,
            'credential_snapshot' => $user->email,
        ]);
    }

    public function test_reset_password_request_can_be_submitted_using_student_number(): void
    {
        $user = User::factory()->create();
        $user->update([
            'student_no' => 'INTERN100',
        ]);

        $response = $this->post('/forgot-password', [
            'credential' => 'INTERN100',
            'request_note' => 'Cannot access account',
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => $user->id,
            'credential_snapshot' => 'INTERN100',
            'request_note' => 'Cannot access account',
            'status' => PasswordResetRequest::STATUS_PENDING,
        ]);
    }

    public function test_reset_password_request_returns_generic_message_for_unknown_account(): void
    {
        $response = $this->post('/forgot-password', [
            'credential' => 'ghost@doxsys.com',
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseCount('password_reset_requests', 0);
    }

    public function test_admin_can_approve_reset_request_and_force_password_change(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        $resetRequest = PasswordResetRequest::query()->create([
            'user_id' => $user->id,
            'credential_snapshot' => $user->email,
            'status' => PasswordResetRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.password_reset_requests.approve', $resetRequest->id));

        $response
            ->assertRedirect(route('admin.password_reset_requests.index'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('password_reset_requests', [
            'id' => $resetRequest->id,
            'status' => PasswordResetRequest::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
        ]);
        $this->assertTrue((bool) $user->fresh()->must_change_password);
    }
}
