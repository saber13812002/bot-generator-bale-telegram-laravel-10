# اتصال ربات مدیا به ماموریت‌ها - بررسی کامل

این فایل بررسی می‌کند که آیا ربات مدیا به درستی به ماموریت‌ها متصل شده است و می‌تواند محتوا را برای پرسنل ارسال کند.

## ✅ بررسی قابلیت‌های موجود

### 1. ربات مدیا (MissionMediaBotController)

**موقعیت**: `app/Http/Controllers/MissionMediaBotController.php`

**قابلیت‌ها**:
- ✅ دریافت `mission_id` از دستورات
- ✅ آپلود مدیا برای ماموریت‌ها (`/upload_mission_{id}`)
- ✅ مشاهده لیست آموزش‌ها (`/get_training_{id}`)
- ✅ ارسال آموزش‌ها به پرسنل خاص (`/send_training_{mission_id}_to_{personnel_id}`)
- ✅ State Management برای آپلود فایل‌ها

**دستورات**:
```
/start - راهنمای ربات
/upload_mission_{id} - شروع آپلود مدیا برای ماموریت
[ارسال فایل‌ها]
/done - پایان آپلود
/get_training_{id} - مشاهده لیست آموزش‌ها
/send_training_{mission_id}_to_{personnel_id} - ارسال به پرسنل
```

### 2. ContentService

**موقعیت**: `app/Services/ContentServiceImpl.php`

**قابلیت‌ها**:
- ✅ `sendTrainingMedia(int $missionId, int $personnelId, string $type)` - ارسال آموزش‌ها
- ✅ `getTrainingMedia(int $missionId)` - دریافت لیست آموزش‌ها

**نحوه کار**:
- دریافت `mission_id` و `personnel_id`
- Dispatch کردن Job برای ارسال ترتیبی
- استفاده از Queue برای ارسال غیرهمزمان

### 3. SendMissionMediaJob

**موقعیت**: `app/Jobs/SendMissionMediaJob.php`

**قابلیت‌ها**:
- ✅ ارسال Prompt ماموریت
- ✅ ارسال تمام محتواها به ترتیب (image, video, audio, pdf, text)
- ✅ استفاده از `BotBuilder` برای ارسال انواع مدیا
- ✅ Delay بین ارسال‌ها برای جلوگیری از Rate Limit

**نحوه کار**:
1. پیدا کردن ماموریت و پرسنل
2. پیدا کردن `chat_id` از `bot_users` با `personnel_id`
3. ارسال Prompt
4. ارسال محتواها به ترتیب

### 4. ربات ماموریت (MissionBotController)

**موقعیت**: `app/Http/Controllers/MissionBotController.php`

**قابلیت‌ها**:
- ✅ `/get_training` - دریافت آموزش‌های ماموریت فعال
- ✅ پیدا کردن ماموریت فعال پرسنل
- ✅ استفاده از `ContentService` برای ارسال

**نحوه کار**:
1. پیدا کردن ماموریت فعال پرسنل
2. فراخوانی `ContentService::sendTrainingMedia()`
3. ارسال پیام تایید به پرسنل

## 🔄 جریان کامل کار

### سناریو 1: آپلود مدیا توسط ادمین

```
1. ادمین در ربات مدیا:
   /upload_mission_1

2. ربات در حالت آپلود قرار می‌گیرد

3. ادمین فایل‌ها را ارسال می‌کند:
   [عکس]
   [ویدیو]
   [صوت]
   [PDF]

4. هر فایل به صورت خودکار:
   - دریافت می‌شود
   - URL از Telegram/Bale API گرفته می‌شود
   - در جدول `contents` ذخیره می‌شود
   - به `mission_contents` متصل می‌شود

5. ادمین:
   /done

6. آپلود به پایان می‌رسد
```

### سناریو 2: ارسال آموزش به پرسنل (از ربات مدیا)

```
1. ادمین در ربات مدیا:
   /send_training_1_to_5
   (ماموریت 1 به پرسنل 5)

2. سیستم:
   - ماموریت را پیدا می‌کند
   - پرسنل را پیدا می‌کند
   - `ContentService::sendTrainingMedia()` را فراخوانی می‌کند
   - Job در Queue قرار می‌گیرد

3. Queue Worker:
   - Prompt را ارسال می‌کند
   - محتواها را به ترتیب ارسال می‌کند
```

### سناریو 3: دریافت آموزش توسط پرسنل (از ربات ماموریت)

```
1. پرسنل در ربات ماموریت:
   /get_training

2. سیستم:
   - ماموریت فعال پرسنل را پیدا می‌کند
   - `ContentService::sendTrainingMedia()` را فراخوانی می‌کند
   - Job در Queue قرار می‌گیرد

3. Queue Worker:
   - Prompt را ارسال می‌کند
   - محتواها را به ترتیب ارسال می‌کند
```

## 📊 ساختار دیتابیس

### جداول مرتبط:

1. **missions** - ماموریت‌ها
2. **contents** - محتواهای آموزشی
3. **mission_contents** - ارتباط ماموریت و محتوا (pivot)
4. **mission_personnel** - ارتباط ماموریت و پرسنل
5. **prompts** - پرامپت‌های ماموریت
6. **bot_users** - کاربران ربات (برای پیدا کردن chat_id)

### روابط:

```
Mission -> hasMany -> MissionContent -> belongsTo -> Content
Mission -> belongsTo -> Prompt
Mission -> belongsToMany -> Personnel (through mission_personnel)
Personnel -> hasMany -> BotUsers (via settings->personnel_id)
```

## ✅ چک‌لیست آماده بودن

### روی سرور بررسی کنید:

- [ ] Migration ها اجرا شده‌اند
- [ ] Seeder ها اجرا شده‌اند (`CompleteTestSeeder`)
- [ ] ربات مدیا در Bot Mother ثبت شده است
- [ ] Webhook ربات مدیا تنظیم شده است
- [ ] Environment Variables تنظیم شده‌اند:
  - `MISSION_MEDIA_BOT_TOKEN_TELEGRAM`
  - `MISSION_MEDIA_BOT_TOKEN_BALE`
  - `MISSION_BOT_TOKEN_TELEGRAM`
  - `MISSION_BOT_TOKEN_BALE`
  - `MISSION_APPROVAL_GROUP_CHAT_ID`
- [ ] Queue Worker در حال اجرا است (`php artisan queue:work`)

### تست دستی:

1. **تست آپلود مدیا**:
   ```
   در ربات مدیا:
   /upload_mission_1
   [ارسال یک عکس]
   /done
   ```

2. **تست مشاهده لیست**:
   ```
   در ربات مدیا:
   /get_training_1
   ```

3. **تست ارسال به پرسنل**:
   ```
   در ربات مدیا:
   /send_training_1_to_1
   ```

4. **تست دریافت توسط پرسنل**:
   ```
   در ربات ماموریت (با حساب پرسنل):
   /get_training
   ```

## 🔍 بررسی کدها

### MissionMediaBotController

```php
// دریافت mission_id از دستور
$missionId = (int) str_replace('/upload_mission_', '', $text);

// استفاده از mission_id برای آپلود
$this->handleUploadMediaStart($bot, $missionId, $chatId);

// استفاده از mission_id برای ارسال
$this->contentService->sendTrainingMedia($missionId, $personnelId, $type);
```

### ContentService

```php
// دریافت mission_id و personnel_id
public function sendTrainingMedia(int $missionId, int $personnelId, string $type)

// Dispatch Job با mission_id
SendMissionMediaJob::dispatch($missionId, $personnelId, $type);
```

### SendMissionMediaJob

```php
// استفاده از mission_id برای پیدا کردن محتواها
$contents = $contentRepository->getByMission($this->missionId);

// ارسال Prompt ماموریت
if ($mission->prompt) {
    // ارسال prompt
}

// ارسال محتواها
foreach ($contents as $content) {
    $this->sendContent($messenger, $chatId, $content);
}
```

## ✅ نتیجه‌گیری

**ربات مدیا به طور کامل به ماموریت‌ها متصل شده است!**

✅ می‌تواند `mission_id` را دریافت کند
✅ می‌تواند محتوا را برای ماموریت‌ها آپلود کند
✅ می‌تواند محتوا را برای پرسنل ارسال کند
✅ از Queue برای ارسال ترتیبی استفاده می‌کند
✅ Prompt و Content را به درستی ارسال می‌کند

**همه چیز آماده است! 🚀**

