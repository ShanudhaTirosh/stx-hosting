# Webmail (PHP + WildDuck API)

Server-rendered PHP 8.3+ webmail for a [WildDuck](https://github.com/nodemailer/wildduck) mail server.
No Composer packages. Pinned against WildDuck **1.55.2** (`tests/fixtures/wildduck-openapi-1.55.2.json`).

## Run
```bash
cp .env.example .env            # set APP_KEY, WILDDUCK_API_URL, WILDDUCK_API_TOKEN
php -S 127.0.0.1:8000 -t public public/index.php   # dev only; use nginx + php-fpm in production
```
Production: point the web root at `public/`, keep `STORAGE_PATH` outside it, serve over HTTPS.

## Tests
```bash
npm install                  # once: dev tooling only (jsdom for the browser-script tests); the app itself has no runtime npm dependencies
php tests/run.php            # everything: PHP unit + contract + end-to-end flows, and the JS suite (tests/js, Node + jsdom)
php tests/run.php flow       # filter by name
npm run test:js              # only the browser-script tests
```
The run also fails if either test server logs a PHP warning/notice. Without `node_modules/jsdom` the JS suite is reported as SKIPPED, never as passed.
`tests/Support/fake_wildduck.php` is test-only. `tests/ContractTest.php` fails if the fake, or any field/parameter
the app uses, stops matching the real WildDuck OpenAPI spec. Passing tests here do **not** replace running the app
against your real WildDuck server (see STATUS below).

## PHP settings for attachments
`upload_max_filesize` and `post_max_size` must be at least `MAX_ATTACHMENT_MB` / `MAX_MESSAGE_MB` (PHP defaults are 2 MB / 8 MB),
and `memory_limit` should be about 4x `MAX_MESSAGE_MB` (attachments are base64-encoded into the API request).
Oversized uploads get a friendly error instead of a failed form.

## Compose notes
- Send = `POST /submit` (WildDuck files it in Sent). Save draft = `isDraft`; editing a draft passes `draft` so WildDuck deletes the old copy,
  but only after the app verifies the target really is a draft in this account's Drafts folder.
- Reply / reply all / forward pass `reference` so threading headers and answered/forwarded flags are set by WildDuck.
- Outgoing HTML uses the same allow-list; remote and internal images are removed (only embedded `data:` images survive).

## Organization
- **Bulk actions** (`POST /mail/bulk`): selections are `mailboxId:uid` pairs, validated against the account's own mailboxes and capped at 100.
  Each mailbox is updated with one `PUT .../messages` call; the success message reports what WildDuck says it changed.
  Permanent delete only touches messages already in Trash.
- **Labels** use WildDuck's native labels (names on messages, IDs in search). Colours come from a fixed palette stored in label `metaData`.
- **Folders**: create (nested with `/`), rename, delete. Built-in folders are protected. Deleting a folder deletes its messages (confirmation page).
- **Conversations**: `/thread/{id}` loads the thread via search across mailboxes and marks it read.
- Sidebar data (folders + label counters) is cached in the session for 10 s and cleared on every state-changing request.

## Deploying (nginx + PHP-FPM)
Files in `deploy/`: `nginx.conf`, `webmail-upstream.conf`, `php-fpm-pool.conf`, `php-webmail.ini`, `smoke.sh`, `verify-local.sh`; `Dockerfile` and `docker-compose.yml` at the top level.
1. Install PHP 8.3 (FPM) with curl, mbstring, xml/dom, fileinfo, sodium (all standard), and nginx.
2. Copy `bin src config views public` to `/srv/webmail` (owner `root:www-data`, group read-only). Create `/var/lib/webmail` owned by `www-data` (sessions + rate-limit files, kept outside the web root).
3. `cp .env.example /srv/webmail/.env`, fill it in (`php bin/generate-key.php` makes the `APP_KEY`), then `chmod 640` and `chown root:www-data`.
4. Install `php-fpm-pool.conf` as a pool, `php-webmail.ini` into `conf.d/`, `webmail-upstream.conf` as `/etc/nginx/webmail-upstream.conf`, and `nginx.conf` as the site (edit host name + certificate paths). **The nginx user must be able to read `/srv/webmail/public` and connect to the PHP socket** (Debian/Ubuntu's default `www-data` does).
5. `php bin/check-config.php --php-ini=/etc/php/8.3/fpm/conf.d/99-webmail.ini` must show no `[FAIL]` (it also confirms WildDuck is reachable and accepts your token, without printing any secret).
6. After reloading: `deploy/smoke.sh https://mail.example.org --http-url http://mail.example.org`.

What the nginx config guarantees: only `/assets/` is served as files; `*.php` and dot-files are never served or executed; everything else goes through `public/index.php`;
uploads above 30 MB get a friendly 413; TLS 1.2/1.3 only; version numbers hidden.
Behind a load balancer set `TRUSTED_PROXIES`, otherwise every client appears to have the balancer's IP and the login rate limit would apply to everyone at once.
Housekeeping: expired session and rate-limit files are removed automatically (about 1% of requests trigger a sweep). Errors go to the PHP-FPM log (`/var/log/php8.3-fpm-webmail.log`); rotate it with logrotate.

**What was verified:** `deploy/verify-local.sh` runs these exact files on a real nginx 1.24 + PHP-FPM 8.3 (Ubuntu 24.04) as `www-data` with a self-signed certificate (only ports and paths substituted)
and passes the smoke test plus end-to-end sign-in, 1 MB / 26 MB / 31 MB upload checks and the config checker. **Not verified here:** the Docker files (no Docker available), systemd units, certbot/Let's Encrypt, and any web server other than nginx.

## Several accounts in one session
Up to **5** WildDuck accounts can be signed in at once (`/accounts/add`); the sidebar's account menu switches between them, signs out of one, or signs out of all.
- Every account goes through the full sign-in (password, and the 2FA code if it has one). A newly added account becomes the active one; adding an account that is already signed in just refreshes it.
- Each account keeps its own AEAD-encrypted WildDuck token in the server-side session, under a random per-session id (never the WildDuck id). Switching only accepts ids that belong to *this* session, rotates the session id,
  and everything per-account (folders, labels, profile, signatures, contacts, addresses) is cached per user and cleared on every change.
- If one account's token expires or is revoked, only that account is dropped and you land in another one; the others stay signed in. Signing out revokes the WildDuck token(s).
- The new-mail poll also reports unread counts for the other accounts (one WildDuck call each, short timeout, an unreachable account is simply left out) and shows them as badges in the menu.
- The CSRF token is renewed at every sign-in.

## New-mail indicator
`public/assets/notify.js` polls `GET /api/notify` (about every 45 s, every 2 min in a background tab, exponential backoff on errors, stops for good on 401).
Each poll is **one** WildDuck call (`GET /users/{id}/mailboxes`); a second one (the newest unread messages) only when the inbox grew in both total and unread.
It updates the tab title `(3) Inbox`, the folder badges, shows an in-page notice (and a refresh banner on the inbox) and, if the user switches on the bell, a desktop notification
while the tab is hidden. The endpoint releases the session lock before calling WildDuck, so a slow upstream can never stall the user's other requests.
Why polling and not WildDuck's SSE stream: each open stream would hold a PHP-FPM worker for as long as the tab lives, which exhausts a normal pool after a few dozen users.

## Contacts, signatures and suggestions
WildDuck has no signature or manual-contact API (its address register is read-only and fills itself from mail), so both are stored in the
account's own **`metaData.webmail`** via `PUT /users/{id}`; no extra database. Rules:
- Only the `webmail` key is touched; every other key in `metaData` (other apps, admin tooling) is preserved on write.
- Writes are read-modify-write against a fresh copy (last writer wins), capped at 2000 contacts, 10 signatures and ~400 KB in total.
- Stored data is re-validated and signature HTML re-sanitised on every read, so edited or corrupted values cannot break or inject into a page.
- Typing suggestions (`GET /api/suggest`) merge your contacts with the WildDuck address register; unauthenticated calls get a 401 JSON, not a redirect.
- **Verify on your server:** that a user-scoped token may `PUT /users/{id}` with `metaData`. If it cannot, the pages show
  "nothing was saved" and everything else keeps working.

## Settings
- **Profile**: display name (`PUT /users/{id} {name}`) and password change (`existingPassword` + `password`, verified by WildDuck).
  Password attempts are rate limited per account (5 wrong guesses / 15 min); passwords are never echoed back or logged.
- **Sender addresses**: lists `GET /users/{id}/addresses`; you can set each address's display name. The compose "From" menu appears when there
  is more than one address; replies/forwards preselect the alias the message was sent to. The posted From is **always re-checked against the
  account's own address list** (anything else falls back to the main address). Adding addresses is an administrator task.
- **Auto-reply**: `PUT /users/{id}/autoreply` (text + safely escaped HTML). Dates are whole days in **UTC**; empty dates send `false` (no check).
  A topbar badge shows when it is on.
- Profile data (name, metaData, auto-reply flag) is read in **one** call and cached for 30 s; if the profile cannot be read, pages keep working
  and the server is not retried on every page for 20 s.
- **Verify on your server**: that a user-scoped token may `PUT` name/password/addresses/autoreply, and which error WildDuck returns for a wrong
  `existingPassword` (the app recognises 400/403 mentioning "password"; anything else shows the server's own message).

## Rules and blocked senders
Built on WildDuck filters (`/users/{id}/filters`). Conditions: from, to/cc, subject, list-id, text, has-attachment, size.
Actions offered: mark read, star, move to a folder or Spam, add labels. Rules apply to **new** incoming mail only.
- **Deliberately not offered:** forwarding (`targets`) and permanent `delete`. Silent forwarding is the classic way to keep reading someone's mail
  after a compromise, and permanent deletion is unrecoverable. Rules that already use them (made by an administrator or another client) are shown
  as **Advanced**: you can turn them off or delete them, but the app never rewrites them, so editing can't silently drop the forwarding/delete part.
- **Blocked senders** are filters named `Blocked sender: <pattern>` with a single `from` condition and a single `spam` action. A look-alike with any
  extra condition/action is treated as an ordinary rule. Matching is a case-insensitive *partial* match on the From header (WildDuck semantics);
  a bare domain is stored as `@domain`. You cannot block your own addresses or domain.
- The message view's **Block sender** creates the rule and moves that message to Spam in one click.
- Folder, label and rule IDs from forms are checked against the account's own lists before use; the account's rule quota is respected.
- **Verify on your server:** that a user-scoped token may create/update/delete filters, and how `spam: true` behaves with your WildDuck version.

## Two-factor sign-in
Authenticator-app (TOTP) codes are supported. Flow: `POST /authenticate` returns the pending methods and a short-lived nonce (and **no token**);
the user types the 6-digit code; `POST /users/{id}/2fa/totp/check` (`token`, `totpNonce`) returns the real user token.
- "Password OK, code needed" lives **only in the server-side session** (nonce AEAD-encrypted, 5-minute lifetime); nothing about it is in HTML or cookies.
- A token that a compatibility-mode WildDuck returns *before* the code is verified is dropped at the service layer and never used.
- Guessing is limited three ways: 5 wrong codes end that sign-in; 5 wrong codes per account per 5 minutes across all sessions lock the code step
  (even for the right code); and an IP limit. Malformed codes are rejected without contacting WildDuck.
- Accounts whose only second factor is a security key / passkey get a clear message (WebAuthn needs browser-side ceremonies and is not built).
  Accounts that have both a key and an authenticator app can use the app. "custom" 2FA is enforced by WildDuck itself.
- Setting up or removing 2FA is not offered here.

## Session handling notes
- The session is written to disk **before** the response is returned (not at PHP shutdown), so a browser that follows the login redirect immediately can never race the write and look signed out.
- Account ids are never all digits: PHP silently turns numeric-string array keys into integers (this once dropped ~1 in 285 new accounts from the session).

## Security model
- Login: `POST /authenticate` with the service token; each session then uses its own **user-scoped** WildDuck token
  (`scope=master`, `token=true`). Token is AEAD-encrypted inside the server-side session; the browser only has an
  opaque HttpOnly/SameSite cookie. Logout revokes the WildDuck token.
- CSRF token on every state-changing request; strict CSP (no inline script, no eval); session id rotated on login;
  login rate-limited per IP and per account.
- Email HTML is sanitised (allow-list) **and** served from a sandboxed, CSP-locked frame; remote images are blocked
  until the user allows them; attachments always download (never rendered), only raster images may show inline.
- All identifiers are validated before being used in an API path; only mailboxes owned by the signed-in account resolve.

## Layout
`src/Core` (router, session, csrf, crypto, view) · `src/WildDuck` (client, services, models) ·
`src/Http/Controllers` · `views/` · `public/assets` (design tokens from DESIGN.md) · `tests/`
