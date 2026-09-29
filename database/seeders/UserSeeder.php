<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Development accounts may only be seeded in local/testing environments.');
        }

        DB::transaction(function () {
            $accounts = [
                ['admin@assetkeeper.test', 'Alex Morgan', 'ICT Administrator', 'ICT Department'],
                ['technician@assetkeeper.test', 'Jordan Lee', 'ICT Staff / Technician', 'ICT Department'],
                ['department@assetkeeper.test', 'Taylor Reed', 'Department Staff', 'Academic Department'],
                ['management@assetkeeper.test', 'Casey Brooks', 'Management (HOD)', 'ICT Department'],
            ];

            foreach ($accounts as [$email, $name, $role, $department]) {
                $existing = User::where('email', $email)->first();
                User::updateOrCreate(['email' => $email], [
                    'name' => $existing ? $existing->name : $name,
                    'department_id' => $existing ? $existing->department_id : Department::where('name', $department)->firstOrFail()->id,
                    'password' => Hash::make('DevOnly!2026AK'),
                    'role' => $role,
                    'is_active' => $role !== 'Department Staff',
                ]);
            }
        });
    }
}
