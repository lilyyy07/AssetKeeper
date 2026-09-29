<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Allocation;
use App\Models\Asset;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BorrowerDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'ICT Administrator', 'is_active' => true]);
        $this->actingAs($user)->withSession(['email_mfa_user' => $user->id]);

        return $user;
    }

    private function equipment(?int $department = null): Asset
    {
        return Asset::create(['asset_tag' => 'BORROW-'.uniqid(), 'name' => 'Borrowing Laptop', 'category' => 'Laptop', 'location' => 'ICT Store', 'status' => 'Available', 'condition' => 'Good', 'department_id' => $department, 'created_by' => auth()->id()]);
    }

    private function details(Asset $asset, Department $department, string $type = 'Student'): array
    {
        return ['asset_id' => $asset->id, 'assigned_to' => 'Borrower Fixture', 'borrower_type' => $type, 'borrower_identifier' => '  AM2412018233  ', 'borrower_email' => 'borrower@example.test', 'department_id' => $department->id, 'location' => 'Learning Lab', 'allocation_date' => today()->toDateString(), 'expected_return_date' => today()->addWeek()->toDateString()];
    }

    public function test_student_and_academic_staff_borrow_without_login_accounts_and_return_synchronizes_inventory(): void
    {
        $this->admin();
        $department = Department::create(['name' => 'Faculty of Computing and Multimedia']);
        foreach (['Student', 'Academic Staff'] as $type) {
            $asset = $this->equipment();
            $data = $this->details($asset, $department, $type);
            $this->post('/allocations', $data)->assertSessionHasNoErrors();
            $allocation = Allocation::where('asset_id', $asset->id)->sole();
            $this->assertSame($type, $allocation->borrower_type);
            $this->assertSame('AM2412018233', $allocation->borrower_identifier);
            $this->assertSame('Allocated', $asset->fresh()->status);
            $this->assertSame($department->id, $asset->fresh()->department_id);
            $this->assertSame('Learning Lab', $asset->fresh()->location);
            $this->get('/allocations')->assertOk()->assertSee('Borrower Fixture')->assertSee('AM2412018233');
            $this->get('/assets/'.$asset->id)->assertOk()->assertSee($type)->assertSee('AM2412018233')->assertDontSee('borrower@example.test');
            $this->get('/inventory')->assertOk()->assertSee('Borrower Fixture');
            $this->post('/allocations/'.$allocation->id.'/return', ['actual_return_date' => today()->toDateString(), 'return_condition' => 'Good'])->assertSessionHasNoErrors();
            $this->assertSame('Available', $asset->fresh()->status);
            $this->assertNull($asset->fresh()->currentAllocation);
            $this->assertSame('Returned', $allocation->fresh()->status);
            $this->assertSame('AM2412018233', $allocation->fresh()->borrower_identifier);
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertSame(['ICT Administrator', 'ICT Staff / Technician', 'Management (HOD)'], User::ROLES);
    }

    public function test_borrower_fields_and_identifiers_are_validated_server_side(): void
    {
        $this->admin();
        $department = Department::create(['name' => 'Academic']);
        $data = $this->details($this->equipment(), $department);
        foreach (['borrower_type', 'assigned_to', 'borrower_identifier'] as $field) {
            $invalid = $data;
            unset($invalid[$field]);
            $this->post('/allocations', $invalid)->assertSessionHasErrors($field);
        }
        foreach (['borrower_type' => 'Lecturer', 'borrower_identifier' => '<invalid>', 'borrower_email' => 'invalid'] as $field => $value) {
            $this->post('/allocations', array_replace($data, [$field => $value]))->assertSessionHasErrors($field);
        }
        $this->post('/allocations', array_replace($data, ['borrower_identifier' => str_repeat('A', 65)]))->assertSessionHasErrors('borrower_identifier');
        $this->assertDatabaseCount('allocations', 0);
        $this->post('/allocations', array_replace($data, ['borrower_type' => 'Department / Unit', 'borrower_identifier' => null, 'borrower_email' => null]))->assertSessionHasNoErrors();
    }

    public function test_borrower_report_csv_escaping_and_audit_minimization(): void
    {
        $this->admin();
        $department = Department::create(['name' => 'Academic']);
        $data = $this->details($this->equipment(), $department);
        $data['assigned_to'] = '<script>Borrower</script>';
        $this->post('/allocations', $data)->assertSessionHasNoErrors();
        $this->get('/reports?type=allocation')->assertOk()->assertSee('Borrower Type')->assertSee('Student / Staff ID')->assertSee('AM2412018233')->assertDontSee('<script>Borrower</script>', false);
        $csv = $this->get('/reports?type=allocation&export=csv')->streamedContent();
        $this->assertStringContainsString('AM2412018233', $csv);
        $this->assertStringContainsString('Student', $csv);
        $this->assertStringNotContainsString('borrower@example.test', $csv);
        $audit = ActivityLog::all()->toJson();
        $this->assertStringNotContainsString('AM2412018233', $audit);
        $this->assertStringNotContainsString('borrower@example.test', $audit);
        $this->assertStringNotContainsString('Borrower</script>', $audit);
    }

    public function test_retired_role_cannot_read_borrower_history_or_signed_qr(): void
    {
        $this->admin();
        $old = Department::create(['name' => 'Old department']);
        $current = Department::create(['name' => 'Current department']);
        $asset = $this->equipment();
        $this->post('/allocations', $this->details($asset, $old));
        $allocation = Allocation::sole();
        $this->post('/allocations/'.$allocation->id.'/return', ['actual_return_date' => today()->toDateString(), 'return_condition' => 'Good']);
        $asset->update(['department_id' => $current->id]);
        $viewer = User::factory()->create(['role' => 'Department Staff', 'department_id' => $current->id, 'is_active' => true]);
        $this->actingAs($viewer);
        $this->get('/assets/'.$asset->id)->assertRedirect('/login');
        $this->get(URL::signedRoute('assets.verify', ['asset' => $asset->id]))->assertRedirect('/login');
        $this->get('/allocations')->assertRedirect('/login');
        $this->get('/reports?type=allocation')->assertRedirect('/login');
        $outsider = User::factory()->create(['role' => 'Department Staff', 'is_active' => true]);
        $this->actingAs($outsider)->get('/assets/'.$asset->id)->assertRedirect('/login');
        $this->post('/logout');
        $this->get(URL::signedRoute('assets.verify', ['asset' => $asset->id]))->assertRedirect('/login');
    }

    public function test_admin_manages_departments_and_names_are_unique_and_nonblank(): void
    {
        $this->admin();
        $this->post('/departments', ['name' => '  Computing  ', 'is_active' => 1])->assertRedirect('/users')->assertSessionHasNoErrors();
        $department = Department::where('name', 'Computing')->sole();
        $this->post('/departments', ['name' => 'computing', 'is_active' => 1])->assertSessionHasErrors('name');
        $this->post('/departments', ['name' => '   ', 'is_active' => 1])->assertSessionHasErrors('name');
        $this->put('/departments/'.$department->id, ['name' => 'Computing Faculty', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($department->fresh()->is_active);
        $this->assertSame('Computing Faculty', $department->fresh()->name);
        $this->put('/departments/'.$department->id, ['name' => 'Computing Faculty', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($department->fresh()->is_active);
        foreach (['/assets'=>'asset-new', '/allocations'=>'allocation-new', '/users'=>'user-new'] as $url=>$modal) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertContains((string) $department->id, $this->optionsFor($html, $modal));
        }
        $this->get('/users')->assertOk()->assertSee('Manage Departments')->assertSee('Computing Faculty');
        $this->assertDatabaseHas('activity_logs', ['action' => 'Department created']);
    }

    public function test_all_non_admin_roles_and_guests_cannot_manage_departments(): void
    {
        $department = Department::create(['name' => 'ICT Department']);
        foreach (['ICT Staff / Technician', 'Management (HOD)'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->withSession(['email_mfa_user' => $user->id]);
            $this->post('/departments', ['name' => 'Unauthorized', 'is_active' => 1])->assertForbidden();
            $this->put('/departments/'.$department->id, ['name' => 'Unauthorized', 'is_active' => 0])->assertForbidden();
        }
        $this->post('/logout');
        $this->post('/departments', ['name' => 'Unauthorized', 'is_active' => 1])->assertRedirect('/login');
        $this->assertDatabaseCount('departments', 1);
        $this->assertSame('ICT Department', $department->fresh()->name);
    }

    private function optionsFor(string $html, string $modal): array
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $nodes = (new \DOMXPath($document))->query('//div[@id="'.$modal.'"]//select[@name="department_id"]/option');

        return array_map(fn ($node) => $node->getAttribute('value'), iterator_to_array($nodes));
    }

    public function test_inactive_departments_are_excluded_for_new_records_but_preserved_in_edits_and_filters(): void
    {
        $admin = $this->admin();
        $active = Department::create(['name' => 'Active department']);
        $inactive = Department::create(['name' => 'Historical department', 'is_active' => false]);
        $admin->update(['department_id' => $inactive->id]);
        $asset = $this->equipment($inactive->id);
        $html = $this->get('/assets')->assertOk()->getContent();
        $this->assertContains((string) $active->id, $this->optionsFor($html, 'asset-new'));
        $this->assertNotContains((string) $inactive->id, $this->optionsFor($html, 'asset-new'));
        $this->assertContains((string) $inactive->id, $this->optionsFor($html, 'asset-'.$asset->id));
        $users = $this->get('/users')->assertOk()->getContent();
        $this->assertNotContains((string) $inactive->id, $this->optionsFor($users, 'user-new'));
        $this->assertContains((string) $inactive->id, $this->optionsFor($users, 'user-'.$admin->id));
        $allocations = $this->get('/allocations')->assertOk()->getContent();
        $this->assertNotContains((string) $inactive->id, $this->optionsFor($allocations, 'allocation-new'));
        $this->get('/reports?department_id='.$inactive->id)->assertOk()->assertSee('Historical department');
        $assetData = $asset->only(['asset_tag', 'name', 'category', 'location', 'department_id', 'status', 'condition']);
        $this->put('/assets/'.$asset->id, $assetData)->assertSessionHasNoErrors();
        $this->post('/assets', array_replace($assetData, ['asset_tag' => 'NEW-INACTIVE']))->assertSessionHasErrors('department_id');
        $this->post('/allocations', $this->details($asset, $inactive))->assertSessionHasErrors('department_id');
        $this->post('/users', ['name' => 'New user', 'email' => 'inactive-dept@example.test', 'password' => 'FixtureOnly!2026', 'password_confirmation' => 'FixtureOnly!2026', 'role' => 'Management (HOD)', 'department_id' => $inactive->id, 'is_active' => 1])->assertSessionHasErrors('department_id');
    }

    public function test_legacy_allocations_remain_readable_and_can_keep_inactive_department_on_edit(): void
    {
        $this->admin();
        $department = Department::create(['name' => 'Historical department', 'is_active' => false]);
        $asset = $this->equipment($department->id);
        $allocation = Allocation::create(['asset_id' => $asset->id, 'assigned_to' => 'Historical borrower', 'department_id' => $department->id, 'location' => 'Lab', 'allocation_date' => today()->toDateString(), 'status' => 'Active', 'created_by' => auth()->id()]);
        $this->assertNull($allocation->fresh()->borrower_type);
        $this->get('/allocations')->assertOk()->assertSee('Historical borrower')->assertSee('Not recorded');
        $this->get('/assets/'.$asset->id)->assertOk()->assertSee('Historical borrower');
        $this->get('/reports?type=allocation')->assertOk()->assertSee('Historical borrower');
        $data = $this->details($asset, $department, 'Other');
        unset($data['asset_id']);
        $this->put('/allocations/'.$allocation->id, $data)->assertSessionHasNoErrors();
        $this->assertSame($department->id, $allocation->fresh()->department_id);
    }

    public function test_dashboard_prioritizes_business_events_without_deleting_security_audit(): void
    {
        $this->admin();
        ActivityLog::create(['action' => 'Asset updated', 'description' => 'Operational event']);
        ActivityLog::create(['action' => 'OTP challenge generated', 'description' => 'OTP challenge generated']);
        $this->get('/dashboard')->assertOk()->assertSee('Operational event')->assertDontSee('OTP challenge generated');
        $this->assertDatabaseHas('activity_logs', ['action' => 'OTP challenge generated']);
    }
}
