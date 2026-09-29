<?php

namespace Database\Seeders;

use App\Models\Allocation;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Services\Audit;
use App\Services\SeededDisplayContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PresentationCleanupSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Presentation cleanup is restricted to local/testing environments.');
        }
        DB::transaction(function () {
            foreach (SeededDisplayContent::USERS as $email => [$old, $new]) {
                $user = User::where('email', $email)->where('name', $old)->first();
                if ($user) {
                    $this->updateDisplay($user, ['name' => $new]);
                }
            }
            for ($i = 1; $i <= 8; $i++) {
                $suffix = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $oldTag = 'AK-DEMO-'.$suffix;
                $newTag = 'UPTM-ICT-'.$suffix;
                $asset = Asset::where('asset_tag', $oldTag)->first();
                if (! $asset) {
                    continue;
                }
                if (Asset::where('asset_tag', $newTag)->exists()) {
                    throw new \RuntimeException('An asset tag conflicts with the cleanup; transaction rolled back.');
                }
                $changes = ['asset_tag' => $newTag];
                foreach (['supplier', 'serial_number'] as $field) {
                    $changes[$field] = SeededDisplayContent::display($asset->$field);
                }
                $this->updateDisplay($asset, $changes);
                foreach (Allocation::where('asset_id', $asset->id)->where('assigned_to', 'Demo Academic Officer')->get() as $allocation) {
                    $this->updateDisplay($allocation, ['assigned_to' => 'Morgan Ellis']);
                }
                foreach (MaintenanceRecord::where('asset_id', $asset->id)->where('issue', 'Fictional network port fault')->get() as $maintenance) {
                    $this->updateDisplay($maintenance, ['issue' => 'Network port fault']);
                }
            }
        });
    }

    private function updateDisplay($record, array $changes): void
    {
        $old = $record->toArray();
        $record->update($changes);
        Audit::record('Display details updated', $record, $old, $record->toArray());
    }
}
