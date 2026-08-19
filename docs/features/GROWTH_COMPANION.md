# رشدیار (Growth Companion)

موتور رشد شخصی قابل‌تنظیم. حوزه زندگی hard-code نیست؛ معنویت فقط یکی از قالب‌هاست.

بعد از آنبوردینگ کاربر **کارت امروز** می‌بیند (متن ساخت‌یافته + یک اقدام اصلی + منوی پایدار ۵ تایی). تختهٔ موضوعات زیر «بیشتر» است. Mini App در این فاز نیست.

## Endpoint

| فیلد | مقدار |
|------|--------|
| `endpoint_id` | `webhook-growth-companion` |
| Route | `POST /api/webhook-growth-companion` |
| Seeder | `GrowthCompanionWebhookEndpointSeeder` |
| Templates | `GrowthCompanionTemplateSeeder` |
| `requires_token` | `true` |

Query: `origin`، `token`، (اختیاری) `bot_id`، `bot_mother_id`.

## فاز فعلی (رابط ربات)

1. در ربات مادر `/start` → نوع **رشدیار** → پلتفرم → توکن.
2. در ربات ساخته‌شده `/start`: حوزهٔ اولیه، شدت سؤال، ساعت (قابل رد کردن).
3. خانه = کارت امروز: وضعیت ثبت‌شده (یا «هنوز ثبت نشده»)، پیشرفت هفته، قدم بعدی، CTA اصلی در ردیف اول Reply Keyboard.
4. منوی پایدار: امروز، ثبت روزانه، سؤال از دستیار، مرور هفته، بیشتر.
5. ثبت روزانه چهار قدم دکمه‌ای (حال، انرژی، خواب، حرکت) جدول `growth_daily_checkins` را پر می‌کند. عدد جعلی روی خانه نشان داده نمی‌شود.
6. سؤال امروز یک کارت است (عنوان + متن + فقط «بعداً»). متن آزاد؛ تختهٔ ۶ موضوع در «موضوعات من».
7. `/start` دوم سؤال نمی‌فرستد؛ همان کارت امروز را نشان می‌دهد.
8. شدت: کم/متعادل = یک موضوع باز در روز؛ زیاد = دو موضوع. بعد از پاسخ، سؤال بعدی فقط اگر بودجه جا داشته باشد.
9. بیشتر: موضوعات من، تاریخچه، خروجی داده، حریم خصوصی، شدت، توقف/ادامه. حالت پیشرفته، روزهای هفته و جملهٔ AI پشت «پیشرفته».
10. مرور هفته ساخت‌یافته است (بردها، چالش‌ها، گلوله‌های متن، تمرکز بعد). متن طولانی بعد از ثبت به خلاصه + «متن کامل» تبدیل می‌شود.

ارسال زمان‌بندی‌شده با `php artisan growth:dispatch-due` هر ۵ دقیقه از طریق `schedule:run`. موضوع پاسخ‌داده‌شده یا خارج از بودجه ارسال نمی‌شود. پیام زمان‌بندی همان کارت سؤال امروز است (بدون دکمهٔ تنظیمات).

## راه‌اندازی

```bash
php artisan migrate
php artisan db:seed --class=GrowthCompanionWebhookEndpointSeeder
php artisan db:seed --class=GrowthCompanionTemplateSeeder
php artisan cache:clear
php artisan route:clear
```

بررسی روت:

```bash
php artisan route:list --path=webhook-growth-companion
```

LLM اختیاری (فقط واریانت سؤال؛ بدون تشخیص): `GROWTH_LLM_API_KEY` در `.env`. بدون کلید همان جمله‌های سیدر استفاده می‌شود.

## فایل‌های کلیدی

- `app/Http/Controllers/GrowthCompanionController.php`
- `app/Services/GrowthCompanionServiceImpl.php`
- `app/Services/GrowthQuestionSelector.php`
- `app/Console/Commands/GrowthDispatchDueCommand.php`
- `database/migrations/2026_08_17_200000_create_growth_companion_tables.php`
- `database/migrations/2026_08_18_150000_add_growth_companion_phase2_tables.php`
- `database/migrations/2026_08_19_160000_create_growth_daily_checkins.php`

بدون Foreign Key به جدول `bots` / `bot_users` (فقط index). FK اختیاری فقط داخل جدول‌های رشد (`growth_daily_checkins` → `growth_profiles`).

Lookup کاربر مثل Channel Poster با `chat_id + origin + bot_id` است؛ از `BotUsers::firstOrNew` استفاده نمی‌شود.

State مکالمه در `bot_user_states` با پیشوند `gc_`.

Callbackها با پیشوند `gc:` (خانه `gc:home`، سؤال `gc:q`، ثبت روزانه `gc:in:`، بیشتر `gc:more`، موضوعات `gc:b:` / `gc:topics`، تاریخچه `gc:hist`، متن کامل `gc:ft:`).

Reply Keyboard با متن ترجمه‌شده (`nav.today` و بقیه) در `handlePrivateMessage` هندل می‌شود؛ locale از `language_code` ربات.

## حریم خصوصی

مالک ربات مادر پاسخ‌های کاربران را نمی‌بیند. کاربر می‌تواند برنامه یا همه داده‌های رشد را از داخل چت حذف کند. متن پاسخ در لاگ ذخیره نمی‌شود. خروجی داده فقط به همان کاربر فرستاده می‌شود.

## Rollback

حذف route و seeder و `php artisan migrate:rollback` برای migrationهای رشد. ربات‌های قبلی دست نخورده می‌مانند.
