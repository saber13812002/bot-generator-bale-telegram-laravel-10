# 🚀 PRODUCTION RUNBOOK — راهنمای عملیات سرور Production

> **مخاطب:** هر agent یا انسانی که باید روی سرور production کار کند (deploy، diagnose، rollback).
> **محیط:** cPanel hosting، سرور واحد، PHP 8.2.34، MySQL، `QUEUE_CONNECTION=sync`.
> **مسیر پروژه:** `/home/pardisa2/bots`
> **دسترسی:** agent مستقیم به production دسترسی ندارد؛ همهٔ دستورالعمل‌ها برای اجرای مالک نوشته شده‌اند.

---

## ۱. تصویر محیط Production

| مورد | مقدار | منبع |
|---|---|---|
| OS / hosting | Linux + cPanel | `DEPLOY_INSTRUCTIONS.md` |
| مسیر ریشه | `/home/pardisa2/bots` | [`deploy_commands.sh`](deploy_commands.sh) |
| PHP | 8.2.34 (مسیر: `/usr/local/bin/php`) | `deploy_commands.sh` |
| Composer | `COMPOSER_MEMORY_LIMIT=-1` (سرور memory محدود دارد) | `DEPLOY_INSTRUCTIONS.md` |
| دیتابیس | MySQL (cPanel) | `config/database.php` |
| Queue | `sync` — هیچ worker ای وجود ندارد و نباید راه‌اندازی شود | `config/queue.php` |
| Timezone | UTC (اپلیکیشن) | `config/app.php` خط 76 |
| cron | **فقط** `* * * * * cd /home/pardisa2/bots && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1` | [`docs/SCHEDULER_CRON_PLAN.md`](docs/SCHEDULER_CRON_PLAN.md) |
| دامنهٔ نمونه | `https://bots.pardisania.ir` | `DEPLOY_INSTRUCTIONS.md` (curl test) |

> ⚠️ اگر `pipeline:status` بگوید heartbeat scheduler مرده است، 90٪ مشکل همین خط cron است.

---

## ۲. Deploy — چک‌لیست کامل

### 2.1 پیش از deploy

```bash
# در سرور
cd /home/pardisa2/bots

# 1. وضعیت git را ببین (باید clean باشد؛ تغییر محلی مانده = خطر)
git status

# 2. backup دیتابیس (الزامی قبل از هر migrate)
mysqldump -u <DB_USER> -p <DB_NAME> > /home/pardisa2/backups/bots_$(date +%Y%m%d_%H%M).sql

# 3. بکاپ از storage (فایل‌های رسانهٔ کانال‌پوستر + آکشیوها)
tar czf /home/pardisa2/backups/storage_$(date +%Y%m%d_%H%M).tar.gz storage/app/channel-poster-media/
```

### 2.2 اجرای deploy (ترتیب دقیق — از [`deploy_commands.sh`](deploy_commands.sh))

```bash
cd /home/pardisa2/bots

# 1. دریافت تغییرات
git pull origin 15esfand1404

# 2. dependencies (memory limit الزامی — بدون آن "Killed" می‌شود)
export COMPOSER_MEMORY_LIMIT=-1
php -d memory_limit=-1 /usr/bin/composer install --no-dev --optimize-autoloader

# 3. پاک‌سازی cache ها
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

# 4. migrations (فقط بعد از بکاپ!)
php artisan migrate --force

# 5. webhook endpoints (در صورت تغییر routeها)
php artisan webhook-endpoints:import-default

# 6. cache برای production
# ⚠️ از optimize:clear استفاده نکنید — bootstrap cache پاک می‌شود و ممکن است با پکیج‌های
#    نصب‌نشده خطا بدهد (نکتهٔ مستقیم در deploy_commands.sh)
php artisan config:cache
php artisan route:cache

# 7. (اختیاری، فقط وقتی swagger لازم است)
php artisan l5-swagger:generate

# 8. تأیید cron (اسکریپت deploy_commands.sh این چک را خودکار انجام می‌دهد)
crontab -l | grep 'schedule:run'
```

### 2.3 پس از deploy — smoke test

```bash
# 1. تست زندهٔ API
curl -s "https://bots.pardisania.ir/api/v1/quran/languages" -H "Accept: application/json" | head -c 300

# 2. سلامت پاپ‌لاین (heartbeat، RSS، صف‌ها، Channel Poster)
php artisan pipeline:status

# 3. بررسی لاگ برای خطای fatal
tail -n 50 storage/logs/laravel.log

# 4. تأیید اینکه scheduler entryها ثبت شده‌اند
php artisan schedule:list
```

اگر `pipeline:status` exit 1 داد → بخش [۵ — دیباگ](#5-عیب‌یابیدiagnostic).

---

## ۳. مدیریت cron

**قانون:** فقط **یک خط** cron وجود دارد:

```
* * * * * cd /home/pardisa2/bots && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

| عملیات | دستور |
|---|---|
| مشاهده | `crontab -l` |
| افزودن (فقط همان خط دقیقه‌ای) | `crontab -e` |
| حذف cron نادرست (مثلا cron مستقیم برای `app:rss_read_translate`) | `crontab -e` و حذف خط |

**اشتباهات ممنوعه (همه از تاریخچهٔ پروژه):**
- ❌ cron جدا برای هر command (تداخل با `withoutOverlapping` و قفل‌های cache) — این دقیقاً bugی بود که `rss_read_translate_lock` را ایجاد کرد.
- ❌ cron `storage:logs-cleanup` — **این command در کد وجود ندارد** (فقط در `SCHEDULER_CRON_PLAN.md` قدیمی ذکر شده).
- ❌ حذف خط `schedule:run` — بدون آن هیچ جابی اجرا نمی‌شود و 15-20 دقیقه بعد `pipeline:watch` هشدار می‌دهد.

→ مرجع کامل 30 entry: [`docs/JOBS_AND_SCHEDULER.md`](docs/JOBS_AND_SCHEDULER.md)

---

## ۴. Migrations و Rollback

### 4.1 مایگریشن‌های اخیر (جدول برای rollback — از `database/migrations/`)

| فایل | کار | rollback (down) |
|---|---|---|
| `2026_10_18_000001_add_recovery_fields_to_rss_post_item_translation_queues_table` | فیلدهای ریکاوری (message_id, media_error, …) | حذف columnها |
| `2026_10_17_100000_add_file_name_and_mime_to_channel_poster_queue_table` | file_name/mime_type | حذف columnها |
| `2026_10_16_210000_add_media_path_to_channel_poster_queue_table` | media_path | حذف column |
| `2026_10_16_200000_add_content_sync_phase4` | Phase 4 (attribution و …) | حذف columnها |
| `2026_10_16_100000_add_content_sync_settings` | تنظیمات sync | حذف columnها |
| `2026_10_15_100000_create_content_sync_tables` | جدول‌های content sync | drop tableها |
| `2026_10_11_000001_add_health_fields_to_rss_items_table` | health فیدها | حذف columnها |
| `2026_10_09_000002_create_channel_motivational_schedules_table` | جدول motiva | drop table |
| `2026_10_09_000001_create_weekly_read_invitations_table` | جدول weekly read | drop table |

> ⚠️ rollback فقط برای **مهاجراتی که در همان deploy اجرا شده‌اند** معنادار است. `migrate:rollback` همهٔ batch فعلی را برمی‌گرداند؛ برای انتخابی استفاده کن:
> `php artisan migrate:rollback --step=<n>` (فقط n آخرین batch) — و قبلش بکاپ داری!

### 4.2 Rollback کد (code)

```bash
cd /home/pardisa2/bots

# سناریو A: آخرین commit مشکل‌ساز
git log --oneline -5                      # شناسایی
git revert <commit>                       # ترجیحی (traceable)
# یا
git reset --hard <commit-قبلی>            # اگر commit هنوز push نشده و deploy فوری است

# بعد:
export COMPOSER_MEMORY_LIMIT=-1
php -d memory_limit=-1 /usr/bin/composer install --no-dev --optimize-autoloader
php artisan config:clear && php artisan route:clear && php artisan cache:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache
```

**قانون:** rollback کد + rollback migration باید **با هم** تصمیم گرفته شوند. اگر کد قدیمی column جدید را نمی‌شناسد، معمولاً column اضافه‌بی‌ضرر است؛ اما اگر کد جدید column قدیمی را نیاز دارد، rollback migration لازم است (اول بکاپ!)

### 4.3 Rollback محتوا (محتوای ارسال‌شده)

- **RSS:** محتوای ارسال‌شده به کانال‌ها قابل بازیابی فنی نیست — تصمیم مالک (حذف دستی از کانال). `rss:recover` هرگز رکورد `sent`/`delivered` را دوباره نمی‌فرستد.
- **Channel Poster:** `channel_poster_queue.status='cancelled'` برای جلوگیری از ارسال آیتم‌های صف‌شده؛ آیتم‌های `published` بازنشر نمی‌شوند (idempotent per-destination).

---

## ۵. عیب‌یابی (Diagnostic)

### 5.1 درخت تصمیم

```
آیا پاپ‌لاین می‌چرخد؟
├─ php artisan pipeline:status
│  ├─ "scheduler heartbeat stale" → cron را چک کن (بخش ۳) → php artisan schedule:list
│  ├─ "RSS: no new items / all feeds failed" → rss:feed-test + لاگ "RSS pipeline run"
│  ├─ "RSS: error buildup" → rss:recover --system=rss (dry-run)
│  └─ "Channel poster: overdue/failed" → بخش 5.2
```

### 5.2 کانال‌پوستر گیر کرده

```bash
php artisan channel-poster:diagnose                 # خلاصهٔ صف + رسانه
php artisan channel-poster:send-queued --id=<ID>    # resend تک‌آیتم
php artisan channel-poster:prune-stale --dry-run    # بررسی stale قبل از prune واقعی
# cache watchdog
php artisan tinker --execute='echo cache("watchdog_state:red_bots");'
```

### 5.3 لاگ‌ها

| فایل | محتوا |
|---|---|
| `storage/logs/laravel.log` | همهٔ لاگ‌های ساختاریافته: `RSS pipeline run started/finished`, `[ChannelPoster] ProcessChannelPosterQueueJob started (diagnostic marker)`, watchdog alerts |
| `storage/logs/` بقیه | بر اساس `config/logging.php` (کانال‌های اختصاصی) |

**نکتهٔ امنیتی:** هنگام بررسی/کپی لاگ، هرگز token در output/تیکت/گیت‌هاب نبر. لاگ‌ها از `TokenHelper::redactTokens` می‌گذرند ولی لاگ‌های قدیمی‌تر ممکن است لیک داشته باشند (توکن Bale — ببین [§۷](#7-امنیت)).

### 5.4 دیتابیس — پرسش‌های سریع

```sql
-- رکوردهای RSS گیر کرده
SELECT id, channel_id, status, attempts, last_error, created_at
FROM rss_post_item_translation_queues
WHERE status IN ('error','queue') ORDER BY id DESC LIMIT 20;

-- صف کانال‌پوستر
SELECT id, bot_id, tag, status, scheduled_at, last_error
FROM channel_poster_queue
WHERE status IN ('pending','failed') ORDER BY id DESC LIMIT 20;

-- log انتشار مقصدها
SELECT * FROM channel_poster_publish_logs
WHERE queue_id = <ID> ORDER BY id DESC;

-- health فیدها
SELECT id, url, status, consecutive_failures, last_synced_at
FROM rss_items ORDER BY id DESC LIMIT 30;
```

---

## ۶. env — نام متغیرها (مقدارها هرگز در مستند)

از [`.agents/AGENTS.md`](.agents/AGENTS.md) و کد — **فقط نام‌ها** معتبرند:

| حوزه | متغیرها |
|---|---|
| عمومی | `APP_URL`, `DB_*`, `CACHE_DRIVER`/`CACHE_STORE`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER` |
| ادمین | `CHAT_ID_ACCOUNT_1_SABER`, `CHAT_ID_ACCOUNT_2_SABER`, `SUPER_ADMIN_CHAT_ID_TELEGRAM`, `SUPER_ADMIN_CHAT_ID_BALE`, `SUPER_ADMIN_CHAT_ID_BALE2`, `SUPER_ADMIN_CHAT_ID_GAP` |
| Webhook | `TELEGRAM_WEBHOOK_SECRET` و مشابه (هر پیام‌رسان) |
| API | `api.token` (Mission API), `WEATHER_API_*`, `TRANSLATE_API_*`, `AI_*` (LLM) |
| Slack | `SLACK_WEBHOOK_URL` (تست روزانهٔ scheduler) |
| Mail | `MAIL_*` (گزارش نماز، Mailtrap در dev) |

> هر تغییر env → **حتماً** `php artisan config:clear` + `config:cache` بعدش (وگرنه تغییر اعمال نمی‌شود).

---

## ۷. امنیت

| اقدام | وضعیت |
|---|---|
| **توکن Bale لیک‌شده در لاگ** | **آیا rotate شده؟ تأییدنشده.** ← اقدام: rotate در پنل Ble → به‌روزرسانی env → `config:clear/config:cache` → پاک‌سازی لاگ‌های قدیمی (فقط با تأیید مالک) |
| redactTokens | پوشش تأییدشده در `RssService`, `RssReadTranslate`, `ChannelPosterBotController`؛ هر لاگ جدید token-bearing باید از [`TokenHelper::redactTokens`](app/Helpers/TokenHelper.php) رد شود |
| لاگ قدیمی | `storage/logs/*.log*` — بایگانی/پاک‌سازی فقط با تصمیم مالک (حجم + ریسک لیک) |
| دسترسی SSH/DB | فقط از حساب مالک؛ agent هرگز credential ندارد |

---

## ۸. ظرفیت‌ها و محدودیت‌های شناخته‌شده

| مورد | محدودیت | منبع |
|---|---|---|
| memory سرور | composer بدون `-d memory_limit=-1` می‌افتد | `DEPLOY_INSTRUCTIONS.md` |
| سقف رسانهٔ کانال‌پوستر | 50MB (fetch) | `ChannelPosterMediaStore::MAX_BYTES` |
| سقف `rss:recover` | 100 رکورد هر سیستم هر اجرا | `RssRecoveryService::HARD_LIMIT` |
| صف کانال‌پوستر هر پردازش | 10 آیتم `ready` | `ProcessChannelPosterQueueJob` |
| freshness RSS | 48 ساعت (retry) | `RssRecoveryService::MAX_RETRY_AGE_HOURS` |
| stale prune | 7 روز (pending) | `STALE_DAYS = 7` |

---

## ۹. چک‌لیست‌های فوری

### «پاپ‌لاین متوقف است»
- [ ] `crontab -l` → خط `schedule:run` موجود و سالم؟
- [ ] `php artisan schedule:list` بدون خطا؟
- [ ] `php artisan pipeline:status` → heartbeat stale؟
- [ ] `tail storage/logs/laravel.log` → fatal error؟
- [ ] `php -v` در سرور → هنوز 8.2.34؟ (update ناگهانی PHP = خطر)

### «محتوای RSS منتشر نمی‌شود»
- [ ] `php artisan pipeline:status`
- [ ] SQL §5.4 → `last_error` چیست؟
- [ ] 403/permission_denied → token کانال مقصد (Bale/…) چک شود — **به‌جای کور retry، اول دلیل را حل کن**
- [ ] `rss:recover --system=rss --channel=<id>` (dry-run) → کلاس رکورد
- [ ] `retryable` → `--execute --admin-chat-id=<admin>` (حداکثر 100)
- [ ] لاگ `RSS pipeline run finished` → `exit_code`, `new_saved`

### «کانال‌پوستر post نمی‌دهد»
- [ ] `php artisan channel-poster:diagnose`
- [ ] آیتم `pending` با `scheduled_at` در گذشته؟ → خودبه‌خود در 5 دقیقهٔ بعدی پردازش می‌شود؛ اگر نه → watchdog 15 دقیقه دیگر red می‌شود
- [ ] `channel_poster_publish_logs` → error هر مقصد
- [ ] رسانه؟ → `storage/app/channel-poster-media/` + `channel-poster:prune-stale --dry-run`
- [ ] اسلات روزانه؟ → `daily-channel:post --slot=N` فقط در پنجرهٔ UTC خودش (00/06/12/18 + grace) — ببین `docs/JOBS_AND_SCHEDULER.md` §7

### «بعد از deploy همه‌چیز شکست»
- [ ] `git status` / `git log -1` → commit صحیح؟
- [ ] بکاپ دیتابیس + rollback (بخش 4)
- [ ] `composer install` واقعاً اجرا شد؟ (autoload stale = خطای کلاس ناخوانده)
- [ ] `config:cache` با env جدید انجام شد؟

---

## ۱۰. اسناد مرتبط

- [`docs/AGENT_HANDOFF.md`](docs/AGENT_HANDOFF.md) — نقطهٔ شروع agent
- [`docs/JOBS_AND_SCHEDULER.md`](docs/JOBS_AND_SCHEDULER.md) — مرجع جاب‌ها و قفل‌ها
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — معماری
- [`DEPLOY_INSTRUCTIONS.md`](DEPLOY_INSTRUCTIONS.md) — دستورالعمل deploy اصلی (کلاسیک)
- [`deploy_commands.sh`](deploy_commands.sh) — اسکریپت deploy با چک cron
- [`docs/RECOVERY_ROLLOUT_PLAN.md`](docs/RECOVERY_ROLLOUT_PLAN.md) — رول‌اوت مرحله‌ای محتوا

---

*آخرین به‌روزرسانی: 2026-10-19 — از روی `deploy_commands.sh`، `DEPLOY_INSTRUCTIONS.md`، `database/migrations/` و کد فعلی.*
