<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Allocation;
use App\Models\Asset;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\PresentationCleanupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FinalCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_provisioning_is_idempotent_and_preserves_other_accounts(): void
    {
        $this->seed();
        $before = User::all()->map->getRawOriginal()->all();
        $department = Department::where('name', 'ICT Department')->firstOrFail();
        foreach (range(1, 2) as $run) {
            $this->artisan('assetkeeper:provision-development-admin')
                ->expectsQuestion('Temporary development password', 'FixtureOnly!2026')
                ->assertExitCode(0);
        }
        $user = User::where('email', 'dalilynurnisasport@gmail.com')->sole();
        $this->assertSame('Lily', $user->name);
        $this->assertTrue(Hash::check('FixtureOnly!2026', $user->password));
        $this->assertTrue($user->is_active);
        $this->assertSame('ICT Administrator', $user->role);
        $this->assertSame($department->id, $user->department_id);
        $this->assertTrue($user->requiresEmailOtp());
        $this->assertSame($before, User::where('id', '!=', $user->id)->get()->map->getRawOriginal()->all());
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('assets', 8);
        $this->assertStringNotContainsString('FixtureOnly!2026', ActivityLog::all()->toJson());
    }

    public function test_presentation_cleanup_preserves_credentials_relationships_and_original_audit_history(): void
    {
        $this->seed();
        $user = User::where('email', 'admin@assetkeeper.test')->firstOrFail();
        $user->update(['name' => 'Demo Administrator']);
        $credentials = $user->getRawOriginal('password');
        $asset = Asset::where('asset_tag', 'UPTM-ICT-002')->firstOrFail();
        $asset->update(['asset_tag' => 'AK-DEMO-002', 'supplier' => 'Demo Equipment Supplier', 'serial_number' => 'FICTIONAL-2']);
        $allocation = Allocation::where('asset_id', $asset->id)->firstOrFail();
        $allocation->update(['assigned_to' => 'Demo Academic Officer']);
        $auditBefore = ActivityLog::all()->map->getRawOriginal()->all();
        $this->seed(PresentationCleanupSeeder::class);
        $count = ActivityLog::count();
        $this->seed(PresentationCleanupSeeder::class);
        $this->assertSame($count, ActivityLog::count());
        $this->assertSame('Alex Morgan', $user->fresh()->name);
        $this->assertSame($credentials, $user->fresh()->getRawOriginal('password'));
        $this->assertSame('UPTM-ICT-002', $asset->fresh()->asset_tag);
        $this->assertSame('Northbridge ICT Supplies', $asset->fresh()->supplier);
        $this->assertSame('ICT-2026-002', $asset->fresh()->serial_number);
        $this->assertSame($asset->id, $allocation->fresh()->asset_id);
        $this->assertSame('Morgan Ellis', $allocation->fresh()->assigned_to);
        $this->assertSame($auditBefore, ActivityLog::whereIn('id', array_column($auditBefore, 'id'))->get()->map->getRawOriginal()->all());
        $this->assertDatabaseCount('assets', 8);
        $this->assertDatabaseCount('allocations', 1);
        $this->assertDatabaseCount('maintenance_records', 1);
        $this->seed();
        $this->assertDatabaseCount('assets', 8);
    }

    public function test_setup_is_rejected_outside_development(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('assetkeeper:provision-development-admin')->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_account_management_updates_access_and_non_admins_are_denied(): void
    {
        $ict = Department::create(['name' => 'ICT Department']);
        $academic = Department::create(['name' => 'Academic Department']);
        $admin = User::factory()->create(['role' => 'ICT Administrator', 'department_id' => $ict->id, 'is_active' => true]);
        $this->actingAs($admin)->withSession(['email_mfa_user' => $admin->id]);
        $data = ['name' => 'Account workflow fixture', 'email' => 'workflow@example.test',
            'password' => 'FixtureOnly!2026', 'password_confirmation' => 'FixtureOnly!2026',
            'role' => 'Management (HOD)', 'department_id' => $academic->id, 'is_active' => 1];
        $this->post('/users', $data)->assertSessionHasNoErrors();
        $user = User::where('email', $data['email'])->sole();
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertFalse($user->requiresEmailOtp());
        $data['role'] = 'ICT Staff / Technician';
        $data['department_id'] = $ict->id;
        $data['password'] = $data['password_confirmation'] = '';
        $this->put('/users/'.$user->id, $data)->assertSessionHasNoErrors();
        $this->assertSame($ict->id, $user->fresh()->department_id);
        $this->assertTrue($user->fresh()->requiresEmailOtp());
        $this->put('/users/'.$user->id, array_replace($data, ['is_active' => 0]))->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->is_active);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'FixtureOnly!2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($admin)->withSession(['email_mfa_user' => $admin->id]);
        $this->put('/users/'.$user->id, $data)->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->is_active);
        foreach (['ICT Staff / Technician', 'Management (HOD)'] as $role) {
            $other = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($other)->withSession(['email_mfa_user' => $other->id]);
            $this->get('/users')->assertForbidden();
            $this->post('/users', $data)->assertForbidden();
            $this->put('/users/'.$user->id, $data)->assertForbidden();
        }
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => true]);
    }
}
