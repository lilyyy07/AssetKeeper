<?php

namespace Tests\Feature;

use App\Mail\LoginVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DedicatedUatAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['uat.enabled' => false, 'uat.otp_bypass_email' => 'assetkeeper.uat@uptm.edu.my', 'mail.default' => 'smtp']);
        Mail::fake();
    }

    public function test_dedicated_account_logs_in_without_otp_and_has_administrator_access(): void
    {
        $user = User::factory()->create(['email' => config('uat.otp_bypass_email'), 'role' => User::ROLES[0], 'is_active' => true]);
        $user->forceFill(['is_uat_account' => true])->save();
        $this->assertTrue(Hash::check('password', $user->password));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('email_otp_challenges', 0);
        foreach (['/dashboard', '/assets', '/allocations', '/inventory', '/maintenance', '/reports', '/users', '/profile'] as $path) {
            $this->get($path)->assertOk();
        }
        foreach (['admin', 'operate', 'reports'] as $gate) {
            $this->assertTrue($user->can($gate));
        }
        $this->post('/asset-categories', ['name' => 'UAT category', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->post('/departments', ['name' => 'UAT department', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('departments', ['name' => 'UAT department']);
    }

    public function test_other_privileged_accounts_still_require_otp_even_if_marked(): void
    {
        foreach (array_slice(User::ROLES, 0, 2) as $role) {
            foreach ([false, true] as $marked) {
                $user = User::factory()->create(['role' => $role, 'is_active' => true]);
                $user->forceFill(['is_uat_account' => $marked])->save();
                $this->assertTrue($user->requiresEmailOtp());
                $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/mfa');
                Mail::assertSent(LoginVerificationCode::class, fn ($mail) => $mail->hasTo($user->email));
                $this->assertGuest();
                $this->post('/logout');
            }
        }
    }

    public function test_email_alone_cannot_bypass_otp_and_removing_exception_ends_access(): void
    {
        $user = User::factory()->create(['email' => config('uat.otp_bypass_email'), 'role' => User::ROLES[0], 'is_active' => true]);
        $this->assertTrue($user->requiresEmailOtp());
        $user->forceFill(['is_uat_account' => true])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        config(['uat.otp_bypass_email' => null]);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}
