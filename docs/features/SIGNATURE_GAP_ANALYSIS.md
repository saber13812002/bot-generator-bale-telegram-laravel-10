# تحلیل شکاف (Gap Analysis) — مدیریت پیشرفتهٔ امضا در Channel Poster

**تاریخ:** 2026-10-24 (۱۴۰۵/۰۸/۰۳)
**شعبه:** `15esfand1404`
**حالت بررسی:** فقط‌خواندنی (read-only)؛ این سند پیش از هرگونه تغییر کد تهیه شده است.
**مبنای درخواست:** الحاقیهٔ ۱ مأموریت امضا — «ابتدا وضعیت واقعی کد و مستندات را بررسی کن، سپس Gap Analysis تهیه کن و فقط بعد از آن اصلاحات لازم را انجام بده.»

---

## ۱. روش بررسی

تمام فایل‌های مرتبط به‌صورت کامل خوانده شدند:

| لایه | فایل |
|---|---|
| ذخیره‌سازی | `app/Models/ChannelPosterTagSetting.php`، `app/Models/ChannelPosterDestination.php`، مهاکریشن‌های `channel_poster_destinations` (۲۰۲۶_۰۸_۱۶ + add_tag + add_channel_link) و `channel_poster_tag_settings` |
| منطق کسب‌وکار | `app/Services/ChannelPosterBotServiceImpl.php` (`buildSignature`، `setSignatureEnabled`، `updateChannelLink`) |
| فرمت‌دهی | `app/Helpers/ProductLinkMessageHelper.php` (کل فایل) |
| انتشار | `app/Services/TelegramChannelPosterPublisher.php` (شعبه‌های publish)، `app/Services/ChannelPosterQueuePublishService.php` |
| ویزارد | `app/Http/Controllers/ChannelPosterBotController.php` (رابط `/sign`، `askSignatureAndQueue`، `publishPendingToTag`) |
| پیش‌نمایش | `app/Services/TestPostPreviewService.php`، `app/Console/Commands/TestSignature.php`، `app/Console/Commands/TestSignatureLink.php` |
| مستندات | `docs/features/LINKED_SIGNATURE.md`، `docs/features/platform-api-research.md`، `docs/features/messenger-platform-capability-matrix.md`، `docs/features/CHANNEL_POSTER_BOT.md` |

---

## ۲. وضعیت فعلی (با مستندسازی از کد)

| # | اجزاء | موقعیت | رفتار فعلی |
|---|---|---|---|
| ۱ | ذخیرهٔ لینک | ستون `channel_poster_destinations.channel_link` (string 255 nullable، مهاکریشن `2026_09_09_150000`) + آینه در `tests/UsesChannelPosterSqlite.php:85` | فقط URL؛ **بدون** label، نوع امضا، یا پرچم فعال/غیرفعال |
| ۲ | پرچم تگ | `channel_poster_tag_settings.signature_enabled` + مدل `ChannelPosterTagSetting` | **کد مرده**: `setSignatureEnabled` در `ChannelPosterBotServiceImpl.php:456-462` فقط در اینترفیس و ایمپل وجود دارد و **هیچ فراخوانی ندارد** |
| ۳ | ساخت امضا | `ChannelPosterBotServiceImpl::buildSignature` (`:473-524`) | برای هر مقصدی که `channel_link` خالی نیست: `"{label platform}:\n{link}"`؛ label از کلیدهای زبان `bot.channel_poster_sig_*` (نام ثابت پلتفرم)؛ فرمت لینک با `ProductLinkMessageHelper::signatureLink`؛ بازگشت `['text' => "\n\n…", 'parse_mode' => null|'html']` |
| ۴ | فرمت لینک | `ProductLinkMessageHelper::signatureLink` (`:57-67`) | telegram/eitaa → `<a href="url">url</a>` با `htmlspecialchars` روی URL؛ bale → `[url](url)` **بدون escape**؛ soroush/rubika → URL خام |
| ۵ | parse_mode | `ProductLinkMessageHelper::parseModeForPlatform` (`:28-35`) | telegram/eitaa → `'html'`؛ بقیه → `null` |
| ۶ | اعمال هنگام انتشار (فوری) | `ChannelPosterBotController::publishPendingToTag` (`:1295-1318`) | اگر `signature_enabled` (فلگ حالتِ **به‌ازای هر پست**) و متن مقصد موجود باشد → امضا به‌ازای هر مقصد اضافه می‌شود و `parse_mode` از امضا گرفته می‌شود |
| ۷ | اعمال هنگام انتشار (کف) | `ChannelPosterQueuePublishService::publishItem` (`:152-164`) | الگوی دقیقاً مشابه؛ متن کف **پاک** ذخیره می‌شود و امضا هنگام اجرا اضافه می‌شود؛ retry مقصدهای موفق را می‌شمارد (`:133-150`) → **جلوگیری از امضای دوتایی موجود است** |
| ۸ | ویزارد `/sign` | `startSignWizard` (`:1453`) → `handleSignTagCallback` (`:1476`) → `showSignChannels`/`handleSignChannelCallback` (`:1496-1559`) → `handleSignLink` (`:1561-1583`) | تگ ← کانال ← لینک ← ذخیرهٔ فوری با `updateChannelLink` و پایان. **گام label و گام تأیید بله/خیر/بازگشت وجود ندارد** |
| ۹ | دکمه‌های بله/خیر | `askSignatureAndQueue` (`:1192-1229`) | `cp:sig:yes` / `cp:sig:no` (کل‌بک‌ها `:791-799`) فقط اگر حداقل یک مقصد لینک داشته باشد؛ فقط فلگ **موقتیِ** همان پست را تنظیم می‌کند، نه تنظیم ماندگار |
| ۱۰ | پیش‌نمایش | `TestPostPreviewService::sendSignatureLinkPreview` (`:347-421`) | فقط eitaa/bale/telegram؛ soroush/rubika صریحاً رد می‌شوند (`:359-365`)؛ مسیر eitaa فقط‌متن (بدون `reply_markup`) |
| ۱۱ | قابلیت دکمه | `ProductLinkMessageHelper::signatureButtonSupported` (`:129-135`) | telegram/eitaa/bale = `true` — **تناقض**: کد پیش‌نمایش برای eitaa فقط‌متن است و `LINKED_SIGNATURE.md` دکمهٔ eitaa را ⛔ ثبت کرده |
| ۱۲ | کامندها | `app:test-signature` (هر ۵ پلتفرم، فقط متن)، `app:test-signature-link` (۳ پلتفرم، inline + button) | |
| ۱۳ | Retry با parse_mode | `TelegramChannelPosterPublisher.php` — telegram/bale `:360-364`، eitaa `:269-273` | ✅ موجود: در شکستِ پیام با parse_mode، ارسال دوباره **بدون** parse_mode |

---

## ۳. جدول شکاف نسبت به مشخصهٔ الحاقیه

| # | نیازمندی مشخصه | وضعیت فعلی | شکاف |
|---|---|---|---|
| G1 | ذخیرهٔ معنایی: عنوان(label)، URL، نوع، وضعیت فعال | فقط URL به‌ازای هر مقصد | **کم**: `signature_label`، `signature_type`، `signature_enabled` روی مقصدها |
| G2 | دریافت متن نمایشی (label) از کاربر | label همیشه نام ثابت پلتفرم از lang | **کم**: گام label در ویزارد + ذخیره |
| G3 | سؤال «آیا می‌خواهید این لینک به‌عنوان امضای انتهای پست‌ها اضافه شود؟» + بله/خیر/بازگشت | بله/خیر فقط در لحظهٔ انتشار (به‌ازای هر پست) | **کم**: گام تأیید ماندگار در ویزارد (+ متن متناظر برای origin‌های native) |
| G4 | انواع امضا: plain text، raw link، inline link، URL button، composite، none | یک قالب ثابت: خط label + خط لینک | **کم**: ستون type + Renderer برحسب نوع |
| G5 | تنظیمات مستقل به‌ازای هر پلتفرم | فرمت‌دهی per-platform موجود است، اما کنترل مستقل در سطح مقصد نیست | **نیمه**: با ستون‌های جدید کامل می‌شود |
| G6 | ماتریس قابلیت در کد (SUPPORTED/UNVERIFIED/UNSUPPORTED) | فقط در مستندات | **کم**: کلاس `SignatureCapability` |
| G7 | منطق مشترک تست و ارسال واقعی | `buildSignature` مشترک است؛ مسیر preview پیاده‌سازی جداگانه دارد | **نیمه**: preview باید از Renderer استفاده کند |
| G8 | امضای دوتایی در retry نباشد | ✅ موجود (متن پاک در کف + شمارش مقصدهای موفق) | **هیچ** |
| G9 | محدودیت طول (caption 1024) | **هیچ** گاردی نیست — امضا بدون چک به caption رسانه می‌چسبد؛ هم‌چنین مسیر رسانه `parse_mode` نمی‌فرستد (HTML خام در caption رندر می‌شود) | **کم**: guard مناسبی که امضا را در صورت سرریز **حذف** کند (نه متن اصلی را) |
| G10 | ایمنی در برابر injection (HTML/Markdown) | URL در HTML escape شده؛ label کاربری وجود ندارد که escape شود؛ markdown bale URL را escape نمی‌کند | **کم**: escape label کاربری + fallback برای کاراکترهای special در markdown |
| G11 | Fallback پیش‌بینی‌پذیر (لینک ساده) | soroush/rubika از قبل raw؛ منطق fallback صریح نیست | **نیمه**: fallback صریح داخل Renderer |
| G12 | رندر به‌ازای هر مقصد | ✅ موجود | **هیچ** |
| G13 | ۱۰ سناریوی تست اجباری | هیچ تست مستقیمی روی `buildSignature`/رندر امضا نیست | **کم**: کامل |
| G14 | Retry بدون parse_mode برای درخواست‌های مبهم | ✅ موجود (telegram/bale/eitaa) | **هیچ** |

---

## ۴. تناقضات سند ↔ کد

| # | تناقض | جزئیات | تصمیم |
|---|---|---|---|
| D1 | parse_mode سروش | `docs/features/platform-api-research.md` §1.3 (سند رسمی SoroushPlus، decode‌شده در 2026-10-19) پشتیبانی `parse_mode` (MarkdownV2/HTML/legacy Markdown) + `reply_markup` را برای `sendMessage` **مستند** کرده، در حالی که کامنت `SoroushBotClient` (~`app/Services/SoroushBotClient.php:139-141`) می‌گوید «parse_mode مستند نیست (BLOCKED)» و آن را از payload می‌پالاید؛ `LINKED_SIGNATURE.md` هم ⛔ ثبت کرده | سند رسمی معتبرتر است → وضعیت: **OFFICIAL_CONFIRMED (doc) / live-unverified**. رفتار فعلی (حذف parse_mode + لینک خام) حفظ می‌شود چون تأیید زنده نداریم |
| D2 | دکمهٔ URL در Eitaa | `signatureButtonSupported('eitaa') === true` درحالی‌که `sendSignatureLinkPreview` مسیرeitaa را فقط‌متن پیاده‌سازی کرده و مستندات ⛔ دارند | **باگ**: مقدار باید `false` باشد |
| D3 | لینک inline در روبیکا | مستندات رسمی روبیکا (ذخیرهٔ محلی [`rubika/sources/rubika_methods.html`](rubika/sources/rubika_methods.html)) پارامتر `parse_mode` را برای `sendMessage` **ندارد** → inline = **OFFICIAL_DOC_NOT_FOUND**؛ فقط URL خام امن است | رفتار فعلی (raw) صحیح است؛ در ماتریس با وضعیت OFFICIAL_DOC_NOT_FOUND ثبت می‌شود |
| D4 | کد مرده | `setSignatureEnabled` + جدول `channel_poster_tag_settings` بدون هیچ فراخوانی | با پیاده‌سازی، `signature_enabled` در سطح **مقصد** پیاده می‌شود؛ جدول تگ دست‌نخورده باقی می‌ماند (سازگاری) |
| D5 | پوشش پیش‌نمایش | `sendSignatureLinkPreview` فقط ۳ پلتفرم | برای soroush/rubika فقط روش raw-link از Renderer مشترک ارسال می‌شود (نه inline/button) |

---

## ۵. طرح اصلاحات (حداقل‌مüdخله، بدون شکستن قرارداد موجود)

1. **مهاکریشن**: افزودن `signature_label` (string 255 nullable)، `signature_type` (string default `inline`)، `signature_enabled` (boolean default false) به `channel_poster_destinations` + آینه در `tests/UsesChannelPosterSqlite.php` + `$fillable` مدل.
2. **`app/Services/ChannelPoster/SignatureCapability.php`**: ماتریس per-platform با enum‌های استاندارد (OFFICIAL_CONFIRMED / COMMUNITY_DOCUMENTED / MOCK_TESTED / LIVE_API_ACCEPTED / CLIENT_RENDER_VERIFIED / UNVERIFIED / UNSUPPORTED / OFFICIAL_DOC_NOT_FOUND) برای: inline link، URL button، parse_mode html، markdown، caption link.
3. **`app/Services/ChannelPoster/SignatureRenderer.php`**: دریافت داده‌های معنایی (label، url، type، platform) → خروجی `['text' => string, 'parse_mode' => ?string, 'fallback' => bool]`؛ escape‌های اختصاصی هر فرمت؛ fallback امن به `label\nurl` وقتی فرمت تأییدشده نباشد یا کاراکترهای special markdown داشته باشد؛ helper `fits(baseLen, sigLen, limit=1024)` برای caption.
4. **`buildSignature`**: بازتعریف روی Renderer؛ **قرارداد خروجی دقیقاً همان** `['text' => "\n\n…", 'parse_mode' => null|'html']` تا هیچ فراخوانی‌کننده‌ای تغییر نکند. label کاربری (در صورت وجود) به‌جای نام ثابت پلتفرم؛ اگر `signature_enabled` مقصد false یا `signature_type === 'none'` → بدون امضا.
5. **Guard طول caption** در هر دو نقطهٔ اعمال (کنترلر `:1295-1318` و کف `:152-164`): اگر `base + signature > 1024` → امضا حذف + `Log::warning`.
6. **ویزارد `/sign`**: پس از ذخیرهٔ لینک → سؤال label (`STATE_AWAITING_SIGN_LABEL`) → سؤال تأیید (`STATE_AWAITING_SIGN_CONFIRM`) با کل‌بک‌های `cp:signconf:yes|no|back`؛ بله → `enabled=true` + type/label؛ خیر → `enabled=false`؛ بازگشت → تکرار گام label. کلیدهای زبان fa/en.
7. **رفع باگ D2**: `signatureButtonSupported('eitaa')` → `false`.
8. **تست‌ها**: `tests/Unit/SignatureRendererTest.php` + `tests/Feature/ChannelPosterSignatureManagementTest.php` (۱۰ سناریوی اجباری، mock-only) + اجرای regression روی سوت‌های Channel Poster.
9. **مستندات**: `docs/features/PLATFORM_LINK_FORMAT_COMPATIBILITY.md` (ماتریس ۱۱ سطر × ۵ پلتفرم + enum + منابع)، به‌روزرسانی `LINKED_SIGNATURE.md` و `CHANNEL_POSTER_BOT.md` و README، جابه‌جایی `docs_tmp_rubika_*` به `docs/features/rubika/sources/`.

## ۶. سناریوهای ۱۰گانهٔ اجباری (طرح تست)

| # | سناریو | انتظار |
|---|---|---|
| 1 | `signature_enabled = false` | هیچ امضایی به متن اضافه نشود |
| 2 | telegram + inline + URL با `&` و `"` | `<a href>` با escape صحیح، `parse_mode = html` |
| 3 | label کاربری حاوی `<script>` / `<b>` | در خروجی HTML کاملاً escape شود (هیچ tag باز نشود) |
| 4 | soroush + هر type | خروجی = URL خام (بدون syntax markdown/HTML) |
| 5 | rubika + هر type | خروجی = URL خام (OFFICIAL_DOC_NOT_FOUND → raw) |
| 6 | چند مقصد (telegram + rubika) | هر مقصد با فرمت مستقلِ پلتفرم خودش |
| 7 | caption رسانه > 1024 با احتساب امضا | امضا حذف شود + warning log؛ متن اصلی دست‌نخورده |
| 8 | retry کف با موفقیتِ جزئی | مقصدهای قبلاً موفق بدون امزای مجدد؛ مقصدهای شکست‌خورده یک‌بار امضا بگیرند |
| 9 | bale + label حاوی `[` یا `](` | fallback به فرمت ساده (خط label + خط URL خام) |
| 10 | جریان کامل ویزارد: لینک ← label ← بله / خیر / بازگشت | ذخیرهٔ درست (label+type+enabled) و state پاک |

## ۷. محدودیت‌های ثبت‌شده (برای گزارش نهایی)

- **هیچ تست زنده‌ای** اجرا نشده است؛ همهٔ نتایج Mock-based هستند.
- مقدار `http_status => 200` در شاخهٔ «بدون message_id» روبیکا یک **تقریب مستندشده** است (کلیانت وضعیت HTTP را بازتولید نمی‌کند؛ هر ≥400 همیشه exception می‌دهد).
- فرمت error envelope روبیکا **OFFICIAL_DOC_NOT_FOUND** است؛ تشخیص فعلی فقط بر پایهٔ shape پاسخ 2xx استوار است.
- قابلیت `inline_keypad`/`chat_keypad` روبیکا برای دکمهٔ URL بررسی‌نشده است و در ماتریس UNVERIFIED ثبت می‌شود.
