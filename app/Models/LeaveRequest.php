<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $fillable = [
        'user_id',
        'dtr_row_id',
        'leave_date',
        'request_type',
        'requested_days',
        'is_paid',
        'approved_paid_days',
        'approved_unpaid_days',
        'deducted_days',
        'balance_before',
        'balance_after',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'decision_note',
    ];

    protected $casts = [
        'leave_date' => 'date',
        'is_paid' => 'boolean',
        'requested_days' => 'decimal:2',
        'approved_paid_days' => 'decimal:2',
        'approved_unpaid_days' => 'decimal:2',
        'deducted_days' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dtrRow()
    {
        return $this->belongsTo(DtrRow::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
