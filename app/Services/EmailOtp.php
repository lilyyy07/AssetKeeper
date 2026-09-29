<?php

namespace App\Services;

use App\Mail\LoginVerificationCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class EmailOtp
{
    public const SESSION_KEY = 'email_mfa_user';

    public function issue(User $user, bool $resend = false): string
    {
        if (config('mail.default') !== 'smtp') {
            throw ValidationException::withMessages(['code' => 'Verification email could not be sent. Ask the ICT Administrator to configure SMTP.']);
        }

        return DB::transaction(function () use ($user, $resend) {
            $user = User::where('is_active', true)->lockForUpdate()->findOrFail($user->id);
            if (RateLimiter::tooManyAttempts('email-otp:send:'.$user->id, 1)) {
                throw ValidationException::withMessages(['code' => 'Please wait 60 seconds between verification emails.']);
            }
            $old = DB::table('email_otp_challenges')->where('user_id', $user->id)->first();
            if ($old && now()->lt(Carbon::parse($old->sent_at)->addSeconds(60))) {
                throw ValidationException::withMessages(['code' => 'Please wait 60 seconds between verification emails.']);
            }
            // Ensure a replacement differs from the previous code, including rare RNG collisions.
            do {
                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            } while ($old && Hash::check($code, $old->code_hash));
            $id = bin2hex(random_bytes(32));
            DB::table('email_otp_challenges')->updateOrInsert(['user_id' => $user->id], ['challenge_id' => $id, 'code_hash' => Hash::make($code), 'credential_hash' => $this->credentialHash($user), 'expires_at' => now()->addMinutes(5), 'sent_at' => now(), 'attempts' => 0]);
            try {
                Mail::mailer('smtp')->to($user->email)->send(new LoginVerificationCode($code));
            } catch (\Throwable $e) {
                throw ValidationException::withMessages(['code' => 'Verification email could not be sent. Please try again later or contact the ICT Administrator.']);
            }
            RateLimiter::hit('email-otp:send:'.$user->id, 60);
            Audit::record($resend ? 'OTP resent' : 'OTP challenge generated', null, [], [], $user->id);

            return $id;
        });
    }

    public function credentialHash(User $user): string
    {
        return hash('sha256', $user->email.'|'.$user->getRawOriginal('password'));
    }

    public function verify(User $user, string $id, string $code): bool
    {
        $key = 'email-otp:verify:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many verification attempts. Wait one minute and request a new code.']);
        }
        RateLimiter::hit($key, 60);
        $valid = DB::transaction(function () use ($user, $id, $code) {
            $user = User::where('is_active', true)->lockForUpdate()->findOrFail($user->id);
            $query = DB::table('email_otp_challenges')->where('user_id', $user->id)->where('challenge_id', $id);
            $challenge = $query->first();
            if (! $challenge) {
                return false;
            }
            if (now()->gte(Carbon::parse($challenge->expires_at)) || ! hash_equals($challenge->credential_hash, $this->credentialHash($user))) {
                $query->delete();

                return false;
            }
            if ($challenge->attempts >= 5) {
                return false;
            }
            // Return instead of throwing here so failed-attempt increments commit.
            $query->increment('attempts');
            if (! preg_match('/\A[0-9]{6}\z/', $code) || ! Hash::check($code, $challenge->code_hash)) {
                return false;
            }
            $query->delete();

            return true;
        });
        Audit::record($valid ? 'MFA verification successful' : 'MFA verification failed', null, [], [], $user->id);
        if ($valid) {
            RateLimiter::clear($key);
        }

        return $valid;
    }

    public function cancel(?int $userId, ?string $id): void
    {
        if ($userId && $id) {
            DB::table('email_otp_challenges')->where('user_id',$userId)->where('challenge_id',$id)->delete();
        }
    }
}
