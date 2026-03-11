<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRecord extends Model
{
    protected $fillable = [
        'user_id',
        'pay_period_start',
        'pay_period_end',
        'salary_type',
        'salary_amount',
        'days_worked',
        'hours_worked',
        'absences',
        'undertime_minutes',
        'half_days',
        'base_pay',
        'paid_leave_pay',
        'paid_holiday_base_pay',
        'holiday_attendance_bonus',
        'leave_deductions',
        'other_deductions',
        'total_deductions',
        'net_pay',
        'total_salary',
        'status',
        'source',
        'reviewed_by',
        'reviewed_at',
        'finalized_by',
        'finalized_at',
        'lock_reason',
        'input_snapshot',
        'correction_count',
        'payslip_path',
        'payslip_original_name',
    ];

    protected $casts = [
        'pay_period_start' => 'date',
        'pay_period_end' => 'date',
        'salary_amount' => 'decimal:2',
        'days_worked' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'base_pay' => 'decimal:2',
        'paid_leave_pay' => 'decimal:2',
        'paid_holiday_base_pay' => 'decimal:2',
        'holiday_attendance_bonus' => 'decimal:2',
        'leave_deductions' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'total_salary' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'finalized_at' => 'datetime',
        'input_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            $totalSalary = round((float) ($record->total_salary ?? 0), 2);
            $totalDeductions = round((float) ($record->total_deductions ?? 0), 2);

            $record->net_pay = round($totalSalary - $totalDeductions, 2);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
