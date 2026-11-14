# AI-02 — Universal Bot Inventory

**وضعیت: READY** (برای طراحی ارتقا) — با محدودیت: دادهٔ Production در دسترس نیست

## 1) خلاصهٔ مدیریتی

پلتفرم **۳۷ نوع ربات** در کاتالوگ رسمی دارد ([`.agents/AGENTS.md`](../../.agents/AGENTS.md:131)) که به‌صورت رکورد `webhook_endpoints` + ربات‌های ساخته‌شده در جدول `bots` ثبت می‌شوند. مصرف فعلی AI بسیار محدود است: **هیچ رباتی OCR/ASR/RAG ندارد**؛ AI فقط در سه نقطه استفاده می‌شود (ترجمهٔ RSS، تحلیل گزارش قرآن، متن انگیزشی کانال) و همه **اختیاری و با Fallback** هستند.

## 2) وضعیت فعلی

| گروه | تعداد | نمونه |
|---|---|---|
| 📖 مذهبی | 6 | Quran، Hadith، Nahj، Prayer Qadha، Sharabe Beheshti، Mawkib Finder |
| 🌤️ کاربردی | 11 | Weather، Presenter، Rating، Psychology، List، Content Submission، RSS، Blog، GetChatID، MP Contact، Channel Poster |
| 📚 کتاب/رسانه | 6 | Book Library، Library Reader، Book Pixel، Poem، Audio Book، Song Sara |
| 👔 کسب‌وکار | 8 | Personnel Reg/Admin، Mission، Mission Media، Task Approval، Admin Bots، Admin Daily Channel، Admin Channel Media Queue |
| 🛠️ پیشرفته | 5 | Social Publish، Bot Kids، Bot Mother، RSS Admin، Growth Companion |

## 3) شواهد کد و دیتابیس

- ثبت انواع ربات: [`webhook_endpoints` migration](../../database/migrations/2026_01_03_144454_create_webhook_endpoints_table.php) + Seederهای هر ربات (نمونه: [`GrowthCompanionWebhookEndpointSeeder.php:18`](../../database/seeders/GrowthCompanionWebhookEndpointSeeder.php:18)، [`BookPixelWebhookEndpointSeeder.php:18`](../../database/seeders/BookPixelWebhookEndpointSeeder.php:18)).
- ربات ساخته‌شده: جدول `bots` با ستون‌های پلتفرمی (`telegram_*`, `bale_*`, `sorusu`/`rubika` طبق [`2026_10_20_000001_add_soroush_rubika_columns_to_bots_table.php`](../../database/migrations/2026_10_20_000001_add_soroush_rubika_columns_to_bots_table.php)).
- **DB محلی خالی است:** `bots = 0` و `webhook_endpoints = 0` (تأیید با خواندن مستقیم Schema/Count) ⇒ فهرست بالا از کد/Seederها/کاتالوگ است، نه از دادهٔ واقعی.
- نقاط AI موجود:
  - ترجمه: [`TranslationService`](../../app/Services/TranslationService.php) + [`LlmTranslationService`](../../app/Services/LlmTranslationService.php:35) (مصرف: خط لولهٔ RSS)
  - گزارش قرآن: [`ActivityReportSummaryService`](../../app/Services/ActivityReportSummaryService.php:208)
  - متن انگیزشی کانال: [`ChannelMotivationSend`](../../app/Console/Commands/ChannelMotivationSend.php:140)
  - واریانت سؤال رشد: [`HttpGrowthLlmProvider`](../../app/Services/HttpGrowthLlmProvider.php:16) (کلید جدا؛ بدون کلید → Seeder)
- Queue/Scheduler هر ربات: `sync` + ۳۶ entry در [`Kernel.php`](../../app/Console/Kernel.php:26) (هیچ Worker وجود ندارد).

## 4) وضعیت Local / GitHub / Production

| محیط | وضعیت |
|---|---|
| Local | `15esfand1404` / `7702b68f` / tree تمیز / DB بدون ربات |
| GitHub | `origin/15esfand1404` = `7702b68f` |
| Production | کد `0b8f990d`/`cb5b8423`؛ DB واقعی (۳۷ ربات + ربات‌های ساخته‌شده) **دسترسی‌ناپذیر** |

## 5) تست‌های انجام‌شده و نتایج واقعی

- اجرای تست‌های موجود در release-gate: **864 passed / 2 skipped / 5367 assertions** (PHP 8.2.12) — منبع [`PROJECT_MASTER_MAP.md:217`](../PROJECT_MASTER_MAP.md:217).
- اجرای مستقیم امروز (زیرمجموعهٔ AI): **10 passed / 42 assertions**.
- تست واقعی هر ربات روی پراکشن: **انجام نشده** (بدون دسترسی).

## 6) موارد ناشناخته و محدودیت دسترسی

- تعداد و پلتفرم واقعی ربات‌های فعال در Production، حجم کاربران و نرخ پیام.
- وضعیت Webhook ثبت‌شده برای هر ربات (`*_webhook_is_set`).
- رفتار واقعی ربات‌ها هنگام قطعی سرویس‌های خارجی (فقط از کد قابل استنتاج است).

## 7) مشکلات و ریسک‌ها

- **AI داخل مسیر درخواست:** با `sync`، هر فراخوانی LLM وب‌هوک را Block می‌کند.
- ربات‌هایی که عکس/صوت می‌گیرند (Book Pixel، Channel Poster، Book Library) هیچ تحلیل محتوایی ندارند؛ فرصت OCR/ASR بی‌استفاده است.
- نبود RAG در ربات‌های دانشی (Quran/Hadith/Nahj) — فقط جستجوی متنی/Fulltext.

## 8) قابلیت‌های قابل استفاده مجدد

- الگوی «قابلیت اختیاری + Fallback» در `ActivityReportSummaryService` و `LlmTranslationService` (در صورت شکست → دادهٔ پایه/متن اصلی).
- `webhook_endpoints.features` / `usage_instructions` (JSON) برای معرفی قابلیت جدید به کاربر و پنل.
- `BotMother` برای ساخت ربات جدید یا افزودن نوع جدید بدون Migration سنگین.

## 9) پیشنهاد MVP — ماتریس ارتقا

| ربات | OCR | ASR | LLM | RAG | اولویت |
|---|---|---|---|---|---|
| Quran Bot | ⭐ (اسکن صفحه → متن) | – | ✅ (گزارش موجود) | ⭐⭐ (پرسش از قرآن) | **بالا** |
| Hadith / Nahj | – | – | کم | ⭐⭐ (پرسش از کتب) | بالا |
| Book Pixel | **⭐⭐ (اسکن صفحه → متن + ویرایش)** | ⭐ | کم | – | **بالا** |
| Book Library / Reader | ⭐ (PDF/تصویر) | – | کم | — | متوسط |
| Channel Poster | ⭐ (متن روی تصویر) | – | ✅ (انگیزشی) | – | متوسط |
| Growth Companion | – | ⭐ (ورودی صوتی) | ⭐⭐ (دستیار) | — | **بالا** |
| Prayer Qadha | – | ⭐ | ⭐ (دستیار) | – | متوسط |
| Mission / Task | ⭐ (رسید/مدرک) | – | کم | – | پایین |
| Weather / Rating / Psychology | – | – | کم | – | پایین |

دلیل ارزش مشتری: OCR برای Book Pixel/Quran ارزش فوری دارد (کاربر خودش عکس می‌فرستد و امروز هیچ پردازشی نمی‌گیرد)؛ RAG برای Quran/Hadith تفکیک‌کنندهٔ محصول است؛ ASR برای رشد و نماز قضا اصطکاک ورودی را حذف می‌کند.

## 10) مراحل بعدی و وابستگی‌ها

1. تأیید مالک روی ۲ ربات اول (پیشنهاد: Book Pixel + Growth).
2. دسترسی خواندنی به DB پراکشن برای آمار واقعی ربات‌ها.
3. تعریف `capabilities` در `webhook_endpoints.settings` پیش از هر کد.

**Status: READY** برای طراحی؛ **BLOCKED** برای دادهٔ واقعی Production.
