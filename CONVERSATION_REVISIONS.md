# Anabu 1G SmartBrgy — Conversation Revision Notes

This file summarizes the website revisions discussed in this conversation. It is a working history, not a verbatim transcript. Details below reflect the conversation and reported checks; review the current code and deployment before treating any item as a release record.

## Project direction

- Keep the website consistent and usable on desktop and mobile, with particular attention to resident and staff workflows.
- Preserve existing authorization, resident data, business rules, and ongoing local changes while revising the UI.
- Hardware changes are outside the project scope; the work is limited to the website.
- Use the existing three user levels: Resident, Barangay Staff, and Super Admin. Staff assignments do not create new top-level roles.

## Revisions discussed and implemented

### Navigation, account access, and permissions

- Refined Super Admin and staff navigation, including page transitions, sidebar scrolling, and the orange scrollbar styling.
- Added a sidebar account menu with a logout option and a confirmation popup.
- Converted Account Security password confirmation to a popup and added show/hide controls to password fields in resident and admin portals.
- Added Super Admin-managed staff page/action assignments and editable presets for Secretary/Assistant, Authorized Kagawad, and Tanod. Administration remains Super Admin-only; physical cabinet access stays separate from RFID page access.
- Tanod access was shaped as incident submission only, within the existing incident-report workflow. The form includes incident categories and an “Iba pa” option that reveals a text field. Successful submission shows a reference and allows another report, without opening an incident detail page.
- Ordinary incident handling and VAWC/BPO access remain separately permissioned. The Tanod form includes guidance to refer sensitive concerns to authorized barangay personnel.

### Resident registration and activation

- Defined the new-resident self-registration path around application review: new residents apply, receive an approval or decline outcome, and proceed to account setup/activation after approval.
- Kept the existing-resident activation path distinct from new-resident registration.
- Adjusted the registration continuation link so a new applicant is not sent directly into the existing-resident activation flow.
- Added approval/decline email messaging, including a decline reason where available. The user later confirmed that email delivery was working.
- Shortened activation codes and made typed codes visible.

### Upload restrictions and incident reporting

- Restricted resident photo uploads to image files and addressed upload validation so unsupported files such as PDFs are rejected.
- Added incident categories to the Tanod submission form and conditional free-text entry for “Iba pa”.
- Kept incident descriptions neutral and focused on observed or reported facts; an allegation should not be presented as a finding.

### Resident Portal language and request flow

- Added an English/Filipino language toggle for resident-facing pages and flows, preserving the preference while navigating and refreshing. Admin language was intentionally left unchanged.
- Fixed the Resident Portal header layout in Filipino and malformed “>Request” text in requirement/fee request buttons.
- Refined registration, login, activation, request, history/status, and profile presentation as part of the resident-facing language and UI work.

### Visual consistency and responsive layout

- Worked through staff/admin sidebar scrollbar appearance and removed the unwanted orange edge/line artifacts identified in screenshots.
- Standardized button sizing and alignment where labels had caused uneven controls.
- Refined cards, forms, modal layouts, navigation, and request boards for responsive viewing.
- Changed the mobile document-request summary cards to a horizontal layout to reduce excessive vertical scrolling; desktop layout was intended to remain as before.
- Continued visual consistency work across public, admin/staff, and private resident pages. Recent private resident checks covered request selection and review, request history/status, and profile views. The request was not submitted during those checks.

## Verification reported in the conversation

- Earlier broad verification reported 904 passing tests and 2 skipped, plus 76 JavaScript tests; Pint, PHPStan, and the production build were also reported as passing at that stage.
- A later resident-focused test run reported 141 passing tests, and the production build passed after the mobile profile spacing adjustment.
- Earlier responsive checks covered staff/admin pages and public pages. Later resident browser checks were performed at desktop, tablet, and mobile widths and in both portal languages; the user’s browser session was used for protected pages.
- A local test login initially used an account that was not linked as a resident; the correct existing resident account was used afterward. No passwords or account identifiers are recorded here.
- No document request was submitted during the browser walkthrough.

## Still open or requiring confirmation

- Verify the current deployment against the latest local responsive/UI changes. The user said an earlier revision set had been pushed to GitHub and AWS, but the current workspace has uncommitted changes, so this note does not claim that the latest local changes are deployed.
- Complete English/Filipino localization for Admin/Super Admin only if that scope is approved later; it was deferred in the resident language-toggle work.
- Replace any placeholder information on the barangay officials page after receiving the verified official names, roles, and photos. The conversation noted that official data was not yet available.
- Confirm any barangay-specific policy details before encoding them in workflows, especially referral processes or incident classifications.
- The pending staff-assignment migration was explicitly not to be applied in the related implementation request. A separate resident registration applications migration was reported as applied by the user.
- Recheck the current working tree before release: there are local modified and untracked files, and they should be reviewed individually before committing or deploying.

## Sensitive information

No passwords, resident numbers, private report contents, or personal account details are included in this summary.
