# Bot Generator Platform — Project Knowledge Base for Antigravity

> **پروژه**: Bot Generator - پلتفرم ربات‌ساز چندمنظوره  
> **توسعه‌دهنده**: Saber Tabatabaee  
> **وب‌سایت**: https://bots.pardisania.ir  
> **مخزن**: saber13812002/bot-generator-bale-telegram-laravel-10

---

## 1. خلاصه پروژه (Project Summary)

یک **اکوسیستم ربات‌ساز** مبتنی بر Laravel 10 که به کاربران اجازه می‌دهد بدون نوشتن کد، ربات‌های پیام‌رسان بسازند. قلب سیستم **ربات مادر (Bot Mother)** است — رباتی که خودش ربات‌های جدید را ایجاد و تنظیم می‌کند.

### پیام‌رسان‌های پشتیبانی‌شده
- **تلگرام** (Telegram Bot API)
- **بله** (Bale Bot API)
- **گپ** (Gap SDP API)
- **ایتا** (Eitaa Bot API)

### تکنولوژی‌ها
| لایه | تکنولوژی |
|------|----------|
| Backend | **Laravel 10** + PHP 8.1+ |
| Database | **MySQL** + Fulltext Search |
| Admin Panel | **Laravel Nova** |
| Authentication | **Bale Safir OTP** |
| Translation | **OneAPI Translation Service** |
| Weather APIs | OpenWeatherMap, Tomorrow.io |
| Queue | Laravel Queue (sync/database) |
| API Auth | **API Token** (middleware `api.token`) |

---

## 2. معماری پروژه (Architecture)

### ساختار دایرکتوری اصلی

```
app/
├── Broadcasting/          # کانال‌های Broadcasting
├── Builders/              # Builder Pattern classes
├── Console/               # دستورات آرتیزان
├── Exceptions/            # Exception Handlers
├── Helpers/               # 26 فایل Helper (منطق مشترک)
│   ├── BotHelper.php      # [55KB] منطق اصلی ربات مادر و ساخت ربات
│   ├── QuranHelper.php    # [74KB] منطق ربات قرآن
│   ├── HadithHelper.php   # منطق جستجوی حدیث
│   ├── PrayerHelper.php   # منطق ربات نماز قضا
│   ├── FileUploadHelper.php # آپلود فایل چندپلتفرمه
│   ├── LogHelper.php      # لاگینگ
│   ├── StringHelper.php   # ابزارهای متنی
│   └── WebhookEndpointHelper.php # مدیریت وب‌هوک‌ها
├── Http/
│   ├── Controllers/       # 65 کنترلر
│   │   ├── Api/           # REST API Controllers (4 فایل)
│   │   ├── Admin/         # پنل ادمین وب
│   │   ├── BotMotherController.php    # [199KB] ❗ بزرگ‌ترین فایل - ربات مادر
│   │   ├── QuranWordController.php    # [155KB] ربات قرآن
│   │   ├── MissionBotController.php   # [82KB] ربات ماموریت
│   │   ├── TaskApprovalController.php # [68KB] تایید وظایف
│   │   ├── PrayerBotController.php    # [56KB] ربات نماز قضا
│   │   ├── BookLibraryReaderController.php # [44KB] کتابخوان
│   │   ├── BookPixelController.php    # [37KB] Book Pixel
│   │   ├── WeatherController.php      # [34KB] ربات آب‌وهوا
│   │   └── PsychologyTestBotController.php # [34KB] تست روانشناسی
│   ├── Middleware/         # میان‌افزارها
│   ├── Requests/           # Form Requests
│   └── Resources/          # API Resources
├── Interfaces/
│   ├── Repositories/      # Repository Interfaces
│   └── Services/          # Service Interfaces
├── Jobs/                  # 11 Background Job
├── Mail/                  # قالب‌های ایمیل
├── Models/                # 119 مدل Eloquent ❗
├── Modules/               # ماژول‌های مستقل
│   ├── AdminBots/         # ربات مدیریت ربات‌ها
│   ├── BaleOtp/           # سیستم OTP بله
│   ├── BotCreation/       # Workflow ساخت ربات (v2)
│   ├── BotOwner/          # پنل مالک ربات (وب)
│   └── BotRegistration/   # ثبت‌نام ربات
├── Nova/                  # 52 Nova Resource + Actions + Metrics
├── Policies/              # Authorization Policies
├── Providers/             # Service Providers
├── Repositories/          # 19 Repository Implementation
├── Services/              # 62 Service Implementation
└── Notifications/         # اعلان‌ها

routes/
├── api.php              # مسیرهای وب‌هوک ربات‌ها + REST API
├── bot-owner.php        # مسیرهای پنل مالک ربات
├── web.php              # مسیرهای وب
├── channels.php         # Broadcasting channels
└── console.php          # Console routes

database/migrations/     # 176 فایل migration
config/                  # 32 فایل تنظیمات
docs/                    # 33 فایل مستندات
```

### الگوی معماری (Design Patterns)

1. **Repository Pattern**: `Interfaces/Repositories` → `Repositories/*RepositoryImpl`
2. **Service Pattern**: `Interfaces/Services` → `Services/*ServiceImpl`
3. **Module Pattern**: `Modules/` برای بخش‌های مستقل (BotOwner, BaleOtp, BotCreation)
4. **Helper Pattern**: `Helpers/` برای منطق مشترک بین کنترلرها
5. **Nova Resources**: پنل ادمین با Laravel Nova

### جریان داده (Data Flow)

```
Messenger (Telegram/Bale/Gap/Eitaa)
    ↓ Webhook POST
routes/api.php → /webhook-{bot-type}
    ↓
Controller (e.g., QuranWordController)
    ↓
Helper (e.g., QuranHelper) + Service
    ↓
Repository → Model → Database
    ↓
BotHelper::sendMessage() → Messenger API
```

---

## 3. لیست کامل ربات‌ها (Complete Bot Catalog)

### 📖 قرآن و مذهبی (6 ربات)

| # | نام ربات | Webhook Route | Controller | فایل‌های کلیدی |
|---|----------|--------------|------------|---------------|
| 1 | **📖 Quran Bot** | `/webhook-quran-word` | `QuranWordController` (155KB) | `QuranHelper.php` (74KB), `QuranBotUserRankingServiceImpl` |
| 2 | **📜 Hadith Bot** | `/webhook-hadith` | `HadithSearchController` | `HadithHelper.php`, `HadithApiServiceImpl` |
| 3 | **📚 Nahj al-Balagha** | `/webhook-nahj` | `NahjController` | `NahjServiceImpl`, `NahjRepositoryImpl` |
| 4 | **🕌 Prayer Qadha** | `/webhook-prayer-bot` | `PrayerBotController` (56KB) | `PrayerBotServiceImpl` (16KB), `PrayerHelper` |
| 5 | **🍷 Sharabe Beheshti** | (RSS-based) | `SharabeBeheshtiMp3Controller` | `SharabeBeheshtiRssMessageBuilder` |
| 6 | **🕋 Mawkib Finder** | `/webhook-mawkib-finder` | `MawkibFinderController` (23KB) | `MawkibFinderServiceImpl`, `MawkibFinderOtpService` |

### 🌤️ ابزارهای کاربردی (11 ربات)

| # | نام ربات | Webhook Route | Controller | فایل‌های کلیدی |
|---|----------|--------------|------------|---------------|
| 7 | **🌤️ Weather Bot** | `/webhook-weather` | `WeatherController` (34KB) | `WeatherAlertServiceImpl`, `WeatherTomorrowApiServiceImpl` |
| 8 | **🎤 Presenter Bot** | `/webhook-presenter-bot` | `PresenterBotController` (15KB) | Model: `PresenterBot` |
| 9 | **⭐ Rating Bot** | `/webhook-rating-bot` | `RatingBotController` (10KB) | Models: `RatingBot`, `RatingBotResponse` |
| 10 | **🧠 Psychology Test** | `/webhook-psychology-test` | `PsychologyTestBotController` (34KB) | Models: `PsychologyTestBot`, `PsychologyTestQuestion` |
| 11 | **📋 List Bot** | `/webhook-list-bot` | `ListBotController` (16KB) | `ListBotMenuParser`, Model: `ListBotConfig` |
| 12 | **📝 Content Submission** | `/webhook-content-submission` | `ContentSubmissionController` (8KB) | `ContentSubmissionServiceImpl` (14KB) |
| 13 | **📰 RSS Feed Bot** | `/webhook-rss` | `RssPostItemTranslationController` | `RssService`, `RssItemService` |
| 14 | **📖 Blog Bot** | `/webhook-blog` | `BlogController` | `BlogMessengerBroadcastService`, `BlogHelper` |
| 15 | **📖 Get Chat ID** | `/webhook-bot-get-id` | `BotMotherController::getIdMother` | — |
| 16 | **🏛️ MP Contact** | `/webhook-mp-contact` | `MpContactBotController` | `MpContactBotServiceImpl` |
| 17 | **📤 Channel Poster** | `/webhook-channel-poster` | `ChannelPosterBotController` | `ChannelPosterBotServiceImpl` |

### 📚 کتاب و رسانه (6 ربات)

| # | نام ربات | Webhook Route | Controller | فایل‌های کلیدی |
|---|----------|--------------|------------|---------------|
| 18 | **📚 Smart Book Library** | `/webhook-book-library` | `BookLibraryController` (28KB) | `BookLibraryServiceImpl`, `BookLibraryDeliveryServiceImpl` |
| 19 | **📖 Book Library Reader** | `/webhook-book-library-reader` | `BookLibraryReaderController` (44KB) | Library models |
| 20 | **📖 Book Pixel** | `/webhook-book-pixel` | `BookPixelController` (37KB) | `BookPixelServiceImpl`, `BookPixelApprovalController` |
| 21 | **📖 Poem Bot** | `/api/webhook-poem-bot` | `PoemBotController` (21KB) | `PoemBotServiceImpl`, Poem models |
| 22 | **📖 Audio Book** | (API endpoint) | `AudioBookController` | `AudioBookService` |
| 23 | **🎵 Song Sara** | (Data import) | `SongSaraPostController` | `SongSaraService`, Songsara models |

### 👔 کسب‌وکار و مدیریت (8 ربات)

| # | نام ربات | Webhook Route | Controller | فایل‌های کلیدی |
|---|----------|--------------|------------|---------------|
| 24 | **👔 Personnel Registration** | `/webhook-personnel-registration` | `PersonnelRegistrationController` (17KB) | Models: `Personnel`, `Tenant` |
| 25 | **👨‍💼 Personnel Admin** | `/webhook-personnel-admin` | `PersonnelAdminBotController` (15KB) | `PersonnelMessageQueue` |
| 26 | **🎯 Mission Bot** | `/webhook-mission-bot` | `MissionBotController` (82KB) | `MissionServiceImpl`, Mission models |
| 27 | **🎬 Mission Media** | `/webhook-mission-media` | `MissionMediaBotController` (21KB) | `SendMissionMediaJob` |
| 28 | **✅ Task Approval** | `/webhook-task-approval` | `TaskApprovalController` (68KB) | Models: `Task`, `MissionPersonnel` |
| 29 | **🤖 Admin Bots** | `/webhook-admin-bots` | `AdminBotsController` (Module) | Module: `Modules/AdminBots/` |
| 30 | **📢 Admin Daily Channel** | (Scheduled) | — | `DailyChannelContentService`, `AdminDailyChannelConfig` |
| 31 | **📺 Admin Channel Media Queue** | (Scheduled) | — | `AdminChannelMediaQueueConfig`, `MediaQueue` models |

### 🛠️ پیشرفته (5 ربات)

| # | نام ربات | Webhook Route | Controller | فایل‌های کلیدی |
|---|----------|--------------|------------|---------------|
| 32 | **🌐 Social Bot** | (Chrome Extension) | `SocialPublishController` | `SocialTools` |
| 33 | **🤖 Bot Kids** | — | `BotKidController` | Model: `BotKid` |
| 34 | **📢 Admin Bot** | `/webhook-bot-mother` | `BotMotherController` (199KB) | `BotHelper`, `BotMotherStateHelper` |
| 35 | **✅ RSS Admin Bot** | `/webhook-rss-admin` | `RssAdminBotController` (9KB) | `RssFeedRegistrationService`, `RssAdminStateHelper` |
| 36 | **📱 Social Publish** | `/chrome_extension_resend` | `SocialPublishController` | Model: `SocialPublish` |
| 37 | **🌱 Growth Companion** | `/webhook-growth-companion` | `GrowthCompanionController` | `GrowthCompanionServiceImpl`, `GrowthQuestionSelector` |

---

## 4. سیستم ربات مادر (Bot Mother System)

### مفهوم
ربات مادر (`BotMotherController`) مرکز کنترل کل پلتفرم است:
1. کاربر `/start` را ارسال می‌کند
2. نوع ربات را از لیست انواع انتخاب می‌کند
3. توکن BotFather را وارد می‌کند
4. ربات مادر وب‌هوک را تنظیم و ربات جدید را فعال می‌کند

### دو روش ساخت ربات
1. **از طریق ربات مادر** (Chat-based) → `BotMotherController`
2. **از طریق پنل وب** → `Modules/BotOwner/` + `Modules/BotCreation/`

### مدل WebhookEndpoint
هر نوع ربات یک رکورد `WebhookEndpoint` دارد با:
- `endpoint_id` (string unique)
- `name`, `route`, `description`
- `requires_bot_mother_id`, `requires_token`, `requires_language`
- `features` (JSON array)
- `wizard_steps` (JSON — مراحل ویزارد ساخت)
- `sample_telegram_link`, `sample_bale_link`

---

## 5. مدل‌های اصلی دیتابیس (Key Models)

### مدل‌های هسته
| مدل | توضیح |
|-----|--------|
| `Bot` | ربات ساخته‌شده (token, endpoint_id, language_code, bot_owner_id) |
| `BotUsers` | کاربران هر ربات |
| `BotLog` | لاگ فعالیت ربات‌ها |
| `BotKid` | ربات‌های فرزند |
| `WebhookEndpoint` | تعریف انواع ربات و مسیرهای وب‌هوک |
| `Language` | زبان‌های پشتیبانی‌شده (18 زبان) |
| `Messenger` | تنظیمات چندپیام‌رسان (Telegram/Bale/Eitaa) |

### مدل‌های قرآنی
`QuranWord`, `QuranSurah`, `QuranAyat`, `QuranTranslation`, `QuranScanPage`, `QuranSearchSuggestion`

### مدل‌های ماموریت
`Mission`, `MissionContent`, `MissionPersonnel`, `Task`, `Personnel`, `Tenant`

### مدل‌های کتاب
`Book`, `BookPage`, `BookPageScan`, `BookPageVoice`, `BookDraft`, `BookScanMission`, `BookPublishingQueue`, `BookPublishingChannel`, `BookModerationGroup`, `BookUserScore`

### مدل‌های کتابخانه
`LibraryBook`, `LibraryBookMedia`, `LibraryGenre`, `LibraryBotConfig`, `LibraryPlanRequest`, `LibraryUserBook`, `LibraryUserSubscription`

### مدل‌های محتوا
`Content`, `ContentItem`, `ContentCategory`, `ContentAsset`, `ContentSubmissionItem`, `ContentSubmissionBotConfig`, `ContentUserProgress`

### مدل‌های RSS
`RssItem`, `RssPostItem`, `RssPostItemTranslation`, `RssChannel`, `RssChannelOrigin`, `RssBusiness`, `RssFeedWebOrigin`

### مدل‌های شعر
`Poem`, `PoemVersion`, `PoemLine`, `PoemLike`, `PoemSuggestion`, `PoemCollaboration`

---

## 6. REST API Endpoints

### Quran API (`/api/v1/quran/`)
- `GET /languages` — لیست زبان‌ها
- `GET /translations` — لیست ترجمه‌ها
- `GET /surahs` — لیست سوره‌ها
- `GET /surahs/{sura}/ayahs/{ayah}` — دریافت آیه
- `GET /search` — جستجو در قرآن
- `GET /juz`, `GET /juz/{juz}` — جزءها
- `GET /audio/{sura}/{ayah}` — صوت آیه
- `GET /feed` — فید محتوا
- Protected: `user/settings`, `user/report`, `user/referral-stats`

### Mission API (`/api/v1/missions/`) — نیاز به `api.token`
- `POST /` — ایجاد ماموریت
- `GET /` — لیست ماموریت‌ها
- `GET /{id}` — جزئیات ماموریت
- `POST /{id}/assign` — تخصیص
- `POST /{id}/submit` — ارسال نتیجه

### Metadata API (`/api/v1/metadata`)
- `GET /metadata` — اطلاعات عمومی سیستم

---

## 7. سیستم‌های جانبی مهم

### سیستم Pro (اشتراک ویژه)
- Model: `ProUser`, `ProPurchaseRequest`
- Service: `ProServiceImpl`, `ProPurchaseNotificationService`
- کاربران رایگان ← محدودیت ساخت ربات، کاربران Pro ← نامحدود

### سیستم OTP بله (Bale Safir)
- Module: `Modules/BaleOtp/`
- Config: `config/bale-otp.php`
- استفاده: احراز هویت مالکان ربات + Mawkib Finder

### سیستم ایمیل گزارش نماز
- Service: `SendPrayerReportEmailJob`, `EmailReportEnhancementService`
- Mailtrap Integration: `MailtrapEmailServiceImpl`
- Threshold: `EmailThresholdService`

### سیستم ترجمه
- Service: `TranslationService` (22KB), `OneApiTranslationService`
- قابلیت ترجمه خودکار محتوای RSS

### سیستم Broadcast
- Service: `AdminBroadcastService` (24KB), `BotMessageBroadcastService`
- Job: `ContentBroadcastJob`
- Model: `BroadcastLog`

### پنل مالک ربات (Bot Owner Panel)
- Module: `Modules/BotOwner/`
- Routes: `routes/bot-owner.php`
- ویژگی‌ها: داشبورد, مدیریت ربات, تنظیمات, آپلود محتوا, مدیریت ادمین‌ها
- Auth: OTP-based login via Bale Safir

### سیستم Idea/Ticket
- Controller: `IdeaController`
- Models: `Idea`, `IdeaMessage`
- ویژگی: ثبت ایده/تیکت با OTP و ایمیل

---

## 8. Environment Variables کلیدی

```env
# ربات مادر
BOT_MOTHER_TOKEN_BALE=...
BOT_MOTHER_TOKEN_TELEGRAM=...
BOT_MOTHER_TOKEN_GAP=...
SUPER_ADMIN_CHAT_ID_BALE=...
SUPER_ADMIN_CHAT_ID_TELEGRAM=...

# ربات‌های قرآنی
QURAN_HEFZ_BOT_TOKEN_BALE=...
QURAN_HEFZ_BOT_TOKEN_TELEGRAM=...

# ربات‌های ماموریت
MISSION_BOT_TOKEN_TELEGRAM=...
MISSION_BOT_TOKEN_BALE=...
MISSION_APPROVAL_GROUP_CHAT_ID=...

# ربات‌های پرسنلی
PERSONNEL_REGISTRATION_BOT_TOKEN_TELEGRAM=...
PERSONNEL_ADMIN_BOT_TOKEN_TELEGRAM=...

# Weather APIs
OPENWEATHER_API_TOKEN=...
TOMORROW_API_TOKEN=...

# Bale Safir OTP
BALE_SAFIR_CLIENT_ID=...
BALE_SAFIR_CLIENT_SECRET=...

# ترجمه
ONE_API_API_TOKEN=...
```

---

## 9. Background Jobs

| Job | وظیفه |
|-----|--------|
| `CheckWeatherAlertsJob` | بررسی هشدارهای آب‌وهوا |
| `ContentBroadcastJob` | ارسال محتوا به کاربران |
| `PreUploadQuranFilesJob` | آپلود پیش‌نیاز فایل‌های قرآنی |
| `RssPostItemTranslationJob` | ترجمه آیتم‌های RSS |
| `RssPostItemTranslationToMessengerJob` | ارسال ترجمه به پیام‌رسان |
| `SendMissionMediaJob` | ارسال مدیای ماموریت |
| `SendPrayerReportEmailJob` | ارسال گزارش نماز به ایمیل |
| `PublishBookPageJob` | انتشار صفحه کتاب |

---

## 10. نکات مهم برای توسعه

### قراردادهای نام‌گذاری
- Controllers: `{BotName}Controller.php`
- Services: `{Feature}ServiceImpl.php`
- Repositories: `{Feature}RepositoryImpl.php`
- Helpers: `{Feature}Helper.php`
- Models: PascalCase, بدون پسوند

### اضافه کردن ربات جدید
1. یک `WebhookEndpoint` در Seeder/Nova اضافه کن
2. Controller جدید بساز (`/webhook-{name}`)
3. Route در `routes/api.php` اضافه کن
4. Models مورد نیاز را بساز
5. Migration‌ها را اجرا کن
6. مستندات: `docs/BOT_CREATION_GUIDE.md`

### فایل‌های بزرگ (نیاز به Refactor)
- `BotMotherController.php` — **199KB** ❗
- `QuranWordController.php` — **155KB** ❗
- `MissionBotController.php` — **82KB**
- `QuranHelper.php` — **74KB**
- `TaskApprovalController.php` — **68KB**

### تست
- PHPUnit در `tests/`
- تست‌های API: `test_quran_api.ps1`, `test_quran_api.sh`
- مستندات تست: `TEST_SCENARIO.md`, `TEST_COMPLETE_GUIDE.md`

---

## 11. مستندات موجود

| فایل | محتوا |
|------|--------|
| `docs/BOT_CREATION_GUIDE.md` | راهنمای ساخت ربات جدید |
| `docs/BOT_TYPES_GUIDE.md` | لیست انواع ربات و endpoint‌ها |
| `docs/BOTS_COMPLETE_GUIDE.md` | راهنمای کامل انواع ربات |
| `docs/API_README.md` | مستندات API |
| `docs/GETTING-STARTED.md` | راهنمای شروع توسعه |
| `docs/PRAYER_BOT_QUICK_START.md` | شروع سریع ربات نماز |
| `docs/QURAN_BOTS_MANAGEMENT_GUIDE.md` | مدیریت ربات‌های قرآنی |
| `swagger.yaml` | مستندات OpenAPI |
| `CHANGELOG.md` | تاریخچه تغییرات |
