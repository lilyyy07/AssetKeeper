<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AssetCategory extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function selectionRule(?string $current = null)
    {
        return Rule::exists('asset_categories', 'name')->where(fn ($q) => $q->where(fn ($q) => $q->where('is_active', true)->when($current, fn ($q) => $q->orWhere('name', $current))));
    }

    public static function names(?string $current = null): array
    {
        return static::where(fn ($q) => $q->where('is_active', true)->when($current, fn ($q) => $q->orWhere('name', $current)))->orderBy('name')->pluck('name')->all();
    }
}
