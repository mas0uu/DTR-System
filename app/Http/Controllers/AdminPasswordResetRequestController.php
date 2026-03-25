<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminPasswordResetRequestController extends Controller
{
    public function index(): Response
    {
        $requests = PasswordResetRequest::query()
            ->with([
                'user:id,name,email,student_no,role,employee_type',
                'reviewer:id,name,email',
            ])
            ->orderByRaw("
                CASE
                    WHEN status = 'pending' THEN 0
                    WHEN status = 'approved' THEN 1
                    ELSE 2
                END
            ")
            ->orderByDesc('created_at')
            ->limit(300)
            ->get()
            ->map(function (PasswordResetRequest $resetRequest) {
                return [
                    'id' => $resetRequest->id,
                    'status' => $resetRequest->status,
                    'credential_snapshot' => $resetRequest->credential_snapshot,
                    'request_note' => $resetRequest->request_note,
                    'decision_note' => $resetRequest->decision_note,
                    'created_at' => optional($resetRequest->created_at)?->toDateTimeString(),
                    'reviewed_at' => optional($resetRequest->reviewed_at)?->toDateTimeString(),
                    'user' => [
                        'id' => $resetRequest->user?->id,
                        'name' => $resetRequest->user?->name,
                        'email' => $resetRequest->user?->email,
                        'student_no' => $resetRequest->user?->student_no,
                        'role' => $resetRequest->user?->role,
                        'employee_type' => $resetRequest->user?->employee_type,
                    ],
                    'reviewer' => [
                        'id' => $resetRequest->reviewer?->id,
                        'name' => $resetRequest->reviewer?->name,
                        'email' => $resetRequest->reviewer?->email,
                    ],
                ];
            });

        return Inertia::render('Admin/Security/PasswordResetRequests', [
            'requests' => $requests,
        ]);
    }

    public function approve(Request $request, PasswordResetRequest $passwordResetRequest, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $decisionNote = isset($validated['decision_note']) && trim((string) $validated['decision_note']) !== ''
            ? trim((string) $validated['decision_note'])
            : 'Password reset request approved by administrator.';

        if ($passwordResetRequest->status !== PasswordResetRequest::STATUS_PENDING) {
            return back()->withErrors([
                'request' => 'This request has already been reviewed.',
            ]);
        }

        $temporaryPassword = $this->generateTemporaryPassword();
        $admin = $request->user();

        $user = DB::transaction(function () use ($passwordResetRequest, $admin, $decisionNote, $temporaryPassword, $auditLogger, $request) {
            /** @var User|null $targetUser */
            $targetUser = User::query()
                ->whereKey($passwordResetRequest->user_id)
                ->lockForUpdate()
                ->first();

            if (! $targetUser) {
                abort(404, 'User account not found.');
            }

            $targetUser->forceFill([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ])->save();

            $passwordResetRequest->update([
                'status' => PasswordResetRequest::STATUS_APPROVED,
                'reviewed_by' => $admin?->id,
                'reviewed_at' => now(),
                'decision_note' => $decisionNote,
            ]);

            $auditLogger->log(
                $admin,
                'password_reset_request.approved',
                'password_reset_request',
                $passwordResetRequest->id,
                [
                    'status' => PasswordResetRequest::STATUS_PENDING,
                ],
                [
                    'status' => PasswordResetRequest::STATUS_APPROVED,
                    'user_id' => $targetUser->id,
                ],
                $decisionNote,
                $request
            );

            return $targetUser;
        });

        return redirect()
            ->route('admin.password_reset_requests.index')
            ->with(
                'success',
                "Password reset approved for {$user->email}. Temporary password: {$temporaryPassword}. Ask the user to sign in and change it immediately."
            );
    }

    public function reject(Request $request, PasswordResetRequest $passwordResetRequest, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'decision_note' => ['required', 'string', 'max:1000'],
        ]);
        $decisionNote = trim((string) $validated['decision_note']);

        if ($passwordResetRequest->status !== PasswordResetRequest::STATUS_PENDING) {
            return back()->withErrors([
                'request' => 'This request has already been reviewed.',
            ]);
        }

        $passwordResetRequest->update([
            'status' => PasswordResetRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'decision_note' => $decisionNote,
        ]);

        $auditLogger->log(
            $request->user(),
            'password_reset_request.rejected',
            'password_reset_request',
            $passwordResetRequest->id,
            [
                'status' => PasswordResetRequest::STATUS_PENDING,
            ],
            [
                'status' => PasswordResetRequest::STATUS_REJECTED,
            ],
            $decisionNote,
            $request
        );

        return redirect()
            ->route('admin.password_reset_requests.index')
            ->with('success', 'Password reset request rejected.');
    }

    private function generateTemporaryPassword(): string
    {
        return Str::upper(Str::random(4)).'-'.random_int(1000, 9999);
    }
}

