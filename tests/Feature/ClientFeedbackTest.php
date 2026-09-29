<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Services\AssetImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $role = 'ICT Administrator'): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $this->actingAs($user)->withSession(['email_mfa_user' => $user->id]);

        return $user;
    }

    private function data(array $changes = []): array
    {
        return array_replace(['asset_tag' => 'IMPORT-001', 'name' => 'Imported laptop', 'category' => 'Laptop', 'location' => 'ICT Store', 'status' => 'Available', 'condition' => 'Good', 'serial_number' => 'SERIAL-001', 'purchase_date' => '2024-06-15', 'purchase_price' => '1200.50'], $changes);
    }

    private function csv(array $rows): UploadedFile
    {
        $file = fopen('php://temp', 'r+');
        fputcsv($file, AssetImport::HEADERS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($file, array_map(fn ($key) => $row[$key] ?? '', AssetImport::HEADERS), ',', '"', '');
        }
        rewind($file);
        $contents = stream_get_contents($file);
        fclose($file);

        return UploadedFile::fake()->createWithContent('assets.csv', $contents);
    }

    private function preview(array $rows)
    {
        return $this->post('/assets/import/preview', ['file' => $this->csv($rows)]);
    }

    private function equipment(): Asset
    {
        return Asset::create($this->data() + ['created_by' => auth()->id()]);
    }

    private function maintenance(Asset $asset, array $changes = []): array
    {
        return array_replace(['asset_id' => $asset->id, 'issue' => 'Keyboard fault', 'type' => 'Hardware', 'priority' => 'High', 'status' => 'Pending', 'date_reported' => '2026-01-01T09:00'], $changes);
    }

    public function test_retired_role_is_not_assignable_and_cannot_login_even_if_flag_is_active(): void
    {
        $this->loginAs();
        $legacy = User::factory()->create(['role' => 'Department Staff', 'is_active' => true]);
        $asset = Asset::create($this->data() + ['created_by' => $legacy->id]);
        $log = ActivityLog::create(['user_id' => $legacy->id, 'asset_id' => $asset->id, 'action' => 'Historical action', 'description' => 'Preserved']);
        $this->put('/users/'.$legacy->id, ['name' => $legacy->name, 'email' => $legacy->email, 'role' => 'Department Staff', 'is_active' => 1])->assertSessionHasErrors('role');
        $this->post('/users', ['name' => 'Rejected', 'email' => 'reject@example.test', 'role' => 'Department Staff', 'is_active' => 1, 'password' => 'FixtureOnly!2026', 'password_confirmation' => 'FixtureOnly!2026'])->assertSessionHasErrors('role');
        $this->post('/logout');
        $this->post('/login', ['email' => $legacy->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'created_by' => $legacy->id]);
        $this->assertDatabaseHas('activity_logs', ['id' => $log->id, 'user_id' => $legacy->id]);
        $this->assertCount(3, User::ROLES);
    }

    public function test_categories_can_be_added_renamed_deactivated_and_reactivated_without_losing_assets(): void
    {
        $this->loginAs();
        $this->post('/asset-categories', ['name' => 'Tablet', 'is_active' => 1])->assertSessionHasNoErrors();
        $category = AssetCategory::where('name', 'Tablet')->sole();
        $asset = $this->equipment();
        $asset->update(['category' => 'Tablet']);
        $this->put('/asset-categories/'.$category->id, ['name' => 'Tablet Device', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertSame('Tablet Device', $asset->fresh()->category);
        $this->get('/assets/'.$asset->id)->assertOk()->assertSee('Tablet Device');
        $this->post('/assets', $this->data(['asset_tag' => 'NEW', 'serial_number' => 'NEW', 'category' => 'Tablet Device']))->assertSessionHasErrors('category');
        $this->put('/assets/'.$asset->id, $this->data(['category' => 'Tablet Device']))->assertSessionHasNoErrors();
        $this->put('/asset-categories/'.$category->id, ['name' => 'Tablet Device', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->post('/assets', $this->data(['asset_tag' => 'NEW', 'serial_number' => 'NEW', 'category' => 'Tablet Device']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_logs', ['action' => 'Category updated']);
    }

    public function test_only_administrators_can_manage_categories_and_import_on_every_endpoint(): void
    {
        $category = AssetCategory::first();
        foreach (['ICT Staff / Technician', 'Management (HOD)'] as $role) {
            $this->loginAs($role);
            $this->post('/asset-categories', ['name' => 'Forbidden', 'is_active' => 1])->assertForbidden();
            $this->put('/asset-categories/'.$category->id, ['name' => 'Forbidden', 'is_active' => 0])->assertForbidden();
            $this->get('/assets/import/template')->assertForbidden();
            $this->post('/assets/import/preview', [])->assertForbidden();
            $this->post('/assets/import/commit', [])->assertForbidden();
            $this->get('/assets')->assertOk()->assertDontSee('Manage Categories')->assertDontSee('Import Assets');
        }
    }

    public function test_valid_import_requires_preview_then_appears_in_registry_inventory_and_reports(): void
    {
        $this->loginAs();
        Department::create(['name' => 'Computing']);
        $this->preview([$this->data(['department' => 'Computing'])])->assertOk()->assertSee('All rows passed');
        $this->assertDatabaseCount('assets', 0);
        $token = session('asset_import.token');
        $this->post('/assets/import/commit', ['token' => $token])->assertRedirect('/assets')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('assets', 1);
        $asset = Asset::sole();
        $this->assertSame('2024', $asset->year_purchased);
        $this->assertSame('Computing', $asset->department->name);
        foreach (['/assets', '/inventory', '/reports', '/assets/'.$asset->id] as $url) {
            $this->get($url)->assertOk()->assertSee('Imported laptop')->assertSee('2024');
        }
        $this->assertStringContainsString('Year Purchased', $this->get('/reports?export=csv')->streamedContent());
        $this->post('/assets/import/commit', ['token' => $token])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('assets', 1);
    }

    public function test_import_rejects_duplicates_in_database_and_file_and_preserves_valid_rows_until_all_pass(): void
    {
        $this->loginAs();
        $existing = $this->equipment();
        $before = $existing->fresh()->getAttributes();
        $rows = [$this->data(), $this->data(['asset_tag' => 'SECOND']), $this->data(['asset_tag' => 'THIRD', 'serial_number' => 'NEW']), $this->data(['asset_tag' => 'THIRD', 'serial_number' => 'OTHER'])];
        $this->preview($rows)->assertOk()->assertViewHas('result', fn ($r) => count($r['errors']) === 3)->assertSee('Row 2')->assertSee('Row 3')->assertSee('Row 5');
        $this->assertNull(session('asset_import'));
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertDatabaseCount('assets', 1);
    }

    public function test_import_rejects_bad_types_limits_headers_and_invalid_row_values(): void
    {
        $this->loginAs();
        $this->post('/assets/import/preview', ['file' => UploadedFile::fake()->createWithContent('bad.php', '<?php echo 1;')])->assertSessionHasErrors('file');
        $this->post('/assets/import/preview', ['file' => UploadedFile::fake()->create('large.csv', 2049, 'text/csv')])->assertSessionHasErrors('file');
        $this->post('/assets/import/preview', ['file' => UploadedFile::fake()->createWithContent('bad.csv', 'wrong,headers')])->assertSessionHasErrors('file');
        $this->preview(array_fill(0, 501, $this->data()))->assertSessionHasErrors('file');
        AssetCategory::where('name', 'Laptop')->update(['is_active' => false]);
        $this->preview([$this->data(['name' => '', 'department' => 'Unknown', 'purchase_date' => '2024-99-99', 'purchase_price' => -1, 'status' => 'Allocated', 'condition' => 'Excellent'])])->assertOk()->assertViewHas('result', fn ($r) => count($r['errors'][2]) >= 6);
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_commit_revalidates_duplicates_and_category_changes_and_rolls_back_batch(): void
    {
        $this->loginAs();
        $this->preview([$this->data(), $this->data(['asset_tag' => 'SECOND', 'serial_number' => 'SECOND'])])->assertOk();
        $token = session('asset_import.token');
        $this->equipment();
        $this->post('/assets/import/commit', ['token' => $token])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('assets', 1);
        $this->preview([$this->data(['asset_tag' => 'SECOND', 'serial_number' => 'SECOND'])])->assertOk();
        $token = session('asset_import.token');
        AssetCategory::where('name', 'Laptop')->update(['is_active' => false]);
        $this->post('/assets/import/commit', ['token' => $token])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('assets', 1);
    }

    public function test_preview_expires_and_cannot_be_confirmed_by_another_user(): void
    {
        $this->loginAs();
        $this->preview([$this->data()]);
        $pending = session('asset_import');
        $this->loginAs();
        $this->post('/assets/import/commit', ['token' => $pending['token']])->assertSessionHasErrors('file');
        $this->preview([$this->data()]);
        $pending = session('asset_import');
        $pending['expires'] = time() - 1;
        $this->withSession(['asset_import' => $pending])->post('/assets/import/commit', ['token' => $pending['token']])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_maintenance_timing_durations_report_and_status_synchronization(): void
    {
        $this->loginAs();
        $asset = $this->equipment();
        $data = $this->maintenance($asset, ['problem_attended_at' => '2026-01-01T09:20', 'repair_started_at' => '2026-01-01T10:00']);
        $this->post('/maintenance', $data)->assertSessionHasNoErrors();
        $record = MaintenanceRecord::sole();
        $this->assertSame('20 minutes', $record->response_time);
        $this->assertNull($record->repair_duration);
        $this->assertSame('Under Maintenance', $asset->fresh()->status);
        unset($data['asset_id']);
        $data['status'] = 'Completed';
        $data['actual_completion_date'] = '2026-01-01T12:15';
        $this->put('/maintenance/'.$record->id, $data)->assertSessionHasNoErrors();
        $this->assertSame('2 hours 15 minutes', $record->fresh()->repair_duration);
        $this->assertSame('Available', $asset->fresh()->status);
        foreach (['/maintenance/'.$record->id, '/reports?type=maintenance', '/assets/'.$asset->id] as $url) {
            $this->get($url)->assertOk()->assertSee('20 minutes')->assertSee('2 hours 15 minutes');
        }
        $csv = $this->get('/reports?type=maintenance&export=csv')->streamedContent();
        $this->assertStringContainsString('Problem Attended', $csv);
        $this->assertStringContainsString('2 hours 15 minutes', $csv);
    }

    public function test_all_present_timeline_stages_are_checked_including_missing_intermediate_stages(): void
    {
        $this->loginAs();
        $asset = $this->equipment();
        foreach ([['problem_attended_at' => '2026-01-01T08:00'], ['problem_attended_at' => '2026-01-01T10:00', 'repair_started_at' => '2026-01-01T09:30'], ['repair_started_at' => '2026-01-01T08:00'], ['status' => 'Completed', 'repair_started_at' => '2026-01-01T11:00', 'actual_completion_date' => '2026-01-01T10:00']] as $changes) {
            $this->post('/maintenance', $this->maintenance($asset, $changes))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('maintenance_records', 0);
        $this->post('/maintenance', $this->maintenance($asset))->assertSessionHasNoErrors();
        $record = MaintenanceRecord::sole();
        $data = $this->maintenance($asset, ['problem_attended_at' => '2026-01-01T10:00', 'repair_started_at' => '2026-01-01T11:00']);
        unset($data['asset_id']);
        $this->put('/maintenance/'.$record->id, $data)->assertSessionHasNoErrors();
        unset($data['repair_started_at']);
        $data['problem_attended_at'] = '2026-01-01T12:00';
        $this->put('/maintenance/'.$record->id, $data)->assertSessionHasErrors('repair_started_at');
    }

    public function test_legacy_date_only_records_remain_readable_without_invented_durations(): void
    {
        $this->loginAs();
        $asset = $this->equipment();
        $record = MaintenanceRecord::create($this->maintenance($asset, ['date_reported' => '2024-01-01', 'actual_completion_date' => '2024-01-02', 'status' => 'Completed']) + ['created_by' => auth()->id()]);
        $this->assertNull($record->response_time);
        $this->assertNull($record->repair_duration);
        $this->get('/reports?type=maintenance')->assertOk()->assertDontSee('time not recorded')->assertSee('01/01/2024');
        $this->get('/assets/'.$asset->id)->assertOk();
    }

    public function test_migration_preserves_business_rows_and_deactivates_only_retired_accounts(): void
    {
        $admin = $this->loginAs();
        $asset = $this->equipment();
        $legacy = User::factory()->create(['role' => 'Department Staff', 'is_active' => true]);
        $log = ActivityLog::create(['user_id' => $legacy->id, 'asset_id' => $asset->id, 'action' => 'Historical', 'description' => 'Preserved']);
        DB::statement('DROP TABLE asset_categories');
        Schema::table('maintenance_records', fn ($t) => $t->dropColumn(['problem_attended_at', 'repair_started_at', 'reported_time_recorded', 'completion_time_recorded']));
        $migration = require database_path('migrations/2026_09_28_000001_apply_client_feedback.php');
        $migration->up();
        $this->assertFalse($legacy->fresh()->is_active);
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['id' => $log->id, 'user_id' => $legacy->id]);
        $this->assertDatabaseHas('assets',['id' => $asset->id, 'asset_tag' => $asset->asset_tag]);
    }
}
