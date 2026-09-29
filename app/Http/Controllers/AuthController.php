<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use App\Services\EmailOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $r, EmailOtp $otp)
    {
        $data = $r->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:255']);
        $key = 'login:'.hash('sha256', strtolower($data['email']).'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active || ! in_array($user->role, User::ROLES, true)) {
            RateLimiter::hit($key, 60);
            Audit::record('Failed login');
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid or the account is unavailable.']);
        }
        RateLimiter::clear($key);
        $otp->cancel($r->session()->get('pending_user'), $r->session()->get('email_otp_id'));
        $r->session()->forget(['pending_user', 'pending_since', 'pending_remember', 'email_otp_id', 'pending_credentials', 'mfa_user', 'setup_secret', EmailOtp::SESSION_KEY]);
        $r->session()->regenerate();
        if ($user->requiresEmailOtp()) {
            $id = $otp->issue($user);
            $r->session()->put(['pending_user' => $user->id, 'pending_since' => now()->timestamp, 'pending_remember' => $r->boolean('remember'), 'pending_credentials' => $otp->credentialHash($user), 'email_otp_id' => $id]);

            return redirect()->route('mfa');
        }
        Auth::login($user, $r->boolean('remember'));
        $r->session()->regenerate();
        Audit::record('Successful login');

        return redirect()->intended(route('dashboard'));
    }

    private function pending(Request $r, EmailOtp $otp): User
    {
        abort_unless($r->session()->has('pending_user') && now()->timestamp - $r->session()->get('pending_since', 0) < 900, 403, 'Sign in again to continue.');
        $user = User::where('is_active', true)->findOrFail($r->session()->get('pending_user'));
        abort_unless($user->requiresEmailOtp() && hash_equals($r->session()->get('pending_credentials', ''), $otp->credentialHash($user)), 403, 'Sign in again to continue.');

        return $user;
    }

    public function mfa(Request $r, EmailOtp $otp)
    {
        $user = $this->pending($r, $otp);
        [$local,$domain] = explode('@', $user->email, 2);
        $maskedEmail = mb_substr($local, 0, 1).'***@'.$domain;

        return response()->view('auth.mfa', compact('maskedEmail'))->header('Cache-Control', 'no-store');
    }

    public function resend(Request $r, EmailOtp $otp)
    {
        $user = $this->pending($r, $otp);
        $id = $otp->issue($user, true);
        $r->session()->put('email_otp_id', $id);

        return redirect()->route('mfa')->with('success', 'A new verification code has been sent to your email.');
    }

    public function verify(Request $r, EmailOtp $otp)
    {
        $user = $this->pending($r, $otp);
        $code = $r->input('code');
        if (! $otp->verify($user, $r->session()->get('email_otp_id', ''), is_string($code) ? $code : '')) {
            throw ValidationException::withMessages(['code' => 'Invalid, expired or already used verification code. Request a new code if needed.']);
        }
        Auth::login($user, $r->session()->pull('pending_remember', false));
        $r->session()->forget(['pending_user', 'pending_since', 'email_otp_id', 'pending_credentials']);
        $r->session()->regenerate();
        $r->session()->put([EmailOtp::SESSION_KEY => $user->id, 'last_activity' => time()]);
        Audit::record('Successful email MFA login');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $r, EmailOtp $otp)
    {
        $otp->cancel($r->session()->get('pending_user'), $r->session()->get('email_otp_id'));
        if (Auth::check()) {
            Audit::record('Logout');
        }Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }
}
