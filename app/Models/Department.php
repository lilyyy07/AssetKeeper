<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Department extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function selectionRule(?int $currentId = null)
    {
        return Rule::exists('departments', 'id')->where(fn ($query) => $query->where(fn ($q) => $q->where('is_active', true)->when($currentId, fn ($q) => $q->orWhere('id', $currentId))));
    }

    public static function options($departments, ?int $currentId = null): array
    {
        return $departments->filter(fn ($department) => $department->is_active || $department->id === $currentId)
            ->mapWithKeys(fn ($department) => [$department->id => $department->name.($department->is_active ? '' : ' (Inactive)')])->all();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}
