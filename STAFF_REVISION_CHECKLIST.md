# Staff Workspace Revision Checklist

Working list for the Version 2 staff-side UI/UX and functional revision (branch `feature/version-2-ui-ux`, local only).
Update it at the end of every session. Statuses come from the source code, not from earlier reports.

**Status tags:** `DONE` already in the code before this revision · `NEW` fixed in this revision · `PARTIAL` · `TODO` not implemented or not yet audited · `BUG` verified defect · `APPROVAL` needs sign-off before work starts

## Where things live

- Staff workspace: one Blade view, `resources/views/admin/dashboard.blade.php`, switched per screen by `WorkspaceController` (`$activeScreen`). Client logic: `public/js/admin.js` (~5.4k lines, global functions, inline `onclick`).
- Styles load in this order: `admin.css` → `government.css` → `figma-tokens.css` → `figma-admin.css` → `form-feedback.css` → `staff-theme.css` (design tokens and the current design layer; add new rules here).
- IoT pages (RFID, smart cabinet, employee cabinet access): `layouts/admin-iot.blade.php` + `resources/views/admin/iot/*`, server-paginated.
- Account settings: Livewire pages under `/staff/settings/*` (`routes/settings.php`).
- Language: `lang/en.json` and `lang/fil.json`; `public/js/admin-localization.js` translates rendered text by matching it against both catalogs (supports `:placeholder` patterns). New UI strings need an entry in both files; `tests/Unit/AdminLocalizationCatalog.test.js` enforces this for `admin.js` and `household-profiling.js`.
- Shared helpers in `admin.js`: `openModal` / `closeModal` / `topOpenModal` (dialog stack), `confirmAction` / `confirmDestructiveAction` (app confirmation dialog), `showToast`, `renderPager` (list pager). Field validation: `public/js/form-feedback.js` (`FormFeedback.showErrors`).
- Tests: Pest (`php artisan test --compact <file>`, PHP via Herd) and Node (`node --test tests/Unit/*.test.js`).

## Known failing tests (pre-existing, not caused by this revision)

- `tests/Unit/AdminOperationsUiTest.php`: 4 tests assert markup an earlier redesign changed (`IoT status` label, audit grid columns, footer Close button in `modal-hardware-status`, `modal-cert-fees` id). Decide per test: update the assertion or restore the markup.
- `tests/Feature/HouseholdControllerTest.php`: 3 tests call archive/remove routes without the supervisor password that `RequireSupervisorPassword` now requires.
- `tests/Unit/ResidentModuleUiTest.php`: 1 test expects an older `changeResidentArchiveState(...)` call string.
- Node: `CaseManagementClient` (incident filters, 1) and `PersonnelNavigation` (3, test stubs lack `addEventListener` / `clearTimeout`).

## 1. General

| Item | Status | Notes |
|---|---|---|
| English/Filipino consistency | `PARTIAL` | `NEW`: 79 untranslated staff JS messages catalogued; hard-coded Filipino in the theme toast and supervisor-password prompt now use English source text; attribute translation keeps `:placeholder` values; guard test added. `TODO`: mixed-language Blade labels (e.g. "Full Name ng Residente *", "Lahat ng Purok", Eligibility screen). |
| Printable reports and exports | `TODO` | Not audited. Keep CSV machine-readable. |
| Import duplicate detection | `TODO` / `APPROVAL` | Profiling import has preview → review → store (`HouseholdProfilingImportController`). Audit its current matching before changing; identity rules need approval. |
| Inline form validation | `DONE` | `form-feedback.js`: messages beside fields, red borders, focus + scroll to first error, reduced-motion-safe shake, maps 422 JSON errors to fields. `NEW`: errors now target the top dialog when dialogs are stacked. |
| Dialogs and confirmations | `DONE` / `NEW` | No browser `confirm()`/`alert()` remain (`confirmAction`). `NEW`: dialog stack — Esc and the Tab trap act on the top dialog, and focus returns to the control that opened each dialog. |
| Pagination | `NEW` / `PARTIAL` | `NEW`: one `renderPager` for residents, voters, incidents, request records, issued certificates, households and household residents ("Showing a–b of N", Page x of y, nav landmark, translatable). `TODO`: audit pager and dashboard activity pager still separate; IoT Blade pages use Laravel `links()` with different styling. |
| Toasts | `NEW` | Success/error toasts no longer show an info icon. |
| Notification panel close | `NEW` | Was a non-focusable `<span>`; now a labelled button. |

## 2. Demographics

| Item | Status | Notes |
|---|---|---|
| KPI accent colours | `DONE` | `demo-kpi-*` classes (covered by `AdminOperationsUiTest`). |
| Modal close controls | `DONE` / `BUG` | 24 dialogs use the standard X button; `modal-hardware-status` still has a footer Close (failing test above). |
| Add Purok errors on the field | `NEW` | Removed the required "Purok ID / Key" field whose value was never sent; name and colour have real labels. |
| Duplicate Purok names | `NEW` | Duplicate check ignores letter case and extra spaces (`StorePurokRequest`); message shown on the name field. |
| Purok panels, empty space, charts | `TODO` | |
| Special Groups link to filtered records | `DONE` | `openSpecialGroupRecords`. |

## 3. Resident Records

| Item | Status | Notes |
|---|---|---|
| Toolbar (Search, New, Households, Import, Export) | `PARTIAL` | All present in one row; export button reads "Export Excel" — confirm the format and label. |
| Special-group filters | `PARTIAL` | Status pills + group dropdown exist (`filterResidentSpecialGroup`); Senior Citizen is a status pill. Review grouping. |
| Row opens details | `DONE` | Rows are keyboard-operable. |
| Native confirmations | `DONE` | |
| Contact-number validation and format | `TODO` | Audit `StoreResidentRequest` / `UpdateResidentRequest` vs stored format. |
| Pagination inside Edit Resident form | `TODO` | No stepper markup found; verify in the browser what the user saw. |
| Long-form organisation | `TODO` | |

## 4. Voters

| Item | Status | Notes |
|---|---|---|
| Actions beside search | `DONE` | Covered by `AdminOperationsUiTest`. |
| KPI colours | `DONE` | |
| Table readability | `TODO` | |
| CSV import/export | `PARTIAL` | Endpoints and `VoterImportTest` exist; verify in the browser. |

## 5. Certificates and Clearances

| Item | Status | Notes |
|---|---|---|
| Process flow redesign | `DONE` | 5-step `cert-flow-step` list. |
| KPI colours | `DONE` | |
| Request Board layout | `TODO` | |
| Issued history pagination + filters | `DONE` | `NEW`: uses the shared pager. |
| Duplicate fees cards / modal entry points | `DONE` | One "Certificates & Fees" entry (`openCertificateFees`); fees list lives in the issue dialog. |

## 6. Request Eligibility

| Item | Status | Notes |
|---|---|---|
| Checker rejects valid input | `BUG` (unconfirmed) | Client labels match `CertificateType`. Suspects: options keyed by `resident_number` (`findEligibilityResident`), list limited to first 100 from `/staff/case-residents`, and only one resident listed after opening from a request record. Reproduce first. |
| KPI cards top, filters below | `TODO` | |

## 7. Incident Reports — `TODO` (one search, filter alignment, record view; keep VAWC permissions).

## 8. RFID File Tracking

Pagination exists (server). `TODO`: search above Movement History, inline filters, drop redundant Recorded Events KPI and Filter Events button.

## 9. Employee Cabinet Access

Pagination exists (server, hidden when one page). `TODO`: employee details in a dialog, access-status clarity.

## 10. Audit Log

Pagination and a detail dialog exist (`openAuditDetail`); records read-only. `TODO`: Category instead of Type in filters, shared pager styling, search/filter layout.

## 11. User Management

| Item | Status | Notes |
|---|---|---|
| Role filter | `TODO` | Options are admin / captain / staff / viewer; check against the roles and staff assignments actually stored. |
| Clickable cards, nested buttons | `TODO` | Cards have keyboard handlers; audit nested buttons. |
| Suspension confirmation | `TODO` | Verify it uses `confirmAction`. |

## 12–14. Account, Settings, My Profile, Security

`TODO` audit. Known destinations: workspace Settings screen (`/admin/settings`, Super Admin), account Settings (`/staff/settings/profile`, `appearance`, `security`), user-menu "Settings" → profile, sidebar "Open Portal" → resident portal in a new tab. Never remove password, session, 2FA or recovery-code features.

## 15. Notifications

| Item | Status | Notes |
|---|---|---|
| Click opens the record | `DONE` | Notifications are built from dashboard activity (document requests, incidents) and open the same record. |
| "View all in Audit Log" | `DONE` | No longer present. |
| Read state | `PARTIAL` | Kept in the browser only; resets on reload. |
| Persistent / role-targeted notifications | `APPROVAL` | Needs a table and recipient rules. |

## Items requiring approval

1. **Resident/household import matching** — define what counts as the same person (proposal: resident number when present; otherwise normalised last + first + middle name **and** date of birth, flagged for review rather than skipped silently). Affects `HouseholdProfilingImportController`.
2. **Case-insensitive unique index on `puroks.name`** — the app-level check is in place; a database index would need a migration.
3. **Persistent notifications** — new table, read state per user, recipient rules per permission; must exclude VAWC details.
4. **Stale tests** — the 8 failing PHP tests above: update the assertions to the current design, or restore the markup.

## Session log

- **Session 1 (2026-11-03)** — Work group 1 (shared UI and validation): dialog stack, shared pager, catalogue coverage and guard test, Add Purok fixes, toast and notification-close fixes. Next: work group 2 (Demographics panels/charts, Resident Records contact-number and form layout, Voters table, import audit).
