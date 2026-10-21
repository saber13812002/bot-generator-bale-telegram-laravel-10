# Rubika — Platform Integration (Phase 3)

## 📝 Overview

Phase 3 of the 10-phase messenger mission: **Rubika** is now a real,
first-class publishing platform for the Channel Poster / Content Sync stack.

- Destination key: `rubika` (`ChannelPosterDestination::PLATFORM_RUBIKA`)
- API base: `https://botapi.rubika.ir/v3/<TOKEN>/<METHOD>` — **POST JSON only**
- Token format: opaque string; loose validation `[a-zA-Z0-9_-]{8,200}`
  (Rubika does not document a Telegram-like `id:secret` format)
- Response envelope: **NOT documented** (`BLOCKED`) → success is defined as
  HTTP 2xx **and** `message_id` present in the JSON body
- Transport: raw HTTP via Laravel `Http` facade (`RubikaBotClient`) — there is
  no usable vendor SDK

## 📊 Status Labels (precise)

| Capability | Status | Evidence |
|---|---|---|
| Text publish (`sendMessage`) | **MOCK_TESTED** | `ChannelPosterMediaPublishTest::test_rubika_text_publish_uses_v3_api_and_message_id`, `RubikaBotClientTest` |
| Media publish (3-step upload) | **MOCK_TESTED** | `ChannelPosterMediaPublishTest::test_rubika_uploads_file_in_three_steps` |
| Test message (wizard verification) | **MOCK_TESTED** (code path exists; `sendTestMessage` rubika branch) | `TelegramChannelPosterPublisher::sendTestMessage` |
| Content Sync (immediate + retry) | **MOCK_TESTED** | `ContentSyncFlowTest::test_soroush_destination_is_published_like_other_platforms` (all non-origin platforms incl. rubika) |
| Bot wizard (token → chat id → connect) | **MOCK_TESTED** (state machine; API calls faked) | `ChannelPosterBotController` |
| Admin panel add/validate | **SUPPORTED** (validation `in:bale,telegram,eitaa,soroush,rubika`) | `AdminChannelPosterController` |
| Response envelope shape | **BLOCKED (unverified)** | Docs never specify the response body; we only rely on `message_id` presence |
| Post URL construction | **BLOCKED (unverified)** | No verified public post URL format → `PostUrlBuilder` returns `['url' => null, 'status' => 'unverified']` |
| `parse_mode` support | **BLOCKED (unverified)** | Not documented for Rubika → stripped in `RubikaBotClient::sendMessage(array)` (SDK-compat surface) |
| Mother webhook + child registration (`updateBotEndpoints`) | **MOCK_TESTED** (Phase 4) | `BotMotherSoroushRubikaWebhookTest` (rubika full flow: bot row → `updateBotEndpoints(url, 'ReceiveUpdate')`); naming discrepancy in docs: methods page says `updateBotEndpoints` (plural), intro page says `updateBotEndpoint` — client uses the plural form |
| Webhook read-back / verification | **BLOCKED** | Rubika exposes no endpoint to read back the configured endpoint; the bot confirms registration, verification is impossible by API |
| **As source origin** — webhook ingestion (`NewMessage/User` normalization), token/owner resolution, native replies | **MOCK_TESTED** (Phase 5) | `ChannelPosterOriginWebhookTest` — `/start` resolved from `rubika_bot_token`, reply via `botapi.rubika.ir/v3`; `sender_type=Bot` and non-`NewMessage` types ignored (zero HTTP) |
| As source — inbound media fields (`Message.file` structure) | **BLOCKED (unverified)** | `file` field exists in the documented model but its structure is undocumented; `normalizeRubikaUpdate()` copies only `text` — engine does not guess |
| As source — media download from origin / inline keyboards / callbacks | **BLOCKED (unverified)** | Registry `download_media_from_origin=false`; keyboards dropped + logged; callbacks no-op |
| Live testing | **NOT_LIVE_TESTED** | Requires a real Rubika bot + channel; out of scope until a token is provided |

No capability is marked `LIVE_TESTED` — all HTTP is faked in tests.

## 📂 File Map

### Services
- `app/Services/RubikaBotClient.php` — raw-HTTP client: `call()`, `fileTypeFor()`, `hasMessageId()`, `extractMessageId()`, `getMe()`, `sendMessage()`, `requestUploadUrl()`, `uploadFile()`, `sendFile()`, `getUpdates()`, `updateEndpoint()`
- `app/Services/ChannelPosterMediaSender.php` — `doUploadRubika()` with documented size guards (Image 10 MB, Video 50 MB, File 50 MB); 3-step upload; `parseResponse()` rubika branch (success iff `message_id` present)
- `app/Services/TelegramChannelPosterPublisher.php` — `publish()` text branch, `sendTestMessage()` branch, `tryFileId()` origin base for rubika
- `app/Services/ContentSync/ContentSyncService.php` — rubika destinations publish like all others; `platformLabels()` includes rubika

### Models / Helpers
- `app/Models/ChannelPosterDestination.php` — `PLATFORM_RUBIKA` constant (added in Phase 3)
- `app/Helpers/TokenHelper.php` — `isToken($t, 'rubika')` (opaque, loose validation); `redactTokens()` has a rubika-specific `/v3/<token>/` pattern that **must run before** the generic `bot|/api/` pattern

### Controllers
- `app/Http/Controllers/ChannelPosterBotController.php` — wizard states `cp_awaiting_rubika_token` / `cp_awaiting_rubika_chat_id`, `handleRubikaToken()`, platform keyboard row, connected message
- `app/Http/Controllers/AdminBots/AdminChannelPosterController.php` — validation list includes `rubika`

### Lang
- 8 new keys in all 15 locales (`lang/*/bot.php`): `channel_poster_btn_rubika`, `channel_poster_sig_rubika`, `channel_poster_ask_rubika_token`, `channel_poster_ask_rubika_chat_id`, `channel_poster_rubika_connected` (+ soroush ask/connected keys added in the same rollout)

### Tests
- `tests/Unit/RubikaBotClientTest.php` (13 tests)
- `tests/Feature/ChannelPosterMediaPublishTest.php` (4 new rubika tests)
- `tests/Feature/Modules/BotMother/BotMotherSoroushRubikaWebhookTest.php` (Phase 4: 5 rubika mother-webhook/registration tests)
- `tests/Feature/ChannelPosterOriginWebhookTest.php` (Phase 5: 4 rubika **source**-origin tests — `/start` normalization, bot-own ignored, non-`NewMessage` ignored, media skip)

### Phase 5 additions (source-origin generalization)
- `config/sync_platforms.php` + `app/Services/SyncPlatformRegistry.php` — rubika declared as `native` publisher, `download_media_from_origin=false`.
- `app/Services/NativeChannelPosterPublisher.php` — `sendPrivateMessage` via `RubikaBotClient` (success iff `message_id` present); `publish`/`sendTestMessage` lazy-delegate to `TelegramChannelPosterPublisher`.
- `ChannelPosterBotController::normalizeRubikaUpdate()` — rubika's `{update:{type:"NewMessage", new_message:{...}}}` shape → engine shape; only `NewMessage` + `sender_type=User` processed; chat id from `update.chat_id` (fallback `new_message.chat_id`), sender from `new_message.sender_id`; copies only the documented `text` field.
- `ChannelPosterBotController` — 6 registry sites (`tokenColumn`/`ownerColumn`/media gate) + data-driven media-download skip.
- `ChannelPosterBotServiceImpl` — `isOwner`/`claimOwnerIfEmpty` registry-driven.
- `bot_users.origin` enum extended to include `rubika` (guarded migration).
- See `docs/features/messenger-sync/README.md` for the full architecture.

### Phase 4 additions (mother bots)
- `BotMotherController` native branch: `origin=rubika` updates are handled by
  `botMotherWebhookNative()` (text-only wizard, origin-scoped state key
  `rubika:<chatId>`, `AdminHelper::isAdmin()` gate). Only `NewMessage`
  updates are processed; everything else is ignored with `200 {"ok":true}`.
- `BotRegistrationService::registerNativeClientBot('rubika')`: `getMe` →
  `bot_id` present → upsert `bots` (new `rubika_*` columns) →
  `WebhookEndpointHelper::createWebhookUrl()` → `updateEndpoint($url, 'ReceiveUpdate')`
  (client throws on HTTP ≥ 400) → `rubika_webhook_is_set` flag.
- Mother token from `env('RUBIKA_BOT_TOKEN')` (reserved in `.env.example`).

## 🔄 Flow

1. Owner picks **Rubika** on the platform keyboard → bot asks for token (`ask_rubika_token`).
2. `handleRubikaToken()` validates with `TokenHelper::isToken($token, 'rubika')` → stores in state.
3. Bot asks for channel id → `handleExternalChatId(..., 'rubika')` → `sendTestMessage` via `RubikaBotClient::sendMessage` → on success (message_id present), destination row created with `bot_token` in DB.
4. **Text**: `publish()` → `sendMessage` → success iff `message_id` in response.
5. **Media (3-step, per official docs)**:
   1. `requestSendFile(type)` → server returns `upload_url`
   2. `POST multipart` (field `file`) to `upload_url` → returns `file_id`
   3. `sendFile(chat_id, file_id, text)` → returns `message_id`
   - `contentType` → `FileTypeEnum` mapping: photo→Image, video→Video, voice→Voice, audio→Music, default→File
   - Size guards (documented limits): Image 10 MB, Video 50 MB, File 50 MB — exceeded → hard failure with a clear error, no fake success
6. Failure → version row `failed` with redacted error; retry via `/retry`.

## 🔐 Security

- Tokens are stored per-destination in `channel_poster_destinations.bot_token` (DB), never in `.env` (`.env.example` documents the reserved Phase 4 vars).
- All log lines and error strings pass through `TokenHelper::redactTokens()`; the rubika URL pattern (`/v3/<token>/`) is redacted specifically because the token sits in the URL path.

## ⛔ BLOCKED / Unverified (do not guess)

1. **Response envelope** — the official docs do not document the response body
   shape. We treat `message_id` presence as the success signal and everything
   else as failure. A live test would upgrade this and the publish statuses.
2. **Public post URL format** — `PostUrlBuilder` returns `unverified`; the
   sync report shows the status instead of a link.
3. **`updateBotEndpoints` naming** — methods page lists the plural form; the
   intro page uses singular. Client uses the plural form per the methods page.
4. **Webhook read-back** — the Phase 4 wiring is `MOCK_TESTED`, but Rubika has
   **no API to verify the configured endpoint**, so webhook verification is
   `BLOCKED` by design (no guessing).
5. **Live webhook testing** — a real mother token + publicly reachable URL is
   required to upgrade to `LIVE_TESTED` (deferred to Phase 7).
6. **Inbound media fields** (Phase 5) — the documented `Message` model has a
   `file` field but its internal structure is undocumented; the engine does
   **not** guess at `photo`/`video`/`document` shapes inside `new_message`.
   Any future extraction must come from official docs, not probing.
7. **Inline keyboards / callback queries** (Phase 5) — Rubika documents only
   *outbound* keypads (`Keypad` model); the inbound callback/answer API is
   undocumented → `NativeChannelPosterPublisher` drops keyboards (logged) and
   no-ops `answerCallback`.

## 📚 Sources

- `docs/features/platform-api-research.md` (Section: Rubika) — official docs captured with dates
- `docs/features/messenger-platform-capability-matrix.md` — per-capability matrix
