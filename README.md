# 🤖 Bots — پلتفرم مدیریت ربات‌های پیام‌رسان (Laravel 10)

> **نقطهٔ ورود عملیاتی** برای انسان‌ها و agentها. این فایل خلاصه است؛ جزئیات در مستندات زیر.
> اگر agent هستی: **اول [`docs/AGENT_HANDOFF.md`](docs/AGENT_HANDOFF.md) را بخوان.**

---

## ⚡ این پروژه چیست؟

یک سامانهٔ Laravel 10 روی **یک سرور cPanel** که کارهای زیر را انجام می‌دهد:

| زیرسیستم | کاری که می‌کند |
|---|---|
| **Bot Mother** | ساخت/پیکربندی ~37 نوع ربات پیام‌رسان از طریق چت یا وب |
| **Channel Poster** | انتشار چندکاناله محتوا (بله، تلگرام، ایطلا، سoroush) با صف، رسانه، watchdog و ریکاوری |
| **Content Sync** | همگام‌سازی یک پست منتشرشده به سایر کانال‌های تگ‌شده با dedup و attribution |
| **RSS Pipeline** | خواندن فیدها، ترجمه و انتشار خودکار در کانال‌های RSS (هر 15 دقیقه) + ریکاوری |
| **ربات‌های تخصصی** | قرآن، حدیث، نماز، آب‌وهوا، کتاب، مأموریت، … (کاتالوگ: [`.agents/AGENTS.md`](.agents/AGENTS.md)) |

**زیرساخت:** PHP 8.2.34 · MySQL · `QUEUE_CONNECTION=sync` (بدون worker) · cron واحد `schedule:run` هر دقیقه · timezone اپلیکیشن **UTC**.

---

## 🚀 شروع سریع

```bash
# محلی
git clone https://github.com/saber13812002/bot-generator-bale-telegram-laravel-10
cd bot-generator-bale-telegram-laravel-10
git checkout 15esfand1404

composer install
cp .env.example .env        # پر کردن DB_*, APP_KEY, tokenها
php artisan key:generate
php artisan migrate

php artisan serve           # محلی
php artisan test --compact  # تست‌ها
```

### Production (cPanel)
- مسیر: `/home/pardisa2/bots`
- **deploy:** [`docs/PRODUCTION_RUNBOOK.md`](docs/PRODUCTION_RUNBOOK.md) §2 (اسکریپت آماده: [`deploy_commands.sh`](deploy_commands.sh))
- **cron (فقط همین یک خط):** `* * * * * cd /home/pardisa2/bots && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1`
- **تست سلامت:** `php artisan pipeline:status` (exit 1 = مشکل)

---

## 📚 نقشهٔ مستندات

| سند | برای چه؟ |
|---|---|
| **[`docs/AGENT_HANDOFF.md`](docs/AGENT_HANDOFF.md)** | ⭐ **مرجع اصلی agent** — هویت پروژه، قوانین طلایی، نقشهٔ زیرسیستم‌ها، عیب‌یابی، قرارداد تست، امنیت |
| **[`docs/JOBS_AND_SCHEDULER.md`](docs/JOBS_AND_SCHEDULER.md)** | مرجع رسمی همهٔ 30 job/cron entry (13 داده برای هرکدام)، قفل‌های همزمانی، تبدیل UTC↔تهران |
| **[`docs/PRODUCTION_RUNBOOK.md`](docs/PRODUCTION_RUNBOOK.md)** | deploy / migrate / cron / diagnose / rollback روی سرور |
| **[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)** | معماری، لایه‌ها، جریان داده، تصمیمات معماری، نقاط ضعف |
| [`docs/features/`](docs/features/) | مستندات فیچر-به-فیچر + سناریوی تست (قانون: هر فیچر جدید باید اینجا مستند شود — [`.agents/rules/DOCUMENTATION_RULE.md`](.agents/rules/DOCUMENTATION_RULE.md)) |
| [`docs/features/TEST_PREVIEW_POST.md`](docs/features/TEST_PREVIEW_POST.md) | 🧪 **پست تستی/پیش‌نمایش** — ارسال متن/عکس/صوت/ویدیو با کپشن به چت تستی از همان متدهای production (`app:send-test-preview`) |
| [`docs/features/LINKED_SIGNATURE.md`](docs/features/LINKED_SIGNATURE.md) | 🔗 **امضای لینک‌دار** — لینک کانال در امضا به‌صورت قابل‌کلیک با نگارش مخصوص هر پیام‌رسان (ایتا/تلگرام HTML، بله مارک‌داون، سروش/روبیکا ساده) + تست اختصاصی دو روش (لینک داخل متن + دکمه URL) با `app:test-signature-link` |
| [`docs/features/PLATFORM_LINK_FORMAT_COMPATIBILITY.md`](docs/features/PLATFORM_LINK_FORMAT_COMPATIBILITY.md) | 🔀 **سازگاری فرمت لینک در ۵ پیام‌رسان** — ماتریس ۱۱×۵ (Telegram/Bale/Eitaa/Soroush/Rubika) با پارامتر دقیق API، نمونه payload، منبع رسمی/جامعه، وضعیت Mock/Live/رندر کلاینت (OFFICIAL_CONFIRMED … OFFICIAL_DOC_NOT_FOUND) — مبنای امضای پیشرفتهٔ `/sign` |
| [`DEPLOY_INSTRUCTIONS.md`](DEPLOY_INSTRUCTIONS.md) · [`deploy_commands.sh`](deploy_commands.sh) | دستورالعمل/اسکریپت deploy |
| [`CHANGELOG.md`](CHANGELOG.md) | تاریخچهٔ تغییرات |
| [`docs/SCHEDULER_CRON_PLAN.md`](docs/SCHEDULER_CRON_PLAN.md) | برنامهٔ اولیهٔ cron (⚠️ مرجع `storage:logs-cleanup` منسوخ — command وجود ندارد) |
| [`docs/RECOVERY_ROLLOUT_PLAN.md`](docs/RECOVERY_ROLLOUT_PLAN.md) | رول‌اوت مرحله‌ای محتوا + rollback |
| [`.agents/AGENTS.md`](.agents/AGENTS.md) | دانش‌نامهٔ پروژه (کاتالوگ 37 ربات، ساختار، env names، APIها) |
| [`docs/API_README.md`](docs/API_README.md) + Swagger | مستندات REST API (`/api/v1/quran`, missions با `api.token`, metadata) |

---

## 🔌 Webhook‌ها و API

- همهٔ webhook ربات‌ها زیر `/api/webhook-*` تعریف‌اند → [`routes/api.php`](routes/api.php) (کاتالوگ کامل در [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) §3)
- مهم‌ترین‌ها: `/api/webhook-channel-poster` (کانال‌پوستر)، `/api/webhook-bot-mother`، `/api/webhook-rss`، `/api/webhook-prayer-bot`، `/api/webhook-quran-word`
- REST: `/api/v1/quran/*`، `/api/v1/missions/*` (نیاز به `api.token`)، `/api/v1/metadata`

---

## 🧪 قرارداد تست

```bash
php artisan test --compact
```

- آخرین اجرا (2026-10-19): **399 passed / 2 skipped / 2 deprecated — 2205 assertions، 0 failure** (PHP محلی 8.5+ نویز deprecation دارد؛ پراکشن 8.2.34)
- هرگز `php -l` را به‌عنوان مدرک رفتار نده؛ هرگز ادعای اجرای تستی را نده که اجرا نشده است.

---

## ⚠️ هشدارهای امن (فقط برای agent/توسعه‌دهنده)

1. **هیچ secret/توکن در مستند، commit یا لاگ.** لاگ‌های token-bearing باید از [`TokenHelper::redactTokens`](app/Helpers/TokenHelper.php) رد شوند.
2. **توکن Bale لیک‌شده در لاگ قدیمی:** آیا rotate شده **تأییدنشده** → اقدام پراکشن: [`docs/PRODUCTION_RUNBOOK.md`](docs/PRODUCTION_RUNBOOK.md) §7.
3. **cron جدید اضافه نکن** بدون آپدیت همزمان `docs/JOBS_AND_SCHEDULER.md`؛ فقط یک خط `schedule:run` مجاز است.
4. **موفقیت جعلی ممنوع:** status `sent`/`published` فقط با پاسخ واقعی پیام‌رسان.
5. **اسلات‌های `daily-channel:post` (00/06/12/18 UTC) دست‌نخورده** — watchdog وابسته به آن‌هاست.

---

## 📂 ساختار کلیدی

```
app/Console/Kernel.php          # ⭐ 30 entry scheduler
app/Services/                   # ~85 service (قلب منطق) + ContentSync/
app/Http/Controllers/           # 73 controller (webhook ربات‌ها + Api)
app/Jobs/                       # 12 job (sync queue → inline)
app/Models/                     # 119 model
routes/api.php                  # webhook‌ها + REST API
docs/                           # ⭐ مستندات عملیاتی (بالا)
tests/                          # Feature/Unit
```

جزئیات: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

---

<p align="center">
  <a href="https://github.com/saber13812002/bot-generator-bale-telegram-laravel-10">GitHub</a>
  · <a href="https://bots.pardisania.ir">🌐 Live</a>
  · <a href="https://saber-tabatabaee.medium.com">📝 Medium</a>
  · <a href="https://vrgl.ir/hp4xr">📖 Virgool</a>
  · <a href="https://hamibash.com/quran_hefz_bale_telegram_bot">❤️ Hamibash</a>
</p>

---

## ⚖️ License

فایل `LICENSE` در مخزن وجود ندارد؛ مالکیت: Saber Tabatabaei — تماس از طریق پروفایل‌های بالا. (افزودن فایل License یک action item باز است.)
