<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtrMonth extends Model
{
    protected $fillable = [
        'user_id',
        'month',
        'year',
        'is_fulfilled',
    ];

    protected $casts = [
        'is_fulfilled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function rows()
    {
        return $this->hasMany(DtrRow::class);
    }

    /**
     * Get total hours logged in this month
     */
    public function getTotalHoursAttribute()
    {
        return $this->rows()
            ->where('status', 'finished')
            ->sum('total_minutes') / 60;
    }

    /**
     * Get finished rows count
     */
    public function getFinishedRowsCountAttribute()
    {
        return $this->rows()->where('status', 'finished')->count();
    }
}
