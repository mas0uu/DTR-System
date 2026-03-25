<?php

namespace App\Http\Controllers\Auth;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'credential' => ['required', 'string', 'max:255'],
            'request_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $credential = trim((string) $validated['credential']);
        $statusMessage = 'If an account exists, your password reset request has been submitted to an administrator.';
        $user = $this->resolveUserFromCredential($credential);

        if (! $user) {
            return back()->with('status', $statusMessage);
        }

        $pendingRequest = PasswordResetRequest::query()
            ->where('user_id', $user->id)
            ->where('status', PasswordResetRequest::STATUS_PENDING)
            ->latest('id')
            ->first();

        $payload = [
            'credential_snapshot' => $credential,
            'request_note' => isset($validated['request_note']) && trim((string) $validated['request_note']) !== ''
                ? trim((string) $validated['request_note'])
                : null,
            'status' => PasswordResetRequest::STATUS_PENDING,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'decision_note' => null,
        ];

        if ($pendingRequest) {
            $pendingRequest->update($payload);
        } else {
            PasswordResetRequest::query()->create($payload + [
                'user_id' => $user->id,
            ]);
        }

        return back()->with('status', $statusMessage);
    }

    private function resolveUserFromCredential(string $credential): ?User
    {
        if ($credential === '') {
            return null;
        }

        if (filter_var($credential, FILTER_VALIDATE_EMAIL)) {
            return User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($credential)])
                ->first();
        }

        return User::query()
            ->whereRaw('LOWER(student_no) = ?', [mb_strtolower($credential)])
            ->first();
    }
}
