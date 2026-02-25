<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtrRow extends Model
{
    public function dtrMonth()
    {
        return $this->belongsTo(DtrMonth::class, 'dtr_month_id');
    }
}
