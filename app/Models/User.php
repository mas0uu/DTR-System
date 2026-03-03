<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'student_name',
        'student_no',
        'school',
        'required_hours',
        'company',
        'department',
        'supervisor_name',
        'supervisor_position',
        'employee_type',
        'starting_date',
        'working_days',
        'work_time_in',
        'work_time_out',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'starting_date' => 'date',
            'working_days' => 'array',
        ];
    }

    /**
     * Get the user's DTR months.
     */
    public function dtrMonths()
    {
        return $this->hasMany(DtrMonth::class);
    }
}
