<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = ['ICT Administrator', 'ICT Staff / Technician', 'Management (HOD)'];

    protected $fillable = ['name', 'email', 'password', 'role', 'department_id', 'is_active'];

    protected $hidden = ['password', 'remember_token', 'mfa_secret', 'recovery_codes', 'mfa_last_timestamp'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'is_active' => 'boolean', 'is_uat_account' => 'boolean'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function isOperator(): bool
    {
        return in_array($this->role, array_slice(self::ROLES, 0, 2), true);
    }

    public function requiresEmailOtp(): bool
    {
        return ! $this->hasUatOtpException() && in_array($this->role, config('auth.email_otp_roles'), true);
    }

    public function hasUatOtpException(): bool
    {
        return $this->is_uat_account === true
            && (config('uat.enabled') === true
                || (is_string(config('uat.otp_bypass_email'))
                    && config('uat.otp_bypass_email') !== ''
                    && $this->email === config('uat.otp_bypass_email')));
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLES[0];
    }
}
