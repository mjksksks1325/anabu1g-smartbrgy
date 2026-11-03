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

## Test status (2026-11-04, final review)

- Node: 190 pass, 0 fail.
- Pest: 1014 pass, 0 fail, 2 skipped. The skipped tests are the starter kit's open staff registration tests, intentionally off; see Final review.
- The 14 earlier failures were resolved as follows:
  - **Outdated tests (11):** they expected UI a teammate removed on purpose (`8e4e1a0`, `e7f1aa7`): the IoT status chip, the "My profile" and "Account security" items, 16 menu icons, and the raw role `admin` instead of `Super Admin`. One test also checked for `wire:navigate` where the link now uses `wire:navigate.hover`. These tests now assert the consolidated design.
  - **Real bugs (2):**
    - The supervisor-password field had no show/hide toggle (`PasswordVisibilityTest`).
    - The dashboard had no link to Password & security (`PersonnelPasswordConfirmationTest`). It now has one in the account menu.
  - **Security mismatch (1):** the account-deletion test sent the user's own password, but deletion requires an active Captain or Super Admin password. The test now uses a captain's password, and a new test checks that the user's own password is refused. Requirement unchanged.

## 1. General

| Item | Status | Notes |
|---|---|---|
| English/Filipino consistency | `NEW` | 79 staff JS messages and about 70 Demographics/Resident Records labels catalogued; hard-coded Filipino source text replaced; placeholder fill order fixed (`:to` no longer breaks `:total`, seen as "2tal" in the browser). Certificates, Incidents, IoT, Users, Settings and notifications audited in Filipino in work groups 3–5. |
| Printable reports and exports | `PARTIAL` | Resident and voter exports are branded `.xlsx` files (`WriteRecordsSpreadsheet`), verified in the browser. The buttons say "Export Excel" / "Export List". Printable reports not yet reviewed. |
| Import duplicate detection | `NEW` | Browser-verified end to end; see Decisions → Import. |
| Lists ignore stale responses | `NEW` | Resident and voter lists render only the newest request (a slow earlier search used to be able to replace newer results) and keep their rows while refreshing. |
| Inline form validation | `DONE` / `NEW` | Existing `form-feedback.js`; errors target the top dialog when dialogs are stacked. |
| Dialogs and confirmations | `NEW` | Dialog stack: Esc closes the top dialog, Tab stays inside it, and focus returns to its opener. Information and form dialogs close through the labelled X. Confirmations (shared confirmation, Reject request, Lift restriction, and the native archive, deactivation and logout dialogs) have a labelled Cancel next to a coloured action. Destructive confirmations open on Cancel; supervisor-password confirmations open on the password field. |
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

## 5. Certificates and Clearances — `NEW` (browser-verified light, dark, Filipino, 390 px; `e877a4b`)

| Item | Status | Notes |
|---|---|---|
| Process flow | `DONE` / `NEW` | 5-step flow existed; step icons were invisible (icon colour overridden) and are now soft tiles with coloured icons, readable in dark mode. |
| KPI colours | `DONE` | |
| Request Board | `NEW` | Lane titles in dark text with colour dots matching the summary cards (Ready and Released were both green before); first lane renamed "New online requests" (it differed from the "Online Requests" KPI, which counts every online submission); taller lanes; card actions in two rows, labels never wrap. Pending walk-in requests appear under Processing by design (staff enter them at the counter); unchanged. |
| Issued history | `DONE` / `NEW` | Paging and filters existed; Reprint is neutral, dates show date and time on two lines, cells no longer wrap mid-number, date filters labelled From/To. |
| Certificates & Fees entry | `DONE` | Already one entry point (fees inside the issue dialog); not recreated. |
| Header "Public Portal" button | `BUG` → `NEW` (removed) | Its overlay no longer existed, so the click threw an error; the leftover code created fake requests in the browser only. The sidebar "Resident Portal" link remains. |
| Fees, templates, QR, issuance rules | unchanged | |

## 6. Request Eligibility — `NEW` (browser-verified; `e56efce`, `e877a4b`)

| Item | Status | Notes |
|---|---|---|
| Layout | `DONE` / `NEW` | KPI cards were already above search; now one toolbar (search + standing filters), and residents are compact rows in a single card instead of tall separate cards. Check is neutral; "N requests" replaces "N req"; the history toggle is a button with `aria-expanded`; Export is secondary. Two KPI cards per row on phones. |
| Loading / empty / error | `NEW` | Loading row only on first load; older searches cannot overwrite newer results. |
| Restrictions card | `NEW` | Search is a form with a normal button; uses the shared pager. |
| Checker rejects valid input | `NEW` (fixed `e56efce`) | Reproduced in the browser: any resident data reload while the checker is open (workspace start-up, resident saves, imports, Resident Records filters) rebuilt the list and dropped the chosen resident, so Check showed "Select a resident." The list now keeps the current choice. Certificate labels, resident-number keys and the request-record path were tested and were not the cause. |
| Residents beyond the first 100 | `NEW` (fixed `e56efce`) | Verified with 117 residents: the list comes from a lookup capped at 100 by surname, so later residents could not be chosen at all. A search field queries the same endpoint by name or resident ID. Eligibility rules unchanged. |
## 7. Incident Reports — `NEW` (browser-verified light, dark, Filipino, 390 px; `e877a4b`)

| Item | Status | Notes |
|---|---|---|
| Table | `BUG` → `NEW` | An old `min-width: 1120px` made the table scroll sideways at desktop width and cut off Archive. Now 8 columns (type + number together, date over time) fit without scrolling; headers were Filipino in English mode and are now English source text. |
| Search and filters | `NEW` | One search (there was only one; the exact-category field was removed earlier on purpose), on its own row; status, severity, labelled From/To dates and assigned staff below. |
| Status presentation | `NEW` | Colours by meaning in the table and the detail dialog (which had fixed amber/green pills). |
| Record navigation | `NEW` | Clicking or pressing Enter on a row opens the record; row buttons keep their own actions. |
| Actions | `NEW` | View and Edit neutral, Archive red outline; File Incident and File Report are primary (were red). Archive keeps the existing supervisor-password dialog. |
| Form validation | `NEW` | Fields carry request names, so type, date, location, details and complainant errors appear on the field (several only reached a toast); messages say "incident date" instead of "occurred date". |
| Confidentiality | unchanged | Verified: Kagawad (`vawc.view`) sees VAWC cases; Secretary gets 403 from the incidents API and has no Incidents menu. |

## 8. RFID File Tracking — `NEW` (browser-verified; `d791e49`)

| Item | Status | Notes |
|---|---|---|
| Search above Movement History, filters in one row | `DONE` | Already in place (search + action, date, cabinet, drawer in one desktop row). |
| Filter Events button / Recorded Events KPI | `DONE` | Neither exists: filters apply on change and the count sits beside the heading. The duplicate "Recorded events only" pill was removed. |
| Predictable filtering | `NEW` | Results update in place: typing no longer reloads the page and loses the cursor (verified: focus and caret kept), stale responses are ignored, a filtered empty result says so, pager links load in place. |
| Bug: wrong filter URL | `NEW` (fixed) | The script read `form.action`, which the "action" filter field shadows; fixed and covered by `IotFilters.test.js`. |
| Pagination | `NEW` | Shared pager partial (`partials/staff-pager`), same layout as the workspace pager. |
| Table | `NEW` | Date over time, current status as a pill, monospace tag; no sideways scroll at desktop width. |
| Trust banner ("Physical files, digitally accountable.") | `NEW` (removed, approved) | Its tags repeated the notice below it ("No simulated scans") and the heading ("Read-only event log"). Banner and its CSS were removed. The teammate test now checks it is gone and that the notice stays. |
| Tracking logic, permissions | unchanged | Secretary and Kagawad (rfid.view) 200; Tanod 403. |

## 9. Employee Cabinet Access — `NEW` (browser-verified; `d791e49`)

| Item | Status | Notes |
|---|---|---|
| Details in a dialog | `DONE` / `NEW` | Dialog existed; it now closes with a labelled X and returns focus to Details. |
| Scannable rows | `NEW` | Status chips: website account, cabinet permission, RFID and face enrollment, drawers; effective-access state beside Details. |
| Filters | `NEW` | Read-only access filter (effective / enabled but not yet effective / disabled) mirroring `isEffective()`; loads in place. |
| Pagination | `NEW` | Shared pager partial (15 per page). |
| Confirmations | `DONE` | Deactivation already confirms in a dialog; enabling, enrollment requests and drawer changes are unchanged. |
| Security, permissions, hardware actions | unchanged | Secretary, Kagawad, Tanod: 403. |

## 10. Audit Log — `NEW` (browser-verified; `d791e49`)

| Item | Status | Notes |
|---|---|---|
| Type → Category | `NEW` | One Category dropdown replaces six type buttons and adds the missing "Accounts and sign-ins" (login) category; no filter lost. |
| Toolbar | `NEW` | Search, category and date in one row (stacked on phones). |
| Pagination | `NEW` | Shared pager; the duplicate footer count and refresh note were removed (refresh interval now on the live badge); no inner scroll box. |
| Detail dialog | `DONE` / `NEW` | Rows already opened details (click and Enter); now: category, date and time, action, performed by (name and user ID), affected record, details; permission changes as lists instead of raw JSON. Read-only. |
| Summary cards | `BUG` → `NEW` | Fourth card said "Account Changes" but counts security and cabinet events; relabelled. Two per row on phones. |
| Access | unchanged | Administrators only; Secretary, Kagawad, Tanod get 403 from the audit API. |


## 11. User Management — `NEW` (browser-verified light, dark, Filipino, 390 px)

| Item | Status | Notes |
|---|---|---|
| Clickable cards without nested buttons | `BUG` → `NEW` | The card was `role="button"` and held the Edit, Suspend and Delete buttons. Now the name is the button and stretches over the row, so a click anywhere on the row still opens the details. Actions sit above it and carry the user's name in their labels. |
| KPI colours | `NEW` | Total is blue, Active green, Suspended red and the new "Active without 2FA" card amber. Suspended and 2FA cards turn neutral at zero, so colour means "needs attention". |
| Toolbar and role filter | `BUG` → `NEW` | The filter had no Super Admin option, and "Admins" mixed super admins with ordinary admins. It now lists Super Admin, Barangay Captain, Barangay Admin, Barangay Staff and View only, plus status and a live result count. Search also matches role labels. |
| Readable account rows | `NEW` | Role labels match the account menu: before, it showed raw `staff`/`admin`, and super admins carried two role chips. Suspended rows no longer fade to 65 % opacity; a red edge marks them instead. A "You" tag marks your own row. |
| Details dialog | `NEW` | Grouped into Account, Staff access and Smart cabinet. Assigned operations show module and action labels instead of permission keys. Enrollment states are readable, and the dialog has an Edit account button. |
| Suspend / reactivate confirmation | `NEW` | Explains the effect: the person is signed out, cannot sign in until reactivated, and keeps their records. It has Cancel and Suspend buttons and opens on Cancel, so Enter does not confirm (session 7 replaced the X-only rule for confirmations). |
| Field errors in the account form | `NEW` | Server errors on the Raspberry Pi ID and status now appear on those fields. |
| Bug: sidebar role | `BUG` → `NEW` | On workspace screens, JS overwrote the account footer's role with the raw role (`captain`). The server label is kept now. |
| Permissions, eligibility, authorization | unchanged | Secretary, Kagawad and Tanod get 403 on `/admin/users` and `/admin/settings`. |

## 12–14. Account & Settings, My Profile, Account Security — `NEW` (browser-verified)

| Item | Status | Notes |
|---|---|---|
| Settings hub (`/admin/settings`) | `NEW` | Has two cards. "Your account" shows identity, profile (dialog), password and 2FA (says you will confirm your password first) and theme. "Workspace" shows the barangay and hardware status. |
| "Open Portal" / Resident Portal | investigated | The link opens the public portal (`/portal`) in a new tab. Residents use a separate guard, so the staff session is not used there. It stays in the sidebar only, so no second Settings link was added. On settings pages it was hard-coded in Filipino; now it is "Resident Portal" with a "(opens in a new tab)" hint in all three layouts. |
| Redundant controls | checked | The hardware-status dialog is also used by the dashboard's System Status card, so it stays. The theme row stays: it is the discoverable place, and the hint points to the top-bar button. |
| Navigation consolidation | `NEW` | The teammate's consolidation (`e7f1aa7`) left no Settings item in the sidebar for non-admin staff. They now get "Settings" → their own profile settings in every layout. Admins keep Settings → hub. Routes are unchanged. |
| Bug: current section | `BUG` → `NEW` | Security and Appearance pages highlighted no sidebar item and no in-page section. Both now show the current section, and tests cover it. |
| Password & security link | `NEW` | The account menu links to Password & security with a full page load, so the password-confirmation popup still guards it. Browser-verified: popup, then Security page with password and 2FA. |
| Supervisor password field | `BUG` → `NEW` | It was the only password field without a show/hide toggle; it now has one and an English label. |
| Delete-account wording | `NEW` | The label said "Super Admin password", but the rule also accepts an active Barangay Captain. The wording now names both. The rule itself is unchanged. |
| Self-deletion path | `APPROVAL` | See Decisions: settings self-deletion hard-deletes without RPi revocation or an audit entry. |
| Dark-mode language links on settings pages | `BUG` → `NEW` | A boxed outline appeared around English / Filipino; removed. |
| Translator | `BUG` → `NEW` | `admin-localization.js` restored a script-updated `aria-label`/`title`/`placeholder` to its first value (seen on the bell's unread count). It now re-reads changed attributes; test added. |

## 15. Notifications — `NEW` / `APPROVAL` for saved notifications

| Item | Status | Notes |
|---|---|---|
| Sources | `DONE` | Dashboard recent activity, filtered on the server by permission (`documents.view`, `Incident::visibleTo`, `records.review`). Kagawad sees only what they may see; Tanod has no dashboard and no bell. |
| Panel | `NEW` | Heading and scope line; one category line per item (Document request / Incident report / Resident application) with a colour mark; one unread dot; bounded height with its own scroll; full width on phones. |
| Accessibility | `NEW` | Items are real buttons; the bell has `aria-expanded` and an unread count in its label. Esc closes the panel and returns focus to the bell. Mark all read is disabled when nothing is unread. |
| Read state | `NEW` (interim) | Kept per user in this browser, so a reload no longer marks everything unread. Only hashed keys are stored, never names, references or incident details. It is not shared between devices or staff; see the proposal. |
| Record links | `DONE` | Items open the existing record (request, incident, application); the target screens authorize again. |
| "View all in Audit Log" | n/a | No longer in the code. Notifications and the audit log stay separate. |

## Final review (session 7, 2026-11-04) — browser-verified on an isolated, seeded copy

The copy had 50 residents, 30 voters, 14 incidents and 12 file movements.

**Coverage:**
- **Staff:** every page as captain; Secretary, Kagawad and Tanod for permission boundaries in work group 5.
- **Resident Portal:** public pages, plus a resident account.
- **Views:** desktop 1440 px, tablet 768/1024 px, phone 390 px; light and dark; English and Filipino.

| Area | Status | Notes |
|---|---|---|
| Confirmation dialogs | `NEW` | Cancel added to the shared confirmation, Reject request and Lift restriction dialogs. Dialogs now open on their first usable field instead of the X. The confirmation alertdialog is no longer wrapped in a second dialog role. The dialog test now requires the X everywhere and Cancel on confirmations. |
| Reject request validation | `NEW` | The reason field has a real label. A reason under 10 characters is now shown on the field, which gets focus; before, it only showed a toast. |
| Certificates KPIs | `BUG` → `NEW` | "Pending / Processing" showed 0 while 11 requests waited. The four cards now count the four board lanes in the same order (New online, Processing, Ready to Print, Released). Verified: processing one request moved the counts from 11/0/3/1 to 10/1/3/1. |
| Announcements | `NEW` | Each record shows its text with Publish and Archive. Editing sits behind an "Edit announcement" disclosure instead of an always-open form per record. File inputs are styled and helper text is muted. A failed edit reopens that record with its field errors and no longer fills the create form. The archive password label was in Filipino only. Full lifecycle verified with audit entries: create, edit, failed edit, publish, archive with supervisor password. |
| IoT top bar, dark mode | `BUG` → `NEW` | A grey box appeared behind English / Filipino; removed. |
| Filipino coverage | `NEW` | 33 Announcements and dialog strings added. |
| Resident Portal | checked | Home, announcements, officials, help, login, registration, new-resident application, request wizard, My requests, profile and QR verification are consistent in all checked modes. No changes needed. |
| Resident Records "Loading…" | not a bug | Rows appear after a few seconds on the single-threaded PHP dev server, which queues the page's parallel requests. Production servers handle them in parallel. |
| Skipped tests (2) | checked | `Auth/RegistrationTest` (starter-kit open staff sign-up) skips because Fortify registration is intentionally off. New `StaffRegistrationDisabledTest` asserts it stays off (no route, POST `/register` returns 404, no user created). |
| Hardware | not verified | Cabinet, RFID and face behaviour were checked only as server data and pages. No Raspberry Pi or reader was exercised. |

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

### Purok name uniqueness at the database level — decided (2026-11-04): keep as is

- The application-level check and the MySQL unique index (case-insensitive collation) stay. The optional SQLite migration is not added: no test or development need for it was found.

- Findings: app-level check (case- and space-insensitive) is in place. Local data: 5 puroks, no collisions, all names normalised. Production uses MySQL with `utf8mb4_unicode_ci`, so the existing `unique(name)` index is **already case-insensitive there**. The gap is SQLite (local development and tests), where `unique` is case-sensitive.
- Proposed migration (not created):
  1. Stop with a clear message listing names if `SELECT LOWER(TRIM(name)), COUNT(*) FROM puroks GROUP BY 1 HAVING COUNT(*) > 1` returns rows. Nothing is changed or merged automatically.
  2. SQLite: `CREATE UNIQUE INDEX puroks_name_lower_unique ON puroks (lower(name))`.
  3. MySQL: read the column collation; if it ends in `_ci`, do nothing; if it is binary/case-sensitive, stop and report.
  4. `down()` drops the SQLite index only.
- Recommendation: optional, since production is already protected. Apply it only if you want local SQLite to behave like production.

### Saved notifications — `APPROVAL` (design only)

- **Today:** the bell lists the dashboard's recent activity (document requests, incidents the user may see via `Incident::visibleTo`, pending resident applications). Since work group 5, read state is kept per user in the browser as hashed keys. It survives reloads but not a change of device. Audit logs are separate and stay separate.
- **Essential events**, each for staff who must act on it:

  | Event | Recipients |
  |---|---|
  | New online document request | Staff with `documents.view` |
  | Resident registration application submitted | Staff with `records.review` |
  | New ordinary incident report | Staff with `incidents.view` |
  | New sensitive or VAWC incident | Staff with `vawc.view` only |
  | Incident assigned to you | The assigned user only |

- **Optional events** (useful, but extra volume):
  - document request status changes (processing, ready for release, released, rejected), to `documents.view`;
  - incident status changes, to the same audience as the incident;
  - announcement published, to `announcements.view`.
- **Permission checks:** recipients are chosen from existing permissions when the event fires, and checked again when the list is read, so someone who has since lost the permission no longer sees the item. No new permissions are needed.
- **Read state:** Laravel's database notifications (`notifications` table: uuid, type, notifiable, data JSON, `read_at`). "Mark all read" becomes a PATCH that sets `read_at`.
- **Links:** store record type + id + reference and open the existing screen; the target endpoints already authorize, and the list hides items whose record the user can no longer see.
- **Sensitive data:** store only the reference number and a generic title ("Sensitive case updated") for VAWC/sensitive incidents; never names, narratives or incident types. Resident names appear only for document requests and applications, which their recipients can already see.
- **Duplicates:** one notification per user per (event, record, new status); repeated saves without a status change send nothing.
- **Database changes:** the standard `notifications` table (`php artisan make:notifications-table`) plus an index on (`notifiable_id`, `read_at`). No changes to existing tables.
- **Migration and rollback:** it only adds a table. `down()` drops it, and the bell falls back to today's dashboard-activity list, so rolling back loses only notification history. On MySQL, create the table during a quiet period; it holds no existing data. Do not backfill old events.
- **Retention:** prune read items after 90 days and unread items after 180 days (scheduled `model:prune`). Delete a user's notifications when the account is deleted.
- **Losing access:** when a user loses a permission, items they can no longer open are hidden at read time; nothing is copied into the payload that the record endpoint would not show.
- **Needs approval:** the event list, recipient rules, retention periods, and the migration.

### Self-deletion from account settings — `APPROVAL` (high-priority security finding, not changed)

**How it works today (verified in code and with a throwaway probe test on the in-memory test database; the probe was not kept):**

- **Entry point:** Settings → Profile → Delete account (`livewire/settings/delete-user-form`, `App\Livewire\Settings\DeleteUserForm::deleteUser`).
  - The page needs `can:access-employee-settings` (admin, captain, staff, viewer).
  - The action itself needs `canLoginAsPersonnel()` (admin, captain, staff). Viewers get 403.
  - The form shows only when the email address is verified.
- **Approval:** the password of any active Barangay Captain or Super Admin (`VerifySupervisorPassword`), rate-limited to 5 tries per minute.
  - A captain or Super Admin can approve their own deletion with their own password. For them the supervisor step is self-approval.
- **What happens:** the user is logged out first, then the row is `forceDelete()`d (a hard delete, no soft delete).

**Outcomes per case:**

| Case | Result |
|---|---|
| Ordinary staff, no cabinet record and no restricting history | Hard-deleted. **No audit entry.** Links in other tables are set to null (audit `user_id`, `rejected_by`, voter `created_by`/`updated_by`, incident `assigned_to`/`updated_by`). |
| Staff with a cabinet access record or file movements | The foreign key `restrictOnDelete` blocks the delete. The user is already logged out, then gets a 500 error. The account stays active, and its cabinet access is unchanged. |
| Staff who reported incidents, created BPOs, or created, reviewed or lifted restrictions | Blocked the same way (restricting foreign keys). |
| Super Admin approving with their own password | Hard-deleted. `DeleteStaffUser` would refuse this. |
| Barangay Captain approving with their own password | Hard-deleted. |
| Viewer | 403. |

**Effects on access:**

- **Sessions:** the current session ends. Other sessions can no longer resolve the deleted user, so they fail to authenticate.
- **Two-factor data:** removed with the row.
- **Smart cabinet:**
  - The server's authorization list (`accessAuthorizations`) only includes active, non-deleted users with an active, enrolled cabinet record.
  - A user who still has cabinet access cannot be hard-deleted at all, so no orphaned credential is created.
  - No RPi revocation is queued on this path either, unlike `DeleteStaffUser`.
  - Whether a Raspberry Pi keeps honouring a cached credential between syncs depends on the Pi software. Browser tests cannot verify that.
- **Residents:** no resident self-deletion exists. Staff activate and deactivate portal accounts (`ResidentPortalAccountController`), so the proposal below does not affect residents.

**Risk:** this is not an unauthorized-access hole, because every case needs a valid captain or Super Admin password. The risks are:
- loss of accountability: a hard delete with no audit entry, and attribution in other records nulled;
- self-approval by captains and super admins, including the last Super Admin;
- a broken, already-logged-out 500 error for cabinet users.

**Proposed change (needs approval; reversible; no migration):**

1. Remove the "Delete account" section from staff settings. In `profile.blade.php`, render `<livewire:settings.delete-user-form />` only for non-personnel accounts; since residents never use these pages, that means never.
2. In `DeleteUserForm::deleteUser`, return 403 for every personnel account, so the endpoint cannot be called directly either.
3. Staff accounts are deleted only in User Management through `DeleteStaffUser`. That path stays unchanged: soft delete, credentials scrubbed, RPi revocation queued, audit entry, Super Admin and self-deletion refused, supervisor password required.
4. Tests:
   - Replace "user can delete their account with the approval of an active captain" with "personnel cannot delete their own account from settings" for staff, captain and Super Admin.
   - Keep the User Management deletion tests.
5. To roll back, revert the commit. No data is touched.

**Interim mitigation:** none applied, because the issue is not exploitable without a supervisor password. If you want it closed before you decide, step 2 alone is a one-line, reversible guard.

## Session log

- **Session 1 (2026-11-03)** — Work group 1: dialog stack, shared pager, catalogue coverage and guard test, Add Purok fixes, toast and notification-close fixes (`fd10c70`).
- **Session 2 (2026-11-03)** — Browser verification of work group 1 against a scratch database copy (found and fixed the "2tal" translation bug, `2c6655d`). Repaired outdated tests and a real navigation stylesheet regression; destructive-route tests now send a valid supervisor password (`0d611b4`). Resident-number import matching (`f10b9be`). Work group 2: Demographics, Resident Records and Voters layouts, contact-number storage, Filipino coverage (`c8034f5`). Next: remaining work group 2 items (Voters table, import/export in the browser), then work group 3 (Eligibility bug first).
- **Session 3 (2026-11-03)** — Work group 2 finished: Voters edit action, validation and language; stale-response guard on resident and voter lists; import summary, lasting result and clearer errors; profiling and voter imports browser-tested on an isolated copy (`4339f7b`). Work group 3 started: Eligibility Checker bug reproduced and fixed, plus resident search beyond 100 (`e56efce`). Next: Request Eligibility layout (KPI cards top, filters below), then Certificates Request Board and Incident Reports.
- **Session 4 (2026-11-03)** — Work group 3 finished: Request Eligibility, Certificates & Clearances and Incident Reports reworked and browser-verified on an isolated copy (captain, kagawad and secretary accounts; light, dark, Filipino, 390 px) (`e877a4b`). Bugs fixed: incident table clipped at desktop width, broken "Public Portal" button with fake-request code, invisible process-step icons, incident errors that only reached a toast, restriction-list pager error hidden by its test harness. Next: work group 4 (RFID File Tracking, Employee Cabinet Access, Audit Log).
- **Session 5 (2026-11-03)** — Work group 4 finished: RFID File Tracking, Employee Cabinet Access and Audit Log reworked and browser-verified on an isolated copy (captain, secretary, kagawad, tanod; light, dark, Filipino, 390 px) (`d791e49`). Bugs fixed: shadowed form.action in the in-place filter, mislabelled audit summary card, missing login category, unreadable dark-mode pills on IoT pages. Next: work group 5 (User Management, Account and Settings, My Profile, Account Security, Notifications) together with the 14 failing settings tests.
- **Session 6 (2026-11-03)** — Work group 5 finished.
  - **Modules:** User Management, Account & Settings, profile and security pages, and notifications were reworked and browser-verified on an isolated copy. Accounts: captain, secretary, kagawad and tanod. Checked in light and dark mode, English and Filipino, and at 390 px.
  - **Bugs fixed:**
    - Nested buttons in user cards.
    - Missing Super Admin role filter.
    - Raw role in the sidebar footer.
    - No current section on the Security and Appearance pages.
    - No Settings link for non-admin staff.
    - Missing password toggle on the supervisor-password field.
    - Translator reverting script-updated labels.
    - Dark-mode boxes on the language links.
    - Notification read state lost on reload.
  - **Other changes:** RFID trust banner removed (approved). All 14 failing tests resolved.
  - **Open for approval:** settings self-deletion path, saved notifications.
  - **Next:** final full-system review.
- **Session 7 (2026-11-04)** — Final security and UI/UX review.
  - **Self-deletion:** investigated and documented with an exact proposal. Nothing changed; awaiting approval.
  - **Dialogs:** confirmations now have Cancel, dialogs open on the right field, and the duplicate dialog role is gone.
  - **Fixes:** Certificates KPIs now match the board lanes; Announcements reworked; reject-reason validation shows on the field; IoT dark-mode language links; 33 Filipino strings.
  - **Tests:** a guard test keeps open staff registration off.
  - **Verification:** Resident Portal and all staff pages reviewed in the browser on a seeded isolated copy.
  - **Open for approval:** self-deletion change, saved notifications.
