# Role Access and Filipino Language Audit

## Status (updated 2026-11-05)

| Item | Status |
|---|---|
| **R1** Captain could take over a Super Admin | **Fixed** (approved). Details below. |
| **R6** Add Purok shown without permission | **Fixed** (approved). The button now needs `records.create`, the permission the server already checks. No permission granted or removed. |
| **R12** Certificates page embedded every request | **Fixed** (approved). The board loads each lane from the paginated `staff.document-requests.list` endpoint (JSON); counts come from the server; search reaches older records. Permissions unchanged. |
| **R13 / L4** Filipino-only server messages in English mode | **Fixed.** English source text plus Filipino catalog entries. One more leak fixed (Smart Cabinet archive label). |
| **L1–L3** Filipino style and consistency | **Applied:** 98 high-visibility entries, then a contextual review of the remaining formal wording (127 changed), plus 120 new entries for staff labels that showed in English (and 7 for the certificates board). |
| R2–R5, R7–R11, R14 | **Awaiting approval of the access matrix.** Full plan in "Final permission implementation plan"; decisions D1–D10. Nothing implemented. |

**R1 fix:**
- **Captain refused, and the attempt recorded:** in `SaveUserRequest::authorize`, an administrator who is not a Super Admin can no longer change a Super Admin account (403). A refused attempt is recorded as the security audit event `security.super-admin-account.change-denied`.
- **Credentials only by the owner:** a Super Admin may still manage other Super Admin accounts, except their **email and password**. Those change only through that Super Admin (self-service), the password reset link, or `user:grant-super-admin`; anything else is a 422 with a field message.
- **Interface:** the Edit button and the details-dialog Edit are hidden for non–Super Admins on Super Admin rows. For a Super Admin editing another Super Admin, the email and password fields are locked with an explanation. Staff management by Captains is unchanged.
- **Tests:** Captain (email and password, password only, name only) refused and audited; Super Admin self-edit allowed; another Super Admin's credentials refused while the name can change; Captain still edits staff; staff refused.
- **Browser check (isolated copy):** the Captain's direct API call returned 403, and the attempted credentials did not sign in.

**Filipino rules applied:**
- Familiar English terms in Taglish: Dashboard, Records, Incident Report, Pending, Household, Cancel.
- Simple action verbs: Maghanap, I-save, I-submit, I-delete, I-publish.
- Official document names exactly as printed (Certificate of Residency, Certificate of Indigency, First Time Jobseeker, Registered Voter Certification), now pinned by a test.
- "Contact" corrected to "Contact number". The Demographics title reads "Populasyon ng Barangay".
- Legal and security wording unchanged: VAWC/BPO, complainant and respondent, supervisor password, data privacy body text, request-hold and eligibility messages. "Notifications" stays "Mga abiso", so the bell and its unread-count label match.

**Audit only.** Nothing in the application was changed. Code was read at `7b15b04` on `feature/version-2-ui-ux` (2026-11-04). Behaviour marked "probed" was confirmed with real sessions on an **isolated database copy**, not the development or production database.

**Legend:** ✓ allowed · ✗ denied · — not applicable

**Classes:**
- **KEEP:** current access is appropriate.
- **RESTRICT:** current access appears too broad.
- **GRANT?:** consider granting more access.
- **INCONSISTENT:** the interface and the server disagree.
- **RISK:** possible unauthorized or sensitive access.
- **POLICY:** the barangay must decide.

---

## Part 1 — Role-based access

### 1.1 How authorization actually works

| Layer | Where | What it does |
|---|---|---|
| Account role | `users.role` | `admin`, `captain`, `staff`, `viewer` (legacy), `resident`. |
| Super Admin flag | `users.is_super_admin` + `User::isSuperAdmin()` | Only for `role = admin`. Granted or revoked only by `php artisan user:grant-super-admin`. |
| Administrative access | `User::hasAdministrativeAccess()` = Super Admin **or** active Captain | **`hasPermission()` returns true for every permission** for these two, VAWC included. |
| Staff permissions | `users.staff_permissions` (JSON), checked by `hasPermission()` | Only for `role = staff`. The 30 keys are in `App\StaffPermissions::modules()`. |
| Presets | `StaffPermissions::presets()` | **Not roles**, just pre-ticked checkbox sets in the user form: Secretary / Assistant, Authorized Kagawad, Tanod. They are saved as a plain permission list; the account remembers no preset name. |
| Route gate | `EnsureStaffAccess` + `StaffPermissions::forRoute()` | Every `staff.*` route needs one of the listed permissions. **An unlisted route resolves to `[]` and only Super Admin or Captain pass.** |
| Admin area | `can:view-administration` (Super Admin or Captain) | Audit Log, Users, Settings, Smart Cabinet, Employee Cabinet Access, portal-account suspension. |
| Policies | `app/Policies/*` | Per record: incidents use `vawc.*` for restricted cases, residents, puroks, voters, documents, BPOs, restrictions. |
| Supervisor password | `RequireSupervisorPassword` | Archive or delete of residents, incidents, household members, announcements, cabinet folders, users: password of an active Captain or Super Admin. |
| Frontend | `hasStaffPermission()`, `data-action-permission`, `@can` | Hides buttons. **Not a security boundary;** the server checks above are. |

**Actual roles:**
- Super Admin;
- Barangay Captain;
- Barangay Staff with custom permissions;
- Resident (portal only).

`viewer` and non-super `admin` still exist as role values but are dead (see R7).

**Presets:**
- **Secretary / Assistant:** 31 keys. Dashboard, demographics, RFID, plus every action of records, households, documents, announcements, voters and eligibility.
- **Authorized Kagawad:** dashboard, demographics, RFID, all `incidents.*` and **all `vawc.*`**.
- **Tanod:** `incidents.submit` only.

### 1.2 Findings by priority

| # | Priority | Finding | Evidence | Recommendation |
|---|---|---|---|---|
| R1 | **Critical** | **A Barangay Captain can take over the Super Admin account.** `SaveUserRequest::authorize()` allows any administrative user to edit any personnel account. For a Super Admin target, `UserController::update()` only blocks role and status changes, so **name, email and password can be changed**. | **Probed:** as Captain, `PATCH /admin/users/{superAdmin}` with a new email and password returned 200. Signing in with them landed on the Super Admin dashboard. `app/Http/Requests/Admin/SaveUserRequest.php` l.18–24, `app/Http/Controllers/Admin/UserController.php` l.88–138. | Only a Super Admin may edit a Super Admin account (or its email and password). Captains manage staff only. Super Admin password recovery stays with the local command or the normal reset link. Add a regression test. |
| R2 | **Critical / POLICY** | **Super Admin and Captain read every VAWC/BPO case automatically**, because `hasPermission()` is true for all keys. Super Admin is typically the IT or system administrator, with no case need-to-know. | `User::hasPermission()` l.93–97; `IncidentPolicy`; `Incident::visibleTo`. | Decide who holds VAWC access. Recommended: VAWC only through explicit `vawc.*` assignment (the VAW desk officer or authorized Kagawad), **including for Super Admin**; Captain by policy (RA 9262 handling usually goes through the Punong Barangay or VAW desk). Super Admin keeps account administration, not case content. |
| R3 | High | **Captain has the same administrative powers as Super Admin.** Examples: create staff, assign any permission including `vawc.*`, reset any staff password, enable cabinet access and drawers, view the full audit log, export the user list. | `hasAdministrativeAccess()` gates everything under `/admin`. | POLICY: confirm Captain should manage user accounts and cabinet credentials. If yes, keep it but log password resets distinctly. If no, limit Captain to records and operations, and keep User Management and cabinet credentials with the Super Admin. |
| R4 | High | **Admins can set another person's password directly.** No reset link, no notice to the owner, no forced change at next sign-in. Someone could act as that staff member, and the audit trail would show the staff member's name. | `UserController::update()` l.129–132. | Prefer sending a reset link, or force a change at next sign-in and notify the owner. At minimum, audit "password set by administrator" separately. |
| R5 | High | **Request restrictions: the same administrator can create and approve their own restriction.** Creation saves it as Pending Review; activation needs an explicit review, but the creator can be the reviewer. | `ResidentRequestRestrictionController::store/review`; policy `create` = `update` = administrative access. | POLICY: require reviewer ≠ creator (four-eyes). Practical only if two administrators are active. |
| R6 | Medium | **Add Purok button shows for everyone on Demographics; saving needs `records.create`.** Kagawad and any demographics-only staff get a 403. | **Probed:** Kagawad sees the button; `POST /staff/puroks` returned 403. Button `resources/views/admin/dashboard.blade.php` l.239 has no permission check; route map `staff.puroks.store → records.create`; `PurokPolicy::create`. | INCONSISTENT. Show the button only with the permission that saves. See 1.4 for who should manage puroks. |
| R7 | Medium | **Dead roles.** `role = admin` without the Super Admin flag gets no permissions (`hasPermission` only passes `staff` or administrative access) and lands on "access pending". `viewer` can open settings only. The User Management filter still lists "Barangay Admin" and "View only". | `User::hasPermission`, `canLoginAsPersonnel`, Gate `access-personnel`. | POLICY: retire them (convert existing accounts to staff with explicit permissions, or deactivate) and remove them from filters. |
| R8 | Medium | **Portal-account Suspend/Reactivate is hidden from Captains** (`currentUserRole === 'admin'`), but the server allows them. | `admin.js` `loadResidentPortalAccount`; `ResidentPortalAccountController::update` uses `hasAdministrativeAccess()`. Same as earlier finding A16. | INCONSISTENT. Show it to all administrative users (`is_administrator`). |
| R9 | Medium / POLICY | **Demographics-only staff can open any resident's full details and photo:** name, birth date, address, civil status, household. The `demographics.view` label says "Browse, search, filter and open details". | **Probed:** Kagawad `GET /staff/residents/1/photo` → 200. `ResidentPolicy::view`, route map `staff.residents.show/photo → records.view, demographics.view`. | Demographics is mostly aggregate. Recommended: demographics-only staff see counts and lists but not photos. POLICY on whether they may open individual records. |
| R10 | Medium / POLICY | **The Authorized Kagawad preset bundles full VAWC access.** Any staff given this preset sees every restricted case. | `StaffPermissions::presets()`. | Split it into "Kagawad (ordinary incidents)" and "VAW desk (VAWC/BPO)". Assign VAWC only to designated persons. |
| R11 | Low / POLICY | **The Secretary preset is broad.** One person can review new-resident applications, issue portal activation codes, issue and print certificates, publish announcements, and import or export resident and voter data. | Preset definition. | Reasonable for a small office. POLICY: confirm, or split "records clerk" and "documents" duties if more staff are available. |
| R12 | Low | **The certificates page embeds every document request** (`DocumentRequest::latest()->get()`) in its HTML for `documents.view`. That includes names, addresses and emails in the page source, not just the visible board. | `WorkspaceController` l.85. | Authorized, but load the board from the paginated JSON endpoint instead. Performance and exposure, not a permission bug. |
| R13 | Low | **Two server messages are Filipino only** and appear in English mode. | `SaveUserRequest` l.92, `UserController` l.118. | Catalog them (see Part 2). |

**No problem found in:**
- **Incident visibility:** ordinary staff never see VAWC in lists, counts, links, dashboard or notifications (probed in session 11).
- **Tanod:** limited to the submission form; the API returns 403.
- **Secretary boundaries:** incidents, audit log and `/admin` return 403 (probed).
- **Exports:** gated by their own `*.export` keys and buttons; the voter export has no full COMELEC number.
- **Legacy `/admin/*` mirrors:** they only redirect (307) to the real, audited `staff.*` routes.
- **Destructive actions:** they need the supervisor password.

### 1.3 Permission matrix (current behaviour from code; recommended)

Columns:
- **SA** Super Admin;
- **Cpt** Captain;
- **Sec** Secretary preset;
- **Kag** Authorized Kagawad preset;
- **Tnd** Tanod preset;
- **Custom:** a staff member holding exactly the named permission.

| Module | Action | Permission (server) | SA | Cpt | Sec | Kag | Tnd | Custom | Recommended | Class | Notes (reason / concern) |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Dashboard | View KPIs and activity | `dashboard.view` | ✓ | ✓ | ✓ | ✓ | ✗ | per key | as now | KEEP | Each card shows only what the user may open. |
| Notifications | Bell and recent activity | `dashboard.view` + per-item permission | ✓ | ✓ | ✓ | ✓ | ✗ | per key | as now | KEEP | Filtered on the server. |
| Demographics | View counts, purok cards, charts | `demographics.view` | ✓ | ✓ | ✓ | ✓ | ✗ | per key | as now | KEEP | |
| Demographics | Open a resident's details or photo | `records.view` or `demographics.view` | ✓ | ✓ | ✓ | ✓ | ✗ | per key | photo: `records.view` only | RESTRICT / POLICY | R9 |
| Demographics | Export census (residents spreadsheet) | `records.export` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | Button checked. |
| Puroks | List | any of `demographics/records/households/voters/documents.view` | ✓ | ✓ | ✓ | ✓ | ✗ | per key | as now | KEEP | Names only. |
| Puroks | Create | `records.create` (via `PurokPolicy::create`) | ✓ | ✓ | ✓ | ✗ (button shown → 403) | ✗ | per key | records clerk + administrators | INCONSISTENT | R6; see 1.4 |
| Puroks | Edit, rename, deactivate, delete | **no endpoint**; policy = administrative access | ✗ | ✗ | ✗ | ✗ | ✗ | — | administrators only, if built | POLICY | Not implemented. |
| Resident Records | List, search, filter | `records.view` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Resident Records | Register | `records.create` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | Duplicate check, identity lock. |
| Resident Records | Edit, upload photo | `records.update` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | Version check (V2). |
| Resident Records | Archive, restore | `records.archive` + supervisor password | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Resident Records | Import profiling | `records.import` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Resident Records | Export | `records.export` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP / POLICY | Personal data export; confirm who needs it. |
| Resident Records | View linked incident reports | `incidents.view` / `vawc.view` (per case) | ✓ | ✓ | ✗ | ✓ | ✗ | per key | as now | KEEP | Number, type, status and role only. |
| Resident registration | Review new-resident applications and photo | `records.review` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP / POLICY | R11 |
| Portal accounts | Issue activation code (in-person ID check) | `records.activate` + `identity_checked` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Portal accounts | Suspend, reactivate | administrative access | ✓ | ✓ (no button) | ✗ | ✗ | ✗ | — | administrators | INCONSISTENT | R8 |
| Households | View | `households.view` (`demographics.view`, `records.view` read the API) | ✓ | ✓ | ✓ | ✓ (read) | ✗ | per key | as now | KEEP | |
| Households | Create | `households.create` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Households | Edit, assign members | `households.update` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Households | Remove member | `households.remove` + supervisor password | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Voters | View, search | `voters.view` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | COMELEC number masked. |
| Voters | Register | `voters.create` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Voters | Edit | `voters.update` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | Version and integrity checks. |
| Voters | Import CSV, template | `voters.import` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Voters | Export | `voters.export` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | No full COMELEC numbers. |
| Certificates | View board, history, request details, attachments | `documents.view` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | R12 fixed: the board loads lanes from the paginated list. |
| Certificates | Process, mark ready, reject | `documents.process` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Certificates | Issue (manual or from request) | `documents.issue` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | Eligibility enforced on the server. |
| Certificates | Print, reprint, certificate photo | `documents.print` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Certificates | Fee table (read only) | page access | ✓ | ✓ | ✓ | ✗ | ✗ | — | as now | KEEP | |
| Request Eligibility | View records, checker | `eligibility.view` or `documents.view` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Request Eligibility | Export request records | `eligibility.export` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP / POLICY | All requests, with names. |
| Request restrictions | List (limited columns for staff) | `eligibility.view` or `documents.view` | ✓ (full) | ✓ (full) | ✓ (limited) | ✗ | ✗ | per key | as now | KEEP | Staff never see reasons or case. |
| Request restrictions | Create, review and activate, lift | administrative access | ✓ | ✓ | ✗ | ✗ | ✗ | — | administrators; reviewer ≠ creator | POLICY | R5 |
| Incidents (ordinary) | List, view, history, attachments | `incidents.view` | ✓ | ✓ | ✗ | ✓ | ✗ | per key | as now | KEEP | |
| Incidents (ordinary) | Submit | `incidents.submit` | ✓ | ✓ | ✗ | ✓ | ✓ (form only) | per key | as now | KEEP | Tanod cannot read cases. |
| Incidents (ordinary) | Edit, manage, assign | `incidents.update` | ✓ | ✓ | ✗ | ✓ | ✗ | per key | as now | KEEP | |
| Incidents (ordinary) | Archive | `incidents.archive` + supervisor password | ✓ | ✓ | ✗ | ✓ | ✗ | per key | as now | KEEP | |
| VAWC / BPO | View restricted cases and BPOs | `vawc.view` | ✓ (implicit) | ✓ (implicit) | ✗ | ✓ | ✗ | per key | **explicit assignment only** | RISK / POLICY | R2, R10 |
| VAWC / BPO | Create restricted case or BPO | `vawc.submit` | ✓ | ✓ | ✗ | ✓ | ✗ | per key | designated VAW desk | POLICY | |
| VAWC / BPO | Manage | `vawc.update` | ✓ | ✓ | ✗ | ✓ | ✗ | per key | designated VAW desk | POLICY | |
| VAWC / BPO | Archive | `vawc.archive` + supervisor password | ✓ | ✓ | ✗ | ✓ | ✗ | per key | designated + supervisor | POLICY | |
| Announcements | View all (drafts included) | `announcements.view` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Announcements | Create draft | `announcements.create` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Announcements | Edit | `announcements.update` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| Announcements | Publish, unpublish | `announcements.publish` | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now / POLICY | KEEP | Public-facing; confirm whether the Secretary publishes without approval. |
| Announcements | Archive, restore | `announcements.archive` (+ supervisor password to archive) | ✓ | ✓ | ✓ | ✗ | ✗ | per key | as now | KEEP | |
| RFID File Tracking | View movement log | `rfid.view` | ✓ | ✓ | ✓ | ✓ | ✗ | per key | as now / POLICY | KEEP | Shows employee names and file references. |
| Smart Cabinet | View devices; edit readers and folders | administrative access (+ supervisor password to archive folders) | ✓ | ✓ | ✗ | ✗ | ✗ | — | as now / POLICY | KEEP | R3 |
| Employee Cabinet Access | Enable access, drawers, RPi ID, enrollment | administrative access | ✓ | ✓ | ✗ | ✗ | ✗ | — | Super Admin (+ Captain by policy) | POLICY | Physical security credentials. |
| Audit Log | View | administrative access | ✓ | ✓ | ✗ | ✗ | ✗ | — | as now | KEEP | |
| User Management | List, export | administrative access | ✓ | ✓ | ✗ | ✗ | ✗ | — | as now / POLICY | KEEP | R3 |
| User Management | Create staff, assign permissions | administrative access | ✓ | ✓ | ✗ | ✗ | ✗ | — | VAWC assignment by Super Admin only? | POLICY | R3, R10 |
| User Management | Edit a staff account (incl. password) | administrative access | ✓ | ✓ | ✗ | ✗ | ✗ | — | reset link instead of setting a password | RISK | R4 |
| User Management | **Edit a Super Admin account (email, password)** | administrative access | ✓ | **✓ (probed)** | ✗ | ✗ | ✗ | — | **Super Admin only** | **RISK (critical)** | R1 |
| User Management | Suspend or delete staff | administrative access + supervisor password (delete) | ✓ | ✓ | ✗ | ✗ | ✗ | — | as now | KEEP | Super Admin and self excluded. |
| User Management | Grant or revoke Super Admin | server command only | console | ✗ | ✗ | ✗ | ✗ | — | as now | KEEP | |
| Settings (hub) | Workspace settings, hardware status | administrative access | ✓ | ✓ | ✗ | ✗ | ✗ | — | as now | KEEP | |
| Account settings | Own profile, password, 2FA, appearance | any active personnel | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | as now | KEEP | Self-deletion removed (session 9). |

### 1.4 Add Purok, in detail

| Question | Current answer (code) |
|---|---|
| Who can view puroks? | Anyone with demographics, records, households, voters or documents view (`PurokPolicy::viewAny`, route map). |
| Who can create? | `records.create`: Super Admin, Captain, Secretary preset, or custom staff with "Register residents". |
| Who can edit, rename, deactivate or delete? | **Nobody:** there is no route. `PurokPolicy::update/delete` says administrators, but nothing uses it. Puroks have an `is_active` flag with no screen to change it. |
| Frontend vs backend | **Mismatch:** the Demographics "Add Purok" button has no permission check, so a Kagawad sees it and gets a 403 (probed). |
| Intentional? | Linking purok creation to "Register residents" looks like a convenience (a clerk registering a resident from a new purok), not a decision. No document mentions it. |

**Recommendation:**
- Puroks are barangay geography and rarely change. A wrong or duplicate purok affects demographics, voters and documents.
- **Option A (no new permission):** keep `records.create` and hide the button without it.
- **Option B (cleaner):** add a `puroks.manage` permission (new key, needs approval) given to administrators and optionally the Secretary.

Renaming or deactivating should stay administrator-only if it is ever built.

### 1.5 Items needing a barangay policy decision

1. Who may read VAWC/BPO cases: only designated persons, or also the Captain and/or the Super Admin? (R2)
2. Should the Captain manage user accounts, permissions and cabinet credentials, or only the Super Admin? (R3)
3. Admin password changes for staff: reset link or forced change instead of direct setting? (R4)
4. Request restrictions: require a second administrator to approve? (R5)
5. Purok management: option A or B; who may rename or deactivate? (R6, 1.4)
6. Retire the legacy `admin` (non-super) and `viewer` roles? (R7)
7. Demographics-only staff: may they open individual records and photos? (R9)
8. Split the Kagawad preset into ordinary incidents and VAW desk? (R10)
9. Secretary preset scope; does the Secretary publish announcements and export personal data without approval? (R11)

---

## Part 2 — Filipino language review

### 2.1 Overall assessment

- **Coverage is good.** 2,005 Filipino entries. Staff scripts have no untranslated English-mode leaks except the two server messages in R13.
- **Tone is uneven.** The resident portal mostly reads naturally ("Puwede nang kunin sa Barangay Hall"). Many staff labels are dictionary translations that barangay staff do not say: "Pangkalahatang-ideya", "Demograpiko", "Katayuang sibil", "Sambahayan", "Pagiging kwalipikado sa request", "Mga naisyung sertipiko".
- **Meaning errors:**
  - "Contact" (a field label for a phone number) is translated as **"Makipag-ugnayan"** ("get in touch").
  - The Demographics title reads **"Populasyon Demograpiko"**, which is ungrammatical.
- **Official document names are translated:**
  - Certificate of Residency → "Katibayan ng Paninirahan"
  - Certificate of Indigency → "Katibayan ng Indigency"
  - First Time Jobseeker → "Katibayan para sa Unang Naghahanap ng Trabaho"
  - Registered Voter Certification → "Katibayan ng Rehistradong Botante"

  These are the names printed on the certificates. Residents and staff use the English names, so they should stay English in both languages.
- **Inconsistent wording:** the same idea has several translations.
  - Pending: "Nakabinbin", "Naghihintay ng pagsusuri", "Pending".
  - Review: "Suriin", "Repasuhin", "pagsusuri".
  - Publish: "Ilathala", "Nailathala".
  - Several capitalization variants of one label: "Activation Code" / "Activation code" / "activation code"; "Detalye ng request" / "Detalye ng Request".

### 2.2 Style rules proposed

1. **Keep familiar English terms in Taglish:** Dashboard, Records, Request, Status, Purok, Household, Voter, Account, Password, Email, Export, Import, Archive, Print, Release, Search results, Draft, Publish, Cancel, Save, Edit, Upload, Activation code, Reference number, Valid ID.
2. **Use the Filipino verb with "i-"/"mag-" for actions:** I-save, I-edit, I-print, I-export, Mag-request, Mag-log in.
3. **Short labels:** buttons ≤ 3 words; sentence case, not Title Case.
4. **Official names unchanged:** document names, VAWC, BPO, RA numbers, COMELEC, PSA, Data Privacy Act.
5. **Use "ninyo"/"kayo" consistently** for residents (polite). Staff screens may use neutral imperatives ("I-save ang changes").
6. **Never simplify legal or security meaning:** see 2.5.

### 2.3 Representative before → after (high-visibility labels)

| English source | Current Filipino | Proposed | Where | Why |
|---|---|---|---|---|
| Overview | Pangkalahatang-ideya | Overview | Sidebar | Dictionary term nobody uses. |
| Demographics | Demograpiko | Demographics | Sidebar, page | Familiar as English. |
| Population Demographics | Populasyon Demograpiko | Populasyon ng Barangay | Page title | Ungrammatical now. |
| Resident Records | Mga rekord ng residente | Mga Resident Record | Sidebar, page | Shorter; familiar. |
| Records | Mga rekord | Records | Sidebar group | |
| Certificates & Clearances | Mga sertipiko at clearance | Certificates at Clearances | Sidebar, page | Office term. |
| Request Eligibility | Pagiging kwalipikado sa request | Eligibility ng Request | Sidebar, page | Current wording is awkward. |
| Incident Reports | Mga ulat ng insidente | Mga Incident Report | Sidebar, page, dashboard | Common usage. |
| Announcements | Mga anunsiyo | Mga Announcement | Sidebar, portal | Both acceptable; "anunsyo" spelling varies. Pick one. |
| RFID File Tracking | Pagsubaybay sa RFID file | RFID File Tracking | Sidebar | Technical module name. |
| Employee Cabinet Access | Access sa cabinet ng empleyado | Cabinet Access ng Staff | Sidebar | |
| User Management | Pamamahala ng mga user | Mga User | Sidebar | |
| Administration | Pangangasiwa | Administration | Sidebar group | |
| Public Information | Pampublikong impormasyon | Para sa Publiko | Sidebar group | |
| IoT Security | Seguridad ng IoT | IoT Security | Sidebar group | |
| Quick Actions | Mga mabilisang aksyon | Quick Actions | Dashboard | User example. |
| Search | Hanapin | Maghanap | Search fields | User example; reads as an action. |
| Save changes | I-save ang mga pagbabago | I-save ang changes | Forms | |
| Cancel | Kanselahin | Cancel | Dialogs | Familiar; "Kanselahin" is formal. |
| Submit | Isumite | I-submit | Forms | |
| Delete | Burahin | I-delete | Users | |
| Restore | Ibalik | I-restore | Records | "Ibalik" is ambiguous (return a file?). |
| Suspend | Suspindihin | I-suspend | Users, portal accounts | |
| View Details | Tingnan ang Detalye | Tingnan ang Details | Lists | User example. |
| Active Residents | Mga aktibong residente | Mga Active na Resident | Dashboard KPI | |
| Issued Certificates | Mga naisyung sertipiko | Mga Na-issue na Certificate | Dashboard KPI | "naisyung" is awkward. |
| Pending Requests | Mga nakabinbing request | Mga Pending na Request | Dashboard KPI | User example. |
| Pending | Nakabinbin | Pending | Status pills | |
| Under Review | Sinusuri | Under review | Status pills | Status labels should match staff talk. |
| Resolved | Nalutas | Resolved | Status pills | |
| Total Households | Kabuuang household | Lahat ng Household | Demographics KPI | |
| Household | Sambahayan | Household | Forms, tables | "Sambahayan" is formal. |
| Manage Households | Pamahalaan ang mga sambahayan | I-manage ang Households | Records | |
| Date of Birth | Petsa ng kapanganakan | Birthday (staff forms: "Petsa ng kapanganakan" stays in official records and certificates) | Forms | User example; context-dependent. |
| Gender | Kasarian | Kasarian (Sex) | Forms | Keep the meaning; add the familiar term. |
| Civil Status | Katayuang sibil | Civil status | Forms | Office term. |
| Address | Tirahan | Address | Forms | |
| **Contact** | **Makipag-ugnayan** | **Contact number** | Resident details | **Meaning error.** |
| Residency Type | (varies) | Uri ng paninirahan → "Residency type" | Forms | |
| Special Groups | Mga special group | Special groups | Records | |
| Notifications | Mga abiso | Notifications | Top bar | |
| Mark all read | Markahang nabasa lahat | Basa na lahat | Notifications | |
| Appearance | Hitsura | Itsura | Settings | Common spelling. |
| Live Count | Live na bilang | Live count | Demographics | |
| Age Distribution | Distribusyon ayon sa edad | Bilang ayon sa edad | Demographics | |
| Required field | Kailangang punan | Required | Forms | |
| No records found. | Walang nakitang rekord. | Walang nakitang record. | Lists | Spelling consistency. |
| Data Privacy Notice | Paunawa sa Pagkapribado ng Datos | Data Privacy Notice | Portal | Legal title under RA 10173; keep English. |
| Published | Nailathala | Published | Announcements | |
| Publish / Unpublish | Ilathala / Huwag nang ilathala | I-publish / I-unpublish | Announcements | |
| Released | Nakuha na | Released (staff) / Nakuha na (resident) | Status | Staff mean "released by office"; residents mean "picked up". Keep both by context. |

### 2.4 Official document names (keep English in Filipino mode)

| Document | Current Filipino | Proposed |
|---|---|---|
| Certificate of Residency | Katibayan ng Paninirahan | Certificate of Residency |
| Certificate of Indigency | Katibayan ng Indigency | Certificate of Indigency |
| First Time Jobseeker | Katibayan para sa Unang Naghahanap ng Trabaho | First Time Jobseeker Certificate |
| Registered Voter Certification | Katibayan ng Rehistradong Botante | Registered Voter Certification |
| Barangay Clearance, Business Clearance, Barangay ID | already English | keep |

A short Filipino description can go under the name where helpful, for example "Patunay na nakatira kayo sa barangay".

### 2.5 Keep precise (do not simplify)

- **VAWC, BPO, RA 9262**, "protected person", "respondent", "complainant". "Inireklamo" and "Nagreklamo" are acceptable in ordinary incidents. In BPO screens keep "Respondent" and "Protected person" with the legal sense.
- **"Supervisor password"** and the confirmation that names "Barangay Captain or Super Admin".
- **Security and 2FA messages**, account-takeover and activation instructions: meaning over brevity.
- **Request hold messages** ("on hold and requires barangay review") must not suggest a finding or a penalty.
- **Eligibility reasons** (good standing, verified indigency, one-time Jobseeker).
- **Data Privacy Act wording** in the request terms.

### 2.6 Consistency fixes (one term each)

| Concept | Variants found | Proposed single term |
|---|---|---|
| Review | Suriin · Repasuhin · pagsusuri · Sinusuri | i-review / Under review |
| Pending | Nakabinbin · Naghihintay ng pagsusuri · Pending | Pending |
| Publish | Ilathala · Nailathala | I-publish / Published |
| Record | rekord · record | record |
| Certificate | sertipiko · certificate · katibayan | certificate (official names unchanged) |
| Activation code | Activation Code · Activation code · activation code | Activation code |
| Request details | Detalye ng request · Detalye ng Request | Detalye ng request |
| Public information | Impormasyon para sa publiko · Pampublikong impormasyon | Para sa publiko |

**Complete replacement list:** prepared on approval as a reviewable diff of `lang/fil.json`. It would cover about 350 entries across the patterns above (the staff workspace mostly; the portal needs fewer changes). Approve the style rules first, so the full list follows one agreed standard.

### 2.7 English-mode leaks to fix

- `SaveUserRequest`: "May active na Barangay Captain. Ilipat muna ang designation bago magtalaga ng iba."
- `UserController`: "Hindi maaaring alisin o suspindihin ang sariling Captain access. Ipa-update ito sa ibang administrator."

Both should become English source text with Filipino catalog entries.

---

## Part 3 — Proposed implementation sequence

1. **Security first (separate commits, each with regression tests):**
   - R1: only a Super Admin edits a Super Admin.
   - R2 and R10, once decided: VAWC access by explicit assignment.
   - R4: password resets by link or forced change.
2. **Permission and navigation consistency:**
   - R6 Add Purok button, R8 portal-account button, R13 messages.
   - The R9 photo restriction, if approved.
   - Retire dead roles (R7), if approved.
3. **Policy-driven changes:** R3, R5, R11, Purok option B. Each after its decision.
4. **Language:**
   - Approve the style rules (2.2), then the document-name decision (2.4).
   - Then the full `fil.json` diff, and the portal and staff source strings that carry Filipino directly.
   - Extend the catalog test so official document names stay English.
5. **Regression:**
   - Role probes for each role (Super Admin, Captain, Secretary, Kagawad, Tanod, custom) against every route in `StaffPermissions::forRoute`.
   - Browser checks in both languages.
   - The full Pest and Node suites.

## Final permission implementation plan (awaiting approval of the access matrix)

**Nothing in this section is implemented. No permission has been granted or revoked.** Each item is ready to build once the matching decision in Part 4 is ticked.

**Accounts it would affect today** (read-only count of the development database, 2026-11-05):

| Account | Role | Permissions now |
|---|---|---|
| #2 | Super Admin | all (implicit) |
| #4 | Barangay Captain | all (implicit) |
| #5, #8 | Staff | Secretary / Assistant preset (31 keys) |
| #6 | Staff | Authorized Kagawad preset (11 keys, includes all `vawc.*`; demographics without records) |
| #7 | Staff | Tanod preset (`incidents.submit`) |

- There are no legacy `admin` (non-super) or `viewer` accounts, and no request restrictions yet.
- There are two active administrators.
- **Production must be counted the same way before rollout.** The census script only reads data.

**Common to every item:**
- Each item is its own commit with its own tests, so it can be reverted alone.
- The role probes (`StaffPermissions::forRoute` against every role) and the browser checks run in both languages on an isolated copy before any commit.

### R2: VAWC/BPO access only by explicit assignment

| | |
|---|---|
| **Current** | `User::hasPermission()` returns true for every key when `hasAdministrativeAccess()`, so the Super Admin and the Captain read every VAWC case and BPO. Every VAWC check already goes through `hasPermission('vawc.*')`: `IncidentPolicy`, `BarangayProtectionOrderPolicy`, `Incident::scopeVisibleTo`, dashboard and incident counts, and `StoreIncidentRequest`. |
| **Recommended** | `vawc.*` only when the key is in the account's `staff_permissions`, **for administrators too**. The Super Admin gets no VAWC access by default. The Captain gets it only if the barangay designates the Punong Barangay to handle RA 9262 cases (decision D1). |
| **Affected** | Super Admin #2 and Captain #4 lose VAWC/BPO reading and handling until assigned. Kagawad #6 is unchanged. Ordinary incidents are unchanged for everyone. |
| **Backend** | `hasPermission()`: `str_starts_with($permission, 'vawc.')` → check `staff_permissions` even for administrators. `SaveUserRequest::after()` and `UserController::saveAssignments()`: allow **only** `vawc.*` keys on Captain and Super Admin accounts (today only staff, plus the dormant Super Admin path). Audit any change to `vawc.*` as `admin.users.vawc-access-changed` with the before/after lists. |
| **Frontend** | User form: for Captain and Super Admin accounts, show only the "Incidents & restricted cases → restricted" group, with a note that other modules are already full access. `AUTHENTICATED_USER.permissions` must reflect the explicit keys so menus and buttons follow (incidents menu stays for ordinary cases). |
| **Security** | Narrows access to the most sensitive records (need to know). Administrators can still grant themselves VAWC, but that is now a recorded, visible act rather than a silent default. |
| **Rollout risk** | Ongoing VAWC cases become invisible to the Captain at deploy. Mitigation: at rollout, assign `vawc.*` to the designated persons **in the same deploy**, decided before deploy. Check that at least one active account holds `vawc.view`; refuse to remove the last one (validation message). |
| **Tests** | Super Admin and Captain without keys: VAWC hidden in list, counts, dashboard, notifications and linked incidents, plus 403 on view, update and BPO. With `vawc.view`: visible. Ordinary incidents unchanged. Assigning or removing `vawc.*` is audited. The last holder cannot be removed. |
| **Rollback** | Revert the commit (one method plus validation). Explicit keys stay in the JSON column and are harmless. |

### R3: Super Admin vs Captain

| | |
|---|---|
| **Current** | `can:view-administration` = Super Admin or active Captain for all of `/admin`: users (create, edit, permissions, suspend, delete, export), cabinet access and drawers, Smart Cabinet, audit log, settings. Since R1, only a Super Admin may change a Super Admin account. |
| **Recommended** | **Option A (recommended for a small barangay):** the Captain keeps User Management and cabinet credentials (the accountable official), with distinct audit events for sensitive changes: VAWC grants (R2), password reset links (R4), cabinet drawer changes. **Option B:** a new gate `manage-accounts` = `isSuperAdmin()` on `admin.users.store/update/destroy/export` and `admin.cabinet-access.*`; the Captain keeps the audit log, Smart Cabinet status and settings. |
| **Affected** | A: none. B: Captain #4 loses account and cabinet-credential management. |
| **Backend** | A: audit events only. B: Gate `manage-accounts`; route middleware `can:manage-accounts` on the listed routes; `SaveUserRequest::authorize` uses it. |
| **Frontend** | B: hide the Users and Cabinet Access navigation, and the add, edit and delete buttons, for Captains (`is_super_admin` flag already in `AUTHENTICATED_USER`). |
| **Security** | B removes the Captain's ability to create a staff account with any permission (privilege path). A keeps it but makes every sensitive change visible. |
| **Rollout risk** | B: if the Super Admin is an outside IT person, day-to-day account work (new staff, suspensions) would wait for them. |
| **Tests** | B: Captain gets 403 on each listed route, keeps audit and Smart Cabinet; Super Admin unchanged; navigation hidden. A: audit rows written. |
| **Rollback** | Revert; no data change. |

### R4: Administrator password changes

| | |
|---|---|
| **Current** | `UserController::update()` sets `password` directly when filled (any staff or Captain account; never another Super Admin since R1). There is no notice to the owner, and no audit entry distinct from a general edit. |
| **Recommended** | Remove the password field from **edit**. Add **"Send password reset link"** (Fortify broker `Password::sendResetLink`; the routes `password.request` and `password.reset` already exist). Audit it as `admin.users.password-reset-sent`. New accounts keep an initial password (D3 can choose a reset link instead). |
| **Affected** | Super Admin and Captain lose the ability to type another person's password. Account owners are unaffected and still change their own in Settings. |
| **Backend** | `SaveUserRequest`: `password` prohibited on update for other accounts. New route `POST /admin/users/{user}/password-reset` (`can:view-administration`, or `manage-accounts` under R3 option B), throttled; refuses Super Admin targets unless the actor is that Super Admin. |
| **Frontend** | Edit dialog: replace the password input with a "Send reset link" button and a status line. Add dialog unchanged. |
| **Security** | No one can sign in as a colleague by setting their password, and the audit trail stays truthful. |
| **Rollout risk** | **Needs working outgoing mail in production.** If mail is not available, staff could not recover a forgotten password without the server command. A "forced change at next sign-in" fallback needs a new column (migration, separate approval). |
| **Tests** | Update with `password` → 422. Reset link sent and audited; throttle. Super Admin target refused for others. Owner's own password change unchanged. |
| **Rollback** | Revert; no data change. |

### R5: Request restrictions need a second administrator

| | |
|---|---|
| **Current** | `ResidentRequestRestrictionController::store` saves Pending Review with `created_by`; `review` activates it with `reviewed_by`. The same administrator may do both. |
| **Recommended** | `review` refuses when `created_by === actor` ("Ask another administrator to review and activate this restriction."). **Lifting stays open** to any administrator, the creator included, because it only restores a resident's access. |
| **Affected** | Super Admin and Captain: each needs the other to activate. With two active administrators today, this is workable. |
| **Backend** | One check in `review`. Audit the refusal as `admin.request-restrictions.self-review-denied`. |
| **Frontend** | On a restriction the viewer created, the Review button is disabled with the note "Needs another administrator." |
| **Security** | Four-eyes on a measure that blocks a resident's documents, which reduces abuse or error by one person. |
| **Rollout risk** | If only one administrator is active, nothing can be activated. Decide whether an emergency path is needed (D4); the recommendation is none. |
| **Tests** | Creator review → 422 and audited; other administrator → activated; creator may lift; existing active restrictions unaffected. |
| **Rollback** | Revert. |

### R7: Legacy roles

| | |
|---|---|
| **Current** | `admin` without the Super Admin flag and `viewer` still validate in `SaveUserRequest` (kept when the account already has them) and show in User Management filters. They have no operational access. |
| **Recommended** | Retire both: remove them from the role filters and from `allowedRoles`. Before deploy, list such accounts in production and convert each to `staff` with explicit permissions, or deactivate (a data step, approved per account). |
| **Affected** | None in the development database. |
| **Backend** | `SaveUserRequest` role list; `canLoginAsPersonnel` keeps `admin` only for the Super Admin. |
| **Frontend** | Remove "Barangay Admin" and "View only" from filters and labels. |
| **Security** | Removes dormant account states nobody reviews. |
| **Rollout risk** | A legacy account in production would stop signing in. Mitigation: census first. |
| **Tests** | Legacy roles rejected on save; filters list only real roles; Super Admin unaffected. |
| **Rollback** | Revert; converted accounts keep their new state, documented in the data step. |

### R8: Portal-account Suspend / Reactivate for Captains (interface only)

| | |
|---|---|
| **Current** | The server (`ResidentPortalAccountController::update`) allows both administrators; `admin.js` l.5994 shows the button only when `currentUserRole === 'admin'` (Super Admin). |
| **Exact change** | `admin.js`: `if (currentUserRole === 'admin')` → `if (window.AUTHENTICATED_USER?.is_administrator)`. |
| **Affected** | Captain #4 sees a button for an action they can already perform. No permission changes. |
| **Tests** | Static UI test for the condition; browser check as Captain (suspend, then reactivate, on an isolated copy). |
| **Rollback** | Revert one line. |

### R9: Resident photos for demographics-only staff

| | |
|---|---|
| **Current** | Route map: `staff.residents.show` and `staff.residents.photo` → `records.view` **or** `demographics.view`. `show()` already trims fields for non-records staff, but the photo endpoint returns the image (probed 200 for Kagawad). The staff interface shows a resident photo only in the edit form (`records.update`), so demographics-only staff never see one on screen; they can only reach it by URL. |
| **Exact change** | `StaffPermissions` route map: `'staff.residents.photo' => ['records.view']`. `show` stays as it is (decision D7 can also remove `show` for demographics-only staff). |
| **Affected** | Kagawad #6 (demographics without records): photo by URL → 403. Nothing on screen changes. |
| **Tests** | Demographics-only → 403 on photo, 200 on trimmed details; records staff → 200 on both. |
| **Rollback** | Revert one line. |

### R10: Split the Kagawad preset

| | |
|---|---|
| **Current** | "Authorized Kagawad" = dashboard, demographics, RFID, all `incidents.*` **and all `vawc.*`**. Presets only pre-tick boxes; accounts store plain keys. |
| **Recommended** | "Kagawad" = dashboard, demographics, RFID, `incidents.view/submit/update`. New "VAW Desk" = dashboard, `vawc.*`, `incidents.view` (to see linked ordinary cases). `incidents.archive` stays out of both (supervisor tasks). |
| **Affected** | No existing account changes automatically. Kagawad #6 keeps `vawc.*` until edited. Removing it is an explicit account decision (D8). |
| **Backend** | `StaffPermissions::presets()` only. |
| **Frontend** | Preset buttons in the user form; translations for the new name. |
| **Security** | Choosing a preset no longer grants VAWC by accident. |
| **Tests** | Preset lists; the user form applies them; no account changed by deploy. |
| **Rollback** | Revert. |

### R11: Secretary duties

| | |
|---|---|
| **Current** | "Secretary / Assistant" = 31 keys: all records (review, activate, import, export), households, voters (import, export), documents (issue, print), announcements (publish), eligibility (export). |
| **Recommended** | Keep the preset (it suits a one-clerk office). Add two narrower presets for larger offices: **"Records Clerk"** (records except export, households, voters except import and export) and **"Documents Officer"** (documents, eligibility view, announcements without publish). Exports and publishing stay with whoever the barangay names (D9). |
| **Affected** | None until assigned. Secretaries #5 and #8 are unchanged. |
| **Backend / Frontend** | `presets()` and the preset buttons only. |
| **Tests** | Preset lists; no account changed. |
| **Rollback** | Revert. |

### R14: Incident staff list loaded for staff without incident access (new, interface only)

| | |
|---|---|
| **Current** | `case-management.js` calls `loadCaseStaff()` (`GET /staff/incident-options`) on every staff page. The route needs `incidents.view` or `vawc.view`, so the Secretary gets a 403 on each page load and an error notice ("Unable to load household records.", the generic fallback). Found by the role probe; no other screen refuses a request for Secretary, Kagawad, VAW desk or Captain. |
| **Exact change** | `case-management.js`: `if (['incidents.view', 'vawc.view'].some(p => window.PERSONNEL_PERMISSIONS?.includes(p) ?? true)) void loadCaseStaff();`. No route or permission change. |
| **Affected** | Staff without incident access stop seeing a false error. |
| **Tests** | Node test for the condition; probe shows no refused requests. |
| **Rollback** | Revert one line. |

### Final proposed access matrix (what changes if every recommendation is approved)

Only the rows that change are shown. Every other row of 1.3 stays as it is.

Columns:
- **SA** Super Admin;
- **Cpt** Captain;
- **Sec** Secretary preset;
- **Kag** new Kagawad preset;
- **VAW** new VAW Desk preset;
- **Tnd** Tanod.

| Capability | SA | Cpt | Sec | Kag | VAW | Tnd | Now |
|---|---|---|---|---|---|---|---|
| View / manage VAWC cases and BPOs | assigned only | assigned only (D1) | ✗ | ✗ | ✓ | ✗ | SA, Cpt implicit; Kag preset ✓ |
| Archive VAWC case (+ supervisor password) | assigned only | assigned only | ✗ | ✗ | ✓ | ✗ | same as above |
| Ordinary incidents view / submit / update | ✓ | ✓ | ✗ | ✓ | view | submit | Kag preset also archive |
| Create staff, assign permissions | ✓ | ✓ (A) / ✗ (B) | ✗ | ✗ | ✗ | ✗ | SA, Cpt |
| Set another person's password | ✗ (reset link) | ✗ (reset link) | ✗ | ✗ | ✗ | ✗ | SA, Cpt |
| Activate a request restriction they created | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | SA, Cpt |
| Activate a restriction created by the other administrator | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | same |
| Suspend / reactivate portal account (button) | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | button SA only |
| Resident photo by URL with demographics only | — | — | — | ✗ | — | — | ✓ |
| Cabinet credentials and drawers | ✓ | ✓ (A) / ✗ (B) | ✗ | ✗ | ✗ | ✗ | SA, Cpt |

### Decisions to approve

| # | Decision | Recommendation |
|---|---|---|
| D1 | Who holds VAWC/BPO access | Explicit only. Super Admin: none. Captain: assigned only if designated. Name the VAW desk person(s) for rollout. |
| D2 | Captain and account / cabinet management (R3) | Option A: keep, with distinct audit events. |
| D3 | Passwords set by administrators (R4) | Reset link only; confirm production mail works first. Initial password for new accounts: keep. |
| D4 | Four-eyes for restrictions (R5) | Yes; creator may still lift; no emergency bypass. |
| D5 | Retire legacy roles (R7) | Yes, after a production census. |
| D6 | R8 portal-account button for Captain | Yes (interface only). |
| D7 | Demographics-only staff and records (R9) | Details without photo. |
| D8 | Kagawad preset split (R10); remove `vawc.*` from account #6? | Split. Account #6 per D1. |
| D9 | Secretary scope (R11); who exports and publishes | Keep the preset; add Records Clerk and Documents Officer. |
| D10 | R14 incident-options call | Yes (interface only). |
| — | Purok option B (`puroks.manage`) | Still open; not needed now. |

## Part 4 — Approval checklist

**Security:**
- [x] **R1** Only Super Admin may edit a Super Admin account; credentials only by the owner. *(done)*
- [ ] **R2** VAWC/BPO access only by explicit `vawc.*` assignment. Decide for Captain: ☐ implicit ☐ explicit only. Decide for Super Admin: ☐ implicit ☐ explicit only.
- [ ] **R4** Replace direct password setting by administrators with a reset link or forced change.

**Administration and duties:**
- [ ] **R3** Captain keeps User Management and cabinet credentials ☐ yes ☐ no (Super Admin only).
- [ ] **R5** Request restrictions need a second administrator to approve.
- [ ] **R10** Split the "Authorized Kagawad" preset into ordinary incidents and VAW desk.
- [ ] **R11** Confirm or split the Secretary preset (publishing, exports).

**Consistency:**
- [x] **R6** Add Purok hidden without `records.create` (option A, done). ☐ Option B (new `puroks.manage`) still open.
- [ ] **R7** Retire the legacy `admin` (non-super) and `viewer` roles.
- [ ] **R8** Show portal-account Suspend/Reactivate to Captains (as the server already allows).
- [ ] **R9** Demographics-only staff: ☐ keep details and photo ☐ details without photo ☐ aggregates only.
- [x] **R12** Load the certificates board from the paginated endpoint instead of embedding all requests. *(done)*
- [ ] **R14** Load the incident staff list only for staff with incident access (interface only).

**Language:**
- [x] **L1** Filipino style rules adopted.
- [x] **L2** Official document names kept as printed (test added).
- [x] **L3** Remaining catalog reviewed in context; untranslated staff labels added.
- [x] **L4** English-mode leaks fixed (three in total).
