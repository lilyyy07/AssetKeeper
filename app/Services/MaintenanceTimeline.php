<?php

namespace App\Services;

use App\Models\MaintenanceRecord;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class MaintenanceTimeline
{
    public static function prepare(array $data, ?MaintenanceRecord $record): array
    {
        $previous = null;
        $previousLabel = null;
        $previousPrecise = false;
        foreach (['date_reported' => 'Problem Reported', 'problem_attended_at' => 'Problem Attended', 'repair_started_at' => 'Repair Started', 'actual_completion_date' => 'Completed'] as $field => $label) {
            $value = array_key_exists($field, $data) ? $data[$field] : $record?->$field;
            if (! $value) {
                continue;
            }
            $precise = (bool) preg_match('/[T ]\d{2}:\d{2}/', $value);
            $flag = match ($field) {
                'date_reported' => 'reported_time_recorded', 'actual_completion_date' => 'completion_time_recorded', default => null
            };
            if ($flag && $record && $record->$field && Carbon::parse($value)->equalTo(Carbon::parse($record->$field))) {
                $precise = (bool) $record->$flag;
            }
            $date = Carbon::parse($value);
            if ($previous && ($precise && $previousPrecise ? $date->lt($previous) : $date->copy()->startOfDay()->lt($previous->copy()->startOfDay()))) {
                throw ValidationException::withMessages([$field => "$label must be on or after $previousLabel."]);
            }
            if (array_key_exists($field, $data)) {
                $data[$field] = $date->format('Y-m-d H:i:s');
            }
            if ($flag) {
                $data[$flag] = $precise;
            }
            $previous = $date;
            $previousLabel = $label;
            $previousPrecise = $precise;
        }

        return $data;
    }
}
