# AssetKeeper: Vercel deployment preparation (not deployed)

## Files and build

- `api/index.php`: PHP front controller; bootstraps the existing Laravel app. Only disposable framework paths (Blade views and bootstrap manifests) use the function temporary directory. Trusts proxy IP/protocol headers for HTTPS in this entry point only, not forwarded host headers. Local `public/index.php` is unchanged.
- `api/php.ini`: disables PHP error display, logs to stderr, allows the existing 2 MB CSV upload limit.
- `vercel.json`: pins `vercel-php@0.7.4` (PHP 8.3), maximum function duration 60 seconds, installs with `npm ci --include=dev`, builds Vite and prepares static output. Serves existing static files first; all other requests go to `api/index.php`. No secrets or database values are embedded.
- `scripts/vercel-static.mjs`: copies public CSS/JS/images/fonts/text and Vite bundles into `.vercel-static`; excludes PHP, dotfiles, symlinks and public storage. The Vite manifest remains in `public/build` for Laravel, not the static site.
- `.vercelignore`: excludes environment files, local dependencies, storage, cached configuration, tests, documentation and database exports from uploads.
- `.gitignore`: adds Vercel outputs, environment variants and SQL exports to existing exclusions. `.env.example` remains eligible for Git.
- `package.json`: pins the supported Node major to 22.x; no frontend dependency ranges or UI code changed.
- `package-lock.json`: locks existing frontend dependencies for repeatable installation.

Set Vercel Framework Preset to **Other**, Node.js **22.x**, and Root Directory to **AssetKeeper** when the repository contains that folder, or the repository root when it contains the Laravel files directly. Keep the build/output settings from vercel.json. The PHP runtime installs Composer dependencies from composer.lock. Do not run migrations, seeders, config:cache, or frontend dev servers as part of a Vercel build. Build-time environment configuration must not be baked into a cached Laravel config file.

Runtime reference: https://github.com/vercel-community/php (0.7.4 is the PHP 8.3 runtime).

## Production environment variables

Enter these in Vercel, not source code. Production and Preview should use separate databases, credentials and cache prefixes. Do not attach previews to the production data.

| Variable | Production value |
| --- | --- |
| APP_NAME | AssetKeeper |
| APP_ENV | production |
| APP_DEBUG | false |
| APP_URL | Your final HTTPS application origin, without a trailing slash |
| APP_KEY | Stable Laravel application key, stored as a secret |
| DB_CONNECTION | mysql |
| DB_HOST | External MySQL hostname, never localhost/127.0.0.1/XAMPP |
| DB_PORT | Provider port (usually 3306) |
| DB_DATABASE | Dedicated production database |
| DB_USERNAME | Dedicated production database user |
| DB_PASSWORD | Database password, secret |
| SESSION_DRIVER | database |
| SESSION_LIFETIME | 30 (preserves current inactivity policy) |
| SESSION_ENCRYPT | true |
| SESSION_SECURE_COOKIE | true |
| SESSION_HTTP_ONLY | true |
| SESSION_SAME_SITE | lax |
| SESSION_PATH | / |
| CACHE_STORE | database |
| CACHE_PREFIX | assetkeeper_production_cache_ (unique per environment) |
| LOG_CHANNEL | stderr |
| LOG_LEVEL | error |
| APP_MAINTENANCE_DRIVER | cache |
| APP_MAINTENANCE_STORE | database |
| UAT_MODE | false |
| UAT_OTP_BYPASS_EMAIL | assetkeeper.uat@uptm.edu.my |
| MAIL_MAILER | smtp (required by current OTP implementation) |
| MAIL_HOST | SMTP provider hostname |
| MAIL_PORT | Provider port; normally 587 with STARTTLS |
| MAIL_USERNAME | Provider username |
| MAIL_PASSWORD | Provider password/app password, secret |
| MAIL_FROM_ADDRESS | Provider-authorized sender |
| MAIL_FROM_NAME | AssetKeeper |
| MAIL_ENCRYPTION | tls for STARTTLS; ssl for implicit TLS |
| MAIL_SCHEME | smtp for STARTTLS; smtps for implicit TLS |

Leave SESSION_DOMAIN unset for host-only cookies. Set an explicit domain only if intentional cross-subdomain sessions are required; never include a URL scheme or port. SESSION_COOKIE can optionally be set to an environment-specific name. SESSION_CONNECTION, SESSION_TABLE, DB_CACHE_CONNECTION, DB_CACHE_TABLE and DB_CACHE_LOCK_TABLE may remain unset: the default database and existing sessions/cache/cache_locks tables are correct. SESSION_STORE is not needed for database sessions.

MAIL_EHLO_DOMAIN is optional if the provider requires a particular HELO name; otherwise it is derived from APP_URL. Leave MAIL_URL unset when using the separate SMTP fields (it can override them). MAIL_LOG_CHANNEL and MAIL_SENDMAIL_PATH are not needed. SMTP port 25 should not be used. No SMTP credentials were copied into source/configuration.

MYSQL_ATTR_SSL_CA is optional/provider-dependent: supply a readable CA certificate path if required for verified TLS. A CA certificate is public material and can be packaged separately; do not disable certificate verification. Leave DB_URL and DB_SOCKET unset when using the six explicit database settings. VITE_APP_NAME=AssetKeeper is optional; never place secrets in VITE_* variables. Do not set ASSET_URL unless using a separate asset origin.

The entry point sets its disposable writable paths itself; do not configure storage, view or bootstrap cache paths manually. FILESYSTEM_DISK can remain local because no durable uploads use it. QUEUE_CONNECTION need not be changed: no application jobs are dispatched.

## Database and migrations

Use external MySQL compatible with Laravel 12 (MySQL 8.0+ recommended), InnoDB transactions/foreign keys and utf8mb4. The host must accept encrypted TCP connections from the Vercel region and from the controlled migration workstation/CI runner. Size connection limits for function concurrency; static egress allowlisting may require provider/Vercel networking arrangements. Place the function and database near each other.

To preserve current data and users, take a consistent backup/export of the local database and restore it into a NEW, empty external database. This is a copy, not a move: never reset or overwrite the local database. Include schema, application data, password hashes, UAT flags, and the migrations table. Do not commit database exports. Transient session/cache/OTP challenge data need not be carried over; keep their table schemas. Retain the existing APP_KEY securely if transferring encrypted values; never rotate it casually. Otherwise create a production key once with `php artisan key:generate --show` in the isolated production-configured checkout, store it securely, and keep it stable.

Use a separate clean checkout/workstation/CI environment with production DB_* injected and no local XAMPP .env. Check the selected DB_HOST/DB_DATABASE before running anything. Back up any existing production data first. Run:

```sh
php artisan migrate:status
php artisan migrate --pretend --force
php artisan migrate --force
```

The final command is the safe forward migration command: `php artisan migrate --force`. Review pending migrations first. Never run migrate:fresh, migrate:refresh, migrate:reset, rollback, or automatic/demo seeding. Do not put migrations into the Vercel build. Vercel has no persistent interactive PHP shell: run migration commands from the controlled PHP 8.3 environment with production connectivity.

Important existing migration caveat: the historical email OTP migration clears old TOTP fields, and the historical client-feedback migration deactivates the old Department Staff role. They were not edited. Preserving the source migrations table prevents re-running completed migrations against existing users. Review any genuinely pending migration before approval; forward migration is not a promise of zero data changes.

## Dedicated production UAT account

Preferred: copy the existing database, retaining the account's ID, name, email, password hash, ICT Administrator role, active status and `is_uat_account=true`. This preserves the existing supplied password without exposing or resetting it. Verify the account exists before doing anything else. Set UAT_MODE=false and UAT_OTP_BYPASS_EMAIL=assetkeeper.uat@uptm.edu.my. The existing protected flag plus exact email identifies the no-OTP account; normal administrators/technicians still use email OTP.

Only if creating a fresh production database and the account is absent, the existing interactive command can create it without plaintext passwords in source or shell arguments:

```sh
php artisan assetkeeper:uat-account assetkeeper.uat@uptm.edu.my --role="ICT Administrator" --name="AssetKeeper UAT Tester"
```

Run this only from the production-configured checkout and enter the existing agreed password at the two hidden prompts. Do not run it against an already-existing account: it resets that account's password. Do not seed demo accounts. No local account was recreated or modified during preparation.

## Runtime review

- APP_URL already comes from environment variables. The dedicated Vercel entry point recognizes forwarded HTTPS; local XAMPP behavior remains unchanged. APP_URL is also used for console-generated links; request-generated links use the actual request host. Use the canonical production domain.
- Email OTP uses synchronous SMTP with a 15-second transport timeout; keep MAIL_MAILER=smtp. Verify external SMTP connectivity and inbox delivery before inviting users. User/profile password changes are present. No public forgot-password/reset-email route or custom reset mailer exists in this checkout; none was added or removed. Laravel's default password broker configuration alone does not provide a working public reset flow.
- Sessions and cache already use the database locally. Retain those drivers in production: login sessions, OTP rate limits and CSV import previews must survive requests across instances. No session/cache application code change was needed.
- CSV import uploads are limited to 2 MB, read from the request temporary file and parsed into the database-backed session. Files are not saved as persistent uploads. CSV reports stream to the response; QR output is generated without durable file storage. No new storage provider is required. Future persistent uploads would need object storage.
- Blade views/bootstrap manifests use ephemeral temporary paths; logs go to stderr. Do not rely on local files for sessions, cache, maintenance state or durable data. Use the shared database maintenance driver.
- No application queued jobs/ShouldQueue dispatches or scheduled tasks were found. No worker, queue service, cron or scheduler is required. Do not run composer dev, artisan serve, queue:listen, or npm run dev on Vercel.
- Runtime application code has no XAMPP/Windows path dependency. Existing configuration has localhost fallbacks that the required environment values replace. A visual-capture test has a Windows-specific file URL; tests/docs/local artifacts are excluded from deployment.
- Existing layouts serve public/css and public/js plus external Bootstrap/fonts; these are preserved. Vite/Tailwind bundles compile and are published too. No redesign or forced migration of layouts to Vite was performed.
- No UAT Email page, invitation sender or Google Form configuration was restored.

## Security and limits

APP_ENV=production and APP_DEBUG=false are required. PHP error display is disabled; no debug runtime flags should be set. .env and variants are ignored by Git and excluded from Vercel, along with database exports and cached local config. There is no Git repository here yet, so there is no commit history to audit. Before the first commit inspect staged filenames (`git diff --cached --name-only`), and verify `git check-ignore .env`. Existing password hashes, role gates and OTP implementation remain unchanged.

Community PHP support is not a first-party Vercel PHP service. A local test cannot prove Vercel packaging, extension availability, provider networking, function cold starts or delivery. Verify these during the first authorized staging deployment. The configured 60-second function limit and Vercel request/response size limits can constrain large imports/reports; synchronous SMTP also consumes request time. Do not change application semantics to conceal those limits. See https://vercel.com/docs/functions/limitations .

## Verification and remaining commands

Run locally with PHP 8.3 and Node 22:

```sh
npm ci --include=dev
npm run build
node scripts/vercel-static.mjs
php -d extension=pdo_sqlite vendor/bin/phpunit
```

The SQLite extension switch is needed only where the installed CLI has it disabled. PHPUnit forces SQLite :memory:; test database resets occur only in that isolated database, never the local MySQL database. Do not run PHPUnit using production database credentials.

Preparation verification: production Vite/Tailwind build passed; static output excludes PHP and secrets; entry-point smoke test returned HTTP 200 for login using production environment, debug=false and HTTPS asset URLs. Full suite passed on PHP 8.3: 76 tests, 933 assertions. The live Vercel build, DB connectivity, SMTP delivery, secure cookie persistence, signed QR links and existing/UAT login flows must be checked after deployment is explicitly authorized.

Ready to prepare a Git repository and import with the settings above, but not yet production-validated. GitHub import may immediately deploy: do not import until deployment is authorized and the production database/environment are ready. No Git push, Vercel link, import or deployment was performed.
