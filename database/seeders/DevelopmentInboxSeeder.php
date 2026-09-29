<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevelopmentInboxSeeder extends Seeder
{
    public const ACCOUNTS = [
        ['dalilynurnisasport@gmail.com', 'dalilynurnisasport@gmail.com', 'ICT Administrator'],
        ['technician@assetkeeper.test', 'dalilynurnisasport+technician@gmail.com', 'ICT Staff / Technician'],
        ['department@assetkeeper.test', 'dalilynurnisasport+department@gmail.com', 'Department Staff'],
        ['management@assetkeeper.test', 'dalilynurnisasport+management@gmail.com', 'Management (HOD)'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Development inbox setup is restricted to local/testing environments.');
        }

        DB::transaction(function () {
            foreach (self::ACCOUNTS as [$original, $destination, $role]) {
                $matches = User::whereIn('email', array_unique([$original, $destination]))->lockForUpdate()->get();
                if ($matches->count() !== 1 || $matches->first()->role !== $role) {
                    throw new \RuntimeException('Expected exactly one existing account with the correct role for '.$destination.'. No accounts were changed.');
                }
                $user = $matches->first();
                if ($user->email !== $destination) {
                    // Update only email: preserve credentials, status, timestamps and every relationship.
                    DB::table('users')->where('id', $user->id)->update(['email' => $destination]);
                }
            }
        });
    }
}
