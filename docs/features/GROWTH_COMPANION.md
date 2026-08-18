# رشدیار (Growth Companion)

موتور رشد شخصی قابل‌تنظیم. حوزه زندگی hard-code نیست؛ معنویت فقط یکی از قالب‌هاست.

کاربر بعد از آنبوردینگ **تختهٔ روزانه** می‌بیند: چند دسته با چک‌باکس خالی یا تیک سبز. تنظیمات پیشرفته اختیاری است.

## Endpoint

| فیلد | مقدار |
|------|--------|
| `endpoint_id` | `webhook-growth-companion` |
| Route | `POST /api/webhook-growth-companion` |
| Seeder | `GrowthCompanionWebhookEndpointSeeder` |
| Templates | `GrowthCompanionTemplateSeeder` |
| `requires_token` | `true` |

Query: `origin`، `token`، (اختیاری) `bot_id`، `bot_mother_id`.

## فاز فعلی (۲)

1. در ربات مادر `/start` → نوع **رشدیار** → پلتفرم → توکن.
2. در ربات ساخته‌شده `/start`: حوزهٔ اولیه، شدت سؤال، ساعت (قابل رد کردن).
3. تختهٔ پیش‌فرض شش دسته (`health`, `family`, `work`, `spirituality`, `study`, `self`). ورزش، روابط و دستهٔ سفارشی از «افزودن دسته».
4. ضربه روی دستهٔ باز → یک سؤال. بعد از پاسخ، همان دکمه تیک سبز می‌خورد.
5. ضربه دوباره همان روز → فقط «این موضوع امروز بررسی شده»؛ سؤال تکرار نمی‌شود.
6. بعد از ساعت **۳** به وقت پروفایل (`Asia/Tehran` پیش‌فرض) چک‌باکس‌های روزانه خالی می‌شوند. موضوعات هفتگی تا مرز هفته قفل می‌مانند.
7. شدت: کم/متعادل = یک موضوع باز در روز؛ زیاد = دو موضوع.
8. تنظیمات: آهنگ هر موضوع (روزانه/هفتگی)، مرور هفته، خروجی داده، حالت پیشرفته (روزهای هفته، جمله با LLM در صورت کلید).

ارسال زمان‌بندی‌شده با `php artisan growth:dispatch-due` هر ۵ دقیقه از طریق `schedule:run`. موضوع پاسخ‌داده‌شده یا خارج از بودجه ارسال نمی‌شود.

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

بدون Foreign Key به جدول `bots` / `bot_users` (فقط index).

Lookup کاربر مثل Channel Poster با `chat_id + origin + bot_id` است؛ از `BotUsers::firstOrNew` استفاده نمی‌شود.

State مکالمه در `bot_user_states` با پیشوند `gc_`.

Callbackها با پیشوند `gc:` (تخته `gc:b:`، افزودن `gc:a:`، حذف `gc:x:`).

## حریم خصوصی

مالک ربات مادر پاسخ‌های کاربران را نمی‌بیند. کاربر می‌تواند برنامه یا همه داده‌های رشد را از داخل چت حذف کند. متن پاسخ در لاگ ذخیره نمی‌شود. خروجی داده فقط به همان کاربر فرستاده می‌شود.

## Rollback

حذف route و seeder و `php artisan migrate:rollback` برای migrationهای رشد. ربات‌های قبلی دست نخورده می‌مانند.
