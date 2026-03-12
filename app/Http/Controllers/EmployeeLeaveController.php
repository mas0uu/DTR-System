<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeLeaveController extends Controller
{
    public function index(Request $request, LeaveBalanceService $leaveBalanceService): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return redirect()->route('admin.leaves.index');
        }
        $leaveBalanceService->refreshAnnualBalanceForUser($user);
        $user->refresh();

        $leaveRequests = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->with('dtrRow:id,date,day,status,time_in,time_out')
            ->orderByDesc('leave_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (LeaveRequest $leaveRequest) {
                return [
                    'id' => $leaveRequest->id,
                    'leave_date' => optional($leaveRequest->leave_date)->format('Y-m-d'),
                    'request_type' => $leaveRequest->request_type,
                    'requested_days' => (float) $leaveRequest->requested_days,
                    'is_paid' => (bool) $leaveRequest->is_paid,
                    'approved_paid_days' => (float) $leaveRequest->approved_paid_days,
                    'approved_unpaid_days' => (float) $leaveRequest->approved_unpaid_days,
                    'deducted_days' => (float) $leaveRequest->deducted_days,
                    'balance_before' => $leaveRequest->balance_before !== null ? (float) $leaveRequest->balance_before : null,
                    'balance_after' => $leaveRequest->balance_after !== null ? (float) $leaveRequest->balance_after : null,
                    'reason' => $leaveRequest->reason,
                    'status' => $leaveRequest->status,
                    'decision_note' => $leaveRequest->decision_note,
                    'reviewed_at' => optional($leaveRequest->reviewed_at)?->toDateTimeString(),
                    'created_at' => optional($leaveRequest->created_at)?->toDateTimeString(),
                    'row_status' => $leaveRequest->dtrRow?->status,
                    'row_time_in' => $leaveRequest->dtrRow?->time_in,
                    'row_time_out' => $leaveRequest->dtrRow?->time_out,
                ];
            });

        return Inertia::render('Leaves/Index', [
            'leave_requests' => $leaveRequests,
            'is_intern' => $user->employee_type === 'intern',
            'leave_balance' => [
                'initial_paid_leave_days' => (float) ($user->initial_paid_leave_days ?? 0),
                'current_paid_leave_balance' => (float) ($user->current_paid_leave_balance ?? 0),
                'leave_reset_month' => (int) ($user->leave_reset_month ?? 1),
                'leave_reset_day' => (int) ($user->leave_reset_day ?? 1),
                'last_leave_refresh_year' => $user->last_leave_refresh_year !== null ? (int) $user->last_leave_refresh_year : null,
                'is_paid_leave_eligible' => $user->isPaidLeaveEligible(),
            ],
        ]);
    }
}
