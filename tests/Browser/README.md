# Browser regression suite

End-to-end checks of the critical resident and staff flows in a real (headless) browser. It complements the Pest
suite: Pest tests the application through the HTTP kernel, this suite tests what people actually see and do, including
JavaScript, uploads, email links and printing.

## Run it

```bash
node --experimental-websocket tests/Browser/run.mjs
node --experimental-websocket tests/Browser/run.mjs --only=certificates,two-factor   # some flows
node --experimental-websocket tests/Browser/run.mjs --keep                          # keep the run folder
```

It prints one `PASS`/`FAIL` line per check and exits with status 1 if any check failed. A full run takes about three
minutes, most of it generating the test photos.

Requirements: Node 20 or later, PHP 8.4 or later with the `pdo_sqlite` and `gd` extensions, Composer dependencies
installed, and Microsoft Edge or Google Chrome. The suite finds them itself; if it cannot, set `PHP_BINARY` (for example
Herd's `php.exe`) or `BROWSER_PATH`. No npm packages are needed.

## What it never touches

Each run creates a temporary folder (in the system temp directory) holding:

- a fresh SQLite database, migrated and seeded with invented people and `@test.invalid` addresses
  (`support/seed.php`, which refuses to run against any other database or storage folder);
- the application's storage (uploaded photos, QR codes, logs, sessions), via `LARAVEL_STORAGE_PATH`;
- generated test photos of 1.5, 5, 9.8 and 11 MB (`support/images.php`).

Mail goes to an in-memory SMTP capture on 127.0.0.1 that relays nothing. The PHP server runs with the upload limits
the deployment guide requires (12M per file, 16M per form, 256M memory), whatever the machine's `php.ini` says. The
project's own database, `.env` settings for the database and mail, `storage/` folder and logs are not used. No Smart
Cabinet or RFID hardware is contacted. The folder is deleted at the end unless `--keep` is given; with `--keep`,
`artifacts/results.json` and the printed certificate PDF are in it.

## Flows

| Flow | Covers |
| --- | --- |
| `resident-registration` | staff-issued activation code, account creation, confirmation email, altered and genuine links |
| `password-reset` | resident Forgot Password in Filipino, neutral answer for unknown addresses, staff Forgot Password, used links |
| `two-factor` | privileged staff sent to set up two-factor, workspace data refused, sign-in with an authenticator code |
| `resident-application` | new-resident application with real photos, 10 MB limit in browser and server, private optimised storage, "file too large" page |
| `document-request` | request submission, confirmation screen, My requests, status tracking, ownership |
| `certificates` | preview, Print & Release, issuance, bottom-left QR, one-page PDF, QR verification |
| `resident-records` | editing a resident record and the verification entry in the Audit Log |
| `vawc-restrictions` | VAWC cases hidden from staff without VAWC access, by list and by address |
| `sidebar-navigation` | neutral background and kept menu position across pages, Back to the right screen |
| `request-rate-limits` | per-resident request limits on a shared network address |

## Adding a flow

Add `flows/<name>.mjs` exporting `name` and `async run({ env, mail, open, check, artifacts })`, and list it in
`FLOWS` in `run.mjs`. `open()` gives a fresh browser profile (`lib/browser.mjs`); `lib/session.mjs` signs people in;
`env.query()` reads the suite database (SELECT only); `mail.to()` and `mail.links()` read captured email. Keep each
check a plain sentence saying what should be true. Do not add screenshots or generated files to Git.
