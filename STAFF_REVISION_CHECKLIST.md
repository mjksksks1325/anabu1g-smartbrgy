# Staff Workspace Revision Checklist

Working list for the Version 2 staff-side UI/UX and functional revision (branch `feature/version-2-ui-ux`, local only).
Update it at the end of every session. Statuses come from the source code, not from earlier reports.

**Status tags:** `DONE` already in the code before this revision · `NEW` fixed in this revision · `PARTIAL` · `TODO` not implemented or not yet audited · `BUG` verified defect · `APPROVAL` needs sign-off before work starts

## Where things live

- Staff workspace: one Blade view, `resources/views/admin/dashboard.blade.php`, switched per screen by `WorkspaceController` (`$activeScreen`). Client logic: `public/js/admin.js` (~5.4k lines, global functions, inline `onclick`). Dialogs sit outside `#app`; scope dialog styles with `body[data-personnel-layout="admin"] .modal`.
- Styles load in this order: `admin.css` → `government.css` → `figma-tokens.css` → `figma-admin.css` → `form-feedback.css` → `staff-theme.css` (design tokens and the current design layer; add new rules here). Watch specificity: `body.light-mode #app…` rules in `figma-admin.css` beat plain `body #app…` rules.
- The staff theme maps `--blue-*` and `--green-*` tokens to brand greens. Use explicit colours where categories must differ.
- IoT pages (RFID, smart cabinet, employee cabinet access): `layouts/admin-iot.blade.php` + `resources/views/admin/iot/*`, server-paginated.
- Account settings: Livewire pages under `/staff/settings/*` (`routes/settings.php`).
- Language: `lang/en.json` and `lang/fil.json`; `public/js/admin-localization.js` translates rendered text by matching it against both catalogs (`:placeholder` patterns allowed; keep patterns specific, because a broad one such as `:count households` rewrites unrelated text). `tests/Unit/AdminLocalizationCatalog.test.js` checks `admin.js` and `household-profiling.js` messages.
- Shared helpers in `admin.js`: `openModal` / `closeModal` / `topOpenModal` (dialog stack), `confirmAction` / `confirmDestructiveAction`, `showToast`, `renderPager` (list pager), `hasStaffPermission`. Field validation: `public/js/form-feedback.js`.
- Tests: Pest and Node (`node --test tests/Unit/*.test.js`). The full Pest suite needs more memory than the default: `php -d memory_limit=1G vendor/pestphp/pest/bin/pest --compact`. Destructive-route tests send `supervisorApproval()` (see `tests/Pest.php`).
- Browser checks: run the app against a **copy** of `database/database.sqlite` (set `DB_DATABASE` and start `php -S 127.0.0.1:8010 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php` from `public/`; `artisan serve` drops the override) and sign in with a seeded local staff account. Never seed test data into the real local database.

## Test status (2026-11-03, after `e56efce`)

- Node: 182 pass, 0 fail.
- Pest: 988 pass, 14 fail, 2 skipped. All 14 are in account settings, profile and navigation (Work Group 5). They need a decision on the intended design, not a quick assertion change:
  - `GovernmentPagesTest`: expects the removed top-bar `IoT status` chip and a `My profile` menu item; sidebar icon count 15 vs 16; account role shown as `Super Admin` where the test expects `admin` (×4 settings/IoT pages); sidebar scroll container.
  - `Settings/ProfileUpdateTest`: settings sidebar icon count 15 vs 16 (×2); `Account security` link order; **account deletion now asks for the active Super Admin password** (`DeleteUserForm`, changed in `e7f1aa7`), and the test still sends the user's own password.
  - `PasswordVisibilityTest` and `PersonnelPasswordConfirmationTest`: expected password toggles and security links not found on the dashboard.

## 1. General

| Item | Status | Notes |
|---|---|---|
| English/Filipino consistency | `NEW` / `PARTIAL` | 79 staff JS messages and about 70 Demographics/Resident Records labels catalogued; hard-coded Filipino source text replaced; placeholder fill order fixed (`:to` no longer breaks `:total`, seen as "2tal" in the browser). `TODO`: remaining screens (Certificates, Incidents, IoT, Users, Settings) need the same browser audit. |
| Printable reports and exports | `PARTIAL` | Resident and voter exports are branded `.xlsx` files (`WriteRecordsSpreadsheet`), verified in the browser. The buttons say "Export Excel" / "Export List". Printable reports not yet reviewed. |
| Import duplicate detection | `NEW` | Browser-verified end to end; see Decisions → Import. |
| Lists ignore stale responses | `NEW` | Resident and voter lists render only the newest request (a slow earlier search used to be able to replace newer results) and keep their rows while refreshing. |
| Inline form validation | `DONE` / `NEW` | Existing `form-feedback.js`; errors target the top dialog when dialogs are stacked. |
| Dialogs and confirmations | `DONE` / `NEW` | Dialog stack: Esc closes the top dialog and focus returns to its opener (browser-verified). Hardware-status and user-details dialogs now close only through the X like every other dialog. |
| Pagination | `NEW` / `PARTIAL` | Shared `renderPager` on residents, voters, incidents, request records, issued certificates, households, household residents and the senior register (browser-verified, both languages). `TODO`: audit and dashboard-activity pagers; IoT Blade pages use Laravel `links()`. |
| Buttons | `NEW` | One primary per toolbar on Resident Records and Voters; Archive shows the intended red outline. |

## 2. Demographics — `NEW` (browser-verified light, dark, Filipino, 390 px)

| Item | Status | Notes |
|---|---|---|
| KPI accent colours | `NEW` | Classes existed but a higher-specificity default made every card green; now six distinct accents. Two cards per row on phones. |
| Modal close controls | `NEW` | |
| Add Purok validation | `NEW` | Dead "Purok ID / Key" field removed; case/space-insensitive duplicate check with the error on the field. |
| Panels balanced, empty space | `NEW` | Age panel gains a male/female split; Special Groups is a compact ranked list; panels align to the top. |
| Special Groups linked to records | `DONE` / `NEW` | Rows are buttons only for staff with `records.view` (Kagawad has `demographics.view` without it). |
| Charts | `NEW` | Thicker bars, ordered blue scale for age bands, amber for 60+, distinct group colours. |
| Redundancy | `NEW` | Removed the repeated "Automated Process" box and the households subtitle that repeated the card's own number. |
| Senior register | `NEW` | Ten per page. Its data source returns at most 100 seniors; a note says so when there are more. |

## 3. Resident Records

| Item | Status | Notes |
|---|---|---|
| Toolbar | `NEW` | Search · New Resident (primary) · Manage Households · Import · Export Excel (secondary). The applications link sits in the page header. |
| Special-group filters | `DONE` | Status buttons + one special-group dropdown (pinned by `AdminOperationsUiTest`). |
| Filter pills | `NEW` | Buttons with `aria-pressed`, labelled group (were `div onclick`). |
| Row opens details | `DONE` | Row actions now neutral View/Edit plus red Archive. |
| Contact number | `NEW` | Both `09…` and `+639…` accepted; stored as `09XXXXXXXXX` (form and profiling import). Existing stored values unchanged. |
| Pagination inside Edit form | `DONE` | None present (verified). |
| Long-form organisation | `NEW` | Four sections: Personal information, Address and contact, Sectors and record status, Household. |

## 4. Voters — `NEW` (browser-verified desktop, 390 px, English/Filipino)

| Item | Status | Notes |
|---|---|---|
| Actions beside search | `DONE` / `NEW` | Add Voter primary; Import CSV and Export List secondary. |
| KPI colours | `NEW` | Same specificity fix as Demographics. |
| Filters and pagination | `NEW` | Age buttons with `aria-pressed`; shared pager (18 voters, 2 pages). |
| Record actions | `NEW` | Edit for staff with `voters.update` (existing PATCH endpoint, version check, audit record). Resident shown read-only; COMELEC number kept unless retyped (masked hint); status explained (non-active statuses leave this active-only list). Rows failing the integrity check show "Locked for review". |
| Validation | `NEW` | Fields carry request names, so errors appear on the field; precinct, cluster and COMELEC formats say which characters are allowed. |
| Language | `NEW` | Eligibility badges and placeholders follow the selected language. |
| Empty / loading / error states | `DONE` / `NEW` | Empty state verified; loading row only on first load. |
| CSV import / export | `DONE` | Browser-verified: 2 imported + 1 skipped as already registered; a file with an unknown resident number is rejected whole with the row named (existing all-or-nothing rule). Export downloads `.xlsx`. |
| Phones | `PARTIAL` | No page overflow; the table scrolls inside its card. A card layout for phones could come later. |

## 5. Certificates and Clearances

Process flow (5 steps), KPI colours, paged/filtered issued history and a single "Certificates & Fees" entry (fees inside the issue dialog) are `DONE`. `TODO`: Request Board layout and heading sizes.

## 6. Request Eligibility

| Item | Status | Notes |
|---|---|---|
| Checker rejects valid input | `NEW` (fixed `e56efce`) | Reproduced in the browser: any resident data reload while the checker is open (workspace start-up, resident saves, imports, Resident Records filters) rebuilt the list and dropped the chosen resident, so Check showed "Select a resident." The list now keeps the current choice. Certificate labels, resident-number keys and the request-record path were tested and were not the cause. |
| Residents beyond the first 100 | `NEW` (fixed `e56efce`) | Verified with 117 residents: the list comes from a lookup capped at 100 by surname, so later residents could not be chosen at all. A search field queries the same endpoint by name or resident ID. Eligibility rules unchanged. |
| KPI cards top, filters below | `TODO` | |

## 7. Incident Reports — `TODO` (one search, filter alignment, record view; keep VAWC permissions). Search already covers incident type; the exact-category filter was removed on purpose (`e7f1aa7`).

## 8. RFID File Tracking — pagination `DONE`; `TODO`: search above Movement History, inline filters, drop the redundant Recorded Events KPI and Filter Events button.

## 9. Employee Cabinet Access — pagination and a details dialog per employee `DONE`; `TODO`: list usability and access-status clarity.

## 10. Audit Log — pagination, detail dialog and a four-column layout with Category first `DONE`; `TODO`: shared pager styling, search/filter layout.

## 11. User Management — `TODO`: role filter vs stored roles (admin / captain / staff / viewer options), nested buttons in cards, suspension confirmation.

## 12–14. Account, Settings, My Profile, Security — `TODO` audit together with the 14 failing tests above. Destinations: workspace Settings (`/admin/settings`), account settings (`/staff/settings/profile`, `appearance`, `security`), user-menu "Settings", sidebar "Resident Portal" (opens the portal in a new tab). Never remove password, session, 2FA or recovery-code features.

## 15. Notifications — `PARTIAL` (client-side only; see proposal below).

## Decisions and proposals

### Import (approved 2026-11-03) — `NEW`

- Verified: `residents.resident_number` has a unique index and is system-generated (`ANB-YYYY-XXXXXXXXXX`).
- Profiling import (`ImportHouseholdProfiling`) now reads an optional `resident_number` column (aliases `Resident ID`, `Resident No`):
  - number exists and matches the row's first name, last name and birth date → confirmed match: skipped by default, or linked to a household; never created again;
  - number unknown, or belongs to someone else → row invalid with the reason; nothing is guessed.
- Rows without a number keep the existing rules: same first/last name + birth date is a possible duplicate that staff must resolve (link, create after confirming, or skip); exact matches are skipped; duplicates inside the file are flagged; records are never overwritten.
- Summary before saving: labelled counts (invalid in red, duplicates in amber, ready in green). After saving, the dialog stays open with a lasting result: created, linked, skipped, households saved.
- Unreadable birth dates stay in the row for correction ("must be a valid date"); contact errors use the resident form's message.
- **What a resident number is (verified):** not fillable, generated only when a record is created (`ANB-YYYY-` + 10 random characters), never backfilled. Every valid number was issued by this application. An unknown number therefore cannot refer to any record, and the voter import already treats it as an error. In the profiling import such a row is held for review, not rejected: clearing the number in the preview turns it into an ordinary new-resident row (browser-verified). No matching rule was changed.
- **Browser test** (isolated copy, 7-row file): 2 valid new rows ready; resident-number match skipped automatically; same name + birth date with a different middle name required a decision; duplicate inside the file flagged; invalid row listed with reasons; unknown number flagged; Save blocked until all were resolved. After saving, 4 residents were created, the matched resident was linked as household head with address and contact unchanged, the possible-duplicate's existing record was untouched, and contact numbers were stored as `09…`.
- No decision remains open for import matching.

### Purok name uniqueness at the database level — `APPROVAL`

- Findings: app-level check (case- and space-insensitive) is in place. Local data: 5 puroks, no collisions, all names normalised. Production uses MySQL with `utf8mb4_unicode_ci`, so the existing `unique(name)` index is **already case-insensitive there**. The gap is SQLite (local development and tests), where `unique` is case-sensitive.
- Proposed migration (not created):
  1. Stop with a clear message listing names if `SELECT LOWER(TRIM(name)), COUNT(*) FROM puroks GROUP BY 1 HAVING COUNT(*) > 1` returns rows. Nothing is changed or merged automatically.
  2. SQLite: `CREATE UNIQUE INDEX puroks_name_lower_unique ON puroks (lower(name))`.
  3. MySQL: read the column collation; if it ends in `_ci`, do nothing; if it is binary/case-sensitive, stop and report.
  4. `down()` drops the SQLite index only.
- Recommendation: optional, since production is already protected. Apply it only if you want local SQLite to behave like production.

### Saved notifications — `APPROVAL` (design only)

- **Today:** the bell lists the dashboard's recent activity (document requests, incidents the user may see via `Incident::visibleTo`, pending resident applications). Read state lives in the browser and resets on reload. Audit logs are separate and stay separate.
- **Events (existing data only):** new online document request; document request status change (processing, ready for release, released, rejected); new incident report; incident status change; incident assigned to a user; new resident registration application.
- **Recipients by existing permission:** document events → `documents.view` (status changes that need action → `documents.process`); ordinary incidents → `incidents.view`; sensitive/VAWC incidents → `vawc.view` only; assignment → the assigned user only; applications → `records.review`. No new permissions.
- **Read state:** Laravel's database notifications (`notifications` table: uuid, type, notifiable, data JSON, `read_at`). "Mark all read" becomes a PATCH that sets `read_at`.
- **Links:** store record type + id + reference and open the existing screen; the target endpoints already authorize, and the list hides items whose record the user can no longer see.
- **Sensitive data:** store only the reference number and a generic title ("Sensitive case updated") for VAWC/sensitive incidents; never names, narratives or incident types. Resident names appear only for document requests and applications, which their recipients can already see.
- **Duplicates:** one notification per user per (event, record, new status); repeated saves without a status change send nothing.
- **Database changes:** the standard `notifications` table (`php artisan make:notifications-table`) plus an index on (`notifiable_id`, `read_at`). No changes to existing tables. Optional pruning of read items after 90 days.
- **Needs approval:** the event list, recipient rules, and retention.

## Session log

- **Session 1 (2026-11-03)** — Work group 1: dialog stack, shared pager, catalogue coverage and guard test, Add Purok fixes, toast and notification-close fixes (`fd10c70`).
- **Session 2 (2026-11-03)** — Browser verification of work group 1 against a scratch database copy (found and fixed the "2tal" translation bug, `2c6655d`). Repaired outdated tests and a real navigation stylesheet regression; destructive-route tests now send a valid supervisor password (`0d611b4`). Resident-number import matching (`f10b9be`). Work group 2: Demographics, Resident Records and Voters layouts, contact-number storage, Filipino coverage (`c8034f5`). Next: remaining work group 2 items (Voters table, import/export in the browser), then work group 3 (Eligibility bug first).
- **Session 3 (2026-11-03)** — Work group 2 finished: Voters edit action, validation and language; stale-response guard on resident and voter lists; import summary, lasting result and clearer errors; profiling and voter imports browser-tested on an isolated copy (`4339f7b`). Work group 3 started: Eligibility Checker bug reproduced and fixed, plus resident search beyond 100 (`e56efce`). Next: Request Eligibility layout (KPI cards top, filters below), then Certificates Request Board and Incident Reports.
