# امنیت و چرخش توکن‌های AI (WS-C)

وضعیت: **DONE (کد) + PARTIAL (چرخش توکن‌ها — نیازمند اقدام مالک)**
تاریخ: ۱۴۰۵/09/23 (۲۰۲۶-11-14)

## ۱. یافته‌ها

| # | یافته | شواهد | وضعیت |
|---|--------|--------|--------|
| 1 | ۱۹ توکن واقعی ربات (Bale/Telegram) در رپوزیتری | `database/seeders/BotsTableSeeder.php` (lines 33–477) | ✅ برطرف شد — env-based |
| 2 | LiteLLM در متن خطا اثر انگشت کلید را چاپ می‌کند (`Key Hash (Token): ...`, `Received API Key: sk-...`) | خروجی زنده `ai:discover-models` پیش از redaction | ✅ برطرف شد — `AiErrorRedactor` |
| 3 | لاگ‌های قدیمی شامل خطای 401 **بدون** redaction | `storage/logs/laravel.log` lines 72022–72033 | ✅ برطرف شد برای لاگ‌های جدید؛ لاگ قدیمی local-only |
| 4 | `config/ai.php` با `verify=false` (SSL off) | commit قبل از WS-B | ✅ برطرف شد — Strict TLS + CA bundle |
| 5 | `error_log` در ریشه رپوزیتری track شده بود | `git ls-files error_log` | ✅ برطرف شد — gitignore + untrack |
| 6 | `storage/` شامل `laravel.log` با داده‌های debug | — | ⚠️ در cPanel، لاگ‌ها در `storage/logs` (خارج از git) |
| 7 | کلید AI در دیتابیس `ai_providers.api_key` | cast `encrypted` (Laravel encryption) | ✅ موجود — AES-256 با APP_KEY |

## ۲. تغییرات اعمال‌شده (کد)

1. **`database/seeders/BotsTableSeeder.php`**: ۱۹ توکن واقعی → `env('BOTS_SEED_<PLATFORM>_<BOT>_TOKEN')`
   - مثال: `'bale_bot_token' => env('BOTS_SEED_BALE_TABA_BOT_TOKEN')`
   - کلیدهای env در `.env.example` با مقدار خالی ثبت شده‌اند.
   - **مهم**: اجرای `db:seed --class=BotsTableSeeder` بدون مقدار env → توکن‌ها NULL می‌شوند.
     برای production، مقدار واقعی هر توکن در `.env` سرور مقصد قرار می‌گیرد.
2. **`app/Services/Ai/AiErrorRedactor.php`**: ماسک‌کردن `sk-...`، `Key Hash (Token)`، `Received API Key`، `Bearer ...`، `master key`
3. **`app/Services/AiProviderService.php`**: تمام مسیرهای خطا از redactor عبور می‌کنند (۶ نقطه)
4. **`config/ai.php`**: Strict TLS پیش‌فرض با `storage/certs/ismc-ai-ca-bundle.pem`
5. **`.gitignore`**: `error_log`
6. **تست‌ها**: `tests/Unit/AiErrorRedactorTest.php` (6 تست) + `tests/Feature/AiTlsConfigTest.php` (6 تست)

## ۳. برنامه چرخش توکن‌ها (چک‌لیست cPanel — اجرا توسط مالک)

### ۳.۱ توکن LiteLLM (اولویت ۱ — بلاک‌کننده اصلی)
- [ ] در LiteLLM (`https://ai.ismc.ir`) یک **توکن جدید** با نام `bots.pardisania.ir` بسازید.
- [ ] محدوده: فقط `chat/completions`، `models`، (در آینده: `audio/transcriptions`)
- [ ] مقادیر جدید را در cPanel → Setup PHP / Environment Variables یا `.env` در `/home/pardisa2/bots`:
  - `AI_DEFAULT_API_KEY=<token جدید>`
- [ ] `php artisan config:clear && php artisan cache:clear`
- [ ] تست: `php artisan ai:health-check` → باید «اتصال با موفقیت» + پاسخ چت «سلام»
- [ ] پس از تأیید، توکن قدیمی در LiteLLM را **غیرفعال** کنید (نه حذف — ۷ روز مهلت rollback)
- [ ] **توکن قدیمی در تاریخ <تاریخ+۷ روز> حذف می‌شود** — یادآوری ثبت کنید

### ۳.۲ توکن‌های ربات (۱۹ عدد — اولویت ۲)
- [ ] برای هر ربات فعال در production، از پنل Bale/Telegram **توکن جدید** بگیرید (revoke + recreate)
- [ ] مقادیر را در `.env` سرور به‌روزرسانی کنید (`BOTS_SEED_*`) — فقط اگر از seeder استفاده می‌شود
- [ ] **بهتر**: به‌جای seeder، رکوردهای `bots` در دیتابیس production مستقیماً آپدیت شوند (Nova → Bots)
  و از re-seed در production پرهیز شود
- [ ] بررسی: `php artisan tinker --execute="App\Models\Bot::with('botMother')->get(['id','name','type'])->each(fn(\$b)=>print(\$b->id.' '.\$b->name.PHP_EOL));"` (بدون چاپ توکن)

### ۳.۳ کلیدهای دیگر (اولویت ۳)
- [ ] `APP_KEY`: اگر هرگز در log چاپ نشده، چرخش **اختیاری** است (اما با chown 600 روی .env)
- [ ] `NOVA_LICENSE_KEY`: ثابت (مربوط به Nova) — چرخش فقط با تغییر license
- [ ] توکن‌های SMTP/Email: بررسی `config/mail.php` — بدون نشت در لاگ‌ها
- [ ] `API tokens` (sanctum): `php artisan Sanctum::unexpired` — بررسی دوره‌ای

## ۴. نظارت و بازرسی دوره‌ای

| کار | فرکانس | ابزار |
|-----|--------|-------|
| اسکن رپوزیتری برای secret | هر PR | `git grep -E "sk-[A-Za-z0-9]{10,}|_bot_token'\s*=>\s*'[0-9]" -- ':!vendor' ':!storage'` |
| بررسی لاگ production برای `Key Hash` | هفتگی | cPanel → Logs → grep `Key Hash` |
| سلامت provider AI | هر ۱ ساعت | `ai:health-check` (scheduler) |
| چک TLS certificate | ماهانه | `ai:discover-models` (verify TLS implicit) |

## ۵. ذخیره‌سازی کلیدها

- **production**: `.env` با permission `600`، owner `pardisa2` (cPanel)
- **دیتابیس**: `ai_providers.api_key` با cast `encrypted` (AES-256-CBC با `APP_KEY`)
- **git**: هیچ secret — فقط env references
- **لاگ**: همه پیام‌های AI از `AiErrorRedactor` عبور می‌کنند

## ۶. Rollback

اگر چرخش توکن باعث خرابی شد:
1. در `.env`، مقدار قبلی (توکن قدیمی — تا ۷ روز معتبر) را برگردانید
2. `php artisan config:clear`
3. `ai:health-check`
