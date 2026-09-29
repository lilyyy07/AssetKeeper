<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevelopmentUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_seeder_resets_existing_accounts_without_duplicates_or_unrelated_changes(): void
    {
        $this->seed();
        $accounts = [
            'admin@assetkeeper.test' => 'ICT Administrator',
            'technician@assetkeeper.test' => 'ICT Staff / Technician',
            'department@assetkeeper.test' => 'Department Staff',
            'management@assetkeeper.test' => 'Management (HOD)',
        ];
        $ids = User::whereIn('email', array_keys($accounts))->pluck('id', 'email')->all();
        $unrelated = User::factory()->create();
        $unrelatedAttributes = $unrelated->fresh()->getRawOriginal();
        foreach ($accounts as $email => $role) {
            $user = User::where('email', $email)->firstOrFail();
            $user->forceFill([
                'name' => 'Preserved custom name',
                'password' => Hash::make('OldDevelopment!2026'),
                'role' => 'Department Staff',
                'is_active' => false,
                'remember_token' => 'preserved-remember-token',
            ])->save();
        }

        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('assets', 8);
        foreach ($accounts as $email => $role) {
            $user = User::where('email', $email)->sole();
            $this->assertSame($ids[$email], $user->id);
            $this->assertSame('Preserved custom name', $user->name);
            $this->assertSame('preserved-remember-token', $user->remember_token);
            $this->assertSame($role, $user->role);
            $this->assertSame($role !== 'Department Staff', $user->is_active);
            $this->assertNotSame('unknown', password_get_info($user->getRawOriginal('password'))['algoName']);
            $this->assertTrue(Hash::check('DevOnly!2026AK', $user->getRawOriginal('password')));
        }
        $this->assertSame($unrelatedAttributes, $unrelated->fresh()->getRawOriginal());
    }
}
