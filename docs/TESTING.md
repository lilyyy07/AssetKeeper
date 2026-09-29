> Authentication update: TOTP has been replaced by Email OTP. See [EMAIL_OTP.md](EMAIL_OTP.md) for current behavior, SMTP setup and verification (35 tests, 359 assertions). Any authenticator/recovery-code references below are historical and superseded.

# Verification and UAT evidence

## Executed verification

- Non-destructive migration applied successfully to MySQL `assetkeeper_db`.
- Demo seed executed twice without duplicate assets/accounts.
- Route listing: 27 application routes; no public registration or delete routes.
- Composer platform requirements passed on PHP 8.2.12.
- Composer audit: no security vulnerability advisories reported at verification time.
- Laravel automated tests: 22 tests, 214 assertions passed.
- Browser checks via isolated headless Chrome: real administrator login, TOTP setup/verification, dashboard, assets, allocations, inventory, maintenance, all-account navigation, reports, users and profile rendering; asset modal; asset QR details; 390px mobile inventory/navigation, with no page-level horizontal overflow. Server validation reopened the asset modal with retained input; report and label-only print layouts were captured and visually inspected.
- Screenshots captured in `storage/app/qa`: login, dashboard, asset-modal, asset-details, mobile-inventory, mobile-navigation, validation-modal, print-label, print-report. No setup secret or recovery-code screenshots were captured.
- Administrator MFA enrollment used for QA is reset afterward; enroll your own authenticator on first use.

Tests use SQLite in memory. MySQL row-lock concurrency across simultaneous requests is implemented but is not a stress-test result. Physical phone scanning, real authenticator enrollment by the evaluator, printer output and participant UAT remain manual verification tasks.

## Automated coverage

| Area | Evidence |
|---|---|
| Unit | Role capability separation; overdue calculation without rewriting history |
| Authentication | Valid/invalid login, logout, inactive accounts, login throttling |
| MFA | Password-only gate, setup, valid TOTP, replay rejection, single-use recovery, throttling, sensitive input excluded from flashed data |
| Authorization | Four roles, 403 responses, department isolation, no public registration, inactive existing sessions |
| Assets | Creation, duplicate tag, required/price validation, edit, scoped inventory/dashboard/report integration |
| Allocation | Available-only assignment, duplicate prevention, return, repeated-return rejection, audit |
| Maintenance | Create, complete, active allocation restoration, return during maintenance, multiple active records, technician restrictions, completion-date validation |
| Security | Signed/tampered QR, cross-department QR denial, CSV formula protection, inactivity timeout, append-only audit |
| Reports / accounts | Five report types and CSV, historical department/location filter, date-only filter, user creation, password check, self-deactivation guard |

## System-testing checklist

Record actual outcome, pass/fail, date, tester and screenshot filename for each test.

| ID | Action | Expected result |
|---|---|---|
| SYS-01 | Sign in/out with all four demo accounts | Correct role navigation and access; privileged accounts require MFA |
| SYS-02 | Enter wrong password five times, then retry | Generic errors and temporary throttle |
| SYS-03 | Disable another account and retry login/existing session | Access refused |
| SYS-04 | Technician enters /users; Management submits asset mutation | 403; no write |
| SYS-05 | Enroll MFA; try wrong/used TOTP and one recovery code twice | Only fresh valid code/unused recovery code succeeds |
| SYS-06 | Create an asset with unique tag and optional serial | Visible in assets/inventory/dashboard/reports and audit |
| SYS-07 | Submit duplicate tag, negative price and invalid dates | Errors; original modal and input restored |
| SYS-08 | Allocate Available asset; attempt a second active allocation | First succeeds, second refused; inventory shows Allocated |
| SYS-09 | Return Good asset; repeat return | History retained, Available, second return refused |
| SYS-10 | Start maintenance on allocated asset; complete | Under Maintenance, then Allocated |
| SYS-11 | Return while maintenance active | Remains Under Maintenance until final active job completes |
| SYS-12 | Return Poor condition and complete maintenance | Damaged preserved until explicit inspected status edit |
| SYS-13 | Generate five reports using filters; download CSV | Database rows, summary, timestamp, filters, correct CSV |
| SYS-14 | Print report and asset label | Readable complete report / label-only output |
| SYS-15 | Scan signed QR on reachable host; alter signature; use other department | Correct record; tamper and unauthorized access denied |
| SYS-16 | Change profile and password with wrong/right current password | Role/status unchanged; only valid password change accepted |
| SYS-17 | Inspect asset/dashboard audit as administrator | Critical events shown without authentication secrets |
| SYS-18 | Mobile navigation, modals, tables and validation | Usable without page-level overflow; tables scroll within container |
| SYS-19 | Leave session idle over 30 minutes | Reauthentication required |
| SYS-20 | Print and export empty filtered report | Clear empty result; no broken screen |

## Participant UAT (4-point Likert)

Obtain evaluator consent and collect only the participant role/reference needed for the FYP. Do not prefill results. Scale: 1 Strongly Disagree, 2 Disagree, 3 Agree, 4 Strongly Agree.

1. The navigation is easy to understand.
2. I can find asset information quickly.
3. Asset registration and editing are clear.
4. Allocation and return steps are easy to complete.
5. Inventory status matches the actions performed.
6. Maintenance information is useful and understandable.
7. Reports provide the information I need.
8. The system responds within a reasonable time.
9. Error messages help me correct mistakes.
10. Login and MFA instructions are understandable.
11. The interface works well on the device I use.
12. Overall, the system supports the department's asset-management work.

Capture free-text comments, observed task completion, suggestions and role-specific N/A answers separately. Calculate item means from actual responses, preserve the original questionnaire responses, and report sample size and limitations. Automated tests and developer browser checks are not substitutes for participant UAT.

## FYP objective mapping

| Objective | Implementation / evaluation support |
|---|---|
| 1: Analyze practices and requirements | Preserved approved ICT scope, three active roles, compact screens, department-based records and documented business rules; no claim to redo prior requirements gathering |
| 2: Develop the system | Database-backed asset records, allocation/return, inventory monitoring, maintenance and five operational reports |
| 3: Evaluate functionality/usability/effectiveness | Meaningful unit/integration/security tests; repeatable fictional data; system checklist, browser screenshots and 4-point participant UAT questionnaire |




See [final client feedback](CLIENT_FEEDBACK.md) for current roles, categories, import, and maintenance timing.
