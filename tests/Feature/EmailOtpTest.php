<?php

namespace Tests\Feature;

use App\Mail\LoginVerificationCode;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\EmailOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp']);
        Mail::fake();
    }

    private function login(string $role = 'ICT Administrator'): User
    {
        $u = User::factory()->create(['role' => $role, 'is_active' => true]);
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect('/mfa');

        return $u;
    }

    private function code(): string
    {
        return Mail::sent(LoginVerificationCode::class)->last()->code;
    }

    public function test_both_privileged_roles_receive_hashed_six_digit_challenge(): void
    {
        foreach (['ICT Administrator', 'ICT Staff / Technician'] as $role) {
            $u = $this->login($role);
            $this->assertGuest();
            Mail::assertSent(LoginVerificationCode::class, fn ($m) => $m->hasTo($u->email));
            $code = $this->code();
            $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
            $c = DB::table('email_otp_challenges')->where('user_id', $u->id)->first();
            $this->assertTrue(Hash::check($code, $c->code_hash));
            $this->assertNotSame($code, $c->code_hash);
            $this->get('/mfa')->assertOk()->assertSee('Verify Your Identity')->assertDontSee($code)->assertDontSee('setup key')->assertDontSee('<svg', false);
            $this->assertStringNotContainsString($code, json_encode(session()->all()));
            $this->assertStringNotContainsString($code, ActivityLog::all()->toJson());
            $this->post('/logout');
        }
    }

    public function test_non_privileged_roles_do_not_require_otp(): void
    {
        foreach (['Management (HOD)'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($u);
            $this->post('/logout');
        }Mail::assertNothingSent();
    }

    public function test_correct_code_logs_in_and_cannot_be_reused(): void
    {
        $u = $this->login();
        $code = $this->code();
        $id = session('email_otp_id');
        $this->post('/mfa', ['code' => $code])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->assertDatabaseCount('email_otp_challenges', 0);
        $this->get('/dashboard')->assertOk();
        $this->assertFalse(app(EmailOtp::class)->verify($u, $id, $code));
        $this->post('/mfa', ['code' => $code])->assertForbidden();
    }

    public function test_incorrect_code_and_expiry_are_rejected(): void
    {
        $this->login();
        $code = $this->code();
        $wrong = $code === '000000' ? '000001' : '000000';
        $this->post('/mfa', ['code' => $wrong])->assertSessionHasErrors('code');
        $this->assertNull(session('_old_input.code'));
        $this->assertGuest();
        $this->travel(5)->minutes();
        $this->post('/mfa', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('email_otp_challenges', 0);
        $this->assertGuest();
    }

    public function test_resend_cooldown_replaces_code_and_resets_expiry(): void
    {
        $u = $this->login();
        $old = $this->code();
        $this->post('/mfa/resend')->assertSessionHasErrors('code');
        Mail::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->post('/mfa/resend')->assertRedirect('/mfa')->assertSessionHas('success');
        $new = $this->code();
        $this->assertNotSame($old, $new);
        $c = DB::table('email_otp_challenges')->where('user_id', $u->id)->first();
        $this->assertEquals(now()->addMinutes(5)->format('Y-m-d H:i:s'), $c->expires_at);
        $this->post('/mfa', ['code' => $old])->assertSessionHasErrors('code');
        $this->post('/mfa', ['code' => $new])->assertRedirect('/dashboard');
    }

    public function test_verification_is_limited_and_resend_does_not_bypass_account_limit(): void
    {
        $this->login();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/mfa', ['code' => 'invalid'])->assertSessionHasErrors('code');
        }$this->post('/mfa', ['code' => $this->code()])->assertSessionHasErrors(['code' => 'Too many verification attempts. Wait one minute and request a new code.']);
        $this->assertSame(5, DB::table('email_otp_challenges')->first()->attempts);
        $this->travel(61)->seconds();
        $this->post('/mfa', ['code' => $this->code()])->assertSessionHasErrors('code');
        $this->post('/mfa/resend')->assertRedirect('/mfa');
        $this->post('/mfa', ['code' => $this->code()])->assertRedirect('/dashboard');
    }

    public function test_pending_login_cannot_access_any_protected_module(): void
    {
        $this->login();
        foreach (['/dashboard', '/assets', '/allocations', '/inventory', '/maintenance', '/reports', '/users', '/profile'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }$this->assertGuest();
    }

    public function test_old_totp_session_does_not_bypass_email_mfa(): void
    {
        $u = User::factory()->create(['role' => 'ICT Administrator']);
        $this->actingAs($u)->withSession(['mfa_user' => $u->id])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_cancellation_deletes_challenge_and_does_not_reset_send_cooldown(): void
    {
        $u = $this->login();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertDatabaseCount('email_otp_challenges', 0);
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('code');
        Mail::assertSentCount(1);
        $this->assertGuest();
    }

    public function test_challenge_is_bound_to_session_and_current_credentials(): void
    {
        $u = $this->login();
        $code = $this->code();
        $id = session('email_otp_id');
        $this->withSession(['email_otp_id' => 'different-session'])->post('/mfa', ['code' => $code])->assertSessionHasErrors('code');
        $this->withSession(['email_otp_id' => $id]);
        $u->update(['email' => 'changed@example.test']);
        $this->post('/mfa', ['code' => $code])->assertForbidden();
        $this->assertGuest();
    }

    public function test_disabled_user_cannot_verify(): void
    {
        $u = $this->login();
        $code = $this->code();
        $u->update(['is_active' => false]);
        $this->post('/mfa', ['code' => $code])->assertNotFound();
        $this->assertGuest();
    }

    public function test_log_mailer_is_refused_without_sending_or_exposing_code(): void
    {
        config(['mail.default' => 'log']);
        Log::spy();
        $u = User::factory()->create(['role' => 'ICT Administrator']);
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('code');
        Mail::assertNothingSent();
        Log::shouldNotHaveReceived('debug');
        $this->assertDatabaseCount('email_otp_challenges', 0);
        $this->assertGuest();
    }

    public function test_delivery_failure_is_safe_and_leaves_no_challenge(): void
    {
        Mail::shouldReceive('mailer')->with('smtp')->andReturnSelf();
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('Sensitive transport details'));
        $u = User::factory()->create(['role' => 'ICT Administrator']);
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors(['code' => 'Verification email could not be sent. Please try again later or contact the ICT Administrator.']);
        $this->assertDatabaseCount('email_otp_challenges', 0);
        $this->assertGuest();
    }

    public function test_email_template_has_expected_content(): void
    {
        $this->login();
        $mail = Mail::sent(LoginVerificationCode::class)->last();
        $html = $mail->render();
        $this->assertStringContainsString('This code will expire in 5 minutes.',$html);
        $this->assertStringContainsString($mail->code,$html);
        $this->assertSame('AssetKeeper Login Verification Code',$mail->subject);
    }
}
