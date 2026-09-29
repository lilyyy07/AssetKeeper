<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DevelopmentInboxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DevelopmentInboxSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_only_emails_without_duplicates_and_is_repeatable(): void
    {
        $this->seed();
        User::factory()->create(['email' => 'dalilynurnisasport@gmail.com', 'role' => 'ICT Administrator', 'is_active' => true]);
        User::factory()->create(['email' => 'deptstaff@assetkeeper.test', 'role' => 'Department Staff']);
        // An inactive account must stay inactive too.
        User::where('email', 'department@assetkeeper.test')->update(['is_active' => false]);
        $before = DB::table('users')->orderBy('id')->get()->keyBy('id');
        $records = [];
        foreach (['assets', 'allocations', 'maintenance_records', 'activity_logs', 'departments'] as $table) {
            $records[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $this->seed(DevelopmentInboxSeeder::class);
        $this->seed(DevelopmentInboxSeeder::class);
        $this->assertDatabaseCount('users', count($before));
        foreach (DB::table('users')->orderBy('id')->get() as $after) {
            $original = (array) $before[$after->id];
            foreach (DevelopmentInboxSeeder::ACCOUNTS as [$old, $new]) {
                if ($original['email'] === $old) {
                    $original['email'] = $new;
                }
            }
            $this->assertSame($original, (array) $after);
        }
        foreach ($records as $table => $snapshot) {
            $this->assertSame($snapshot, DB::table($table)->orderBy('id')->get()->toJson());
        }
        foreach (DevelopmentInboxSeeder::ACCOUNTS as [$old, $new, $role]) {
            $user = User::where('email', $new)->sole();
            $this->assertSame($role, $user->role);
            $this->assertSame(in_array($role, ['ICT Administrator', 'ICT Staff / Technician'], true), $user->requiresEmailOtp());
        }
    }

    public function test_destination_collision_rolls_back_all_updates(): void
    {
        $this->seed();
        User::factory()->create(['email' => 'dalilynurnisasport@gmail.com', 'role' => 'ICT Administrator']);
        User::factory()->create(['email' => 'dalilynurnisasport+management@gmail.com', 'role' => 'Management (HOD)']);
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        try {
            $this->seed(DevelopmentInboxSeeder::class);
            $this->fail('Expected a destination collision to abort the transaction.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Expected exactly one existing account', $e->getMessage());
        }
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
    }

    public function test_production_environment_is_rejected(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        (new DevelopmentInboxSeeder)->run();
    }
}
