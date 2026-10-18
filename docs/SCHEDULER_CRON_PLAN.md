# P1 — برنامه Cron نهایی (cPanel + PHP 8.2)

> هدف: **فقط یک cron اصلی** برای scheduler، حذف cron تکراری `app:rss_read_translate`،
> و حذف workerهای بیهوده. همه‌ی entryها داخل `app/Console/Kernel.php` تعریف‌شده‌اند و با
> `schedule:run` هر دقیقه اجرا می‌شوند.

## ۱. چرا؟

| مشکل تولیدی (مستند) | ریشه |
|---|---|
| `schedule:run` فقط ساعت 23:59 اجرا می‌شد | جدول cron cPanel فقط یک entry برای schedule داشت |
| cron مستقل ۱۹ دقیقه‌ای `app:rss_read_translate` | تکرار وظیفه‌ی scheduler + اجرای موازی بدون قفل |
| ۱۶ فرآیند `queue:work` قدیمی (stale) | `QUEUE_CONNECTION=sync` است — اصلاً workerی لازم نیست |
| ناهمخوانی app-UTC / سرور +03:30 | entryهای ساعت‌دار همه **UTC** هستند و **نمی‌توان** جابه‌جا کرد |

## ۲. جدول cron نهایی (cPanel → Cron Jobs)

```cron
# فقط این دو خط — و هیچ‌چیز دیگر.
* * * * * cd /home/USERNAME/bot-platform && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1

0 4 * * * cd /home/USERNAME/bot-platform && /usr/local/bin/php artisan storage:logs-cleanup >> /dev/null 2>&1
```

توضیح:
- خط ۱: `schedule:run` هر دقیقه. **فقط** یک entry. تمام entryهای Kernel (RSS هر ۱۵ دقیقه،
  heartbeat هر دقیقه، feeds-health هفتگی، verse-slots ۰۰/۰۶/۱۲/۱۸ UTC، ...) از این اجرا می‌شوند.
- خط ۲ (اختیاری، نگهداری): پاک‌سازی لاگ‌ها یک‌بار در روز ساعت ۰۴:۰۰ UTC (۰۷:۳۰ ایران).
  اگر این command را ندارید، خط را حذف کنید.
- مسیر `php` دقیقاً `/usr/local/bin/php` (PHP 8.2.34 cPanel) — از `php` خالی استفاده نکنید.
- `>> /dev/null 2>&1` برای cron نگران نیست چون لاگ‌های عملیاتی داخل application log (Laravel) می‌روند.

## ۳. حذف‌ها

1. **cron مستقل `app:rss_read_translate` (هر ۱۹ دقیقه)** → حذف شود.
   کار را همان entry `RssReadTranslate::everyFifteenMinutes()->withoutOverlapping()` در Kernel انجام می‌دهد.
   حتی اگر موقتاً حذف نشود، `Cache::lock('rss_read_translate_lock', 900)` داخل خود command جلوی اجرای
   موازی را می‌گیرد، ولی حذف cron مستقل الزامی است تا لایه‌ی دفاع اول فعال بماند.
2. **همه‌ی `queue:work` / `queue:listen` cron‌ها و ۱۶ فرآیند قدیمی** → حذف + kill:
   ```bash
   ps -ef | grep 'artisan queue:work' | grep -v grep
   # سپس pid هر فرآیند:
   kill <pid>
   ```
   با `QUEUE_CONNECTION=sync` هیچ jobی در صف نمی‌ماند؛ workerها بیهوده‌اند و فقط RAM/DB
   connection خالی می‌گیرند و `$timeout` جاب‌ها را بی‌اثر می‌کنند.

## ۴. قفل‌های همزمانی (Concurrency locks)

| Entry | مکانیسم |
|---|---|
| `app:rss_read_translate` | `withoutOverlapping()` (Kernel) + `Cache::lock('rss_read_translate_lock', 900)` (داخل command) — دو لایه |
| `pipeline:heartbeat` | `withoutOverlapping()` |
| `rss:feeds-health` | `withoutOverlapping()->onOneServer()` |
| `pipeline:watch` | `withoutOverlapping()->onOneServer()` |
| سایر entryها | `withoutOverlapping()` (+ `onOneServer()` جایی که معنی دارد) |

`onOneServer` در محیط تک‌سروری cPanel بی‌ضرر است؛ اگر روزی سرور دوم اضافه شد،
دریافت‌کننده‌ی lock دوم entry را رد می‌کند (نیاز به Redis/cache شبکه‌ای).

## ۵. UTC vs +03:30

- `config('app.timezone')` = `UTC` (بدون تغییر).
- **entryهای ساعت‌دار (verse slots 00/06/12/18 UTC) نباید تغییر کنند** — `PostOverdueWatchdog`
  و `shouldRunInSlot` روی همین UTC-اسلات‌ها تنظیم‌اند (README_LEGACY.md، بخش «🚨 اصلاح مهم»).
- cron cPanel خودسرور (معمولاً UTC) اجرا می‌شود؛ `schedule:run` هر دقیقه که اجرا شود،
  entryها بر اساس ساعت اپلیکیشن (UTC) trigger می‌شوند — پس timezone سرور بی‌تأثیر است.

## ۶. بررسی صحت بعد از دپلوی

```bash
# ۱) cron هر دقیقه کار می‌کند؟
tail -f storage/logs/laravel.log   # entry "pipeline:heartbeat" هر دقیقه log می‌شود

# ۲) heartbeat تازه است؟
/usr/local/bin/php artisan pipeline:status

# ۳) برنامه‌ی کامل entryها
/usr/local/bin/php artisan schedule:list
```

موفقیت: `pipeline:status` نشان می‌دهد `Scheduler heartbeat: OK (age < 60s)` و
`rss:feeds-health` / `app:rss_read_translate` در لاگ با `duration_ms` ثبت می‌شوند.
