# پیوست اجباری: ماتریس پوشش روبیکا روی همهٔ انواع ربات BotMother

> این سند «پیوست اجباری» مأموریت **BM-RUBIKA-DIAGNOSTIC-BOT** است و نتیجهٔ
> ممیزی جامع (audit) نیاز «پشتیبانی یونیورسال روبیکا در تمام انواع ربات» را
> نشان می‌دهد. همهٔ وضعیت‌ها **بر اساس کد واقعی و تست‌های خودکار** تعیین شده‌اند،
> نه تخمین. تاریخ ممیزی: ۲۰۲۶/۰۸/۱۴ (هم‌زمان با Commit مأموریت ۱).

## راهنمای وضعیت‌ها (Legend)

| وضعیت | معنی |
|---|---|
| ✅ `SUPPORTED` | پشتیبانی تولیدی legacy — ترافیک واقعی با vendor SDK (Telegram/Bale) |
| 🧪 `MOCK_TESTED` | تست خودکار با `Http::fake` انجام شده؛ رفتار تأیید شده، **تست زنده انجام نشده** |
| 📝 `REG_ONLY` | قابل ساخت در wizard، اعتبارسنجی توکن، ثبت در `bots` و ست‌شدن webhook انجام می‌شود؛ **اما** controller فرزند آن origin را پردازش نمی‌کند (update با `200` / `{"ok":true}` صامتانه تأیید و نادیده گرفته می‌شود) |
| ❌ `NOT_IMPLEMENTED` | هیچ مسیر کدی وجود ندارد |
| ⛔ `BLOCKED` | محدودیت خود API (مثلاً نبود read-back برای webhook روبیکا) |
| 📤 `DEST_ONLY` | فقط به‌عنوان **مقصد انتشار** (publish destination) — نه به‌عنوان origin ربات |
| ➖ `N/A` | به‌طور طراحی کاربرد ندارد |

سلسله‌مراتب فازی: 📝 یعنی «ساختن و ثبت» کار می‌کند ولی «دریافت و پردازش پیام»
**کار نمی‌کند**؛ این تمایز دقیقاً همان معیارهای ممیزی خواسته‌شده (قابل‌انتخاب
بودن، اعتبارسنجی توکن، ثبت استاندارد، webhook اختصاصی، پردازش update،
خصوصی/گروه/کانال، دستورات، stop/activate/edit/delete، لاگ خطا) است.

---

## بخش A — قابلیت‌های مشترک پلتفرم (لایهٔ Adapter)

| معیار ممیزی | Telegram | Bale | Eitaa | Soroush | Rubika |
|---|---|---|---|---|---|
| قابل انتخاب در wizard مادر | ✅ legacy | ✅ legacy | ❌ | 🧪 flow بومی | 🧪 flow بومی |
| اعتبارسنجی توکن | ✅ | ✅ | ❌ | 🧪 | 🧪 (regex صحت‌سنجی؛ توکن opaque است) |
| ثبت استاندارد (upsert در `bots`) | ✅ | ✅ | ❌ | 🧪 | 🧪 |
| webhook اختصاصی | ✅ `setWebhook` | ✅ | ❌ | 🧪 `setWebhook` + read-back | 🧪 `updateBotEndpoints` (read-back ⛔) |
| پردازش update روی endpoint فرزند | ✅ | ✅ | ❌ | 📝 (مگر channel-poster 🧪) | 📝 (مگر rubika-diagnostic 🧪 + channel-poster 🧪) |
| چت خصوصی | ✅ | ✅ | ❌ | 📝 | 🧪 (ربات تشخیصی) |
| گروه | ✅ | ✅ | ❌ | 📝 | 🧪 |
| کانال | ✅ | ✅ | ❌ | 📝 | 🧪 (+ رویدادهای BotJoined/Removed/PermissionsChanged) |
| دستورات (commands) | ✅ | ✅ | ❌ | 📝 | 🧪 (`/start` `/id` `/chat` `/forward`) |
| Stop / Activate | 📝 | 📝 | ❌ | 📝 | 📝 (بدون toggle یکپارچه — فقط `MpContact` poll `is_active` می‌کند) |
| Edit (ثبت مجدد با همان توکن) | ✅ upsert | ✅ upsert | ❌ | 🧪 upsert | 🧪 upsert |
| Delete | 📝 دستی (DB/Nova) | 📝 دستی (DB/Nova) | ❌ | 📝 دستی + ⚠️ `CleanEmptyBots` | 📝 دستی + ⚠️ `CleanEmptyBots` |
| لاگ خطا | ✅ `LogHelper` | ✅ `LogHelper` | ❌ | 🧪 `Log::error` با redact | 🧪 `Log::error` با redact + جدول `bot_logs` |

**شواهد (فایل‌ها و خط‌ها):**

- فهرست پلتفرم‌های قابل ثبت:
  [`app/Modules/BotRegistration/Services/BotRegistrationService.php`](../../app/Modules/BotRegistration/Services/BotRegistrationService.php) —
  `getSupportedPlatforms()` = `['telegram','bale','soroush','rubika']` (خط ۲۹) و
  whitelist همان ۴ پلتفرم (خط ۴۱). **Eitaa در این فهرست نیست** → ساختن ربات
  Eitaa از مسیر استاندارد وجود ندارد.
- مسیر ثبت بومی soroush/rubika: `registerNativeClientBot()` (خط‌های ۱۴۷–۲۶۶):
  `getMe` (برای rubika: بدون `bot_id` در پاسخ → خطا) → upsert با ستون
  `rubika_bot_token` / `soroush_bot_token` → `createWebhookUrl` →
  soroush: `setWebhook` + read-back با `getWebhookInfo`؛ rubika:
  `updateEndpoint($url,'ReceiveUpdate')` **بدون read-back → ⛔ BLOCKED** و
  `rubika_webhook_is_set=1` به‌صورت best-effort.
- ساخت URL webhook با ستون توکن اختصاصی هر پلتفرم:
  [`app/Helpers/WebhookEndpointHelper.php`](../../app/Helpers/WebhookEndpointHelper.php)
  `createWebhookUrl()` (خط‌های ۵۹–۱۳۵؛ match توکن در ۷۹–۸۴، پارامتر `origin` در ۱۰۵).
- اعتبارسنجی توکن:
  [`app/Helpers/TokenHelper.php`](../../app/Helpers/TokenHelper.php)
  `isToken()` (خط‌های ۱۰–۳۸): rubika `^[a-zA-Z0-9_-]{8,200}$`
  (توکن opaque؛ صرفا sanity check).
- انتخاب endpoint در flow بومی مادر:
  [`app/Http/Controllers/BotMotherController.php`](../../app/Http/Controllers/BotMotherController.php)
  `handleNativeMotherEndpointSelection()` (خط ۶۲۴) از
  `WebhookEndpointHelper::getAvailableEndpoints()` **بدون فیلتر پلتفرم** استفاده
  می‌کند → همهٔ endpointها برای soroush/rubika قابل انتخاب‌اند.
- حل تکراری نبود read-back و وضعیت فازها:
  [`docs/features/rubika/README.md`](README.md) و
  [`docs/features/messenger-platform-capability-matrix.md`](../messenger-platform-capability-matrix.md).

**موقعیت Eitaa:** Eitaa فقط به‌عنوان **مقصد انتشار** وجود دارد
(`BotHelper::call_eitaa_api` / `sendMessageEitaaSupport` در
[`app/Helpers/BotHelper.php`](../../app/Helpers/BotHelper.php)) — فقط در سطر
`webhook-channel-poster` بخش B ظاهر می‌شود.

---

## بخش B — ماتریس endpointها (۳۳ نوع ربات × ۵ پلتفرم)

منبع فهرست: ۲۴ endpoint از
[`app/Services/WebhookEndpointDefaultImporter.php`](../../app/Services/WebhookEndpointDefaultImporter.php)
`getDefaultEndpoints()` (خط‌های ۵۵–۲۷۸) + ۹ endpoint فقط-seeder
(grep از `database/seeders`). هیچ endpointی حذف یا تغییرنام **نشده** است.

| # | endpoint_id | Telegram | Bale | Eitaa | Soroush | Rubika |
|---|---|---|---|---|---|---|
| 1 | `webhook-personnel-registration` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 2 | `webhook-personnel-admin` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 3 | `webhook-mission-bot` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 4 | `webhook-mission-media` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 5 | `webhook-task-approval` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 6 | `webhook-weather` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 7 | `webhook-quran-word` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 8 | `webhook-quran-ayat` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 9 | `webhook-hadith` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 10 | `webhook-nahj` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 11 | `webhook-blog` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 12 | `webhook-rss` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 13 | `webhook-bot-children` | ✅ | ✅ | ❌ | 📝 ¹ | 📝 ¹ |
| 14 | `webhook-presenter-bot` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 15 | `webhook-rating-bot` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 16 | `webhook-psychology-test` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 17 | `admin-daily-channel` (زیرحالت مادر) | ✅ | ✅ | ❌ | 📝 ¹ | 📝 ¹ |
| 18 | `admin-channel-media-queue` (زیرحالت مادر) | ✅ | ✅ | ❌ | 📝 ¹ | 📝 ¹ |
| 19 | `webhook-list-bot` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 20 | `webhook-mp-contact` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 21 | `webhook-channel-poster` | ✅ | ✅ | 📤 ² | 🧪 ³ | 🧪 ³ |
| 22 | `webhook-rss-admin` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 23 | **`rubika-diagnostic`** (جدید) | ➖ | ➖ | ➖ | ➖ | 🧪 ⁴ |
| 24 | `webhook-growth-companion` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 25 | `get-chat-id` | ✅ | ✅ | ❌ | ❌ ⁵ | ❌ ⁵ |
| 26 | `book-library` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 27 | `book-library-reader` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 28 | `content-submission` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 29 | `admin-bots` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 30 | `poem-bot` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 31 | `prayer-bot` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 32 | `book-pixel` | ✅ | ✅ | ❌ | 📝 | 📝 |
| 33 | `mawkib-finder` | ✅ | ✅ | ❌ | 📝 | 📝 |

**فوترها:**

1. **`requires_token=false` بودن:** `webhook-bot-children`، `admin-daily-channel`
   و `admin-channel-media-queue` در schema `requires_token=false` دارند، اما
   flow بومی soroush/rubika مادر (stateful: endpoint → زبان → توکن) همچنان
   توکن می‌خواهد (edge case شناخته‌شده؛ بدون تأثیر روی legacy telegram/bale).
2. **channel-poster + Eitaa:** فقط **مقصد انتشار**
   (`BotHelper::call_eitaa_api`) — ربات Eitaa به‌عنوان origin وجود ندارد.
3. **channel-poster + Soroush/Rubika:** به‌عنوان **مبدأ (source)** پیاده و
   MOCK_TESTED است (Phase 5،
   [`docs/features/rubika/README.md`](README.md)).
4. **`rubika-diagnostic`:** rubika-only به‌طور طراحی. پوشش کامل:
   `tests/Feature/Modules/BotMother/RubikaDiagnosticBotWebhookTest.php`
   (۳۲ تست: خصوصی/گروه/کانال، فوروارد از کاربر و کانال، BotJoined با/بدون
   صلاحیت، BotRemoved، BotPermissionsChanged، رویداد ناشناخته، dedup،
   عدم‌مداخلهٔ بین‌پلتفرمی، poll command) +
   `tests/Unit/RubikaUpdateNormalizerTest.php` (۲۳ تست). هر دو سبز.
5. **`get-chat-id` + Soroush/Rubika = ❌:** `getIdMother()` در
   `BotMotherController` (خط ۷۵۲) فقط شاخهٔ `bale`/`telegram`/`gap` دارد؛ برای
   soroush/rubika متغیر `$bot` هرگز تنظیم نمی‌شود → خطر خطای
   undefined variable. در flow بومی مادر قابل انتخاب است ولی webhook آن
   `api/webhook-bot-get-id` origin بومی را نمی‌شناسد.

---

## بخش C — شواهد عدم مداخلهٔ بین‌پلتفرمی (آزمایش‌های اجباری)

نیاز «بدون مداخله بین پلتفرم‌ها» با این مکانیزم‌ها و تست‌ها تأیید شده است:

**تست‌ها** (در `tests/Feature/Modules/BotMother/RubikaDiagnosticBotWebhookTest.php`):

| تست | چیزی که ثابت می‌کند |
|---|---|
| `test_foreign_tokens_and_payloads_do_not_interfere` | payload روبیکا با توکن متعلق به ربات **دیگر** پردازش نمی‌شود؛ ربات صاحب توکن هم هیچ پیامی دریافت نمی‌کند |
| `test_non_rubika_payloads_are_acked_without_processing` | payload ساختار telegram/soroush روی webhook rubika فقط `{"ok":true}` می‌گیرد (gate `isRubikaUpdate`) |
| `test_unknown_token_is_acked_without_processing` | توکن ناشناخته → `{"ok":true}` بدون هیچ ارسال پیام و بدون ثبت bot_log |

**مکانیزم‌های جداکنندگی:**

- **ستون توکن اختصاصی هر پلتفرم** در `bots`
  (`telegram_bot_token` / `bale_bot_token` / `soroush_bot_token` /
  `rubika_bot_token`): `resolveBot()` در
  [`app/Http/Controllers/RubikaDiagnosticBotController.php`](../../app/Http/Controllers/RubikaDiagnosticBotController.php)
  فقط با `Bot::where('rubika_bot_token', ...)` (و fallback روی `bot_id`) ربات را
  پیدا می‌کند — توکن telegram/bale هرگز در مسیر روبیکا خوانده نمی‌شود.
- **کلید state با پیشوند origin** در flow مادر: `rubika:<chatId>` /
  `soroush:<chatId>` → دیالوگ‌های ساخت‌ربات دو پلتفرم بر هم اثر نمی‌گذارند.
- **Normalizer سخت‌گیر:** `RubikaUpdateNormalizer::isRubikaUpdate` فقط payloadی
  را قبول می‌کند که `update.type` (یا `type` در سطح بالا) رشتهٔ غیرخالی باشد →
  هر payload telegram/soroush رد و ack-only می‌شود.

---

## بخش D — ریسک‌ها و محدودیت‌های شناخته‌شده

| # | ریسک / محدودیت | مرجع | اثر |
|---|---|---|---|
| 1 | **`CleanEmptyBots` ربات‌های rubika-only / soroush-only را حذف می‌کند** — معیار ۱ رباتی را پاک می‌کند که **هم** `telegram_bot_token` **و هم** `bale_bot_token` نداشته باشد | [`app/Console/Commands/CleanEmptyBots.php`](../../app/Console/Commands/CleanEmptyBots.php) (خط‌های ۴۶–۵۷) | تا رفع این باگ، `clean-empty-bots` را روی محیطی که ربات روبیکا/سروش دارد **اجرا نکنید** |
| 2 | **ack صامت legacy:** controllerهای فرزند قدیمی برای origin غیر از telegram/bale شاخهٔ `else return 200;` دارند | نمونه: [`app/Http/Controllers/QuranWordController.php`](../../app/Http/Controllers/QuranWordController.php) (خط‌های ۲۶۶۳–۲۶۷۲؛ الگوی مشابه در BotQuranAyat، Nahj، RssPostItemTranslation، TestController و الگوی `$type = $request->input('origin')` در Baskهٔ controllerهای legacy) | updateهای soroush/rubika روی endpointهای legacy **بی‌صدا از دست می‌روند** — نه خطا، نه لاگ؛ مبنای وضعیت 📝 در بخش B همین است |
| 3 | **read-back webhook روبیکا ⛔ BLOCKED:** API روبیکا معادل `getWebhookInfo` ندارد | `BotRegistrationService::registerNativeClientBot` (خط‌های ۲۵۱–۲۵۶) | `rubika_webhook_is_set=1` فقط «درخواست ارسال‌شده بدون خطا HTTP≥400» است؛ تأیید نهایی فقط با تست زنده |
| 4 | **flow بومی مادر روی rubika فقط متن (NewMessage):** `extractNativeChatId` فقط `update.type === 'NewMessage'` را می‌شناسد | `BotMotherController` (خط ۴۹۶) | رویدادهای دیگر روی **webhook مادر** ack-only می‌شوند؛ پوشش کامل رویدادها روی **فرزند** `rubika-diagnostic` است |
| 5 | **`get-chat-id` بدون شاخهٔ soroush/rubika** → `$bot` undefined | `BotMotherController::getIdMother` (خط ۷۵۲) | اگر کاربر از flow بومی روی get-chat-id ثبت کند، webhook `api/webhook-bot-get-id` خطا می‌دهد |
| 6 | **توکن برای endpointهای `requires_token=false` در flow بومی** (bot-children، admin-daily-channel، admin-channel-media-queue) | `handleNativeMotherFlow` (stateful endpoint→زبان→توکن) | edge case؛ بدون اثر روی legacy telegram/bale |
| 7 | **بدون toggle یکپارچهٔ stop/activate** برای ربات‌های فرزند در هیچ پلتفرمی | کل کدبیس (فقط `MpContact` poll `is_active` می‌کند) | غیرفعال‌سازی/حذف = دستی (Nova/DB)؛ delete ربات‌های rubika/soroush به‌دلیل ریسک #۱ با احتیاط |

---

## جمع‌بندی

- **۳۳ نوع ربات** ممیزی شد؛ **هیچ‌کدام** حذف یا تغییرنام نشده است.
- **Rubika:** یک نوع (۱ از ۳۳) پوشش کامل پردازش دارد
  (`rubika-diagnostic` — 🧪)، یکی پوشش source دارد
  (`webhook-channel-poster` — 🧪 Phase 5)، بقیه 📝 REG_ONLY هستند:
  قابل ساخت/ثبت/ست‌webhook از flow بومی مادر، ولی پردازش update روی endpoint
  فرزندشان پیاده نیست (ack صامت). این وضعیت صادقانه و مطابق کد واقعی است؛
  ارتقای هر سطر 📝 به 🧪 نیازمند پیاده‌سازی مجزا و تست است.
- **Eitaa:** به‌عنوان origin ربات در هیچ سطر پیاده نیست (فقط مقصد انتشار در
  channel-poster).
- شواهد عدم مداخلهٔ بین‌پلتفرمی: بخش C. ریسک‌های باز: بخش D (مهم‌ترین:
  `CleanEmptyBots`).
