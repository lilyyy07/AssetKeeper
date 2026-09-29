# AssetKeeper

**A Web-Based IT Asset Tracking and Inventory Management System for UPTM ICT Department**

AssetKeeper supports the approved FYP scope: asset records, allocation and returns, inventory monitoring, maintenance, and operational reports. It extends the existing Laravel project and preserves its Bootstrap navy/purple design.

## Requirements and installation

- PHP 8.2+, Composer, MySQL 8+/MariaDB supported by Laravel 12, XAMPP.
- PHP extensions: PDO MySQL, PDO SQLite (tests), OpenSSL, mbstring, DOM/XML, fileinfo, iconv.
- Bootstrap, Bootstrap Icons and Inter currently load from CDNs. Internet access is needed for those visual assets. No frontend build is required for the Blade application.

From `C:\xampp\htdocs\AssetKeeper`:

```powershell
composer install
# Only on a NEW installation without .env:
Copy-Item .env.example .env
php artisan key:generate
```

Do not regenerate an existing APP_KEY: it protects encrypted sessions. Keep an existing `.env` and its credentials private.

Create `assetkeeper_db` in MySQL and set your local connection in `.env`:

```dotenv
APP_NAME=AssetKeeper
APP_ENV=local
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=assetkeeper_db
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_ENCRYPT=true
```

The empty database password above is an example for a local XAMPP installation, not a production credential. Set the actual password locally.

```powershell
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8000
```

Open http://127.0.0.1:8000. Migrations and seed data have already been applied to the current workspace database. Do not use `migrate:fresh` against this database. The demo seeder is repeatable and only runs in `local` or `testing`; it preserves asset records and resets the four development accounts to the documented password, roles and active status using updateOrCreate() and Hash::make(). Existing user IDs, profile names, departments and MFA settings are preserved.

For Apache, point the virtual host document root to `AssetKeeper/public`, never the project root. Use one consistent host matching APP_URL. A phone scanning QR labels must be able to reach that host: `127.0.0.1` refers to the phone itself, so use your reachable LAN hostname/IP when collecting scan evidence. Reprint labels if the host or APP_KEY changes. For HTTPS deployments set `SESSION_SECURE_COOKIE=true` and keep `APP_DEBUG=false`.

## Development accounts

All four fictional development accounts initially use **`DevOnly!2026AK`**. These credentials are for local development only.

| Email | Role | Scope |
|---|---|---|
| admin@assetkeeper.test | ICT Administrator | All modules, users and audit information |
| technician@assetkeeper.test | ICT Staff / Technician | Assets, allocations, inventory, maintenance, operational reports |
| department@assetkeeper.test | Retired Department Staff | Inactive historical testing account; login denied |
| management@assetkeeper.test | Management (HOD) | Global read-only dashboard, assets, inventory and reports |

Administrator and technician accounts must verify a six-digit email OTP after password login. Management does not require MFA by default. Department Staff is retired and cannot log in. See [Email OTP setup and verification](docs/EMAIL_OTP.md) to configure SMTP before privileged login. Development `.test` addresses do not receive real internet email; use a local SMTP capture inbox or a test account with a mailbox you control.

To reset only these four development accounts without reseeding assets, run `php artisan db:seed --class=UserSeeder`. This command is restricted to local/testing environments.

No public registration exists. Administrators create users, change roles/departments and activate/deactivate accounts. They cannot demote/deactivate themselves or remove the final active administrator. Existing inactive sessions are rejected on their next protected request.

## Main screens and workflows

1. Login (plus the email OTP verification screen).
2. Dashboard: database counts, status/category summaries, recent maintenance, administrator-only recent audit events.
3. Asset Management: create/edit modals, search, filters, pagination.
4. Asset Details: complete record, current assignment, allocation/maintenance history, administrator-only asset audit, signed QR and printable label.
5. Asset Allocation: allocation/edit/return modals and preserved history.
6. Inventory Tracking: monitoring from assets and relationships, with no separate inventory table.
7. Maintenance: add/edit/status modal and preserved history.
8. Reports: one page with inventory, allocation, status, location/department and maintenance report types; filters, complete print results and CSV.
9. User Management: administrator-only account modals.

Profile and Change Password are in the navbar dropdown. Prior static create/edit/detail templates for allocation/maintenance remain in the repository as unused design references; they have no routes or navigation links.

### Status rules

- Only Available assets without an active allocation/maintenance can be allocated.
- Allocation atomically records the assignment and updates asset department/location/status. Overdue is calculated from an active allocation's expected return date.
- Returns preserve history, date, condition and remarks. Poor condition marks Damaged. Good/Fair returns become Available unless active maintenance remains. Department/location are retained as last recorded placement until explicitly updated.
- Pending and In Progress maintenance are both active maintenance. The asset becomes Under Maintenance unless already Damaged/Disposed. Disposed equipment cannot enter maintenance.
- Completing the last active maintenance returns the asset to Allocated if an active allocation exists, otherwise Available. Damaged/Disposed states are preserved. A repaired Damaged asset requires an explicit asset edit to Available after inspection; active workflows still take precedence.
- Multiple maintenance records may be active; completing one does not clear another. Completed maintenance and returned allocations are historical and cannot be edited through the application.
- Disposal is blocked while an active allocation or maintenance exists. No asset, allocation, maintenance or user deletion endpoints exist.
- Transactions and asset row locks serialize related asset/allocation/maintenance writes on MySQL.

Asset Status edits offer Available, Damaged and Disposed. Allocated and Under Maintenance are workflow-derived, preventing inconsistent manual assignments. Historical allocation reports use the allocation's original department/location, not the asset's later placement. Inventory report date filters refer to asset creation; allocation and maintenance use allocation/report dates.

## Database and relationships

| Table | Purpose / relationships |
|---|---|
| departments | Has many users and assets |
| users | Belongs to department; role and active flag; retired TOTP fields are cleared and unused |
| assets | Belongs to department and creator; has many allocations, maintenance records, activity logs; current active allocation |
| allocations | Belongs to asset, department and creator; return history |
| maintenance_records | Belongs to asset, technician and creator |
| email_otp_challenges | One current hashed, session-bound OTP challenge per user; five-minute expiry |
| activity_logs | Belongs to user and optionally asset; entity reference and safe old/new values |

Laravel's existing sessions, password_reset_tokens, cache/cache_locks, jobs/job_batches/failed_jobs tables are retained. Unique asset tags, optional unique serial numbers, unique emails, foreign keys and common lookup indexes are enforced. There is no duplicate inventory table.

## Security controls

- Laravel session authentication, password hashing, remember-me, session ID regeneration, CSRF forms and logout invalidation.
- Server-side role gates and department-scoped asset queries, including signed QR detail access.
- Generic login errors; five failed attempts per email/IP within 60 seconds cause throttling. MFA has a separate five-attempt user/IP limit.
- Email OTP: secure random six-digit codes, hashed database storage, five-minute expiry, single use, session binding, five attempts per challenge and account/minute, and a 60-second resend cooldown. Pending password verification expires after 15 minutes. SMTP-only delivery refuses log/array/failover mailers.
- Local SVG QR generation with `bacon/bacon-qr-code` 3.1.1. Asset data is not sent to a third-party QR service. Asset QR contains a signed internal URL and still requires authentication/authorization.
- Thirty-minute inactivity check; encrypted database sessions; HttpOnly/SameSite=Lax cookies; current-password verification for changes; other database sessions revoked after password changes.
- Server validation, escaped Blade output, CSV formula neutralization, no-store responses, anti-framing and content-type headers; debug output disabled in current/example configuration.
- Append-only application audit records for authentication, users, asset changes, allocation/return and maintenance. Only safe allowlisted old/new fields are recorded. No passwords, OTPs, recovery codes, secrets or session tokens are logged. Audit records have no write routes; the model rejects updates/deletes. Database administrators are outside this application-level immutability boundary.

Email MFA has no authenticator enrollment or recovery codes. Use Resend OTP after its cooldown, or contact the administrator to correct an inaccessible registered email. Keep the application key and database backups securely. UI confirmation prompts complement server validation and authorization.

## Verification and FYP evidence

```powershell
php artisan migrate:status
php artisan route:list --except-vendor
php artisan test
php artisan view:cache
composer check-platform-reqs
composer audit --locked
```

Automated tests force an isolated SQLite in-memory database; they do not reset `assetkeeper_db`. MySQL migrations and repeatable seeding were also executed on the local database. See [verification and manual/UAT checklist](docs/TESTING.md), [implementation inventory](docs/IMPLEMENTATION.md), and local browser evidence under `storage/app/qa`.

The existing visual layout/CSS is retained, with responsive tables, modal actions, old-input reopening, validation and success alerts, empty states and print layouts. Final human acceptance, actual phone scanning and physical print quality should be recorded by the project evaluator; do not represent them as completed participant UAT.



See [final client feedback](docs/CLIENT_FEEDBACK.md) for current roles, categories, import, and maintenance timing.
