# AI-05 — Personal AI Companion Discovery

**وضعیت: NEEDS-DESIGN** (پیاده‌سازی وابسته به AI-01 و AI-07)

## 1) خلاصهٔ مدیریتی

بین دو گزینه، **رشدیار (Growth Companion)** به‌مراتب مناسب‌تر است: از قبل «وضعیت» دارد (پروفایل، برنامه، ثبت روزانه، مرور هفته)، ماشین حالت مکالمه (`gc_*`)، چندزبانه، لایهٔ Pro و تست دارد. ربات نماز قضا داده‌محور و ساده است (شمارش رکعت + تخمین) و حافظهٔ گفتگو ندارد. بنابراین MVP دستیار شخصی باید روی رشدیار ساخته شود، نه به‌صورت یک چت‌بات عمومی.

## 2) وضعیت فعلی

| قابلیت | رشدیار | نماز قضا |
|---|---|---|
| ثبت/پیگیری برنامه | ✅ `growth_programs`, `growth_profile_topics` | ⚠️ فقط رکعت‌شمار |
| ثبت وضعیت روزانه | ✅ `growth_daily_checkins` | ❌ |
| مرور دوره‌ای | ✅ `growth_reviews` (هفتگی) | ⚠️ گزارش هفتگی ایمیلی |
| حافظهٔ مکالمه | ⚠️ State ویزارد (`gc_` در `bot_user_states`) نه حافظهٔ معنایی | ❌ |
| LLM | ✅ اختیاری (واریانت سؤال) | ❌ |
| چندزبانه | ✅ (locale از `language_code` ربات) | ✅ (lang/fa,en) |
| اشتراک/Pro | ✅ `pro_users.expires_at` | ❌ |
| تست | ✅ [`GrowthCompanionPhase2UnitTest`](../../tests/Unit/GrowthCompanionPhase2UnitTest.php)، [`GrowthQuestionSelectorTest`](../../tests/Unit/GrowthQuestionSelectorTest.php) | ⚠️ محدود |

## 3) شواهد کد و دیتابیس

- کنترلر: [`GrowthCompanionController`](../../app/Http/Controllers/GrowthCompanionController.php) — Callbackها با پیشوند `gc:` ([مستند](../features/GROWTH_COMPANION.md:71)).
- سرویس: [`GrowthCompanionServiceImpl`](../../app/Services/GrowthCompanionServiceImpl.php:89) (پروفایل/چک‌این/مرور).
- انتخاب سؤال: [`GrowthQuestionSelector`](../../app/Services/GrowthQuestionSelector.php).
- LLM جدا: [`HttpGrowthLlmProvider`](../../app/Services/HttpGrowthLlmProvider.php:16) — کلید `GROWTH_LLM_*`، Prompt از `resources/growth_prompts/variant_generation.v1.md`.
- Migrationها: [`2026_08_17_200000_create_growth_companion_tables.php`](../../database/migrations/2026_08_17_200000_create_growth_companion_tables.php)، [`2026_08_19_160000_create_growth_daily_checkins.php`](../../database/migrations/2026_08_19_160000_create_growth_daily_checkins.php).
- زمان‌بندی: `growth:dispatch-due` هر ۵ دقیقه ([`Kernel.php:149`](../../app/Console/Kernel.php:149)).
- نماز قضا: [`PrayerBotController`](../../app/Http/Controllers/PrayerBotController.php:296) (۲/۳/۴ رکعت)، [`PrayerBotServiceImpl`](../../app/Services/PrayerBotServiceImpl.php:133)، جدول `prayer_records` ([migration](../../database/migrations/2026_01_06_140532_create_prayer_records_table.php:23)).

## 4) وضعیت Local / GitHub / Production

| محیط | وضعیت |
|---|---|
| Local | DB خالی؛ اجرای واقعی جریان رشد ممکن نیست |
| GitHub | `7702b68f` |
| Production | کد قدیمی‌تر؛ وضعیت واقعی رشدیار (تعداد کاربر/Pro) نامعلوم |

## 5) تست‌های انجام‌شده و نتایج واقعی

- Unit: Growth phase 2 + QuestionSelector — بخشی از رگرسیون ۸۶۴ تست سبز ([PMM](../PROJECT_MASTER_MAP.md:217)).
- تست واقعی دستیار LLM: **انجام نشده** (بدون توکن؛ `GROWTH_LLM_API_KEY` محلی خالی نیست ولی مدل مقصد تأییدنشده).

## 6) موارد ناشناخته و محدودیت دسترسی

- نرخ پاسخ‌دهی کاربران به کارت روزانه و طول عمر (retention).
- سیاست حریم خصوصی برای استفاده از تاریخچهٔ گفتگو در Prompt.
- مدل مقصد `GROWTH_LLM_*` در Production (OpenAI یا LiteLLM؟).

## 7) مشکلات و ریسک‌ها

- **حریم خصوصی:** بازتاب متن کاربر به Prompt ⇒ نیاز به Consent صریح و امکان پاک‌سازی.
- **توهم (Hallucination):** توصیهٔ سلامت/روان — باید Guardrail و «نه تشخیص پزشکی» داشته باشد (همین امروز در مستند ذکر شده).
- **وابستگی به LLM:** اگر LLM پاسخ ندهد، «کارت امروز» باید از Seeder بماند (امروز همین است — حفظ شود).
- **State در `bot_user_states`** برای مکالمهٔ چندنوبتی کافی نیست؛ انبار حافظهٔ مستقل لازم است.
- با `sync`، پاسخ LLM وب‌هوک را Block می‌کند ⇒ تجربهٔ کند در پیام‌های متوالی.

## 8) قابلیت‌های قابل استفاده مجدد

- `GrowthCompanionServiceImpl` (پروفایل/برنامه/چک‌این/مرور) — پایهٔ «وضعیت».
- `GrowthQuestionSelector` — Fallback بدون AI.
- `HttpGrowthLlmProvider` — الگوی Prompt فایل‌محور نسخه‌دار (`resources/growth_prompts/*.vN.md`).
- `pro_users` — گیت اشتراک برای قابلیت AI.

## 9) پیشنهاد MVP

**انتخاب: رشدیار.**

1. ورودی: متن (صوت در فاز بعد — AI-03).
2. حافظه: جدول جدید `growth_assistant_messages` (`profile_id`, `role`, `content`, `tokens`, `created_at`) — **فقط با رضایت**.
3. Master Prompt نسخه‌دار: `resources/growth_prompts/companion.v1.md` با قواعد: بدون تشخیص، بدون وعده، فارسی طبیعی، ≤۳ پاراگراف، پایان با یک سؤال یا یک اقدام.
4. شناخت هدف: تزریق خلاصهٔ `growth_profile` + `growth_programs` فعال + ۷ روز چک‌این.
5. پیگیری: `growth_assistant_followups` (`profile_id`, `due_at`, `text`, `status`) — یادآوری با رضایت، مصرف‌کننده: `growth:dispatch-due`.
6. گزارش پیشرفت: خلاصهٔ هفتگی موجود + یک پاراگراف LLM (اختیاری).
7. حالت قطعی AI: پیام «دستیار موقتاً در دسترس نیست» + کارت روزانهٔ معمول؛ هیچ داده‌ای گم نشود.

**سناریوهای پذیرش:** «هدفم را تغییر بده» → به‌روزرسانی برنامه · «دیروز چه کردم؟» → خلاصه از چک‌این واقعی · «۲ روز دیگر یادم بیاور» → ثبت Followup · قطع LLM → پاسخ Fallback بدون خطا.

## 10) مراحل بعدی و وابستگی‌ها

| گام | وابستگی |
|---|---|
| تأیید انتخاب رشدیار | مالک |
| توکن/مدل LLM | AI-01 |
| صف برای پاسخ‌های LLM | AI-07 |
| سیاست Consent و Retention | مالک |

**Status: NEEDS-DESIGN.**
