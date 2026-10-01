# Resident Portal implementation report

Completed locally. No deployment, commit, or push was performed. Existing untracked `resident-import-test.csv` was preserved.

## 1. Existing architecture
Laravel 13.31.0 / PHP 8.4, Fortify 1.39.0, Livewire 4.4.4, Flux 2.19.0. Existing employee authentication uses users.role and is_active, with policy allowlists for Admin/Staff and existing Viewer restrictions. Residents use SoftDeletes. Document requests, issuance, QR verification, CertificateType fees, administrative auditing, and archive/restore already existed. Public request submission previously accepted identity from the client and attempted identity matching.

## 2. Added functionality
Resident account verification, registration, login, read-only profile, own request history, public service information, registration assistance, custom resident logout confirmation, staff activation-code issuance, and administrator account suspension/reactivation.

## 3. Modified functionality
Online submissions now require an eligible linked resident account. Identity comes from official records and account email. Reference-code status access requires authentication and ownership, while authorized Admin/Staff can still look up requests. Employee account management excludes resident accounts. Existing staff settings remain available to employee accounts; residents use their dedicated profile.

## 4. Database changes
Additive migration `2026_09_22_175723_add_resident_portal_accounts.php` adds:
- Nullable unique users.resident_id, foreign key to residents with restrictive deletion.
- Nullable residents.portal_registration_hash and portal_registration_expires_at.
- Nullable document_requests.private_attachment_path.
Existing employee accounts retain null resident_id. No resident rows or historical requests are deleted or recreated. The unique constraint permits at most one linked account, including suspended accounts.

Migration was successfully applied to the configured local MySQL connection after confirming app.env=local and host=127.0.0.1. No production database operations were performed.

## 5. Routes
Added public GET /portal/information, /portal/login, /portal/register, /portal/registration-help; POST /portal/register/verify and /portal/register.
Added protected GET /portal/account, /portal/profile, /portal/identity.
Added administrative POST /admin/residents/{resident}/portal-activation, PATCH /admin/residents/{resident}/portal-account, and GET /admin/document-requests/{documentRequest}/attachment.
Changed POST /portal/request and GET /portal/request/{referenceCode} authorization. /dashboard redirects resident accounts into the portal. Existing /login, /logout and password-reset routes are reused. Employee settings routes now reject resident accounts.

## 6. Authorization and security
Resident role is a separate discriminator and grants no administrative permissions. Protected resident actions check active account, linked non-archived resident, and active resident status. Existing policies still protect employee functions. Registration uses CSRF, server validation, password hashing, throttling, transactions, row locking and a unique database constraint. Client-supplied role, resident_id and identity cannot select privileges or another resident. Activation secrets and passwords are excluded from flashed input; no raw verification data is added to audit logs.

## 7. Verification behavior
Authorized staff first verifies the person in person and issues a random private activation code for an existing active resident. Only its SHA-256 hash is stored. The code expires after 24 hours; reissuing it replaces the previous code. Public verification requires resident number and code, and produces a server-side proof valid for 10 minutes. Eligibility, expiration and code rotation are rechecked when the account is created. Registration creates a User only and never a Resident.
An already registered record with a still-valid code receives safe login/recovery guidance without exposing email. The code cannot create another account; after expiry the generic assistance flow applies. Public verification/registration share limits of five attempts per minute and thirty per hour per IP.

## 8. Denied registration
Unknown records, wrong or expired codes, inactive records and archived records receive the same helpful assistance flow. It does not identify which personal field matched, reveal resident details, restore records or invent documentary requirements.

## 9. Login, profile and My Requests
Shared Fortify authentication retains session regeneration and existing employee login behavior. Resident logins return to the request flow and retain a valid selected service. My Requests queries through the authenticated resident relationship, showing reference, document, date, status, rejection reason and release guidance. Profile displays official identity read-only. Password recovery reuses Fortify.

## 10. Archive and restore
Existing SoftDeletes routes and archived filtering were reused. Archive date/status is displayed and restoration requires confirmation. Accounts and historical requests survive archive/restore. Archived residents cannot use protected resident functions; restoring an otherwise active record restores eligibility without recreating an account.

## 11. Document workflow
Online requests still use the same document_requests table, source=online, reference codes, statuses, admin processing, certificate issuance and QR verification. Name, birth date and address come from the official resident; email comes from their account. Purpose, business name and permitted attachments remain request-specific. New ID uploads use private local storage and an authorized Admin/Staff download endpoint. Existing attachments are retained.

## 12. Audit logging
Successful registration records portal.account.registered. Existing auth.login events remain, with resident logins identified as Resident portal sign in. Code issuance, account suspension/reactivation and existing archive/restore actions use administrative auditing. Suspension and reactivation have distinct events. No passwords, activation codes, password hashes or authentication secrets are included in these events. Admin resident details show masked email, account status, created date and last recorded login.

## 13. Files changed
- `app/CertificateType.php`
- `app/Http/Controllers/Admin/AuditLogController.php`
- `app/Http/Controllers/Admin/DocumentRequestController.php`
- `app/Http/Controllers/Admin/ResidentController.php`
- `app/Http/Controllers/Admin/UserController.php`
- `app/Http/Controllers/DocumentRequestController.php`
- `app/Http/Middleware/EnsureActiveAccount.php`
- `app/Http/Middleware/RecordAdministrativeAction.php`
- `app/Models/DocumentRequest.php`
- `app/Models/Resident.php`
- `app/Models/User.php`
- `app/Providers/AppServiceProvider.php`
- `app/Providers/FortifyServiceProvider.php`
- `bootstrap/app.php`
- `database/factories/UserFactory.php`
- `public/css/admin.css`
- `public/css/portal.css`
- `public/js/admin.js`
- `public/js/document-request.js`
- `public/js/portal-form.js`
- `public/js/status-checker.js`
- `resources/views/portal/index.blade.php`
- `routes/settings.php`
- `routes/web.php`
- `tests/Feature/DocumentRequestSubmissionTest.php`
- `tests/Unit/AdminClient.test.js`
- `tests/Unit/PortalClient.test.js`
- `app/Http/Controllers/Admin/ResidentPortalAccountController.php`
- `app/Http/Controllers/ResidentPortalController.php`
- `app/Http/Controllers/ResidentRegistrationController.php`
- `app/Http/Middleware/EnsureResidentAccount.php`
- `app/Http/Middleware/PreventAccountCaching.php`
- `app/Http/Requests/RegisterResidentAccountRequest.php`
- `app/Http/Requests/VerifyResidentAccountRequest.php`
- `app/Http/Responses/AccountLoginResponse.php`
- `database/migrations/2026_09_22_175723_add_resident_portal_accounts.php`
- `public/js/resident-account.js`
- `resources/views/layouts/portal.blade.php`
- `resources/views/partials/resident-nav.blade.php`
- `resources/views/portal/account.blade.php`
- `resources/views/portal/assistance.blade.php`
- `resources/views/portal/information.blade.php`
- `resources/views/portal/login.blade.php`
- `resources/views/portal/profile.blade.php`
- `resources/views/portal/register.blade.php`
- `resources/views/portal/registration-help.blade.php`
- `tests/Feature/ResidentPortalAccountTest.php`
- `tests/Unit/ResidentAccountClient.test.js`

## 14. Tests added or updated
Added ResidentPortalAccountTest.php for registration, eligibility, spoofing, duplicate constraints/proof reuse, throttling, role boundaries, recovery, audits, own history and logout. Updated DocumentRequestSubmissionTest.php for authenticated identity and private attachments. Added ResidentAccountClient.test.js and extended existing PortalClient/AdminClient tests for modal behavior, duplicate submission, draft clearing, browser-cache restoration and late-response privacy.

## 15-17. Verification and exact results
- php artisan test --compact: 208 tests total, 206 passed, 2 skipped, 0 failed; 1,003 assertions. The existing unrestricted Fortify registration tests are skipped because that registration feature remains disabled.
- node --test --test-isolation=none tests/Unit/*.test.js: 32 passed, 0 failed.
- php vendor/bin/phpstan analyse --no-progress --error-format=table: passed, 0 errors.
- php vendor/bin/pint --dirty --format agent: passed after formatting.
- npm run build: passed. Existing optional fontaine/optimized-font-fallback warning remains; dependencies were not changed. Initial sandbox process-spawn restriction was resolved by running the authorized build outside the sandbox.
- php artisan route:list --path=portal --except-vendor: routes inspected.
- php artisan view:cache --no-interaction: successful.
- git diff --check: successful.
- php artisan migrate --path=database/migrations/2026_09_22_175723_add_resident_portal_accounts.php --no-interaction: local additive migration completed.
- Local HTTP checks: /portal, /portal/register, /portal/login and /portal/information returned 200 with no-store headers. A guest visiting /portal/account redirected to /portal/login.

## 18. Remaining limitations
Actual browser visual/mobile/keyboard inspection was not performed; JavaScript tests use a simulated DOM. Registration depends on authorized staff correctly verifying the person and privately handing over the code. Mailbox ownership is not additionally verified by this flow, and live password-reset delivery depends on the existing mail configuration; recovery tests use fake notifications. Historical public attachment files were not moved or deleted and retain their previous storage exposure; only new uploads use the protected path. Historical unlinked requests are not automatically reassigned based on names or birthdays. Production migration and deployment remain deliberately unperformed.

## 19. Official information awaiting confirmation
Current officials, documentary requirements, contact numbers, processing times and current office hours/policies need barangay confirmation. The new information page marks missing details accordingly. Fees come from CertificateType, with confirmation wording. Existing portal hours/terms were preserved and were not independently validated.

## 20. Recommended manual localhost smoke tests
1. As staff, open an existing active resident and issue an activation code after verifying the person. Confirm the code disappears when the modal closes.
2. In a separate/private browser session, register using that resident number/code, log in and confirm the read-only profile matches the official record.
3. Request a supported document with purpose and an allowed attachment; confirm it appears in My Requests and the existing admin processing queue with source=online.
4. Process/reject/release a request through the normal employee workflow; confirm the resident sees the appropriate own-request status and existing issuance/QR behavior remains usable.
5. Suspend/reactivate the account as Admin and archive/restore the resident as authorized staff; check access is denied/restored without losing history.
6. Log out, use Back, refresh, and switch accounts; verify no previous resident identity, request content or attachment preview remains visible.
7. Check mobile widths, light/dark mode, keyboard navigation, form errors and logout cancellation in a real browser.

## 21. Remaining issue fixes (2026-10-02, local working tree)

The Request History page previously rendered database statuses only at page load. It now polls a resident-authenticated, rate-limited endpoint every five seconds for the IDs on the current page. The endpoint reads only that resident's requests and returns the existing Blade card markup. The browser replaces a card only when its status, remarks, or rejection reason changes. Polling pauses in hidden tabs, prevents overlapping calls, retries network failures after 15 seconds, and reloads if account access is revoked. The existing pagination and status workflow are retained.

Administrative resident creation and update previously used a first-name, last-name, and birth-date warning that staff could override even for an exact duplicate. The approved rule now rejects an exact match on normalized first, middle, and last name, suffix, and birth date, including archived records. A same first/last name and birth date with a different middle name or suffix still presents the existing staff confirmation path. A cache lock serializes matching create and update operations when the configured cache store is shared. No migration, dependency, notification feature, or existing resident row was changed.

### Test record

The affected PHP feature run passed 144 tests and 859 assertions. The JavaScript run passed 53 tests. The individual new or changed behavior checks are recorded below; `ResidentPortalAccountTest.php` and `ResidentControllerTest.php` are under `tests/Feature`, and `RequestHistoryClient.test.js` is under `tests/Unit`.

| Test ID | Test date | Expected result | Actual result | PASS/FAIL | Supporting evidence |
| --- | --- | --- | --- | --- | --- |
| AT-16-01 | 2026-10-02 | Polling returns own selected requests only. | Other resident and unselected request absent. | PASS | `ResidentPortalAccountTest.php`: request history polling returns only visible requests owned by the resident. |
| AT-16-02 | 2026-10-02 | Page 2 renders one of 16 requests with polling available. | Page 2 and one request card rendered. | PASS | `ResidentPortalAccountTest.php`: request history keeps its second page while exposing only that page to polling. |
| AT-16-03 | 2026-10-02 | Admin approval appears with escaped remarks. | Approved card and escaped text returned. | PASS | `ResidentPortalAccountTest.php`: request history polling reflects administrative status changes and escapes private remarks. |
| AT-16-04 | 2026-10-02 | Guests, staff, ineligible accounts, and oversized ID lists are rejected. | 401, 403, and 422 paths passed. | PASS | `ResidentPortalAccountTest.php`: request history polling requires an eligible resident account and a bounded set of ids. |
| AT-16-05 | 2026-10-02 | Poll every five seconds and update changed rows only. | Unchanged row untouched; changed row updated. | PASS | `RequestHistoryClient.test.js`: polls visible request ids and changes only rows with a new version. |
| AT-16-06 | 2026-10-02 | Hidden tabs pause and requests do not overlap. | No hidden timer or overlapping fetch. | PASS | `RequestHistoryClient.test.js`: pauses in hidden tabs and prevents overlapping requests. |
| AT-16-07 | 2026-10-02 | Network error backs off; navigation stops polling. | 15-second retry scheduled, then canceled. | PASS | `RequestHistoryClient.test.js`: retries network failures slowly and stops after navigation. |
| AT-16-08 | 2026-10-02 | Revoked access stops polling and reloads authorization. | One reload and no next timer. | PASS | `RequestHistoryClient.test.js`: reloads when resident access is revoked and does not keep polling. |
| AT-22-01 | 2026-10-02 | Exact duplicate is rejected even with confirmation. | 422 and one resident retained. | PASS | `ResidentControllerTest.php`: rejects an exact resident duplicate even when staff confirms it. |
| AT-22-02 | 2026-10-02 | Similar resident requires confirmation; a distinct middle name can be saved. | Warning then successful confirmed save. | PASS | `ResidentControllerTest.php`: requires staff confirmation for a similar resident but permits a distinct middle name. |
| AT-22-03 | 2026-10-02 | Different birth date can be saved. | Second resident created. | PASS | `ResidentControllerTest.php`: allows the same first and last name when the birth date differs. |
| AT-22-04 | 2026-10-02 | Case and whitespace variants of an exact match are rejected. | 422 and one resident retained. | PASS | `ResidentControllerTest.php`: detects a matching identity despite case and whitespace differences. |
| AT-22-05 | 2026-10-02 | Archived exact match is rejected with restore guidance. | 422 and archived row retained. | PASS | `ResidentControllerTest.php`: does not permit a new record to replace an archived exact match. |
| AT-22-06 | 2026-10-02 | An update cannot duplicate another identity. | 422 and both original rows unchanged. | PASS | `ResidentControllerTest.php`: rejects a resident update that would duplicate another exact identity. |
| TC-16 | 2026-10-02 | A resident sees an administrator's status change on Request History within about five seconds without refreshing, including on a paginated page. | Two-session localhost browser retest has not been performed; prior failed classification is retained. | FAILED | No manual browser evidence yet; automated evidence is AT-16-01 through AT-16-08. |
| TC-22 | 2026-10-02 | Staff cannot create an exact duplicate, can confirm a distinct similar resident, and sees clear messages. | Staff browser retest has not been performed; prior failed classification is retained. | FAILED | No manual browser evidence yet; automated evidence is AT-22-01 through AT-22-06. |
| TC-23: Resident Notifications | 2026-10-02 | N/A, excluded from the approved capstone scope. | No notification functionality was implemented or tested. | N/A (Out of Scope) | Approved scope in the 2026-10-02 task; this is not a software defect. |

### Manual localhost retest instructions

1. In separate browser profiles, sign in as an administrator and as a resident with at least one document request. Open the resident's My Requests page; leave it visible without refreshing.
2. In the administrator profile, change that request from Pending to Approved. Record both timestamps and capture the resident card changing to Approved, including its explanatory text. Repeat with Ready for release or Rejected to check the callout and reason.
3. Check a resident history page with more than 15 requests. Stay on page 2, change one of its visible requests as administrator, and confirm that the page number and other cards remain in place. Check the browser Network panel for one status request about every five seconds, no overlapping calls, and no calls while the tab is hidden.
4. Temporarily disable the resident browser network, restore it, and confirm polling resumes. Sign out or suspend the account and confirm protected history is no longer shown.
5. In the staff resident form, try an exact match (including Confirm Duplicate), an archived exact match, a similar name with a different middle name, and a different birth date. Capture validation or confirmation messages and verify existing rows are unchanged. Use test records only in a local test environment.

The PHP tests used in-memory SQLite; no localhost browser session or simultaneous multi-process registration test was performed. The cache lock needs a shared lock-capable cache store across application workers; without a database uniqueness constraint, it does not protect writes that bypass the application. The approved exact-match rule can also reject two real people who share the same full name and birth date; staff must investigate such a case. This task intentionally made no restrictive schema change. The full PHP suite remains for the project owner to run with `php artisan test --compact`.
