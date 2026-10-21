# Soroush Plus — Platform Integration (Phase 3)

## 📝 Overview

Phase 3 of the 10-phase messenger mission: **Soroush Plus** is now a real,
first-class publishing platform for the Channel Poster / Content Sync stack —
no longer a "coming soon" placeholder.

- Destination key: `soroush` (`ChannelPosterDestination::PLATFORM_SOROUSH`)
- API base: `https://api.splus.ir/bot<TOKEN>/<METHOD>` (HTTPS only)
- Token format: identical to Telegram (`123456:ABC-...`)
- Response envelope: `{ ok, result, description, error_code }`
- Transport: raw HTTP via Laravel `Http` facade (`SoroushBotClient`) — the
  vendor SDK is unused because its base URL is hardcoded to Telegram.

## 📊 Status Labels (precise)

| Capability | Status | Evidence |
|---|---|---|
| Text publish (`sendMessage`) | **MOCK_TESTED** | `ChannelPosterMediaPublishTest::test_soroush_text_publish_uses_splus_api`, `SoroushBotClientTest` |
| Media publish (multipart `sendPhoto`/`sendVideo`/`sendVoice`/`sendAudio`/`sendDocument`) | **MOCK_TESTED** | `ChannelPosterMediaPublishTest::test_soroush_receives_real_file_as_multipart_with_caption` |
| Test message (wizard verification) | **MOCK_TESTED** (code path exists; `sendTestMessage` soroush branch) | `TelegramChannelPosterPublisher::sendTestMessage` |
| Content Sync (immediate + retry) | **MOCK_TESTED** | `ContentSyncFlowTest::test_soroush_destination_is_published_like_other_platforms` |
| Bot wizard (token → chat id → connect) | **MOCK_TESTED** (state machine; API calls faked) | `ChannelPosterBotController` |
| Admin panel add/validate | **SUPPORTED** (validation `in:bale,telegram,eitaa,soroush,rubika`) | `AdminChannelPosterController` |
| Polls (`sendPoll`) | **BLOCKED (unverified)** | `sendPoll` is NOT in the official Soroush Plus method list — see `docs/features/platform-api-research.md` |
| Post URL construction | **BLOCKED (unverified)** | No verified public post URL format → `PostUrlBuilder` returns `['url' => null, 'status' => 'unverified']` |
| `parse_mode` support | **BLOCKED (unverified)** | Not documented for Soroush Plus → stripped in `SoroushBotClient::sendMessage(array)` (SDK-compat surface) |
| Mother webhook + child registration (`setWebhook` + `getWebhookInfo` read-back) | **MOCK_TESTED** (Phase 4) | `BotMotherSoroushRubikaWebhookTest` (soroush full flow: bot row → `setWebhook` URL → `getWebhookInfo` verify); allowed ports per docs: 443/80/88/8443 |
| **As source origin** — webhook ingestion, token/owner resolution, native replies | **MOCK_TESTED** (Phase 5) | `ChannelPosterOriginWebhookTest` — `/start` resolved from `soroush_bot_token`, reply via `api.splus.ir`, owner via `soroush_owner_chat_id` |
| As source — media download from origin (`getFile`) | **BLOCKED (unverified)** | Registry `download_media_from_origin=false`; flow continues with origin `file_id` (data-driven skip) |
| As source — inline keyboards / callbacks | **BLOCKED (unverified)** | Dropped + logged (`NativeChannelPosterPublisher`) / no-op |
| Live testing | **NOT_LIVE_TESTED** | Requires a real Soroush bot + channel; out of scope until a token is provided |

No capability is marked `LIVE_TESTED` — all HTTP is faked in tests.

## 📂 File Map

### Services
- `app/Services/SoroushBotClient.php` — raw-HTTP client: `call()`, `isOk()`, `getMe()`, `sendMessage()`, `sendMedia()`, `setWebhook()`, `deleteWebhook()`, `getWebhookInfo()`
- `app/Services/ChannelPosterMediaSender.php` — `doUpload()` soroush branch → `sendViaMultipart()` against `https://api.splus.ir/bot<token>/<method>`
- `app/Services/TelegramChannelPosterPublisher.php` — `publish()` text branch, `sendTestMessage()` branch, `tryFileId()` origin base for soroush
- `app/Services/ContentSync/ContentSyncService.php` — soroush skip blocks removed; `platformLabels()` includes soroush

### Models / Helpers
- `app/Models/ChannelPosterDestination.php` — `PLATFORM_SOROUSH` constant (existing)
- `app/Helpers/TokenHelper.php` — `isToken($t, 'soroush')` (Telegram-format regex); `redactTokens()` handles splus URLs via the generic `bot|/api/` pattern

### Controllers
- `app/Http/Controllers/ChannelPosterBotController.php` — wizard states `cp_awaiting_soroush_token` / `cp_awaiting_soroush_chat_id`, `handleSoroushToken()`, platform keyboard row, connected message
- `app/Http/Controllers/AdminBots/AdminChannelPosterController.php` — validation list includes `soroush`

### Lang
- 8 new keys in all 15 locales (`lang/*/bot.php`): `channel_poster_btn_rubika` is for rubika; soroush keys: `channel_poster_ask_soroush_token`, `channel_poster_ask_soroush_chat_id`, `channel_poster_soroush_connected` (+ existing `btn_soroush`, `sig_soroush`)

### Tests
- `tests/Unit/SoroushBotClientTest.php` (9 tests)
- `tests/Feature/ChannelPosterMediaPublishTest.php` (3 new soroush tests)
- `tests/Feature/ContentSyncFlowTest.php` (rewritten: `test_soroush_destination_is_published_like_other_platforms`)
- `tests/Feature/Modules/BotMother/BotMotherSoroushRubikaWebhookTest.php` (Phase 4: 5 soroush mother-webhook/registration tests)
- `tests/Feature/ChannelPosterOriginWebhookTest.php` (Phase 5: 2 soroush **source**-origin tests — `/start` + media skip)

### Phase 5 additions (source-origin generalization)
- `config/sync_platforms.php` + `app/Services/SyncPlatformRegistry.php` — soroush declared as `native` publisher, `download_media_from_origin=false`.
- `app/Services/NativeChannelPosterPublisher.php` — `sendPrivateMessage` via `SoroushBotClient`; `publish`/`sendTestMessage` lazy-delegate to `TelegramChannelPosterPublisher`.
- `ChannelPosterBotController` — 6 registry sites (`tokenColumn`/`ownerColumn`/media gate) + data-driven media-download skip.
- `ChannelPosterBotServiceImpl` — `isOwner`/`claimOwnerIfEmpty` registry-driven.
- `bot_users.origin` enum extended to include `soroush` (guarded migration).
- See `docs/features/messenger-sync/README.md` for the full architecture.

### Phase 4 additions (mother bots)
- `BotMotherController` native branch: `origin=soroush` updates are handled by
  `botMotherWebhookNative()` (text-only wizard, origin-scoped state key
  `soroush:<chatId>`, `AdminHelper::isAdmin()` gate).
- `BotRegistrationService::registerNativeClientBot('soroush')`: `getMe` →
  `isOk` → upsert `bots` (new `soroush_*` columns) →
  `WebhookEndpointHelper::createWebhookUrl()` → `setWebhook()` → `isOk` →
  `getWebhookInfo()` read-back (non-fatal) → `soroush_webhook_is_set` flag.
- Mother token from `env('SOROUSH_BOT_TOKEN')` (reserved in `.env.example`).

## 🔄 Flow

1. Owner picks **Soroush** on the platform keyboard → bot asks for token (`ask_soroush_token`).
2. `handleSoroushToken()` validates with `TokenHelper::isToken($token, 'soroush')` → stores in state.
3. Bot asks for channel id → `handleExternalChatId(..., 'soroush')` → `sendTestMessage` via `SoroushBotClient::sendMessage` → on success, destination row created with `bot_token` in DB.
4. Content Sync: `ContentSyncService::publishImmediate()` / `retryFailed()` now include soroush destinations → `ChannelPosterPublisher::publish()` → text via `SoroushBotClient`, media via `ChannelPosterMediaSender` multipart.
5. Failure → version row `failed` with redacted error; retry via `/retry`.

## 🔐 Security

- Tokens are stored per-destination in `channel_poster_destinations.bot_token` (DB), never in `.env` (`.env.example` documents the reserved Phase 4 vars).
- All log lines and error strings pass through `TokenHelper::redactTokens()`.

## ⛔ BLOCKED / Unverified (do not guess)

1. **`sendPoll`** — not in the official method list; unimplemented on purpose.
2. **Public post URL format** — `PostUrlBuilder` returns `unverified`; the sync report shows the status instead of a link.
3. **Envelope nuances** — `ok`/`description`/`error_code` are per docs; live confirmation pending (would upgrade MOCK_TESTED → LIVE_TESTED).
4. **`parse_mode`** — not documented; both `sendMessage` forms strip it for safety.
5. **Live webhook testing** — the Phase 4 wiring is `MOCK_TESTED` only; a real
   mother token + publicly reachable URL (allowed ports 443/80/88/8443) is
   required to upgrade to `LIVE_TESTED` (deferred to Phase 7).

## 📚 Sources

- `docs/features/platform-api-research.md` (Section: Soroush Plus) — official docs captured with dates
- `docs/features/messenger-platform-capability-matrix.md` — per-capability matrix
