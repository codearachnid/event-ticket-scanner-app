# TEC Ticket Scanner — NativePHP (SuperNative) Mobile App + Companion WP Plugin

## Context

Timothy wants a mobile app for event organizers: point the phone at an attendee's ticket QR code and get an instant **green (valid — checked in) / red (invalid) screen**. Tickets are sold through **The Events Calendar / Event Tickets** (StellarWP) on WordPress. This repo (`tec-ticket-scanner`, fresh Laravel 13 skeleton, branch `13.x`) becomes the **NativePHP for Mobile v4 ("SuperNative") app**. A **companion WordPress plugin lives in a separate repository** and exposes purpose-built REST endpoints.

This document is the master build plan, written to be executed in stages by future agents. Each stage has explicit deliverables and acceptance criteria. **Do the stages in order** — later stages assume earlier ones are complete.

### Decisions already made with the user (do not re-litigate)

| Decision | Choice |
|---|---|
| Mobile stack | NativePHP for Mobile **v4** (`nativephp/mobile` ^4.1), SuperNative **EDGE native UI** (SwiftUI/Compose rendered from Blade) — not webview |
| WP integration | **Companion WP plugin** (separate repo) with custom REST endpoints; works with the **free** Event Tickets plugin |
| Auth | **WordPress Application Passwords** (Basic auth over HTTPS), stored on-device in **SecureStorage** (Keychain/Keystore) |
| Data strategy | **Offline-first**: full attendee list synced to on-device SQLite; scans validate locally; check-ins queue and sync back |
| Scope | Scan → green/red, attendee search + manual check-in, undo check-in, live event stats, multiple sites/events |
| Licensing | User has **NativePHP Ultra** — premium plugins (Scanner, SecureStorage) are available via `plugins.nativephp.com` |
| Sequencing | **Mobile app first against fixtures**; user sets up a WP test site after the first pass; companion plugin + integration follow |

### Verified facts this plan is built on (researched Aug 2026 — do not re-research unless something fails)

**NativePHP Mobile v4** (docs: `nativephp.com/docs/mobile/4/…`):
- v4.1.0 supports Laravel 13; on-device runtime is PHP 8.4 (ZTS) embedded via libphp — no web server. Dev machine needs macOS 15.6+, Xcode 26+ (iOS), Android Studio + JDK 17 (Android).
- UI = **EDGE components**: Livewire-like PHP component classes + Blade templates compiled to native SwiftUI/Jetpack Compose. Generator: `php artisan native:make`. Tests: `native:make-test`, Pest with `Native::fakeBridge()`.
- **Scanner (premium)**: `Native\Mobile\Facades\Scanner` — `Scanner::scan()->prompt(string)->continuous(bool)->formats(['qr'])->id(string)`. Results arrive as a `Native\Mobile\Events\Scanner\CodeScanned` event handled with the `#[OnNative]` attribute (`handleScan($data, $format, $id)`); `scan()` itself returns nothing. Enable `scanner` permission in `config/nativephp.php`. Pest: `assertScanRequested()`.
- **SecureStorage (premium)**: `SecureStorage::set/get/delete` — note `get()` returns `['value' => '']` (empty string, not null) for missing keys.
- **Device (free)**: `Device::vibrate()` (haptic tap), `Device::flashlight()` (torch toggle), `Device::getId()`.
- **Network (free)**: `Network::status()` → `['connected' => bool, 'type' => …]`. Poll-based; no connectivity-change event.
- **SQLite only**; migrations auto-run on every app launch (never write destructive migrations). `QUEUE_CONNECTION=database` works — an on-device worker thread processes jobs while the app runs; jobs persist across restarts. No scheduler.
- Premium plugin install: `composer config repositories.nativephp-plugins composer https://plugins.nativephp.com` + `composer config http-basic.plugins.nativephp.com <email> <ultra-license-key>` + `composer require nativephp/mobile-scanner` + `php artisan native:plugin:register nativephp/mobile-scanner`.
- Commands: `native:install` (once, `--with-icu` optional), `native:run --watch`, `native:sim`, `native:package` for store builds. The `/nativephp` dir is ephemeral — gitignore it.

**Event Tickets (free plugin, v5.29.x)** — QR + check-in REST moved into the FREE plugin at 5.7.0:
- **Ticket QR payload is a URL** (not JSON): home URL + query args `event_qr_code`, `ticket_id` (⚠️ actually the **attendee post ID**), `event_id`, `security_code`, `path`. This is everything needed for offline validation.
- **App-pairing QR** (Tickets → Settings → Integrations) is JSON: `{"url": site_url, "api_key": …, "tec": bool}` — we can reuse the URL from it for site setup, but we auth with Application Passwords, not that 8-char api_key.
- Attendees are WP posts, one per provider: Tickets Commerce `tec_tc_attendee` (meta prefix `_tec_tickets_commerce_*`: `_event`, `_ticket`, `_security_code`, `_checked_in`, `_full_name`, `_email`, `_status`), RSVP `tribe_rsvp_attendees` (`_tribe_rsvp_event`, `_tribe_rsvp_product`, `_tribe_rsvp_security_code`, `_tribe_rsvp_checkedin`, `_tribe_rsvp_full_name`, `_tribe_rsvp_email`), ET+ Woo `tribe_wooticket` (`_tribe_wooticket_*`).
- **Canonical check-in PHP API**: `tribe('tickets.data_api')->get_ticket_provider($attendee_id)` → `$provider->checkin($attendee_id, $qr, $event_id)` / `$provider->uncheckin($attendee_id)`. Fires `event_tickets_checkin` / `event_tickets_uncheckin` actions (RSVP also fires `rsvp_checkin`).
- **ORM**: `tribe_attendees()->by('event', $id)->by('checkedin', true)->per_page(100)->page($n)` — filters include `holder_name__like`, `holder_email__like`, `security_code`, `order_status`, `provider`.
- Free REST (`/wp-json/tribe/tickets/v1/`) gaps our plugin must fill: **no un-check-in endpoint**, check-in is a GET with an 8-char shared api_key in the query string, attendee list caps at `per_page=100` with **no delta/`updated_since` sync**, no stats endpoint. Manage-level REST access requires `edit_users` OR the custom cap `tribe_manage_attendees` (granted to no role by default).
- ⚠️ **Check-in only writes postmeta — it does NOT bump the attendee post's `post_modified`.** Delta sync cannot rely on `post_modified` alone; the companion plugin must hook `event_tickets_checkin`/`event_tickets_uncheckin`/`rsvp_checkin` and maintain its own last-modified touch (see Stage 7).

---

## Architecture overview

```
┌─ iPhone/Android (this repo) ────────────────┐      ┌─ WordPress site (separate repo) ────────┐
│ NativePHP v4 app (Laravel 13, PHP 8.4)      │      │ Companion plugin: tec-scanner-companion │
│                                             │      │ REST ns: tec-scanner/v1                 │
│ EDGE UI ── ScanService ── SQLite            │HTTPS │  GET  /me            (pairing check)    │
│    │           │        (events, attendees, │◄────►│  GET  /events                           │
│ Scanner     CheckinQueue  checkin_queue)    │Basic │  GET  /events/{id}/attendees?updated_since│
│ facade         │                            │auth  │  POST /checkins      (batch, idempotent)│
│             SyncEngine ── ApiClient ────────┼──────┤  GET  /events/{id}/stats                │
│ SecureStorage (app password per site)       │      │ Auth: Application Passwords + custom cap│
└─────────────────────────────────────────────┘      └─────────────────────────────────────────┘
```

**Scan decision (local, instant):** parse QR URL → extract `ticket_id` (attendee ID), `event_id`, `security_code` → look up in SQLite:
- **GREEN**: attendee found for active event, security code matches, order status complete, not yet checked in → mark checked-in locally, enqueue sync, single haptic tap.
- **AMBER (already checked in)**: valid but previously checked in — show who/when. Distinct screen from red; this is the door-staff "duplicate ticket" case.
- **RED**: unknown attendee ID, security code mismatch, wrong event, or incomplete/refunded order → show reason.

**Contract-first:** Stage 1 produces an OpenAPI spec + JSON fixtures in this repo (`docs/api/`). The mobile `ApiClient` is an interface with two implementations: `HttpApiClient` (real) and `FixtureApiClient` (reads fixtures, simulates latency/conflicts). All app development through Stage 6 runs on `FixtureApiClient`. The companion plugin (Stage 7) implements the same spec and copies the fixtures into its contract tests — the spec is the single source of truth both sides test against.

---

## Stage 0 — Toolchain & NativePHP bootstrap (this repo)

Turn the Laravel skeleton into a running NativePHP app on the iOS simulator.

1. Verify host prerequisites: macOS 15.6+, Xcode 26+ with CLT, CocoaPods, PHP 8.3+/Composer locally. (Android Studio setup can be deferred to Stage 9.)
2. `composer require nativephp/mobile` (^4.1).
3. Configure the premium repo with the user's Ultra credentials (ask the user for the license email/key — do **not** commit them; they live in `auth.json`, gitignored):
   `composer config repositories.nativephp-plugins composer https://plugins.nativephp.com` then `composer config http-basic.plugins.nativephp.com <email> <key>`.
4. `composer require nativephp/mobile-scanner nativephp/secure-storage nativephp/mobile-device nativephp/mobile-network nativephp/mobile-dialog` (verify exact secure-storage package name against `nativephp.com/docs/mobile/4/plugins/core/secure-storage` at install time), then `php artisan native:plugin:register` for each premium plugin.
5. `.env`: set `NATIVEPHP_APP_ID` (e.g. `com.codearachnid.tecscanner`), `NATIVEPHP_DEVELOPMENT_TEAM` (ask user for Apple Team ID; simulator works without it), `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=database`.
6. `php artisan native:install`, then `php artisan native:run --watch` → default app boots in iOS simulator.
7. Enable `scanner` permission in `config/nativephp.php`. Add `/nativephp` to `.gitignore`.
8. Set up Pest with NativePHP test helpers; one smoke test asserting the app boots (`Native::fakeBridge()`).

**Acceptance:** `native:run` launches on the simulator; `php artisan test` passes; premium plugins registered (`native:plugin:list`).

## Stage 1 — API contract (in this repo, `docs/api/`)

The source of truth both codebases build against.

1. `docs/api/openapi.yaml` — REST namespace `tec-scanner/v1`, Basic auth (Application Passwords):
   - `GET /me` → `{site_name, user, capabilities: {can_checkin: bool}, plugin_version, event_tickets_version, providers: [...]}` — used to validate a site connection at pairing time.
   - `GET /events?upcoming=1&page=` → ticketable events (from any active provider): `{id, title, start_date, end_date, venue, timezone, attendee_count, checked_in_count}`.
   - `GET /events/{id}/attendees?updated_since=<ISO8601>&page=&per_page=` → `{attendees: [...], total, page, has_more, server_time}`. Attendee shape: `{id, event_id, ticket_id, ticket_name, provider, holder_name, holder_email, security_code, order_status, checked_in, checked_in_at, checked_in_by, updated_at}`. `updated_since` omitted = full sync. `server_time` is echoed back as the next `updated_since` (avoids clock-skew).
   - `POST /checkins` — batch, idempotent: `{device_id, operations: [{op_id: uuid, attendee_id, action: "checkin"|"uncheckin", occurred_at}]}` → per-op results `{op_id, status: "ok"|"already_checked_in"|"not_found"|"conflict"|"error", attendee: {...current server state}}`. `already_checked_in` for a `checkin` op is a **success-with-info** (another device got there first), not an error.
   - `GET /events/{id}/stats` → `{total, checked_in, by_ticket_type: [{ticket_id, name, total, checked_in}]}`.
2. `docs/api/fixtures/*.json` — realistic fixture responses for every endpoint: one event with ~50 attendees across providers (`tec_tc_attendee` + `tribe_rsvp_attendees` shapes), including checked-in, refunded/incomplete-order, and duplicate-scan cases.
3. `docs/api/qr-format.md` — document the ticket QR URL format (`event_qr_code`, `ticket_id`=attendee ID, `event_id`, `security_code`, `path` query args) with 3 sample QR payload strings matching fixture attendees, plus the JSON app-pairing QR format.

**Acceptance:** spec lints (`npx @redocly/cli lint`); fixtures validate against the spec; a reviewer can build either side from `docs/api/` alone.

## Stage 2 — Mobile data layer & services (no UI yet)

1. **Migrations** (SQLite, additive-only — they run on every launch on user devices):
   - `sites`: `id, name, base_url, username, last_verified_at` (app password NOT here — SecureStorage key `site_{id}_password`).
   - `events`: `id, site_id, wp_event_id, title, starts_at, ends_at, venue, timezone, last_synced_at, sync_cursor` (`sync_cursor` = last `server_time`).
   - `attendees`: `id, site_id, wp_attendee_id, wp_event_id, ticket_name, provider, holder_name, holder_email, security_code, order_status, checked_in (bool), checked_in_at, checked_in_source (local|server), updated_at`. Unique index `(site_id, wp_attendee_id)`; index `(site_id, wp_event_id)`; index on `security_code`.
   - `checkin_operations`: `id, op_id (uuid), site_id, wp_attendee_id, action, occurred_at, synced_at (null=pending), result_status, attempts`.
2. **`App\Services\Api\ApiClient`** interface + `HttpApiClient` (Laravel HTTP client, Basic auth from SecureStorage, per-site base URL) + `FixtureApiClient` (serves `docs/api/fixtures/`, configurable latency + conflict injection). Bind via config flag `TICKETSCANNER_API=fixture|http` (default `fixture` until Stage 8).
3. **`App\Services\QrParser`** — parse a scanned string into `{attendee_id, event_id, security_code}`. Accept the full checkin URL form; tolerate URL-encoding variants; reject non-ticket QRs cleanly. Also parse the JSON pairing QR into `{url}`.
4. **`App\Services\ScanValidator`** — pure function: `(parsed_qr, active_event) → GREEN|AMBER|RED + reason + attendee`. Implements the decision table above. This is the most-tested class in the app.
5. **`App\Services\SyncEngine`** —
   - `pullAttendees(Event $e)`: paginated fetch with `updated_since = sync_cursor`, upsert by `(site_id, wp_attendee_id)`; **server state wins except**: a local `checked_in=true` with a pending (unsynced) operation is preserved.
   - `pushCheckins(Site $s)`: batch pending `checkin_operations` (batches of 50), apply per-op results (`already_checked_in` → update attendee with server's `checked_in_by/at`), mark synced, exponential backoff on failure via `attempts`.
   - Queued job `SyncEventJob` (database queue) dispatched on: app foreground, post-scan (debounced ~15s), manual pull-to-refresh. Check `Network::status()['connected']` before HTTP; skip silently offline.
6. Models: `Site`, `Event`, `Attendee`, `CheckinOperation` with the obvious relationships.

**Acceptance:** Pest unit tests green for `QrParser` (valid/malformed/foreign QRs), `ScanValidator` (full decision table: valid, duplicate, wrong event, bad code, refunded), and `SyncEngine` against `FixtureApiClient` (fresh sync, delta sync, conflict where server checked in first, offline no-op). No UI.

## Stage 3 — EDGE UI shell: onboarding, sites, events

All screens are SuperNative EDGE components (`php artisan native:make`). Consult `nativephp.com/docs/mobile/4/` EDGE component reference while building — the component set is new (v4 shipped Aug 2026); expect to adapt to what List/Modal/TextInput actually support.

1. **Connect Site screen** (first-run): two paths — scan the pairing QR from Tickets → Settings → Integrations (reuses Scanner, extracts site URL from JSON) or manual entry. Fields: site URL, username, application password. On submit: store password in SecureStorage, call `GET /me` to verify, save `sites` row. Clear error states (bad URL / bad credentials / `can_checkin: false` / plugin missing → tell user to install companion plugin).
2. **Events screen**: list events for the selected site (synced via `GET /events`), showing date + checked-in/total counts. Site switcher if >1 site. Selecting an event syncs its attendees (progress indicator on first full sync) and lands on the Event Home screen.
3. **Event Home screen**: big "Start Scanning" button, stats summary (local counts), sync status line (last synced, pending ops count), links to Attendees list and Stats.
4. Navigation via EDGE bottom-nav or stack per what v4 supports; keep a `NavigationService` thin so screen wiring is centralized.

**Acceptance:** on the simulator with `FixtureApiClient`: connect a fake site, see the fixture event, open Event Home, see correct counts. Component tests via `native:make-test` for the connect-site validation flow.

## Stage 4 — Scanning & green/red screen (the core loop)

1. **Scan screen**: `Scanner::scan()->continuous(true)->formats(['qr'])->id('door-scan')`; handle results in an EDGE component method with `#[OnNative(CodeScanned::class)]`. Torch toggle button calls `Device::flashlight()` (⚠️ unverified whether torch works while the scanner UI is open — prototype this first; if it doesn't, drop the in-scan torch button).
2. On `CodeScanned`: `QrParser` → `ScanValidator` → result overlay:
   - **GREEN** full-screen: attendee name + ticket type, `Device::vibrate()` once. Auto-dismiss after ~2s back to scanning (continuous mode).
   - **AMBER**: "Already checked in — {time} via {device/user}", double vibrate, requires tap to dismiss (staff must consciously wave through or turn away).
   - **RED**: reason (unknown ticket / wrong event / code mismatch / order not complete), double vibrate, tap to dismiss.
3. Debounce duplicate reads of the same code within ~3s (continuous scanners re-fire).
4. Every GREEN writes: local attendee update + `checkin_operations` row + debounced `SyncEventJob` dispatch. Scanning must work fully offline.
5. Result screens must be legible at arm's length in daylight: full-bleed color, huge type.

**Acceptance:** Pest: `assertScanRequested()`; fake `CodeScanned` events drive all three outcomes; a GREEN scan enqueues exactly one operation; the same QR twice yields GREEN then AMBER. Manual: simulator run-through with fixture QR strings (render the 3 sample payloads from `docs/api/qr-format.md` as QR images to scan from a second screen).

## Stage 5 — Search, manual check-in, undo, stats

1. **Attendees screen**: local-SQLite search (name/email, `LIKE`, debounced) with checked-in state badges; filter All / Checked-in / Not checked-in; works offline.
2. **Attendee detail**: ticket info, order status, check-in history; **Check in** button (same path as a GREEN scan) and **Undo check-in** (writes an `uncheckin` operation, confirm dialog via `mobile-dialog`).
3. **Stats screen**: local counts (total / checked-in / by ticket type) with a "last synced" caveat line; pull-to-refresh triggers sync which also fetches `GET /events/{id}/stats` for server-truth comparison when online.

**Acceptance:** Pest component tests for search filtering and undo (undo enqueues `uncheckin`, flips local state, AMBER on rescan becomes GREEN after undo). Everything works with `Network` faked offline.

## Stage 6 — Mobile first-pass polish (end of "app first pass" — user then sets up WP test site)

1. Empty/error/loading states on every screen; sync-failure surfacing (non-blocking banner: "N check-ins pending sync").
2. Settings screen: manage sites (add/remove — removing deletes SecureStorage entry + cascades local data), device name (sent as `device_id`), re-sync buttons.
3. App icon + splash per NativePHP config.
4. Full simulator regression pass on fixtures; tag `v0.1.0-alpha`.

**Acceptance:** demoable end-to-end on simulator against fixtures. **Pause here: ask the user to stand up the WP test site (WordPress + Event Tickets free, ≥1 event with Tickets Commerce tickets + RSVP, several test attendees, an Application Password for an admin user, HTTPS reachable from the phone/simulator).**

## Stage 7 — Companion WordPress plugin (separate repo: `tec-scanner-companion`)

Ask the user where to create the repo (e.g. `/Users/codearachnid/Sites/wordpress/tec-scanner-companion`) and initialize it fresh. Copy `docs/api/openapi.yaml` + fixtures from this repo as the contract; this repo stays the spec's source of truth.

1. **Bootstrap**: standard plugin structure (`tec-scanner-companion.php`, `src/` PSR-4 via composer, `TEC_Scanner\` namespace). Activation check: Event Tickets ≥ 5.7 active, else admin notice + self-deactivate. Dev env: `@wordpress/env` (wp-env) with `event-tickets` from WordPress.org; PHPUnit + `wp-env run tests` for integration tests; a seeder WP-CLI command (`wp tec-scanner seed`) creating a test event + Tickets Commerce/RSVP attendees for repeatable tests.
2. **Auth & caps**: all routes `permission_callback` → `current_user_can('tec_scanner_checkin')`. On activation grant `tec_scanner_checkin` to `administrator` + `editor` (filter `tec_scanner_checkin_roles`). Application Passwords work automatically via WP core Basic auth — require HTTPS (`is_ssl()` check with filterable override for local dev).
3. **REST endpoints** (`tec-scanner/v1`) exactly per the OpenAPI spec:
   - `/me`: capability + version report.
   - `/events`: query ticketable posts having tickets (use `tribe_events` + any ticketable post types; counts via `tribe_attendees()->by('event', $id)->found()`).
   - `/events/{id}/attendees`: `tribe_attendees()->by('event', $id)` paginated; map per-provider meta to the contract's attendee shape (provider-specific meta keys listed in the facts section); include `security_code`, `checked_in`, `checked_in_at` (from `{checkin_key}_details` meta), `updated_at` from the **touch index** (below). `updated_since` filters against the touch index.
   - `/checkins` (POST, batch): for each op, resolve provider via `tribe('tickets.data_api')->get_ticket_provider($attendee_id)`; `checkin($id, true, $event_id)` / `uncheckin($id)`; detect already-checked-in **before** calling checkin (read `{checkin_key}` meta) and return `already_checked_in` with current state; record `op_id` in a dedupe table so retried batches are idempotent (same `op_id` → return stored result).
   - `/events/{id}/stats`: counts via the ORM (`->by('checkedin', true)->found()` etc.), per ticket type.
4. **Touch index for delta sync** (critical — check-in doesn't bump `post_modified`): custom table `wp_tec_scanner_touch (attendee_id PK, event_id, touched_at)` maintained by hooking `event_tickets_checkin`, `event_tickets_uncheckin`, `rsvp_checkin`, `rsvp_uncheckin`, and attendee post `save_post_{type}`/`wp_insert_post` for the three attendee post types (+ deletions via `before_delete_post`). `updated_since` queries join against it; attendees missing from the index sort as "always include on full sync".
5. **Contract tests**: PHPUnit tests that boot wp-env, seed, hit every endpoint, and validate responses against the copied OpenAPI schemas — plus golden tests comparing endpoint output shape to the fixture files.

**Acceptance:** wp-env test suite green; manual `curl` with an Application Password against a local wp-env site returns spec-conformant responses for all five endpoints; a checkin op via `curl` shows as checked-in in wp-admin's Attendees screen (and vice-versa an admin check-in appears in the next delta pull).

## Stage 8 — Integration: app ↔ real WordPress

Requires the user's test site (Stage 6 pause) with the companion plugin installed.

1. Flip the app to `TICKETSCANNER_API=http`; connect to the test site with a real Application Password.
2. End-to-end pass: pair site → sync events → sync attendees → scan real QR from a ticket email (GREEN) → verify in wp-admin → rescan (AMBER) → undo from app → verify in wp-admin → check-in from wp-admin → delta sync pulls it → stats match.
3. Two-device conflict drill (or app + `curl` simulating a second device): both check in the same attendee offline; on sync, second device gets `already_checked_in` and reconciles to AMBER state locally.
4. Offline drill: airplane mode → 5 scans → back online → queue drains → server state correct; force-quit mid-queue → relaunch → queue resumes (database queue persistence).
5. Fix contract drift discovered here in **both** repos + the spec; keep fixtures in sync with real responses.

**Acceptance:** the full checklist above executed on a physical iPhone against the test site, recorded as a pass/fail table in `docs/integration-test.md`.

## Stage 9 — Hardening & release

1. Large-event performance: seed 5,000 attendees; first sync time acceptable (<60s on wifi), search stays <100ms, scan validation instant. Tune per-page size and SQLite indexes as needed.
2. Security review: app password only in SecureStorage (never logs/SQLite), HTTPS enforced (reject http:// site URLs outside debug), REST responses minimize PII, companion plugin has no unauthenticated surface.
3. Android: install Android Studio toolchain, `native:run` on emulator, fix platform-specific EDGE issues.
4. Release pipeline: `native:release` versioning + `native:package --export-method=app-store` (needs Apple Developer account — ask user) / Play Store equivalent. Companion plugin: tagged zip via GitHub Action for manual WP install.
5. Docs: README in both repos (install, pairing walkthrough with screenshots, troubleshooting), `docs/api/` kept authoritative.

**Acceptance:** TestFlight (or ad-hoc) build scans successfully at a real or simulated door; plugin zip installs cleanly on a fresh WP site.

---

## Verification strategy (applies across stages)

- **Unit (mobile)**: Pest; `ScanValidator` + `QrParser` + `SyncEngine` are the highest-value targets. Use `Native::fakeBridge()` and Scanner/Network fakes — never require a device for CI.
- **Contract**: OpenAPI spec is CI-linted in this repo; both `FixtureApiClient` fixtures and the WP plugin's golden tests validate against the same schemas.
- **Integration (plugin)**: wp-env + seeder command → deterministic, agent-runnable without the user's site.
- **E2E**: Stage 8 checklist on real hardware; repeat before any release.
- Run on simulator: `php artisan native:run --watch` (see `/run` conventions once configured).

## Risks & watch-items for future agents

- **SuperNative v4 is days old** (v4.0.0 released 2026-08-05). Expect EDGE component gaps/bugs; check `nativephp.com/docs/mobile/4/` and the changelog before fighting an issue. Fallback exists (webview element + Livewire) but don't take it without asking the user.
- **Scanner + torch coexistence unverified** — prototype in Stage 4 step 1 before designing around it.
- **ET+ WooCommerce attendees** (`tribe_wooticket`): the ORM handles them transparently, but meta-key details came from lagging docs. If the user's site runs ET+ Woo tickets, verify the attendee mapping against real data in Stage 8.
- **Migrations run on every app launch on end-user devices** — additive migrations only, ever.
- **Clock skew**: always use the server-echoed `server_time` as the next `updated_since` cursor, never device time.
- Ultra credentials: needed for `composer install` (private plugin repo) — keep in `auth.json` (gitignored); CI will need them as secrets.
