<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    protected $guarded = ['id'];

    public function timelineDisplay(string $field): string
    {
        if (! $this->$field) {
            return '—';
        }
        $date = Carbon::parse($this->$field);
        $precise = match ($field) {
            'date_reported' => $this->reported_time_recorded,
            'actual_completion_date' => $this->completion_time_recorded,
            default => true,
        };

        return $date->format($precise ? 'd/m/Y H:i' : 'd/m/Y');
    }

    public function timelineInput(string $field): ?string
    {
        if (! $this->$field) {
            return null;
        }
        $precise = match ($field) {
            'date_reported' => $this->reported_time_recorded, 'actual_completion_date' => $this->completion_time_recorded, default => true
        };

        return Carbon::parse($this->$field)->format($precise ? 'Y-m-d\TH:i' : 'Y-m-d');
    }

    private function duration(?string $start, ?string $end): ?string
    {
        if (! $start || ! $end) {
            return null;
        }
        $seconds = Carbon::parse($start)->diffInSeconds(Carbon::parse($end), false);
        if ($seconds < 0) {
            return null;
        }
        $minutes = (int) floor($seconds / 60);
        if ($minutes === 0) {
            return 'Less than 1 minute';
        }
        $parts = [];
        foreach ([1440 => 'day', 60 => 'hour', 1 => 'minute'] as $unit => $label) {
            $count = intdiv($minutes, $unit);
            if ($count) {
                $parts[] = $count.' '.$label.($count === 1 ? '' : 's');
            }
            $minutes %= $unit;
        }

        return implode(' ', $parts);
    }

    public function getResponseTimeAttribute(): ?string
    {
        return $this->reported_time_recorded ? $this->duration($this->date_reported, $this->problem_attended_at) : null;
    }

    public function getRepairDurationAttribute(): ?string
    {
        return $this->completion_time_recorded ? $this->duration($this->repair_started_at, $this->actual_completion_date) : null;
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
