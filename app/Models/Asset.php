<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    public const STATUSES = ['Available', 'Allocated', 'Under Maintenance', 'Damaged', 'Disposed'];

    public const CONDITIONS = ['Good', 'Fair', 'Poor'];

    public function getYearPurchasedAttribute(): ?string
    {
        return $this->purchase_date ? substr($this->purchase_date, 0, 4) : null;
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['purchase_price' => 'decimal:2'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations()
    {
        return $this->hasMany(Allocation::class);
    }

    public function currentAllocation()
    {
        return $this->hasOne(Allocation::class)->where('status', 'Active');
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function scopeVisibleTo($query, User $user)
    {
        return in_array($user->role, User::ROLES, true) ? $query : $query->whereRaw('1=0');
    }
}
