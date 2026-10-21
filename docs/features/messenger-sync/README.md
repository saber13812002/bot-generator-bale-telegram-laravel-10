# Messenger Sync — Engine Generalization (Phase 5)

## 📝 Overview

Phase 5 of the 10-phase messenger mission. The Channel Poster / Content Sync
engine previously handled platform-specific behaviour with **hardcoded
`if/switch` branches**. Phase 5 makes that behaviour **data-driven**: a single
config registry declares, per platform, which publisher it uses, which DB
column holds its token/owner, and whether media may be downloaded from the
platform's own API.

Concrete outcomes:

1. **Soroush Plus and Rubika are now first-class *source* origins** of the
   Channel Poster webhook — not just destinations. An owner can talk to the
   channel-poster bot on their own Soroush/Rubika account (`/start`, forward
   a channel, send media), and the bot resolves the bot row from the
   per-platform token column and replies through the internal native client
   (`SoroushBotClient` / `RubikaBotClient`) — no vendor SDK involved.
2. **Rubika's distinct webhook shape is normalized** by the engine
   (`{ "update": { "type": "NewMessage", ... } }` → the engine's
   `{ message: { chat, from, ... } }` shape), with documented filtering:
   only `NewMessage` + `sender_type=User` is processed; bot-own messages and
   other update types are ignored with `200 {"ok":true}`.
3. **Media download from origin is a data-driven decision**: bale/telegram
   are allowed (documented `getFile`), soroush/rubika are *explicitly skipped*
   (their `getFile`/inbound-media surfaces are unverified → `BLOCKED`).
   The flow continues with the origin `file_id` (same-platform reuse), and
   cross-platform media publishes report a clear failure — never a fake
   success.
4. Unknown origins fall back to the legacy vendor publisher behaviour, so
   existing `gap`/`soroosh` tokens keep working unchanged.

Nothing here is `LIVE_TESTED`. All HTTP is faked; live webhook testing is
Phase 7 (needs real tokens + a public URL).

## 🏛️ Architecture

### The registry — `config/sync_platforms.php`

```php
'soroush' => [
    'label' => 'Soroush',
    'token_column' => 'soroush_bot_token',
    'owner_column' => 'soroush_owner_chat_id',
    'publisher' => 'native',            // internal client, no vendor SDK
    'download_media_from_origin' => false, // getFile unverified (BLOCKED)
],
'rubika' => [
    ...
    'publisher' => 'native',
    'download_media_from_origin' => false, // inbound media fields undocumented (BLOCKED)
],
'bale' => [ 'publisher' => 'vendor', 'download_media_from_origin' => true, ... ],
'telegram' => [ 'publisher' => 'vendor', 'download_media_from_origin' => true, ... ],
```

### Accessor — `app/Services/SyncPlatformRegistry.php`

Static, config-backed accessors with a safe telegram-default fallback for
unknown keys (mirrors the legacy vendor behaviour):

| Method | Returns |
|---|---|
| `tokenColumn($origin)` | the `bots` column holding the platform token |
| `ownerColumn($origin)` | the `bots` column holding the owner chat id |
| `publisher($origin)` | `'vendor'` \| `'native'` |
| `canDownloadMediaFromOrigin($origin)` | bool — data-driven media-download gate |
| `label($origin)` | display label |

### Publisher selection — `TelegramChannelPosterPublisherFactory::make()`

```php
if (SyncPlatformRegistry::publisher($origin) === 'native') {
    return new NativeChannelPosterPublisher($token, $origin, $sender);
}
$bot = $origin === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
return new TelegramChannelPosterPublisher($bot, $origin, $sender);
```

`NativeChannelPosterPublisher` (new, `app/Services/NativeChannelPosterPublisher.php`):

- `sendPrivateMessage()` → text via `SoroushBotClient`/`RubikaBotClient`;
  inline keyboards are **dropped with a log line** (undocumented on both
  platforms — `BLOCKED`, see below).
- `publish()` / `sendTestMessage()` → **lazy-delegated** to
  `TelegramChannelPosterPublisher` (constructed only on first use), which
  already contains the verified soroush/rubika destination branches from
  Phase 3. The carrier `Telegram` bot is never *called* for these origins —
  the native clients are.
- `answerCallback()` → no-op + log (callback API undocumented → `BLOCKED`).

### Controller sites switched to the registry
(`app/Http/Controllers/ChannelPosterBotController.php`)

| Site | Before | After |
|---|---|---|
| `resolveBotAndToken()` | `match($type)` token column | `SyncPlatformRegistry::tokenColumn($type)` |
| webhook owner log | hardcoded `bale_owner_chat_id` | `SyncPlatformRegistry::ownerColumn($type)` |
| `extractUpdate()` | telegram/bale shapes only | + `normalizeRubikaUpdate()` hook |
| `handlePrivateMessage()` owner log | hardcoded | registry `ownerColumn` |
| `offerOrPublishContent()` media download | unconditional `fetchAndStore` | gated by `canDownloadMediaFromOrigin($type)` |
| `handleSyncRetryCommand()` publisher | `match($type)` token | registry `tokenColumn` |
| `ChannelPosterBotServiceImpl::isOwner/claimOwnerIfEmpty` | hardcoded `bale_owner_chat_id` | registry `ownerColumn($origin)` |

`normalizeRubikaUpdate()` (private static, in the controller): requires
`update.type === 'NewMessage'` **and** `new_message.sender_type === 'User'`
(exact string, per the documented shape in
`docs/features/platform-api-research.md`), maps chat id from
`update.chat_id` (fallback `new_message.chat_id`) and sender from
`new_message.sender_id`, and copies only the documented `text` field.
Everything else returns `null` → the webhook answers `200 {"ok":true}` and
does nothing.

## 📊 Per-platform source-vs-destination matrix (Phase 5 state)

| Capability | Soroush | Rubika | Evidence / notes |
|---|---|---|---|
| **As destination** (Phase 3) | `MOCK_TESTED` | `MOCK_TESTED` | text + media publish, sync, wizard, test message, admin panel |
| **As source — webhook ingestion** | `MOCK_TESTED` | `MOCK_TESTED` | soroush: Telegram-style updates (native shape); rubika: `NewMessage/User` normalized in `extractUpdate()` |
| **As source — bot resolution by token** | `MOCK_TESTED` | `MOCK_TESTED` | `resolveBotAndToken()` via registry `tokenColumn` |
| **As source — owner detection / claim** | `MOCK_TESTED` | `MOCK_TESTED` | `isOwner`/`claimOwnerIfEmpty` via registry `ownerColumn` |
| **As source — private replies** | `MOCK_TESTED` | `MOCK_TESTED` | `NativeChannelPosterPublisher::sendPrivateMessage` → native clients |
| **As source — media download from origin API** | `BLOCKED (unverified)` — data-driven skip | `BLOCKED (unverified)` — data-driven skip | soroush `getFile` is documented (≤20 MB, 1 h link) but never verified against a live bot → policy: unverified ⇒ skip. Rubika inbound media fields (`Message.file`) exist but their structure is undocumented ⇒ engine must not guess |
| **As source — same-platform file_id reuse** | `SUPPORTED` (design) | `SUPPORTED` (design) | flow stores `file_id`, `media_path=null`; same-platform publish uses it, cross-platform media reports a clear error |
| **Inline keyboards on replies** | `BLOCKED (unverified)` — dropped + logged | `BLOCKED (unverified)` — dropped + logged | keyboard APIs not documented for either platform |
| **Callback queries (source-side)** | `BLOCKED (unverified)` — no-op | `BLOCKED (unverified)` — no-op | rubika `Keypad` docs cover only *outbound* keypads |
| **Registration as source-capable bot** | `SUPPORTED` (additive migration) | `SUPPORTED` (additive migration) | `bot_users.origin` enum extended to include `soroush`, `rubika` (MySQL-guarded, `soroosh` kept for Phase 6) |
| **Live webhook testing** | `NOT_LIVE_TESTED` | `NOT_LIVE_TESTED` | Phase 7 (needs real tokens + public URL) |

## 📂 Files

### Created
- `config/sync_platforms.php` — the platform registry
- `app/Services/SyncPlatformRegistry.php` — config accessors
- `app/Services/NativeChannelPosterPublisher.php` — native-client publisher
- `database/migrations/2026_10_20_000002_extend_origin_enum_to_bot_users_table.php`
  — `bot_users.origin` → `ENUM('bale','telegram','gap','soroosh','soroush','rubika') NULL`
  (MySQL-only, driver + `hasTable` + `SHOW COLUMNS` guarded; `down()` restores
  the 4-value enum; **legacy `soroosh` kept for Phase 6**)
- `tests/Feature/ChannelPosterOriginWebhookTest.php` — 7 feature tests

### Modified
- `app/Services/TelegramChannelPosterPublisherFactory.php` — registry branch in `make()`
- `app/Http/Controllers/ChannelPosterBotController.php` — 6 registry sites + `normalizeRubikaUpdate()`
- `app/Services/ChannelPosterBotServiceImpl.php` — `isOwner`/`claimOwnerIfEmpty` registry-driven
- `tests/UsesChannelPosterSqlite.php` — 12 additive sqlite `bots` columns (soroush/rubika × 6)

## ✅ Test evidence (REAL)

Subset — `tests/Feature/ChannelPosterOriginWebhookTest.php` (Http::fake, sqlite in-memory):

```
Tests:    2 deprecated, 5 passed (39 assertions)
Duration: 3.16s
```

(7 tests total; the 2 `!` markers are the same vendor PHP 8.5 deprecation
noise present in the baseline — the `Tests:` line is the source of truth.)

Covered:

1. soroush `/start` → bot resolved from `soroush_bot_token`, welcome +
   forward prompt sent to `https://api.splus.ir/bot<TOKEN>/sendMessage`,
   `bot_users.origin='soroush'`, state `cp_awaiting_bale_forward`
2. rubika `NewMessage/User` `/start` → normalized, reply via
   `https://botapi.rubika.ir/v3/<TOKEN>/sendMessage`, `origin='rubika'`
3. rubika `NewMessage/Bot` (own message) → ignored, **zero HTTP**
4. rubika non-`NewMessage` (`StartedBot`) → ignored, **zero HTTP**
5. soroush-origin photo → **`fetchAndStore` never called** (data-driven
   skip), publish-options prompt still sent, pending content stored with the
   origin `file_id` and `media_path=null`
6. rubika-origin media (`file` field present) → same skip; because the
   inbound media *structure* is unverified, the engine intentionally does
   not guess it — flow continues as the message's `text`, stored as
   `content_type=text`, `file_id=null`
7. factory returns `TelegramChannelPosterPublisher` for `bale`/`telegram`/
   unknown (`gap`) and `NativeChannelPosterPublisher` for `soroush`/`rubika`

Full suite (post-Phase 5):

```
Tests:    2 deprecated, 2 skipped, 451 passed (2402 assertions)
Duration: 69.44s
```

**0 failures.** Post-Phase 4 baseline was 444 passed / 2363 assertions →
**+7 tests, +39 assertions, 0 regressions.**

## ⛔ BLOCKED / Unverified (do not guess)

1. **Media download from soroush origin** — `getFile` is documented
   (≤20 MB, download link valid ≥1 h) but never verified against a live
   bot; registry keeps `download_media_from_origin=false`. Upgrading to
   `true` requires one live confirmation (Phase 7).
2. **Rubika inbound media fields** — `Message.file` exists in the documented
   model but its internal structure is undocumented; `normalizeRubikaUpdate`
   copies only `text`. Any future extraction must come from docs, not
   probing.
3. **Inline keyboards (outbound) on soroush/rubika** — undocumented →
   `NativeChannelPosterPublisher` drops them with a log line.
4. **Callback queries / `answerCallbackQuery`** — soroush documents the
   method but no live verification; rubika documents only *outbound*
   keypads. Both no-op with a log line.
5. **Rubika response envelope** — still undocumented; success is judged by
   `message_id` presence (carried over from Phase 3).
6. **Live webhook testing** — everything above is `MOCK_TESTED` only.

## 🔐 Security

- No secrets in code or logs: all error strings pass through
  `TokenHelper::redactTokens()`.
- Tokens stay in the DB (`bots.soroush_bot_token` / `bots.rubika_bot_token`
  for source bots; `channel_poster_destinations.bot_token` for destinations).
- The migration is additive and driver-guarded; `down()` restores prior state.

## 📚 Sources

- `docs/features/platform-api-research.md` — Soroush Plus + Rubika v3 (dated captures)
- `docs/features/messenger-platform-capability-matrix.md` — per-capability matrix
- `IMPLEMENTATION_CHECKPOINT.md` — Phase 5 entry (test numbers)
- `docs/features/soroush/README.md`, `docs/features/rubika/README.md` — platform docs
