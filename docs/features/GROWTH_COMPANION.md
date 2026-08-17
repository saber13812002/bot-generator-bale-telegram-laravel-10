# رشدیار (Growth Companion)

موتور رشد شخصی قابل‌تنظیم. حوزه زندگی hard-code نیست؛ معنویت فقط یکی از قالب‌هاست.

کاربر ساده روزی یک سؤال می‌گیرد و جواب می‌دهد. تنظیمات پیشرفته مخفی است.

## Endpoint

| فیلد | مقدار |
|------|--------|
| `endpoint_id` | `webhook-growth-companion` |
| Route | `POST /api/webhook-growth-companion` |
| Seeder | `GrowthCompanionWebhookEndpointSeeder` |
| Templates | `GrowthCompanionTemplateSeeder` |
| `requires_token` | `true` |

Query: `origin`، `token`، (اختیاری) `bot_id`، `bot_mother_id`.

## فاز فعلی (MVP)

1. در ربات مادر `/start` → نوع **رشدیار** → پلتفرم → توکن.
2. در ربات ساخته‌شده `/start`.
3. حوزه را انتخاب کن (سلامت، خانواده، کار، معنویت، مطالعه، خودشناسی، ورزش، روابط، هدف شخصی).
4. شدت تعامل و ساعت (قابل رد کردن).
5. یک سؤال بگیر، جواب بده.
6. تنظیمات: توقف، روزانه/هفتگی، سؤال سفارشی، حذف برنامه، حذف داده‌ها.

ارسال بعدی با `php artisan growth:dispatch-due` هر ۵ دقیقه از طریق `schedule:run`.

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

## فایل‌های کلیدی

- `app/Http/Controllers/GrowthCompanionController.php`
- `app/Services/GrowthCompanionServiceImpl.php`
- `app/Services/GrowthQuestionSelector.php`
- `app/Console/Commands/GrowthDispatchDueCommand.php`
- `database/migrations/2026_08_17_200000_create_growth_companion_tables.php`

بدون Foreign Key به جدول `bots` / `bot_users` (فقط index).

Lookup کاربر مثل Channel Poster با `chat_id + origin + bot_id` است؛ از `BotUsers::firstOrNew` استفاده نمی‌شود.

State مکالمه در `bot_user_states` با پیشوند `gc_`.

Callbackها با پیشوند `gc:`.

## حریم خصوصی

مالک ربات مادر پاسخ‌های کاربران را نمی‌بیند. کاربر می‌تواند برنامه یا همه داده‌های رشد را از داخل چت حذف کند. متن پاسخ در لاگ ذخیره نمی‌شود.

## Rollback

حذف route و seeder و `php artisan migrate:rollback` برای migration رشد. ربات‌های قبلی دست نخورده می‌مانند.
