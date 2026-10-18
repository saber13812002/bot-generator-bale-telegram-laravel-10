# P4 — برنامه رول‌اوت مرحله‌ای محتوای جدید + Rollback

> هدف: پس از احیای خط لوله، انتشار **جدید** محتوا را با ریسک حداقل‌بها و به‌ترتیب
> اولویت کانال‌ها بازگردانیم. هیچ‌چیز قبل از تأیید ادمین ارسال واقعی نمی‌شود.

## ۱. اولویت کانال‌ها (طبق سفارشات)

| # | کانال | توضیح |
|---|---|---|
| 1 | Sharabe Beheshti | اولویت یک — بیشترین حساسیت زمانی |
| 2 | Audiobooks (Navaar) | |
| 3 | Evand | |
| 4 | Songsara | |
| 5 | IT / سایر | |

## ۲. مراحل رول‌اوت

### مرحله ۰ — پیش‌نیاز (یک‌بار)
- [ ] `php artisan migrate` (مدرنیت: ستون‌های سرنجه‌ی تحویل `message_id/sent_at/attempts/last_error/media_error`)
- [ ] `php artisan config:clear && php artisan cache:clear && php artisan route:clear`
- [ ] cron جدید طبق [SCHEDULER_CRON_PLAN.md](SCHEDULER_CRON_PLAN.md) — فقط `schedule:run` هر دقیقه
- [ ] kill همه‌ی `queue:work` قدیمی
- [ ] `php artisan pipeline:status` → heartbeat OK + خروجی پایدار
- [ ] `php artisan rss:feeds-health` (بدون `--disable-dead`) → بررسی verdict هر فید؛ فیدهای
      `invalid_url` جداگانه اصلاح شوند (link dedup/URL تغییرکرده)

### مرحله ۱ — یک منبع + یک مقصد (سنگین‌ترین تست: Sharabe)
- فقط **یک فید فعال** مرتبط با Sharabe Beheshti + فقط **یک کانال مقصد**.
  (سایر فیدها را موقتاً `is_active=0` بگذارید — از admin panel یا SQL با backup قبلی.)
- [ ] `php artisan app:rss_read_translate --feed-id=<id> --dry-run` → خروجی `total/new` سالم
- [ ] `php artisan rss:feed-test --feed-id=<id>` → `ok=1`
- [ ] اجرای واقعی یک بار: `php artisan app:rss_read_translate --feed-id=<id>`
- [ ] بررسی کانال مقصد: پیام اصلی + موزیک + لینک محصول (Sharabe product block) + hashtag‌ها
- [ ] `php artisan pipeline:status` → `sent` افزایش + `last_publish` تازه + `message_id` ثبت‌شده
- [ ] **۲۴ ساعت مشاهده** (سرخود) بدون دخالت — `pipeline:watch` هشدار ندهد

### مرحله ۲ — کانال‌های ۲ تا ۳ (Audiobooks + Evand)
- فیدهای Navaar + Evand را فعال کنید.
- [ ] هر ۱۵ دقیقه (خودکار) چک کنید که `new_saved` > 0 و در کانال‌ها محتوای جدید می‌رسد
- [ ] تست partial-failure: (اختیاری) یک توکن مقصد را موقتاً غیرمعتبر بگذارید →
      رکورد باید `error` شود و `pipeline:watch` هشدار دهد؛ سپس با `rss:recover` (dry-run → execute) احیا شود
- [ ] **۲۴–۴۸ ساعت** پایداری

### مرحله ۳ — کانال Songsara
- [ ] فعال‌سازی فید Songsara + بررسی `audio(songsara)` (فراخوانی mp3 از صفحه‌ی وب)
- [ ] **۲۴ ساعت** پایداری

### مرحله ۴ — بقیه (IT/other) + بازفعال‌سازی کنترل‌شده فیدهای غیرفعال
- [ ] `php artisan rss:feeds-health --enable-healthy` → فقط فیدهای **سالم** بازفعال می‌شوند
      (فیدهای مرده باز نمی‌گردند — برای آن‌ها باید `invalid_url`/`html_not_xml` اول اصلاح شود)
- [ ] بازتست خودکار فیدهای غیرفعال: `RssItemService::retestDisabledFeeds()` (حداکثر ۳ فید در هر
      اجرای `app:rss_read_translate`، با backoff ۷ روز)

### مرحله ۵ — بک‌لاگ (فقط با freshness check + سقف)
- برای رکوردهای قدیمی در صف (`status='queue'` قدیمی): **هرگز ارسال انبوه**.
- [ ] `php artisan rss:recover` (dry-run) → دسته‌بندی ۱۸۰۵ رکورد تاریخی:
  `delivered / retryable / needs_review / insufficient_evidence`
- [ ] فقط رکوردهای `retryable` (خطای صریح «قبل از تحویل» بدون سرنجه) با:
  `php artisan rss:recover --execute --admin-chat-id=<admin> --limit=<کوچک> ` — خُرد و تکرار
- [ ] رکوردهای `needs_review` → بررسی دستی تک‌تک (ممکن است رسیده باشند!)
- [ ] رکوردهای `insufficient_evidence` (رکوردهای قدیمی بدون `sent_at`) → بدون فرض ارسال‌نشده؛
  بررسی دستی در کانال قبل از هر resend
- [ ] محتوای **بیش از ۷ روز** (backlog کهنه) منتشر **نمی‌شود** — freshness check؛ فقط محتوا
  حداکثر تا ۴۸ ساعت قدیمی با سقف روزانه (پیش‌فرض `--limit`)

## ۳. Rollback

هر مرحله قابل بازگشت است بدون از دست رفتن داده:

| اگر… | کار کنید |
|---|---|
| انتشار نادرست/تکراری در یک کانال | فید مرتبط را `is_active=0` کنید (یک خط SQL / admin) — خط لوله متوقف، داده‌ها سالم |
| `rss:feeds-health` موج غیرفعال‌سازی جدید ایجاد کرد | `php artisan rss:feeds-health --enable-healthy` فقط سالم‌ها را برمی‌گرداند؛ در بحران: فیدهای مورد نظر را دستی `is_active=1` |
| job ارسال با خطای عجیب | revert commit شاخه‌ی `fix/content-pipeline-recovery` (کد فقط)؛ ستون‌های جدید nullable هستند و rollback migration داده را نمی‌شکند |
| scheduler خراب شد | cron را برگردانید + `pipeline:status` → heartbeat ستاله می‌شود و `pipeline:watch` هشدار می‌دهد |
| هشدارهای `pipeline:watch` مزاحم | suppress 6h دارد؛ در بحران `php artisan cache:clear` کلیدهای `pipeline:alert:*` را پاک می‌کند |

**نکته‌ی امنیتی:** هیچ‌کدام از دستورات ریکاوری بدون `--execute` و `--admin-chat-id` معتبر
ارسال واقعی انجام نمی‌دهند.

## ۴. چک‌لیست موفقیت (P5 verification)

برای هر کانال/پلتفرم (Bale / Telegram / Eitaa):
- [ ] پیام جدید در کانال مقصد ظاهر شده (بصری + `message_id` در DB)
- [ ] `pipeline:status` → `last_publish` تازه + `sent` در حال افزایش
- [ ] `pipeline:watch` هیچ هشدار فعالی ندارد (یا `--dry-run` «all green»)
- [ ] لاگ‌ها: `app:rss_read_translate` هر ۱۵ دقیقه با `duration_ms` + `new_saved` > 0
- [ ] `rss:recover` (dry-run) → `retryable=0` (یا حداقل کاهش چشمگیر)
