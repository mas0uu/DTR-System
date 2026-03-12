<?php

namespace App\Http\Controllers;

use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return redirect()->route('admin.payroll.index');
        }
        if (! $this->payrollAccessEnabled($user)) {
            return redirect()->route('dtr.index')->with('success', 'Payroll is disabled for your internship program.');
        }

        $records = PayrollRecord::query()
            ->where('user_id', $user->id)
            ->orderByDesc('pay_period_end')
            ->orderByDesc('pay_period_start')
            ->get()
            ->map(function (PayrollRecord $record) {
                return [
                    'id' => $record->id,
                    'pay_period_start' => $record->pay_period_start->format('Y-m-d'),
                    'pay_period_end' => $record->pay_period_end->format('Y-m-d'),
                    'salary_type' => $record->salary_type,
                    'salary_amount' => (float) $record->salary_amount,
                    'days_worked' => (float) $record->days_worked,
                    'hours_worked' => (float) $record->hours_worked,
                    'absences' => (int) $record->absences,
                    'undertime_minutes' => (int) $record->undertime_minutes,
                    'half_days' => (int) $record->half_days,
                    'base_pay' => (float) $record->base_pay,
                    'paid_leave_pay' => (float) $record->paid_leave_pay,
                    'paid_holiday_base_pay' => (float) $record->paid_holiday_base_pay,
                    'holiday_attendance_bonus' => (float) $record->holiday_attendance_bonus,
                    'leave_deductions' => (float) $record->leave_deductions,
                    'other_deductions' => (float) $record->other_deductions,
                    'total_deductions' => (float) $record->total_deductions,
                    'net_pay' => (float) $record->net_pay,
                    'total_salary' => (float) $record->total_salary,
                    'status' => $record->status,
                    'payslip_available' => ! empty($record->payslip_path),
                ];
            });

        return Inertia::render('Payroll/Index', [
            'salary_summary' => [
                'salary_type' => $user->salary_type,
                'salary_amount' => $user->salary_amount !== null ? (float) $user->salary_amount : null,
            ],
            'payroll_records' => $records,
        ]);
    }

    public function downloadPayslip(Request $request, PayrollRecord $payrollRecord): StreamedResponse
    {
        if (! $this->payrollAccessEnabled($request->user())) {
            abort(403, 'Payroll is disabled for your internship program.');
        }
        if ($payrollRecord->user_id !== $request->user()->id) {
            abort(403);
        }
        if ($payrollRecord->status !== 'finalized') {
            abort(403, 'Payslip is available only for finalized payroll records.');
        }
        if (! $payrollRecord->payslip_path) {
            abort(404, 'Payslip is not available for this payroll record.');
        }

        $disk = config('filesystems.default', 'local');
        if (! Storage::disk($disk)->exists($payrollRecord->payslip_path)) {
            abort(404, 'Payslip file not found.');
        }

        $downloadName = $payrollRecord->payslip_original_name ?: basename($payrollRecord->payslip_path);

        return Storage::disk($disk)->download($payrollRecord->payslip_path, $downloadName);
    }

    public function showPayslip(Request $request, PayrollRecord $payrollRecord): Response
    {
        $user = $request->user();
        if (! $this->payrollAccessEnabled($user)) {
            abort(403, 'Payroll is disabled for your internship program.');
        }
        if ($payrollRecord->user_id !== $user->id) {
            abort(403);
        }
        if ($payrollRecord->status !== 'finalized') {
            abort(403, 'Payslip is available only for finalized payroll records.');
        }

        return Inertia::render('Payslips/Show', [
            'role' => 'employee',
            'auto_print' => $request->boolean('print'),
            'payslip' => $this->buildPayslipPayload($payrollRecord, $user),
        ]);
    }

    public function generate(Request $request, PayrollCalculator $calculator, AuditLogger $auditLogger)
    {
        $validated = $request->validate([
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after_or_equal:pay_period_start',
        ]);

        $user = $request->user();
        if (! $this->payrollAccessEnabled($user)) {
            return response()->json([
                'error' => 'Payroll is disabled for your internship program.',
            ], 403);
        }

        if (! in_array($user->salary_type, ['monthly', 'daily', 'hourly'], true)) {
            return response()->json([
                'error' => 'Employee salary type is not configured.',
            ], 422);
        }

        if ($user->salary_amount === null || (float) $user->salary_amount <= 0) {
            return response()->json([
                'error' => 'Employee salary amount is not configured.',
            ], 422);
        }

        $timezone = 'Asia/Manila';
        $periodStart = Carbon::parse($validated['pay_period_start'], $timezone)->startOfDay();
        $periodEnd = Carbon::parse($validated['pay_period_end'], $timezone)->startOfDay();
        if (! $periodStart->isSameMonth($periodEnd)) {
            return response()->json([
                'error' => 'Payroll period must be within a single calendar month.',
            ], 422);
        }
        $existing = PayrollRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('pay_period_start', $periodStart->toDateString())
            ->whereDate('pay_period_end', $periodEnd->toDateString())
            ->first();
        if ($existing && in_array($existing->status, ['reviewed', 'finalized'], true)) {
            return response()->json([
                'error' => 'This payroll period is locked for review/finalization and cannot be regenerated.',
            ], 422);
        }

        $computed = $calculator->calculate($user, $periodStart, $periodEnd);
        $snapshot = [
            'generated_by' => $user->id,
            'generated_at' => now()->toIso8601String(),
            'employee' => [
                'id' => $user->id,
                'salary_type' => $user->salary_type,
                'salary_amount' => (float) $user->salary_amount,
                'work_time_in' => $user->work_time_in,
                'work_time_out' => $user->work_time_out,
                'working_days' => $user->working_days,
            ],
        ];
        $before = $existing?->toArray();

        $record = PayrollRecord::updateOrCreate(
            [
                'user_id' => $user->id,
                'pay_period_start' => $computed['pay_period_start'],
                'pay_period_end' => $computed['pay_period_end'],
            ],
            [
                'salary_type' => $computed['salary_type'],
                'salary_amount' => $computed['salary_amount'],
                'days_worked' => $computed['days_worked'],
                'hours_worked' => $computed['hours_worked'],
                'absences' => $computed['absences'],
                'undertime_minutes' => $computed['undertime_minutes'],
                'half_days' => $computed['half_days'],
                'base_pay' => $computed['base_pay'],
                'paid_leave_pay' => $computed['paid_leave_pay'],
                'paid_holiday_base_pay' => $computed['paid_holiday_base_pay'],
                'holiday_attendance_bonus' => $computed['holiday_attendance_bonus'],
                'leave_deductions' => $computed['leave_deductions'],
                'other_deductions' => $computed['other_deductions'],
                'total_deductions' => $computed['total_deductions'],
                'net_pay' => $computed['net_pay'],
                'total_salary' => $computed['total_salary'],
                'status' => 'generated',
                'source' => 'self',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'finalized_by' => null,
                'finalized_at' => null,
                'lock_reason' => null,
                'input_snapshot' => $snapshot,
            ]
        );
        $auditLogger->log(
            $user,
            $existing ? 'payroll.self_regenerated' : 'payroll.self_generated',
            'payroll_record',
            $record->id,
            $before,
            $record->fresh()->toArray(),
            'Employee generated own payroll draft.',
            $request
        );

        return response()->json([
            'message' => 'Payroll generated successfully.',
            'data' => $record->fresh(),
        ]);
    }

    private function payrollAccessEnabled($user): bool
    {
        if ($user->employee_type !== 'intern') {
            return true;
        }

        return (bool) $user->intern_compensation_enabled;
    }

    private function buildPayslipPayload(PayrollRecord $record, User $employee): array
    {
        $employeeId = $employee->student_no ?: (string) $employee->id;
        $designation = $employee->department ?: strtoupper((string) ($employee->employee_type ?: 'employee'));
        $payDate = optional($record->finalized_at)->toDateString() ?: optional($record->created_at)->toDateString();

        return [
            'id' => $record->id,
            'status' => $record->status,
            'pay_period_start' => optional($record->pay_period_start)->format('Y-m-d'),
            'pay_period_end' => optional($record->pay_period_end)->format('Y-m-d'),
            'pay_date' => $payDate,
            'employee' => [
                'name' => $employee->name,
                'employee_id' => $employeeId,
                'designation' => $designation,
                'company' => $employee->company ?: config('app.name', 'DTR System'),
            ],
            'payroll' => [
                'salary_type' => (string) $record->salary_type,
                'salary_amount' => (float) $record->salary_amount,
                'days_worked' => (float) $record->days_worked,
                'hours_worked' => (float) $record->hours_worked,
                'absences' => (int) $record->absences,
                'undertime_minutes' => (int) $record->undertime_minutes,
                'half_days' => (int) $record->half_days,
            ],
            'earnings' => [
                'basic_pay' => (float) $record->base_pay,
                'paid_leave_pay' => (float) $record->paid_leave_pay,
                'paid_holiday_base_pay' => (float) $record->paid_holiday_base_pay,
                'holiday_attendance_bonus' => (float) $record->holiday_attendance_bonus,
                'overtime_pay' => null,
                'total_earnings' => (float) $record->total_salary,
            ],
            'deductions' => [
                'leave_deductions' => (float) $record->leave_deductions,
                'other_deductions' => (float) $record->other_deductions,
                'total_deductions' => (float) $record->total_deductions,
            ],
            'summary' => [
                'net_pay' => (float) $record->net_pay,
            ],
            'download_url' => $record->payslip_path ? route('payroll.payslip', $record->id) : null,
            'is_read_only' => true,
            'finalized_at' => optional($record->finalized_at)?->toDateTimeString(),
        ];
    }
}
