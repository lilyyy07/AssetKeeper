<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Allocation;
use App\Models\Asset;
use App\Models\Department;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AssetKeeperTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'ICT Administrator', ?int $department = null): User
    {
        return User::factory()->create(['role' => $role, 'department_id' => $department, 'is_active' => true]);
    }

    private function operator(): User
    {
        $u = $this->user();
        $this->actingAs($u)->withSession(['email_mfa_user' => $u->id]);

        return $u;
    }

    private function asset(array $data = []): Asset
    {
        return Asset::create(array_merge(['asset_tag' => 'TEST-'.uniqid(), 'name' => 'Test Laptop', 'category' => 'Laptop', 'location' => 'ICT Store', 'status' => 'Available', 'condition' => 'Good', 'created_by' => auth()->id() ?? $this->user()->id], $data));
    }

    private function allocation(Asset $a): array
    {
        return ['asset_id' => $a->id, 'assigned_to' => 'Fictional Officer', 'borrower_type' => 'Other', 'department_id' => Department::firstOrCreate(['name' => 'Academic'])->id, 'location' => 'Lab', 'allocation_date' => today()->toDateString(), 'expected_return_date' => today()->addDay()->toDateString()];
    }

    private function maintenance(Asset $a): array
    {
        return ['asset_id' => $a->id, 'issue' => 'Keyboard fault', 'type' => 'Hardware', 'priority' => 'High', 'date_reported' => today()->toDateString(), 'status' => 'Pending'];
    }

    public function test_guest_routes_and_no_registration(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk();
        $this->get('/register')->assertNotFound();
    }

    public function test_valid_login_logout_and_inactive_rejection(): void
    {
        $u = $this->user('Management (HOD)');
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $u->update(['is_active' => false]);
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_invalid_login_is_throttled(): void
    {
        $this->freezeTime();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'missing@example.test', 'password' => 'bad'])->assertSessionHasErrors('email');
        }$this->post('/login', ['email' => 'missing@example.test', 'password' => 'bad'])->assertSessionHasErrors(['email' => 'Too many attempts. Try again in 60 seconds.']);
        $this->assertDatabaseCount('activity_logs', 5);
    }

    public function test_all_roles_and_server_side_permissions(): void
    {
        $admin = $this->operator();
        $this->get('/users')->assertOk();
        $staff = $this->user('ICT Staff / Technician');
        $this->actingAs($staff)->withSession(['email_mfa_user' => $staff->id]);
        $this->get('/users')->assertForbidden();
        $this->post('/users', [])->assertForbidden();
        $this->get('/allocations')->assertOk();
        $department = $this->user('Department Staff');
        $this->actingAs($department);
        foreach (['/users', '/allocations', '/maintenance', '/reports'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }$this->post('/assets', [])->assertRedirect('/login');
        $management = $this->user('Management (HOD)');
        $this->actingAs($management);
        $this->get('/reports')->assertOk();
        $this->post('/assets', [])->assertForbidden();
    }

    public function test_management_visibility_and_signed_qr(): void
    {
        $this->operator();
        $asset = $this->asset();
        $u = $this->user('Management (HOD)');
        $this->actingAs($u);
        $this->get('/assets')->assertSee($asset->asset_tag);
        $this->get('/inventory')->assertSee($asset->asset_tag);
        $this->get(URL::signedRoute('assets.verify', ['asset' => $asset->id]))->assertOk();
        $this->get('/assets/'.$asset->id.'/verify?signature=invalid')->assertForbidden();
    }
    public function test_asset_validation_and_integration(): void
    {
        $this->operator();
        $data = ['asset_tag' => 'AK-TEST', 'name' => 'Integration Laptop', 'category' => 'Laptop', 'location' => 'ICT Store', 'status' => 'Available', 'condition' => 'Good'];
        $this->post('/assets', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assets', ['asset_tag' => 'AK-TEST']);
        $this->post('/assets', $data)->assertSessionHasErrors('asset_tag');
        $this->post('/assets', ['name' => ''])->assertSessionHasErrors(['asset_tag', 'name', 'location']);
        foreach (['/assets', '/inventory', '/reports'] as $path) {
            $this->get($path)->assertOk()->assertSee('Integration Laptop');
        }$this->get('/dashboard')->assertViewHas('counts', fn ($c) => $c['Available'] === 1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Asset created']);
    }

    public function test_allocation_duplicate_return_and_audit(): void
    {
        $this->operator();
        $a = $this->asset();
        $data = $this->allocation($a);
        $this->post('/allocations', $data)->assertSessionHasNoErrors();
        $this->assertSame('Allocated', $a->fresh()->status);
        $this->post('/allocations', $data)->assertSessionHasErrors('asset_id');
        $this->assertDatabaseCount('allocations', 1);
        $record = Allocation::first();
        $this->post('/allocations/'.$record->id.'/return', ['actual_return_date' => today()->toDateString(), 'return_condition' => 'Good', 'return_remarks' => 'Checked'])->assertSessionHasNoErrors();
        $this->assertSame('Available', $a->fresh()->status);
        $this->assertSame('Returned', $record->fresh()->status);
        $this->post('/allocations/'.$record->id.'/return', ['actual_return_date' => today()->toDateString(), 'return_condition' => 'Good'])->assertSessionHasErrors('allocation');
        $this->assertDatabaseHas('activity_logs', ['action' => 'Asset returned']);
    }

    public function test_maintenance_completion_restores_allocation_and_return_preserves_maintenance(): void
    {
        $this->operator();
        $a = $this->asset();
        $this->post('/allocations', $this->allocation($a));
        $this->post('/maintenance', $this->maintenance($a))->assertSessionHasNoErrors();
        $this->assertSame('Under Maintenance', $a->fresh()->status);
        $m = MaintenanceRecord::first();
        $completion = collect($this->maintenance($a))->except('asset_id')->all() + ['actual_completion_date' => today()->toDateString()];
        $completion['status'] = 'Completed';
        $this->put('/maintenance/'.$m->id, $completion)->assertSessionHasNoErrors();
        $this->assertSame('Allocated', $a->fresh()->status);
        $this->post('/maintenance', $this->maintenance($a));
        $this->post('/allocations/'.Allocation::first()->id.'/return', ['actual_return_date' => today()->toDateString(), 'return_condition' => 'Good']);
        $this->assertSame('Under Maintenance', $a->fresh()->status);
        $this->put('/maintenance/'.MaintenanceRecord::latest('id')->first()->id, $completion);
        $this->assertSame('Available', $a->fresh()->status);
    }

    public function test_damaged_state_preserved_and_disposal_is_guarded(): void
    {
        $this->operator();
        $a = $this->asset();
        $this->post('/allocations', $this->allocation($a));
        $data = $a->only(['asset_tag', 'name', 'category', 'location', 'condition']);
        $this->put('/assets/'.$a->id, $data + ['status' => 'Disposed'])->assertSessionHasErrors('status');
        $this->post('/maintenance', $this->maintenance($a));
        $this->post('/allocations/'.Allocation::first()->id.'/return', ['actual_return_date' => today()->toDateString(), 'return_condition' => 'Poor']);
        $this->assertSame('Damaged', $a->fresh()->status);
        $completion = collect($this->maintenance($a))->except('asset_id')->all();
        $completion['status'] = 'Completed';
        $completion['actual_completion_date'] = today()->toDateString();
        $this->put('/maintenance/'.MaintenanceRecord::first()->id, $completion);
        $this->assertSame('Damaged', $a->fresh()->status);
    }

    public function test_all_reports_csv_and_pages_render(): void
    {
        $this->seed();
        $u = User::where('email', 'admin@assetkeeper.test')->first();
        $this->actingAs($u)->withSession(['email_mfa_user' => $u->id]);
        foreach (['/dashboard', '/assets', '/inventory', '/allocations', '/maintenance', '/users', '/profile'] as $url) {
            $this->get($url)->assertOk();
        }foreach (Asset::all() as $asset) {
            $this->get('/assets/'.$asset->id)->assertOk();
        }foreach (['inventory', 'allocation', 'status', 'location', 'maintenance'] as $type) {
            $this->get('/reports?type='.$type)->assertOk()->assertViewHas('total', fn ($count) => $count > 0);
            $response = $this->get('/reports?type='.$type.'&export=csv')->assertOk();
            $this->assertNotEmpty($response->streamedContent());
        }
    }

    public function test_user_management_and_password_change(): void
    {
        $admin = $this->operator();
        $this->post('/users', ['name' => 'New Demo', 'email' => 'new@example.test', 'role' => 'Management (HOD)', 'is_active' => 1, 'password' => 'SecureExample!2026', 'password_confirmation' => 'SecureExample!2026'])->assertSessionHasNoErrors();
        $new = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('SecureExample!2026', $new->password));
        $this->put('/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => $admin->role, 'is_active' => 0])->assertSessionHasErrors('role');
        $this->put('/profile/password', ['current_password' => 'incorrect', 'password' => 'ChangedExample!2026', 'password_confirmation' => 'ChangedExample!2026'])->assertSessionHasErrors('current_password');
        $this->put('/profile/password', ['current_password' => 'password', 'password' => 'ChangedExample!2026', 'password_confirmation' => 'ChangedExample!2026'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('ChangedExample!2026', $admin->fresh()->password));
    }

    public function test_existing_session_cannot_bypass_deactivation_or_mfa(): void
    {
        $u = $this->user();
        $this->actingAs($u)->get('/dashboard')->assertRedirect('/login');
        $this->actingAs($u);
        $this->withSession(['email_mfa_user' => $u->id]);
        $u->update(['is_active' => false]);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_seed_is_repeatable(): void
    {
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('assets', 8);
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('allocations', 1);
    }

    public function test_filter_dates_historical_department_and_csv_formula_safety(): void
    {
        $this->operator();
        $a = $this->asset(['name' => '=HYPERLINK("example")']);
        $original = $this->allocation($a);
        $this->post('/allocations', $original);
        $allocation = Allocation::first();
        $this->post('/allocations/'.$allocation->id.'/return', ['actual_return_date' => today()->toDateString(), 'return_condition' => 'Good']);
        $other = Department::create(['name' => 'Other']);
        $a->refresh()->update(['department_id' => $other->id, 'location' => 'New Store']);
        $this->get('/reports?type=allocation&department_id='.$original['department_id'].'&location=Lab')->assertViewHas('total', 1);
        $this->get('/reports?to='.today()->toDateString())->assertOk()->assertViewHas('total', 1);
        $csv = $this->get('/reports?export=csv')->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->get('/inventory?condition=Good&location=New%20Store')->assertViewHas('assets', fn ($rows) => $rows->total() === 1);
    }

    public function test_multiple_maintenance_records_and_validation(): void
    {
        $this->operator();
        $a = $this->asset();
        $data = $this->maintenance($a);
        $this->post('/maintenance', $data);
        $this->post('/maintenance', $data);
        $done = collect($data)->except('asset_id')->all();
        $done['status'] = 'Completed';
        $this->put('/maintenance/'.MaintenanceRecord::first()->id, $done)->assertSessionHasErrors('actual_completion_date');
        $done['actual_completion_date'] = today()->toDateString();
        $this->put('/maintenance/'.MaintenanceRecord::first()->id, $done)->assertSessionHasNoErrors();
        $this->assertSame('Under Maintenance', $a->fresh()->status);
        $this->post('/allocations', $this->allocation($a))->assertSessionHasErrors('asset_id');
        $outsider = $this->user('Department Staff');
        $data['technician_id'] = $outsider->id;
        $this->post('/maintenance', $data)->assertSessionHasErrors('technician_id');
    }

    public function test_session_timeout(): void
    {
        $this->operator();
        $this->withSession(['last_activity' => time() - 1801])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_asset_edit_validation_old_input_and_audit_safety(): void
    {
        $this->operator();
        $a = $this->asset();
        $data = $a->only(['asset_tag', 'name', 'category', 'location', 'status', 'condition']);
        $data['purchase_price'] = -1;
        $this->from('/assets')->put('/assets/'.$a->id, $data + ['_form' => 'asset-'.$a->id])->assertRedirect('/assets')->assertSessionHasErrors('purchase_price')->assertSessionHas('_old_input._form', 'asset-'.$a->id);
        $data['purchase_price'] = 10;
        $data['status'] = 'Damaged';
        $this->put('/assets/'.$a->id, $data)->assertSessionHasNoErrors();
        $this->assertSame('Damaged', $a->fresh()->status);
        $this->get('/inventory?status=Damaged')->assertViewHas('assets', fn ($rows) => $rows->total() === 1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Asset updated']);
        $log = ActivityLog::first();
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->expectException(\LogicException::class);
        $log->delete();
    }
}
