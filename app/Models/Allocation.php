<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Allocation extends Model
{
    public const BORROWER_TYPES = ['Student', 'Academic Staff', 'Administrative Staff', 'ICT Staff', 'Department / Unit', 'Other'];

    protected $guarded = ['id'];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getDisplayStatusAttribute()
    {
        return $this->status === 'Active' && $this->expected_return_date && $this->expected_return_date < today()->toDateString() ? 'Overdue' : $this->status;
    }
}
