<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit history is immutable.'));
        static::deleting(fn () => throw new \LogicException('Audit history is immutable.'));
    }
}
