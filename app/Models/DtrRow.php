<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtrRow extends Model
{
    protected $fillable = [
        'dtr_month_id',
        'date',
        'day',
        'time_in',
        'time_out',
        'total_minutes',
        'break_minutes',
        'status',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function dtrMonth()
    {
        return $this->belongsTo(DtrMonth::class, 'dtr_month_id');
    }

    /**
     * Calculate total hours from time_in and time_out
     */
    public function calculateTotalHours()
    {
        if (!$this->time_in || !$this->time_out) {
            return 0;
        }

        $timeIn = \DateTime::createFromFormat('H:i:s', $this->time_in);
        $timeOut = \DateTime::createFromFormat('H:i:s', $this->time_out);

        if (!$timeIn || !$timeOut) {
            return 0;
        }

        $diff = $timeOut->diff($timeIn);
        $minutes = $diff->h * 60 + $diff->i;

        return max(0, $minutes);
    }
}
