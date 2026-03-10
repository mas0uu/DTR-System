<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = [
        'name',
        'date_start',
        'date_end',
        'holiday_type',
        'is_paid',
        'has_attendance_bonus',
        'attendance_bonus_type',
        'attendance_bonus_value',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'date_start' => 'date',
        'date_end' => 'date',
        'is_paid' => 'boolean',
        'has_attendance_bonus' => 'boolean',
        'attendance_bonus_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
