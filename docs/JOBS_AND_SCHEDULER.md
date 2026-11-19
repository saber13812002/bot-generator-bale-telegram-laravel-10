# JOBS & SCHEDULER — مرجع رسمی Cron / Scheduler پروژه Bots

> **حرفه‌ای:** این فایل مرجع رسمی همه کارهای زمان‌بندی‌شده است. قبل از هر تغییری در
> Scheduler، cron سرور، یا هر command این فهرست را بخوانید و بعد از تغییر،
> `php artisan schedule:list` را دوباره اجرا و مقایسه کنید.
>
> **شواهد:** [app/Console/Kernel.php](../app/Console/Kernel.php) + خروجی واقعی
> `php artisan schedule:list` (اجراشده ۲۰۲۶-۱۰-۱۹ روی branch `15esfand1404`).
>
> ⏱️ **مهم — UTC:** همه ساعت‌های زیر **UTC** هستند (سرور UTC است،
> `config('app.timezone') = 'UTC'` در [config/app.php](../config/app.php)).
> ساعت ایران = UTC+3:30. **اسلات‌های `daily-channel:post` (00/06/12/18 UTC) را
> به هیچ‌وجه به ساعت ایران تغییر ندهید** — منطق `shouldRunInSlot` و
> `PostOverdueWatchdog` دقیقاً بر همین اسلات‌هاستوارند (تفصیل در بخش ۷).

---

## ۰) معماری کلی اجرا

| لایه | مقدار |
|------|-------|
| Trigger | cron سرور cPanel: `* * * * * cd /home/pardisa2/bots && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1` (فقط همین یک خط ضروری) |
| Scheduler | Laravel Scheduler داخل [app/Console/Kernel.php](../app/Console/Kernel.php) |
| Queue | `QUEUE_CONNECTION=sync` — جاب‌ها **inline** اجرا می‌شوند؛ `queue:work` وجود ندارد و لازم نیست |
| Concurrency | `withoutOverlapping()` روی همه entry‌ها (قفل Cache) + `onOneServer()` روی اکثر entry‌ها (lock چندسروری از طریق Cache) |
| Heartbeat | `pipeline:heartbeat` هر دقیقه → `pipeline:status` / `pipeline:watch` از روی آن تشخیص می‌دهند که scheduler زنده است |
| زمان‌بندی | PHP 8.2، Laravel 10، cPanel cron |

### قواعد طلایی (از تاریخچه‌ی مشکلات)

1. **فقط یک خط cron** برای `schedule:run` هر دقیقه. cron‌های مستقیم روی commandها
   (مثل cron مستقل 19 دقیقه‌ای قدیمی `app:rss_read_translate`) **حذف شدند** چون
   بدون قفل، هم‌زمانی دو اجرای همزمان → تکرار پیام و خطا. تاریخچه:
   [docs/SCHEDULER_CRON_PLAN.md](SCHEDULER_CRON_PLAN.md).
2. **ساعت‌ها UTC هستند.** هر `dailyAt` در Kernel به UTC مربوط است؛ کامنت‌های
   «iran» کنار بعضی خط‌ها فقط راهنمای نمایشی است.
3. `php -l` یا «اجرا نشد = خراب» به‌عنوان اثبات استفاده نکنید؛ `schedule:list`
   و لاگ `storage/logs/laravel.log` منبع حقیقت‌اند.
4. هر entry جدید **باید** `withoutOverlapping()` (و ترجیحاً `onOneServer()`) داشته باشد.
5. `schedule:run` خودش قفل دقیقه‌ای دارد؛ ورودی‌های دست‌نویس cron که مستقیم
   command را اجرا کنند، دفاع هم‌زمانی Kernel را دور می‌زنند.

---

## ۱) فهرست کامل Entry‌های Scheduler

فرمت هر ردیف (۱۳ داده‌ی موردنیاز):

1. نام command (نام دقیق artisan)
2. کلاس Command (فایل)
3. زمان/cron (UTC)
4. Options/آرگومان‌ها
5. بدون تداخل (withoutOverlapping)؟
6. onOneServer؟
7. وظیفه (یک خط)
8. مکانیزم اجرای Job (sync/inline)
9. ورودی‌های کلیدی (جدول/env)
10. خروجی/ثبت (جدول، لاگ، پیام)
11. خطای رایج و نشانه در لاگ
12. دستورات عیب‌یابی مرتبط
13. مرتبط با چه زیرسیستمی / هشدار

### 1.1 — خط لوله محتوا (RSS + Channel Poster)

#### `pipeline:heartbeat`

| # | داده |
|---|------|
| 1 | `pipeline:heartbeat` |
| 2 | [app/Console/Commands/PipelineHeartbeat.php](../app/Console/Commands/PipelineHeartbeat.php) |
| 3 | `* * * * *` (هر دقیقه) |
| 4 | بدون option |
| 5 | ✅ `withoutOverlapping()` |
| 6 | ❌ (سبک‌ترین entry؛ lock محلی کافی است) |
| 7 | ثبت `pipeline:heartbeat:ts` + `pipeline:heartbeat:at` (TTL 600s) در Cache — تنها مدره زنده‌بودن scheduler |
| 8 | sync (دو خط Cache::put) |
| 9 | — |
| 10 | Cache keys `pipeline:heartbeat:ts` / `:at` |
| 11 | اگر `pipeline:status` سن heartbeat را >600s ببیند → exit 1 (scheduler/cron مرد) |
| 12 | `php artisan pipeline:status` |
| 13 | زیرسیستم: پایش؛ اگر این نباشد **هیچ** هشدار SCHEDULER-STALE‌ای ساخته نمی‌شود |

#### `app:rss_read_translate`

| # | داده |
|---|------|
| 1 | `app:rss_read_translate` |
| 2 | [app/Console/Commands/RssReadTranslate.php](../app/Console/Commands/RssReadTranslate.php) |
| 3 | `*/15 * * * *` (هر ۱۵ دقیقه) |
| 4 | بدون option در schedule (options موجود: `--switch`, `--feed-id=`, `--dry-run`, `--limit=`, `--no-translate`) |
| 5 | ✅ `withoutOverlapping()` |
| 6 | ❌ + قفل دوم: Cache lock `rss_read_translate_lock` TTL **900 ثانیه** (داخل command؛ get/skip/release in finally) |
| 7 | خواندن فیدهای سرِوقت RSS (`RssItemService::run`) + ترجمه/ثبت برای ارسال (`RssPostItemTranslationToMessengerService::run` — limit 5، یا 20 اگر `--feed-id`) |
| 8 | sync (inline؛ HTTP fetch با timeout 15s برای هر فید) |
| 9 | `rss_items` (فیدها + `interval_minutes` due filter + anti-starvation بر اساس `last_synced_at` قدیمی‌ترین اول) |
| 10 | `rss_post_items` (آیتم‌های جدید)، `rss_post_item_translations`، `rss_post_item_translation_queues` (status `queue`)، لاگ `[RssReadTranslate]` با `duration_ms`؛ خروجی 1 در صورت شکست command-level یا شکست همه فیدهای هدف |
| 11 | `RssService: failed to read RSS feed` (با `rss_item_id` و error redacted)؛ `rss_read_translate_lock is held — skipping`؛ خروجی 1 = خطا |
| 12 | `php artisan pipeline:status`؛ `php artisan app:rss_read_translate --dry-run --limit=5`؛ `php artisan rss:feed-test --feed-id=N`؛ `php artisan rss:feeds-health --dry-run` |
| 13 | زیرسیستم RSS fetch+translate؛ ⚠️ این command **حذف** نشد و نباید cron مستقل جدا داشته باشد (تاریخچه: cron 19 دقیقه‌ای قدیمی → حذف شد) |

#### `channel-poster:process`

| # | داده |
|---|------|
| 1 | `channel-poster:process` |
| 2 | [app/Console/Commands/ChannelPosterProcess.php](../app/Console/Commands/ChannelPosterProcess.php) |
| 3 | `*/5 * * * *` (هر ۵ دقیقه) |
| 4 | بدون option |
| 5 | ✅ `withoutOverlapping()` |
| 6 | ✅ `onOneServer()` |
| 7 | پردازش صف انتشار کانال: `ProcessChannelPosterQueueJob::dispatchSync()` — لغو آیتم‌های pending قدیمی (>7 روز) + انتشار حداکثر 10 آیتم ready (`scheduled_at <= now`) |
| 8 | sync (`dispatchSync` = inline؛ به `queue:work` وابسته نیست) |
| 9 | `channel_poster_queue` (scope `ready()` = pending + scheduled_at <= now) |
| 10 | `channel_poster_queue.status` → published/failed/cancelled، `channel_poster_publish_logs`، فایل‌های رسانه در `storage/app/channel-poster-media/` (حذف بعد از موفقیت)، لاگ `[ChannelPoster] ... (diagnostic marker)` |
| 11 | آیتم در صف می‌ماند ولی هر 5 دقیقه تکرار نمی‌شود چون فقط `ready` اجرا می‌شود و watchdog ساعتی backstop است؛ `❌ resend #N → failed: <reason>` |
| 12 | `php artisan channel-poster:diagnose`؛ `php artisan channel-poster:send-queued --id=N`؛ `php artisan channel-poster:watchdog --dry-run` |
| 13 | زیرسیستم Channel Poster؛ backstop = `channel-poster:watchdog` (ساعتی) |

#### `channel-poster:watchdog`

| # | داده |
|---|------|
| 1 | `channel-poster:watchdog` |
| 2 | [app/Console/Commands/PostOverdueWatchdog.php](../app/Console/Commands/PostOverdueWatchdog.php) |
| 3 | `0 * * * *` (هر ساعت، دقیقه 0) |
| 4 | `{--dry-run : فقط گزارش بده؛ ارسال مجدد و هشدار انجام نده}` |
| 5 | ✅ `withoutOverlapping()` |
| 6 | ✅ `onOneServer()` |
| 7 | نگهبان ساعتی صف کانال: (1) resend آیتم‌های pending با grace 15 دقیقه (حداکثر 7 روز)، (2) شناسایی بوت‌های «قرمز»، (3) بررسی اسلات‌های روزانه `PostDailyVerseToChannels` از روی `BotHealthEvent`، (4) هشدار قرمز (هر ساعت تا حل) / سبز (یک‌بار هنگام recovery) |
| 8 | sync (inline publishItem) |
| 9 | `channel_poster_queue` (pending/failed با scheduled_at در بازه [now-7d, now-15m])، `admin_daily_channel_configs` + `bot_health_events` |
| 10 | Cache keys `watchdog_state:bot:<id>`, `watchdog_state:red_bots`, `watchdog_state:red_daily` (TTL 48h)؛ پیام‌های `🔴 Channel Poster OVERDUE` / `🟢 Channel Poster RECOVERED` / `🔴 Daily Channel Slots MISSED` از طریق `AiProviderService::notifyAllAdmins`؛ exit 1 اگر هر قرمزی باشد |
| 11 | `🐕 PostOverdueWatchdog شروع شد` + `📊 نتیجه:`؛ `❌ resend #N → failed` |
| 12 | `php artisan channel-poster:watchdog --dry-run` (سایر: `channel-poster:diagnose`) |
| 13 | backstop کانال؛ ⚠️ اسلات‌های روزانه را با UTC محاسبه می‌کند — اگر `daily-channel:post` جابه‌جا شود، اینجا هم باید هم‌زمان تغییر کند |

#### `channel-poster:prune-stale`

| # | داده |
|---|------|
| 1 | `channel-poster:prune-stale` |
| 2 | [app/Console/Commands/ChannelPosterPruneStale.php](../app/Console/Commands/ChannelPosterPruneStale.php) |
| 3 | `0 4 * * *` (روزانه 04:00 UTC) |
| 4 | `{--days=7 : ...}` `{--dry-run : ...}` |
| 5 | ✅ `withoutOverlapping()` |
| 6 | ✅ `onOneServer()` |
| 7 | لغو (cancel) آیتم‌های pending باقدمت >7 روز (تا محتوای کهنه منتشر نشود) + حذف فایل رسانه‌ی آن‌ها + prune فایل‌های یتیم |
| 8 | sync |
| 9 | `channel_poster_queue` (pending، scheduled_at < now-7d) |
| 10 | `status=cancelled` + `media_path=null` + حذف فایل؛ لاگ `[ChannelPoster] Pruned stale pending queue items` |
| 11 | `Cancelled N stale item(s); removed N media file(s), N orphan file(s)` — خروجی 0 همیشه (مگر exception) |
| 12 | `php artisan channel-poster:prune-stale --dry-run` (بررسی بدون تغییر) |
| 13 | زیرسیستم Channel Poster / مدیریت دیسک (50MB cap هر فایل، `pruneOrphans`/`pruneStale` در [ChannelPosterMediaStore.php](../app/Services/ChannelPosterMediaStore.php)) |

#### `daily-channel:post --slot=N` (4 entry)

| # | داده |
|---|------|
| 1 | `daily-channel:post` (اسلات 1..4) |
| 2 | [app/Console/Commands/PostDailyVerseToChannels.php](../app/Console/Commands/PostDailyVerseToChannels.php) |
| 3 | `0 0 * * *` / `0 6 * * *` / `0 12 * * *` / `0 18 * * *` — **UTC** |
| 4 | `{--slot= : اسلات ۱–۴ (۱=۰۰:۰۰، ۲=۰۶:۰۰، ۳=۱۲:۰۰، ۴=۱۸:۰۰). اگر ندهی از ساعت فعلی محاسبه می‌شود}` |
| 5 | ✅ `withoutOverlapping()` |
| 6 | ✅ `onOneServer()` |
| 7 | ارسال آیه/حدیث/نهج/شراب‌بهشتی روزانه به کانال‌های ادمین؛ هر `AdminDailyChannelConfig` بر اساس `posts_per_day` در 1/2/4 اسلات ارسال می‌کند (`shouldRunInSlot`) |
| 8 | sync |
| 9 | `admin_daily_channel_configs` (is_active + slot mask) |
| 10 | پیام به کانال‌ها + `bot_health_events` (event_type `channel_post`، `meta.config_id`) — همان ردی که watchdog از آن «اسلات از‌دست‌رفته» را تشخیص می‌دهد |
| 11 | `🔴 Daily Channel Slots MISSED` در هشدار watchdog = نشانه‌ی شکست این command برای یک config |
| 12 | `php artisan daily-channel:post --slot=N` (دستی)؛ `php artisan channel-poster:watchdog --dry-run` (بررسی اسلات‌ها) |
| 13 | ⚠️ **UTC-محور — تغییر ساعت ممنوع**؛ watchdog (بخش 1.1) هم‌راسته‌ی همین اسلات‌هاست |

#### `media-queue:post`

| # | داده |
|---|------|
| 1 | `media-queue:post` |
| 2 | [app/Console/Commands/PostMediaQueueToChannels.php](../app/Console/Commands/PostMediaQueueToChannels.php) |
| 3 | `0 10 * * *` (روزانه 10:00 UTC) |
| 4 | `{--dry-run : فقط نمایش، بدون ارسال}` |
| 5 | ✅ `withoutOverlapping()` |
| 6 | ✅ `onOneServer()` |
| 7 | ارسال نوبتی صف رسانه (`admin_channel_media_queue_configs`) به کانال‌ها — یک بار در روز |
| 8 | sync |
| 9 | `media_queues` / `media_queue_items` + `admin_channel_media_queue_configs` |
| 10 | پیام رسانه به کانال‌ها + به‌روزرسانی آیتم‌های صف |
| 11 | — |
| 12 | `php artisan media-queue:post --dry-run` |
| 13 | زیرسیستم صف رسانه ادمین |

#### `pipeline:status` / `pipeline:watch`

`pipeline:status` **در schedule نیست** — command تشخیصی read-only است.

| # | داده (`pipeline:watch`) |
|---|------|
| 1 | `pipeline:watch` |
| 2 | [app/Console/Commands/PipelineWatch.php](../app/Console/Commands/PipelineWatch.php) |
| 3 | `0 * * * *` (هر ساعت) |
| 4 | `{--dry-run : ...}` |
| 5 | ✅ `withoutOverlapping()` |
| 6 | ✅ `onOneServer()` |
| 7 | پایش ساعتی خط لوله‌ی محتوا: 4 نوع هشدار — SCHEDULER-STALE (heartbeat >600s)، NO-PUBLISH (هیچ انتشار RSS/کانال در 24h)، RSS-ERRORS (رشد رکوردهای error)، CHANNEL-FAILED (رشد failed در صف کانال) |
| 8 | sync |
| 9 | `pipeline:heartbeat:ts`، `rss_post_item_translation_queues`، `channel_poster_queue` |
| 10 | هشدار از طریق `AiProviderService::notifyAllAdmins` با suppress 6 ساعت (کلید `pipeline:alert:*` در Cache)؛ همیشه exit 0 (خودِ هشداردهنده نباید «خطا» گزارش شود) |
| 11 | — |
| 12 | `php artisan pipeline:watch --dry-run` |
| 13 | لایه‌ی آخر دفاع: اگر heartbeat مرد، همین command هشدار می‌دهد |

`pipeline:status` ([PipelineStatus.php](../app/Console/Commands/PipelineStatus.php)):
read-only، `{--json}`، **exit 1 اگر heartbeat >600s باشد**؛ گزارش: فیدهای
stale (≥48h)، شمارنده‌ی صف‌ها (queue/sent/error + 24h)، کانال‌های channel-poster،
آخرین رکورد error. برای مانیتورینگ/CI مناسب.

#### `rss:feeds-health`

| # | داده |
|---|------|
| 1 | `rss:feeds-health` |
| 2 | [app/Console/Commands/RssFeedsHealth.php](../app/Console/Commands/RssFeedsHealth.php) |
| 3 | `0 0 * * 0` (هفتگی، شنبه 00:00 UTC) |
| 4 | `--disable-dead --enable-healthy --limit=100` (options کامل: `--enable-all` (فوق‌العاده، دستی)، `--dry-run`, `--only-disabled`) |
| 5 | ✅ `withoutOverlapping()` |
| 6 | ✅ `onOneServer()` |
| 7 | بررسی هفتگی سلامت فیدها: probe واقعی HTTP+XML؛ غیرفعال‌سازی فقط بعد از آستانه (transient ≥3، HTML ≥2، URL نامعتبر = 1)؛ بازفعال‌سازی کنترل‌شده فیدهای غیرفعالِ سالم (set `last_synced_at=now-1d`) |
| 8 | sync (HTTP probe با retry) |
| 9 | `rss_items` (consecutive_failures, is_active) |
| 10 | به‌روزرسانی `rss_items.consecutive_failures`/`is_active`/`last_synced_at` |
| 11 | جدول verdicts در خروجی command |
| 12 | `php artisan rss:feeds-health --dry-run`؛ `php artisan rss:feed-test --feed-id=N` |
| 13 | زیرسیستم RSS؛ آستانه‌ها در `RssItemService` (`MAX_CONSECUTIVE_FAILURES=10` برای fetch) و این command |

#### `app:rss-post-to-translate` — ⚠️ در Scheduler نیست

[app/Console/Commands/RssPostToTranslate.php](../app/Console/Commands/RssPostToTranslate.php)
(`app:rss-post-to-translate`) **در Kernel ثبت نشده** و در خروجی `schedule:list`
نبود. این command فقط `RssPostItemTranslationToMessengerService::run()` (limit 5)
را صدا می‌زند. کار ترجمه/ارسال فعلي از مسیر
`app:rss_read_translate` (هر 15 دقیقه، مرحله دوم همان command) انجام می‌شود.
اگر روزی دوباره به schedule اضافه شود، این بخش را به‌روز کنید.

### 1.2 — نماز، قرآن، کتاب، محتوا

| Command | کلاس | Cron (UTC) | Options | بدون تداخل | onOneServer | وظیفه |
|---------|------|-----------|---------|:---:|:---:|--------|
| `prayer:send-weekly-reports` | [SendPrayerWeeklyReports.php](../app/Console/Commands/SendPrayerWeeklyReports.php) | هر 10 دقیقه (`*/10`) | `--batch-size=5 --interval=10` | ✅ | ✅ | ایمیل گزارش هفتگی نماز قضا — باکس کوچک |
| `prayer:send-weekly-reports` | """ | هر 30 دقیقه (`*/30`) | `--batch-size=10 --interval=30` | ✅ | ✅ | باکس متوسط |
| `prayer:send-weekly-reports` | """ | هر ساعت (`0 * * * *`) | `--batch-size=10 --interval=60` | ✅ | ✅ | باکس بزرگ |
| `app:task-reminder-command` | [TaskReminderCommand.php](../app/Console/Commands/TaskReminderCommand.php) | `20:27 * * 4` (پنجشنبه) | — | ❌ (در Kernel بدون withoutOverlapping) | ❌ | یادآوری وظایف |
| `app:users-ranking-command` | [UsersRankingCommand.php](../app/Console/Commands/UsersRankingCommand.php) | `20:29 * * *` | — | ❌ | ❌ | رتبه‌بندی/آمار کاربران ربات قرآن |
| `app:test-schedule-daily-into-slack` | [TestScheduleDailyIntoSlack.php](../app/Console/Commands/TestScheduleDailyIntoSlack.php) | `0 7 * * *` | — | ❌ | ❌ | **فقط اگر `env('TestScheduleDailyIntoSlack')` ست باشد** — تست |
| `weather:check-alerts` | [CheckWeatherAlerts.php](../app/Console/Commands/CheckWeatherAlerts.php) | `0 * * * *` | — | ✅ | ✅ | بررسی هشدارهای آب‌وهوا |
| `app:schedule-book-publishing` | [ScheduleBookPublishing.php](../app/Console/Commands/ScheduleBookPublishing.php) | `0 19-23 * * *` (ساعتی بین 19:00-23:59) | — | ✅ | ✅ | انتشار صفحات کتاب به کانال‌ها |
| `quran:send-daily-suggestion-to-admins` | [SendDailyQuranSuggestionToAdmins.php](../app/Console/Commands/SendDailyQuranSuggestionToAdmins.php) | `0 8 * * *` | `{--days=30}` (schedule بدون option) | ✅ | ✅ | پیشنهاد روزانه قرآن به ادمین‌ها |
| `audio:test-report` | [SendAudioTestReport.php](../app/Console/Commands/SendAudioTestReport.php) | `0 10 * * *` | `{--limit=5}` | ✅ | ✅ | گزارش کتابخانه صوتی تستی |
| `app:daily-blog-reminder` | [DailyBlogReminderCommand.php](../app/Console/Commands/DailyBlogReminderCommand.php) | `1 9 * * *` (09:01) | — | ✅ | ✅ | یادآوری روزانه بلاگ |
| `content:deliver-hourly` | [ScheduleContentDelivery.php](../app/Console/Commands/ScheduleContentDelivery.php) | `0 * * * *` | `{--dry-run}` `{--limit}` | ✅ | ✅ | ارسال ساعتی محتوای کتابخانه (پادکست) به کاربران صف‌بندی‌شده |
| `weekly-read:invite` | [WeeklyReadInvite.php](../app/Console/Commands/WeeklyReadInvite.php) | `0 10 * * *` | `{--dry-run}` `{--force}` | ✅ | ✅ | F5: دعوت هفتگی به مطالعه مطلب آرشیوی |
| `channel-motivation:send` | [ChannelMotivationSend.php](../app/Console/Commands/ChannelMotivationSend.php) | `0 11 * * *` | `{--dry-run}` | ✅ | ✅ | F6: متن انگیزشی LLM برای کانال‌های اسکدیول‌شده |

> **نکته:** `app:task-reminder-command` و `app:users-ranking-command` و
> `app:test-schedule-daily-into-slack` در Kernel فعلی **بدون** `withoutOverlapping()`
> ثبت‌اند (سطر 30، 31، 78 [Kernel.php](../app/Console/Kernel.php)). این یک
> **یافته** است؛ چون یک‌سرور و یک cron است ریسک عملی کم است، اما در صورت
> تغییر معماری به چندسرور، `withoutOverlapping()`+`onOneServer()` لازم می‌شود.

### 1.3 — AI و رشد و پایش زیرساخت

| Command | کلاس | Cron (UTC) | Options | بدون تداخل | onOneServer | وظیفه |
|---------|------|-----------|---------|:---:|:---:|--------|
| `ai:health-check` | [AiHealthCheck.php](../app/Console/Commands/AiHealthCheck.php) | `0 * * * *` | — | ✅ | ✅ | بررسی سلامت سرورهای AI فعال |
| `ai:daily-health-report` | [AiDailyHealthReport.php](../app/Console/Commands/AiDailyHealthReport.php) | `0 9 * * *` | `{--dry-run}` | ✅ | ✅ | گزارش 24h سلامت AI (سالم→«من زنده هستم»/خراب→هشدار) به ربات مادر و ادمین قرآن |
| `growth:dispatch-due` | [GrowthDispatchDueCommand.php](../app/Console/Commands/GrowthDispatchDueCommand.php) | `*/5 * * * *` | `{--limit=50}` | ✅ | ✅ | ارسال سؤالات/محتوای Growth Companion سرِوقت |
| `observability:prune` | [PruneObservabilityCommand.php](../app/Console/Commands/PruneObservabilityCommand.php) | `0 0 * * *` (daily()) | `{--log-max=}` | ✅ | ✅ | پاک‌سازی دوره‌ای `app_log_entries` و داده‌های مشاهده‌پذیری |

### 1.4 — Commands دستی/تشخیصی مهم (در schedule نیستند)

| Command | کلاس | کاربرد | گزینه‌ها |
|---------|------|--------|----------|
| `rss:recover` | [RssRecover.php](../app/Console/Commands/RssRecover.php) | **ریکاوری ایمن** رکوردهای RSS/کانال (dry-run پیش‌فرض) | `--system=both --channel= --record-id= --status= --since= --until= --limit=100 --execute --admin-chat-id= --include-queue` |
| `rss:feed-test` | [RssTestFeed.php](../app/Console/Commands/RssTestFeed.php) | probe فقط (HTTP+XML+تعداد لینک جدید) — **هرگز ذخیره/ارسال نمی‌کند** | `{--feed-id=} {--dry-run}` |
| `channel-poster:diagnose` | [ChannelPosterDiagnose.php](../app/Console/Commands/ChannelPosterDiagnose.php) | read-only: شمارش status صف، مقصدها بر اساس پلتفرم، آمار فایل‌های رسانه (referenced/orphan/stale/bytes)، 10 آیتم اخیر | — |
| `channel-poster:send-queued` | [ChannelPosterSendQueued.php](../app/Console/Commands/ChannelPosterSendQueued.php) | ارسال دستی آیتم‌های صف (default: فقط ready) | `{--bot=} {--id=} {--all}` |
| `content-sync:report` | [ContentSyncReport.php](../app/Console/Commands/ContentSyncReport.php) | خروجی text/CSV گزارش همگام‌سازی (برای مایگریشن 5000 پست) | `{--bot=} {--all} {--csv}` |
| `pipeline:status` | [PipelineStatus.php](../app/Console/Commands/PipelineStatus.php) | read-only وضعیت خط لوله | `{--json}` |
| `app:rss-to-bot` | [RssToBot.php](../app/Console/Commands/RssToBot.php) | (legacy) | — |

---

## ۲) قفل‌های همزمانی (Concurrency Locks)

| قفل | نوع | TTL | کجا |
|-----|-----|-----|-----|
| `withoutOverlapping()` (هر entry) | Cache lock توسط Laravel | تا پایان execution | Kernel |
| `onOneServer()` | Cache lock (multi-server) | تا پایان execution | Kernel (اکثر entry‌ها) |
| `rss_read_translate_lock` | `Cache::lock` دستی | **900s** | داخل [RssReadTranslate.php](../app/Console/Commands/RssReadTranslate.php) — دفاع دوم در برابر cron/دستور دستی هم‌زمان |
| `rss_recover_exec_lock` | `Cache::lock` دستی | **1800s** | [RssRecoveryService.php](../app/Services/RssRecoveryService.php) — فقط هنگام `--execute` |
| `watchdog_state:*` | Cache keys وضعیت | 48h | [PostOverdueWatchdog.php](../app/Console/Commands/PostOverdueWatchdog.php) |
| `pipeline:alert:*` | suppress هشدار | 6h | [PipelineWatch.php](../app/Console/Commands/PipelineWatch.php) |
| `rss_msg_job_log_throttle_<channel>` | throttle لاگ | 1800s | [RssPostItemTranslationToMessengerJob.php](../app/Jobs/RssPostItemTranslationToMessengerJob.php) |

---

## ۳) Queue — چرا `queue:work` وجود ندارد؟

`QUEUE_CONNECTION=sync` در `.env` (محلی) و (طبق مستندات production) در production:

- جاب‌های `ShouldQueue` (مثل `RssPostItemTranslationToMessengerJob`،
  `ProcessChannelPosterQueueJob`) با `dispatch`/`dispatchSync` **فوری و inline**
  اجرا می‌شوند.
- جدول `jobs`/`failed_jobs` برای این مسیرها استفاده نمی‌شود؛ «failed job» در اینجا
  یعنی status=`error` در `rss_post_item_translation_queues` یا status=`failed` در
  `channel_poster_queue` (با `last_error`).
- `timeout = 45` در `RssPostItemTranslationToMessengerJob` **فقط با worker واقعی
  معتبر است** (در sync نادیده می‌رود) — همان‌طور که در کامنت کد آمده.
- هر commandی که جاب dispatch کند، زمان اجرای خودش طولانی‌تر می‌شود؛ به همین دلیل
  `withoutOverlapping` ضروری است.

اگر روزی به queue واقعی (database/redis) مهاجرت شود: جدول `failed_jobs` فعال
می‌شود و این بخش باید بازنویسی شود.

---

## ۴) UTC vs +03:30 — قانون تبدیل

| UTC (سرور) | ایران (UTC+3:30) |
|-----------|------------------|
| 00:00 | 03:30 |
| 06:00 | 09:30 |
| 08:00 | 11:30 |
| 10:00 | 13:30 |
| 11:00 | 14:30 |
| 12:00 | 15:30 |
| 18:00 | 21:30 |
| 19:00-23:59 | 22:30-03:29 |

**قانون:** هر تغییری روی ساعت‌های `dailyAt` باید با درک این جدول و با
هم‌راستا نگه‌داشتن `PostOverdueWatchdog::findMissedDailySlots` (که اسلات‌ها را
از `startOfDay + (slot-1)*6h` محاسبه می‌کند) انجام شود.

---

## ۵) تشخیص خرابی cron / schedule:run

اگر «هیچ جابی اجرا نمی‌شود»:

1. `php artisan pipeline:status` — اگر `SCHEDULER HEARTBEAT` stale باشد (exit 1)،
   مشکل در cron/`schedule:run` است، نه در commandها.
2. `crontab -l` در سرور — خط `* * * * * ... schedule:run` باید وجود داشته باشد
   (نمونه در [deploy_commands.sh](../deploy_commands.sh)).
3. `tail -f storage/logs/laravel.log | grep -i schedule` — هر دقیقه `Running
   command: pipeline:heartbeat` (یا مشابه) باید ببینید.
4. بررسی uptime سرور/cPanel cron log.
5. اگر cron سالم است ولی entry خاصی اجرا نمی‌شود → `php artisan schedule:list`
   و اجرای دستی همان command.

> ⚠️ تاریخچه: یک‌بار cron فقط شبانه `schedule:run` داشت و روزها هیچ جابی
> اجرا نمی‌شد؛ همچنین cron‌های دست‌نویس 19 دقیقه‌ای RSS و چندین `queue:work`
> قدیمی وجود داشت که همگی در 2026-10-19 اصلاح و حذف شدند (
> [docs/SCHEDULER_CRON_PLAN.md](SCHEDULER_CRON_PLAN.md)).

---

## ۶) تغییرات امن در Scheduler (checklist)

- [ ] entry جدید/تغییرشده `withoutOverlapping()` دارد؟
- [ ] برای entryهای سنگین/تکراری، `onOneServer()` اضافه شده؟
- [ ] اگر ساعت UTC تغییری می‌کند، `PostOverdueWatchdog` و `shouldRunInSlot` هم‌راسته‌اند؟
- [ ] `php artisan schedule:list` بعد از تغییر اجرا و با این فایل مقایسه شده؟
- [ ] برای commandهای bulk، نسخه dry-run قبل از اجرا تست شده؟
- [ ] commit message با توضیح دلیل تغییر همراه است (برای تاریخچه)؟

---

## ۷) اسلات‌های 6 ساعته `daily-channel:post` (جزئیات)

- 4 اسلات: 1=00:00، 2=06:00، 3=12:00، 4=18:00 (UTC).
- هر `AdminDailyChannelConfig` یک mask از اسلات‌ها دارد (`posts_per_day` → 1/2/4
  اسلات)؛ متد `shouldRunInSlot($slot)` در مدل تعیین می‌کند.
- [PostOverdueWatchdog.php](../app/Console/Commands/PostOverdueWatchdog.php)
  (خط 182-230) برای هر اسلاتِ سرِ‌رفته (grace 15 دقیقه) بررسی می‌کند که آیا
  `BotHealthEvent` با `event_type=channel_post` و `meta.config_id` در بازه‌ی
  `[slotTime, nextSlotTime)` ثبت شده؛ نبود → «میس‌شده».
- **هر تغییری در ساعت‌های این 4 entry باید هم‌زمان در این منطق watchdog اعمال
  شود** (و بالعکس).

---

## ۸) منابع

- [app/Console/Kernel.php](../app/Console/Kernel.php) — منبع حقیقت schedule
- خروجی `php artisan schedule:list` (2026-10-19) — تایید عملی
- [docs/SCHEDULER_CRON_PLAN.md](SCHEDULER_CRON_PLAN.md) — تصمیم حذف cron‌های قدیمی
- [docs/RECOVERY_ROLLOUT_PLAN.md](RECOVERY_ROLLOUT_PLAN.md) — رول‌اوت ریکاوری RSS
- [docs/PRODUCTION_RUNBOOK.md](PRODUCTION_RUNBOOK.md) — دیپلوی و عیب‌یابی production
