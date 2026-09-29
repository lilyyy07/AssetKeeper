<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetWorkflow
{
    public function recalculate(Asset $asset): void
    {
        if (in_array($asset->status, ['Damaged', 'Disposed'])) {
            return;
        }
        $old = $asset->status;
        $asset->status = $asset->maintenanceRecords()->where('status', '!=', 'Completed')->exists() ? 'Under Maintenance' : ($asset->currentAllocation()->exists() ? 'Allocated' : 'Available');
        $asset->save();
        if ($old !== $asset->status) {
            Audit::record('Asset status changed', $asset, ['status' => $old], ['status' => $asset->status]);
        }
    }

    public function allocate(array $data): Allocation
    {
        return DB::transaction(function () use ($data) {
            $asset = Asset::lockForUpdate()->findOrFail($data['asset_id']);
            if ($asset->status !== 'Available' || $asset->currentAllocation()->exists() || $asset->maintenanceRecords()->where('status', '!=', 'Completed')->exists()) {
                throw ValidationException::withMessages(['asset_id' => 'Only an available asset can be allocated.']);
            }
            $allocation = Allocation::create($data + ['created_by' => auth()->id(), 'status' => 'Active']);
            $asset->update(['location' => $data['location'], 'department_id' => $data['department_id']]);
            $this->recalculate($asset);
            Audit::record('Asset allocated', $allocation, [], $allocation->toArray());

            return $allocation;
        });
    }

    public function returnAsset(Allocation $allocation, array $data): void
    {
        DB::transaction(function () use ($allocation, $data) {
            $asset = Asset::lockForUpdate()->findOrFail($allocation->asset_id);
            $allocation = Allocation::lockForUpdate()->findOrFail($allocation->id);
            if ($allocation->status !== 'Active') {
                throw ValidationException::withMessages(['allocation' => 'This allocation has already been returned.']);
            }
            $allocation->update($data + ['status' => 'Returned']);
            $asset->condition = $data['return_condition'];
            if ($asset->condition === 'Poor' && $asset->status !== 'Disposed') {
                $asset->status = 'Damaged';
            } $asset->save();
            $this->recalculate($asset);
            Audit::record('Asset returned', $allocation, [], $allocation->toArray());
        });
    }

    public function maintenance(array $data, ?MaintenanceRecord $record = null): MaintenanceRecord
    {
        return DB::transaction(function () use ($data, $record) {
            $asset = Asset::lockForUpdate()->findOrFail($record?->asset_id ?? $data['asset_id']);
            if ($asset->status === 'Disposed') {
                throw ValidationException::withMessages(['asset_id' => 'Disposed assets cannot enter maintenance.']);
            } if ($record) {
                $record = MaintenanceRecord::lockForUpdate()->findOrFail($record->id);
                if ($record->status === 'Completed') {
                    throw ValidationException::withMessages(['status' => 'Completed maintenance is preserved as history.']);
                }
            }
            $old = $record?->toArray() ?? [];
            if ($record) {
                $record->update($data);
            } else {
                $record = MaintenanceRecord::create($data + ['created_by' => auth()->id()]);
            } $this->recalculate($asset);
            Audit::record($record->status === 'Completed' ? 'Maintenance completed' : ($old ? 'Maintenance updated' : 'Maintenance created'), $record, $old, $record->toArray());

            return $record;
        });
    }
}
