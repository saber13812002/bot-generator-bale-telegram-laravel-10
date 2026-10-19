# 🏛️ ARCHITECTURE — معماری سیستم Bots

> مرجع ساختاری پروژه. همهٔ بخش‌ها از کد فعلی (2026-10-19) تأیید شده‌اند. برای جزئیات عملیاتی به [`docs/AGENT_HANDOFF.md`](docs/AGENT_HANDOFF.md) و [`docs/JOBS_AND_SCHEDULER.md`](docs/JOBS_AND_SCHEDULER.md) مراجعه کن.

---

## ۱. تصویر کلی (Big Picture)

```
                        ┌─────────────────────────────────────────────────────┐
                        │                  cPanel / سرور واحد                  │
                        │  /home/pardisa2/bots   PHP 8.2.34   MySQL            │
                        └─────────────────────────────────────────────────────┘
                                      │
        ┌─────────────────────────────┼──────────────────────────────┐
        │                             │                              │
   ┌────▼─────┐                 ┌─────▼──────┐                  ┌────▼─────┐
   │ Webhooks │                 │  REST API  │                  │  Cron    │
   │ (پیام‌ها) │                 │  /api/v1/  │                  │ schedule │
   │ api.php  │                 │ + web.php  │                  │  :run 1' │
   └────┬─────┘                 └─────┬──────┘                  └────┬─────┘
        │                             │                              │
        ▼                             ▼                              ▼
┌────────────────────────────────────────────────────────────────────────────┐
│                       Laravel 10 Application (app/)                       │
│                                                                            │
│  Http/Controllers (73 controller)   Services (~85 service)   Models (119)  │
│  ├─ BotMother (ساخت ربات)          ├─ Rss* (خط لوله RSS)    ├─ Rss*      │
│  ├─ ربات‌های تخصصی (37 ربات)       ├─ ChannelPoster*         ├─ Channel*  │
│  └─ Api/* (token, mission, quran)   ├─ ContentSync/           ├─ Content*  │
│                                     ├─ Bot* (health, obsv)    └─ Bot*      │
│                                     └─ …                               │   │
│  Jobs/ (12 job — همه در sync queue inline اجرا می‌شوند)                    │
│  Console/Commands (30+ command — 30 entry در Kernel schedule)             │
└────────────────────────────────────────────────────────────────────────────┘
        │                              │                              │
        ▼                              ▼                              ▼
  پیام‌رسان‌ها (webhook outbound)   دیتابیس MySQL              Cache (lock,
  Ble / Telegram / Eitaa / Soroush  (MySQL cPanel)             heartbeat, state)
```

**نقطهٔ کلیدی:** همهٔ چیز — دریافت webhook، پردازش، انتشار، scheduled jobs — روی **یک سرور** با **یک خط cron** (`schedule:run` هر دقیقه) و **صف sync** می‌چرخد. هیچ worker، هیچ server دوم، هیچ queue broker.

---

## ۲. لایه‌ها (Layers)

| لایه | محل | مسئولیت |
|---|---|---|
| **Route** | [`routes/api.php`](routes/api.php) (webhook‌ها + API)، [`routes/web.php`](routes/web.php) (Idea/OTP و …)، [`routes/bot-owner.php`](routes/bot-owner.php)، [`routes/channels.php`](routes/channels.php)، [`routes/console.php`](routes/console.php) | ورودی HTTP |
| **Controller** | [`app/Http/Controllers`](app/Http/Controllers) (73 فایل) | parse update، روتینگ به service، پاسخ JSON |
| **Service** | [`app/Services`](app/Services) (~85 فایل) + زیرشاخه‌های `ContentSync/`, `Email/` | منطق کسب‌وکار |
| **Model** | [`app/Models`](app/Models) (119 فایل) | ORM + relation + scope |
| **Job** | [`app/Jobs`](app/Jobs) (12 فایل) | واحدهای کاری (sync queue → inline) |
| **Command** | [`app/Console/Commands`](app/Console/Commands) (30+) | entry نقاط cron/دستی |
| **Helper** | [`app/Helpers`](app/Helpers) | `AdminHelper`, `TokenHelper`, `RssHelper`, … |
| **Module** | [`app/Modules`](app/Modules) (`AdminBots`, `BaleOtp`, `BotCreation`, `BotOwner`, `BotRegistration`) | ماژول‌های مجزا (معماری module) |
| **Logging** | [`app/Logging`](app/Logging) (`DatabaseLogger`, `DatabaseLogHandler`) + `config/logging.php` | لاگ ساختاریافته |

### الگوهای غالب (از [`.agents/AGENTS.md`](.agents/AGENTS.md) + مشاهدهٔ کد)
- **Controller باریک → Service سنگین:** controller webhook فقط resolve bot + `handlePrivateMessage` می‌کند.
- **State machine در دیتابیس:** `BotUserState` برای وایزارد ربات‌ها (state + data JSON).
- **Factory per platform:** `TelegramChannelPosterPublisherFactory`, `TelegramGrowthMessengerFactory` — انتخاب publisher بر اساس origin/پلتفرم.
- **Repository** در [`app/Repositories`](app/Repositories) (استفادهٔ محدود، بیشتر مستقیم Eloquent).

---

## ۳. ورودی‌های webhook (تأییدشده از `routes/api.php`)

> همهٔ webhook‌ها زیر پیشوند `/api` هستند. الگوی عمومی: `POST /api/{webhook-xxx}` → controller با `webhook()` یا `index()`.

| Route | Controller | حوزه |
|---|---|---|
| `/api/webhook-bot-mother` , `-v2` | `BotMotherController` / `BotMotherWorkflowController` | ربات مادر (ساخت ربات) |
| `/api/webhook-channel-poster` | [`ChannelPosterBotController`](app/Http/Controllers/ChannelPosterBotController.php) | **کانال‌پوستر** |
| `/api/webhook-rss` (2 تعریف!) | `BotMotherController::rss` **و** `RssPostItemTranslationController::index` | ⚠️ تعریف تکراری — ببین [§۷ یافته‌ها](#7-یافته‌ها-و-نقاط-ضعف) |
| `/api/webhook-rss-admin` | `RssAdminBotController` | مدیریت RSS |
| `/api/webhook-prayer-bot` | `PrayerBotController` | نماز |
| `/api/webhook-quran-word` , `/api/gap` , `/api/webhook-quran-message-to-all` | `QuranWordController` | قرآن |
| `/api/webhook-quran-ayat` | `BotQuranAyatController` | قرآن (آیات) |
| `/api/webhook-hadith` / `/api/webhook-nahj` | `HadithSearchController` / `NahjController` | حدیث / نهج‌البلاغه |
| `/api/webhook-weather` | `WeatherController` | آب‌وهوا |
| `/api/webhook-blog` | `BlogController` | بلاگ |
| `/api/webhook-mission-bot` , `-mission-media` , `/api/webhook-task-approval` | Mission controllers | مأموریت |
| `/api/webhook-personnel-registration` , `-personnel-admin` | Personnel controllers | پرسنل |
| `/api/webhook-book-pixel` , `-book-pixel-approval` | BookPixel controllers | کتاب پیکسل |
| `/api/webhook-book-library` , `-book-library-reader` | BookLibrary controllers | کتاب‌خانه |
| `/api/webhook-content-submission` | `ContentSubmissionController` | ثبت محتوا |
| `/api/webhook-mawkib-finder` | `MawkibFinderController` | مأمکب |
| `/api/webhook-growth-companion` | `GrowthCompanionController` | رشد همراه |
| `/api/webhook-rating-bot` , `-psychology-test` , `-presenter-bot` , `-list-bot` , `-mp-contact` , `-admin-bots` | … | بقیهٔ ربات‌ها |
| `/api/rss-generator` , `/api/song-sara-songs` | `RssFeedWebOriginController` / `SongSaraPostController` | ورودی فید/محتوا |

کاتالوگ کامل 37 ربات: [`.agents/AGENTS.md` بخش 4-8](.agents/AGENTS.md).

---

## ۴. REST API

| مسیر | احراز | منبع |
|---|---|---|
| `/api/v1/quran/*` (languages, user/settings, …) | عمومی + کاربری | `routes/api.php` (block quran) |
| `/api/v1/missions/*` (create, content, prompt, assign, submit) | **`api.token`** (حساب کاربری) | `routes/api.php` + `MissionApiController` |
| `/api/v1/metadata` | عمومی | `routes/api.php` |
| `/api/.../generate` (API token) | `ApiTokenController` | `routes/api.php` خط 175 |
| `/api/v1/missions` POST/GET (request/cancel/submit) | `MissionController` | `routes/api.php` |

→ مستندات: [`docs/API_README.md`](docs/API_README.md), [`docs/API_QUICK_START.md`](docs/API_QUICK_START.md), [`docs/API_MISSION_DOCUMENTATION.md`](docs/API_MISSION_DOCUMENTATION.md), Swagger: [`docs/SWAGGER_DOCUMENTATION.md`](docs/SWAGGER_DOCUMENTATION.md)

---

## ۵. جریان دادهٔ اصلی‌ها

### 5.1 خط لولهٔ RSS (هر 15 دقیقه)

```
cron ─► app:rss_read_translate
        ├─ RssItemService::run  ─►  RssService::readRssAndSave (هر فید)
        │      └─ rss_items / rss_post_items  (insert race-safe)
        └─ RssPostItemTranslationToMessengerService::run
               └─ INSERT rss_post_item_translation_queues
                      └─ Model boot() ─► RssPostItemTranslationToMessengerJob (inline/sync)
                             ├─ RssChannelMessageSender::sendBaseMessage  ─►  پیام‌رسان
                             ├─ recordBaseSuccess (sent_at, message_id)
                             └─ sendMedia (عکس + MP3های سایت‌های ویژه)
                                    └─ markSent(mediaErrors) | markFailed
```
جریان ریکاوری و قفل‌ها: [`docs/AGENT_HANDOFF.md` §3.1](docs/AGENT_HANDOFF.md) و [`docs/JOBS_AND_SCHEDULER.md`](docs/JOBS_AND_SCHEDULER.md).

### 5.2 Channel Poster (هر 5 دقیقه)

```
cron ─► channel-poster:process ─► ProcessChannelPosterQueueJob
        ├─ cancel pending > 7 روز
        └─ 10 آیتم ready() ─► ChannelPosterQueuePublishService::publishItem
               ├─ ChannelPosterMediaStore (fetch ≤50MB → storage/app/channel-poster-media/)
               ├─ per destination: publisher (Bale/TG/Eitaa/Soroush)
               │      ├─ tryFileId (فقط origin platform)  → وگرنه multipart re-upload
               │      └─ ChannelPosterPublishLog (success/fail per destination)
               └─ status: pending → published | failed
```

### 5.3 Content Sync (on-demand از ربات)

```
webhook-channel-poster ─► /sync (لینک+متن) یا fast-mode (لینک در متن)
   └─ ContentSyncService::publishImmediate | enqueueForTag
        ├─ PostUrlParser::parse (canonical URL per platform)
        ├─ dedup: exclude_source_channel | exclude_source_platform → skipped/existing_source
        ├─ attribution freeze → channel_poster_queue.source_attribution
        ├─ publishChunks (TextChunker ~3500 کاراکتر)
        └─ ContentSyncVersion per destination (published/failed/queued/skipped)
```

---

## ۶. زیرساخت‌ها

| بخش | پیاده‌سازی |
|---|---|
| **Queue** | `sync` — `ShouldQueue` jobs در همان request/cron inline اجرا می‌شوند. جدول `jobs`/`failed_jobs` استفاده نمی‌شود. "job failed" = status `error`/`failed` در جدول صف هر زیرسیستم. |
| **Cache** | `config/cache.php` — استفاده برای lockها (`Cache::lock`), heartbeat (`pipeline:heartbeat:*`), state watchdog، throttle لاگ. (دریور env: `CACHE_STORE`) |
| **Logging** | `config/logging.php` + `app/Logging/DatabaseLogger` — لاگ‌های ساختاریافته (channel, context array) + `BotHealthEvent` برای health monitoring |
| **Observability** | `PrometheusMetricsExporter`, `BotObservabilityService`, `observability:prune` (روزانه) — [`docs/features/PROMETHEUS_METRICS.md`](docs/features/PROMETHEUS_METRICS.md) |
| **Mail** | `config/mail.php` + `app/Services/Email/` (Mailtrap در dev) — گزارش نماز/هشدارها |
| **Nova** | `app/Nova` + `config/nova.php` (پنل مدیریت — وضعیت فعال‌بودن در production: **تأییدنشده** از محل محلی) |
| **Auth** | Sanctum (`config/sanctum.php`) + `app/Policies` |
| **Broadcasting** | `RocketChatService`, `app/Broadcasting/RocketChatChannel.php` |

---

## ۷. یافته‌ها و نقاط ضعف

| # | مورد | وضعیت |
|---|---|---|
| 1 | **دو route با نام `POST /api/webhook-rss`** در `routes/api.php` (خط 67 و 97) — تعریف دوم بر اول غلبه می‌کند؛ intent تاریخی/legacy | ⚠️ برای بازنویسی در PR جدا — تا آن‌وقت **تأییدنشده** کدام intent است |
| 2 | فایل‌های بزرگ: `BotMotherController` (~199KB)، `QuranWordController` (~155KB)، `ChannelPosterBotController` (2520 خط) | فهرست refactor در [`.agents/AGENTS.md`](.agents/AGENTS.md) |
| 2b | 3 command scheduler بدون `withoutOverlapping` (`task-reminder`, `users-ranking`, `test-schedule-daily-into-slack`) | ریسک فقط در چندسرور — مستندشده در `docs/JOBS_AND_SCHEDULER.md` |
| 3 | `storage:logs-cleanup` در `SCHEDULER_CRON_PLAN.md` وجود ندارد | حذف از برنامهٔ cron |
| 4 | `RssPostItemTranslationJob` (بدون ToMessenger) در `app/Jobs` — نسخهٔ قدیمی/legacy در مقابل `RssPostItemTranslationToMessengerJob` | **تأییدنشده** که هنوز caller فعال دارد — قبل از حذف، grep شود |
| 5 | `app/Nova` + `config/nova.php` وجود دارد ولی استفادهٔ production **تأییدنشده** | |
| 6 | `config/l5-swagger.php.disabled` — swagger غیرفعال است؛ `l5-swagger:generate` در deploy فقط در صورت نیاز | مطابق `deploy_commands.sh` (اختیاری) |
| 7 | دیتابیس: 119 مدل در یک schema — مرزهای domain ضعیف (معماری تک-سرویس قدیمی) | بدهی فنی شناخته‌شده؛ refactor تدریجی |
| 8 | `findTheNewOrNonExistentItems` در `RssService` با `@deprecated` علامت‌گذاری شده | کاندیدای حذف |

---

## ۸. نقشهٔ دایرکتوری (مرجع سریع)

```
app/
├── Broadcasting/        # RocketChatChannel
├── Builders/            # BotBuilder
├── Console/
│   ├── Kernel.php       # ⭐ 30 entry scheduler — مرجع: docs/JOBS_AND_SCHEDULER.md
│   └── Commands/        # 30+ command (app:*, rss:*, channel-poster:*, pipeline:*, …)
├── Exceptions/          # Handler
├── Helpers/             # AdminHelper, TokenHelper, RssHelper, …
├── Http/Controllers/    # 73 controller (webhook ربات‌ها + Api/)
├── Interfaces/          # contractها (ChannelPosterPublisher, …)
├── Jobs/                # 12 job (sync queue)
├── Logging/             # DatabaseLogger, DatabaseLogHandler
├── Mail/                # مَیل‌ها
├── Models/              # 119 model
├── Modules/             # AdminBots, BaleOtp, BotCreation, BotOwner, BotRegistration
├── Notifications/       # NewRssPostItemTranslationNotification
├── Nova/                # پنل Nova
├── Policies/            # authorization
├── Providers/           # service providerها
├── Repositories/        # (محدود)
└── Services/            # ~85 service — قلب منطق
    ├── ContentSync/     # PostUrlParser, ContentSyncService, TextChunker, PostUrlBuilder, …
    └── Email/           # سرویس‌های ایمیل

config/                  # app.php (timezone=UTC!), queue.php, cache.php, logging.php, …
database/migrations/     # migrations (جدیدترین‌ها: content sync 2026-10, recovery fields 2026-10-18)
routes/                  # api.php (webhook+API), web.php, bot-owner.php, channels.php, console.php
docs/                    # ⭐ AGENT_HANDOFF.md, JOBS_AND_SCHEDULER.md, PRODUCTION_RUNBOOK.md,
                         #    ARCHITECTURE.md + features/ (مستندات فیچر) + API_* + swagger
tests/                   # Feature + Unit (399 passed / 2205 assertions — 2026-10-19)
.agents/                 # AGENTS.md (دانش‌نامه) + rules/DOCUMENTATION_RULE.md
```

---

## ۹. تصمیمات معماری مهم (و چرا)

1. **چرا sync queue؟** — سرور cPanel واحد بدون daemon. `schedule:run` هر دقیقه + inline job = بدون supervisor، بدون `queue:work`، بدون broker. هزینه: جاب‌های طولانی request را طولانی می‌کنند (مثلا `RssPostItemTranslationToMessengerJob` timeout=45s) — آگاهانه پذیرفته.
2. **چرا `withoutOverlapping` + قفل cache؟ (دو لایه)** — `withoutOverlapping` entry scheduler را قفل می‌کند ولی اگر فرایند crash کند، قفل sticky می‌ماند؛ قفل `Cache::lock` با TTL (900s/1800s) crash را پاک می‌کند.
3. **چرا heartbeat جدا (`pipeline:heartbeat` هر دقیقه)؟** — `pipeline:watch` (هر ساعت) بدون اجرا مستقیم، فقط از freshnessِ کلیدهای cache قضاوت می‌کند که "آیا اصلا کسی کار کرده".
4. **چرا `sent` فقط با پاسخ واقعی؟** — هر موفقیت جعلی = تکرار محتوا در کانال + از دست رفتن محتوای واقعی. قرارداد سخت سیستم.
5. **چرا `source_attribution` فریز می‌شود؟** — تنظیمات ادمین می‌تواند بین صف‌شدن و انتشار تغییر کند؛ فریز، خروجی قابل پیش‌بینی می‌دهد و bug "تغییر پس از صف‌شدن" را حذف می‌کند.
6. **چرا file_id بین پلتفرم‌ها کار نمی‌کند؟** — محدودیت ذاتی APIهای پیام‌رسان‌ها؛ `tryFileId` فقط در origin platform، در غیر اینصورت re-upload multipart از فایل محلی.

---

*آخرین به‌روزرسانی: 2026-10-19 — از روی `routes/api.php`, `app/` (list), `config/`, [`.agents/AGENTS.md`](.agents/AGENTS.md).*
