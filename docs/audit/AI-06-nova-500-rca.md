# AI-06 — Nova HTTP 500 RCA

**وضعیت: BLOCKED** (بازتولید شد، اما Root Cause بدون لاگ سرور قابل اثبات نیست)

## 1) خلاصهٔ مدیریتی

خطای 500 روی `https://bots.pardisania.ir/nova/login` **بازتولید شد** (GET، بدون تغییر داده): `HTTP 500`، حجم `6641` بایت، عنوان صفحه `Server Error` و **بدون هیچ Stack Trace** (یعنی `APP_DEBUG=false` روی پراکشن). این یعنی صفحهٔ عمومی Laravel است و متن خطا فقط در `storage/logs/laravel.log` سرور وجود دارد که در دسترس نیست. بنابراین نمی‌توان Root Cause را مستند کرد؛ اما مجموعهٔ علل محتمل بر اساس کد و پیکربندی محدود شده است.

## 2) وضعیت فعلی

| مورد | مقدار |
|---|---|
| `GET /nova/login` | **500** — `total=0.657s`، `size=6609–6641` |
| بدنهٔ پاسخ | فقط «Server Error» (بدون جزئیات) |
| `config/nova.php` | موجود؛ `path=/nova`، `guard=NOVA_GUARD`، middleware شامل `HandleInertiaRequests` ([`config/nova.php:101`](../../config/nova.php:101)) |
| Nova | fork `saber13812002/laravel-nova 4.32.16` ([`composer.json:25`](../../composer.json:25)) |
| Gate | فقط `saber.tabatabaei@gmail.com` → `viewNova` ([`NovaServiceProvider.php:43`](../../app/Providers/NovaServiceProvider.php:43)) |
| Migration | 197 رکورد در DB محلی؛ `action_events` = موجود؛ `users` = موجود؛ `jobs` = **ناموجود**؛ `failed_jobs` = موجود |
| Assets | `public/vendor/nova` در local **موجود** |
| Local error log | هیچ 500 مربوط به Nova ثبت نشده؛ فقط stack trace دستور `nova:user` (شامل اطلاعات حساس) و خطاهای نامرتبط |

## 3) شواهد کد و دیتابیس

- صفحهدان: [`NovaServiceProvider::routes()`](../../app/Providers/NovaServiceProvider.php:26) — `withAuthenticationRoutes()->withPasswordResetRoutes()`.
- Gate ناهم‌خوان با سیاست: `viewNova` فقط یک ایمیل را مجاز می‌کند، ولی خطای 500 در `/nova/login` **قبل از احراز هویت** رخ می‌دهد ⇒ Gate علت نیست (اما برای ورود موفق سد خواهد شد).
- `config/nova.php` کلیدهای `license_key`, `middleware`, `api_middleware`, `storage_disk`, `actions.resource` را تعریف می‌کند — با نسخهٔ fork باید تطبیق داده شود.

## 4) وضعیت Local / GitHub / Production

| محیط | وضعیت |
|---|---|
| Local | Nova محلی اجرا/تست نشده در این audit؛ `public/vendor/nova` موجود |
| GitHub | `7702b68f` — بدون تغییر مرتبط با Nova در commits اخیر (`cp-signature:` scope) |
| Production | کد `0b8f990d`/`cb5b8423` (عقب‌تر از local) — **لاگ سرور دسترسی‌ناپذیر** |

## 5) تست‌های انجام‌شده و نتایج واقعی

| تست | نتیجه |
|---|---|
| `GET /nova/login` (بدون Login Attempt، بدون تغییر داده) | **500** (بازتولید شد) |
| بررسی بدنه برای Stack Trace | ناموفق — `APP_DEBUG=false` |
| Grep لاگ محلی برای Nova 500 | چیزی یافت نشد (خطا در پراکشن رخ می‌دهد) |
| بررسی وجود جدول‌های لازم (local) | `action_events` ✅، `users` ✅ |

## 6) موارد ناشناخته و محدودیت دسترسی

- متن دقیق Exception در `storage/logs/laravel.log` پراکشن.
- وجود `public/vendor/nova` و `public/build/manifest.json` روی پراکشن (احتمال زیاد Assets در deploy دستی جا افتاده).
- نسخهٔ واقعی PHP/Fork روی پراکشن (`8.2.34` مستند شده).
- وضعیت `config:cache` و `route:cache` پراکشن (deploy ناقص).

## 7) مشکلات و ریسک‌ها — فرضیه‌ها به‌ترتیب احتمال

| # | فرضیه | چگونه تأیید/رد می‌شود | ریسک |
|---|---|---|---|
| 1 | **Config Cache قدیمی** بعد از deploy (`config/nova.php` یا `NOVA_*` قدیمی در bootstrap/cache/config.php) | `php artisan config:clear` سپس درخواست؛ یا `php artisan about` | بالا — شایع‌ترین علت 500 بعد از deploy دستی |
| 2 | **Assets Nova روی پراکشن منتشر نشده** (`public/vendor/nova`, `manifest`) | `ls public/vendor/nova` و `ls public/build` | بالا |
| 3 | **Migration ناقص** (`action_events` یا جدول‌های Nova) | `php artisan migrate:status \| tail` | متوسط |
| 4 | **نسخهٔ PHP 8.2 + fork 4.32.16** ناسازگاری در `HandleInertiaRequests` | `tail storage/logs/laravel.log` | متوسط |
| 5 | **کش Route/View کهنه** (route:cache با کنترلر حذف‌شده) | `route:clear`، `view:clear` | متوسط |
| 6 | Gate ناهم‌خوان (فقط یک ایمیل) | بعد از رفع 500، ورود ادمین دیگر تست شود | پایین برای 500، بالا برای دسترسی |

## 8) قابلیت‌های قابل استفاده مجدد

- اسکریپت/رویهٔ بازتولید فقط-GET (بدون Login Attempt) برای پایش.
- `/api/health` و `/api/metrics` موجود ([`HealthController`](../../app/Http/Controllers/HealthController.php:21)) برای تفکیک «کل اپ خراب است» از «فقط Nova خراب است».

## 9) پیشنهاد MVP (برنامهٔ اصلاح مرحله‌ای)

1. **تشخیص (فقط خواندن):** `tail -n 200 storage/logs/laravel.log` + `php artisan about` + `ls public/vendor/nova` + `php artisan migrate:status`.
2. **اصلاح بی‌ریسک:** `php artisan config:clear && php artisan route:clear && php artisan view:clear` (بدون cache:clear دیتابیس/Cache).
3. اگر Assets نبود: `php artisan nova:publish` (فقط پس از Backup).
4. در صورت نیاز: `php artisan config:cache` مجدد در پنجرهٔ کم‌ترافیک.
5. تأیید: `GET /nova/login` = 200 و سپس یک Login کنترل‌شده توسط مالک.

⚠️ هیچ‌کدام از این گام‌ها در این مأموریت اجرا نشد (بدون دسترسی سرور).

## 10) مراحل بعدی و وابستگی‌ها

| گام | وابستگی |
|---|---|
| خواندن لاگ پراکشن | دسترسی cPanel Terminal یا Log Viewer |
| اجرای گام‌های اصلاح | تأیید مالک (چون تغییر runtime است) |
| بازبینی Gate Nova | تصمیم مالک (چند ادمین؟) |

**Status: BLOCKED** — Root Cause بدون لاگ سرور قابل اثبات نیست.
