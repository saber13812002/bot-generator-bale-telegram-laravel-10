# 📚 راهنمای انواع ربات‌ها در سیستم Bot Mother

## محل تعریف انواع ربات‌ها

انواع ربات‌های قابل ساخت با Bot Mother در **جدول `webhook_endpoints`** تعریف می‌شوند.

---

## 🗂️ ساختار جدول webhook_endpoints

```sql
CREATE TABLE webhook_endpoints (
    id BIGINT PRIMARY KEY,
    endpoint_id VARCHAR UNIQUE,           -- شناسه یکتا (مثل: quran-bot)
    name VARCHAR,                         -- نام نمایشی (مثل: Quran Bot)
    route VARCHAR,                        -- مسیر API (مثل: webhook-quran-word)
    description TEXT,                     -- توضیحات
    requires_bot_mother_id BOOLEAN,       -- نیاز به bot_mother_id
    requires_token BOOLEAN,               -- نیاز به token
    requires_language BOOLEAN,            -- نیاز به language
    supports_multiple_languages BOOLEAN,  -- پشتیبانی از چند زبان
    is_active BOOLEAN,                    -- فعال/غیرفعال
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Migration:** [`database/migrations/2026_01_03_144454_create_webhook_endpoints_table.php`](../database/migrations/2026_01_03_144454_create_webhook_endpoints_table.php)

---

## 📋 لیست کامل انواع ربات‌های موجود

### 1. 📖 Quran Bot (ربات قرآن)
```json
{
  "endpoint_id": "quran-bot",
  "name": "Quran Bot",
  "route": "webhook-quran-word",
  "description": "قرآن کریم با امکانات جستجو، حفظ و ترجمه",
  "requires_bot_mother_id": true,
  "requires_language": true,
  "supports_multiple_languages": true
}
```
**Controller:** `QuranWordController.php`  
**Features:** مطالعه آیه به آیه، جستجو، حفظ، ترجمه به 15 زبان

---

### 2. 🌤️ Weather Bot (ربات هواشناسی)
```json
{
  "endpoint_id": "weather-bot",
  "name": "Weather Bot",
  "route": "webhook-weather",
  "description": "پیش‌بینی آب و هوا با هشدارهای خودکار"
}
```
**Controller:** `WeatherController.php`  
**Features:** پیش‌بینی 16 ساعته، هشدار باد و دما

---

### 3. 📰 Blog Bot (ربات وبلاگ)
```json
{
  "endpoint_id": "blog-bot",
  "name": "Blog Bot",
  "route": "webhook-blog",
  "description": "اتصال به سیستم مدیریت محتوا"
}
```
**Controller:** `BlogController.php`

---

### 4. 📚 Hadith Bot (ربات حدیث)
```json
{
  "endpoint_id": "hadith-bot",
  "name": "Hadith Bot",
  "route": "webhook-hadith",
  "description": "جستجو در کتب حدیث شیعه"
}
```
**Controller:** `HadithSearchController.php`  
**Features:** جستجوی پیشرفته، نمایش سلسله سند

---

### 5. 📜 Nahj Bot (ربات نهج البلاغه)
```json
{
  "endpoint_id": "nahj-bot",
  "name": "Nahj al-Balagha Bot",
  "route": "webhook-nahj",
  "description": "جستجو در نهج البلاغه"
}
```
**Controller:** `NahjController.php`

---

### 6. 👔 Personnel Registration Bot
```json
{
  "endpoint_id": "personnel-registration",
  "name": "Personnel Registration Bot",
  "route": "webhook-personnel-registration",
  "description": "ثبت‌نام پرسنل"
}
```
**Controller:** `PersonnelRegistrationController.php`

---

### 7. 👨‍💼 Personnel Admin Bot
```json
{
  "endpoint_id": "personnel-admin",
  "name": "Personnel Admin Bot",
  "route": "webhook-personnel-admin",
  "description": "مدیریت پرسنل"
}
```
**Controller:** `PersonnelAdminBotController.php`

---

### 8. 📋 Mission Bot
```json
{
  "endpoint_id": "mission-bot",
  "name": "Mission Bot",
  "route": "webhook-mission-bot",
  "description": "مدیریت ماموریت‌ها"
}
```
**Controller:** `MissionBotController.php`

---

### 9. 🎬 Mission Media Bot
```json
{
  "endpoint_id": "mission-media",
  "name": "Mission Media Bot",
  "route": "webhook-mission-media",
  "description": "مدیریت رسانه ماموریت‌ها"
}
```
**Controller:** `MissionMediaBotController.php`

---

### 10. ✅ Task Approval Bot
```json
{
  "endpoint_id": "task-approval",
  "name": "Task Approval Bot",
  "route": "webhook-task-approval",
  "description": "تایید وظایف"
}
```
**Controller:** `TaskApprovalController.php`

---

### 11. 🎤 Presenter Bot
```json
{
  "endpoint_id": "presenter-bot",
  "name": "Presenter Bot",
  "route": "webhook-presenter-bot",
  "description": "ارائه محتوا به صورت خودکار"
}
```
**Controller:** `PresenterBotController.php`

---

### 12. 🧠 Psychology Test Bot
```json
{
  "endpoint_id": "psychology-test",
  "name": "Psychology Test Bot",
  "route": "webhook-psychology-test",
  "description": "تست‌های روانشناسی (MBTI و...)"
}
```
**Controller:** `PsychologyTestBotController.php`

---

### 13. 🕌 Prayer Qadha Bot (ربات نماز قضا) ⭐ جدید
```json
{
  "endpoint_id": "prayer-bot",
  "name": "Prayer Qadha Bot",
  "route": "webhook-prayer-bot",
  "description": "ثبت و پیگیری نماز قضا",
  "requires_bot_mother_id": true,
  "supports_multiple_languages": true
}
```
**Controller:** `PrayerBotController.php`  
**Features:** ثبت رکعات، تشخیص هوشمند، گزارش ایمیلی  
**Docs:** [`docs/features/PRAYER_QADHA_BOT.md`](./features/PRAYER_QADHA_BOT.md)

---

### 14. 🏛️ MP Contact (ارتباط با نماینده مجلس)
```json
{
  "endpoint_id": "webhook-mp-contact",
  "name": "ارتباط با نماینده مجلس",
  "route": "api/webhook-mp-contact",
  "description": "تیکتینگ مردمی و نظرسنجی داخل چت",
  "requires_bot_mother_id": true,
  "requires_token": true
}
```
**Controller:** `MpContactBotController.php`  
**Docs:** [`docs/features/MP_CONTACT_BOT.md`](./features/MP_CONTACT_BOT.md)

---

### 15. 📤 Channel Poster (ارسال به کانال‌ها)
```json
{
  "endpoint_id": "webhook-channel-poster",
  "name": "ارسال به کانال‌ها",
  "route": "api/webhook-channel-poster",
  "description": "ارسال متن/عکس/صوت/ویدیو از خصوصی بله به کانال",
  "requires_bot_mother_id": true,
  "requires_token": true
}
```
**Controller:** `ChannelPosterBotController.php`  
**Docs:** [`docs/features/CHANNEL_POSTER_BOT.md`](./features/CHANNEL_POSTER_BOT.md)

---

### 16. 🌱 Growth Companion (رشدیار)
```json
{
  "endpoint_id": "webhook-growth-companion",
  "name": "رشدیار",
  "route": "api/webhook-growth-companion",
  "description": "موتور رشد شخصی: تختهٔ روزانه، چک‌باکس موضوعات، بودجهٔ شدت سؤال",
  "requires_bot_mother_id": true,
  "requires_token": true
}
```
**Controller:** `GrowthCompanionController.php`  
**Docs:** [`docs/features/GROWTH_COMPANION.md`](./features/GROWTH_COMPANION.md)

---

## 🔧 نحوه افزودن ربات جدید

### مرحله 1: ایجاد Controller
```php
// app/Http/Controllers/YourBotController.php
class YourBotController extends Controller
{
    public function webhook(Request $request): int
    {
        // منطق ربات
        return 200;
    }
}
```

### مرحله 2: افزودن Route
```php
// routes/api.php
Route::post('/webhook-your-bot', [YourBotController::class, 'webhook']);
```

### مرحله 3: ایجاد Seeder
```php
// database/seeders/YourBotWebhookEndpointSeeder.php
DB::table('webhook_endpoints')->insert([
    'endpoint_id' => 'your-bot',
    'name' => 'Your Bot',
    'route' => 'webhook-your-bot',
    'description' => 'توضیحات ربات شما',
    'requires_bot_mother_id' => true,
    'requires_token' => false,
    'requires_language' => false,
    'supports_multiple_languages' => false,
    'is_active' => true,
]);
```

### مرحله 4: اجرای Seeder
```bash
php artisan db:seed --class=YourBotWebhookEndpointSeeder
```

### مرحله 5: تست
```bash
# از طریق Bot Mother
/start
# انتخاب endpoint جدید از لیست
```

---

## 🔍 Helper کلاس برای مدیریت Endpoints

**کلاس:** [`app/Helpers/WebhookEndpointHelper.php`](../app/Helpers/WebhookEndpointHelper.php)

### متدهای مفید:

```php
// دریافت لیست تمام endpoint ها
WebhookEndpointHelper::getAvailableEndpoints();

// دریافت یک endpoint خاص
WebhookEndpointHelper::getEndpointById('prayer-bot');

// ساخت URL webhook
WebhookEndpointHelper::createWebhookUrl($endpointId, $botItem, $type, $language);

// تولید پیام لیست endpoint ها برای کاربر
WebhookEndpointHelper::getEndpointsListMessage();
```

---

## 📊 نمایش Endpoints در Bot Mother

وقتی کاربر `/start` می‌زند، لیست endpoint ها از جدول خوانده می‌شود:

```php
// BotMotherController.php
private function handleStart(Telegram $bot, string $type, int $botMotherId): void
{
    $message = "🤖 ربات ساز\n\n";
    $message .= WebhookEndpointHelper::getEndpointsListMessage();
    BotHelper::sendMessage($bot, $message);
    
    // ذخیره state
    BotMotherStateHelper::setState($chatId, 
        BotMotherStateHelper::STATE_WAITING_ENDPOINT_SELECTION);
}
```

---

## 🗄️ مشاهده Endpoints فعال

### از طریق Database:
```sql
SELECT endpoint_id, name, route, is_active 
FROM webhook_endpoints 
WHERE is_active = 1
ORDER BY name;
```

### از طریق Laravel Nova:
- مراجعه به `/nova/resources/webhook-endpoints`

### از طریق Tinker:
```bash
php artisan tinker
>>> WebhookEndpoint::where('is_active', true)->get(['endpoint_id', 'name']);
```

---

## 🎯 نکات مهم

1. **Endpoint ID باید یکتا باشد** - استفاده از kebab-case (مثل: `prayer-bot`)

2. **Route باید در `routes/api.php` تعریف شود**

3. **هر endpoint باید یک Controller اختصاصی داشته باشد**

4. **برای غیرفعال کردن endpoint:** `is_active = false` کنید

5. **پارامترهای اضافی در URL:**
   - `origin` - همیشه الزامی (telegram/bale)
   - `bot_mother_id` - اگر `requires_bot_mother_id = true`
   - `language` - اگر `requires_language = true`
   - `token` - اگر `requires_token = true`

---

## 📖 منابع بیشتر

- **کد Bot Mother:** [`app/Http/Controllers/BotMotherController.php`](../app/Http/Controllers/BotMotherController.php)
- **Helper Endpoints:** [`app/Helpers/WebhookEndpointHelper.php`](../app/Helpers/WebhookEndpointHelper.php)
- **Migration Endpoints:** [`database/migrations/2026_01_03_144454_create_webhook_endpoints_table.php`](../database/migrations/2026_01_03_144454_create_webhook_endpoints_table.php)

---

**آخرین به‌روزرسانی:** 1404/10/16
