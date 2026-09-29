# AssetKeeper UAT guide

## Operator preparation (before inviting respondents)

This change prepares UAT access; it does not deploy the application, enable UAT mode, provision live test credentials, or reset existing data. The final roles remain ICT Administrator, ICT Staff / Technician, and Management (HOD). Student and academic staff are borrowers, not login roles. Department Staff remains retired.

Use a dedicated hosted UAT copy or approved test dataset where practical. Keep existing records intact. Ask respondents to prefix new asset tags/names and test remarks with UAT plus their assigned respondent identifier. Use fictional borrower details. UAT administrators have the same real permissions as normal administrators, so coordinate their tasks and avoid editing baseline records or normal accounts.

### Provision the three dedicated accounts

Run the safe additive migrations on the intended host with `php artisan migrate --force`. Do not use migrate:fresh. Keep a normal active administrator with a working mailbox available.

Use these command patterns, replacing placeholder addresses with dedicated addresses selected by the host operator:

```shell
php artisan assetkeeper:uat-account <admin-email> --role="ICT Administrator" --name="UAT ICT Administrator"
php artisan assetkeeper:uat-account <technician-email> --role="ICT Staff / Technician" --name="UAT ICT Staff / Technician"
php artisan assetkeeper:uat-account <management-email> --role="Management (HOD)" --name="UAT Management / HOD"
```

Optionally add `--department=<active-department-id>`. The command prompts twice for a hidden password (minimum 12 characters, uppercase, lowercase, number and symbol), hashes it with Laravel, and never prints it. Use a password manager to generate/store passwords and send credentials privately. Never pass passwords in CLI arguments, shell history, source, documentation, screenshots, or application logs. Run from an interactive terminal that supports hidden prompts. There is no default or master password.

The command explicitly sets is_uat_account=true. This flag is deliberately absent from web forms and mass-assignable fields. It refuses to convert an existing normal account. Re-run it with the same dedicated email to reset that UAT account's password; identity/history remain and existing sessions/remember tokens are revoked. It will not demote or deactivate the last active administrator.

### Enable controlled UAT access

Only on the intended temporary UAT host, set the server environment configuration `UAT_MODE=true`, then run `php artisan config:cache`. Restart long-lived application workers if used so they read the changed configuration. There is no public mode toggle.

OTP exception requires BOTH config('uat.enabled') === true AND user.is_uat_account === true. Default/example configuration is UAT_MODE=false. Email spelling alone never qualifies. Normal administrators and technicians still require email OTP even while UAT mode is enabled. Management keeps its existing password-only policy. Marked accounts otherwise follow exactly the existing role permissions, password authentication, CSRF, validation, throttling and inactivity/session rules. No bypass code is introduced.

Sign in separately as each provisioned role to verify permissions. Verify that a normal administrator and a normal technician still receive OTP. Do not use the project owner's normal account for respondent access.

## Respondent flow

1. Sign in using the UAT credentials privately supplied to you.
2. View Dashboard and the asset status/category summaries.
3. Search for an assigned test asset and open its details. Review Year Purchased and the QR section.
4. Follow the tasks for your role below. Use only assigned UAT records.
5. View Inventory Tracking and apply filters.
6. Generate reports, apply filters, and try CSV export and Print / Save as PDF.
7. Confirm your menu and permissions match your assigned role.
8. Review Profile / Change Password if requested by the coordinator.
9. Log out and complete the external Google Form questionnaire supplied by the coordinator. No questionnaire URL is embedded in this application.

| Role | Authorized UAT tasks |
| --- | --- |
| ICT Administrator | Test Add/Edit/View Asset; approved category/department test entries; CSV template, validation preview and import; allocation/return; maintenance; reports; assigned test-user management. |
| ICT Staff / Technician | Test Add/Edit/View Asset; allocate/return assigned test assets using permitted borrower types; create/update maintenance; view reports. User, department/category administration and import must be denied. |
| Management (HOD) | View dashboard, assets and inventory; filter and export reports. Do not perform allocation, maintenance editing, asset mutations, user management, import or configuration tasks. |

For operator maintenance tasks, record Problem Reported first, then Problem Attended, Repair Started and Completed as applicable. Leave later stages blank until reached. Dates/times must be chronological. View is available for every authorized maintenance record; Edit / Status is available for active records only. Completed records remain read-only. Durations are automatic: Response Time = Attended − Reported; Repair Duration = Completed − Repair Started. Missing values show an em dash; date-only history does not imply a midnight time.

CSV imports require a valid preview and confirmation. Existing asset tags/serial numbers are never overwritten. Only import the coordinator's approved fixture file.

## End UAT safely

1. Set server `UAT_MODE=false`, run `php artisan config:cache`, and reload long-lived application workers if used.
2. Deactivate each dedicated account using `php artisan assetkeeper:uat-account <dedicated-email> --deactivate`, or use the existing administrator User Management deactivation control. Keep a normal active administrator. Do not delete users, assets, allocations, maintenance, or audit history.
3. Existing privileged sessions that relied on the exception are rejected on the next protected request after the new configuration is loaded. A marked administrator/technician must again complete normal email OTP at the next login. Management follows its unchanged policy, so explicitly deactivate its UAT account as well.
4. Verify a normal administrator and technician still complete OTP and a deactivated UAT account cannot sign in.
5. Retain UAT attribution: marked users display “UAT account” in User Management, and new activity descriptions are prefixed “UAT:”. Original audit entries are unchanged.

## Pre-hosting checks and limitations

Do not deploy until the host/operator has checked HTTPS, public/ as document root, APP_DEBUG disabled, protected environment configuration, real SMTP delivery, database backup/restore, and correct application timezone. Keep production defaults and secrets outside source control. Confirm the UAT accounts, mode lifecycle, session expiration, and representative CSV on the intended host. Check printer/PDF pagination using realistic report volumes.

Browser checks use emulated desktop Chrome viewports, not physical phones. A real iPhone/Android check after hosting is recommended, including touch scrolling, mobile keyboard/date inputs, offcanvas navigation and modal footers. CMS integration remains a future enhancement requiring authorized UPTM endpoints, credentials and field permissions; no CMS integration or student lookup is implemented.