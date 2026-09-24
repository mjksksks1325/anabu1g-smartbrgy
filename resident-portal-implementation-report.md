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
