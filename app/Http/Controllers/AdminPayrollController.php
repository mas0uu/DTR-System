<?php

namespace App\Http\Controllers;

use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPayrollController extends Controller
{
    public function index(): Response
    {
        $employees = User::query()
            ->where('is_admin', false)
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhere('role', '!=', User::ROLE_ADMIN);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'employee_type', 'intern_compensation_enabled', 'salary_type', 'salary_amount'])
            ->map(function (User $employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'employee_type' => $employee->employee_type,
                    'intern_compensation_enabled' => (bool) $employee->intern_compensation_enabled,
                    'salary_type' => $employee->salary_type,
                    'salary_amount' => $employee->salary_amount !== null ? (float) $employee->salary_amount : null,
                ];
            });

        $records = PayrollRecord::query()
            ->with([
                'user:id,name,email',
                'reviewer:id,name',
                'finalizer:id,name',
            ])
            ->orderByDesc('pay_period_end')
            ->orderByDesc('pay_period_start')
            ->limit(100)
            ->get()
            ->map(function (PayrollRecord $record) {
                return [
                    'id' => $record->id,
                    'employee_id' => $record->user_id,
                    'employee_name' => $record->user?->name ?? 'Unknown',
                    'employee_email' => $record->user?->email ?? '-',
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
                    'reviewed_by' => $record->reviewer?->name,
                    'reviewed_at' => optional($record->reviewed_at)?->toDateTimeString(),
                    'finalized_by' => $record->finalizer?->name,
                    'finalized_at' => optional($record->finalized_at)?->toDateTimeString(),
                    'lock_reason' => $record->lock_reason,
                    'correction_count' => (int) $record->correction_count,
                    'payslip_available' => ! empty($record->payslip_path),
                ];
            });

        return Inertia::render('Admin/Payroll/Index', [
            'employees' => $employees,
            'payroll_records' => $records,
        ]);
    }

    public function generate(Request $request, PayrollCalculator $calculator, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:users,id',
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after_or_equal:pay_period_start',
        ]);

        /** @var User $employee */
        $employee = User::query()
            ->where('is_admin', false)
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhere('role', '!=', User::ROLE_ADMIN);
            })
            ->findOrFail($validated['employee_id']);
        if ($employee->employee_type === 'intern' && ! $employee->intern_compensation_enabled) {
            return redirect()
                ->route('admin.payroll.index')
                ->withErrors([
                    'payroll' => 'Payroll is disabled for this intern account.',
                ]);
        }

        if (! in_array($employee->salary_type, ['monthly', 'daily', 'hourly'], true)) {
            return redirect()
                ->route('admin.payroll.index')
                ->withErrors([
                    'payroll' => 'Payroll not generated: employee salary type is missing.',
                ]);
        }

        if ($employee->salary_amount === null || (float) $employee->salary_amount <= 0) {
            return redirect()
                ->route('admin.payroll.index')
                ->withErrors([
                    'payroll' => 'Payroll not generated: employee salary amount is missing.',
                ]);
        }

        $timezone = 'Asia/Manila';
        $periodStart = Carbon::parse($validated['pay_period_start'], $timezone)->startOfDay();
        $periodEnd = Carbon::parse($validated['pay_period_end'], $timezone)->startOfDay();
        if (! $periodStart->isSameMonth($periodEnd)) {
            return redirect()
                ->route('admin.payroll.index')
                ->withErrors([
                    'payroll' => 'Payroll period must be within a single calendar month.',
                ]);
        }
        $existing = PayrollRecord::query()
            ->where('user_id', $employee->id)
            ->whereDate('pay_period_start', $periodStart->toDateString())
            ->whereDate('pay_period_end', $periodEnd->toDateString())
            ->first();
        if ($existing && in_array($existing->status, ['reviewed', 'finalized'], true)) {
            return redirect()
                ->route('admin.payroll.index')
                ->withErrors([
                    'payroll' => 'This payroll period is '.$existing->status.' and cannot be regenerated directly.',
                ]);
        }
        $overlappingRecord = $this->findOverlappingRecord($employee->id, $periodStart, $periodEnd);
        if ($overlappingRecord) {
            return redirect()
                ->route('admin.payroll.index')
                ->withErrors([
                    'payroll' => sprintf(
                        'This payroll period overlaps with existing period %s to %s (status: %s).',
                        $overlappingRecord->pay_period_start->format('Y-m-d'),
                        $overlappingRecord->pay_period_end->format('Y-m-d'),
                        $overlappingRecord->status
                    ),
                ]);
        }

        $computed = $calculator->calculate($employee, $periodStart, $periodEnd);
        $snapshot = [
            'generated_by' => $request->user()->id,
            'generated_at' => now()->toIso8601String(),
            'employee' => [
                'id' => $employee->id,
                'salary_type' => $employee->salary_type,
                'salary_amount' => (float) $employee->salary_amount,
                'work_time_in' => $employee->work_time_in,
                'work_time_out' => $employee->work_time_out,
                'working_days' => $employee->working_days,
            ],
        ];
        $before = $existing?->toArray();

        $record = PayrollRecord::updateOrCreate(
            [
                'user_id' => $employee->id,
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
                'source' => 'admin',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'finalized_by' => null,
                'finalized_at' => null,
                'lock_reason' => null,
                'input_snapshot' => $snapshot,
            ]
        );
        $auditLogger->log(
            $request->user(),
            $existing ? 'payroll.regenerated' : 'payroll.generated',
            'payroll_record',
            $record->id,
            $before,
            $record->fresh()->toArray(),
            'Generated payroll from attendance records.',
            $request
        );

        return redirect()
            ->route('admin.payroll.index')
            ->with('success', 'Payroll generated successfully.');
    }

    public function generateAll(Request $request, PayrollCalculator $calculator, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'pay_period_start' => 'required|date',
            'pay_period_end' => 'required|date|after_or_equal:pay_period_start',
        ]);

        $timezone = 'Asia/Manila';
        $periodStart = Carbon::parse($validated['pay_period_start'], $timezone)->startOfDay();
        $periodEnd = Carbon::parse($validated['pay_period_end'], $timezone)->startOfDay();
        if (! $periodStart->isSameMonth($periodEnd)) {
            return redirect()
                ->route('admin.payroll.index')
                ->withErrors([
                    'payroll' => 'Payroll period must be within a single calendar month.',
                ]);
        }
        $actor = $request->user();

        $employees = User::query()
            ->where('is_admin', false)
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhere('role', '!=', User::ROLE_ADMIN);
            })
            ->orderBy('name')
            ->get();

        $generatedCount = 0;
        $regeneratedCount = 0;
        $skipped = [];

        foreach ($employees as $employee) {
            if ($employee->employee_type === 'intern' && ! $employee->intern_compensation_enabled) {
                $skipped[] = "{$employee->name} (payroll disabled for intern account)";
                continue;
            }

            if (! in_array($employee->salary_type, ['monthly', 'daily', 'hourly'], true)) {
                $skipped[] = "{$employee->name} (salary type missing)";
                continue;
            }

            if ($employee->salary_amount === null || (float) $employee->salary_amount <= 0) {
                $skipped[] = "{$employee->name} (salary amount missing)";
                continue;
            }

            $existing = PayrollRecord::query()
                ->where('user_id', $employee->id)
                ->whereDate('pay_period_start', $periodStart->toDateString())
                ->whereDate('pay_period_end', $periodEnd->toDateString())
                ->first();

            if ($existing && in_array($existing->status, ['reviewed', 'finalized'], true)) {
                $skipped[] = "{$employee->name} ({$existing->status} and locked)";
                continue;
            }
            $overlappingRecord = $this->findOverlappingRecord($employee->id, $periodStart, $periodEnd);
            if ($overlappingRecord) {
                $skipped[] = sprintf(
                    '%s (overlaps %s to %s)',
                    $employee->name,
                    $overlappingRecord->pay_period_start->format('Y-m-d'),
                    $overlappingRecord->pay_period_end->format('Y-m-d')
                );
                continue;
            }

            $computed = $calculator->calculate($employee, $periodStart, $periodEnd);
            $snapshot = [
                'generated_by' => $actor->id,
                'generated_at' => now()->toIso8601String(),
                'employee' => [
                    'id' => $employee->id,
                    'salary_type' => $employee->salary_type,
                    'salary_amount' => (float) $employee->salary_amount,
                    'work_time_in' => $employee->work_time_in,
                    'work_time_out' => $employee->work_time_out,
                    'working_days' => $employee->working_days,
                ],
            ];
            $before = $existing?->toArray();

            $record = PayrollRecord::updateOrCreate(
                [
                    'user_id' => $employee->id,
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
                    'source' => 'admin',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'finalized_by' => null,
                    'finalized_at' => null,
                    'lock_reason' => null,
                    'input_snapshot' => $snapshot,
                ]
            );

            $auditLogger->log(
                $actor,
                $existing ? 'payroll.regenerated' : 'payroll.generated',
                'payroll_record',
                $record->id,
                $before,
                $record->fresh()->toArray(),
                'Generated payroll from bulk payroll run.',
                $request
            );

            if ($existing) {
                $regeneratedCount++;
            } else {
                $generatedCount++;
            }
        }

        $processedCount = $generatedCount + $regeneratedCount;
        $skippedCount = count($skipped);
        $summary = "Bulk payroll ({$periodStart->toDateString()} to {$periodEnd->toDateString()}): "
            ."{$processedCount} processed ({$generatedCount} generated, {$regeneratedCount} regenerated), "
            ."{$skippedCount} skipped.";

        if ($skippedCount > 0) {
            $preview = implode('; ', array_slice($skipped, 0, 3));
            $summary .= " Skipped: {$preview}".($skippedCount > 3 ? '; ...' : '');
        }

        return redirect()
            ->route('admin.payroll.index')
            ->with('success', $summary);
    }

    public function review(Request $request, PayrollRecord $payrollRecord, AuditLogger $auditLogger): RedirectResponse
    {
        if ($payrollRecord->status === 'finalized') {
            return redirect()->route('admin.payroll.index')->withErrors([
                'payroll' => 'Finalized payroll cannot be moved back to reviewed.',
            ]);
        }

        $before = $payrollRecord->only(['status', 'reviewed_by', 'reviewed_at']);
        $payrollRecord->update([
            'status' => 'reviewed',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        $auditLogger->log(
            $request->user(),
            'payroll.reviewed',
            'payroll_record',
            $payrollRecord->id,
            $before,
            $payrollRecord->only(array_keys($before)),
            'Payroll reviewed.',
            $request
        );

        return redirect()->route('admin.payroll.index')->with('success', 'Payroll marked as reviewed.');
    }

    public function finalize(Request $request, PayrollRecord $payrollRecord, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'lock_reason' => 'required|string|max:1000',
        ]);

        if ($payrollRecord->status === 'finalized') {
            return redirect()->route('admin.payroll.index')->with('success', 'Payroll is already finalized.');
        }

        $before = $payrollRecord->only([
            'status',
            'reviewed_by',
            'reviewed_at',
            'finalized_by',
            'finalized_at',
            'lock_reason',
        ]);
        $payrollRecord->update([
            'status' => 'finalized',
            'reviewed_by' => $payrollRecord->reviewed_by ?: $request->user()->id,
            'reviewed_at' => $payrollRecord->reviewed_at ?: now(),
            'finalized_by' => $request->user()->id,
            'finalized_at' => now(),
            'lock_reason' => $validated['lock_reason'],
        ]);
        $auditLogger->log(
            $request->user(),
            'payroll.finalized',
            'payroll_record',
            $payrollRecord->id,
            $before,
            $payrollRecord->only(array_keys($before)),
            $validated['lock_reason'],
            $request
        );

        return redirect()->route('admin.payroll.index')->with('success', 'Payroll finalized and locked.');
    }

    public function destroy(Request $request, PayrollRecord $payrollRecord, AuditLogger $auditLogger): RedirectResponse
    {
        if ($payrollRecord->status === 'finalized') {
            return redirect()->route('admin.payroll.index')->withErrors([
                'payroll' => 'Finalized payroll cannot be deleted.',
            ]);
        }

        $before = $payrollRecord->only([
            'user_id',
            'pay_period_start',
            'pay_period_end',
            'status',
            'salary_type',
            'salary_amount',
            'base_pay',
            'paid_leave_pay',
            'paid_holiday_base_pay',
            'holiday_attendance_bonus',
            'total_deductions',
            'net_pay',
            'total_salary',
            'reviewed_by',
            'reviewed_at',
            'finalized_by',
            'finalized_at',
            'lock_reason',
            'payslip_path',
            'payslip_original_name',
        ]);
        $payrollRecordId = $payrollRecord->id;

        if ($payrollRecord->payslip_path) {
            $disk = config('filesystems.default', 'local');
            if (Storage::disk($disk)->exists($payrollRecord->payslip_path)) {
                Storage::disk($disk)->delete($payrollRecord->payslip_path);
            }
        }

        $payrollRecord->delete();

        $auditLogger->log(
            $request->user(),
            'payroll.deleted',
            'payroll_record',
            $payrollRecordId,
            $before,
            null,
            'Deleted non-finalized payroll record.',
            $request
        );

        return redirect()->route('admin.payroll.index')->with('success', 'Payroll record deleted.');
    }

    public function showPayslip(Request $request, PayrollRecord $payrollRecord): Response
    {
        if (! in_array($payrollRecord->status, ['generated', 'reviewed', 'finalized'], true)) {
            abort(403, 'Payroll view is not available for this record status.');
        }

        $payrollRecord->loadMissing('user:id,name,student_no,department,company,employee_type');
        $employee = $payrollRecord->user;
        if (! $employee) {
            abort(404, 'Employee record is not available for this payslip.');
        }

        return Inertia::render('Payslips/Show', [
            'role' => 'admin',
            'auto_print' => $request->boolean('print'),
            'payslip' => $this->buildPayslipPayload($payrollRecord, $employee),
        ]);
    }

    public function downloadPayslip(PayrollRecord $payrollRecord): StreamedResponse
    {
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
            'download_url' => $record->payslip_path ? route('admin.payroll.payslip.download', $record->id) : null,
            'is_read_only' => true,
            'finalized_at' => optional($record->finalized_at)?->toDateTimeString(),
        ];
    }

    private function findOverlappingRecord(int $userId, Carbon $periodStart, Carbon $periodEnd): ?PayrollRecord
    {
        return PayrollRecord::query()
            ->where('user_id', $userId)
            ->whereDate('pay_period_start', '<=', $periodEnd->toDateString())
            ->whereDate('pay_period_end', '>=', $periodStart->toDateString())
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->whereDate('pay_period_start', '!=', $periodStart->toDateString())
                    ->orWhereDate('pay_period_end', '!=', $periodEnd->toDateString());
            })
            ->orderByDesc('pay_period_end')
            ->first();
    }
}
