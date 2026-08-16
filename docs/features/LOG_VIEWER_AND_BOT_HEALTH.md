# نمایشگر لاگ سری و جدول سلامت ربات‌ها

## توضیحات

صفحهٔ داخلی برای دیدن آخرین لاگ‌های سرور (با فیلتر `bot_id`) و وضعیت سلامت ارسال کانال‌ها. لاگ فایل `storage/logs/laravel.log` مثل قبل نوشته می‌شود؛ همزمان آخرین رکوردها در دیتابیس هم ذخیره می‌شوند تا بشود همان لحظه کپی کرد و به Cursor داد.

`/health` و `/metrics` (Prometheus/Grafana) در این فاز نیستند.

## اهداف

- دیدن آخرین لاگ‌ها از روی یک آدرس خیلی طولانی و سری
- فیلتر بر اساس `bot_id` و کپی ۱۰۰ رکورد آخر
- ثبت ok/fail وقتی پست کانال (از جمله شراب بهشتی) واقعاً توسط API تأیید شود
- به‌روز شدن `bots.last_activity_at` وقتی `bot_id` مشخص باشد و status برابر `ok` باشد

## مسیر فایل‌ها

- Controller: `app/Http/Controllers/LogViewerController.php`
- Services: `app/Services/LogViewerService.php`, `app/Services/BotHealthRecorder.php`
- Logging: `app/Logging/DatabaseLogger.php`, `app/Logging/DatabaseLogHandler.php`
- Models: `app/Models/AppLogEntry.php`, `app/Models/BotHealthEvent.php`
- View: `resources/views/observability/log-viewer.blade.php`
- Command: `app/Console/Commands/PruneObservabilityCommand.php`
- Config: `config/observability.php`
- Route: `routes/api.php` — فقط اگر `LOG_VIEWER_SECRET` ست شده باشد
- Migration: `database/migrations/2026_08_16_000001_create_app_log_entries_and_bot_health_events_tables.php`

## Environment Variables

```env
LOG_VIEWER_SECRET=
APP_LOG_MAX_ROWS=5000
BOT_HEALTH_RETENTION_DAYS=30
```

`LOG_VIEWER_SECRET` را روی سرور یک رشتهٔ خیلی طولانی بگذار (حداقل ۳۲ کاراکتر، بدون فاصله). خالی = route ثبت نمی‌شود.

مثال:

```env
LOG_VIEWER_SECRET=kharid-naraftan-be-in-safhe-agar-ramz-nadari-a7f3c9e2b1d4
```

آدرس:

`https://bots.pardisania.ir/api/kharid-naraftan-be-in-safhe-agar-ramz-nadari-a7f3c9e2b1d4`

Query:

- `?bot_id=53` فقط لاگ همان بات
- `?limit=100` پیش‌فرض ۱۰۰، سقف ۱۰۰۰
- `?format=text` متن خام برای کپی به Cursor
- `?source=file` tail از انتهای `storage/logs/laravel.log`

بعد از تغییر `.env`:

```bash
php artisan config:clear
php artisan route:clear
php artisan migrate
```

## ساختار دیتابیس

### جدول `app_log_entries`

- `level`, `message`, `bot_id` (nullable، بدون FK), `feature_key`, `context` json, `created_at`
- سقف نگهداشت: `APP_LOG_MAX_ROWS` (پیش‌فرض ۵۰۰۰)

### جدول `bot_health_events`

- `bot_id` (nullable، بدون FK)
- `feature_key` (مثلاً `sharabe_beheshti`, `verse`, `hadith`, `nahj`)
- `platform` (`bale` / `telegram` / `eitaa`)
- `event_type` (`channel_post`)
- `status` (`ok` / `fail`)
- `message`, `meta` json, `created_at`
- رویدادهای قدیمی‌تر از ۳۰ روز با `observability:prune` پاک می‌شوند

FK به `bots` گذاشته نشده (قانون MariaDB).

## جریان کاری

1. `Log::info/error` مثل قبل به `laravel.log` می‌رود و اگر context شامل `bot_id` باشد همان در `app_log_entries` ذخیره می‌شود.
2. ارسال روزانه کانال (`daily-channel:post`) پاسخ API را چک می‌کند و برای هر پلتفرم یک ردیف سلامت می‌نویسد.
3. جاب RSS شراب بهشتی بعد از `sendAudio` همین کار را می‌کند.
4. صفحهٔ سری لاگ‌ها و کارت سلامت (سبز = امروز ok داشته) را نشان می‌دهد.

کارت سبز یعنی آخرین `ok` مربوط به امروز است. اگر شراب بهشتی ۱۰ روز پست نگذاشته باشد، کارت قرمز است و تاریخ آخرین ok مشخص است.

## کران

با `php artisan schedule:run` روزانه اجرا می‌شود:

```bash
php artisan observability:prune
```

دستی:

```bash
php artisan observability:prune --log-max=5000 --health-days=30
```

## لاگ فایل و دیسک

این فاز کانال فایل را از `single` به `daily` عوض نمی‌کند. اگر دیسک پر می‌شود، جداگانه `LOG_CHANNEL=daily` را روی سرور در نظر بگیر.

## Rollback

1. `LOG_VIEWER_SECRET` را خالی کن و `config:clear` / `route:clear`
2. از `config/logging.php` کانال `database` را از `stack` بردار (برگرداندن به `['single']`)
3. `php artisan migrate:rollback --step=1` فقط اگر آخرین migration همین فیچر باشد؛ وگرنه جداول `app_log_entries` و `bot_health_events` را drop کن
4. هوک سلامت در `PostDailyVerseToChannels` و `RssPostItemTranslationToMessengerJob` side-by-side است؛ با revert کد، ارسال کانال به رفتار قبلی برمی‌گردد

توکن نمایشگر را در git نگذار؛ فقط در `.env` سرور.
