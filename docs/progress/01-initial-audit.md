# Phase 0 — Initial Audit (State of the Repository)

> Audit date: 2026-10-19 (branch `15esfand1404`, clean working tree, 1 commit ahead).
> This document records the **verified** state of the codebase — not what the README claims.
> Every finding below is backed by file/class/line evidence in the repo.

---

## 1. Environment

| Item | Value |
|---|---|
| OS | Windows 10, cmd.exe |
| PHP | 8.5.5 (`C:\php855\php.exe`) |
| Composer | 2.9.5 |
| Framework | Laravel 10 (`laravel/framework ^10.0`, PHP `^8.1`) |
| Test runner | PHPUnit 10 via `php artisan test` |
| Test DB | `pardisa2_bot_platform_test` (MySQL), `QUEUE_CONNECTION=sync`, `CACHE_DRIVER=array` |

### Baseline test run (before any changes)

```
php artisan test --compact
→ 399 passed, 2 skipped, 2 deprecated, 2205 assertions, 78.06s
```

Exit code is 1 **only** because of PHP 8.5 deprecation noise (implicit nullable
parameter warnings from vendor packages) and one PHPUnit warning
("Class QuranTest does not extend TestCase"). Functionally the baseline is
**green**; this run is the regression reference for all later phases.

---

## 2. High-level architecture

This repo is a "bot mother" platform: it registers child bots on Iranian
messengers, receives their webhooks, and publishes content to channels/groups
on one or more messengers from a single source.

```
Messenger webhook ──► routes/api.php ──► BotMotherController / webhook handlers
                                              │
                                              ▼
   App\Helpers\BotHelper (1622 lines) ── central dispatcher (handleRequestBotMother)
                                              │
                                              ▼
   App\Services\ChannelPosterBotServiceImpl  (destination CRUD, tags, queue, slots)
   App\Services\ContentSync\ContentSyncService (cross-messenger sync engine, 1251 lines)
   App\Services\ChannelPosterQueuePublishService (idempotent queue publishing)
                                              │
                                              ▼
   App\Interfaces\Services\ChannelPosterPublisher (interface)
   App\Interfaces\Services\ChannelPosterPublisherFactory (interface)
   App\Services\TelegramChannelPosterPublisherFactory  → picks publisher by origin
   App\Services\TelegramChannelPosterPublisher         (publish to bale/telegram/eitaa/soroush)
   App\Services\ChannelPosterMediaSender               (multipart media upload per platform)
```

Key packages:

- `saber13812002/telegram-bale-bot-php` (fork of eleirbag89/telegrambotphp) —
  one `Telegram` SDK class used for **both** Telegram (`new Telegram($token)`)
  and Bale (`new Telegram($token, 'bale')`).
- `saber13812002/gap-sdp-api` — separate SDK for Gap (`Gap\SDP\Api`).
- Eitaa has **no SDK** — it is handled with raw HTTP in
  `App\Helpers\BotHelper::call_eitaa_api()` / `sendMessageEitaaSupport()`.

---

## 3. Per-platform implementation status (VERIFIED in code)

| Platform | SDK / transport | Bot registration | Webhook in | Private msg | Channel publish (text) | Channel publish (media) | Evidence |
|---|---|---|---|---|---|---|---|
| **Telegram** | vendor SDK | ✅ `BotRegistrationService` | ✅ | ✅ | ✅ `TelegramChannelPublisher::publish()` → `$client->sendMessage()` | ✅ multipart `https://api.telegram.org/bot{token}/send*` | `BotRegistrationService.php:37-121`, `ChannelPosterMediaSender.php:140-145` |
| **Bale** | vendor SDK (`new Telegram($token,'bale')`) | ✅ `BotRegistrationService` | ✅ | ✅ | ✅ same path as Telegram | ✅ multipart `https://tapi.bale.ai/bot{token}/send*` | `BotRegistrationService.php:51`, `ChannelPosterMediaSender.php:133-138` |
| **Eitaa** | raw HTTP (no SDK) | ❌ not in registration flow | partial (outbound only) | ✅ via `BotHelper::sendMessageByChatIdEitaa` | ✅ `BotHelper::sendMessageEitaaSupport` | ✅ multipart `https://eitaayar.ir/api/{token}/sendFile` | `BotHelper.php:284-352`, `ChannelPosterMediaSender.php:116-131` |
| **Gap** | separate SDK | partial (webhook + posting via `sendGap4InlineMessage`, `sendPhotoGap`) | ✅ | ✅ | ✅ via SDK | ✅ `BotHelper::sendPhotoGap` | `BotHelper.php:578-604, 1346-1349` |
| **Soroush** | **none** | ❌ | ❌ | ❌ | ❌ **stubbed — always SKIPPED** | ❌ | see §4 |
| **Rubika** | **none** | ❌ | ❌ | ❌ | ❌ **absent** | ❌ | 0 matches for `rubika` in `app/` |

### 3.1 Soroush — what actually exists (stub only)

- `ChannelPosterDestination::PLATFORM_SOROUSH = 'soroush'` constant exists.
- Platform validation lists include `soroush`:
  - `app/Http/Requests/BotRequest.php` → `Rule::in(['bale','telegram','soroosh','gap'])`
    (note: the string used there is `soroosh`, **not** `soroush` — inconsistency).
  - `AdminChannelPosterController` → `in:bale,telegram,eitaa,soroush`.
- **But every real publish path skips it:**
  - `ContentSyncService::publishImmediate()` and `retryFailed()`:
    ```php
    if ($destination->platform === ChannelPosterDestination::PLATFORM_SOROUSH) {
        $version = $this->upsertVersion($content, $destination, [
            'publish_status' => ContentSyncVersion::STATUS_SKIPPED,
            'last_error' => trans('bot.channel_sync_soroush_not_ready'),
        ]);
        continue; // skipped, never published
    }
    ```
  - `TelegramChannelPosterPublisher::publish()` excludes soroush from media
    upload (`$platform !== PLATFORM_SOROUSH`) and treats it as text-only.
  - `PostUrlBuilder` returns `['url' => null, 'status' => 'unverified']` for soroush
    ("هنوز نمونه واقعی لینک سروش نداریم").
  - `PostUrlParser` recognizes domains `soroush.chat`, `splus.ir` but marks them unverified.
- `app/Services/SPlusChatService.php` is **not** a Soroush bot API. It is the
  **S+ BUI pattern-message** service: sends a pre-registered pattern message
  (`pattern_id` 341) via `POST {SPLUS_BASE_URL}/v2/messages/send` with
  `SPLUS_BOT_TOKEN`. It is used by `RssPostItemTranslationController` when
  `$channel == "splus"`. It cannot register bots, cannot set webhooks, and
  cannot send arbitrary channel posts. It must not be conflated with a Soroush
  provider.

**Conclusion: Soroush = "NOT_IMPLEMENTED" (stub/skip). Any "Soroush support"
claim in the README is not backed by publishable code.**

### 3.2 Rubika — completely absent

- 0 grep matches for `rubika` / `rub` bot endpoints in `app/`, `config/`, `routes/`.
- No platform constant, no env vars, no token validation, no publisher branch.

---

## 4. Content sync engine (existing capability to generalize)

`App\Services\ContentSync\ContentSyncService` (1251 lines) is the core engine:

- `registerSource(Bot, rawUrl, adminChatId, origin)` — registers a source channel
  (URL parsed by `PostUrlParser`), returns content row.
- `attachText(content, text)` / `publishImmediate(...)` — immediate publish to
  all resolved destinations.
- `enqueueForTag(...)` — schedule via `ChannelPosterQueue`; actual publishing in
  `ChannelPosterQueuePublishService::publishItem()` which is **idempotent**
  (skips destinations already marked successful in `ChannelPosterPublishLog`).
- Dedup: unique constraints on source/destination association tables;
  source channel itself is excluded from being a destination
  (`sourceChannelVerdict()`).
- Per-destination status rows: `ContentSyncVersion` (`publish_status`,
  `last_error`, `message_id`, `post_url`).
- Attribution suffixes: signed / link_only / none via
  `buildAttributionSuffix()` + `ChannelPosterTagSetting`.
- Long text: `TextChunker` splits; `publishChunks()` publishes each chunk.
- Queue slots: `ChannelPosterBotServiceImpl::getNextSlot()` — 2-hour slots,
  quiet hours 02:00–09:00 Tehran.
- **Platform switch is hardcoded in the engine** (the Soroush skip is an `if`
  on platform). This is exactly what Phase 5 must make config-driven.

Supporting models: `ContentSyncContent`, `ContentSyncVersion`,
`ChannelPosterQueue`, `ChannelPosterPublishLog`, `ChannelPosterDestination`,
`ChannelPosterTagSetting`.

---

## 5. Database (migrations relevant to this mission)

All additive/nullable — the project's established backward-compat pattern is
"add nullable column, never alter existing":

| Migration | Table / change |
|---|---|
| `2026_08_16_182200_create_channel_poster_destinations_table.php` | destinations (platform, channel_chat_id, token, tag…) |
| `2026_08_16_201500_add_tag_to_channel_poster_destinations.php` | `tag` |
| `2026_09_09_150000_add_channel_link_to_channel_poster_destinations_table.php` | `channel_link`, `channel_title`, `channel_username` |
| `2026_09_09_150001_create_channel_poster_tag_settings_table.php` | per-tag attribution settings |
| `2026_09_09_150002_create_channel_poster_queue_table.php` | queue items |
| `2026_09_09_150003_create_channel_poster_publish_logs_table.php` | publish logs (idempotency) |
| `2026_10_15_100000_create_content_sync_tables.php` | content sync core tables |
| `2026_10_16_200000_add_content_sync_phase4.php` | phase 4 columns |
| `2026_10_16_210000_add_media_path...` | `media_path` |
| `2026_10_17_100000_add_file_name_and_mime...` | `file_name`, `mime_type` |

`ChannelPosterDestination.platform` is a plain string column — **no DB migration
is strictly required to add `rubika`** (constant + validation + code only), but a
seeder/checker migration will be added in Phase 6 for consistency.

`Bot` model (`app/Models/Bot.php`) has per-platform column families
(`bale_bot_token`, `bale_bot_name`, `bale_owner_chat_id`, `bale_webhook_is_set`,
`telegram_*`, …) — Soroush/Rubika columns will follow the same pattern.

---

## 6. Config & environment

- `config/bot.php` — `base_url` for bale/telegram/gap only; soroosh commented
  out; `tokens.bale.robat = env('ROBAT_BALE_TOKEN_TEST')`.
- `config/services.php` — `rocketchat` and `splus` (BUI) endpoints.
- `.env.example` (217 lines) — has `BOT_MOTHER_TOKEN_BALE/TELEGRAM/GAP`,
  per-feature bot tokens, `SPLUS_BOT_TOKEN` (BUI pattern messaging).
  **No Rubika vars, no Soroush-bot vars.** New vars will be added in Phase 3
  with empty/example values only (no real secrets).

Token validation: `TokenHelper::isToken($text, $type)` with per-platform regexes
(bale `^[0-9]{7,10}:[a-zA-Z0-9_-]{30,50}$`, telegram similar).
`TokenHelper::redactTokens()` masks tokens in all logs/errors — every new code
path must route errors through it.

---

## 7. Testing infrastructure

- `tests/Feature/ContentSyncFlowTest.php` — sync engine end-to-end with
  `FakeChannelPosterPublisher(Factory)` (`tests/Fakes/`).
- `tests/Feature/ChannelPosterMediaPublishTest.php` — media upload with
  `Http::fake`.
- `tests/Feature/ChannelPosterQueueSendTest.php` — queue idempotency.
- `tests/Feature/ChannelPosterBotWebhookTest.php` — webhook → destination flow.
- Some suites use SQLite traits (`UsesChannelPosterSqlite`); most use the MySQL
  test DB. HTTP is always faked — **no live calls in CI-style tests**.
- `tests/TestCase.php` — standard Laravel trait setup.

Pattern to reuse for new providers: implement `ChannelPosterPublisher` +
factory, fake the factory in feature tests, fake HTTP in unit tests.

---

## 8. Risks & open questions (going into Phase 1–3)

1. **No public bot-API guarantee for Soroush/Rubika** — the mission forbids
   guessing endpoints. Phase 2 research must only assert what official docs
   support; anything unverified must be labeled `BLOCKED`/`UNSUPPORTED` and the
   code must degrade gracefully (skip with a clear status, never fake success).
2. **`soroosh` vs `soroush` string mismatch** in `BotRequest` validation vs
   `ChannelPosterDestination::PLATFORM_SOROUSH`. Phase 4 will normalize.
3. **Eitaa is outbound-only** (no webhook ingestion in the registration flow);
   it is a *destination* platform, not a source-eligible one. Soroush/Rubika
   may share this characteristic depending on Phase 2 findings.
4. The vendor `Telegram` SDK is the single choke point for Telegram/Bale.
   Soroush (if a bot API exists) will likely need raw HTTP like Eitaa.
5. PHP 8.5 deprecation noise makes `php artisan test` exit non-zero even on
   green; reports must count "passed/skipped/deprecated", not exit code.

---

## 9. Plan for subsequent phases

- **Phase 1** — capability matrix per platform (`docs/features/messenger-platform-capability-matrix.md`).
- **Phase 2** — official API research for Soroush Plus & Rubika
  (`docs/features/platform-api-research.md`) — evidence-only, no guessing.
- **Phase 3** — implement `SoroushPublisher` / `RubikaPublisher` behind the
  existing `ChannelPosterPublisher` interface + factory; platform constants;
  validation lists; `.env.example`.
- **Phase 4** — shared capabilities (registration, webhooks, token validation).
- **Phase 5** — generalize the sync engine: config-driven source/destination
  platform rules, dedup, loop prevention, per-destination retry.
- **Phase 6** — DB review + additive migrations.
- **Phase 7** — unit + mock-integration + feature tests; run full suite.
- **Phase 8** — `IMPLEMENTATION_CHECKPOINT.md` at repo root after each phase.
- **Phase 9** — Release Candidate: `docs/release/RELEASE_CANDIDATE.md` + final
  Persian report (A–I).
