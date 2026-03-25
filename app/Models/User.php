<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_EMPLOYEE = 'employee';
    public const ROLE_INTERN = 'intern';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'student_name',
        'student_no',
        'school',
        'required_hours',
        'company',
        'department',
        'supervisor_name',
        'supervisor_position',
        'employee_type',
        'intern_compensation_enabled',
        'starting_date',
        'working_days',
        'work_time_in',
        'work_time_out',
        'default_break_minutes',
        'salary_type',
        'salary_amount',
        'initial_paid_leave_days',
        'current_paid_leave_balance',
        'leave_reset_month',
        'leave_reset_day',
        'last_leave_refresh_year',
        'profile_photo_path',
        'role',
        'is_admin',
        'employment_status',
        'deactivated_at',
        'deactivated_by',
        'archived_at',
        'archived_by',
        'status_reason',
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
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url',
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
            'default_break_minutes' => 'integer',
            'salary_amount' => 'decimal:2',
            'initial_paid_leave_days' => 'decimal:2',
            'current_paid_leave_balance' => 'decimal:2',
            'leave_reset_month' => 'integer',
            'leave_reset_day' => 'integer',
            'last_leave_refresh_year' => 'integer',
            'is_admin' => 'boolean',
            'intern_compensation_enabled' => 'boolean',
            'must_change_password' => 'boolean',
            'deactivated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $role = $user->attributes['role'] ?? null;

            if (! in_array($role, [self::ROLE_ADMIN, self::ROLE_EMPLOYEE, self::ROLE_INTERN], true)) {
                $role = $user->is_admin
                    ? self::ROLE_ADMIN
                    : (($user->employee_type ?? null) === 'intern' ? self::ROLE_INTERN : self::ROLE_EMPLOYEE);
            }

            $user->attributes['role'] = $role;
            $user->attributes['is_admin'] = $role === self::ROLE_ADMIN;

            if ($role === self::ROLE_ADMIN) {
                $user->attributes['employee_type'] = null;
            } elseif ($role === self::ROLE_INTERN) {
                $user->attributes['employee_type'] = 'intern';
            } else {
                $user->attributes['employee_type'] = 'regular';
            }
        });
    }

    /**
     * Get the user's DTR months.
     */
    public function dtrMonths()
    {
        return $this->hasMany(DtrMonth::class);
    }

    public function payrollRecords()
    {
        return $this->hasMany(PayrollRecord::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function reviewedLeaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'reviewed_by');
    }

    public function createdHolidays()
    {
        return $this->hasMany(Holiday::class, 'created_by');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function leaveBalanceRefreshLogs()
    {
        return $this->hasMany(LeaveBalanceRefreshLog::class);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->employment_status === 'active';
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || (bool) $this->is_admin;
    }

    public function isPayrollEligible(): bool
    {
        if ($this->isAdmin()) {
            return false;
        }

        if ($this->role !== self::ROLE_INTERN) {
            return true;
        }

        return (bool) $this->intern_compensation_enabled;
    }

    public function isPaidLeaveEligible(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (! $this->profile_photo_path) {
            return null;
        }

        return '/storage/'.$this->profile_photo_path;
    }
}
