# IMPLEMENTATION_CHECKPOINT

Checkpoints of the 10-phase messenger mission (Soroush Plus + Rubika integration).
One entry per completed phase, with **real, reproducible evidence** (test numbers,
file lists). Status labels are precise: `SUPPORTED`, `PARTIAL`, `UNSUPPORTED`,
`NOT_IMPLEMENTED`, `MOCK_TESTED`, `LIVE_TESTED`, `BLOCKED`.

---

## ✅ Phase 0 — Initial Audit (COMPLETE)

- **Deliverable:** `docs/progress/01-initial-audit.md`
- Inventory of all existing messenger integrations (Telegram origin, Bale, Eitaa),
  the Channel Poster / Content Sync stack, the bot wizard state machine, and the
  admin panel. Identified soroush as a partially-wired placeholder (URL parser +
  label only, publish skipped) and rubika as absent.
- Baseline recorded before any change: git branch `15esfand1404`, HEAD `c6e00b2c`,
  suite **399 passed / 2 skipped / 2205 assertions, 0 failed**.
- Status: audit `SUPPORTED` (complete).

## ✅ Phase 1 — Capability Matrix (COMPLETE)

- **Deliverable:** `docs/features/messenger-platform-capability-matrix.md`
- Per-capability matrix for telegram / bale / eitaa / soroush / rubika:
  text publish, media upload, test message, content sync, wizard, polls,
  post URL, webhook, admin panel.
- Every cell carries a precise status label; gaps (rubika absent, soroush
  publish-skipped, soroush poll missing) flagged as mission scope.
- Status: `SUPPORTED`.

## ✅ Phase 2 — API Research (COMPLETE)

- **Deliverable:** `docs/features/platform-api-research.md`
- Official docs captured **with dates** for both platforms; research scripts kept
  in `scripts/research/` (`soroush_*.php`, `rubika_links.php`, etc.).
- Key verified facts:
  - **Soroush Plus:** base `https://api.splus.ir/bot<TOKEN>/<METHOD>` (HTTPS),
    Telegram-format token, envelope `{ ok, result, description, error_code }`,
    multipart/JSON/form all accepted, `sendPoll` NOT in method list,
    webhook ports 443/80/88/8443.
  - **Rubika v3:** base `https://botapi.rubika.ir/v3/<TOKEN>/<METHOD>`,
    **POST JSON only**, opaque token, 3-step file upload
    (`requestSendFile` → multipart upload → `sendFile`), FileTypeEnum
    (File 50 MB, Image 10 MB jpg/gif/png/webp, Voice mp3, Video mp4 50 MB,
    Music mp3).
- Known discrepancies documented: rubika response envelope **undocumented**
  (BLOCKED); `updateBotEndpoints` (methods page) vs `updateBotEndpoint`
  (intro page).
- Status: `SUPPORTED` for documented surface; envelope/post-URL items
  `BLOCKED (unverified)` — no guessing.

## ✅ Phase 3 — Soroush Plus + Rubika as Real Providers (COMPLETE)

### Scope delivered
Both platforms are now first-class destinations in the Channel Poster /
Content Sync stack: bot wizard (token → chat id → connect → test message),
text publish, real-file media publish, admin panel, sync reports, retries.
Tokens are stored **per-destination in the DB** (`channel_poster_destinations.bot_token`),
never in `.env`.

### Files created
- `app/Services/SoroushBotClient.php` — raw-HTTP client (`call`, `isOk`,
  `getMe`, `sendMessage`, `sendMedia`, `setWebhook`, `deleteWebhook`,
  `getWebhookInfo`)
- `app/Services/RubikaBotClient.php` — raw-HTTP client (`call`, `fileTypeFor`,
  `hasMessageId`, `extractMessageId`, `getMe`, `sendMessage`,
  `requestUploadUrl`, `uploadFile`, `sendFile`, `getUpdates`, `updateEndpoint`)
- `tests/Unit/SoroushBotClientTest.php` — 9 tests (Http::fake)
- `tests/Unit/RubikaBotClientTest.php` — 13 tests (Http::fake)
- `docs/features/soroush/README.md`, `docs/features/rubika/README.md`
- `.env.example` — reserved Phase 4 vars documented (`SOROUSH_BOT_TOKEN=`,
  `RUBIKA_BOT_TOKEN=`), **no real secrets**
- `scripts/research/add_rubika_lang_keys.php` — idempotent lang rollout script
  (kept for audit)

### Files modified
- `app/Models/ChannelPosterDestination.php` — `PLATFORM_RUBIKA` constant
- `app/Helpers/TokenHelper.php` — `isToken($t, 'rubika')` (opaque,
  `[a-zA-Z0-9_-]{8,200}`); `redactTokens()` rubika `/v3/<token>/` pattern
  (runs **before** the generic `bot|/api/` pattern)
- `app/Services/ChannelPosterMediaSender.php` — soroush branch in
  `doUpload()`; new `doUploadRubika()` (3-step + size guards Image 10 MB /
  Video 50 MB / File 50 MB); `parseResponse()` rubika branch
  (success iff `message_id` present)
- `app/Services/TelegramChannelPosterPublisher.php` — soroush + rubika
  branches in `publish()`, `sendTestMessage()`, `tryFileId()`
- `app/Services/ContentSync/ContentSyncService.php` — soroush skip blocks
  removed; rubika in `platformLabels()`
- `app/Http/Controllers/ChannelPosterBotController.php` — wizard states
  `cp_awaiting_soroush_token` / `cp_awaiting_soroush_chat_id` /
  `cp_awaiting_rubika_token` / `cp_awaiting_rubika_chat_id`,
  `handleSoroushToken()`, `handleRubikaToken()`, keyboard row, connected msgs
- `app/Http/Controllers/AdminBots/AdminChannelPosterController.php` —
  validation `in:bale,telegram,eitaa,soroush,rubika`
- `lang/*/bot.php` (15 locales) — 8 new keys each (btn/sig rubika,
  ask soroush token/chat_id, ask rubika token/chat_id, connected soroush/rubika)
- `tests/Feature/ChannelPosterMediaPublishTest.php` — 7 new tests
- `tests/Feature/ContentSyncFlowTest.php` — stale
  `test_soroush_destination_is_skipped_with_clear_reason` **rewritten** as
  `test_soroush_destination_is_published_like_other_platforms`

### Full test suite — REAL results (post-Phase 3)

```
Tests:    2 deprecated, 2 skipped, 433 passed (2296 assertions)
Duration: 66.04s
```

**0 failures.** Baseline was 399 passed / 2205 assertions →
**+34 tests, +91 assertions**. (PHP 8.5 deprecation noise can make the
process exit non-zero even with all tests passing; the `Tests:` line is the
source of truth, per baseline convention.)

Subset evidence (4 files): **105 passed, 817 assertions, 0 failed, 18.58s**.

### Bug found & fixed during Phase 3 verification
The rubika redaction regex in `TokenHelper::redactTokens()` contained an
unescaped `#` inside its character class — and `#` is also the pattern
delimiter, so the pattern was **invalid PCRE** and every `redactTokens()`
call threw. The 4-file test subset didn't hit redaction, so it only surfaced
on the full-suite run (15 failures in RssFetchPipelineTest / RssRecoveryTest).
Fixed by escaping (`\\#`) and verified with `scripts/research/check_redact_regex.php`
(tokens redacted in all cases, no preg errors).

### Precise status labels (Phase 3 surface)

| Item | Status |
|---|---|
| Soroush text/media publish, sync, wizard, test message | **MOCK_TESTED** (Http::fake; evidence in `docs/features/soroush/README.md`) |
| Rubika text/media (3-step) publish, sync, wizard, test message | **MOCK_TESTED** (Http::fake; evidence in `docs/features/rubika/README.md`) |
| Admin panel (both platforms) | **SUPPORTED** |
| Soroush `sendPoll` | **BLOCKED (unverified)** — not in official method list |
| Soroush public post URL | **BLOCKED (unverified)** — `PostUrlBuilder` returns `unverified` |
| Rubika response envelope shape | **BLOCKED (unverified)** — undocumented; success = `message_id` presence |
| Rubika public post URL | **BLOCKED (unverified)** |
| `updateBotEndpoints` naming (plural vs singular) | **BLOCKED (unverified)** — client uses plural per methods page |
| Soroush/Rubika webhook wiring | **NOT_IMPLEMENTED** (client methods exist → Phase 4) |
| Live testing of either platform | **NOT_LIVE_TESTED** — no real token available |

**No capability is marked `LIVE_TESTED`.**

---

## ✅ Phase 4 — Shared "mother" bots: registration + webhooks (COMPLETE)

### Scope delivered
Soroush Plus and Rubika are now full citizens of the **Bot Mother** platform
(the vendor-SDK-free path): the mother bot receives its own platform webhooks
at `POST /api/webhook-bot-mother` with `origin=soroush` / `origin=rubika`,
walks the admin through the text-only wizard (start menu → endpoint →
language → child token), and registers child bots via
`BotRegistrationService::registerNativeClientBot()` — `getMe` → upsert row in
`bots` (per-platform columns) → build webhook URL →
`SoroushBotClient::setWebhook()` (+ `getWebhookInfo` read-back, non-fatal) or
`RubikaBotClient::updateEndpoint($url, 'ReceiveUpdate')`. The reserved env
vars `SOROUSH_BOT_TOKEN` / `RUBIKA_BOT_TOKEN` configure the two mother bots;
if missing, the endpoint returns `{"ok":false,"error":"mother bot token not
configured"}` with **zero API calls**.

### Files created
- `database/migrations/2026_10_20_000001_add_soroush_rubika_columns_to_bots_table.php`
  — 12 additive columns on `bots`
  (`soroush_{owner_chat_id,bot_name,bot_token,get_me_api_response,bot_status,webhook_is_set}`
  + same 6 for `rubika_*`), each `hasColumn`-guarded;
  MySQL-only (driver-guarded, `hasTable`/`hasColumn`-guarded) enum extensions:
  `bots.type` → `ENUM('telegram','bale','soroush','rubika') NULL` and
  `bot_logs.type` → `ENUM('bale','telegram','gap','soroosh','soroush','rubika') NULL`
  (so `LogHelper::log()` can record the new platforms). `down()` drops all 12
  columns. **Legacy `soroosh` value is intentionally kept in the enum — the
  rename to `soroush` is Phase 6 scope.**
- `tests/Feature/Modules/BotMother/BotMotherSoroushRubikaWebhookTest.php` —
  11 feature tests (Http::fake): soroush `/start` menu + state + log row,
  soroush full flow (bot row columns, `setWebhook` URL with
  `origin=soroush`/token/`bot_mother_id`/`language=fa`, `getWebhookInfo`
  read-back, state cleared), soroush child `getMe` failure → no bot + `❌`,
  soroush invalid token rejected without API call, rubika `/start`, rubika
  full flow (`updateBotEndpoints` with `type=ReceiveUpdate`), rubika `getMe`
  without `bot_id` → failure, rubika HTTP 401 → failure, rubika non-`NewMessage`
  update ignored (`assertNothingSent`), non-admin chat rejected (`⛔`, no state),
  missing mother token → error JSON with no API call.

### Files modified
- `app/Http/Controllers/BotMotherController.php` — DI of
  `BotRegistrationServiceInterface`; early branch in `botMotherWebhook()` for
  `origin` in `{soroush, rubika}`; 10 new private methods
  (`botMotherWebhookNative`, `nativeMotherClient`, `extractNativeChatId`,
  `handleNativeMotherFlow`, `nativeMotherShowStartMenu`,
  `nativeMotherHelpMessage`, `platformLabel`,
  `handleNativeMotherEndpointSelection`, `handleNativeMotherLanguageSelection`,
  `handleNativeMotherTokenInput`). State keys are origin-scoped
  (`soroush:<chatId>` / `rubika:<chatId>`); admin gate via
  `AdminHelper::isAdmin()`; error logging through
  `TokenHelper::redactTokens()`.
- `app/Modules/BotRegistration/Services/BotRegistrationService.php` —
  `registerNativeClientBot()`: soroush `getMe` → `isOk` → upsert
  (`bot_name`/`bot_token`/`get_me_api_response`/`bot_status`/
  `soroush_owner_chat_id`, `webhook_is_set` after setWebhook) →
  `WebhookEndpointHelper::createWebhookUrl()` → `setWebhook()` → `isOk` →
  `getWebhookInfo()` (non-fatal verify) → flag; rubika `getMe` →
  `bot_id` present → upsert → `updateEndpoint($url, 'ReceiveUpdate')` →
  flag; webhook verification is BLOCKED (no rubika read-back API).
- `app/Services/SoroushBotClient.php` — SDK-compat surface
  (`setData`/`getData`/`Text`/`Caption`/`ChatID`, Telegram-style shapes) +
  `sendMessage(array)` strips `parse_mode` (BLOCKED — not documented by
  Soroush Plus).
- `app/Services/RubikaBotClient.php` — SDK-compat surface
  (`Text()`/`Caption()`/`ChatID()` from `update.new_message` shape) +
  `sendMessage(array)` strips `parse_mode` (BLOCKED — undocumented).
- `app/Helpers/WebhookEndpointHelper.php` — `createWebhookUrl()` token +
  `bot_user_name`/`bot_token` match branches for `soroush`/`rubika`
  (URL built via `http_build_query`, so tokens are urlencoded).
- `app/Http/Requests/BotRequest.php` — `origin` rule extended to
  `{bale, telegram, soroosh, gap, soroush, rubika}`.

### Full test suite — REAL results (post-Phase 4)

```
Tests:    2 deprecated, 2 skipped, 444 passed (2363 assertions)
Duration: 67.40s
```

**0 failures.** Baseline (post-Phase 3) was 433 passed / 2296 assertions →
**+11 tests, +67 assertions**. New file subset: **11 tests, 67 assertions,
16.95s** (reported as `9 passed, 2 deprecated` — the two deprecation
markers are the same vendor PHP 8.5 noise as the baseline; the `Tests:`
line is the source of truth).

### Bug found & fixed during Phase 4 verification
First test run: 7/11 failed with `Array to string conversion`. Cause:
`Illuminate\Http\Client\Request::data()` takes **no arguments** —
`$r->data('key')` returned the whole params array and the `(string)` cast
blew up under Laravel's error handler. All `assertSent` callbacks now use
array access (`$r['text'] ?? ''`, `$r['url'] ?? ''`,
`($r['type'] ?? null) === 'ReceiveUpdate'`). Verified against
`vendor/.../Http/Client/Request.php` (`offsetGet` → `data()[$offset]`).

### Precise status labels (Phase 4 surface)

| Item | Status |
|---|---|
| Soroush mother webhook wizard (start → endpoint → language → token) | **MOCK_TESTED** |
| Soroush child registration + `setWebhook` + `getWebhookInfo` read-back | **MOCK_TESTED** |
| Rubika mother webhook wizard | **MOCK_TESTED** |
| Rubika child registration + `updateBotEndpoints(url, ReceiveUpdate)` | **MOCK_TESTED** |
| Admin gate + origin-scoped state + redacted error logging | **MOCK_TESTED** |
| Rubika webhook read-back / verification | **BLOCKED** — no such API exists |
| Rubika response envelope shape | **BLOCKED (unverified)** — undocumented |
| Soroush `parse_mode` support | **BLOCKED (unverified)** — stripped in both clients |
| Soroush/Rubika live webhook testing | **NOT_LIVE_TESTED** — deferred to Phase 7 (needs real mother tokens + public URL) |
| `bots.type` / `bot_logs.type` enum extension | **SUPPORTED** (additive, guarded migration) |
| Legacy `soroosh` → `soroush` value rename | **Phase 6 scope** (kept in enum for now) |

**No capability is marked `LIVE_TESTED`.**

---

## ✅ Phase 5 — Sync engine generalization (COMPLETE)

### Scope delivered
The Channel Poster / Content Sync engine no longer hardcodes platform
behaviour in `if/switch` branches. A config registry
(`config/sync_platforms.php`) now declares per platform: token/owner DB
column, which publisher to use (`vendor` | `native`), and whether media may
be downloaded from the origin platform's API. Soroush Plus and Rubika are
now **first-class source origins** of the channel-poster webhook: the bot
resolves itself from the per-platform token column, detects the owner via
the per-platform owner column, replies through the internal native clients
(no vendor SDK), and normalizes rubika's distinct `NewMessage` webhook
shape. Media download from origin is a data-driven gate: bale/telegram
allowed, soroush/rubika explicitly skipped (unverified `getFile`/inbound
media → `BLOCKED`) — the flow continues with the origin `file_id`, and
cross-platform media publishes report a clear failure, never a fake success.

### Files created
- `config/sync_platforms.php` — 4-platform registry (bale/telegram:
  `vendor` publisher + media download allowed; soroush/rubika: `native`
  publisher + media download **disabled**)
- `app/Services/SyncPlatformRegistry.php` — config accessors
  (`tokenColumn` / `ownerColumn` / `publisher` /
  `canDownloadMediaFromOrigin` / `label`) with telegram-default fallback
- `app/Services/NativeChannelPosterPublisher.php` — implements
  `ChannelPosterPublisher`: `sendPrivateMessage` via
  `SoroushBotClient`/`RubikaBotClient` (inline keyboards dropped + logged —
  undocumented), `publish`/`sendTestMessage` **lazy-delegate** to
  `TelegramChannelPosterPublisher` (verified Phase 3 destination branches),
  `answerCallback` no-op + log (undocumented)
- `database/migrations/2026_10_20_000002_extend_origin_enum_to_bot_users_table.php`
  — `bot_users.origin` → `ENUM('bale','telegram','gap','soroosh','soroush','rubika') NULL`;
  MySQL-only (driver + `hasTable` + `SHOW COLUMNS` guarded), `down()` restores
  the 4-value enum; **legacy `soroosh` kept for Phase 6**
- `tests/Feature/ChannelPosterOriginWebhookTest.php` — 7 feature tests
  (real factory, Http::fake, in-memory sqlite)

### Files modified
- `app/Services/TelegramChannelPosterPublisherFactory.php` — `make()` now
  consults the registry: `native` → `NativeChannelPosterPublisher`,
  otherwise the legacy vendor publisher (unknown origins fall back to
  vendor behaviour)
- `app/Http/Controllers/ChannelPosterBotController.php` — 6 registry sites:
  `resolveBotAndToken()` (token column), webhook + `handlePrivateMessage()`
  owner logs, `offerOrPublishContent()` media-download gate,
  `handleSyncRetryCommand()` (publisher token); new
  `normalizeRubikaUpdate()` (only `NewMessage` + `sender_type=User`;
  maps chat/sender; copies only documented `text`; everything else →
  ignored with `200 {"ok":true}`) hooked into `extractUpdate()`
- `app/Services/ChannelPosterBotServiceImpl.php` — `isOwner()` /
  `claimOwnerIfEmpty()` registry-driven owner column
- `tests/UsesChannelPosterSqlite.php` — 12 additive sqlite `bots` columns
  (soroush/rubika × `{owner_chat_id, bot_name, bot_token,
  get_me_api_response, bot_status, webhook_is_set}`)

### Full test suite — REAL results (post-Phase 5)

```
Tests:    2 deprecated, 2 skipped, 451 passed (2402 assertions)
Duration: 69.44s
```

**0 failures.** Baseline (post-Phase 4) was 444 passed / 2363 assertions →
**+7 tests, +39 assertions, 0 regressions.** New-file subset:
`Tests: 2 deprecated, 5 passed (39 assertions), 3.16s` (the 2 `!` markers
are the same vendor PHP 8.5 deprecation noise as the baseline).

Test coverage (all MOCK_TESTED): soroush `/start` (bot resolved from
`soroush_bot_token`, reply to `api.splus.ir`, `origin='soroush'`, state
`cp_awaiting_bale_forward`); rubika `NewMessage/User` normalization (reply
to `botapi.rubika.ir/v3`, `origin='rubika'`); rubika bot-own message →
ignored (zero HTTP); rubika non-`NewMessage` → ignored (zero HTTP);
soroush-origin photo → `fetchAndStore` **never called** (data-driven skip)
yet flow continues with the origin `file_id` + `media_path=null`;
rubika-origin media → same skip, and because the inbound media *structure*
is unverified the engine does not guess — flow continues as `text`;
factory returns `TelegramChannelPosterPublisher` for bale/telegram/unknown
and `NativeChannelPosterPublisher` for soroush/rubika.

### Bug found & fixed during Phase 5 verification
First run of the new file: 1/7 failed
(`test_rubika_origin_media_does_not_download_from_origin` — expected the
rubika `photo` array's `file_id` in pending state, got `null`). Root
cause: the test payload used a **Telegram-style `photo` field** inside
rubika's `new_message`, but the documented rubika `Message` model has no
`photo` field — only an **undocumented-structure** `file` field
(`platform-api-research.md`). `normalizeRubikaUpdate()` correctly copies
only the documented `text`, so the content was (correctly) extracted as
`text` with `file_id=null`. The engine behaviour was right; the test
payload made an undocumented assumption. Fixed by reworking the test to
assert the honest documented behaviour: the `file` field is passed through
raw, the registry skip still holds (`fetchAndStore` never called), and the
pending content is stored as `content_type=text`, `file_id=null`,
`media_path=null`. Also fixed a latent test bug: `BotUserState` was queried
by `bot->id` (a coincidental id collision) instead of the `BotUsers` row id.

### Precise status labels (Phase 5 surface)

| Item | Status |
|---|---|
| Soroush as **source** (webhook ingestion, token resolution, owner detection, replies) | **MOCK_TESTED** |
| Rubika as **source** (incl. `NewMessage/User` normalization) | **MOCK_TESTED** |
| Rubika bot-own / non-`NewMessage` updates ignored (zero HTTP) | **MOCK_TESTED** |
| Data-driven media-download gate (soroush/rubika skip, bale/telegram allow) | **MOCK_TESTED** |
| Config-driven factory (vendor vs native publisher) | **MOCK_TESTED** |
| Media download from soroush origin (`getFile`) | **BLOCKED (unverified)** — registry keeps it off |
| Rubika inbound media fields (`Message.file` structure) | **BLOCKED (unverified)** — engine does not guess |
| Inline keyboards on soroush/rubika replies | **BLOCKED (unverified)** — dropped + logged |
| Callback queries from soroush/rubika source | **BLOCKED (unverified)** — no-op + logged |
| `bot_users.origin` enum extension | **SUPPORTED** (additive, guarded migration) |
| Soroush/rubika source live webhook testing | **NOT_LIVE_TESTED** — Phase 7 |
| Legacy `soroosh` → `soroush` value rename | **Phase 6 scope** (kept in enum) |

**No capability is marked `LIVE_TESTED`.**

---

## Next: Phase 6 — DB backward-compat migrations

- Normalize legacy `soroosh` enum values in `bot_mothers.type`,
  `bot_users.origin`, `blog_users.type`, `quran_scan_pages.type` to
  `soroush` (additive, backward-compatible data migration with rollback).
- Checkpoint entry with real test numbers.
