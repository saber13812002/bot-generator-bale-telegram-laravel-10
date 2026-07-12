# 📘 راهنمای مدیریت ربات‌های قرآنی

> تاریخ آخرین بروزرسانی: 2026-07-12

## فهرست مطالب

1. [لیست کامل ربات‌های قرآنی](#1-لیست-کامل-ربات‌های-قرآنی)
2. [بررسی وضعیت ربات‌ها](#2-بررسی-وضعیت-ربات‌ها)
3. [بررسی Webhook با API تلگرام](#3-بررسی-webhook-با-api-تلگرام)
4. [آمار کاربران](#4-آمار-کاربران)
5. [مشاهده لاگ‌ها](#5-مشاهده-لاگ‌ها)
6. [ارسال پیام به کاربران](#6-ارسال-پیام-به-کاربران)
7. [ساختار دیتابیس](#7-ساختار-دیتابیس)

---

## 1. لیست کامل ربات‌های قرآنی

ربات‌های قرآنی در دو پلتفرم **تلگرام** و **بله** فعال هستند.  
اطلاعات این ربات‌ها در [`config/quran_bots.php`](config/quran_bots.php) ذخیره شده است.

### ربات‌های تلگرام (11 زبان)

| ردیف | زبان | نام ربات | لینک |
|------|------|----------|------|
| 1 | 🇸🇦 عربی | `Quran_Hifzbot` | [t.me/Quran_Hifzbot](https://t.me/Quran_Hifzbot) |
| 2 | 🇵🇰 اردو | `Quran_urdubot` | [t.me/Quran_urdubot](https://t.me/Quran_urdubot) |
| 3 | 🇨🇳 چینی | `Gulanjing_yuedu_bot` | [t.me/Gulanjing_yuedu_bot](https://t.me/Gulanjing_yuedu_bot) |
| 4 | 🇪🇸 اسپانیایی | `Coran_spanish_bot` | [t.me/Coran_spanish_bot](https://t.me/Coran_spanish_bot) |
| 5 | 🇩🇪 آلمانی | `KoranTextBot` | [t.me/KoranTextBot](https://t.me/KoranTextBot) |
| 6 | 🇫🇷 فرانسوی | `Coran_Texte_bot` | [t.me/Coran_Texte_bot](https://t.me/Coran_Texte_bot) |
| 7 | 🇬🇧 انگلیسی | `Tilawat_Quran_Bot` | [t.me/Tilawat_Quran_Bot](https://t.me/Tilawat_Quran_Bot) |
| 8 | 🇮🇷 فارسی | `hefzaquran_word_daily_bot` | [t.me/hefzaquran_word_daily_bot](https://t.me/hefzaquran_word_daily_bot) |
| 9 | **🇷🇺 روسی** | **`Koran_chteniye_bot`** | **[t.me/Koran_chteniye_bot](https://t.me/Koran_chteniye_bot)** |
| 10 | 🇹🇷 ترکی | `Kurani_Kerim_bot` | [t.me/Kurani_Kerim_bot](https://t.me/Kurani_Kerim_bot) |
| 11 | 🇮🇱 عبری | `Quran_in_Hebrew_translation_BOT` | [t.me/Quran_in_Hebrew_translation_BOT](https://t.me/Quran_in_Hebrew_translation_BOT) |

### ربات‌های بله

ربات‌های بله نیز با نام‌های مشابه در پلتفرم بله فعال هستند.  
اطلاعات کامل آنها در جدول [`bots`](database/migrations/2023_03_31_190444_create_bots_table.php) با فیلد `bale_bot_name` ذخیره شده است.

---

## 2. بررسی وضعیت ربات‌ها

### روش 1️⃣: استفاده از Artisan Command (پیشنهادی)

یک کامند جدید ایجاد شده که تمام اطلاعات را یکجا نشان می‌دهد:

```bash
php artisan quran:check-bots-status
```

با جزئیات بیشتر:

```bash
php artisan quran:check-bots-status --detail
```

فقط تلگرام یا فقط بله:

```bash
php artisan quran:check-bots-status --type=telegram
php artisan quran:check-bots-status --type=bale
```

این کامند اطلاعات زیر را نمایش می‌دهد:
- ✅ وضعیت Webhook
- 🟢/🔴 وضعیت فعال/غیرفعال بودن ربات
- 👥 تعداد کاربران ثبت‌نام شده
- 📊 تعداد درخواست‌های 30 روز اخیر
- 🕐 آخرین فعالیت

فایل کامند: [`app/Console/Commands/CheckQuranBotsStatus.php`](app/Console/Commands/CheckQuranBotsStatus.php)

### روش 2️⃣: استفاده از SQL Queries

فایل کوئری‌های SQL در [`database/sql_tests/quran_bots_status_queries.sql`](database/sql_tests/quran_bots_status_queries.sql) قرار دارد.  
می‌توانید آن را در MySQL Workbench، phpMyAdmin یا خط فرمان اجرا کنید:

```bash
mysql -u root -p berimbasket_bot_generator_promoter < database/sql_tests/quran_bots_status_queries.sql
```

### روش 3️⃣: استفاده از PowerShell Script

اسکریپت PowerShell در [`scripts/check_webhook_status.ps1`](scripts/check_webhook_status.ps1) قرار دارد:

```powershell
.\scripts\check_webhook_status.ps1
```

---

## 3. بررسی Webhook با API تلگرام

برای بررسی مستقیم webhook از API تلگرام، می‌توانید از این دستور استفاده کنید:

```bash
# جای TOKEN را با توکن ربات جایگزین کنید
curl -X GET "https://api.telegram.org/botTOKEN/getWebhookInfo"
```

**خروجی نمونه:**
```json
{
  "ok": true,
  "result": {
    "url": "https://bots.pardisania.ir/api/webhook-quran-word?language=ru&bot_mother_id=1",
    "has_custom_certificate": false,
    "pending_update_count": 0,
    "max_connections": 40,
    "ip_address": "185.xxx.xxx.xxx"
  }
}
```

**برای تنظیم Webhook:**
```bash
curl -X POST "https://api.telegram.org/botTOKEN/setWebhook?url=https://bots.pardisania.ir/api/webhook-quran-word?language=ru&bot_mother_id=1"
```

**برای حذف Webhook:**
```bash
curl -X POST "https://api.telegram.org/botTOKEN/deleteWebhook"
```

---

## 4. آمار کاربران

### کاربران ثبت‌نام شده (جدول `bot_users`)

این جدول کاربرانی را که در ربات ثبت‌نام کرده‌اند ذخیره می‌کند:

```sql
-- تعداد کاربران هر ربات
SELECT 
    bu.bot_id,
    b.telegram_bot_name,
    b.bale_bot_name,
    b.language_code,
    bu.origin,
    COUNT(*) AS total_users,
    SUM(CASE WHEN bu.status = 'active' THEN 1 ELSE 0 END) AS active_users
FROM bot_users bu
JOIN bots b ON b.id = bu.bot_id
WHERE b.bot_mother_id = 1
GROUP BY bu.bot_id, bu.origin;
```

### کاربران فعال (از `bot_logs`)

این کوئری کاربرانی را نشان می‌دهد که واقعاً از ربات استفاده کرده‌اند:

```sql
-- کاربران فعال هر زبان در 30 روز اخیر
SELECT 
    bl.language,
    bl.type AS platform,
    COUNT(DISTINCT bl.chat_id) AS unique_users,
    COUNT(*) AS total_requests
FROM bot_logs bl
WHERE bl.webhook_endpoint_uri = 'webhook-quran-word'
  AND bl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY bl.language, bl.type
ORDER BY unique_users DESC;
```

---

## 5. مشاهده لاگ‌ها

### لاگ‌های دیتابیس (جدول `bot_logs`)

تمام درخواست‌های کاربران در جدول `bot_logs` ذخیره می‌شود:

```sql
-- آخرین 50 درخواست
SELECT 
    id, language, type, text, chat_id, created_at
FROM bot_logs
WHERE webhook_endpoint_uri = 'webhook-quran-word'
ORDER BY created_at DESC
LIMIT 50;
```

### لاگ‌های فایل (Laravel Logs)

لاگ‌های خطا و اطلاعات در مسیرهای زیر ذخیره می‌شوند:

| مسیر | توضیحات |
|------|---------|
| [`storage/logs/`](storage/logs/) | لاگ‌های اصلی Laravel |
| [`logs/`](logs/) | لاگ‌های خطاهای Telegram API |

لاگ‌های خطای Telegram:
```bash
# مشاهده آخرین خطاهای Telegram
cat logs/TelegramErrorLogger-*.txt
```

---

## 6. ارسال پیام به کاربران

### روش 1️⃣: ارسال از طریق Webhook (مناسب برای ادمین)

می‌توانید از طریق route زیر پیام ارسال کنید:

```
POST /api/webhook-quran-message-to-all
```

**پارامترها:**

| پارامتر | توضیحات | مثال |
|---------|---------|------|
| `origin` | پلتفرم | `telegram` یا `bale` |
| `token` | توکن ربات مادر | `6112415974:...` |
| `language` | کد زبان | `ru`, `en`, `fa`, ... |
| `text` | متن پیام | `سلام کاربر گرامی` |
| `to_admins` | فقط برای ادمین‌ها؟ | `false` یا `true` |

**مثال با curl:**
```bash
curl -X POST "https://bots.pardisania.ir/api/webhook-quran-message-to-all" \
  -H "Content-Type: application/json" \
  -d '{
    "origin": "telegram",
    "language": "ru",
    "token": "BOT_MOTHER_TOKEN_TELEGRAM",
    "text": "/admin سلام کاربران روسی زبان! این یک پیام آزمایشی است",
    "to_admins": "false"
  }'
```

### روش 2️⃣: استفاده از BotMessageBroadcastService (برنامه‌نویسی)

سرویس [`BotMessageBroadcastService`](app/Services/BotMessageBroadcastService.php) دو متد اصلی دارد:

**ارسال بر اساس زبان:**
```php
use App\Services\BotMessageBroadcastService;

$service = app(BotMessageBroadcastService::class);

$result = $service->sendMessageToUsersByLanguage(
    language: 'ru',         // کد زبان
    message: 'متن پیام شما', 
    botType: 'telegram',    // 'telegram' یا 'bale'
    botMotherId: 1,         // bot_mother_id
    endpointUri: 'webhook-quran-word' // (اختیاری)
);

echo "موفق: {$result['success_count']}, خطا: {$result['error_count']}";
```

**ارسال بر اساس bot_id:**
```php
$result = $service->sendMessageToBotUsers(
    botId: 42,          // شناسه ربات در جدول bots
    message: 'متن پیام',
    botType: 'telegram'
);
```

### روش 3️⃣: ارسال مستقیم با API (ادمین)

از طریق ربات ادمین (بله) می‌توانید پیام ارسال کنید:
1. به ربات ادمین بروید
2. دستور `/admin` را بزنید
3. متن پیام را بنویسید
4. ربات به صورت خودکار به همه کاربران ارسال می‌کند

**نکته مهم:** برای ارسال پیام به کاربران یک زبان خاص، از دستور زیر در ربات ادمین استفاده کنید:

```
/admin سلام کاربران روسی زبان!
```

و مطمئن شوید که language درخواست به `ru` تنظیم شده است.

---

## 7. ساختار دیتابیس

### جدول `bots`
| فیلد | نوع | توضیحات |
|------|-----|---------|
| `id` | bigint | شناسه یکتا |
| `telegram_bot_name` | string | نام کاربری ربات در تلگرام |
| `telegram_bot_token` | string | توکن ربات تلگرام |
| `telegram_bot_status` | enum | Active/DeActive |
| `telegram_webhook_is_set` | boolean | وضعیت webhook |
| `bale_bot_name` | string | نام کاربری در بله |
| `bale_bot_token` | string | توکن ربات بله |
| `bale_bot_status` | enum | Active/DeActive |
| `bale_webhook_is_set` | boolean | وضعیت webhook بله |
| `bot_mother_id` | bigint | شناسه ربات مادر |
| `endpoint_id` | string | شناسه endpoint |
| `language_code` | string | کد زبان (مثلاً `ru`, `fa`) |
| `type` | enum | telegram/bale |

### جدول `bot_users`
| فیلد | نوع | توضیحات |
|------|-----|---------|
| `id` | bigint | شناسه یکتا |
| `chat_id` | bigint | شناسه کاربر در پیام‌رسان |
| `bot_id` | bigint | شناسه ربات |
| `status` | enum | suspend/active |
| `origin` | enum | telegram/bale |
| `alias_name` | string | نام مستعار کاربر |

### جدول `bot_logs`
| فیلد | نوع | توضیحات |
|------|-----|---------|
| `id` | bigint | شناسه یکتا |
| `webhook_endpoint_uri` | string | آدرس endpoint |
| `bot_mother_id` | bigint | شناسه ربات مادر |
| `language` | string | کد زبان |
| `type` | enum | bale/telegram |
| `text` | text | متن پیام |
| `chat_id` | bigint | شناسه کاربر |
| `bot_id` | bigint | شناسه ربات (nullable) |

---

## 8. Troubleshooting

### ربات کار نمی‌کند؟

1. **بررسی Webhook:**
   ```bash
   curl -X GET "https://api.telegram.org/botTOKEN/getWebhookInfo"
   ```
   اگر `url` خالی بود یا خطا داشت، webhook را مجدداً تنظیم کنید.

2. **بررسی توکن:**
   - توکن‌ها در [`.env`](.env.example) با کلیدهای `QURAN_HEFZ_BOT_TOKEN_TELEGRAM` و `QURAN_HEFZ_BOT_TOKEN_BALE` تعریف شده‌اند
   - همچنین می‌توانند در جدول `bots` در فیلدهای `telegram_bot_token` و `bale_bot_token` باشند

3. **بررسی سرور:**
   - مطمئن شوید سرور شما (`bots.pardisania.ir`) در دسترس است
   - لاگ‌های خطا در [`storage/logs/`](storage/logs/) را بررسی کنید

4. **بررسی لاگ‌های Telegram:**
   ```bash
   cat logs/TelegramErrorLogger-2026-07-12.txt
   ```

---

## 9. دستورات سریع

```bash
# بررسی کامل وضعیت ربات‌ها
php artisan quran:check-bots-status --detail

# کوئری مستقیم دیتابیس
mysql -u root -p berimbasket_bot_generator_promoter -e "
  SELECT telegram_bot_name, telegram_bot_status, telegram_webhook_is_set, 
         bale_bot_name, bale_bot_status, bale_webhook_is_set, language_code
  FROM bots WHERE bot_mother_id = 1;
"

# بررسی یک ربات خاص (Koran_chteniye_bot)
mysql -u root -p berimbasket_bot_generator_promoter -e "
  SELECT * FROM bots 
  WHERE telegram_bot_name = 'Koran_chteniye_bot' 
     OR bale_bot_name = 'Koran_chteniye_bot';
"

# تعداد کاربران ربات روسی
mysql -u root -p berimbasket_bot_generator_promoter -e "
  SELECT COUNT(*) as total_users, 
         SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_users
  FROM bot_users 
  WHERE bot_id = (SELECT id FROM bots WHERE telegram_bot_name = 'Koran_chteniye_bot' LIMIT 1);
"

# آخرین فعالیت ربات روسی
mysql -u root -p berimbasket_bot_generator_promoter -e "
  SELECT COUNT(DISTINCT chat_id) as unique_users, COUNT(*) as requests,
         MAX(created_at) as last_activity
  FROM bot_logs 
  WHERE language = 'ru' 
    AND webhook_endpoint_uri = 'webhook-quran-word'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);
"
```
