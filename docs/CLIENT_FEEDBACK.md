# Final UPTM client feedback

Active roles are ICT Administrator, ICT Staff / Technician, and Management (HOD). The existing Management label is retained. Department Staff is retired: its accounts are deactivated by the migration, excluded from user role choices, and rejected by login and authenticated-session middleware even if accidentally reactivated. Historical identity/role text and references remain. Departments/faculties and all six borrower types remain unchanged. Development seeding retains only an inactive legacy testing fixture.

Year Purchased is derived from purchase_date, shown in asset details, beneath the asset name in the registry/inventory, and in asset reports. No redundant purchase-year column is stored.

Administrators use Manage Categories inside Asset Management. Categories can be added, renamed, activated or deactivated. Existing names are backfilled from defaults and actual assets. Renaming synchronizes the existing assets.category strings in a transaction; deactivation preserves history. New assets require active categories; editing an asset can retain its current inactive category. Filters include inactive categories. There is no category deletion endpoint or new sidebar module.

## CSV import

Import Assets is inside Asset Management and restricted server-side to administrators. Download the header template; upload UTF-8 CSV, at most 2 MB / 500 data rows. XLSX is not supported. Fields map to the existing schema: asset_tag, name, category, brand, model, serial_number, condition, status, department, location, purchase_date, purchase_price, supplier, warranty_expiry, notes. Department uses its active name; blank is allowed. Required columns: asset_tag, name, category, condition, status, location. Dates are YYYY-MM-DD and prices numeric RM.

Upload validates MIME/extension, headers, row shape, required values, active categories/departments, dates, numeric bounds, status/condition and asset-tag/serial uniqueness in the database and file. A row-numbered preview must pass entirely before confirmation. Confirmation revalidates current data and commits all assets and audit entries in a transaction; no partial batch and no overwrites. Concurrent database conflicts roll back and show a safe error. Blank serial numbers remain null. Available, Damaged and Disposed are importable; Allocated and Under Maintenance require the existing workflows so inventory cannot acquire orphaned workflow states.

Preview data is session-bound to its administrator, one-use, and expires after 30 minutes. The upload is read from PHP's temporary upload location and not copied to public storage; PHP removes it after the request. Session cleanup handles expired preview data. CSRF, authentication, account/MFA checks and administrator authorization protect import mutations. Uploaded content is parsed as CSV and never executed. Exports retain spreadsheet-formula escaping.

## Maintenance

Existing date_reported and actual_completion_date are upgraded from dates to datetimes; nullable problem_attended_at and repair_started_at are added. Later stages remain optional until reached; completion still requires Completed's timestamp. The server checks every present stage in chronological order, including when an intermediate stage is absent. Existing asset status synchronization is unchanged.

Response Time = Problem Attended minus Problem Reported. Repair Duration = Completed minus Repair Started. Durations display days/hours/minutes. Historical date-only values are preserved and labeled time not recorded. Precision flags prevent inventing a response or repair duration from midnight conversion. No ratings are introduced. Input/display follows the existing application timezone.

Maintenance screens and asset history show the timeline. The maintenance report pairs timestamps in compact columns for screen/landscape print; CSV retains separate timestamp and duration columns. Existing filters and Print / Save as PDF remain.

## Preservation and deployment

The additive 2026_09_28_000001 migration preserves business rows and does not rebuild users or erase referenced records. It is intentionally forward-only so rollback cannot silently drop new timing/category data or reactivate retired accounts. Do not run migrate:fresh on the application database. Tests use the explicitly isolated in-memory SQLite database. Password hashing, email OTP, expiration/attempt/cooldown/throttling controls, CSRF, session checks, audit logging and signed QR remain intact.

CMS integration is a future enhancement only. It requires authorized UPTM API/database access, approved endpoint and field permissions, and credentials. No integration, fake student records, endpoint or scraping has been implemented; manual borrower entry remains available.

Verification: php artisan test; php artisan route:list; php artisan view:cache. Compare private before/after row snapshots around migration, allowing only retired-account deactivation and date-to-datetime representation changes. Browser checks should cover category add/edit/deactivate, CSV preview/errors/confirmation, timeline input, mobile modals, report print layout and console errors.
## Verification results (28 September 2026)

- Application migration completed successfully; no migrate:fresh or business-data deletion was used.
- Full suite: 63 passed, 702 assertions, zero failures. This includes OTP, RBAC, signed QR, borrower allocation/return, department/user management, import and maintenance coverage.
- All 35 routes checked; Blade compilation passed.
- Before/after preservation comparison: users 7/7, departments 5/5, assets 10/10, allocations 4/4, maintenance 4/4, activity logs 156/156. Every original field checked, allowing only retired account deactivation and equivalent historical date-to-datetime conversion; no unexpected changes. Active retired accounts: zero.
- Headless Chrome rendered isolated fixture pages at 1440px and 390px: category/import modals opened, maintenance datetime inputs rendered correctly, no page-level horizontal overflow and no captured JavaScript exceptions. Screenshots were visually reviewed and small spacing/header-wrap issues corrected. Landscape print output was generated.
- Still recommended: a final authenticated UPTM acceptance walkthrough with a representative real CSV, actual SMTP OTP delivery, and print preview on the intended office browser/printer. Browser rendering used isolated test-fixture HTML; mutation workflows were exercised by Laravel HTTP tests rather than a live production browser session.