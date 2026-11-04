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

## Test status (2026-11-04, session 10)

- Node: 200 pass, 0 fail.
- Pest: 1030 pass, 0 fail, 2 skipped. The skipped tests are the starter kit's open staff registration tests, intentionally off; see Final review.
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

## Independent system audit (session 8, 2026-11-04)

**Method:** a critical pass over the real application, not the earlier checklist. The browser checks used **genuine mouse and keyboard input** (CDP `Input.dispatch*`) on the seeded isolated copy, served by PHP's dev server with 4 workers.

**Harness caveat:** earlier sessions submitted the staff login form directly. That triggered Chrome's save-password prompt, which swallowed all later input, so earlier "Escape did not work" observations were harness artefacts. Sign-in now uses `fetch`.

| # | Module / screen | Issue found | Severity | Impact | Fix | Status |
|---|---|---|---|---|---|---|
| A1 | All workspace dialogs | Confirmation dialogs appeared **behind** resident details, Manage Households, household import, incident, certificate-request and eligibility dialogs. Every overlay had `z-index: 800`, and the rule meant to lift the confirmation (`#app.admin-interface #modal-confirm-action`) never matched, because dialogs sit outside `#app`. Reproduced on the old code: a click on the confirmation's Cancel hit the details dialog behind it. | High | Users click the wrong layer; a confirmation can be dismissed without being seen. | `openModal`/`closeModal` now layer dialogs in open order. Dialogs underneath and the page are `inert`, the page cannot scroll, and focus returns to the opener, including openers that disabled themselves before confirming. Dead CSS rule removed; regression tests added. | `NEW` — verified with real Tab, Shift+Tab and Escape: focus is trapped, Escape closes only the top dialog, and focus returns to "Suspend portal account" and then to "View". |
| A2 | Settings → Delete account | Staff self-deletion hard-deletes without an audit entry; captains and super admins approve their own deletion. | Critical | Loss of accountability. | Removed the Delete account section and the `DeleteUserForm` Livewire component, so a direct call fails with "component not found". User Management deletion (`DeleteStaffUser`) and resident portal accounts are unchanged. Tests cover staff, captain and super admin. | `NEW` (approved 2026-11-04) — removed in `02b1f31` |
| A3 | Dashboard → Pending Requests | Left out the valid `approved` status, which the board shows as Ready to Print. | High | Undercounted outstanding work. | Counts pending, processing, approved and ready for release. Test added. | `NEW` |
| A4 | Dashboard → incident card | Counted open reports but was labelled "Incident Reports" and opened the unfiltered list. | High | The number did not match its destination. | Now "Open Incident Reports", and it opens the active filter. Verified: 14 → 14. | `NEW` |
| A5 | KPI cards (Voters, Incidents, Users) | Most KPI cards were not interactive, even where a matching list exists. | Medium | Extra steps to reach the counted records. | The whole card is a button that applies the matching filter. Counts and destinations share one definition: a new `active` incident status uses `Incident::PENDING_STATUSES`. Verified with real clicks: voters 23/2/6/15, incidents 14/4, users 3/48/51 all matched. | `NEW` |
| A6 | KPI cards with no honest destination | The following stay informational: Resolved This Month (resolution date versus incident-date filter), Request Eligibility (counts requests, filters residents), Active without 2FA (no filter), Certificates lanes (already visible below), Demographics gender and households. | — | — | Left informational on purpose. | decided |
| A7 | Demographics → Registered Voters | Counts residents with an active registration whatever their status or age (seeded copy: **30**). Voters → Lahat ng Botante counts only active residents aged 15 and over (**23**). | High | Two different "registered voters" numbers. | Cause: all 7 extra were residents under 15 with an active registration, which the registry excludes. Both cards now use `Resident::inVoterRegistry()` (active, not archived, aged 15+); the Demographics card links to the registry. Eligibility rules and stored data are unchanged. | `NEW` — `07fd22e` |
| A8 | Lists: residents, voters, incidents, request records, issued certificates, audit, users | A load failure showed the raw browser text "Failed to fetch", or an empty list, with no way to retry. | Medium | Staff could not tell an outage from "no data". | Plain-language message and a Try again button. Server validation and permission messages are kept. | `NEW` |
| A9 | Resident form, Purok form | Save could be clicked repeatedly while a request was in flight; a non-JSON error response (for example an expired session) produced a parser error. | High | Duplicate resident records; meaningless error text. | One request per save, button marked busy. Safe JSON parse. Verified: a real double-click sent one POST. | `NEW` |
| A10 | Resident form → Purok | If the purok list failed to load, the required Purok select was empty with no option or message, so the form could not be saved. | Medium | Blocked registration with no explanation. | The form retries the load when it opens and explains a failure inside the field. | `NEW` |
| A11 | Resident form validation | Checked in the browser. Empty save: server errors appear under the right fields, the first gets focus, and no misleading toast. A bad contact number gets a specific message, focus, and no request; input is kept. | — | — | No change needed. | verified |
| A12 | Dashboard layout | KPI subtitles repeated the number. Quick actions used three button styles (the third rendered differently). The request donut was 150 px in a large card. | Low | Visual noise; weak chart. | Subtitles say what the number covers; one quick-action style; 200 px chart with its legend beside it on wide screens. | `NEW` |
| A13 | Dashboard → Purok Breakdown (and similar panels) | On a load failure it says "No resident counts available by purok", which reads as a real empty state. | Medium | Misleading. | Purok panels show loading, a failure with Try again, or "No puroks have been added yet". A permission failure shows the server message. Failed loads show "—" instead of 0 on the incident, voter and user cards (`8667d67`). | `NEW` — `07fd22e` |
| A14 | Certificates & Clearances | The static five-step "Process Flow" card takes the top of the page every visit and pushes the request board below the fold. Board cards stack three full-width buttons each. | Medium | More scrolling; heavy cards. | The flow is a one-line summary that expands to the same five steps (54 px instead of about 190 px), so the board starts above the fold. The card name opens the request, leaving one row of buttons. The summary cards jump to their lanes. Verified at 1440 px and 390 px, dark, Filipino. | `NEW` — `88b88a2` |
| A15 | Resident form | Long single-column form; required fields are not marked before the first submit. | Medium | Slower data entry. | Real `<label for>` elements; Special Groups is a fieldset. Every server-required field is marked `*` and `required` up front (Address had been unmarked). `name` attributes route server errors to the right field. Household is marked Optional, and the photo field is simplified. | `NEW` — `c61c0fe` |
| A16 | Resident details → portal account | Suspending or reactivating a resident portal account is shown only when `role === 'admin'`. Barangay Captains (administrative access) do not see it. | Medium | Possibly an unintended permission gap. | Cause: an inconsistent UI check, not a policy. The backend (`ResidentPortalAccountController@update`) allows `hasAdministrativeAccess()` (Captain or Super Admin); the UI tests `role === 'admin'`. Recommendation: test `AUTHENTICATED_USER.is_administrator` instead. That shows the button to Captains, who are already authorised, and hides it from ordinary admin-role accounts, which now get a 403. No permission would change. Not applied. | `APPROVAL` — recommendation below |
| A17 | Smart Cabinet | Oversized notice text; cabinet cards nested inside a panel card. | Low | Visual weight. | One card level (cabinet and reader rows are flat inside their panels), a single divided detail grid, the cabinet state as a labelled list instead of raw JSON, normal notice size, and Filipino strings. Hardware logic is unchanged. | `NEW` — `8667d67` |
| A18 | Incident numbers | `INC-YYYY-` plus a 26-character ULID wraps over three lines in tables and activity lists. | Low | Hard to scan. | One break after `INC-YYYY-`, with the ULID kept on one line; the full number stays in the copied text and the title. Verified at 1440 px and 390 px. | `NEW` — `8667d67` |

**Not verified:** physical cabinet, RFID or face hardware. Screen-reader output (only semantics and focus were checked). Automated contrast scanning.

## Stakeholder voice-recorded revisions (session 10, 2026-11-04)

**Source:** shorthand notes from a voice-recorded stakeholder discussion, audited against the code at `c682236`. Each note was checked against the code. External facts were checked on 2026-11-04; the sources are listed under V-F. **Statuses:** `DONE` · `PARTIAL` · `BUG` · `NOT IMPLEMENTED` · `APPROVAL` · `INFO` (needs data from the barangay) · `NEW` (fixed this session).

### V-A. Resident records and demographics

| # | Note | What exists | Status | Recommendation / fix | Needs |
|---|---|---|---|---|---|
| A1 | Hanap ng pangalan | Resident Records, request records, Voters, the case-resident lookup and the restriction search matched the whole query against each column separately. "Juan Dela Cruz" (first + last name), "Dela Cruz, Juan" and double spaces found nothing. | `BUG` → `NEW` | `Resident::matchingSearch`: every word must match the resident ID or a name part, in any order. Commas, extra spaces and initials' full stops are ignored, and letter case follows the database collation. Results already show ID, age and purok to tell same-name residents apart; no extra personal data was added. Filters and pagination unchanged. Cost: the same leading-wildcard `LIKE` as before, at most 6 words. | — |
| A2 | Per family na register | Households have a number, name, address, purok and head. Members carry a free-text relationship to the head. Manage Households adds and removes members. The profiling import groups rows by household and checks duplicates by resident ID and by name + birth date. **There is no family entity:** the import even maps a `family_id` column onto the household group. | `APPROVAL` | Proposal V1. Households and families stay separate. | Migration approval |
| A3 | Tag na registered voter | Household member tables showed Yes/No, so a resident with no voter record read as "No". Resident Records rows and details had no voter information. | `PARTIAL` → `NEW` | Rows show a green **Voter** tag for an active voter record. Details show **Voter registration**: Registered voter, Transferred, Deceased, Deregistered, or **No voter record**, with a note that no record does not mean unregistered. Household tables use the same labels. The source is the Voters module (`voter_registrations.status`); COMELEC numbers stay out of the payload. No new filter: the Voters screen is already the filtered list. | — |
| A4 | Realtime na encoding | Saves are written immediately. After a save, the list, purok counts and resident pickers reload from the server. Double submits are blocked (A9), stale searches are ignored, an identity lock stops two simultaneous duplicate creations, and voter edits have a version check. **Resident edits have no conflict check** (last save wins). Other staff see changes on their next load or search. | `PARTIAL` | Read as "saved and visible right away", which is in place. Live multi-user sync is not needed for a barangay office, so no WebSockets or polling. Proposal V2 covers the edit-conflict gap. | V2 approval |
| A5 | Family sa demographics | Demographics shows residents, male, female, seniors, households, registered voters, puroks and special groups. No family data is stored. | `APPROVAL` | Households are not relabelled as families. A family count becomes possible after V1. | Depends on V1 |
| A6 | Separate male at female | Separate Male and Female KPI cards with a share of the population, plus a male/female split in the age panel. Sex is validated as Male or Female in the staff form, the application and the import, so no other value can be stored (local data has only these two). | `DONE` | — | Recording other values would be a data-model decision |
| A7 | ID verification | **Existing residents** activate with: full name exactly as on record → birth date → a one-time code emailed to **any address they enter** → password and photo. The account works at once; the photo is stored, but no staff review exists. Staff can also issue an activation code from Resident details. **New residents** apply with a photo, and staff approve or decline. One account per record is enforced, the routes are rate-limited, and sign-ups are audited. Requests need an activated account; pickup needs an original valid ID at the hall (portal text). | `APPROVAL` | **Risk:** anyone who knows a resident's full name and birth date (a relative or neighbour) can claim an unclaimed record with their own email and request documents in that name. Proposal V3. No biometrics and no new ID-document storage. | V3 decision |

### V-B. Public homepage and Resident Portal

| # | Note | What exists | Status | Recommendation / fix | Needs |
|---|---|---|---|---|---|
| B1 | Presyo ng fee at docs | `CertificateType::fee()` is the server source. It feeds the portal fee tables, request status and the recorded `amount_paid`. Staff screens repeat the amounts in three places in `admin.js`: the fee table (~line 657), print and release (~1437) and manual issuance (~1467). **Current amounts:** Clearance, Residency and Voter Certification PHP 25; Barangay ID PHP 100; Indigency and First Time Jobseeker free; Business Clearance PHP 200. The portal shows "PHP 200.00" beside a "Business Clearance fee: To be confirmed" tag, and the staff table shows "PHP 200.00+". | `PARTIAL` | No amounts changed. Proposal V4: staff screens read `CertificateType`, so a confirmed change is entered in one place. | Official fee schedule, especially Business Clearance |
| B2 | Anabu I-G map | Home and Information pages embed Google Maps searched by "Barangay Anabu I-G, Imus, Cavite", with an "Open in Google Maps" link. The hall entrance is marked unconfirmed. There is no hall marker and no boundary. | `PARTIAL` | Pin the hall once the barangay confirms its location (or a Google Maps place for the hall). Draw a boundary only from PSA or City GIS data. | Verified hall location; boundary data |
| B3 | Sukat area | "Land Area: To be confirmed". No authoritative figure was found: PhilAtlas and citypopulation.de give population, not an official area. | `DONE` (correctly unpublished) | Publish with units (km² or ha) once the barangay or City Planning provides it. | Official figure |
| B4 | Firetruck hotline | Bureau of Fire Protection 0915 528 3256 (tap to call), with 911, City emergency (046) 888 9911 and Imus PNP 0998 598 5601. All match cityofimus.gov.ph (checked 2026-11-04). The city site also lists BFP landlines 970-5161 and 416-3032 without an area code. | `DONE` | Optionally add the landlines after the area code is confirmed. | — |
| B5 | Picture ng namumuno | `/portal/officials` (linked from Get help and the footer) renders cards from `config/portal.php`: photo, name, position and term. All four entries are placeholders with a neutral placeholder image. Officials are not on the homepage, and updates need a config change. | `INFO` | Supply approved photos and verified names, positions and terms. After that, consider a homepage strip and an admin-managed list (a new table, so it needs approval). | Photos and data |
| B6 | Interactive name at term | Each card has a "View Details" disclosure (`<details>`), usable by keyboard and touch, that shows the term. No hover-only content. | `DONE` (structure) | Add responsibilities only when verified. | Data from B5 |
| B7 | Pangalan at birthday lang | Existing-resident activation already asks only name and birth date before the email code. Requests take name, address, email and birth date read-only from the verified profile; residents type only purpose, business name (Business Clearance) and an optional attachment. New-resident applications ask for the full profile because they create a record for staff review. | `DONE` | Identity checks are not reduced (see A7). Finding: the form marks business name as required, but the server accepts it empty. | — |
| B8 | 8888 hotline | Information → "Official resources and assistance" links to 8888.gov.ph and describes it as the national complaint channel. Verified through EO No. 6 (2016) and PNA; the site blocks automated fetches. It is not on the homepage. | `PARTIAL` | Add a compact "Government services" row on the homepage, reusing the same config entries and labelled as national, separate from barangay incident reports. | Approval of homepage placement |
| B9 | Provincial page link | cavite.gov.ph is in the footer and in the resources list (correct jurisdiction for Imus). | `DONE` | — | — |
| B10 | Medical / burial assistance | Both link to cavite.gov.ph/social-welfare-services. Verified 2026-11-04: it lists Assistance to Individuals in Crisis Situation, Burial Assistance for the Poor, Medical and Burial Endowment and Burial Assistance for Senior Citizens. These are provincial programmes. Smart Barangay has no application workflow for them and does not imply one. | `DONE` | Optionally add City or DSWD pages after verification, labelled by agency. | — |

### V-C. Smart Cabinet

| # | Note | What exists | Status | Recommendation | Needs |
|---|---|---|---|---|---|
| C1 | Backup kapag walang internet | **Server side:** the cabinet pulls `access-authorizations`: active staff with enrolled RFID **and** face, their effective drawers, and an `authorization_version`. It also pulls pending enrollments and credential revocations, which it acknowledges by request ID. It posts access and movement events with a device event ID and `occurred_at`; duplicates are ignored, so events can arrive late. Access decisions and credentials live on the Raspberry Pi, whose software is **not in this repository**. Offline behaviour (cached list, local log, fail-closed or fail-open) therefore cannot be verified here. | `APPROVAL` | Proposal V5. Nothing implemented. | Firmware review and approval |
| C2 | Turnover ng bagong admin | Super Admin is granted or revoked only by a server command (`user:grant-super-admin`), which is audited and refuses to revoke the last active Super Admin. User Management cannot demote or deactivate a Super Admin, and a captain cannot demote themselves. Deleting a staff account queues RFID and face revocation on the cabinet. No turnover procedure is documented. | `APPROVAL` | Proposal V6: a procedure first. No automatic transfer of privileges. | Approval |

### V-D. Incident reports and protection orders

| # | Note | What exists | Status | Recommendation / fix | Needs |
|---|---|---|---|---|---|
| D1 | Barangay protection order | BPO records hang off incidents: protected person, respondent, issued/effective/end dates **as entered**, a free-text status, issuing authority, document reference and private remarks. They are restricted to `vawc.*` (Super Admin by default) and audited without narrative. Cases with a BPO are hidden from ordinary incident staff. There are no issuance stages, deadlines or reminders, and no dates are calculated. | `PARTIAL` | Keep it as record-keeping. Stages, a fixed status list or monitoring reminders must follow RA 9262 and its IRR as confirmed by the barangay VAW desk (proposal V7). **Fixed:** the BPO list title read "BPO record #3 ? recorded", and the restriction case picker read "INC-… ? Theft ? Open"; corrupted separators replaced with "·". | Legal requirements |
| D2 | Counts ng cases | KPIs: Open/Under review (all active statuses), Resolved this month and High severity; all respect `visibleTo`. The status filter covers every status. There is no per-status breakdown and no BPO count. | `PARTIAL` | Add Under review, Referred, Resolved and Closed counts that apply the existing status filter, plus a BPO-linked count for `vawc.view` only. Safe; planned for next session. | — |
| D3 | Case number / notif | Incident numbers exist, and the incident API filters by resident within the viewer's visibility. Resident details had no case information. | `PARTIAL` → `NEW` | Resident details now list linked reports for staff with `incidents.view` or `vawc.view`: number, type, status and the resident's role, plus total and active counts. View opens that report. No narrative and no other party's name. It states that a linked report does not by itself restrict requests. Staff without incident access do not get the panel (the server returns 403). | — |
| D4 | Bakit hindi maprocess | **Staff:** the eligibility checker lists specific reasons: inactive record, not in good standing, voter registration needed, indigency not verified, Jobseeker already issued, or a restriction. **Residents:** a rejected request shows the staff's rejection reason. An active restriction blocks submission with a generic "on hold and requires barangay review". The restriction's `resident_visible_reason` column exists, but the staff form never sets it. A pending ordinary incident naming the resident as respondent shows a neutral, non-blocking notice. | `PARTIAL` | Proposal V8. | Approval |
| D5 | Details ng pending case | Staff case details are behind `incidents.view` / `vawc.view`. Restriction internals are visible to administrators only. Residents see only their own request statuses and the neutral notice. | `DONE` | No change. | — |
| D6 | Kailan makakapag-request ulit | Restrictions store start, end, next review, status, reviewer and the person who lifted them, and expire automatically at the end date. Residents are not told the end date. | `PARTIAL` | Part of V8: show the end date staff set (never a computed one). For open-ended holds, say staff will review the hold. | Approval |

### V-E. Proposals for approval

- **V1 Families inside households.**
  - New `families` table: `household_id`, an optional family head, an optional label.
  - New nullable `residents.family_id` and a relationship-to-family-head field.
  - Workflow: in Manage Households, a "Families in this household" list where staff add a family and assign existing members.
  - The import reads an optional `family_group` column, which today is an alias of the household group; this changes that alias.
  - Demographics gains a family count from the table.
  - Additive nullable columns, no backfill. `down()` drops them.
- **V2 Edit conflicts.** The resident form sends the loaded record's `updated_at`. If the record changed since, the server returns 409: "Someone else updated this record. Reload to see their changes." No migration.
- **V3 Account activation.** Pick one:
  - (a) Self-activated accounts stay "pending review" until staff confirm the person, for example at the hall or against known contact details. Requests are blocked until then.
  - (b) Send the code only to an email or contact number already on the resident record; otherwise require a staff-issued code.
  - (c) Keep the current flow and accept the risk.

  Recommended: (a) or (b). The existing staff-issued code path stays either way.
- **V4 One fee source for staff screens.** Pass `CertificateType` fees to the staff page and remove the three JS copies. Amounts stay unchanged; confirmed amounts are entered in `app/CertificateType.php` only.
- **V5 Cabinet offline plan** (firmware; needs testing on the cabinet).
  - **Fail closed by default.**
  - The Pi keeps the last authorization list with its version and a maximum offline age set by the barangay. Within that age:
    - an internet outage still allows listed staff;
    - credentials revoked during the outage cannot be honoured, which is why the age limit exists.
  - Every event goes into a local append-only log with a device event ID and syncs on reconnection; the server already de-duplicates.
  - Power, LAN or Pi failure: the physical key only.
  - Emergency override: the physical key held by the Captain, logged on a paper form and entered afterwards. No software bypass.
- **V6 Administrator turnover procedure.**
  1. Written authority (Captain or council resolution) naming the incoming administrator.
  2. Create the account and require 2FA.
  3. `user:grant-super-admin <new>`, then verify access.
  4. Enroll RFID and face for the incoming administrator.
  5. Disable the outgoing person's cabinet access and confirm the Pi acknowledged the revocation.
  6. `user:grant-super-admin <old> --revoke`. The guard keeps at least one Super Admin.
  7. Review the audit log.
  8. Sign over physical keys and recovery codes.

  Optional later: a read-only "turnover report" listing active administrators, cabinet credentials and pending revocations.
- **V7 BPO workflow.** Only after the VAW desk confirms the required fields and stages. Until then, record-keeping only.
- **V8 Resident-facing hold message.**
  - Add an optional "Message to the resident" field to the restriction form, using the existing `resident_visible_reason` column.
  - The resident's hold message shows that text and, if staff set one, the end date.
  - Otherwise it says "Barangay staff will review it. Ask at the Barangay Hall."
  - It never mentions incidents. Restriction rules are unchanged.

### V-F. Sources (checked 2026-11-04)

- Hotlines: [cityofimus.gov.ph](https://cityofimus.gov.ph/) home page (BFP, PNP, city emergency, CDRRMO).
- Medical and burial: [cavite.gov.ph/social-welfare-services](https://cavite.gov.ph/social-welfare-services/).
- 8888: [8888.gov.ph](https://8888.gov.ph/) blocks automated fetches. Confirmed through [EO No. 6 (2016)](https://lawphil.net/executive/execord/eo2016/eo_6_2016.html) and the PNA.
- Land area: no authoritative source found.
- **To confirm:** the portal's "PSA data" link (`psa.gov.ph/classification/psgc/barangays/0402109000`) could not be opened (403), and citypopulation.de lists Anabu I-G as `042109034`. Confirm the link and the "2,345 (2024 Census)" figure with PSA.

### V-G. Verification

- Feature tests: name search in five forms (order, comma, case and spacing, initial, suffix) on Resident Records, request records, the case lookup and Voters; voter status for active, transferred and no record, with no COMELEC data; household member voter status; linked incidents limited by role (ordinary, VAWC, 403 for records-only staff).
- Node tests: Voter tag only for active records; household labels never turn a missing record into "No"; the linked-incident panel shows number, type, status and role but no narrative or names, and is removed on 403.
- **Not verified:** no browser tool was available this session, so the new labels and panel were not checked visually (light/dark, Filipino, 390 px). No hardware was exercised.

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

### Self-deletion from account settings — resolved (approved and implemented 2026-11-04, `02b1f31`)

Option (b) was applied: no staff role can delete its own account; staff accounts are removed only in User Management. The findings below are kept for the record.

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
- **Session 8 (2026-11-04)** — Independent system audit (table above).
  - **Fixed:**
    - confirmation dialogs layered behind detail dialogs (`4785456`);
    - actionable KPI cards, plain-language load failures with retry, double-submit guards, purok load retry (`5b62468`);
    - dashboard pending count, open-incident card and layout (`19a885a`).
  - **Verification:** genuine mouse and keyboard input in the browser.
  - **Open:**
    - for approval: A2 self-deletion, A7 registered-voter definition, A16 captain portal-account permission;
    - to do: A13–A15, A17–A18.
- **Session 9 (2026-11-04)** — Audit findings resolved.
  - **Fixed:**
    - A2 staff self-deletion removed (approved);
    - A7 one registered-voter definition;
    - A13 load states;
    - A14 Certificates layout;
    - A15 resident form;
    - A17 Smart Cabinet;
    - A18 incident numbers.
  - **Open:** A16 recommendation only, awaiting a decision.
  - **Verification:** real clicks and keys on the isolated copy; desktop, tablet and phone; light, dark and Filipino.
- **Session 10 (2026-11-04)** — Stakeholder voice-recorded revisions audited (section above).
  - **Fixed:**
    - A1 multi-word name search;
    - A3 voter registration indicator;
    - D3 linked incident reports in resident details;
    - D1 corrupted "?" separators in the BPO and restriction-case lists.
  - **For approval:** V1–V8 (families, edit conflicts, account activation, fee source, cabinet offline plan, admin turnover, BPO workflow, resident hold message).
  - **Needs barangay data:** official fees (Business Clearance), hall location, land area, officials' photos and terms, PSA link confirmation.
  - **Safe next steps:** D2 per-status incident counts, B8 homepage government-services row (placement approval), V4 single fee source.
  - **Not verified:** visual browser check (no browser tool this session), hardware.
