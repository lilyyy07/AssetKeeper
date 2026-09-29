# Email OTP MFA replacement

## Implementation and files

Authentication uses email OTP instead of authenticator TOTP. Asset QR generation and signed verification are unchanged. Operational modules and RBAC gates are unchanged.

Created:
- `app/Services/EmailOtp.php`: hashed challenges, secure generation, delivery, resend/verify limits, safe audit events.
- `app/Mail/LoginVerificationCode.php` and `resources/views/emails/login-code.blade.php`: synchronous Laravel SMTP email.
- `database/migrations/2026_09_20_000002_add_email_otp_challenges.php`.
- `tests/Feature/EmailOtpTest.php`.

Modified:
- `app/Http/Controllers/AuthController.php`, `app/Http/Middleware/AccountAccess.php`, `app/Models/User.php`.
- `config/auth.php`, `config/mail.php`, `routes/web.php`.
- `resources/views/auth/mfa.blade.php`, the MFA text in `resources/views/profile.blade.php`.
- `.env` (sender display name and optional encryption setting only; existing credentials/mail transport preserved), `.env.example`.
- `composer.json`, `composer.lock`, authentication/seeder integration tests, README and MFA documentation.

Removed: recovery-code Blade page, Google2FA package and its unused constant-time-encoding dependency, obsolete local TOTP browser QA script. BaconQrCode remains for asset labels.

## Database changes

The applied additive migration creates `email_otp_challenges`, with one row per user: random challenge identifier, code hash, credential fingerprint, expiry/send dates and attempt count. No plaintext OTP is persisted. Failed-attempt updates commit; successful or expired verification deletes the challenge. A replacement overwrites the previous challenge.

The same migration clears `mfa_secret`, `mfa_confirmed_at`, `recovery_codes` and `mfa_last_timestamp`. Those legacy columns remain nullable for safe schema retirement; no authentication code reads them. The original migration still describes their historical creation. They cannot enable or bypass MFA. Migration rollback removes only the new challenge table; it cannot restore cleared authenticator enrollment. No unrelated table was dropped, no user was removed and no migrate:fresh command was run.

## Authentication rules

- `config/auth.php` -> `email_otp_roles` centralizes mandatory roles: ICT Administrator and ICT Staff / Technician.
- Correct password sends a cryptographically random six-digit OTP and creates pending session state; it does not authenticate the user yet.
- OTP expires after exactly five minutes, is single-use, and is bound to the pending session's challenge identifier and current email/password fingerprint.
- Maximum five attempts per challenge and five attempts per account per minute. Malformed codes count as attempts. Exhausted challenges require a resend after cooldown.
- One successful send per account per 60 seconds, including repeated password login, logout and resend. Resend generates a different code, resets expiry and invalidates the previous identifier/code. It does not clear the account verification limiter.
- Pending password proof lasts 15 minutes. After that, sign in again.
- Success deletes the challenge, logs in, regenerates the session and sets the email-MFA session marker. Old TOTP session markers are ignored.
- Guest pending sessions cannot access any application module. Authenticated/remembered privileged sessions without the new verification marker are signed out and asked to repeat password/email verification.
- Management retains password-only login. Department Staff is retired and cannot log in. They have no authenticator setup links.
- OTP email contains only the verification code, expiry guidance and branding. OTPs are absent from audit events, web page source, flashed input, URLs and application logs. Mail transport exceptions are replaced with a fixed safe error; exception contents are not logged by this service.

## Configure SMTP locally

Real delivery has NOT been tested. The current `.env` still selects `MAIL_MAILER=log`, has no SMTP credentials and uses a placeholder sender. Email OTP deliberately rejects this configuration instead of logging a code or granting access.

Set the following in your private `.env`, using your SMTP provider's actual values:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.example
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD="your-smtp-password-or-app-password"
MAIL_ENCRYPTION=tls
MAIL_SCHEME=null
MAIL_FROM_ADDRESS=your-verified-sender@example.com
MAIL_FROM_NAME="AssetKeeper"
```

These are placeholders, not working credentials. Keep credentials out of source control. `MAIL_ENCRYPTION=tls` requires STARTTLS; for implicit TLS use port 465 and `MAIL_ENCRYPTION=ssl`. Leave MAIL_SCHEME null unless your provider specifically requires overriding it with smtp/smtps. Remove a stale MAIL_URL if present because it may override individual SMTP fields. Standard Laravel mail configuration remains in use; no credentials are hard-coded.

For a local SMTP capture inbox already installed on your machine, use its host/port (often 127.0.0.1:1025), null username/password and `MAIL_ENCRYPTION=null`. This is SMTP capture, not the Laravel log mailer. No capture server was installed or started by this change.

Run:

```powershell
php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000
```

Restart an already-running server after configuration changes if necessary. Migrations have already been applied here; on another checkout run `php artisan migrate`.

## Test real email delivery

1. Configure SMTP and a sender accepted by the provider, then clear configuration cache.
2. Use a privileged test user with a registered inbox you control. The seeded `@assetkeeper.test` addresses are fictional and will not receive internet email. They can be used with a local capture inbox.
3. If SMTP is configured but you need an initial real-address administrator, create a separate local test account via `php artisan tinker`:

   ```php
   $department = App\Models\Department::where('name', 'ICT Department')->firstOrFail();
   App\Models\User::updateOrCreate(
       ['email' => 'your-real-test-inbox@example.com'],
       ['name' => 'Email MFA Test Administrator', 'role' => 'ICT Administrator', 'department_id' => $department->id, 'is_active' => true, 'password' => Illuminate\Support\Facades\Hash::make('DevOnly!2026AK')]
   );
   ```

   Replace the placeholder address before running. This uses a development-only password and creates/updates only the chosen test account. The agent has NOT executed this example or changed seeded email addresses.
4. Open a private browser window at http://127.0.0.1:8000/login and enter that account's credentials.
5. Confirm the Verify Your Identity screen shows a masked email, and check the inbox/spam folder for the AssetKeeper Login Verification Code email.
6. Before entering the code, manually visit `/dashboard` or `/assets`; access must be refused/redirected to login. Return to `/mfa` to complete the still-pending challenge.
7. Enter the received code within five minutes. Verify the dashboard opens and permissions match the account's role.
8. On another login, wait 60 seconds and select Resend OTP. The previous code must fail; the replacement must work. Check invalid, expired and reused codes are rejected and repeated guesses are blocked.
9. Confirm Management login without an OTP and Department Staff login is rejected, and the signed Asset QR still opens only for an authorized user.

## Verification results

- `php artisan migrate --force`: applied safely to local MySQL, after using DATETIME columns for compatibility with this server's timestamp defaults.
- `php artisan test`: 35 passed, 359 assertions. Mail::fake() covers generation, recipients, hashing, UI, role requirements, success/failure, expiry, single use, resend, throttling, session binding, cancellation, inactive accounts, legacy session rejection, unsafe mailer refusal and safe delivery errors.
- Existing password throttling, CSRF, role gates, department scoping and signed Asset QR tests still pass.
- MFA routes: GET/POST `/mfa`, POST `/mfa/resend`. No authenticator enrollment/recovery route exists.
- Blade compilation passed. Real SMTP delivery remains unverified until you supply and test SMTP configuration.

Earlier screenshot/test reports in docs and storage describe the previous development milestone; their TOTP results are historical, not current email-delivery evidence.

See [final client feedback](CLIENT_FEEDBACK.md) for current roles, categories, import, and maintenance timing.
