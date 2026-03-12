<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LeaveBalanceService;
use App\Services\PayrollLockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminLeaveController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $leaveRequestId = (int) $request->query('leave_request_id', 0);
        $query = LeaveRequest::query()
            ->with([
                'user:id,name,email,employee_type,initial_paid_leave_days,current_paid_leave_balance,leave_reset_month,leave_reset_day,last_leave_refresh_year',
                'reviewer:id,name',
                'dtrRow:id,date,day,status,time_in,time_out',
            ])
            ->orderByDesc('leave_date')
            ->orderByDesc('created_at');

        if ($leaveRequestId > 0) {
            $query->whereKey($leaveRequestId);
        } elseif (in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $leaveRequests = $query->get()->map(function (LeaveRequest $leaveRequest) {
            return [
                'id' => $leaveRequest->id,
                'employee_id' => $leaveRequest->user_id,
                'employee_name' => $leaveRequest->user?->name,
                'employee_email' => $leaveRequest->user?->email,
                'employee_type' => $leaveRequest->user?->employee_type,
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
                'dtr_row_id' => $leaveRequest->dtr_row_id,
                'dtr_row_status' => $leaveRequest->dtrRow?->status,
                'reviewed_by' => $leaveRequest->reviewer?->name,
                'reviewed_at' => optional($leaveRequest->reviewed_at)?->toDateTimeString(),
                'decision_note' => $leaveRequest->decision_note,
                'created_at' => optional($leaveRequest->created_at)?->toDateTimeString(),
                'employee_paid_leave_balance' => $leaveRequest->user?->current_paid_leave_balance !== null
                    ? (float) $leaveRequest->user?->current_paid_leave_balance
                    : null,
                'employee_initial_paid_leave_days' => $leaveRequest->user?->initial_paid_leave_days !== null
                    ? (float) $leaveRequest->user?->initial_paid_leave_days
                    : null,
            ];
        });

        return Inertia::render('Admin/Leaves/Index', [
            'leave_requests' => $leaveRequests,
        ]);
    }

    public function decide(
        Request $request,
        LeaveRequest $leaveRequest,
        PayrollLockService $payrollLockService,
        LeaveBalanceService $leaveBalanceService,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'decision_note' => 'nullable|string|max:1000',
        ]);
        if ($leaveRequest->status !== 'pending') {
            return redirect()->route('admin.leaves.index')->withErrors([
                'status' => 'Only pending leave requests can be decided.',
            ]);
        }

        $before = $leaveRequest->only([
            'status',
            'is_paid',
            'requested_days',
            'approved_paid_days',
            'approved_unpaid_days',
            'deducted_days',
            'balance_before',
            'balance_after',
            'reviewed_by',
            'reviewed_at',
            'decision_note',
        ]);

        $leaveRequest->loadMissing('dtrRow', 'user');
        $status = $validated['status'];
        $decisionNote = $validated['decision_note'] ?? null;
        if ($status === 'approved') {
            if ($leaveRequest->user) {
                $leaveBalanceService->refreshAnnualBalanceForUser($leaveRequest->user, null, $request->user());
            }
            $leaveRequest = $leaveBalanceService->applyApprovedLeave($leaveRequest, $request->user());
        } else {
            $leaveRequest = $leaveBalanceService->markLeaveRejected($leaveRequest, $request->user());
        }
        $leaveRequest->status = $status;
        $leaveRequest->decision_note = $decisionNote;
        $leaveRequest->save();

        if ($status === 'approved' && $leaveRequest->dtrRow) {
            $row = $leaveRequest->dtrRow;
            $approvedRowStatus = $leaveRequest->request_type === 'intern_absence'
                ? 'missed'
                : 'leave';
            $rowBefore = $row->only([
                'time_in',
                'time_out',
                'break_minutes',
                'late_minutes',
                'total_minutes',
                'status',
            ]);
            $row->update([
                'status' => $approvedRowStatus,
                'time_in' => null,
                'time_out' => null,
                'total_minutes' => 0,
                'late_minutes' => 0,
                'break_minutes' => 0,
                'on_break' => false,
                'break_started_at' => null,
                'break_target_minutes' => null,
            ]);

            if ($leaveRequest->user && $payrollLockService->isDateFinalized($leaveRequest->user->id, $row->date)) {
                $payrollLockService->resetFinalizedRecordsForDate(
                    $leaveRequest->user->id,
                    $row->date,
                    'Attendance adjusted by approved leave request.'
                );
            }
            $auditLogger->log(
                $request->user(),
                'attendance.leave_applied',
                'dtr_row',
                $row->id,
                $rowBefore,
                $row->fresh()->only(array_keys($rowBefore)),
                'Approved leave request applied to attendance row.',
                $request
            );
        }

        $auditLogger->log(
            $request->user(),
            'leave_request.'.$status,
            'leave_request',
            $leaveRequest->id,
            $before,
            $leaveRequest->fresh()->only(array_keys($before)),
            $decisionNote,
            $request
        );

        return redirect()
            ->route('admin.leaves.index')
            ->with('success', ucfirst($leaveRequest->request_type === 'intern_absence' ? 'absence request' : 'leave request').' '.$status.'.');
    }

    public function adjustBalance(Request $request, User $employee, AuditLogger $auditLogger): RedirectResponse
    {
        abort_if($employee->isAdmin(), 404);
        if (! $employee->isPaidLeaveEligible()) {
            return redirect()->route('admin.leaves.index')->withErrors([
                'leave_balance' => 'Leave balance adjustments are available only for regular employees.',
            ]);
        }

        $validated = $request->validate([
            'new_balance' => 'required|numeric|min:0',
            'reason' => 'required|string|max:1000',
        ]);

        $before = [
            'initial_paid_leave_days' => (float) ($employee->initial_paid_leave_days ?? 0),
            'current_paid_leave_balance' => (float) ($employee->current_paid_leave_balance ?? 0),
        ];

        $employee->current_paid_leave_balance = round((float) $validated['new_balance'], 2);
        $employee->save();

        $auditLogger->log(
            $request->user(),
            'leave_balance.adjusted',
            'user',
            $employee->id,
            $before,
            [
                'initial_paid_leave_days' => (float) ($employee->initial_paid_leave_days ?? 0),
                'current_paid_leave_balance' => (float) ($employee->current_paid_leave_balance ?? 0),
            ],
            $validated['reason'],
            $request
        );

        return redirect()->route('admin.leaves.index')->with('success', 'Leave balance adjusted successfully.');
    }
}
