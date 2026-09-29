<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Allocation;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(string $action, ?Model $entity = null, array $old = [], array $new = [], ?int $userId = null): void
    {
        $safe = ['name', 'email', 'role', 'department_id', 'is_active', 'asset_tag', 'status', 'condition', 'location', 'assigned_to', 'actual_return_date', 'return_condition', 'priority', 'type', 'technician_id', 'actual_completion_date', 'date_reported', 'problem_attended_at', 'repair_started_at'];
        if ($entity instanceof Allocation) {
            $safe = array_diff($safe, ['assigned_to']);
        }
        ActivityLog::create(['user_id' => $userId ?? auth()->id(), 'asset_id' => $entity instanceof Asset ? $entity->id : ($entity?->asset_id), 'action' => $action, 'entity_type' => $entity ? class_basename($entity) : null, 'entity_id' => $entity?->id, 'description' => (($userId ? User::find($userId)?->is_uat_account : auth()->user()?->is_uat_account) ? 'UAT: ' : '').$action, 'old_values' => array_intersect_key($old, array_flip($safe)), 'new_values' => array_intersect_key($new, array_flip($safe))]);
    }
}
