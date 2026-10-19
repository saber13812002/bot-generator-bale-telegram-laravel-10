# 🤝 AGENT HANDOFF — مرجع تحویل دانش پروژه Bots

> **این سند برای چه کسی است؟** هر هوش مصنوعی (Claude, Antigravity, Xcode, ZCode, Zoo, …) که وارد این مخزن می‌شود، **اول این فایل** را بخواند.
> **قانون طلایی:** فقط آنچه در این مستندات آمده از **کد، کانفیگ، تست و Git** تأیید شده است. هر چیز غیرمؤیّد با برچسب **«تأییدنشده»** مشخص شده و هرگز به‌عنوان واقعیت فعلی پیش‌نهاد نمی‌شود.

---

## ⚡ شروع سریع: اگر مالک گفت «برو سراغ جاب‌ها و مشکل انتشار RSS را بررسی کن»

۱. `php artisan pipeline:status` را اجرا کن (خروجی کامل وضعیت؛ exit code 1 یعنی مشکل موجود).
۲. جدول `rss_post_item_translation_queues` را برای رکوردهای `error` / بدون `sent_at` در ۲۴ ساعت اخیر چک کن؛ ستون `last_error` را بخوان.
۳. برای هر رکورد مشکوک: `php artisan rss:recover --system=rss --record-id=<ID>` — **پیش‌فرض dry-run است، فقط گزارش می‌دهد.**
۴. اگر نتیجه `retryable` بود: `php artisan rss:recover --system=rss --record-id=<ID> --execute --admin-chat-id=<chat-id-مدیر>` (حداکثر ۱۰۰ رکورد در هر اجرا، قفل اجرایی ۱۸۰۰ ثانیه).
۵. اگر همه فیدها می‌افتند: `php artisan rss:feed-test --url=<url>` و لاگ‌های کلید `RSS pipeline run` در `storage/logs/laravel.log`.
۶. جزئیات کامل: بخش [§۵ — جریان عیب‌یابی](#5-جریان-عیب‌یابیtroubleshooting) و سند [`docs/JOBS_AND_SCHEDULER.md`](docs/JOBS_AND_SCHEDULER.md).

---

## ۱. هویت پروژه (همهٔ موارد از کد/اسکریپت‌ها تأیید شده)

| مورد | مقدار | منبع تأیید |
|---|---|---|
| مخزن GitHub | `https://github.com/saber13812002/bot-generator-bale-telegram-laravel-10` | `git remote` |
| شاخهٔ کاری | `15esfand1404` | `git branch` (وضعیت محلی تأیید شده؛ وضعیت ریموت از محل محلی **تأییدنشده**) |
| مسیر پراکشن | `/home/pardisa2/bots` | [`deploy_commands.sh`](deploy_commands.sh)، [`DEPLOY_INSTRUCTIONS.md`](DEPLOY_INSTRUCTIONS.md) |
| فریم‌ورک | Laravel 10 | `composer.json` |
| PHP پراکشن | 8.2.34 | از مستندات/گزارش مالک (ماشین محلی این تحلیل PHP 8.5+ اجرا می‌کند — تفاوت deprecation‌ها را ببین [§۶](#6-قرارداد-کیفیت-تست)) |
| دیتابیس | MySQL | `config/database.php` |
| زمان‌بندی | cPanel، **فقط یک خط cron**: `* * * * * cd /home/pardisa2/bots && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1` | [`docs/SCHEDULER_CRON_PLAN.md`](docs/SCHEDULER_CRON_PLAN.md)، بررسی در `deploy_commands.sh` |
| Timezone اپلیکیشن | **UTC** | [`config/app.php`](config/app.php) خط 76: `'timezone' => 'UTC'` |
| صف (Queue) | `QUEUE_CONNECTION=sync` — جاب‌ها inline اجرا می‌شوند؛ `queue:work` **وجود ندارد** و لازم نیست | `config/queue.php` + [§۳ [`docs/JOBS_AND_SCHEDULER.md`](docs/JOBS_AND_SCHEDULER.md)] |
| پیام‌رسان‌ها | بله، تلگرام، ایطلا، سoroush (متنی) | [`PostUrlParser::HOSTS`](app/Services/ContentSync/PostUrlParser.php) |

> **هشدار:** زمان‌های scheduler (مثلاً اسلات‌های 00/06/12/18) **UTC** هستند. تهران = UTC+3:30. هرگز اسلات‌های شش‌ساعته را بدون درک `PostOverdueWatchdog::findMissedDailySlots` تغییر نده.

---

## ۲. قوانین طلایی (غیرقابل مذاکره)

1. **فقط یک خط cron** (`schedule:run`). هرگز cron مستقیم برای command جدا اضافه نکن؛ تداخل با `withoutOverlapping` و قفل‌های داخلی ایجاد می‌شود (دقیقاً همین تداخل — cron مستقل 19 دقیقه‌ای قدیمی + entry 15 دقیقه‌ای — باعث ساخته‌شدن قفل `rss_read_translate_lock` در [`RssReadTranslate.php`](app/Console/Commands/RssReadTranslate.php) شده است).
2. **هیچ موفقیت جعلی نیست.** وضعیت `sent`/`published` فقط با پاسخ واقعی پیام‌رسان ثبت می‌شود (`isMessengerSuccess` / بررسی `ok` flag).
3. **`file_id` فقط در پلتفرم مبدأ معتبر است.** انتشار متقاطع بین پلتفرم‌ها نیاز به re-upload multipart دارد ([`ChannelPosterMediaSender`](app/Services/ChannelPosterMediaSender.php)، `TelegramChannelPosterPublisher::tryFileId` فقط origin platform).
4. **سقف رسانه 50 مگابایت** است (`MAX_BYTES = 50*1024*1024` در [`ChannelPosterMediaStore`](app/Services/ChannelPosterMediaStore.php)) — فرض 20MB را رها کن.
5. **فقط رکوردهای `pending` منتشر می‌شوند** (Channel Poster)؛ بازنشر مقصدی که `channel_poster_publish_logs` آن موفق است، انجام نمی‌شود.
6. **ریکاوری همیشه dry-run-first:** `rss:recover` بدون `--execute` فقط گزارش می‌دهد؛ `--execute` بدون `--admin-chat-id` معتبر رد می‌شود.
7. **Secret در مستند/لاگ نمی‌آید.** همهٔ لاگ‌های دارای token باید از [`TokenHelper::redactTokens`](app/Helpers/TokenHelper.php) عبور کنند.
8. **اسلات‌های شش‌ساعته `daily-channel:post` (00/06/12/18 UTC) دست نخورده می‌مانند** تا وقتی `PostOverdueWatchdog` و `shouldRunInSlot` بازنویسی نشوند.
9. **تست واقعی اجرا کن و عدد واقعی گزارش بده.** `php -l` مدرک رفتار نیست. هرگز ادعا نکن تستی را اجرا کرده‌ای که اجرا نکرده‌ای.
10. **مستندات هر فیچر جدید** طبق [`.agents/rules/DOCUMENTATION_RULE.md`](.agents/rules/DOCUMENTATION_RULE.md) باید در `docs/features/` + سناریوی تست داشته باشد.

---

## ۳. نقشهٔ زیرسیستم‌ها

### 3.1 خط لولهٔ RSS (خواندن → ترجمه → انتشار)

جریان کامل (هر 15 دقیقه توسط `app:rss_read_translate`):

```
cron schedule:run ──► app:rss_read_translate  (Kernel: everyFifteenMinutes + withoutOverlapping)
   │  قفل داخلی: Cache lock 'rss_read_translate_lock' TTL 900s (fallback crash)
   ├─ مرحله 1: RssItemService::run
   │    └─ برای هر فید فعال: RssService::readRssAndSave
   │         └─ Http::timeout(15) → تشخیص HTML به‌جای XML → parsePubDate (null امن)
   │         └─ insert RssPostItem با تحمل duplicate-entry (race-safe)
   ├─ مرحله 2: RssPostItemTranslationToMessengerService::run(limit=5 | 20 اگر --feed-id)
   │    └─ ساخت رکورد RssPostItemTranslationQueue
   │         └─ model boot() ⟶ dispatch خودکار RssPostItemTranslationToMessengerJob  ⚠️ خودکار، دستی نیست
   └─ exit code: 0 = ok/partial ، 1 = همهٔ فیدها شکست یا crash
```

جاب انتشار ([`RssPostItemTranslationToMessengerJob`](app/Jobs/RssPostItemTranslationToMessengerJob.php)، 529 خط):
- `timeout = 45` (فقط با worker واقعی معنا دارد؛ در sync نادیده گرفته می‌شود).
- ساخت پیام از `RssHelper::createMessage` → `RssChannelMessageSender::sendBaseMessage`.
- **شکست base** (مثلاً 403 permission_denied) → `markFailed` و توقف — **هیچ call رسانهٔ بی‌فایده‌ای نمی‌شود.**
- **موفقیت base** → `recordBaseSuccess`: `sent_at=now()`، `message_id` (از `message_id`/`id`/`result.message_id`)، `attempts+1`، پاک‌سازی `last_error`/`media_error`.
- سپس `sendMedia`: عکس از `image_url` + موارد ویژهٔ سایت:
  - `navaar.ir` → sample.mp3 با UUID کتاب
  - `songsara.net` → `WebPageMediaFindSave::fetchAndSaveMp3Url`
  - `balaghah.net` → `NahjController`
  - `sharabebeheshti.ir` → `SharabeBeheshtiMp3Controller` + `BotHealthRecorder`
- **اگر base رسیده باشد، رکورد همیشه `sent` می‌شود** حتی اگر رسانه خطا داده باشد (خطا در `media_error` ثبت می‌شود) — چون بازنشر = دوباره‌فرستادن متن.
- exception **قبل** از delivery base → `markFailed`.
- throttle لاگ: `rss_msg_job_log_throttle_<channel>` TTL 1800s؛ health → `BotHealthRecorder` (feature_key `rss_channel_post`).

**ریکاوری** ([`RssRecoveryService`](app/Services/RssRecoveryService.php) + [`rss:recover`](app/Console/Commands/RssRecover.php)):
- تفکیک رکوردها: `delivered | retryable | needs_review | insufficient_evidence | cancelled | pending`.
- **نیمکت تازگی: `MAX_RETRY_AGE_HOURS = 48`** — رکورد قدیمی‌تر از 48 ساعت retry نمی‌شود (جلوگیری از اسپم محتوای کهنه).
- خطاهای مبهم (markers: `timeout` و … در `AMBIGUOUS_ERROR_MARKERS`) → `needs_review`، resend کور انجام نمی‌شود.
- قفل اجرا: `rss_recover_exec_lock` TTL 1800s؛ سقف سخت 100 رکورد هر سیستم.
- `cancelled` (channel-poster) هرگز بدون تأیید دستی بازنویسی نمی‌شود.
- `lockForUpdate` + re-check وضعیت هنگام execute (race-safe).

**آزمایش سلامت فیدها:** `rss:feeds-health` هفتگی (options فعلی Kernel: `--disable-dead --enable-healthy --limit=100`) و `rss:feed-test` دستی.

> **تاریخچهٔ مهم:** 106,940 رکورد صف و 88 فید، **snapshot تاریخی** است — عدد فعلی دیتابیس را هرگز از این مستندات استنباط نکن.

### 3.2 Channel Poster (انتشار چندکاناله)

- **مدل‌ها:** [`ChannelPosterQueue`](app/Models/ChannelPosterQueue.php) (statusها: `pending | published | failed | cancelled` + `source_attribution`)، [`ChannelPosterDestination`](app/Models/ChannelPosterDestination.php)، [`ChannelPosterPublishLog`](app/Models/ChannelPosterPublishLog.php) (رویداد broadcast به هر مقصد: `success`, `message_id`, `error`, `post_url`, `link_status`).
- **مقیام انتشار:** `ChannelPosterQueuePublishService::publishItem($item, $notifyOwner)` — فقط `pending`؛ برای هر مقصد یک log؛ publisherها per-platform: `TelegramChannelPosterPublisher` (تلفیقی برای ble/tg/eitaa بر اساس origin)، `BaleChannelPosterPublisher`، `EitaaChannelPosterPublisher`، Soroush = متن‌فقط.
- **رسانه:** `ChannelPosterMediaStore::fetchAndStore` (حداکثر 50MB، مسیر `storage/app/channel-poster-media/`) → `ChannelPosterMediaSender::send` (multipart upload؛ field بر اساس contentType: `photo`/`video`/`document`/`audio`).
- **پردازش صف:** `channel-poster:process` هر 5 دقیقه → `ProcessChannelPosterQueueJob` (`STALE_DAYS=7`: آیتم‌های pending با `scheduled_at` قبل از 7 روز → cancelled؛ سپس 10 آیتم `ready()` اول به‌ترتیب `scheduled_at`).
- **Watchdog:** `channel-poster:watchdog` هر ساعت ([`PostOverdueWatchdog`](app/Console/Commands/PostOverdueWatchdog.php)): grace 15 دقیقه، max age 7 روز؛ machine وضعیت `ok|red` در cache (TTL 48h)؛ هشدار red هر ساعت تا بهبود + یک هشدار green در بهبود؛ تشخیص missed daily slot از رویدادهای `BotHealthEvent` (event_type `channel_post`، `meta.config_id`) در پنجرهٔ `[slotTime, nextSlotTime)`.
- **Prune:** `channel-poster:prune-stale` روزانه 04:00 UTC — pendingهای قدیمی‌تر از 7 روز → `cancelled` + حذف فایل رسانه.
- **ربات:** [`ChannelPosterBotController`](app/Http/Controllers/ChannelPosterBotController.php) (2520 خط، endpoint `webhook-channel-poster`) — 14 وضعیت وایزارد + 3 وضعیت sync.
- **دستورات ربات (تأییدشده از کد):** `/start` `/cancel` `/add` `/sign` `/queue` `/new` `/reset` `/mode` `/status` `/report` `/retry` `/last` `/sync` `/tags` `/admin` `/channels` `/settings` `/help`.

### 3.3 Content Sync (همگام‌سازی محتوا)

- **سرویس مرکزی:** [`ContentSyncService`](app/Services/ContentSync/ContentSyncService.php) (1251 خط):
  - `registerSource` / `attachText` / `resolveTag` / `resolveTargetsDetailed` / `publishImmediate` / `enqueueForTag` / `recordQueueResults` / `retryFailed`.
  - **حالت‌های dedup:** `exclude_source_channel` (فقط کانال مبدأ حذف می‌شود) و `exclude_source_platform` (کل پلتفرم مبدأ حذف می‌شود). مقصدی که با مبدأ match شود → status `skipped` / `existing_source`.
  - **منبع:** `sourceChannelVerdict` — آیا این مقصد همان کانال مبدأ خود محتواست؟ `recordSourceAssociation` این تطبیق را ذخیره می‌کند.
  - **انتسب (attribution):** سه حالت `signed | link_only | none`؛ در `enqueueForTag` مقدار انتخابی **لحظهٔ صف‌شدن** در `channel_poster_queue.source_attribution` **فریز** می‌شود (در زمان ارسال از همان مقدار فریزشده استفاده می‌شود، نه ترجیحات لحظه‌ای). هیچ fallback خاموش روی tag وجود ندارد.
  - `retryFailed`: فقط نسخه‌های `failed` که `destination_id` دارند؛ dedup دوباره چک می‌شود (کانال مبدأ هرگز باز-نشر نمی‌شود).
- **پارس URL:** [`PostUrlParser`](app/Services/ContentSync/PostUrlParser.php):
  - Telegram: `t.me/{user}/{id}` و `t.me/c/{short}/{id}` (کانال خصوصی فقط با chat id عددی match می‌شود)
  - Bale: `ble.ir/{user}/{chatId}/{msgId}` (3 segment)
  - Eitaa: `eitaa.com`/`my.eitaa.com`
  - Soroush: `soroush.chat`/`splus.ir` — **فرمت canonical سoroush تأییدنشده**
- **تنظیمات:** [`ContentSyncAdminPrefs`](app/Models/ContentSyncAdminPrefs.php) (per admin) + [`ContentSyncBotDefaults`](app/Models/ContentSyncBotDefaults.php) (per bot)؛ fast mode با تشخیص لینک در متن (`extractFromText`) فقط برای ادمین.
- **دستورات ربات:** `/sync` (لینک + متن) `/last` `/retry` `/tags` `/admin` `/channels` + کیبورد تنظیمات یک‌نفره (compact settings).
- **گزارش:** `php artisan content-sync:report` (برای migration 5000 پست هم استفاده شد).

### 3.4 Scheduler / cron

→ مرجع رسمی کامل: **[`docs/JOBS_AND_SCHEDULER.md`](docs/JOBS_AND_SCHEDULER.md)** (30 entry، هرکدام با 13 دادهٔ لازم، جدول قفل‌ها، تبدیل UTC↔تهران، چک‌لیست تغییر امن).

نکته‌های حیاتی خلاصه:
- `app:rss-post-to-translate` **در scheduler نیست** (فقط دستی/legacy).
- 3 command بدون `withoutOverlapping`: `app:task-reminder-command`، `app:users-ranking-command`، `app:test-schedule-daily-into-slack` — در سرور واحد خطر کم، در چندسرور خطر تداخل.
- `storage:logs-cleanup` در [`docs/SCHEDULER_CRON_PLAN.md`](docs/SCHEDULER_CRON_PLAN.md) ذکر شده ولی **در کد وجود ندارد** (0 نتیجهٔ جستجو) — cron آن را هرگز اضافه نکن.

---

## ۴. سریع‌نامهٔ مدل‌های داده

| مدل | جدول | کاربرد |
|---|---|---|
| [`RssItem`](app/Models/RssItem.php) | `rss_items` | فیدها (URL, `last_synced_at` به‌صورت `Y-m-d`) |
| `RssPostItem` | `rss_post_items` | آیتم‌های فید (لینک، توضیح، تصویر، `pub_date`) |
| [`RssChannel`](app/Models/RssChannel.php) | `rss_channels` | کانال مقصد RSS (با `HasTags`، `origin_id` → `RssChannelOrigin`) |
| [`RssPostItemTranslationQueue`](app/Models/RssPostItemTranslationQueue.php) | `rss_post_item_translation_queues` | صف انتشار؛ statusها: `queue / sent / error`؛ `boot()` جاب را خودکار dispatch می‌کند |
| [`ChannelPosterQueue`](app/Models/ChannelPosterQueue.php) | `channel_poster_queue` | صف انتشار چندکاناله؛ `pending/published/failed/cancelled` + `source_attribution` فریزشده |
| [`ChannelPosterDestination`](app/Models/ChannelPosterDestination.php) | `channel_poster_destinations` | مقصدها (platform, chat_id, token) |
| [`ChannelPosterPublishLog`](app/Models/ChannelPosterPublishLog.php) | `channel_poster_publish_logs` | log هر مقصد هر انتشار (idempotency) |
| [`ContentSyncContent`](app/Models/ContentSyncContent.php) | `content_sync_contents` | محتوای sync (متن فریزشده — `attachText` فقط یک‌بار) |
| [`ContentSyncVersion`](app/Models/ContentSyncVersion.php) | `content_sync_versions` | وضعیت انتشار هر مقصد: `queued/published/failed/skipped/existing_source` + `post_url`, `link_status`, `queue_id` |
| `BotHealthEvent` | `bot_health_events` | رویدادهای سلامت (watchdog missed-slot از همین‌جا می‌خواند) |
| [`Bot`](app/Models/Bot.php) / `BotUsers` / `BotUserState` | — | شناسایی ادمین (`isOwnerOrAdmin`) و state machine وایزارد |

---

## ۵. جریان عیب‌یابی (Troubleshooting)

### 5.1 مشکل انتشار RSS (سناریوی اصلی)

| مرحله | دستور/بررسی | تفسیر |
|---|---|---|
| 1 | `php artisan pipeline:status` | snapshot یک‌نمایشی: heartbeat scheduler، وضعیت RSS، صف‌ها، Channel Poster. exit 1 = مشکل. |
| 2 | `SELECT id,status,channel_id,last_error,sent_at,created_at FROM rss_post_item_translation_queues WHERE status='error' ORDER BY id DESC LIMIT 20;` | `last_error` ریشهٔ مشکل. `permission_denied`(403) = token/کانال؛ `timeout` = مبهم → `needs_review`. |
| 3 | `php artisan rss:recover --system=rss --channel=<id>` | dry-run؛ کلاس‌بندی: `retryable` (قابل باز-ارسال)، `needs_review` (مبهم)، `delivered` (رسیده — دست نزن). |
| 4 | `php artisan rss:recover --system=rss --record-id=<ID> --execute --admin-chat-id=<admin>` | resend واقعی؛ فقط اگر رکورد < 48 ساعت باشد و admin معتبر. |
| 5 | `php artisan rss:feed-test --url=<feed>` | اگر همهٔ کانال‌ها یک‌جا می‌افتند → مشکل فید/سرور لاگ. |
| 6 | `grep "RSS pipeline run" storage/logs/laravel.log` | `Fetch summary`, `exit_code`, `failed_feed_ids` هر اجرا لاگ می‌شود. |
| 7 | `php artisan pipeline:watch --dry-run` | خروجی همان چک‌هایی که هر ساعت در background می‌افتد (heartbeat، no-publish، error buildup، channel poster failures) با suppress 6 ساعته. |

### 5.2 Channel Poster گیر کرده

| مرحله | دستور/بررسی |
|---|---|
| 1 | `php artisan channel-poster:diagnose` (خلاصهٔ صف + رسانه) |
| 2 | `SELECT * FROM channel_poster_queue WHERE status IN ('pending','failed') ORDER BY id DESC LIMIT 20;` + `channel_poster_publish_logs` برای همان آیتم‌ها |
| 3 | `php artisan channel-poster:send-queued --id=<ID>` (resend تک‌آیتم، notify owner فعال) |
| 4 | وضعیت watchdog در cache: `watchdog_state:bot:<id>` (ok|red) — اگر red است، هشدار هر ساعت می‌افتد تا resolved. |
| 5 | `storage/app/channel-poster-media/` — orphan/استایل پرین در `channel-poster:prune-stale --dry-run` |

### 5.3 Scheduler اصلاً نمی‌چرخد

1. `crontab -l` → فقط همان **یک خط** `schedule:run` باید باشد.
2. `php artisan schedule:list` → 30 entry باید دیده شود.
3. heartbeat: `pipeline:status` بخش scheduler؛ کلید cache `pipeline:heartbeat:ts` / `pipeline:heartbeat:at` (TTL 600s).
4. اگر `schedule:run` در crontab نیست: ببینید [§4 Rollback/Deploy در `docs/PRODUCTION_RUNBOOK.md`](docs/PRODUCTION_RUNBOOK.md).

---

## ۶. قرارداد کیفیت تست

- **نتیجهٔ واقعی اجرا (2026-10-19، ماشین محلی):**
  - `Tests: 2 deprecated, 2 skipped, 399 passed (2205 assertions)` — **0 failure**، مدت 108.41 ثانیه.
  - 403 روش تست به‌طور کل وجود دارد (399 passed + 2 skipped + 2 deprecated). اختلاف «403 در مقابل 402» گزارش‌شده همین است: 2 تست deprecated + 2 تست skipped خارج از شمارش passed.
  - تعداد assertion واقعی 2205 است (نه 2198 که در commit 60880a6c ذکر شده).
- **قید محیط:** PHP محلی 8.5+ است → deprecation notices زیادی در خروجی (exit code کل run به‌خاطر deprecation = 1، نه failure تست). پراکشن PHP 8.2.34 است و این نویز را ندارد.
- **قوانین:**
  - اجرا: `php artisan test --compact`
  - هرگز `php -l` را به‌عنوان مدرک رفتار ارائه نده.
  - هرگز ادعای اجرای تستی را نده که اجرا نکرده‌ای.
  - پس از هر تغییر: تست‌های حوزهٔ مربوطه + گزارش عدد واقعی.

---

## ۷. امنیت

| مورد | وضعیت |
|---|---|
| [`TokenHelper::redactTokens`](app/Helpers/TokenHelper.php) | **تأییدشده:** در `RssService`، `RssReadTranslate` (خطای crash)، `ChannelPosterBotController` (همهٔ error logها) و سایر لاگ‌های token-bearing استفاده می‌شود. هر لاگ جدیدی که ممکن است token داشته باشد **باید** از این helper رد شود. |
| [`AdminHelper::isAdmin`](app/Helpers/AdminHelper.php) | chat_idهای ادمین از 6 متغیر env: `CHAT_ID_ACCOUNT_1_SABER`, `CHAT_ID_ACCOUNT_2_SABER`, `SUPER_ADMIN_CHAT_ID_TELEGRAM`, `SUPER_ADMIN_CHAT_ID_BALE`, `SUPER_ADMIN_CHAT_ID_BALE2`, `SUPER_ADMIN_CHAT_ID_GAP`. |
| **لیک توکن Bale در لاگ** | از تاریخچهٔ پروژه (گزارش مالک): توکن Bale در لاگ دیده شده. **آیا rotate شده؟ تأییدنشده.** → **اقدام پراکشن:** توکن را در پنل Ble rotate کن و `.env` پراکشن را به‌روزرسانی کن؛ بعد از آن در این جدول علامت بزن. |
| اصل عمومی | هیچ secret/توکن/کلید API در هیچ مستند، commit، لاگ یا پاسخ agent. مقدار env فقط نامش ذکر می‌شود (مطابق سبک [`.agents/AGENTS.md`](.agents/AGENTS.md)). |

---

## ۸. مشکلات تاریخی و یافته‌های شناخته‌شده

| # | موضوع | وضعیت |
|---|---|---|
| 1 | اختلاف 403/402 در شمارش تست | **حل شد** — اجرای واقعی: 403 روش = 399 passed + 2 skipped + 2 deprecated |
| 2 | توکن Bale لیک‌شده در لاگ | **اقدام پراکشن باز** — rotate باید انجام/تأیید شود (تأییدنشده) |
| 3 | `storage:logs-cleanup` در `SCHEDULER_CRON_PLAN.md` | **در کد وجود ندارد** — هرگز cron آن را اضافه نکن |
| 4 | 3 command بدون `withoutOverlapping` (`task-reminder`, `users-ranking`, `test-schedule-daily-into-slack`) | یافتهٔ مستندشده در `docs/JOBS_AND_SCHEDULER.md` — در چندسرور ریسک تداخل |
| 5 | تداخل cron 19 دقیقه‌ای قدیمی با scheduler 15 دقیقه‌ای | **حل شد** — قفل `rss_read_translate_lock` (TTL 900s) + حذف cron جدا |
| 6 | اسنپ‌شات 106,940 رکورد صف RSS / 88 فید | **تاریخی** — به‌عنوان وضعیت فعلی هرگز استفاده نشود |
| 7 | 1805 رکورد تاریخی channel-poster در `rss:recover` | فقط گزارش dry-run؛ resend انبوه بدون تأیید تک‌تک ممنوع (سند [`RssRecover.php`](app/Console/Commands/RssRecover.php)) |
| 8 | پشتیبانی MP3/دکومنت در کانال‌پوستر (commit 7cdd2d79) | در کد هست؛ **در CHANGELOG ثبت نشده** (در جریان تکمیل) |
| 9 | نیمکت تازگی 48 ساعت در `rss:recover` (commits 394317b3 / 13f1eca6) | در کد هست؛ **در CHANGELOG ثبت نشده** (در جریان تکمیل) |
| 10 | timezone اپلیکیشن | **تأییدشده UTC** — `config/app.php` خط 76 |
| 11 | Content Sync Phase 4 + UX compact + API Token hardening | ثبت شده در `CHANGELOG.md` (2026-10-15/16) |
| 12 | نویز deprecation در PHP 8.5+ محلی در مقابل 8.2.34 پراکشن | شناخته‌شده — تفاوت خروجی `artisan test` محلی/پراکشن |
| 13 | فایل‌های بزرگ نیازمند refactor (`BotMotherController` 199KB, `QuranWordController` 155KB, …) | فهرست در [`.agents/AGENTS.md`](.agents/AGENTS.md) بخش 10 |

---

## ۹. موارد تأییدنشده (هرگز به‌عنوان واقعیت نده)

- اینکه توکن Bale لیک‌شده **rotate شده** یا خیر.
- فرمت canonical URL سoroush (در `PostUrlParser` هست ولی عملاً test-شده نیست).
- وضعیت ریموت Git از دیدگاه محلی (فقط state محلی تأیید شده).
- مقادیر `.env` پراکشن (فقط نام متغیرها از [`.agents/AGENTS.md`](.agents/AGENTS.md) و اسکریپت‌ها).
- هر آمار دیتابیس فعلی (تعداد رکوردها/فیدها/کانال‌ها) — باید از دیتابیس زنده پرسیده شود.

---

## ۱۰. نمایهٔ مستندات و لینک‌های متقاطع

| سند | محتوا |
|---|---|
| [`docs/AGENT_HANDOFF.md`](docs/AGENT_HANDOFF.md) | **این فایل** — نقطهٔ شروع agent |
| [`docs/JOBS_AND_SCHEDULER.md`](docs/JOBS_AND_SCHEDULER.md) | مرجع رسمی همهٔ jobها (13 داده برای هرکدام)، قفل‌ها، UTC↔تهران، دیباگ cron |
| [`docs/PRODUCTION_RUNBOOK.md`](docs/PRODUCTION_RUNBOOK.md) | Deploy / migrate / diagnose / rollback |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | معماری کلی، دیاگرام جریان، نقش‌ها |
| [`README.md`](README.md) | نقطهٔ ورود عملیاتی (concise) |
| [`CHANGELOG.md`](CHANGELOG.md) | تاریخچهٔ تغییرات |
| [`DEPLOY_INSTRUCTIONS.md`](DEPLOY_INSTRUCTIONS.md) / [`deploy_commands.sh`](deploy_commands.sh) | دستورالعمل deploy + اسکریپت با چک crontab |
| [`docs/SCHEDULER_CRON_PLAN.md`](docs/SCHEDULER_CRON_PLAN.md) | برنامهٔ اولیهٔ cron (⚠️ مرجع `storage:logs-cleanup` منسوخ/غیرموجود) |
| [`docs/RECOVERY_ROLLOUT_PLAN.md`](docs/RECOVERY_ROLLOUT_PLAN.md) | برنامهٔ رول‌اوت مرحله‌ای محتوا + rollback |
| [`.agents/AGENTS.md`](.agents/AGENTS.md) | دانش‌نامهٔ Antigravity (کاتالوگ 37 ربات، ساختار، env names، APIها) |
| [`.agents/rules/DOCUMENTATION_RULE.md`](.agents/rules/DOCUMENTATION_RULE.md) | قانون مستندسازی فیچرهای جدید |
| [`docs/features/`](docs/features/) | مستندات فیچر-به-فیچر + سناریوی تست |
| [`docs/QUICK_REFERENCE.md`](docs/QUICK_REFERENCE.md) | سریع‌نامهٔ قدیمی (مکمل) |

---

## ۱۱. چرخهٔ کار agent و قالب گزارش پایانی

هر agent پس از هر وظیفه باید این گزارش را بدهد (مطابق دستورالعمل مالک):

1. **فایل‌های لمس‌شده** (ساخته/تغییر/حذف) با وضعیت.
2. **یافته‌های تأییدشده** — هر مورد با منبع (فایل+خط یا خروجی command).
3. **یافته‌های غیرمؤیّد** — با برچسب «تأییدنشده».
4. **خروجی تست واقعی** (تعداد passed/failed/skipped/deprecated + assertions + مدت) — یا صریحاً «تست اجرا نشد».
5. **ابهام‌ها** و تصمیم‌های گرفته‌شده.
6. **کارهای باقی‌مانده** (action items پراکشن، refactorها، موارد §۹).
7. **تأثیر روی scheduler/cron** (اگر هر چیزی در Kernel یا crontab لمس شد).
8. **امنیت:** آیا چیزی token-bearing اضافه شد؟ `redactTokens` چک شد؟
9. **مستندات:** آیا مستندات زیرسیستم به‌روزرسانی شد؟ (`docs/features/` طبق قانون)
10. **خطرات:** چه چیزی ممکن است در پراکشن بشکند؟
11. **تأیید نهایی:** `git status` تمیز؟ merge-conflict نبود؟

---

## ۱۲. قوانین کار روی پراکشن

- **دسترس مستقیم به پراکشن از محل agent وجود ندارد.** همهٔ تغییرات پراکشنی در قالب دستورالعمل/اسکریپت/چک‌لیست برای مالک نوشته می‌شوند (مطابق [`docs/PRODUCTION_RUNBOOK.md`](docs/PRODUCTION_RUNBOOK.md)).
- **اول dry-run، بعد execute.** (rss:recover، prune-stale، watchdog، media-queue:post و…)
- **rollback plan هر deploy** باید در همین commit/PR باشد (مطابق `docs/PRODUCTION_RUNBOOK.md` §Rollback).
- **هرگز cron جدید اضافه نکن** بدون آپدیت همزمان `docs/JOBS_AND_SCHEDULER.md`.
- **migrate فقط با `--force` در deploy** و بعد از backup (در RUNBOOK).

---

*آخرین به‌روزرسانی: 2026-10-19 — پس از اجرای کامل تست (399 passed / 2205 assertions) و تطبیق کامل کد. نویسنده: agent مستندسازی (بر اساس MASTER PROMPT مالک).*
