# 🗺️ PROJECT MASTER MAP — نقشهٔ جامع پروژه (مطالعهٔ کامل ۲۰۲۶-۱۱-۱۰)

> **این سند چیست؟** نتیجهٔ یک بررسی کامل (audit) از کل مخزن، تاریخ‌سازِ Git، محیط Production و تمام زیرسیستم‌ها. هدف: هر agent یا توسعه‌دهنده‌ای که دفعات بعدی وارد مخزن می‌شود، با خواندن این فایل بداند **کدوم شاخه به کجا ربط دارد، Production کجاست، هر پروژه/ماژول چه می‌کند و هر مستند کجاست.**
> **نکتهٔ روش‌شناسی:** همهٔ یافته‌های این سند ۲۰۲۶-۱۱-۱۰ (تاریخ UTC) مستقیماً از `git`، فایل‌ها و کد تأیید شده‌اند (نه حدس). جایی که چیزی تأییدنیست، با «تأییدنشده» برچسب خورده است.
> **سند مکمل اصلی:** [`docs/AGENT_HANDOFF.md`](AGENT_HANDOFF.md) (قوانین و ریکاوری). این فایل **نقشهٔ کلی و گیت** را پوشش می‌دهد.
> **به‌روزرسانی T10 (2026-11-10) — ⏳ Snapshot تاریخی:** در لحظهٔ commit T10، HEAD = `277c977a` (T9) بود و رگرسیون **852 passed**؛ خودِ commit T10 (`4851503c`) بعداً این وضعیت را جایگزین کرد. این خط فقط تاریخچه است. مرجع امضای کانال: [`docs/CHANNEL_POSTER_SIGNATURES.md`](CHANNEL_POSTER_SIGNATURES.md).
> **به‌روزرسانی release-gate (2026-11-11):** HEAD = `4851503c` (T10)؛ دو فایل تست ترنزیشن جدید (`tests/Unit/SignatureStateTransitionTest.php` + `tests/Feature/SignatureStateTransitionFeatureTest.php` — ۱۰ تست / 330 assertion). رگرسیون کامل: **PHP 8.2.12: 864 passed / 2 skipped / 5367 assertions** و **PHP 8.5.5: 862 passed + 2 deprecated / 2 skipped / 5367 assertions**.

---

## ۱. شناسنامهٔ پروژه (Quick Snapshot)

| مورد | مقدار | منبع تأیید |
|---|---|---|
| نام | Bot Generator — پلتفرم ربات‌ساز چندمنظوره | `composer.json`، `README.md` |
| مالک | Saber Tabatabaee | `.agents/AGENTS.md` |
| مخزن GitHub | `saber13812002/bot-generator-bale-telegram-laravel-10` | `git remote -v` |
| دامنهٔ Production | `https://bots.pardisania.ir` | `.env.example` (APP_URL)، `DEPLOY_INSTRUCTIONS.md` |
| فریم‌ورک | **Laravel 10** (PHP `^8.1`؛ Production = 8.2.34، محلی = 8.5+) | `composer.json`، `deploy_commands.sh` |
| پنل ادمین | **Laravel Nova** (fork خصوصی: `saber13812002/laravel-nova` 4.32.16) | `composer.json` |
| دیتابیس | MySQL (cPanel)؛ نمونهٔ DB: `berimbasket_bot_generator_promoter` | `config/database.php`، `.env.example` |
| Queue | **`sync`** — جاب‌ها inline اجرا می‌شوند، worker وجود ندارد | `.env.example`، `docs/PRODUCTION_RUNBOOK.md` |
| Timezone | **UTC** (تهران = UTC+3:30 — همهٔ اسلات‌های UTC هستند) | `config/app.php` |
| زمان‌بندی | cPanel؛ خط اصلی: `* * * * * … artisan schedule:run` | `deploy_commands.sh` |
| پیام‌رسان‌ها | بله، تلگرام، ایطلا، گپ، سoroush (متنی)، روبیکا | [`PostUrlParser::HOSTS`](../app/Services/ContentSync/PostUrlParser.php)، کاتالوگ ربات‌ها |

### حجم فعلی کوب (شمارش مستقیم ۲۰۲۶-۱۱-۱۰ روی `15esfand1404`)

| واحد | تعداد |
|---|---|
| Migration | 204 |
| Controller | 79 (از آن 37 webhook ربات) |
| Model | 154 |
| Service | 102 |
| Console Command | 106 |
| Job | 12 |
| Helper | 26 |
| Nova Resource | 57 |
| entry در `Kernel schedule` | 36 |
| ربات (کاتالوگ رسمی) | 37 — جدول کامل در [`.agents/AGENTS.md`](../.agents/AGENTS.md) §3 |

> ⚠️ اعداد `AGENTS.md` (65 controller/119 model/62 service) قدیمی‌ترند؛ اجدال بالا شمارش زندهٔ این audit است.

---

## ۲. توپولوژی Git — کدوم شاخه به کجا ربط دارد (مهم‌ترین بخش)

### ۲.۱ دو نسل (lineage) کاملاً مستقل در یک مخزن

مخزن **دو خط خونی جدا** دارد که هیچ merge-base با هم ندارند:

```
نسل A (Legacy، ریشه: a188d621 "initial commit of laravel!" — 2023-06)
  ├── main2 (لوکال: 758902b5، 2025-12-31، merge PR #45)
  ├── origin/main2 (c6b0eca9 "plan"، 2026-08-12) ← origin/HEAD (شاخهٔ پیش‌فرض GitHub)
  └── شاخه‌های قدیمی: namaz-ghaza-1, move_channel_admin_to_bots_app,
      parse_mode_HTML, hadith_bot_search_all_books, gap_quran_scan,
      voice_of_islam, nova, command_template_as_app_const,
      send_button_bale_when_admin_has_command_message, fix-callback-query-handler

نسل B (Fresh Start، ریشه: cc34d011 "Fresh start: keep current files only" — 2026-01-03)
  ├── tuning12khordad (da283ace، 2026-03-06) ← 236 commit پشت 15esfand1404 (ancestor است)
  ├── fix/content-pipeline-recovery (60880a6c، 2026-10-18) ← ancestor مستقیم 15esfand1404
  └── 15esfand1404 (e66bd888، 2026-11-07) ★★ شاخهٔ پراکشن و شاخهٔ کاری ★★
```

**توضیح:** ۲۰۲۶-۰۱-۰۳ یک «fresh start» انجام شده: ریشهٔ جدیدی با نام `Fresh start: keep current files only` ساخته شده و فقط فایل‌های آن لحظه (892 فایل) به‌عنوان تاریخ‌ساز تازه باقی مانده‌اند. تاریخ‌ساز قدیمی (نسل A، 7902 commit) جدا باقی مانده و در `main2`/`origin/main2` و شاخه‌های 2023 ذخیره است.

### ۲.۲ جدول کامل شاخه‌ها

| شاخه | commit نوین | تاریخ | نسل | به کجا ربط دارد |
|---|---|---|---|---|
| **`15esfand1404`** (لوکال، جاری) | `4851503c` (T10) | 2026-11-11 | B | **★ Production + شاخهٔ کاری فعال.** ۷ commit جلوتر از origin (T4–T10 push نشده — scope `cp-signature:`). «۱۵ اسفند ۱۴۰۴» = نام تاریخ شروع. |
| `origin/15esfand1404` | `64de7a3a` (T3) | 2026-11-10 | B | آخرین نسخهٔ push شده = T3 (multi-line contract). زنجیرهٔ `e66bd888` ← T1 ← T2 ← T3 روی GitHub است؛ T4–T9 محلی‌اند. |
| `fix/content-pipeline-recovery` | `60880a6c` | 2026-10-18 | B | ancestor از `15esfand1404` — شاخهٔ ریکاوری RSS که merged شد؛ دیگر مستقل نیست. |
| `tuning12khordad` | `da283ace` | 2026-03-06 | B | ancestor از `15esfand1404` — شاخهٔ قدیمی‌تر (امتیازدهی/رسانه). نامش شبیه `tuning12khordad` قدیمی است ولی در نسل B است. |
| `main2` (لوکال) | `758902b5` | 2025-12-31 | A | نسل legacy؛ 1 commit پشت `origin/main2`. |
| `origin/main2` (= origin/HEAD) | `c6b0eca9` | 2026-08-12 | A | **پیش‌فرض GitHub است ولی Production از آن deploy نمی‌کند.** آخرش commit «plan» است. |
| 10 شاخهٔ remote قدیمی (2023-2026) | — | 2023 تا 2026-01 | A | شاخه‌های فیچر ربات قرآن (v5.86 → v6.18)، nova، voice_of_islam و… — تاریخ‌نگاری نسل A. |

### ۲.۳ وضعیت لحظهٔ audit (2026-11-10) — Snapshot تاریخی + وضعیت نهایی (2026-11-11)

| مورد | وضعیت |
|---|---|
| شاخهٔ کاری لوکال | `15esfand1404` |
| Push‌شده/نشده | `e66bd888` + T1–T3 **روی GitHub** (origin = `64de7a3a`)؛ **T4–T10 (`76468b01`…`4851503c`) push نشده** |
| فایل untracked (لحظهٔ audit 2026-11-10) | `docs/superpowers/` (پلن v2 امضا) |
| فایل untracked (release-gate 2026-11-11، قبل از finalization) | + `tests/Unit/SignatureStateTransitionTest.php` + `tests/Feature/SignatureStateTransitionFeatureTest.php` (تست‌های جدید ترنزیشن) |
| درخت (2026-11-11، بعد از finalization) | **تمیز** — پلن + ۲ فایل تست + همین MASTER_MAP در commit نهایی‌سازی (scope `cp-signature:`) وارد git شدند؛ فایل زائد ۰‌بایتی `$ok }` (حاصل خطای typing ترمینال، 2026-11-11 15:17) حذف شد |
| Deploy (2026-11-11) | **انجام نشده** — پراکشن همچنان `0b8f990d`/`cb5b8423`؛ push + deploy فقط بعد از تأیید مالک |
| `origin/HEAD` | → `origin/main2` (نسل legacy) — **مهم:** اگر کسی clone کند، default branch `main2` است و **باید** `git checkout 15esfand1404` بزند. |

### ۲.۴ Tags

| Tag | روی چه ناسلی | نکته |
|---|---|---|
| `v1.0` (2024-07-23) | A (legacy) | آخرین tag پراکتیکال نسل legacy |
| `v3.0.0 … v3.2.11` (2023) | A | دورهٔ ربات قرآن v5-v6 |
| `v10.0.0 … v10.0.5` (2023) | A | تگ‌های Laravel 10 |
| — | **نسل B هیچ tag ندارد** | اولین release tag نسل B هنوز زده نشده (ایده: `v11.x` برای نسل fresh-start) |

### ۲.۵ یافتهٔ خاص: commit یتیم

`8cd5c275` — "Agent host session 2ab96d1b… baseline checkpoint" (2026-10-02): ریشهٔ سومِ orphan که **در هیچ branch/tag فعلی نیست** (فقط در object store/reflog). یک checkpoint لحظه‌ای agent بوده که بعداً با fresh-start یا pull عوض شده. **مطمئن‌کننده نیست که محتوایش منحصر‌به‌فرد باشد؛ در صورت نیاز می‌توان از آن فایل‌ها را گرفت** (`git show 8cd5c275:<path>`).

### ۲.۶ CI/CD

- [`.github/workflows/tests.yml`](../.github/workflows/tests.yml): PHPUnit روی PHP 8.1/8.2 — **اما فقط روی branch‌های `master` و `*.x`**. هیچ‌کدام از شاخه‌های فعال (`15esfand1404`، `main2`) این الگو را ندارند ⇒ **CI عملاً روی شاخه‌های زنده غیرفعال است.** تست‌ها فقط محلی/دستی اجرا می‌شوند.
- `workflows/pull-requests.yml`، `issues.yml`، `update-changelog.yml` هم وجود دارند (فعالیت واقعی‌شان در شاخه‌های زنده تأییدنشده).
- **deploy خودکار وجود ندارد** — deploy دستی از cPanel ترمینال است (بخش ۳).

---

## ۳. Production — کجاست و چگونه کار می‌کند

| مورد | مقدار | منبع |
|---|---|---|
| سرور | shared hosting + **cPanel**؛ IP `80.191.92.187`، کاربر `pardisa2` | [PRODUCTION-RECOVERY-07](PRODUCTION_RECOVERY_07_CPANEL_CHECKLIST.md) |
| مسیر ریشه | **`/home/pardisa2/bots`** | `deploy_commands.sh`، RUNBOOK |
| PHP | 8.2.34 در `/usr/local/bin/php` | `deploy_commands.sh` |
| دامنه | `https://bots.pardisania.ir` | `.env.example` |
| Queue | `sync` — **هیچ worker/queue broker ندارد** | RUNBOOK |
| دسترسی agent | **دسترسی مستقیم ندارد** (SSH از دستگاه محلی در 2026-11-06 timeout شد). همهٔ کارها به‌صورت check-list برای اجرا در **cPanel → Terminal** نوشته می‌شود | [RECOVERY-07](PRODUCTION_RECOVERY_07_CPANEL_CHECKLIST.md) |

### ۳.۱ وضعیت commit پراکشن (آخرین اطلاعات مستند، 2026-11-07)

```
دوران‌شدهٔ قبلی:  d69ef082  (PRODUCTION-STABILIZATION-06)
هدف تأییدشده:     0b8f990d  (storage pruning + free-space guard)   ← push شده (داخل cb5b8423)
بعد از آن روی 15esfand1404: cb5b8423 → e66bd888 → T1 → T2 → T3 (64de7a3a) → [T4–T10: 4851503c محلی، push نشده]
```
به‌روزرسانی T10 (2026-11-10): `e66bd888` + T1–T3 **به GitHub رفته‌اند** (origin/15esfand1404 = `64de7a3a`) ولی **deploy به پراکشن اجرا نشده** — پراکشن همچنان **تا `0b8f990d`** (یا `cb5b8423`) است؛ T4–T10 (۷ commit) push نشده‌اند و بعد از push باید deploy شوند.

### ۳.۲ crontab واقعی پراکشن (مهم‌تر از «فقط یک خط»)

- قانون رسمی مستندات: **فقط** `* * * * * … artisan schedule:run` (به‌خاطر `withoutOverlapping` و قفل‌های cache — مستقیم cron زدن برای command تداخل می‌سازد؛ ریشهٔ باگ `rss_read_translate_lock`).
- **ولی** crontab واقعی پراکشن (مستندشده در [RECOVERY-07](PRODUCTION_RECOVERY_07_CPANEL_CHECKLIST.md)) حداقل 3 خط دارد:
  1. `* * * * * … schedule:run`
  2. `*/14 * * * * … app:rss-post-to-translate` (خط قدیمی؛ در scheduler نیست — `app:rss-post-to-translate` عمدی از Kernel خارج شده)
  3. `46 2 * * * … artisan add_mp3_to_rss_for_sharabebeheshti` ← **خط خراب** (پیشوند `app:` کم بود ⇒ command هرگز اجرا نشد). اصلاح: `30 20 * * *` + `app:` prefix. چک‌لیست کامل fix در [RECOVERY-07](PRODUCTION_RECOVERY_07_CPANEL_CHECKLIST.md).
- ⚠️ `storage:logs-cleanup` در `SCHEDULER_CRON_PLAN.md` قدیمی ذکر شده ولی **در کد وجود ندارد** — هرگز به cron اضافه نشود.

### ۳.۳ فرآیند deploy (ترتیب رسمی)

از [`deploy_commands.sh`](../deploy_commands.sh) + [PRODUCTION_RUNBOOK](PRODUCTION_RUNBOOK.md) §2:

```bash
cd /home/pardisa2/bots
git pull origin 15esfand1404
export COMPOSER_MEMORY_LIMIT=-1
php -d memory_limit=-1 /usr/bin/composer install --no-dev --optimize-autoloader
php artisan config:clear && php artisan route:clear && php artisan cache:clear && php artisan view:clear
php artisan migrate --force              # فقط بعد از backup دیتابیس!
php artisan webhook-endpoints:import-default
php artisan config:cache && php artisan route:cache   # نه optimize:clear
crontab -l | grep schedule:run           # چک خط دقیقه‌ای
```

Smoke test بعد از deploy: `curl https://bots.pardisania.ir/api/v1/quran/languages` + `php artisan pipeline:status` + `tail storage/logs/laravel.log`.

### ۳.۴ Observability پراکشن

| ابزار | مسیر |
|---|---|
| سلامت JSON | `/api/health?token=$HEALTH_SECRET` |
| Prometheus | `/api/metrics?token=$METRICS_SECRET` |
| Log viewer | `/api/{LOG_VIEWER_SECRET}` |
| وضعیت پاپ‌لاین | `php artisan pipeline:status` (exit 1 = مشکل) |
| ریکاوری RSS | `php artisan rss:recover` (dry-run-first، نیمکت 48h، سقف 100 رکورد) |

---

## ۴. نقشهٔ زیرسیستم‌ها (هر پروژه/بخش چه می‌کند)

### ۴.۱ چهار موتور اصلی (heartbeats پراکشن)

| زیرسیستم | ورودی | مسیر | مستند |
|---|---|---|---|
| **RSS Pipeline** | cron هر 15 دقیقه `app:rss_read_translate` | فیدها → `RssService` → queue ترجمه → `RssPostItemTranslationToMessengerJob` (sync) → پیام‌رسان‌ها | [AGENT_HANDOFF §3.1](AGENT_HANDOFF.md) |
| **Channel Poster** | cron هر 5 دقیقه `channel-poster:process` + ربات `/webhook-channel-poster` | `ChannelPosterQueue` → publisherها (Bale/TG/Eitaa/Soroush) + re-upload رسانه (≤50MB) + `PostOverdueWatchdog` | [docs/features/CHANNEL_POSTER_BOT.md](features/CHANNEL_POSTER_BOT.md) |
| **Content Sync** | ربات `/sync` | `ContentSyncService` (1251 خط): parse URL، dedup، attribution فریزشده | [docs/features/PLATFORM_LINK_FORMAT_COMPATIBILITY.md](features/PLATFORM_LINK_FORMAT_COMPATIBILITY.md) |
| **Bot Mother** | `/webhook-bot-mother` | ساخت/ثبت ~37 نوع ربات؛ `BotMotherController` (199KB — بزرگ‌ترین فایل) | [`.agents/AGENTS.md` §4](../.agents/AGENTS.md) |

### ۴.۲ کاتالوگ 37 ربات

جدول کامل (webhook + controller + فایل‌های کلیدی): **`.agents/AGENTS.md` بخش 3** — در 5 گروه:
- 📖 مذهبی (6): Quran، Hadith، Nahj، Prayer Qadha، Sharabe Beheshti، Mawkib Finder
- 🌤️ کاربردی (11): Weather، Presenter، Rating، Psychology Test، List، Content Submission، RSS، Blog، GetChatID، MP Contact، Channel Poster
- 📚 کتاب/رسانه (6): Book Library، Library Reader، Book Pixel، Poem، Audio Book، Song Sara
- 👔 کسب‌وکار (8): Personnel Registration/Admin، Mission، Mission Media، Task Approval، Admin Bots، Admin Daily Channel، Admin Channel Media Queue
- 🛠️ پیشرفته (5): Social Publish، Bot Kids، Admin (BotMother)، RSS Admin، Growth Companion

### ۴.۳ ماژول‌های مجزا (`app/Modules/`)

| ماژول | نقش |
|---|---|
| `BotOwner` | پنل وب مالک ربات (داشبورد، ادمین‌ها، Pro) — Auth با **Bale Safir OTP** |
| `BotCreation` | workflow ساخت ربات v2 (پنل وب) |
| `BaleOtp` | OTP بله (سافیر) — مشترک |
| `BotRegistration` | ثبت‌نام ربات |
| `AdminBots` | ربات مدیریت ربات‌ها |

### ۴.۴ بقیهٔ ساختار

| مسیر | محتوا |
|---|---|
| `app/Http/Controllers` (79) | 37 webhook ربات + `Api/` (REST) + `Admin/` (وب) |
| `app/Services` (102) | `ContentSync/`، `ChannelPoster/`، `Email/` + ~85 سرویس |
| `app/Models` (154) | ORM + relations (فهرست گروه‌ها: `AGENTS.md` §5) |
| `app/Console/Commands` (106) | 36 entry در Kernel + دستورات دستی (pipeline:status، rss:recover، …) |
| `app/Logging` | `DatabaseLogger`/`DatabaseLogHandler` — لاگ ساختاریافته |
| `app/Nova` (57) | پنل ادمین (fork خصوصی Nova) |
| `app/Repositories` (19) / `app/Interfaces` | Repository/Service pattern (استفادهٔ محدود) |
| `routes/api.php` (215 خط، 37 webhook) | ورودی اصلی + REST API (quran، missions با `api.token`، metadata) |
| `routes/bot-owner.php`، `routes/web.php` | پنل مالک + وب (Idea/Ticket، OTP) |
| `lang/` (de-DE، en، es，ur，zh-CN) | 6 زبان UI |
| `tests/` | ~864 تست (آخرین اجرای 2026-11-11/release-gate: **864 passed / 2 skipped / 0 failed — 5367 assertions** روی PHP 8.2.12؛ PHP 8.5.5: 862 passed + 2 deprecated / 5367 assertions) |

### ۴.۵ گروه‌های env (از [`.env.example`](../.env.example) — 234 خط)

| گروه | متغیرهای کلیدی |
|---|---|
| Core/observability | `APP_URL`، `LOG_VIEWER_SECRET`، `METRICS_SECRET`، `HEALTH_SECRET` |
| Bot Mother | `BOT_MOTHER_TOKEN_{BALE,TELEGRAM,GAP}`، `SUPER_ADMIN_CHAT_ID_*` (6 متغیر برای `AdminHelper`) |
| ربات‌های تخصصی | `QURAN_HEFZ_*`، `BOT_HADITH_*`، `PERSONNEL_*`، `MISSION_*`، `RSS_ADMIN_BOT_*`، `ADMIN_BOTS_*` |
| OTP | `BALE_SAFIR_CLIENT_ID/SECRET`، `BOT_OWNER_OTP_*` |
| ترجمه/AI | `ONE_API_API_TOKEN`، `TRANSLATION_DRIVER` (one_api\|llm\|auto)، `AI_DEFAULT_*` (ai.ismc.ir)، `GROWTH_LLM_*` |
| Weather | `OPENWEATHER_API_TOKEN`، `TOMORROW_API_TOKEN` |
| Email/SMS | Kavenegar، Mailtrap (`Email` services)، `MAILTRAP_*` |
| نوتیفیکیشن | `ROCKETCHAT_WEBHOOK_URL` (chat.depna.com) |
| Blog | `BLOG_API_URL` (blog.pardisania.ir) |
| تست | `TEST_PREVIEW_*`، `TestScheduleDailyIntoSlack` |
| Channel poster | توکن‌ها **در دیتابیس** (`channel_poster_destinations.bot_token`) نه env |

### ۴.۶ وابستگی‌های خارجی (خارج از این مخزن)

| سرویس | کاربرد |
|---|---|
| `blog.pardisania.ir` (API) | فید بلاگ → بلاگ‌بات |
| `chat.depna.com` (RocketChat) | اعلان‌های operat (pipeline:watch و…) |
| `one-api.ir` | ترجمهٔ RSS |
| `ai.ismc.ir` | LLM provider (ترجمه/health) |
| OpenWeatherMap / Tomorrow.io | ربات آب‌وهوا |
| Bale Safir (safir.bale.ai) | OTP |
| Kavenegar / Mailtrap | SMS / Email |
| فیدهای سایت: sharabebeheshti.ir، navaar.ir، songsara.net، balaghah.net | محتوای RSS ویژه |

---

## ۵. ایندکس مستندات (هر سند کجاست و چه می‌گوید)

### ۵.۱ لایهٔ ۰ — نقطهٔ ورود

| سند | نقش |
|---|---|
| **این فایل** | نقشهٔ جامع + گیت |
| [`README.md`](../README.md) | ورودی عملیاتی خلاصه (clone/checkout/serve) |
| [`docs/AGENT_HANDOFF.md`](AGENT_HANDOFF.md) | **قوانین طلایی، زیرسیستم‌ها، ریکاوری، قرارداد تست** — اولین فایلی که باید بخوانی |
| [`docs/PROJECT_MASTER_MAP.md`](PROJECT_MASTER_MAP.md) | ← اینجا |
| [`.agents/AGENTS.md`](../.agents/AGENTS.md) | دانش‌نامه: کاتالوگ 37 ربات، معماری، env، API |

### ۵.۲ لایهٔ ۱ — معماری و عملیات

| سند | محتوا |
|---|---|
| [`docs/ARCHITECTURE.md`](ARCHITECTURE.md) | لایه‌ها، دیاگرام جریان، 37 webhook، REST API |
| [`docs/CHANNEL_POSTER_SIGNATURES.md`](CHANNEL_POSTER_SIGNATURES.md) | ★ مرجع امضای کانال (۲۰۲۶-۱۱-۱۰): نقشهٔ سناریو→سیاست/چیدمان، اولویت `signature_enabled`، `/deletepurelink`، ترتیب پیش‌فرض + `link_order`، راهنمای Eitaa plain، parse_mode رسانه |
| [`docs/JOBS_AND_SCHEDULER.md`](JOBS_AND_SCHEDULER.md) | مرجع رسمی 30+ entry scheduler، قفل‌ها، UTC↔تهران |
| [`docs/PRODUCTION_RUNBOOK.md`](PRODUCTION_RUNBOOK.md) | deploy / migrate / cron / rollback |
| [`docs/SCHEDULER_CRON_PLAN.md`](SCHEDULER_CRON_PLAN.md) | ⚠️ قدیمی — `storage:logs-cleanup` آن منسوخ است |
| [`docs/RECOVERY_ROLLOUT_PLAN.md`](RECOVERY_ROLLOUT_PLAN.md) | رول‌اوت مرحله‌ای ریکاوری backlog |
| [`docs/PRODUCTION_STABILIZATION_06_CHECKLIST.md`](PRODUCTION_STABILIZATION_06_CHECKLIST.md) | چک‌لیست operat 2026-11 |
| [`docs/PRODUCTION_RECOVERY_07_CPANEL_CHECKLIST.md`](PRODUCTION_RECOVERY_07_CPANEL_CHECKLIST.md) | ★ جدیدترین: bug cron sharabe + deploy `0b8f990d` |
| [`DEPLOY_INSTRUCTIONS.md`](../DEPLOY_INSTRUCTIONS.md) / [`deploy_commands.sh`](../deploy_commands.sh) | deploy پایه + چک cron |
| [`CHANGELOG.md`](../CHANGELOG.md) | تاریخچهٔ release |

### ۵.۳ لایهٔ ۲ — فیچر (54+ سند در `docs/features/`)

مهم‌ترین‌ها:
- [`CHANNEL_POSTER_BOT.md`](features/CHANNEL_POSTER_BOT.md)، [`messenger-platform-capability-matrix.md`](features/messenger-platform-capability-matrix.md)، [`PLATFORM_LINK_FORMAT_COMPATIBILITY.md`](features/PLATFORM_LINK_FORMAT_COMPATIBILITY.md)، [`LINKED_SIGNATURE.md`](features/LINKED_SIGNATURE.md)، [`SMART_SIGNATURE_SETTINGS.md`](features/SMART_SIGNATURE_SETTINGS.md)
- [`mission-bot.md`](features/mission-bot.md)، [`mission-selection.md`](features/mission-selection.md)
- [`GROWTH_COMPANION.md`](features/GROWTH_COMPANION.md)، [`PRAYER_BOT_README.md`](features/PRAYER_BOT_README.md)، [`QURAN_TRANSLATION_IMPORT.md`](features/QURAN_TRANSLATION_IMPORT.md)
- `BOOK_PIXEL_BOT*` (7 سند)، `BOOK_LIBRARY_BOT.md`، [`personnel-registration.md`](features/personnel-registration.md)
- [`rubika/`](features/rubika/README.md) (coverage matrix + CHAT_ID_DISCOVERY)، [`soroush/`](features/soroush/README.md)
- [`PROMETHEUS_METRICS.md`](features/PROMETHEUS_METRICS.md)، [`BOT_HEALTH_JSON.md`](features/BOT_HEALTH_JSON.md)، [`LOG_VIEWER_AND_BOT_HEALTH.md`](features/LOG_VIEWER_AND_BOT_HEALTH.md)
- [`OFFICIAL/EitaaYar_API_Documentation`](features/OFFICIAL/EitaaYar_API_Documentation.txt) — مستند رسمی API ایطلا (مورداستفاده در RCA لینک)

### ۵.۴ برنامه‌های باز/تکمیل‌شده (`plans/`)

| پلان | وضعیت (از commitها) |
|---|---|
| `LLM_REPORTS_TRANSLATION_QUEUE_CAMPAIGNS.md` | ✅ پیاده (commit `d71e40fa`) |
| `quran-bot-admin-broadcast-architecture.md`، `QURAN_BOT_IMPROVEMENTS_PLAN.md` | ✅ پیاده (commits 33fa5960/932ee4f9) |
| `library-milestone-rewards-plan.md` | ✅ تست‌ها: `tests/Feature/BookLibrary/MilestoneRewardTest.php` |
| `BOT_ADMIN_MANAGEMENT_PANEL.md`، `BOT_CLAIM_TOME_SYSTEM.md`، `BOT_OWNERSHIP_CLAIM_VERIFICATION.md`، `UNIFIED_BOT_CREATION_WORKFLOW.md`، `BOT_GENERATOR_ARCHITECTURE.md` | برنامه‌ها (بعضی پیاده در Modules؛ وضعیت تک‌تک تأییدنشده) |

### ۵.۵ مستندات قدیمی/ریشه‌ای (نسل A)

`README-DEVELOP.md`، `docs/GETTING-STARTED.md`، `docs/BOTS_COMPLETE_GUIDE.md`، `docs/BOT_CREATION_GUIDE.md`، `docs/QURAN_BOTS_MANAGEMENT_GUIDE.md`، `PERSONNEL_REGISTRATION_README.md`، `QUICK_START_BOOK_LIBRARY.md`، `TEST_SCENARIO*.md` — بعضی از دورهٔ نسل A هستند و ممکن است قدیمی باشند؛ مرجع فعلی لایه‌های ۰/۱ است.

---

## ۶. وضعیت تست (آخرین اجراهای واقعی)

| اجرا | نتیجه |
|---|---|
| 2026-10-19 (PHP 8.5 محلی) | **399 passed / 2 skipped / 2 deprecated — 2205 assertions, 0 failure** (108s) |
| قبل از deploy RECOVERY-07 (2026-11-07) | **779 passed / 4559 assertions / 0 failed** |
| 2026-11-10 (T10، PHP 8.5 محلی) | **852 passed / 2 skipped / 0 failed — 5037 assertions** (`2 deprecated` = نویز vendor) |
| 2026-11-11 (release-gate، PHP 8.2.12) | **864 passed / 2 skipped / 0 failed — 5367 assertions** (138.79s) |
| 2026-11-11 (release-gate، PHP 8.5.5) | **862 passed + 2 deprecated / 2 skipped / 0 failed — 5367 assertions** (90.35s) |
| قرارداد | `php artisan test --compact`؛ `php -l` مدرک نیست؛ عدد واقعی گزارش شود؛ deprecation محلی ≠ failure |

> تفاوت 399 و 779: بین دو تاریخ تست‌های زیادی (ChannelPoster، Rubika، Eitaa، Storage) اضافه شده.

---

## ۷. کارهای باز و ریسک‌ها (Action Items)

| # | مورد | وضعیت |
|---|---|---|
| 1 | **T4–T10 (۷ commit، `76468b01`…`4851503c`) push نشده** روی `15esfand1404` (origin = `64de7a3a`/T3) | باز — بعد از push، deploy به پراکشن (پراکشن هنوز `0b8f990d`/`cb5b8423`) |
| 2 | **خط cron خراب sharabe-beheshti** در crontab پراکشن (کمبود `app:`) | چک‌لیست fix در [RECOVERY-07](PRODUCTION_RECOVERY_07_CPANEL_CHECKLIST.md) — اجرا در cPanel |
| 3 | **rotate توکن Bale لیک‌شده** در لاگ (تاریخ) | تأییدنشده — چک‌لیست در [PRODUCTION_RUNBOOK](PRODUCTION_RUNBOOK.md) |
| 4 | تعریف تکراری `POST /api/webhook-rss` در `routes/api.php` (legacy + جدید) | شناخته‌شده — تعریف بعدی برنده است؛ نیاز به تمیزکاری |
| 5 | 3 command بدون `withoutOverlapping()` (`task-reminder`، `users-ranking`، `test-schedule-daily-into-slack`) | شناخته‌شده در JOBS_AND_SCHEDULER |
| 6 | `origin/HEAD` روی `main2` (legacy) است ولی پراکشن `15esfand1404` است | ریسک clone اشتباه — تغییر default branch در GitHub پیشنهاد می‌شود |
| 7 | CI روی شاخه‌های زنده غیرفعال است (`*.x`/`master` pattern) | ریسک — اضافه‌کردن `15esfand1404` به triggers پیشنهاد می‌شود |
| 8 | فایل‌های غول‌پیکر: `BotMotherController` 199KB، `QuranWordController` 155KB، `ChannelPosterBotController` 5155 خط | refactor پیشنهادی |
| 9 | `full_sched.txt` untracked | حذف یا gitignore |
| 10 | نسل B (fresh start) tag ندارد | ایده: اولین release tag بعد از deploy `e66bd888` |
| 11 | فرمت canonical URL سoroush در `PostUrlParser` | تأییدنشده عملیاً |

---

## ۸. چک‌لیست شروع کار برای دفعات بعدی

1. `git status -sb` → باید `15esfand1404` و درخت تمیز باشد.
2. اگر push نشده‌ای دیدی (`e66bd888` یا بعدی‌ها) → اول push، بعد deploy.
3. قبل از هر تغییر روی خط انتشار: `docs/AGENT_HANDOFF.md` §2 (قوانین طلایی) را بخوان.
4. تست: `php artisan test --compact` (انتظار: ~864 passed؛ deprecation محلی = نویز).
5. هر تغییر scheduler/cron → همزمان `docs/JOBS_AND_SCHEDULER.md` آپدیت شود.
6. هر فیچر جدید → `docs/features/<FEATURE>.md` + سناریوی تست (طبق `.agents/rules/DOCUMENTATION_RULE.md`).
7. کارهای پراکشنی → فقط در قالب check-list برای cPanel ترمینال (دسترسی مستقیم agent نیست).
8. گزارش پایانی مطابق قالب 11-بخشی `AGENT_HANDOFF.md` §11.

---

*تألیف: audit کامل 2026-11-10 (Zoo). همهٔ یافته‌های گیت با `git rev-list/merge-base/for-each-ref/show` و یافته‌های کوب با شمارش مستقیم فایل‌ها تأیید شد.*
