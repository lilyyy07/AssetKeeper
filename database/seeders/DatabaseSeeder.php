<?php

namespace Database\Seeders;

use App\Models\Allocation;
use App\Models\Asset;
use App\Models\Department;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Development seeding is allowed only in local/testing environments.');
        }
        $departments = [];
        foreach (['ICT Department', 'Administration', 'Academic Department', 'Finance'] as $name) {
            $departments[] = Department::firstOrCreate(['name' => $name]);
        }
        $this->call(PresentationCleanupSeeder::class);
        $this->call(UserSeeder::class);
        $admin = User::where('email', 'admin@assetkeeper.test')->first();
        $tech = User::where('email', 'technician@assetkeeper.test')->first();
        $names = ['Dell Latitude 5440', 'Lenovo ThinkCentre M90q', 'HP LaserJet Pro', 'Cisco Catalyst Switch', 'Epson Projector', 'Dell Monitor', 'Dell PowerEdge Server', 'Logitech Keyboard'];
        $categories = ['Laptop', 'Desktop', 'Printer', 'Network Device', 'Projector', 'Monitor', 'Server', 'Peripheral'];
        foreach ($names as $i => $name) {
            $asset = Asset::firstOrCreate(['asset_tag' => 'UPTM-ICT-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT)], ['name' => $name, 'category' => $categories[$i], 'brand' => explode(' ', $name)[0], 'serial_number' => 'ICT-2026-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT), 'location' => $i === 1 ? 'Academic Lab' : 'ICT Store', 'department_id' => $departments[$i === 1 ? 2 : 0]->id, 'status' => match ($i) {
                1 => 'Allocated',3 => 'Under Maintenance',6 => 'Damaged',7 => 'Disposed',default => 'Available'
            }, 'condition' => $i === 6 ? 'Poor' : 'Good', 'purchase_date' => '2026-01-15', 'purchase_price' => 1200 + ($i * 300), 'supplier' => 'Northbridge ICT Supplies', 'created_by' => $admin->id]);
            if ($asset->wasRecentlyCreated) {
                if ($i === 1) {
                    Allocation::create(['asset_id' => $asset->id, 'assigned_to' => 'Morgan Ellis', 'department_id' => $departments[2]->id, 'location' => 'Academic Lab', 'allocation_date' => today()->subDays(14)->toDateString(), 'expected_return_date' => today()->addDays(14)->toDateString(), 'status' => 'Active', 'created_by' => $admin->id]);
                }if ($i === 3) {
                    MaintenanceRecord::create(['asset_id' => $asset->id, 'issue' => 'Network port fault', 'type' => 'Hardware', 'priority' => 'High', 'date_reported' => today()->toDateString(), 'technician_id' => $tech->id, 'status' => 'In Progress', 'created_by' => $admin->id]);
                }Audit::record('Asset created', $asset, [], $asset->toArray(), $admin->id);
            }
        }
    }
}
