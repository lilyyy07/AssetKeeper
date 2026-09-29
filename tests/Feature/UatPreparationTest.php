<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Services\EmailOtp;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class UatPreparationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'ICT Administrator', bool $uat = false): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $user->forceFill(['is_uat_account' => $uat])->save();

        return $user;
    }

    private function fixture(User $user, string $status = 'Pending'): MaintenanceRecord
    {
        $asset = Asset::create(['asset_tag' => 'M-'.Str::random(6), 'name' => 'Maintenance fixture', 'category' => 'Laptop', 'location' => 'ICT', 'condition' => 'Good', 'status' => 'Available', 'created_by' => $user->id]);

        return MaintenanceRecord::create(['asset_id' => $asset->id, 'issue' => 'Intermittent fault', 'type' => 'Hardware', 'priority' => 'High', 'date_reported' => '2026-01-01', 'status' => $status, 'actual_completion_date' => $status === 'Completed' ? '2026-01-02' : null, 'created_by' => $user->id]);
    }

    public function test_active_and_completed_maintenance_have_real_view_pages_and_completed_remains_read_only(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withSession([EmailOtp::SESSION_KEY => $user->id]);
        $active = $this->fixture($user);
        $done = $this->fixture($user, 'Completed');
        $this->get('/maintenance/'.$active->id)->assertOk()->assertSee('Edit / Status')->assertSee('Intermittent fault')->assertSee('Response Time')->assertDontSee('Not enough timing information');
        $this->get('/maintenance/'.$done->id)->assertOk()->assertDontSee('Edit / Status')->assertSee('02/01/2026')->assertDontSee('time not recorded');
        $this->get('/maintenance')->assertOk()->assertSee(route('maintenance.show', $done), false)->assertSee('View')->assertDontSee('Response Time')->assertDontSee('Not recorded');
        $data = ['issue' => 'Updated fault', 'type' => 'Hardware', 'priority' => 'High', 'date_reported' => '2026-01-01', 'status' => 'In Progress', 'problem_attended_at' => '2026-01-01T10:00'];
        $this->put('/maintenance/'.$active->id, $data)->assertSessionHasNoErrors();
        $this->assertSame('In Progress', $active->fresh()->status);
        $this->put('/maintenance/'.$done->id, $data)->assertSessionHasErrors('status');
        $this->assertSame('Completed', $done->fresh()->status);
        $this->assertNull($done->response_time);
        $this->assertNull($done->repair_duration);
        $management = $this->user('Management (HOD)');
        $this->actingAs($management)->get('/maintenance/'.$active->id)->assertForbidden();
        $this->post('/logout');
        $this->get('/maintenance/'.$active->id)->assertRedirect('/login');
    }

    public function test_otp_exception_requires_both_switch_and_account_flag_for_each_privileged_role(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        foreach (array_slice(User::ROLES, 0, 2) as $role) {
            foreach ([false, true] as $mode) {
                foreach ([false, true] as $flag) {
                    config(['uat.enabled' => $mode]);
                    $user = $this->user($role, $flag);
                    $password = Str::password(24);
                    $user->update(['password' => $password]);
                    $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect($mode && $flag ? '/dashboard' : '/mfa');
                    if ($mode && $flag) {
                        $this->assertAuthenticatedAs($user);
                        $this->assertNull(session(EmailOtp::SESSION_KEY));
                        $this->assertDatabaseMissing('email_otp_challenges', ['user_id' => $user->id]);
                    } else {
                        $this->assertGuest();
                        $this->assertDatabaseHas('email_otp_challenges', ['user_id' => $user->id]);
                    }
                    $this->post('/logout');
                }
            }
        }
        Mail::assertSentCount(6);
    }

    public function test_uat_requires_correct_password_and_login_throttle_remains(): void
    {
        config(['uat.enabled' => true]);
        $user = $this->user(uat: true);
        $password = Str::password(24);
        $user->update(['password' => $password]);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => Str::random(30)])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('activity_logs', 5);
    }

    public function test_disabling_uat_mode_or_account_flag_ends_existing_bypass_session(): void
    {
        foreach (['mode', 'flag', 'active'] as $change) {
            config(['uat.enabled' => true]);
            $user = $this->user(uat: true);
            $this->actingAs($user)->withSession(['last_activity' => time()])->get('/dashboard')->assertOk();
            if ($change === 'mode') {
                config(['uat.enabled' => false]);
            } else {
                $user->forceFill([$change === 'flag' ? 'is_uat_account' : 'is_active' => false])->save();
            }
            $this->get('/dashboard')->assertRedirect('/login');
            $this->assertGuest();
        }
    }

    public function test_uat_accounts_keep_role_boundaries_and_audit_attribution(): void
    {
        config(['uat.enabled' => true]);
        foreach (User::ROLES as $role) {
            $user = $this->user($role, true);
            $password = Str::password(24);
            $user->update(['password' => $password]);
            $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/dashboard');
            $this->get('/reports')->assertOk();
            $this->get('/inventory')->assertOk();
            $this->get('/users')->assertStatus($user->isAdmin() ? 200 : 403);
            $this->post('/asset-categories', ['name' => 'UAT-'.$user->id, 'is_active' => 1])->assertStatus($user->isAdmin() ? 302 : 403);
            $this->post('/assets/import/preview')->assertStatus($user->isAdmin() ? 302 : 403);
            $this->post('/departments', ['name' => 'UAT-'.$user->id, 'is_active' => 1])->assertStatus($user->isAdmin() ? 302 : 403);
            $this->post('/users', [])->assertStatus($user->isAdmin() ? 302 : 403);
            $this->get('/allocations')->assertStatus($user->isOperator() ? 200 : 403);
            $this->post('/maintenance', [])->assertStatus($user->isOperator() ? 302 : 403);
            $this->get('/assets')->assertOk();
            $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'description' => 'UAT: Successful login']);
            $this->post('/logout');
        }
    }

    public function test_web_requests_cannot_mark_normal_accounts_as_uat(): void
    {
        config(['uat.enabled' => true]);
        $admin = $this->user();
        $target = $this->user('Management (HOD)');
        $this->actingAs($admin)->withSession([EmailOtp::SESSION_KEY => $admin->id]);
        $this->put('/users/'.$target->id, ['name' => $target->name, 'email' => $target->email, 'role' => $target->role, 'is_active' => 1, 'is_uat_account' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($target->fresh()->is_uat_account);
        $this->put('/profile', ['name' => $admin->name, 'email' => $admin->email, 'is_uat_account' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->is_uat_account);
        $this->assertTrue($admin->requiresEmailOtp());
    }

    public function test_cli_provisions_resets_and_deactivates_without_printing_or_logging_credentials(): void
    {
        $this->user();
        $password = Str::password(24);
        $args = ['email' => 'uat-fixture@example.test', '--role' => 'ICT Staff / Technician', '--name' => 'UAT Technician'];
        $this->artisan('assetkeeper:uat-account', $args)->expectsQuestion('New UAT password', $password)->expectsQuestion('Confirm UAT password', $password)->assertSuccessful();
        $user = User::where('email', $args['email'])->sole();
        $id = $user->id;
        $this->assertTrue($user->is_uat_account);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertStringNotContainsString($password, ActivityLog::all()->toJson());
        $this->assertStringNotContainsString($user->password, ActivityLog::all()->toJson());
        $replacement = Str::password(24);
        $this->artisan('assetkeeper:uat-account', $args)->expectsQuestion('New UAT password', $replacement)->expectsQuestion('Confirm UAT password', $replacement)->assertSuccessful();
        $this->assertSame($id, $user->fresh()->id);
        $this->assertTrue(Hash::check($replacement, $user->fresh()->password));
        $record = $this->fixture($user);
        $logs = ActivityLog::count();
        $this->artisan('assetkeeper:uat-account', ['email' => $args['email'], '--deactivate' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseHas('maintenance_records', ['id' => $record->id, 'created_by' => $id]);
        $this->assertGreaterThan($logs, ActivityLog::count());
    }

    public function test_cli_refuses_to_convert_normal_accounts_or_remove_last_administrator(): void
    {
        $normal = $this->user();
        $this->artisan('assetkeeper:uat-account', ['email' => $normal->email, '--deactivate' => true])->assertFailed();
        $this->assertTrue($normal->fresh()->is_active);
        $this->assertFalse($normal->fresh()->is_uat_account);
        $normal->update(['is_active' => false]);
        $uat = $this->user(uat: true);
        $this->artisan('assetkeeper:uat-account', ['email' => $uat->email, '--deactivate' => true])->assertFailed();
        $this->assertTrue($uat->fresh()->is_active);
    }

    public function test_uat_inactivity_timeout_and_csrf_are_not_bypassed(): void
    {
        config(['uat.enabled' => true]);
        $user = $this->user(uat: true);
        $this->actingAs($user)->withSession(['last_activity' => time() - 1801])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($user)->post('/asset-categories',['name' => 'Denied', 'is_active' => 1])->assertStatus(419);
    }
}
