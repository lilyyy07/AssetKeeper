# Final account and branding cleanup

Completed on 23 September 2026 in the existing AssetKeeper Laravel project.

## Account

The local development account dalilynurnisasport@gmail.com is configured as Lily, Active, ICT Administrator, ICT Department. Laravel Hash::check passed against the supplied temporary password. Email OTP is required. No password or SMTP values are recorded here.

Use `php artisan assetkeeper:provision-development-admin` for a future intentional reset; the command prompts privately for a temporary password and is restricted to local/testing environments. It is idempotent by email and does not run the existing role-account seeder. The compatible PHP runtime used here is `C:\xampp-new\php\php.exe`.

**Real Gmail delivery was not verified.** OTP transport was faked in automated tests. The OTP screenshot fixture rendered the Blade view with a masked address; it did not represent an actual email delivery or authenticated session.

## Preserved data and security

No database rebuild, migration reset, table drop or business record deletion was performed. Eight assets, one allocation and one maintenance record were preserved. Existing role account credentials, emails, roles, departments and status were unchanged; only the four exact seeded display names were cleaned.

Known seeded users now display fictional names: Alex Morgan, Jordan Lee, Taylor Reed and Casey Brooks. These do not identify real UPTM staff. Asset tags are UPTM-ICT-001 through UPTM-ICT-008; seeded serial labels and supplier wording were updated. The allocation recipient is the fictional Morgan Ellis, and the maintenance issue reads Network port fault.

Original audit rows remain immutable and unchanged. New cleanup entries were appended. Exact legacy fixture labels are normalized only for display in the dashboard and asset history; original stored audit values remain available. No broad text replacement was applied to business records.

Authentication logic, OTP rules, middleware, CSRF, signed QR generation and RBAC were preserved. No public registration routes or links were introduced. Account creation remains administrator-only; initial password labels now explain private delivery and changing the password in Profile.

## Visual changes

Login and OTP share responsive authentication styling: 55/45 desktop split, navy/purple gradient, restrained ICT illustration, white form card, focus states, field icons, masked email, six-digit input, existing resend/cancel actions, and dynamic footer year. Login includes an accessible show/hide password control.

The original device-and-shield SVG is referenced by one reusable Blade component with sizes and light/dark wordmarks. It is used on Login, OTP, desktop/sidebar mobile navigation, reports and the printable QR label. The same SVG is the favicon. Branding remains outside the QR itself.

## Verification

- `php artisan test`: **39 passed, 411 assertions, zero failures**.
- Existing tests retained. Added account setup idempotency, record/credential/audit preservation, production-environment rejection, and administrator provisioning/edit/role/department/activation tests.
- OTP tests cover both privileged roles, six digits, hashed storage, expiry, one-time use, resend replacement/cooldown, five-attempt limit, blocked modules, cancellation, disabled accounts and safe transport errors.
- Core regression coverage includes dashboard/pages, assets, allocations/returns, maintenance synchronization, inventory filtering, reports/CSV, profile/password, audit history, department isolation, signed QR, inactivity and CSRF.
- `php artisan view:cache` passed. `php artisan route:list --except-vendor` listed 28 routes and no public registration route.
- Login and new SVG/CSS/JS URLs returned HTTP 200.
- Isolated Chrome checks at desktop, tablet and mobile widths (1440, 820, 390 and 320 pixels) found no horizontal overflow, loaded logo assets and a working password toggle. Desktop Login and mobile Login/OTP screenshots were visually inspected. OTP uses a non-sensitive rendered fixture.
- Changed source was scanned against the configured SMTP password, supplied development password and stored password hashes: no matches. All stored user passwords were recognized password hashes. Current user/asset/allocation/maintenance display fields contained no remaining targeted prototype wording.

## Created files

- app/Console/Commands/ProvisionDevelopmentAdmin.php
- app/Services/SeededDisplayContent.php
- database/seeders/PresentationCleanupSeeder.php
- resources/views/components/brand-logo.blade.php
- public/brand-mark.svg
- public/css/auth.css
- public/js/auth.js
- tests/Feature/FinalCleanupTest.php
- docs/FINAL_ACCOUNT_BRANDING.md

## Modified files

- database/seeders/UserSeeder.php
- database/seeders/DatabaseSeeder.php
- resources/views/auth/layout.blade.php
- resources/views/auth/login.blade.php
- resources/views/auth/mfa.blade.php
- resources/views/layouts/app.blade.php
- resources/views/partials/sidebar.blade.php
- resources/views/partials/sidebar-content.blade.php
- resources/views/partials/user-form.blade.php
- resources/views/reports.blade.php
- resources/views/assets/show.blade.php
- resources/views/dashboard.blade.php
- public/css/assetkeeper.css

Temporary visual QA artifacts are in storage/app/branding-qa. Laravel also regenerated its normal compiled-view/test cache artifacts. The .env file was not modified.

## Manual FYP checks

1. Sign in as Lily using the supplied development password. Confirm an actual Gmail OTP arrives (including checking spam) and successfully opens the dashboard. Do not share the code.
2. Before verifying, try protected module URLs. Check wrong/expired codes, the 60-second resend cooldown and rejection of the previous code after resend.
3. Change the temporary password through Profile after testing. Exercise Add/Edit User and Active/Inactive changes, and confirm each non-admin role cannot access User Management.
4. Walk through Add/View/Edit/Search assets, allocate/return, inventory filters and maintenance completion with your intended FYP records.
5. Print each report and an asset label; scan the printed QR with a phone. Check physical scan reliability, report pagination and browser print output.
6. Check Login/OTP and mobile navigation on your actual phone. Review desktop sidebar/report branding and all five reports/CSV exports.