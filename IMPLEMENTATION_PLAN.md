# Personal Growth Engine — Implementation Plan

**Status:** Planning only. No production code, migrations, routes, catalog edits, dependency installs, or refactors were made while writing this document.

**Product working name:** رشدیار / Growth Companion  
**Technical id:** `webhook-growth-companion`  
**Core loop:** Question → Response → Reflection → Review → Adjustment → Next Question

---

## 0. How to read this document

This plan has three layers. Layer 3 is a gate: architecture lock-in is invalid without it.

| Layer | What it is | Source of truth |
| ----- | ---------- | --------------- |
| 1. Product | What we want to build | Product requirements |
| 2. Architecture | Smallest safe change in *this* repo | Evidence + product |
| 3. Codebase Evidence | What the code actually does | Traced call graphs |

Planning rule used: **READ → TRACE → VERIFY → DOCUMENT → PLAN**.

The question this document answers:

> Given the code that already exists and actually works in this repository, what is the smallest, safest, most maintainable change required to add Growth Companion?

Do not treat this as a greenfield Laravel app.

---

# Layer 1 — Product Plan

## Product intent

Build a Telegram/Bale bot whose core is a **Personal Growth & Reflection Engine**, not a “محاسبه نفس bot” and not a generic chatbot.

Domain must not be hard-coded. Spirituality is one **template**. The same engine can serve health, family, work, study, finance, relationships, habits, or a custom goal.

Philosophy:

- Not a dry task manager.
- Not an all-purpose chat agent.
- Combination of reflection, self-awareness, habit building, goal tracking, periodic review, personalized questioning, AI-assisted coaching (later), and lightweight accountability.
- User should feel they built the bot for themselves.
- **Simple by default, powerful when needed.**
- Complexity is progressive. First session is two questions, not a settings panel.
- AI is a **Reflection Companion**, not a judge, preacher, therapist, doctor, or life manager.
- The system should increase self-awareness, not dependency.

### Modes

**Simple Mode:** receive one question → answer → done. No customization nags.

**Advanced Mode:** change questions, frequency, tone, pause, custom questions, extra programs. Hidden until chosen.

### Programs

A user may have multiple Programs so spiritual, health, family, and work do not share one stream.

Hierarchy (full product, not all in MVP):

```text
User → Program → Area → Goal → Habit / Metric → Question → Variant → Response → Reflection
```

MVP flattens this to:

```text
User → Program → Question → Variant → Response
```

### Phases (product)

- **Phase 1 MVP:** onboarding, Simple Mode, one Program, question + variants, daily schedule, response, pause / frequency / delete.
- **Phase 2:** multiple Programs, weekly review, custom question, custom frequency, AI variant generation.
- **Phase 3:** adaptive follow-ups, monthly review, semantic anti-repeat, program builder, metrics/habits.
- **Phase 4 later:** template marketplace, multi-bot product split, heavy analytics.

Do not build now: social network, public chat, streak-as-product, autonomous agent, recommendation engine, extra dashboards.

---

# Layer 3 — Codebase Evidence Report

This section is the gate. Gap Analysis and architecture follow it.

## A. Confirmed Existing Architecture

**Confidence: HIGH** unless noted.

| Fact | Evidence |
| ---- | -------- |
| PHP `^8.1`, Laravel `^10.0` | `composer.json` |
| Messenger SDK: `saber13812002/telegram-bale-bot-php` `^101.4` (`Telegram` class; second arg `'bale'` / `'eitaa'`) | `composer.json`; `PrayerBotController::createBotInstance()` |
| Gap: `saber13812002/gap-sdp-api` | `composer.json`; Quran Gap route only |
| No OpenAI / Anthropic / Gemini composer package | `composer.json` require list |
| HTTP client available: Guzzle `^7.2` | `composer.json`; used by `OneApiTranslationService` |
| Queue default is `sync` | `config/queue.php` → `env('QUEUE_CONNECTION', 'sync')` |
| App timezone `UTC`; locale `fa` | `config/app.php` |
| 15 translation locales for `bot.php` | `lang/{fa,en,ar-IQ,az,bs,de-DE,es,fr,he,pt-BR,pt-PT,ru,tr,ur,zh-CN}/bot.php` |
| API routes live in `routes/api.php` under prefix `api` | `RouteServiceProvider::boot()` |
| Effective Channel Poster URL is `POST /api/webhook-channel-poster` | `routes/api.php` line 138 + prefix |
| New bot types are rows in `webhook_endpoints`, listed by Bot Mother via `WebhookEndpointHelper::getAvailableEndpoints()` | `app/Helpers/WebhookEndpointHelper.php` |
| DI bindings in `AppServiceProvider` | e.g. `PrayerBotService` → `PrayerBotServiceImpl`, `ChannelPosterBotService` → `ChannelPosterBotServiceImpl` |
| Scheduler is Laravel `Kernel::schedule()` + server crontab `php artisan schedule:run` | `app/Console/Kernel.php`; `README_LEGACY.md` Cron Jobs |
| Tests: PHPUnit 10, not Pest | `composer.json`; `phpunit.xml` |
| `phpunit.xml` points at MySQL `pardisa2_bot_platform_test`, not SQLite | `phpunit.xml` |
| Newest bot test pattern forces SQLite `:memory:` per test class | `tests/UsesChannelPosterSqlite.php` |
| Catalog currently claims **36 bot types** | `README.md` |

There is **no** `Bot::createBotInstance()` method. Token resolution is **private on each controller**, with three live variants:

1. `ChannelPosterBotController::resolveBotAndToken()` — token query → `Bot::where(*_bot_token)` else `Bot::find($botId)`
2. `PrayerBotController::createBotInstance()` — token query → `Bot::find` → **env fallback** `PRAYER_BOT_TOKEN_*`
3. `WeatherController::getToken()` then `new Telegram($token, $type)` — token query → `Bot::find` → env fallback `BOT_WEATHER_TOKEN_*`

Interfaces live in `app/Interfaces/Services/` and `app/Interfaces/Repositories/`, not inside `app/Services/`.

---

## B. Confirmed Reusable Components

Actual classes/methods Growth Companion should attach to. **Do not rewrite these** unless a later audit proves extension is impossible.

| Component | Class::method | Role | Classification |
| --------- | ------------- | ---- | -------------- |
| Route registration | `routes/api.php` + `RouteServiceProvider` prefix `api` | Webhook entry | ACTIVE |
| Endpoint catalog | `WebhookEndpointHelper::getAvailableEndpoints()`, `createWebhookUrl()` | Bot Mother listing | ACTIVE |
| Default importer | `WebhookEndpointDefaultImporter` | Cache/import of endpoints | ACTIVE |
| Bot row | `Bot::find()`, `Bot::where('bale_bot_token'/'telegram_bot_token')` | Token + language + owner | ACTIVE |
| End user | `BotUsers` model | Messenger identity | ACTIVE |
| User lookup (safe) | `ChannelPosterBotController::resolveBotUser()` | `chat_id` + `origin` + **`bot_id`** | ACTIVE — **prefer this** |
| User lookup (legacy) | `BotUsers::firstOrNew($chatId, $botMotherId, $origin)` | `chat_id` + `origin` **only**; `bot_id` not in WHERE | ACTIVE but **unsafe to copy** for multi-instance bots |
| Conversation state | `BotUserState::create()`, `scopeActive()`, `getData()`, `setData()` | Wizard steps | ACTIVE |
| State helpers (Prayer) | `PrayerBotServiceImpl::setState/getState/clearState` | Estimate/email wizards | ACTIVE |
| Keyboards + send | `BotHelper::sendMessage`, `sendMessageByChatId`, `sendKeyboardMessage`, `sendKeyboardMessageToChatId` | Telegram/Bale send | ACTIVE |
| SDK send | `Telegram::sendMessage()`, `buildInlineKeyBoardButton()`, `buildKeyBoard()`, `answerCallbackQuery()` | Direct API | ACTIVE |
| i18n | `trans('bot.*')`, `app()->setLocale($bot->language_code)` | 15 locales | ACTIVE |
| Scheduler host | `Console\Kernel::schedule()` + `withoutOverlapping()` + `onOneServer()` | Cron | ACTIVE |
| Scheduled Telegram delivery | `ScheduleContentDelivery::handle()` → `ContentDeliveryServiceImpl::deliverNextInCategory()` → `BotHelper::sendMessageByChatId()` | Closest live per-user messenger dispatch | ACTIVE |
| Test fake publisher | `Tests\Fakes\FakeChannelPosterPublisher` + bind factory in test | Webhook tests without network | ACTIVE |
| SQLite test trait | `Tests\UsesChannelPosterSqlite` | Isolate from MySQL phpunit.xml | ACTIVE |
| Feature docs pattern | `docs/features/CHANNEL_POSTER_BOT.md` | New bot documentation | ACTIVE |
| Seeder pattern | `ChannelPosterWebhookEndpointSeeder` | `endpoint_id`, `route` starting `api/`, `requires_token` true | ACTIVE |

---

## C. Existing Working Flows (call graphs)

### C1. Channel Poster — private message /start (ACTIVE)

Reference for: webhook shape, token resolution, `BotUserState`, inline callbacks `cp:`, tests.

```text
Telegram/Bale Update (JSON body)
 ↓
RouteServiceProvider prefix api
 ↓
routes/api.php  POST /webhook-channel-poster
 ↓
ChannelPosterBotController::webhook(Request)
 ↓
ChannelPosterBotController::resolveBotAndToken($request, $type)
    → Bot::where('bale_bot_token'|'telegram_bot_token', $token)->first()
    → else Bot::find($botId)
 ↓
endpoint_id must equal ChannelPosterBotController::ENDPOINT_ID ('webhook-channel-poster')
 ↓
app()->setLocale($botItem->language_code)   if set
 ↓
TelegramChannelPosterPublisherFactory::make($token, $type)
    → new TelegramChannelPosterPublisher(new Telegram($token, $origin), $origin)
 ↓
ChannelPosterBotController::extractUpdate()
 ↓
callback_query? → handleCallbackQuery()
 else private message → handlePrivateMessage()
 ↓
ChannelPosterBotServiceImpl::isOwner($botItem, $chatId, $type)
    reads bots.bale_owner_chat_id / telegram_owner_chat_id
    if empty → claimOwnerIfEmpty() writes owner chat id
    if not owner → TelegramChannelPosterPublisher::sendPrivateMessage() + return
 ↓
text /start → clearState() + handleStart()
 ↓
ChannelPosterBotController::resolveBotUser()
    BotUsers::where(chat_id, origin, bot_id=botItem.id)->first()
    else BotUsers::create(...)
 ↓
ChannelPosterBotController::setState()
    deletes prior BotUserState for that user+motherId
    BotUserState::create(state=cp_awaiting_bale_forward, expires_at=now()+6h)
 ↓
TelegramChannelPosterPublisher::sendPrivateMessage($chatId, trans(...), $inlineKeyboardRows)
 ↓
Telegram::sendMessage(['chat_id','text','reply_markup'])
 ↓
Tables: bots (read/update owner), bot_users (insert), bot_user_states (insert)
Scheduler: none
External API: Telegram/Bale sendMessage
```

Callback namespace: `cp:add`, `cp:addtag:`, `cp:plat:`, etc. **No `gc:` collision.**

**Important:** this bot is **owner-only**. Copying `isOwner()` into Growth Companion would block end users. Reuse webhook/token/state/test pattern, not the ownership gate.

### C2. Prayer Bot — record a number as rakats (ACTIVE)

Reference for: end-user conversational loop, `trans()`, `BotUserState` via service, repository write.

```text
Telegram/Bale Update
 ↓
routes/api.php  POST /webhook-prayer-bot
 ↓
PrayerBotController::webhook(Request)   returns int 200/500 (not JsonResponse)
 ↓
requires query origin; bot_mother_id default 1
 ↓
PrayerBotController::createBotInstance($request, $type)
    token from query → else Bot::find($bot_id) → else env PRAYER_BOT_TOKEN_*
    return new Telegram($token, 'bale') or new Telegram($token)
 ↓
Telegram::ChatID(), Telegram::Text()
 ↓
BotUsers::firstOrNew($chatId, $botMotherId, $type)
    WHERE chat_id + origin ONLY (bot_id stored but not used in lookup)
 ↓
callback_query → handleCallbackQuery()
    prefixes: prayer_quick_*, prayer_*, email_*, help_*, estimate_*
    Telegram::answerCallbackQuery()
 else text → handleTextMessage()
 ↓
/start → PrayerBotController::handleStart()
    BotHelper::sendMessage($bot, trans('bot.welcome_prayer_bot') ...)
 ↓
numeric text → PrayerHelper::detectNumberInText()
 ↓
PrayerBotController::handleRecordPrayer()
 ↓
PrayerBotServiceImpl::recordPrayer()
    BotUsers::firstOrNew()
    PrayerRecordRepository::create()  → table prayer_records
 ↓
Telegram::sendMessage() with inline_keyboard (quick 2/34/44)
    or BotHelper::sendMessage() on error
 ↓
Tables: bot_users, prayer_records, bot_user_states (estimate/email wizards only)
Scheduler: none on this path
```

Estimate wizard uses `PrayerBotServiceImpl::setState()` → `BotUserState` with 10-minute expiry (not 6 hours).

**Do not extend Prayer Bot into Growth Companion.** It is a domain-specific qadha tracker. Reuse patterns only.

### C3. Weather webhook + scheduled alerts (webhook ACTIVE; scheduled send PARTIALLY USED)

Webhook path (live):

```text
Update
 ↓
routes/api.php  POST /webhook-weather
 ↓
WeatherController::index(BotRequest)
 ↓
WeatherController::setLocale() → Bot::find($botId) → App::setLocale(language_code)
 ↓
WeatherController::getToken() → new Telegram($token, $type)
 ↓
BotUsers::firstOrNew($chatId, $botMotherId, $type)
 ↓
callback / location / command
 ↓
/alert_add → WeatherAlertService (creates weather_alerts row: time_hour, is_active)
 ↓
BotHelper::sendMessage()
 ↓
LogHelper::log($request, $type, $bot)   ← project rules warn this can TypeError; Prayer/Channel Poster avoid it
```

Scheduled path (Kernel hourly):

```text
Console\Kernel::schedule()
 ↓
$schedule->job(new CheckWeatherAlertsJob)->hourly()->withoutOverlapping()->onOneServer()
 ↓
CheckWeatherAlertsJob::handle()
 ↓
WeatherAlert::active()->get()
 ↓
CheckWeatherAlertsJob::checkAlert()
    $alert->botUser  (FK to bot_users)
    skip if no location
    WeatherTomorrowApiRepository::call(lat, lon)   EXTERNAL Tomorrow.io
    if time_hour set: compare to now()->format('H') in APP timezone UTC
    WeatherAlertService::compareWeather()
 ↓
CheckWeatherAlertsJob::triggerAlert()
    UPDATE weather_alerts.last_triggered_at
    Log::info
    *** does NOT call BotHelper or Telegram::sendMessage ***
    comment in source: "باید از BotHelper استفاده شود اما نیاز به bot instance داریم"
```

**Hypothesis overturned:** Weather alerts are **not** a working per-user Telegram reminder. They persist rules and log triggers. Do not build Growth dispatch on this job.

Timezone: `now()` is UTC (`config/app.php`). Weather forecast windows hardcode `Asia/Tehran` in `WeatherTomorrowApiServiceImpl` only. **No per-user timezone column.**

### C4. Content hourly delivery — closest live scheduled messenger send (ACTIVE)

```text
Console\Kernel::schedule()
 ↓
$schedule->command(ScheduleContentDelivery::class)->hourly()->withoutOverlapping()->onOneServer()
 ↓
ScheduleContentDelivery::handle()   signature content:deliver-hourly
 ↓
Bot::whereIn('endpoint_id', config('content_bots.content_endpoint_ids'))->get()
 ↓
new Telegram($token, 'bale'|default)
 ↓
ContentCategory::where(bot_id, is_active)
 ↓
ContentUserProgress::where(category_id, bot_id, last_position>0)->with('botUser')
 ↓
skip if progress.updated_at > now()-1 hour   (throttle)
 ↓
ContentQueueServiceImpl::getNextItemForUser()
 ↓
ContentDeliveryServiceImpl::deliverNextInCategory($bot, $botUser, $item, $botId, $origin)
 ↓
BotHelper::sendMessageByChatId($bot, $chatId, ...)
 Telegram::sendAudio / sendMessage
 inline callbacks bl:cat:, bl:note:, bl:question:
 ↓
ContentQueueServiceImpl::advanceProgress()
 ↓
Tables: bots, content_categories, content_items, content_user_progress, library_user_books (best-effort)
Queue: command runs inline if QUEUE_CONNECTION=sync
```

This is the **reference scheduled delivery** for `growth:dispatch-due`: load bot token from `bots`, construct `Telegram`, send by `chat_id`, throttle, log failures per user, continue the loop.

### C5. Prayer weekly report — EMAIL, not messenger (ACTIVE, wrong channel)

```text
Kernel::schedule()  every 10 / 30 / 60 minutes
 ↓
prayer:send-weekly-reports --batch-size --interval
 ↓
SendPrayerWeeklyReports::handle()
 ↓
EmailThresholdService::canSendEmail('daily')
 ↓
EmailSchedulingService::getEligibleUsers($batchSize)
 ↓
SendPrayerWeeklyReports::processUser()
    PrayerBotServiceImpl::getWeeklyReport()
    EmailReportQueue insert
    SendPrayerReportEmailJob::dispatch()   tries=3, backoff=60s
 ↓
EmailService::sendWeeklyReportEmail()
```

Useful later for **weekly review content**, not for daily question delivery. Delivery channel is email.

---

## D. Existing Technical Debt (do not fix in this feature)

| ID | Location | Evidence | Severity | Growth depends? |
| -- | -------- | -------- | -------- | --------------- |
| TD1 | `BotUsers::firstOrNew()` | Lookup ignores `bot_id`; comment shows it was considered | Medium | No — use Channel Poster `resolveBotUser` pattern |
| TD2 | `CheckWeatherAlertsJob::triggerAlert()` | No Telegram send | Medium | No — do not reuse this job |
| TD3 | `WeatherController` + `LogHelper::log($request, $type, $bot)` | `.cursorrules` forbids this; Prayer replaced it with `Log::info` | Medium | No — follow Prayer/Channel Poster logging |
| TD4 | `PrayerBotController::webhook()` returns `int` / HTTP 500 | Channel Poster always JSON 200 so platforms do not retry-storm | Low | No — follow Channel Poster JsonResponse 200 |
| TD5 | Token resolution duplicated in every controller | Three slightly different methods | Low | Copy one private method; do not extract a global helper unless needed |
| TD6 | `phpunit.xml` uses MySQL test DB | Channel Poster/Observability tests override SQLite | Medium | Copy SQLite trait; do not change global phpunit.xml in this feature |
| TD7 | `BotUserState` duplicated get/set in 5 controllers | ChannelPoster, Prayer service, Poem, BookPixel, MpContact | Low | Copy Channel Poster private methods or Prayer service methods locally |
| TD8 | Keyboard helpers duplicated | Fat `BotHelper` + per-controller inline arrays | Low | Use `BotHelper` + controller-local keyboards |
| TD9 | `PROJECT_RULES.md` missing at repo root | Referenced by `.cursorrules`; rules live in `.cursorrules` | Low | N/A |
| TD10 | Poem route double `/api/` prefix (known) | Architecture scan | Low | N/A |
| TD11 | Queue default `sync` | Jobs inside `schedule:run` block the cron | Medium | Document; Growth command should batch with timeout budget |
| TD12 | Mission “AI” is copy-paste prompts | `Prompt` + `AiLlm.url`; curl in MissionBot is `answerCallbackQuery` to tapi.bale.ai / api.telegram.org | Low | Do not reuse Prompt/AiLlm tables |

Do not refactor these during Growth Companion implementation.

---

## E. Confirmed Missing Capabilities

After inspection, these are actually absent:

- Personal growth / program / reflection / habit / metric domain tables
- Question identity + variants + anti-repeat
- Per-user timezone column
- Dynamic per-question schedule with quiet hours and interaction budget
- Working scheduled **Telegram** reminder that is generic (content delivery is hourly library audio, not questions)
- Generative LLM HTTP client (no `chat/completions`, embeddings, OpenAI/Anthropic/Gemini URLs in `app/`)
- Versioned LLM system-prompt files (`prompts/` directory does not exist)
- Privacy: export my data / delete my data / AI consent
- Semantic similarity / vector store (`laravel-fulltext` is MySQL fulltext for Quran)
- `gc:` callback namespace (free)

**Present but not the right abstraction:**

- `psychology_test_questions` — scored Likert items, not reflection concepts
- `prompts` + `ai_llms` — mission copy-paste catalog
- `weather_alerts.time_hour` — threshold alerts, no timezone, send incomplete
- `bot_users.settings` JSON — ad-hoc prefs (Quran, weather email, psychology answers); too unstructured for Programs
- Prayer weekly email — review analog, wrong channel

---

## F. Architecture Conflicts (proposed design vs repo)

| Proposed | Conflict | Resolution |
| -------- | -------- | ---------- |
| Reuse `BotUsers::firstOrNew` | Not scoped by `bot_id`; two Growth bots would share one user row | Follow `ChannelPosterBotController::resolveBotUser()` |
| Reuse Weather alerts as scheduler | `triggerAlert()` does not send messages; hour is UTC | New `growth:dispatch-due` in **existing** `Kernel.php` |
| Reuse `ChannelPosterPublisher` | Tied to owner-only channel posting | Use `BotHelper::sendMessageByChatId` + Telegram SDK; copy **test fake** idea if useful |
| Reuse `Prompt` / `AiLlm` for LLM | Never calls an LLM API | New prompt files + `LlmProvider` in Phase 2; leave mission tables alone |
| Reuse Psychology Test questions | Scored tests, answers in `bot_users.settings` | New `growth_questions` / variants |
| Extend Prayer Bot | Domain-hard-coded qadha | New endpoint; do not add growth into `PrayerBotController` |
| Owner sees user data | Channel Poster is owner-only; Growth is personal | Default: owner cannot read reflections |
| `createBotInstance` on `Bot` | Does not exist | Private controller method like Channel Poster / Prayer |
| Global SQLite phpunit | `phpunit.xml` is MySQL | Per-feature SQLite trait like Channel Poster |

None of these require rewriting Bot Mother, `Bot`, or `BotUsers`. They require **not copying the wrong method**.

---

## G. Recommended Reuse Map

| Proposed Component | Existing Reference | Strategy | Reason |
| ------------------ | ------------------ | -------- | ------ |
| Webhook Controller | `ChannelPosterBotController::webhook` | Adapt | JsonResponse 200, extractUpdate, locale, try/catch. Drop `isOwner` gate. |
| Bot resolution / token | `ChannelPosterBotController::resolveBotAndToken` | Reuse pattern | Query token then `Bot` row. Skip env fallback (Prayer/Weather) unless needed. |
| Endpoint registration | `ChannelPosterWebhookEndpointSeeder` + `WebhookEndpointDefaultImporter` | Copy pattern | `requires_token=true`, route `api/webhook-growth-companion` |
| Bot user handling | `ChannelPosterBotController::resolveBotUser` | Reuse pattern | Scoped by `bot_id`. Do **not** use `BotUsers::firstOrNew`. |
| Conversation state | `ChannelPosterBotController::getState/setState/clearState` + `BotUserState` | Reuse | 6h expiry for onboarding; Prayer’s 10 min is too short. |
| Keyboard UX | `BotHelper::sendKeyboardMessageToChatId` + `Telegram::buildInlineKeyBoardButton` | Reuse | Inline for actions (`gc:`), reply keyboard optional for Simple Mode. |
| Callback namespace | `cp:`, `bl:`, `mpc:` | New prefix `gc:` | No collision found. Keep payloads short (Telegram 64-byte limit). |
| Localization | `lang/*/book_library.php` + `ChannelPosterBotController` setLocale | Copy pattern | Dedicated `lang/*/growth_companion.php` (15 locales), not only `bot.php`. |
| Scheduler host | `Kernel::schedule()` `withoutOverlapping` `onOneServer` | Extend | Add one command. Do not invent a second scheduler. |
| Per-user dispatch | `ScheduleContentDelivery::handle` + `ContentDeliveryServiceImpl::deliverNextInCategory` | Adapt | Iterate due rows, load bot token, `BotHelper::sendMessageByChatId`, per-user try/catch. |
| Weekly review later | `PrayerBotServiceImpl::getWeeklyReport` + email job | Adapt later | Reuse *idea* of aggregating records; send in-chat, not email, in Phase 2. |
| Settings | `bot_users.settings` JSON | Isolate | Keep onboarding flags in `BotUserState`; durable prefs in `growth_profiles`, not settings JSON. |
| Tests | `ChannelPosterBotWebhookTest` + `UsesChannelPosterSqlite` + Fake publisher | Copy pattern | SQLite memory, `postJson` webhook, fake send. |
| Logging | `ChannelPosterBotController` / `PrayerBotController` `Log::info` | Reuse | Do not call `LogHelper::log($request, $type, $bot)`. |
| AI catalog | `AiLlm`, `Prompt` | Do not reuse | Wrong semantics. Phase 2 new `LlmProvider`. |
| Psychology questions | `PsychologyTestQuestion` | Do not reuse | Scored test, not reflection concepts. |
| Privacy / delete-data | none | New | Commands on `growth_*` only. |
| DI bind | `AppServiceProvider` Channel Poster binds | Copy | Interface in `app/Interfaces/Services`. |

Principle: **Reuse → Adapt → Extend → Refactor → New only when necessary.**

---

## H. Confidence Level

| Conclusion | Confidence |
| ---------- | ---------- |
| Laravel 10 bot-generator; new type = webhook endpoint | HIGH |
| No generative LLM HTTP in `app/` | HIGH |
| `BotUsers::firstOrNew` is not bot-scoped | HIGH |
| Channel Poster is owner-only | HIGH |
| Weather scheduled job does not send Telegram | HIGH |
| Content hourly delivery does send Telegram | HIGH |
| Prayer weekly report is email | HIGH |
| `gc:` callback prefix unused | HIGH |
| Default queue `sync`; production may differ | MEDIUM (env not in repo) |
| Server crontab already runs `schedule:run` every minute | MEDIUM (`README_LEGACY.md`; confirm production crontab) |
| Display name رشدیار | LOW (product choice) |
| Default timezone `Asia/Tehran` if user skips | LOW (product choice; code default is UTC) |

---

# Layer 2 — Architecture Plan (updated after evidence)

**Smallest safe change:** add a **new Bot Mother endpoint** with new `growth_*` tables, a new controller/service, one Kernel command, translations, tests, and catalog docs. Attach to existing `Bot`, `BotUsers` (via bot-scoped lookup), `BotUserState`, `BotHelper`, and `Kernel::schedule()`. Do not rewrite cores. Do not extend Prayer/Weather/Psychology/Mission schemas.

Core-component rule: **do not rewrite existing core components unless extension is unsafe or impossible. Prefer reuse and extension.** Audit result: extension is possible; the unsafe part is copying `firstOrNew` and Weather’s job.

---

## 1. Current Architecture

See Evidence A.

Runtime shape:

```text
Messenger platforms (Telegram, Bale; Gap/Eitaa partial)
        ↓
Bot Mother (create child bot → webhook_endpoints → setWebhook)
        ↓
Child webhook controller (per endpoint_id)
        ↓
Bot row + BotUsers + optional BotUserState
        ↓
Service / repository
        ↓
Telegram SDK / BotHelper
        ↓
Feature tables (prayer_records, weather_alerts, channel_poster_destinations, ...)
        ↓
Kernel schedule:run → commands/jobs (RSS, prayer email, weather check, content delivery, ...)
```

Growth Companion becomes **one more child endpoint** on this spine.

---

## 2. Existing Components

See Evidence B and G.

Repository map (runtime role, not folder dogma):

| Directory | Role |
| --------- | ---- |
| `app/Http/Controllers` | Webhook + HTTP. Newer bots: Channel Poster, Prayer, MpContact. Older: fat Quran/Hadith. |
| `app/Services` + `app/Interfaces/Services` | Business logic; bound in `AppServiceProvider`. |
| `app/Repositories` + `app/Interfaces/Repositories` | Eloquent access for some bots (Prayer has repos; Channel Poster often queries in controller/service). |
| `app/Models` | 129 models. Messenger users = `BotUsers`. |
| `app/Helpers` | `BotHelper`, `WebhookEndpointHelper`, `BotMotherStateHelper` (Cache, Bot Mother only). |
| `app/Jobs` | ShouldQueue workers; weather/prayer email/content/RSS. |
| `app/Console/Commands` + `Kernel.php` | Artisan + schedule. |
| `app/Modules` | BotCreation v2, BotOwner panel, BaleOtp, AdminBots. |
| `database/migrations` | Feature tables; avoid FK to `bots`/`bot_users` on MariaDB. |
| `database/seeders` | Per-bot `*WebhookEndpointSeeder`. |
| `lang/*` | `bot.php` shared + per-feature files (`book_library.php`). |
| `config/` | `bot.php`, `book_library.php`, `observability.php`, `queue.php`. |
| `tests/` | PHPUnit Feature/Unit; mixed MySQL vs SQLite traits. |
| `docs/features/` | Per-bot docs. |

---

## 3. Existing Telegram Flow

Three live patterns:

1. **Channel Poster (newest):** JsonResponse, publisher interface, `BotUserState`, callback `cp:`, **owner-only**.
2. **Prayer (end-user):** `Telegram` SDK in controller, `BotHelper`, callbacks `prayer_`/`help_`, `BotUsers::firstOrNew`.
3. **Book Library:** reply keyboard + inline `bl:`, state in `bot_users.settings` JSON.

Growth Companion UX should mix:

- Channel Poster **plumbing** (webhook, token, BotUserState, tests, JSON 200)
- Prayer **end-user openness** (any chat user, not owner)
- Book Library **Simple Mode menu** only if needed; prefer inline `gc:` so labels are not locale-matched text

Message editing exists (`PrayerBotController::showHelpMain` → `$bot->editMessageText`). Optional for settings; not required in MVP.

---

## 4. Existing Database

Reuse as identity:

- `bots` — instance, tokens, `endpoint_id`, `language_code`, owner chat ids
- `bot_users` — `chat_id`, `origin`, `bot_id`, `settings` JSON, email fields
- `bot_user_states` — ephemeral wizard
- `webhook_endpoints` — catalog row

Remain isolated (do not store growth domain here):

- `prayer_records` / `prayer_estimates`
- `weather_alerts` / `weather_history`
- `psychology_test_*`
- `prompts` / `ai_llms` / `missions`
- `content_*` / `library_*`
- `bot_users.settings` as primary growth store

New (MVP):

- `growth_profiles`
- `growth_programs`
- `growth_questions`
- `growth_question_variants`
- `growth_question_schedules`
- `growth_responses`
- `growth_templates`
- `growth_template_questions`

MariaDB: `bot_id` / `bot_user_id` as `unsignedBigInteger` + index, **no** `foreign()` to `bots`/`bot_users`. Internal FKs among `growth_*` are allowed. `down()` required. Prefer not altering `bot_users`.

---

## 5. Existing AI Integration

**Confirmed: no generative LLM API in this repository.**

| Piece | What it actually is | Callers |
| ----- | ------------------- | ------- |
| `AiLlm` / `ai_llms` | Catalog of external websites (ChatGPT, Claude, Gemini URLs) | Mission Bot selection UI; Personnel Admin `/add_ai` |
| `Prompt` / `prompts` | Copy-paste text sent to humans | `MissionBotController`, `SendMissionMediaJob` (`$mission->prompt->content`) |
| MissionBot `curl` | `answerCallbackQuery` to `tapi.bale.ai` / `api.telegram.org` | Not an LLM |
| `OneApiTranslationService` | Guzzle GET `https://one-api.ir/translate/` | RSS translation pipeline |
| `laravel-fulltext` | MySQL fulltext | Quran search |
| Env | `ONE_API_API_TOKEN`, `TRANSLATIONIO_KEY` | Not LLM keys |
| `.env.example` | No `OPENAI_*` / `ANTHROPIC_*` / `GEMINI_*` | — |

Phase 2 may add `LlmProvider` over Guzzle. Do not overload `prompts` or `ai_llms`.

---

## 6. Gap Analysis

From Evidence E. Gaps that Phase 1 must fill:

1. New endpoint + controller + service for a domain-neutral growth bot
2. `growth_*` schema (profile, program, question, variant, schedule, response, templates)
3. Conversational onboarding (BotUserState)
4. Simple Mode daily question + response
5. Dynamic schedule command in existing Kernel
6. Pause / frequency / delete program / delete my growth data
7. Seeded variants (no LLM)
8. Timezone on `growth_profiles` (not on `bot_users` in MVP)
9. Tests (SQLite trait)
10. Catalog + feature doc + 15 locales + cron README row

Gaps deferred:

- LLM generation, embeddings, program builder, monthly review, metrics/habits, marketplace, owner analytics of reflections

---

## 7. Proposed Architecture

```text
Bot Mother
    → webhook_endpoints.endpoint_id = webhook-growth-companion
    → POST /api/webhook-growth-companion?origin=&token=&bot_id=&bot_mother_id=

GrowthCompanionController::webhook
    → resolveBotAndToken (Channel Poster pattern)
    → resolveBotUser (bot_id scoped)
    → BotUserState onboarding / awaiting_answer
    → GrowthCompanionService

Kernel::schedule
    → growth:dispatch-due every 5 minutes, withoutOverlapping, onOneServer
    → GrowthDispatchDueCommand
        → due growth_question_schedules
        → QuestionSelector (exclude variants used 30 days)
        → BotHelper::sendMessageByChatId / inline gc:
        → update last_sent_at, next_due_at

Phase 2 ports (stubs only in MVP if cheap; else omit until Phase 2):
    LlmProvider, QuestionGenerator, WeeklyReviewer
```

Layers:

- Controller: HTTP + Telegram UX only
- Domain services: Program, Question, Schedule, Response — **no LLM inside**
- AI ports: Phase 2

One bot instance + many Programs per end-user. Future multi-bot = more Bot Mother instances (already supported).

---

## 8. Domain Model

MVP ER:

```text
bot_users 1──* growth_profiles
growth_profiles 1──* growth_programs
growth_templates 1──* growth_programs (optional template_slug)
growth_templates 1──* growth_template_questions
growth_programs 1──* growth_questions
growth_questions 1──* growth_question_variants
growth_questions 1──* growth_question_schedules
growth_questions 1──* growth_responses
growth_question_variants 1──* growth_responses
```

### growth_profiles

- `bot_user_id`, `bot_id` (indexed, no FK)
- `mode` simple|advanced
- `tone` nullable
- `timezone` string (e.g. `Asia/Tehran`)
- `notify_time` time
- `quiet_hours_start` / `quiet_hours_end` nullable
- `depth` quick|reflective|deep (MVP: quick)
- `intensity` minimal|balanced|active
- `interaction_budget_per_day` int default 1
- `onboarding_completed_at` nullable
- timestamps

### growth_programs

- `growth_profile_id`
- `bot_id`, `bot_user_id` (denormalized for dispatcher queries)
- `name`, `template_slug` nullable
- `status` active|paused|deleted
- `settings` JSON nullable (question_count, etc.)
- timestamps

### growth_questions

- `growth_program_id`
- `question_key` (stable identity, e.g. `daily_self_reflection`)
- `intent`, `domain` (string, not enum of religions)
- `difficulty` 1–5 default 1
- `frequency` daily|weekly|monthly|custom|paused
- `paused_at` nullable
- `source` template|user|ai
- `active` boolean
- timestamps

### growth_question_variants

- `growth_question_id`
- `body` text
- `locale`, `tone` nullable, `difficulty` nullable
- timestamps

### growth_question_schedules

- `growth_question_id`
- `cadence_type`
- `days_of_week` JSON nullable
- `time_local` time
- `next_due_at` datetime (UTC stored, computed from timezone)
- `last_sent_at` nullable
- timestamps

### growth_responses

- `growth_question_id`, `growth_question_variant_id` nullable
- `bot_user_id`, `bot_id`
- `body` text
- `answered_at`
- timestamps

### growth_templates / growth_template_questions

System rows: `spiritual`, `health`, `family`, `work`, `study`, `self-knowledge`, `sport`, `relationships`, `custom`. Seed variants in seeder, not PHP if/else on domain.

Phase 2+: areas, goals, habits, metrics, reviews, ai_interactions, feedback, embeddings.

---

## 9. Database Changes

One feature migration `create_growth_companion_tables` (all `growth_*`). Internal FKs OK. No FK to `bots`/`bot_users`. If a previous failed migrate exists on a server, `dropIfExists` those tables at start of `up()` (project MariaDB rule).

Do not add columns to `bot_users` in MVP.

Seeder: `GrowthCompanionWebhookEndpointSeeder` + template/question seed class.

---

## 10. API Changes

No public REST API in MVP. Only:

```text
POST /api/webhook-growth-companion
```

Query: `origin` (required), `token`, `bot_id`, `bot_mother_id`, `language` optional.

Return `JsonResponse` HTTP 200 even on handled errors (Channel Poster), so Telegram/Bale do not retry.

Privacy delete/export are **chat commands**, not HTTP, in MVP.

---

## 11. Telegram UX Flow

Callback prefix `gc:` (confirmed unused). Keep under 64 bytes (e.g. `gc:a`, `gc:later`, `gc:set`, `gc:p`, `gc:freq:w`).

Reply keyboard optional. Prefer inline so Simple Mode is one message.

Error handling: log + short `trans('growth_companion.error')`. Never 500 to the webhook.

Commands: `/start`, `/privacy`, `/settings`. User should not need more.

---

## 12. Onboarding Flow

Conversational, `BotUserState`, not a long form.

```text
/start
 ↓
gc_onboarding_focus
  "دوست داری روی چه چیزی بیشتر کار کنی؟"
  health | family | work | spirituality | study | self | sport | relations | custom
 ↓
gc_onboarding_intensity
  minimal | balanced | active
 ↓
gc_onboarding_time (skippable)
  default 21:00 local
 ↓
Create growth_profiles + one growth_programs from template
  + 1 question + 3–10 variants + schedule next_due_at=now
 ↓
Ask first question immediately (Simple Mode)
  buttons: Answer (wait for text) | Later | Settings
```

Custom focus: one free-text then continue. Do not ask tone, frequency calendar, or AI flags in MVP.

Timezone: if skipped, product default **open question**; implementation should store an explicit string on `growth_profiles` (not rely on `config('app.timezone')` UTC).

---

## 13. Question Engine

- Stable `question_key` per concept.
- Selector picks the **question due now** (MVP: usually one daily).
- Then picks a **variant** not used by this user in 30 days; else least recently used.
- Difficulty stays at 1 in MVP (no jump to level 5).
- Interaction budget: Simple Mode max 1 outbound prompt per profile per calendar day in user timezone.
- Adaptive follow-ups: Phase 3; `depth=quick` only in MVP.

---

## 14. Question Variant Engine

Seed 3–10 variants per template question. Round-robin / least-recent, not sequential forever.

No embeddings in MVP. Exact `body` match + `variant_id` history is enough.

AI generation of new variants: Phase 2, must lock intent/domain/difficulty.

---

## 15. AI Architecture

MVP: **no live LLM**.

Phase 2:

```text
LlmProvider (interface)
  → OpenAiCompatibleHttpProvider (Guzzle; already in composer)
QuestionGenerator / QuestionRewriter / FollowUpGenerator
ProgramBuilder / WeeklyReviewer / MonthlyReviewer / ReflectionSummarizer
```

Guardrails: keep intent, no diagnosis, no preacher/judge, no medical claims, no leaking other users’ data.

Do not use `AiLlm` or `Prompt` models.

---

## 16. Prompt Architecture

Phase 2 files:

```text
resources/growth_prompts/question_generation.v1.md
resources/growth_prompts/question_variation.v1.md
resources/growth_prompts/follow_up.v1.md
resources/growth_prompts/weekly_review.v1.md
resources/growth_prompts/program_builder.v1.md
```

Version in filename. Not scattered in controllers. Not table `prompts`.

---

## 17. Scheduling Architecture

```text
Kernel.php::schedule()
 ↓
growth:dispatch-due   everyFiveMinutes()
 withoutOverlapping()
 onOneServer()
 ↓
GrowthDispatchDueCommand::handle()
 ↓
Query growth_question_schedules
   where next_due_at <= now()
   join active programs/questions/profiles
 ↓
For each row (try/catch per user):
   skip quiet hours (profile timezone)
   skip if daily budget exhausted
   QuestionSelector::pickVariant()
   Load Bot by bot_id, new Telegram(token, origin from user)
   BotHelper::sendMessageByChatId + gc: buttons
   last_sent_at = now()
   next_due_at = compute next in timezone
```

Compare to existing:

| Concern | Existing | Growth |
| ------- | -------- | ------ |
| Host | `Kernel::schedule` | Same |
| Lock | `withoutOverlapping` + `onOneServer` | Same |
| Queue | default sync | Command can run synchronously; optional later job per send |
| Throttle | content: 1 hour per progress row; prayer email: threshold service | interaction_budget + quiet hours |
| Timezone | UTC `now()`; weather hour naive | Store UTC `next_due_at` computed from profile timezone |
| Retry | Prayer email job tries=3 | Per-user catch + log; do not fail whole run |
| Batch | prayer batch-size | Limit N due rows per run (e.g. 50) so sync cron does not block |
| Delivery | content uses BotHelper; weather does **not** send | Follow **content**, not weather |

Do not create a second crontab besides documenting `growth:dispatch-due` under existing `schedule:run`.

Reminder copy: human, non-judgmental `trans` strings. One nudge, not “YOU HAVE NOT ANSWERED!!!”.

---

## 18. Simple Mode

Visible:

- Today’s question
- Answer (next text message = response)
- Later
- Settings (pause, frequency daily/weekly, time, Advanced, privacy)

Hidden: new question, tone, variants, AI, extra programs.

After answer: short ack. No extra question.

---

## 19. Advanced Mode

Phase 2 UI. Schema in MVP can already store `mode=advanced`.

Capabilities when shown: add/edit/pause/delete question, custom frequency (weekdays, every 2 weeks), extra program, tone.

---

## 20. Program Architecture

MVP: exactly one active program per profile, created from onboarding template.

Phase 2: multiple active programs, each with its own budget (e.g. spiritual 2/week, health 3/week). Dispatcher must sum budgets against profile budget.

Delete program = soft `status=deleted` + cascade pause schedules. Hard delete reflections is a separate privacy action.

---

## 21. Template Architecture

Templates are **data**. Seed:

- Spiritual / محاسبه نفس topics as one template (نماز، قرآن، نهج، اخلاق، خشم، نیت، خانواده…) without making core religious
- Secular: health, career, family, relationships, learning, finance, productivity

Each template: name, description, areas-as-strings, default questions, variants, default frequency, default tone, optional `ai_instructions` text for Phase 2.

Admin-created templates: Phase 4. User “build me a program”: Phase 3.

---

## 22. Privacy

MVP chat actions (`/privacy` or Settings):

- Pause program
- Delete program (soft)
- Delete all reflections (`growth_responses` for this profile)
- Delete all my growth data (profile + programs + questions + responses + states)

Do not log response bodies. Log `bot_id`, event name, ids only.

Phase 2: export JSON; AI data-usage toggle (default off until LLM exists).

Bot Mother **owner must not** read reflections. No Nova resource on responses in MVP.

---

## 23. Analytics

MVP: optional `Log::info` counters only (activation, first_response, dispatch_sent, dispatch_skipped_budget). No warehouse.

Phase 2+: aggregate tables without storing answer text. Observability (`config/observability.php`) can gain counters later — not a Grafana project in MVP.

Product metrics to design for (collect later): activation, first response, daily/weekly retention, completion rate, notification response rate, feedback. Do not collect extra sensitive fields.

---

## 24. Testing Strategy

Follow Channel Poster, do not invent Pest or change global `phpunit.xml`.

- Trait `UsesGrowthCompanionSqlite` (clone `UsesChannelPosterSqlite`, create `growth_*` + `bots` + `bot_users` + `bot_user_states`)
- Feature: `GrowthCompanionWebhookTest` — `/start` onboarding, answer question, pause, delete data
- Feature or command test: `GrowthDispatchDueCommandTest` — due row sends (fake Telegram), respects pause and budget
- Unit: `QuestionSelectorTest` — does not repeat variant_id within window
- Fake send: bind a small publisher or mock `Telegram` / wrap send in an interface **only if tests need it**. Channel Poster already proved Fake publisher + `app->instance`. Prefer a `GrowthMessenger` interface if controller tests cannot hit SDK.

No factories required if tests insert rows like Channel Poster. `BotFactory` exists but Channel Poster tests create models inline.

---

## 25. Migration Strategy

- Side-by-side: new route, old bots untouched (project iron rule)
- `php artisan migrate` only (never migrate:fresh on production)
- Seeder `--class=GrowthCompanionWebhookEndpointSeeder`
- `cache:clear` + `route:clear` so Bot Mother lists the endpoint
- Rollback: `migrate:rollback` of the growth migration + unpublish route/seeder; no data in `bots`/`bot_users` schema to undo
- If MariaDB errno 150: there must be no FK to `bots`

---

## 26. MVP Scope

**In:**

- Bot Mother type + webhook
- Onboarding (focus, intensity, optional time)
- One program from template
- Daily question + variants
- Response storage
- Simple Mode
- `growth:dispatch-due`
- Pause, frequency daily↔weekly, delete program, delete my data
- 15 locales for user-facing strings
- Tests + `docs/features/GROWTH_COMPANION.md` + catalog 36→37
- Cron row in `README.md` / `README_LEGACY.md` schedule table

**Out:**

- LLM, embeddings, weekly/monthly AI review, program builder, metrics, marketplace, Gap/Eitaa, owner dashboard of reflections, gamification

### MVP acceptance criteria

User can:

1. `/start` the child bot
2. Choose a focus
3. Get a program without seeing Advanced UI
4. Receive one question
5. Answer in free text
6. Receive the next daily question via scheduler
7. Change frequency (daily/weekly)
8. Pause the question
9. Add one custom question (simple text + daily) — if this slips, it can be first item of Phase 2; prefer in MVP because the original acceptance list includes it
10. Delete the program
11. Delete all growth data

Domain is not hard-coded as religion.

---

## 27. Phase 2

- Multiple programs
- Weekly review in-chat (adapt Prayer weekly *aggregation*, send via BotHelper not email)
- Custom frequency (days of week, every 2 weeks, Fridays)
- AI variant generation with intent lock (`LlmProvider` + prompt files)
- User feedback (“too many / too few questions”) affecting budget
- Export my data
- Advanced Mode UI
- AI usage consent

Acceptance: two programs; weekly summary; custom question already in MVP stays; generate 3 variants without changing `question_key` intent.

---

## 28. Phase 3

- Reflective/Deep follow-ups (configurable)
- Monthly review without personality diagnosis
- Semantic duplicate detection (embeddings)
- AI program builder (draft → accept/edit/regenerate)
- Habits + metrics + minimum viable habit suggestions
- Progressive difficulty 1→5 from engagement

Phase 4 (explicitly later): template marketplace, multi-bot branding split, heavy analytics.

---

## 29. Technical Risks

| Risk | Mitigation |
| ---- | ---------- |
| MariaDB FK errno 150 | No FK to `bots`/`bot_users` |
| `QUEUE_CONNECTION=sync` blocks cron | Batch limit + 5-minute cadence + withoutOverlapping |
| Timezone math (UTC app vs user local) | Store `next_due_at` UTC; compute with profile timezone |
| Callback 64-byte limit | Short `gc:` codes |
| `BotUsers::firstOrNew` cross-bot collision | Do not call it |
| Weather-like “scheduler that does not send” | Copy content delivery send path; test command |
| Webhook 500 retries | Always HTTP 200 JsonResponse |
| `LogHelper` TypeError | `Log::info` only |
| Duplicate state helpers | Local methods; do not refactor five bots |

---

## 30. Product / UX / AI / Privacy Risks

| Risk | Mitigation |
| ---- | ---------- |
| Over-onboarding | Two steps + skippable time |
| User feels managed | Simple Mode; quiet hours; pause |
| AI as preacher/therapist | No LLM in MVP; later guardrails |
| Owner reads reflections | No Nova; no owner APIs |
| Spiritual template captures the product | Templates are data; core keys are domain-neutral |
| Overload (10 questions/day) | interaction_budget |
| Judgmental reminders | Copy review in `trans` keys |
| Weak conclusions from few answers | Weekly review language stays tentative (Phase 2) |
| Sensitive logs | Never log response body |

---

## 31. Open Questions (priority)

1. **Display name:** رشدیار vs مسیر / همراه / خودیار / رشد. Technical id can stay `growth-companion`.
2. **Bot Mother catalog vs first-party-only token.** Evidence favors catalog (this platform’s only working distribution). Confirm.
3. **Phase 2 LLM vendor:** OpenAI-compatible HTTP vs specific vendor. No client exists today.
4. **May the Bot Mother owner see anonymized aggregates?** Default **no**.
5. **Who reviews spiritual template wording** so core stays non-sectarian?
6. **Default timezone if user skips:** `Asia/Tehran` vs asking explicitly? App default is UTC; most users are Iran.
7. **Custom question in MVP vs Phase 2?** Original acceptance includes it; if timeboxed, ship pause/frequency first.
8. **Production crontab:** confirm `schedule:run` every minute still (documented in `README_LEGACY.md`, not main `README.md`).

---

# Appendix A — Architecture diagram

```text
[Telegram/Bale]
      │ webhook
      ▼
GrowthCompanionController
      │
      ├─ resolveBotAndToken → bots
      ├─ resolveBotUser     → bot_users (scoped bot_id)
      ├─ BotUserState       → onboarding / awaiting_answer
      └─ GrowthCompanionService
              ├─ Profile / Program / Question / Response
              └─ QuestionSelector
                      ▲
                      │
Kernel.schedule ──► growth:dispatch-due ──► Telegram send
                      │
                      └─ growth_question_schedules.next_due_at

Phase 2: LlmProvider + resources/growth_prompts/*.vN.md
```

---

# Appendix B — Entity relationship (MVP)

```text
webhook_endpoints 1──* bots
bots             1──* bot_users          (logical, no FK)
bot_users        1──* bot_user_states
bot_users        1──* growth_profiles
growth_profiles  1──* growth_programs
growth_templates 1──* growth_template_questions
growth_programs  *──1 growth_templates   (slug)
growth_programs  1──* growth_questions
growth_questions 1──* growth_question_variants
growth_questions 1──* growth_question_schedules
growth_questions 1──* growth_responses
```

---

# Appendix C — User flows

### Onboarding + first question

```text
User /start
 → focus keyboard
 → intensity keyboard
 → optional time
 → persist profile+program+question+variants+schedule
 → send variant text
 → state = gc_awaiting_answer
User text
 → growth_responses insert
 → ack
 → clear awaiting state
```

### Daily

```text
Cron schedule:run
 → growth:dispatch-due
 → due schedule
 → select unused variant
 → send question
User answers or Later / Pause / Settings
```

---

# Appendix D — Implementation phases (when coding starts)

This appendix is for a **future** implementation pass. This planning document does not create these files.

### Phase 1 — files

- `app/Http/Controllers/GrowthCompanionController.php`
- `app/Interfaces/Services/GrowthCompanionService.php`
- `app/Services/GrowthCompanionServiceImpl.php`
- `app/Services/GrowthQuestionSelector.php` (or method on service)
- Models under `app/Models/Growth*.php`
- `database/migrations/2026_08_17_*_create_growth_companion_tables.php`
- `database/seeders/GrowthCompanionWebhookEndpointSeeder.php`
- `database/seeders/GrowthCompanionTemplateSeeder.php`
- `app/Console/Commands/GrowthDispatchDueCommand.php` signature `growth:dispatch-due`
- bind in `AppServiceProvider`
- route in `routes/api.php`: `Route::post('/webhook-growth-companion', ...)`
- importer row in `WebhookEndpointDefaultImporter`
- `lang/{15}/growth_companion.php`
- `tests/UsesGrowthCompanionSqlite.php`
- `tests/Feature/GrowthCompanionWebhookTest.php`
- `tests/Unit/GrowthQuestionSelectorTest.php`
- `docs/features/GROWTH_COMPANION.md`
- catalog: `README.md`, `docs/BOT_TYPES_GUIDE.md`, `docs/BOTS_COMPLETE_GUIDE.md`, `README_LEGACY.md`, `docs/README.md`, `.agents/AGENTS.md` (36→37)
- cron mention beside `Kernel.php` docs

**DB:** `growth_*` as above. **API:** webhook only. **Telegram:** onboarding + Simple Mode. **AI:** none. **Tests:** SQLite webhook + selector + dispatch. **Acceptance:** section 26.

### Phase 2 / 3 / 4

As sections 27–28. Do not start until Phase 1 acceptance passes.

---

# Appendix E — Risk register

See sections 29–30 (Technical, Product, UX, AI, Privacy).

---

# Appendix F — What this planning pass did *not* do

- No production code changes
- No migrations run
- No dead-code deletion
- No dependency installs
- No config changes
- The Cursor plan file was not edited

---

*End of implementation plan. Next step after human approval: implement Phase 1 only, following Reuse → Adapt → Extend, with Channel Poster plumbing and Content Delivery’s send loop as the scheduler reference.*
