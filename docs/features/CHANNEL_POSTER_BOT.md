# ربات ارسال به کانال‌ها

ربات «ارسال به کانال‌ها» نوع ربات جدیدی است که مالک در **خصوصی بله** مطلب می‌فرستد و ربات آن را به کانال بله منتشر می‌کند.

ربات‌های قبلی (`admin-daily-channel`، صف رسانه، `content-submission`) دست نخورده مانده‌اند. ویزارد اتصال کانال **داخل همین ربات** است، نه در ربات مادر.

## Endpoint

| فیلد | مقدار |
|------|--------|
| `endpoint_id` | `webhook-channel-poster` |
| Route | `POST /api/webhook-channel-poster` |
| Seeder | `ChannelPosterWebhookEndpointSeeder` |
| `requires_token` | `true` |

Query پارامترها: `origin`، `token`، (اختیاری) `bot_id`، `bot_mother_id`.

## فاز فعلی: تگ + چند مقصد

یک ربات می‌تواند چند کانال بله/تلگرام/ایتا داشته باشد. مقصدها با **تگ** گروه‌بندی می‌شوند (مثلاً «شراب بهشتی» یا «پدر ژپتو»). مطلب به همه مقصدهای همان تگ می‌رود.

1. در ربات مادر `/start` → نوع **ارسال به کانال‌ها** → پلتفرم بله → توکن.
2. در ربات ساخته‌شده `/start` بزن. ربات را ادمین کانال بله کن و یک پست فوروارد کن (یا شناسه عددی بفرست).
3. اگر کانال **بدون تگ** است، نام تگ را بفرست (مثلاً شراب بهشتی). ارسال مطلب بدون تگ هم همان کانال را هدف می‌گیرد.
4. `/add` → تگ موجود یا تگ جدید → بله / تلگرام / ایتا.
   - بله: فوروارد پست کانال.
   - تلگرام: توکن ربات تلگرام + شناسه کانال.
   - ایتا: توکن ایتایار + شناسه کانال.
5. اگر فقط یک تگ داری، مطلب همان‌جا می‌رود. اگر چند تگ داری، ربات می‌پرسد کدام تگ.

سروش در منو هست ولی هنوز «به‌زودی» است. مدیای بله با `file_id` به کانال بله می‌رود؛ تلگرام/ایتا برای امشب بیشتر متن (و در تلگرام در صورت امکان همان file_id).

فقط `bots.bale_owner_chat_id` می‌تواند ربات را استفاده کند.

## راه‌اندازی

```bash
php artisan migrate
php artisan db:seed --class=ChannelPosterWebhookEndpointSeeder
php artisan cache:clear
php artisan route:clear
```

اگر ربات در لیست ربات مادر نیامد:

```bash
php artisan cache:clear
```

یا ایمپورت پیش‌فرض endpointها (در صورت استفاده در محیط شما).

بررسی روت:

```bash
php artisan route:list --path=webhook-channel-poster
```

## فایل‌های کلیدی

- `app/Http/Controllers/ChannelPosterBotController.php`
- `app/Services/ChannelPosterBotServiceImpl.php`
- `app/Services/TelegramChannelPosterPublisher.php`
- `app/Models/ChannelPosterDestination.php`
- `database/migrations/2026_08_16_182200_create_channel_poster_destinations_table.php`
- `database/migrations/2026_08_16_201500_add_tag_to_channel_poster_destinations.php`

بدون Foreign Key به جدول `bots` (فقط index).

## جدول

`channel_poster_destinations`

- چند ردیف برای یک `bot_id` مجاز است (چند کانال بله/تلگرام/ایتا)
- `tag` nullable — مقصدهای بدون تگ همچنان کار می‌کنند؛ با `/start` می‌توان تگ گذاشت
- یکتا: `bot_id + platform + channel_chat_id`
- `bot_token` برای بله خالی است؛ برای تلگرام و ایتا ذخیره می‌شود
- `verified_at` بعد از پست تست موفق

State مکالمه در `bot_user_states`:

- `cp_awaiting_bale_forward`
- `cp_awaiting_untagged_name` / `cp_awaiting_tag_name` / `cp_awaiting_platform`
- `cp_awaiting_telegram_token` / `cp_awaiting_telegram_chat_id`
- `cp_awaiting_eitaa_token` / `cp_awaiting_eitaa_chat_id`
- `cp_awaiting_destination`

## چک‌لیست تست بله

- [ ] Seeder و migrate اجرا شده و گزینه «ارسال به کانال‌ها» در ربات مادر هست.
- [ ] ساخت ربات با توکن بله و ست شدن webhook.
- [ ] `/start` توسط غیرمالک پیام «فقط مالک» می‌دهد.
- [ ] `/start` مالک بدون کانال، فوروارد یا شناسه عددی می‌خواهد.
- [ ] فوروارد چت خصوصی کاربر رد می‌شود.
- [ ] فوروارد بله بدون فیلد `type` (فقط `id`) پذیرفته می‌شود.
- [ ] شناسه عددی کانال به‌جای فوروارد کار می‌کند.
- [ ] اگر ربات ادمین نباشد، پیام خطا می‌آید.
- [ ] بعد از ادمین شدن و فوروارد/شناسه، پست تست در کانال ظاهر می‌شود.
- [ ] `/start` برای کانال بدون تگ، نام تگ می‌خواهد؛ ارسال مطلب بدون تگ هم همان کانال را هدف می‌گیرد.
- [ ] `/add` توکن و شناسه تلگرام/ایتا را می‌گیرد و زیر همان تگ ذخیره می‌کند.
- [ ] با دو تگ، ربات می‌پرسد مطلب برای کدام تگ است.
- [ ] `/cancel` state را پاک می‌کند.

## عیب‌یابی فوروارد بله

بله گاهی `forward_from_chat` را بدون `type` می‌فرستد (نمونه در `tests/curl/sample-webhooks.json`). ربات دیگر به `type=channel` اجباری وابسته نیست.

اگر باز هم فوروارد تشخیص داده نشد:

1. شناسه عددی **کانال** را بفرست (نه آی‌دی ربات). از ربات Get Chat ID یا از خود فوروارد بعد از دیپلوی.
2. در لاگ به‌ترتیب این‌ها را ببین:
   - `[ChannelPoster] Bot resolved` — اگر نباشد، webhook به این ربات نرسیده.
   - `[ChannelPoster] No private message in update` — بدنه آپدیت بله `message` ندارد.
   - `[ChannelPoster] Not owner` — `bale_owner_chat_id` با چت تو یکی نیست.
   - `[ChannelPoster] Channel target not parsed` — فوروارد کانال در payload نیست.
   - `[ChannelPoster] Test send failed` — ربات نتوانسته در کانال پست تست بگذارد (`description` را بخوان).

## Rollback

1. روت `/webhook-channel-poster` را از `routes/api.php` حذف کنید.
2. ردیف `webhook_endpoints` با `endpoint_id = webhook-channel-poster` را حذف کنید.
3. `php artisan migrate:rollback` برای migration این فیچر (یا drop جدول `channel_poster_destinations`).
4. Cache و route را پاک کنید.

ربات‌های قبلی و webhookهای دیگر تحت تأثیر نیستند.

## فاز بعدی (پیاده نشده)

### تلگرام

- از داخل ربات بله: ساخت ربات تلگرام و دادن توکن.
- همان ربات تلگرام را ادمین کانال تلگرام کردن.
- تایپ «سلام» در کانال تلگرام؛ وب‌هوک `channel_post` را می‌بیند، پست تست می‌گذارد و روی بله تأیید می‌کند.

### ایتا

- عضو کردن ایتاییا در کانال.
- دادن توکن ایتاییا و شناسه کانال از پنل ایتاییا.

## آخرین به‌روزرسانی

2026-08-16
