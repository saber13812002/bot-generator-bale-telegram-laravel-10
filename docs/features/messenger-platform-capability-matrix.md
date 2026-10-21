# Messenger Platform Capability Matrix

> Phase 1 — built strictly from **code evidence** in this repo (branch `15esfand1404`).
> "Official Evidence" cites the code location that proves each claim.
> Status values: `SUPPORTED` · `PARTIAL` · `UNSUPPORTED` · `NOT_IMPLEMENTED` · `MOCK_TESTED` · `LIVE_TESTED` · `BLOCKED`

## Legend for test status

- **MOCK_TESTED** — covered by automated tests with faked HTTP / fake publisher (no real network).
- **LIVE_TESTED** — verified against the real platform API (requires real tokens; not claimed anywhere in this repo yet).
- **NOT_IMPLEMENTED** — code path does not exist (or is a no-op/stub).
- **BLOCKED** — blocked on external evidence (official API documentation not yet confirmed).

---

## Capability Matrix

| Capability | Telegram | Bale | Eitaa | Gap (current gap in platform) | Soroush | Rubika | Official Evidence (code) | Implementation | Tests | Limitations |
|---|---|---|---|---|---|---|---|---|---|---|
| **SDK / transport** | vendor SDK `saber13812002/telegram-bale-bot-php` (`new Telegram($token)`) | same SDK, `new Telegram($token,'bale')` | raw HTTP (`BotHelper::call_eitaa_api`, `https://eitaayar.ir/api/{token}/…`) | separate SDK `saber13812002/gap-sdp-api` | none | none | `composer.json:10-30`; `app/Helpers/BotHelper.php:306-352` | `SUPPORTED` / `SUPPORTED` / `PARTIAL` / `SUPPORTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` | — | Soroush & Rubika have no transport layer at all. |
| **Bot registration (token → bot row → webhook)** | `BotRegistrationService::registerBot` (getMe + setWebhook, saves `telegram_*` fields) | same service (`bale_*` fields) | ❌ not in registration flow | partial (handled in legacy `BotHelper` flow) | ❌ | ❌ | `app/Modules/BotRegistration/Services/BotRegistrationService.php:23-121` | `SUPPORTED` / `SUPPORTED` / `NOT_IMPLEMENTED` / `PARTIAL` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` | `tests/Feature/BotCreationWorkflowIntegrationTest.php` (MOCK_TESTED) | Registration only accepts `['telegram','bale']` (`getSupportedPlatforms`). |
| **Webhook ingestion (inbound messages)** | ✅ `/api/webhook-bot-*` routes → `BotMotherController::botMotherWebhook` | ✅ same routes (type = 'bale') | outbound only (no inbound registration) | ✅ via Gap SDK webhook | ❌ | ❌ | `routes/api.php` (webhook routes), `app/Http/Controllers/BotMotherController.php:54` | `SUPPORTED` / `SUPPORTED` / `UNSUPPORTED` (inbound) / `SUPPORTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` | `tests/Feature/WebhookTest.php`, `ChannelPosterBotWebhookTest.php` (MOCK_TESTED) | Eitaa cannot act as a *source* messenger. |
| **Private message (to user/chat)** | `sendMessageByChatId` / SDK | same | `BotHelper::sendMessageByChatIdEitaa` | `sendGap4InlineMessage` | ❌ | ❌ | `app/Helpers/BotHelper.php:460-469, 512-519, 578-604` | `SUPPORTED` / `SUPPORTED` / `SUPPORTED` / `PARTIAL` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` | — | |
| **Channel publish — text** | `TelegramChannelPosterPublisher::publish` → `sendMessage` | same | `BotHelper::sendMessageEitaaSupport` | SDK-based | ❌ **hard-skipped** with `channel_sync_soroush_not_ready` | ❌ | `app/Services/TelegramChannelPosterPublisher.php:83-222`; `app/Services/ContentSync/ContentSyncService.php:477-492` | `SUPPORTED` / `SUPPORTED` / `SUPPORTED` / `PARTIAL` / `NOT_IMPLEMENTED` (stub) / `NOT_IMPLEMENTED` | `tests/Feature/ContentSyncFlowTest.php`, `ChannelPosterQueueSendTest.php` (MOCK_TESTED) | Soroush destinations exist in DB but the engine always marks them `SKIPPED`. |
| **Channel publish — media (photo/video/voice/audio/document)** | multipart `https://api.telegram.org/bot{token}/send*` | multipart `https://tapi.bale.ai/bot{token}/send*` | multipart `https://eitaayar.ir/api/{token}/sendFile` | `BotHelper::sendPhotoGap` | ❌ (excluded from media upload; text-only fallback intended) | ❌ | `app/Services/ChannelPosterMediaSender.php:104-184`; `TelegramChannelPosterPublisher.php:114-155` | `SUPPORTED` / `SUPPORTED` / `SUPPORTED` / `PARTIAL` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` | `tests/Feature/ChannelPosterMediaPublishTest.php` (MOCK_TESTED) | Media from a source platform requires a local file or same-platform `file_id`; cross-platform file_id is rejected by design (`tryFileId` returns null for other platforms). |
| **file_id reuse (same platform)** | ✅ `tryFileId` | ✅ `tryFileId` | ❌ (no file_id concept used) | ❌ | ❌ | ❌ | `app/Services/TelegramChannelPosterPublisher.php:232-298` | `SUPPORTED` / `SUPPORTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` | — | |
| **Post URL after publish** | `t.me/{user}/{id}` (confirmed), `t.me/c/{short}/{id}` (constructed) | `ble.ir/{user}/{chatId}/{id}` (constructed) | `eitaa.com/{id}/{id}` (constructed) | ❌ unimplemented in sync URL builder | ❌ always `unverified` | ❌ | `app/Services/ContentSync/PostUrlBuilder.php:22-99` | `SUPPORTED` / `SUPPORTED` / `SUPPORTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` | — | Soroush URL format has no real sample; Rubika has no code. |
| **Post URL parsing (source link → identifiers)** | `t.me`, `telegram.me`, `tlg.sk` | `ble.ir`, `bale.ir`, `bale.ai`, `mybale.ai` | `eitaa.com`, `my.eitaa.com` | ❌ (gap not in parser allowlist) | `soroush.chat`, `splus.ir` — **unverified format** | ❌ | `app/Services/ContentSync/PostUrlParser.php:26-31, 152-166` | `SUPPORTED` / `SUPPORTED` / `SUPPORTED` / `NOT_IMPLEMENTED` / `PARTIAL` (unverified) / `NOT_IMPLEMENTED` | — | Soroush URL pattern is an assumption; marked unverified in code comments. |
| **Platform validation lists (request/destination)** | ✅ | ✅ | ✅ (`in:bale,telegram,eitaa,soroush` in admin controller) | `soroosh` (note misspelling) in `BotRequest` | `soroush` in `ChannelPosterDestination` + admin controller; **`soroosh` in `BotRequest`** (string mismatch) | ❌ absent everywhere | `app/Http/Requests/BotRequest.php`; `app/Http/Controllers/AdminBots/AdminChannelPosterController.php:48`; `app/Models/ChannelPosterDestination.php` | `SUPPORTED` / `SUPPORTED` / `SUPPORTED` / `PARTIAL` / `PARTIAL` (inconsistent token) / `NOT_IMPLEMENTED` | — | `soroosh` vs `soroush` mismatch must be normalized (Phase 4). |
| **Token validation regex** | ✅ (digits:30+ chars) | ✅ (digits:30-50 chars) | ❌ (no regex; token passed through) | ❌ | ❌ | ❌ | `app/Helpers/TokenHelper.php:10-30` | `SUPPORTED` / `SUPPORTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` | — | Soroush/Rubika token formats unknown until Phase 2 research. |
| **Token redaction in logs** | ✅ `TokenHelper::redactTokens` (covers `digits:secret` + `bot{token}/` URLs) | ✅ same | ✅ same (URL pattern `/api/{token}/`) | ✅ same | n/a | n/a | `app/Helpers/TokenHelper.php:56-73` | `SUPPORTED` (all transports route errors through it) | — | New platforms must route all error strings through `redactTokens`. |
| **Content sync engine (source → N destinations, tags, dedup, per-destination status)** | ✅ as source & destination | ✅ as source & destination | ✅ as destination only (no webhook source) | ❌ not in sync engine | ❌ **skipped** in `publishImmediate` + `retryFailed` | ❌ | `app/Services/ContentSync/ContentSyncService.php:455-560, 674-799` | `SUPPORTED` / `SUPPORTED` / `PARTIAL` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` (skip stub) / `NOT_IMPLEMENTED` | `tests/Feature/ContentSyncFlowTest.php` (MOCK_TESTED) | Platform handling is hardcoded `if/switch` — Phase 5 generalizes to config-driven rules. |
| **Queue publishing (slots, quiet hours, idempotency)** | ✅ `ChannelPosterQueuePublishService::publishItem` (2h slots, quiet 02–09 Tehran, skip-if-published via `ChannelPosterPublishLog`) | ✅ same | ✅ same | ❌ | ❌ | ❌ | `app/Services/ChannelPosterQueuePublishService.php:44-257`; `ChannelPosterBotServiceImpl.php:500-524` | `SUPPORTED` / `SUPPORTED` / `SUPPORTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` | `tests/Feature/ChannelPosterQueueSendTest.php` (MOCK_TESTED) | |
| **Attribution / signature suffix** | ✅ signed / link_only / none (`buildAttributionSuffix`) | ✅ | ✅ | ❌ | label exists (`channel_poster_sig_soroush`) but never published | ❌ | `ContentSyncService.php:1096-1154, 1074-1082` | `SUPPORTED` / `SUPPORTED` / `SUPPORTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` | — | |
| **Text chunking (long posts)** | ✅ `TextChunker` | ✅ | ✅ | ❌ | ❌ | ❌ | `ContentSyncService::publishChunks` (line 1162) | `SUPPORTED` / `SUPPORTED` / `SUPPORTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` / `NOT_IMPLEMENTED` | — | |
| **DB support (destinations row)** | ✅ | ✅ | ✅ | ⚠️ no constant | ✅ constant `PLATFORM_SOROUSH` exists | ❌ no constant | `app/Models/ChannelPosterDestination.php`; migrations §5 of audit | `SUPPORTED` / `SUPPORTED` / `SUPPORTED` / `PARTIAL` / `PARTIAL` (column exists, never used) / `NOT_IMPLEMENTED` | — | `platform` is a plain string column → adding `rubika` needs no migration, only constant + validation + code. |
| **Env vars (`.env.example`)** | `BOT_MOTHER_TOKEN_TELEGRAM` + per-feature tokens | `BOT_MOTHER_TOKEN_BALE` + per-feature tokens | `BOT_EITAA_TOKEN_SABER` etc. | `BOT_MOTHER_TOKEN_GAP` | `SPLUS_BOT_TOKEN` (BUI pattern-messaging only — **not** a bot token) | ❌ | `.env.example` (217 lines) | `SUPPORTED` / `SUPPORTED` / `PARTIAL` / `SUPPORTED` / `PARTIAL` (wrong purpose) / `NOT_IMPLEMENTED` | — | New vars added in Phase 3 with empty values only. |

---

## Per-platform summary

| Platform | Overall status | Notes |
|---|---|---|
| **Telegram** | `SUPPORTED` (fully wired) | Source + destination + registration + webhooks + media + URL parsing + queue + attribution. MOCK_TESTED. |
| **Bale** | `SUPPORTED` (fully wired) | Mirror of Telegram via same SDK with `bale` flavor. MOCK_TESTED. |
| **Eitaa** | `PARTIAL` | Destination-only platform (no inbound webhook/registration). Text + media via raw HTTP. No token regex. |
| **Gap** | `PARTIAL` | Legacy integration only (private msg, photo, inline keyboards). Not part of channel-poster/sync engine at all. |
| **Soroush** | `PARTIAL` | Phase 3: raw-HTTP transport, text/media publish, sync, wizard (MOCK_TESTED). Phase 4: mother webhook + child registration (MOCK_TESTED). Phase 5: first-class **source** origin (webhook ingestion, token/owner resolution, native replies, data-driven media-download skip) — MOCK_TESTED. Polls and public post URL remain BLOCKED; no live testing yet. |
| **Rubika** | `PARTIAL` | Phase 3: raw-HTTP transport, text + 3-step media publish, sync, wizard (MOCK_TESTED). Phase 4: mother webhook + child registration (MOCK_TESTED). Phase 5: first-class **source** origin (incl. `NewMessage/User` normalization) — MOCK_TESTED. Response envelope and webhook read-back remain BLOCKED; no live testing yet. |

---

## Status update (Phases 3–4, 2026-10-20)

The table above is the **Phase 1 audit snapshot**. The rows below were
re-verified against the current code after Phases 3 and 4; the per-platform
summary has been updated in place.

| Capability (Phase 1 row) | Soroush (current) | Rubika (current) |
|---|---|---|
| SDK / transport | `SUPPORTED` — `app/Services/SoroushBotClient.php` (raw HTTP, `api.splus.ir`) | `SUPPORTED` — `app/Services/RubikaBotClient.php` (raw HTTP, `botapi.rubika.ir/v3`, POST JSON) |
| Bot registration (token → bot row → webhook) | `MOCK_TESTED` — `BotRegistrationService::registerNativeClientBot()`: `getMe` → upsert `soroush_*` columns → `setWebhook` → `getWebhookInfo` read-back | `MOCK_TESTED` — `getMe` → upsert `rubika_*` columns → `updateBotEndpoints($url, 'ReceiveUpdate')` |
| Webhook ingestion (inbound messages) | `MOCK_TESTED` — `origin=soroush` → `BotMotherController::botMotherWebhookNative()` (text-only mother wizard, admin-gated) | `MOCK_TESTED` — `origin=rubika` → same path, `NewMessage` updates only |
| Token validation regex | `SUPPORTED` — `TokenHelper::isToken($t, 'soroush')` (Telegram-style) | `SUPPORTED` — `isToken($t, 'rubika')` (loose opaque, no documented format) |
| Token redaction | `SUPPORTED` — generic `bot|/api/` pattern covers splus URLs | `SUPPORTED` — dedicated `/v3/<token>/` pattern (runs before the generic one) |
| Env vars (`.env.example`) | `SOROUSH_BOT_TOKEN=` reserved (Phase 4, empty) | `RUBIKA_BOT_TOKEN=` reserved (Phase 4, empty) |
| DB support (`bots` table) | 6 additive `soroush_*` columns (guarded migration, Phase 4) | 6 additive `rubika_*` columns (guarded migration, Phase 4) |

Evidence: `tests/Feature/Modules/BotMother/BotMotherSoroushRubikaWebhookTest.php`
(11 tests, Http::fake), `tests/Unit/SoroushBotClientTest.php` (9),
`tests/Unit/RubikaBotClientTest.php` (13). Full suite post-Phase 4:
**444 passed / 2 skipped / 2 deprecated / 2363 assertions, 0 failures**
(see `IMPLEMENTATION_CHECKPOINT.md`, Phase 4 entry).

Still `NOT_IMPLEMENTED` / `BLOCKED` for both platforms: polls, verified
public post URLs (and URL parsing beyond the unverified soroush pattern),
content sync **source** usage (Phase 5 generalizes the engine), and all
`LIVE_TESTED` claims. The `soroosh` → `soroush` legacy value rename is
deferred to Phase 6.

---

## What Phase 3 must deliver (mapped from the gaps above)

1. `ChannelPosterDestination::PLATFORM_RUBIKA = 'rubika'` constant (Soroush constant already exists).
2. Real `publish()` paths for both platforms **only where official API evidence exists** (Phase 2). Anything unverified stays `BLOCKED` with a clear skip status — never a fake success.
3. Platform constants + validation lists normalized: `soroosh` → `soroush` everywhere; `rubika` added to `AdminChannelPosterController` (`in:bale,telegram,eitaa,soroush,rubika`), `ContentSyncService::platformLabels()`, `PostUrlParser`/`PostUrlBuilder` (if format is verified).
4. `TokenHelper::isToken()` branches for the new token formats (per Phase 2 evidence).
5. `ChannelPosterMediaSender::doUpload()` branches if/when the official media endpoints are confirmed.
6. `.env.example`: `SOROUSH_BOT_TOKEN=`, `RUBIKA_BOT_TOKEN=` (empty; documented).
7. Remove the hardcoded Soroush skip in `ContentSyncService` and replace with the config-driven platform rules from Phase 5 (so a working publisher is used when present, and a *clear, data-driven* skip is used when not).

---

## Status update (Phase 5, 2026-10-20)

The rows above remain the **Phase 1 audit snapshot**; the row re-verified
after Phases 3–4 is in the previous section. Phase 5 generalized the engine
to a config-driven platform registry — see
`docs/features/messenger-sync/README.md` for the full architecture.

| Capability (Phase 1 row) | Soroush (current) | Rubika (current) |
|---|---|---|
| Webhook ingestion — **channel-poster source** | `MOCK_TESTED` — `origin=soroush` at `POST /api/webhook-channel-poster`; bot resolved from `soroush_bot_token` via `SyncPlatformRegistry::tokenColumn()`; Telegram-style updates ingested natively | `MOCK_TESTED` — same endpoint; rubika `{update:{NewMessage,User}}` shape normalized by `ChannelPosterBotController::normalizeRubikaUpdate()`; `sender_type=Bot` and non-`NewMessage` types ignored with zero HTTP |
| Private message (to user/chat) — source side | `MOCK_TESTED` — `NativeChannelPosterPublisher` → `SoroushBotClient::sendMessage` (`api.splus.ir`) | `MOCK_TESTED` — `NativeChannelPosterPublisher` → `RubikaBotClient::sendMessage` (`botapi.rubika.ir/v3`) |
| Content sync engine — **as source origin** | `MOCK_TESTED` — owner/claim + media flow registry-driven (`ownerColumn` / `canDownloadMediaFromOrigin`) | `MOCK_TESTED` — same |
| Media download from origin | `BLOCKED (unverified)` — data-driven skip (`download_media_from_origin=false`); flow continues with origin `file_id`, cross-platform media reports a clear failure | `BLOCKED (unverified)` — same skip; inbound `Message.file` structure undocumented, engine does not guess |
| Inline keyboards / callbacks (source replies) | `BLOCKED (unverified)` — dropped + logged / no-op | `BLOCKED (unverified)` — dropped + logged / no-op |
| `bot_users.origin` enum | `SUPPORTED` — extended to include `soroush` (guarded migration) | `SUPPORTED` — extended to include `rubika` |

Still `NOT_IMPLEMENTED` / `BLOCKED` for both platforms: polls, verified
public post URLs (and URL parsing beyond the unverified soroush pattern),
**live** source webhook testing (Phase 7), and all `LIVE_TESTED` claims.
The `soroosh` → `soroush` legacy value rename is deferred to Phase 6.

Evidence: `tests/Feature/ChannelPosterOriginWebhookTest.php` (7 tests,
Http::fake). Full suite post-Phase 5:
**451 passed / 2 skipped / 2 deprecated / 2402 assertions, 0 failures**
(see `IMPLEMENTATION_CHECKPOINT.md`, Phase 5 entry).
