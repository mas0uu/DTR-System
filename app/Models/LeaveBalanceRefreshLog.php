<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalanceRefreshLog extends Model
{
    protected $fillable = [
        'user_id',
        'refresh_year',
        'balance_before',
        'allocation_added',
        'balance_after',
        'refreshed_by',
        'source',
        'reason',
    ];

    protected $casts = [
        'refresh_year' => 'integer',
        'balance_before' => 'decimal:2',
        'allocation_added' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function refresher()
    {
        return $this->belongsTo(User::class, 'refreshed_by');
    }
}

