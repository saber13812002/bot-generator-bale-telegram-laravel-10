# امضای کانال‌های Channel Poster — مرجع کامل (۲۰۲۶-۱۱-۱۰)

> سند رسمی رفتار امضا (signature) در ربات «انتشار به کانال» پس از بازطراحی T1–T9
> (پلن: [`docs/superpowers/plans/2026-11-10-channel-poster-signature-fixes.md`](superpowers/plans/2026-11-10-channel-poster-signature-fixes.md)).
> هر قید این سند با تست پوشیده شده؛ فهرست تست‌ها در بخش ۱۰.

---

## ۱. معماری در یک نگاه

| لایه | فایل | وظیفه |
|---|---|---|
| سیاست‌ها | `app/Services/ChannelPoster/SignatureSettingsService.php` | پیش‌فرض‌ها، `normalize()`، انتخاب مقصدها (`resolveSelected`)، فریز انتخاب برای صف، `buildPreview` |
| رندر | `app/Services/ChannelPoster/SignatureRenderer.php` | `render()` تک‌لینک، `renderMulti()` چندلینک، `renderHandle()` — خروجی = متن + parse_mode + button + fallback |
| ساخت امضا | `app/Services/ChannelPosterBotServiceImpl.php` → `buildSignatureForDestination()` | ترکیب سیاست + ترتیب + رندر برای هر مقصد |
| حذف تایپ‌آگه | `deletePureUrlSignature()` / `disableAllSignatures()` | حذف فقط فیلدهای نوع `pure_url` / غیرفعال‌سازی صریح همه |
| ویزاردها | `app/Http/Controllers/ChannelPosterBotController.php` | ویزارد امضای هوشمند (سیاست/چیدمان/جداکننده/اعمال/پیش‌نمایش) + `/deletepurelink` |
| تست پذیرش | `tests/Unit/SignatureScenariosTest.php` | ۱۰ سناریوی پذیرش (F) |

---

## ۲. انواع امضا (`signature_type`) و تفسیر `signature_label` (type-aware)

ستون `signature_label` **چندمعنایی** است؛ تفسیر آن به `signature_type` وابسته است
(`SignatureSettingsService::LABEL_AS_LINK_TYPES`):

| نوع | معنای `signature_label` | خروجی رندر (نمونه) |
|---|---|---|
| `handle` | **ایموجی** — هرگز برچسب لینک نیست | `"\n\n⭐ @sabernews"` (فاصله بین ایموجی و @handle اجباری — T1) |
| `pure_url` | **متن دلخواه پیام** — هرگز برچسب لینک نیست | `"\n\nلینک خبرگزاری ما:\nhttps://bale.ir/c123"` |
| `inline` / `composite` / `null`(legacy) | **برچسب لینک** است | تلگرام: anchor با href؛ بله: markdown؛ بقیه: متن ساده |
| `raw`/`plain`/`button`/`none` | برچسب لینک نیست | — |

**قانون ضد نشت (T2/T3):** در چیدمان چندلینک (`all_channels` و…) برچسب هر لینک **فقط**
برای نوع‌های `inline`/`composite`/legacy از `signature_label` آن مقصد می‌آید؛ در غیر این
صورت برچسب پلتفرم (مثلاً «در بله») استفاده می‌شود. ایموجی handle یا متن pure_url **هرگز**
به لینک‌های دیگر یا پیش‌نمایش نشت نمی‌کند (سناریوهای ۳ و ۴).

**یک امضای فعال بر مقصد — تصمیم مالک (Option A پلن):** ترکیب هم‌زمان سه نوع امضا
(pure_url + handle + smart) روی یک مقصد **حمایت نمی‌شود و لازم نیست**؛ ستون
`signature_type` تکی است (last-writer-wins) و هر ویزارد فقط فیلدهای نوع خودش را می‌زند.
سلسله‌مراتب رندر (هر پست): `signature_enabled=false` → هیچ امضا ←
`signature_settings ≠ null` → موتور هوشمند (type را نادیده می‌گیرد) ← وگرنه legacy بر
اساس `signature_type`. Option B (جدول مستقل + نمایش هم‌زمان) مصوب نشد.

---

## ۳. تنظیمات هوشمند (`signature_settings` JSON)

کلیدها (ترتیب = ترتیب `defaults()`؛ همه با `normalize()` اعتبارسنجی می‌شوند):

| کلید | مقادیر | پیش‌فرض |
|---|---|---|
| `enabled` | bool | `true` |
| `link_selection_policy` | `self_only`، `all_channels`، `exclude_self`، `self_only_plus_eitaa_on_telegram`، `custom`، `random` | `self_only` |
| `layout` | `single_line`، `multi_line` | `single_line` |
| `separator` | تا ۱۶ نویسه | `' | '` |
| `selected_destinations` | int[] (id مقصدها) | `[]` |
| `link_order` | int[] (id مقصدها) — **T9** | `[]` |
| `random_count` | ۱..۵۰ | `2` |
| `application_policy` | `always_apply`، `ask_every_post`، `disabled` | `always_apply` |

`signature_settings = null` → رفتار legacy بدون تغییر (سازگاری معکوس).

### نقشهٔ سناریو ← سیاست/چیدمان

| خواسته کاربر | سیاست | چیدمان | نکته |
|---|---|---|---|
| فقط لینک خودم | `self_only` | single | سؤال چیدمان نمی‌آید (۱ لینک) |
| همهٔ کانال‌ها | `all_channels` | single یا multi | multi فقط وقتی ۲+ لینک باشد |
| همهٔ کانال‌ها جز خودم | `exclude_self` | single یا multi | خود مقصد حذف می‌شود |
| خودم + ایتا (برای مقصد تلگرام) | `self_only_plus_eitaa_on_telegram` | — | خود مقصد **اول** می‌ماند (قرارداد سیاست) |
| انتخاب دستی چند کانال | `custom` + `selected_destinations` | — | **ترتیب انتخاب کاربر حفظ می‌شود** (استثنا) |
| تصادفی | `random` + `random_count` | — | انتخاب **فریز** می‌شود در صف → retry همان امضا |

### اولویت‌های `signature_enabled` (T7/addendum A)

`isSignatureActive($dest)` به این ترتیب تصمیم می‌گیرد:

1. `signature_enabled === false` → **هیچ امضایی** (فلگ سکهٔ کلیدی).
2. تنظیمات هوشمند دارد → `enabled && application_policy !== 'disabled'`.
3. legacy → وجود `channel_link`.

**`signature_enabled=false` فقط از مسیر صریح `disableAllSignatures()` ساخته می‌شود**
(دکمهٔ «غیرفعال‌کردن همه»). `deletePureUrlSignature()` هرگز این فلگ را لمس نمی‌کند —
حذف pure_url نباید امضای handle یا سایر انواع را بکشد (سناریو ۱).

---

## ۴. قرارداد ترتیب پیش‌فرض (T4) + `link_order` (T9)

- **`all_channels`/`exclude_self`:** ترتیب قطعی **ایتا ← بله ← سروش ← تلگرام ← روبیکا**
  (`PLATFORM_PRIORITY`) — مستقل از ترتیب درج در دیتابیس.
- **استثنائات مصوب:** `custom` (ترتیب انتخاب کاربر)، `random` (ترتیب فریزشده)،
  `self_only_plus_eitaa` (خود مقصد اول).
- **`link_order` (T9):** آرایهٔ id مقصدها. اگر پُر باشد، **برتر** از ترتیب پیش‌فرض؛
  idهای غایب (یا مقصدهای حذف‌شده/لینک‌نداشته) **به انتها، به ترتیب priority** اضافه
  می‌شوند. `normalize()` فقط اعداد مثبت، بدون تکرار، با حفظ ترتیب نگه می‌دارد.
  نمونهٔ پذیرش: `all_channels` + `link_order` با سروش اول → در مقصد تلگرام، خط اول = سروش
  (`test_smart_all_channels_link_order_overrides_default_order`).

چیدمان‌ها در رندر چندلینک:
- `multi_line`: هر لینک **دو خط** (خط برچسب + خط لینک) — همسان‌سازی ۵ پلتفرم (T3).
- `single_line`: یک خط، جداکنندهٔ قابل‌تنظیم.
- **dedup:** لینک تکراری (همان url در دو مقصد) یک‌بار چاپ می‌شود (T3، سناریوهای ۵/۶).

---

## ۵. توضیح «بعضی جاها» — parse_mode و رسانه (T5/T6)

| پلتفرم | متن | caption رسانه |
|---|---|---|
| **تلگرام** | `parse_mode=html` با برچسب escape‌شده (`htmlspecialchars`) — T5 | همان `html`، multipart (سناریو ۷/۸) |
| **ایتا (Eitaa)** | **هرگز HTML خام** — فقط متن ساده و URL برهنه؛ `parse_mode` در publisher **زور null** می‌شود | همان (سناریو ۹) |
| بله/سروش/روبیکا | markdown ساده / URL برهنه | بدون parse_mode |

- **retry امن (T5):** فقط «parse error صریح» API تلگرام باعث یک‌بار تکرار **بدون**
  parse_mode می‌شود. خطای شبکه (curl 28)، خطای API ناشناخته، و HTTP 500 **هرگز**
  تکرار نمی‌شوند (سناریو ۱۰ + تست‌های `ChannelPosterMediaPublishTest`).
- **چرا «بعضی جاها»:** امضا به متن اضافه می‌شود **قبل از** انتخاب parse_mode؛ در رسانه
  (عکس/ویدیو/صوت) همان امضا به **caption** می‌رود — یعنی parse_mode رسانه هم باید
  امضا را درک کند. multi_line در caption تلگرام با anchor چندخطی کار می‌کند؛ در ایتا
  چون HTML وجود ندارد، multi_line به‌صورت متن ساده (برچسب: + لینک) چاپ می‌شود.

---

## ۶. راهنمای Eitaa plain (T6 — addendum B)

1. `SignatureRenderer::render*` برای ایتا **هیچ** تگ HTML/parse_mode تولید نمی‌کند.
2. `TelegramChannelPosterPublisher::publishEitaa*` در آخرین لایه، `parseMode` را **null
   اجباری** می‌کند حتی اگر فراخوان `html` بدهد (دفاع در عمق).
3. لینک ایتا مثل سروش/روبیکا **برهنه** (bare URL) است — ایتا خود auto-link می‌کند.
4. caption عکس/ویدیو ایتا **دقیقاً** همان متن سادهٔ چندلینک است (تست‌های
   `ChannelPosterEitaaHardeningTest` + سناریو ۹).
5. API: `sendFile` ایتا همزمان `caption` و `text` می‌پذیرد؛ ما فقط `caption` می‌فرستیم
   (مطابق مستند رسمی `docs/features/OFFICIAL/EitaaYar_API_Documentation.txt`).

---

## ۷. `/deletepurelink` (T7)

ویزارد حذف **فقط** امضای `pure_url` (type-aware):

- انتخاب تگ ← مقصد ← تأیید. برای مقصدهایی که نوعشان `pure_url` نیست، عملیات **no-op**
  است و گزارش می‌شود (مثلاً `changed=false, previous_type=handle`) — handle/inline هرگز
  آسیب نمی‌بیند.
- `deletePureUrlSignature()` فقط فیلدهای `pure_url` را پاک می‌کند؛ `signature_enabled`
  دست‌نخورده می‌ماند.
- **fallback متنی برای سروش/روبیکا** (بدون keyboard): کلمات‌کلیدی فارسی/انگلیسی
  (بازگشت/back/❮، انصراف/cancel، بله/نه) — مطابق `handleDeletePureLinkText`.

---

## ۸. ویزارد امضای هوشمند

مراحل: تگ ← مقصد ← سیاست ← (انتخاب دستی/تعداد تصادفی) ← چیدمان (فقط ۲+ لینک)
← جداکننده (فقط single) ← اعمال (همیشه/هر بار/بدون) ← پیش‌نمایش ← ذخیره.
- callback پیشوند `cp:ssig*:`, بازگشت `cp:ssigbk`؛ در سروش/روبیکا **fallback متنی** کامل.
- پیش‌نمایش از `SignatureSettingsService::buildPreview()` می‌آید — **همان helper**ی که
  publish path با `renderMulti` از آن تفسیر دارد (یک تفسیر، دو مصرف).
- ترتیب دلخواه (T9) فعلاً با ویرایش مستقیم `signature_settings.link_order` (Nova/DB)
  اعمال می‌شود؛ گام UI اختیاری ترتیب (بالا/پایین) در پلن «اختیاری — حذف‌پذیر در review»
  بود و برای این ریلز اجرا **نشده** است (سیستم core + تست کامل‌اند).

---

## ۹. فهرست commitها (scope `cp-signature:`)

| commit | task |
|---|---|
| `553e5a98` + `698067c0` | T1 — فاصلهٔ ایموجی/‌@handle + انتظار تست‌ها |
| `fda06a81` | T2 — type-aware label (بدون نشت) |
| `64de7a3a` | T3 — قرارداد multi-line (دو خطی، dedup، همسان ۵ پلتفرم) |
| `76468b01` | T4 — ترتیب پیش‌فرض پلتفرم‌ها + استثنائات |
| `1a2c021f` | T5 — parse_mode برای caption رسانه + retry امن |
| `e30998e5` | T6 — سخت‌سازی Eitaa (هرگز HTML خام) |
| `4a709f2a` | T7 — `/deletepurelink` type-aware |
| `d1ca421f` | T8 — `SignatureScenariosTest` (۱۰ سناریوی پذیرش) |
| `277c977a` | T9 — `link_order` (ترتیب دلخواه + fallback priority) |
| `4851503c` | T10 — این سند + به‌روزرسانی PROJECT_MASTER_MAP + رگرسیون (852 passed در آن لحظه) |

---

## ۹.۱. ثبت شناسهٔ کانال (CP-CHANNEL-LINK — نوامبر ۲۰۲۶)

امکان ثبت **لینک عمومی** کانال برای استخراج شناسهٔ handle بدون نیاز به recheck دستی:

- **دسترسی:** `/settings` → «🔗 ثبت شناسه کانال» (callback `cp:chlink`) یا دکمهٔ inline در هشدار publish (callback `cp:chlfix:{destId}`).
- **ویزارد:** انتخاب تگ → انتخاب کانال مقصد → وارد کردن لینک → اعتبارسنجی سفت‌گیرانه → تأیید.
- **اعتبارسنجی:** [`ChannelHandleExtractor::extract()`](app/Services/ChannelPoster/ChannelHandleExtractor.php) با `RESERVED_PATHS` denylist (~100 مسیر سیستمی: `join`, `joinc`, `c`, `invite`, `login`, `settings`, `bot`, `channel`, `addstickers`, `share`, …). لینک‌های خصوصی، chat id عددی، و مسیرهای رزرو **رد** می‌شوند.
- **پشتیبانی پلتفرمی:**
  - Bale: `/{username}` (لینک عمومی) + `/{username}/{chatId}/{msgId}` (سه‌بخشی)
  - Rubika: `/{username}` یا `/{username}/{msgId}` (هاست‌ها: `rubika.ir`, `rubika.ru`)
  - Telegram: `/{username}` یا `/{username}/{msgId}`
  - Eitaa / Soroush: `/{username}` یا `/{username}/{msgId}`
- **`/recheck`:** دستورات `cp:chrecheck` و `/recheck` — بررسی مجدد تمام مقاصد و گزارش وضعیت شناسه.

### ⚠️ هشدار خودکار زمان انتشار (CP-SIG-WARNING)

- **تصریف:** هنگام `publishItem()`، اگر `signature_type='handle'` و `signature_enabled=true` باشد ولی `ChannelHandleExtractor::extract()` مقدار معتبر برنگرداند → مقصد به `$missingHandleDests` اضافه می‌شود.
- **رفتار:** پست **بدون امزا** منتشر می‌شود (publish fail نمی‌شود)؛ پس از publish، صاحب ربات پیام خصوصی + دکمهٔ «🔗 ثبت شناسه کانال» دریافت می‌کند.
- **Rate-limit:** ۱۲ ساعت بر مقصد (`Cache::put("cp_sig_handle_warn:{destId}", 1, 12h)`).
- **پیاده‌سازی:** [`ChannelPosterQueuePublishService::publishItem()`](app/Services/ChannelPosterQueuePublishService.php) — بلوک `$missingHandleDests`.
- **lang keys:** `channel_poster_queue_sig_handle_missing` + `channel_poster_queue_sig_handle_missing_btn` در `lang/fa/bot.php`.

---

## ۱۰. پوشش تست

- `tests/Unit/SignatureScenariosTest.php` — **۱۰ سناریوی پذیرش** (F):
  ۱) حذف pure_url، handle زنده می‌ماند؛ ۲) فاصلهٔ ایموجی/‌@ در ۵ پلتفرم؛
  ۳) نشت نداشتن label pure_url؛ ۴) نشت نداشتن ایموجی handle؛ ۵) قرارداد multi-line
  در ۵ پلتفرم؛ ۶) قرارداد single-line + dedup بله؛ ۷) متن تلگرام parse_mode html +
  برچسب escape‌شده؛ ۸) caption رسانهٔ تلگرام multipart + html؛ ۹) ایتا: متن/عکس/ویدیو
  بدون هیچ HTML خام + parse_mode null؛ ۱۰) retry دقیقاً یک‌بار برای parse error + حفظ
  تنظیمات مقصد دیگر.
- واحدی: `SignatureRendererTest`، `SignatureSettingsServiceTest`، `ChannelPosterBotServiceTest`
  (شامل تست‌های T9)، `ChannelHandleExtractorTest`.
- Feature: `ChannelPosterSmartSignatureTest` (ویزارد/پریست/صف)،
  `ChannelPosterEitaaHardeningTest`، `ChannelPosterMediaPublishTest` (retry/رسانه)،
  `ContentSyncFlowTest`.
- **تست‌های ترنزیشن (release-gate 2026-11-11) — ۷ رفتار مصوب در ۱۰ تست واقعی:**
  - `tests/Unit/SignatureStateTransitionTest.php` (۶ تست): pure→handle، handle→smart،
    smart→pure (هرکدام با `assertSame` بایت‌دقیق خروجی پیش/بعد + ایزولیشن مقصد دوم)،
    `disableAllSignatures` روی ۴ نوع، ماتریس `/deletepurelink` روی ۶ نوع،
    ایزولیشن تنظیمات بین مقصدها پس از ترنزیشن‌های ترکیبی.
  - `tests/Feature/SignatureStateTransitionFeatureTest.php` (۴ تست، webhook واقعی
    بله → ویزارد → publish): هر ۳ ترنزیشن با خروجی پابلیش بایت‌دقیق (label/emoji/anchor
    قدیمی نمانده، خروجی مقصد همتگ بی‌تغییر) + ماتریس ویزارد `/deletepurelink`
    (دکمهٔ حذف فقط برای pure_url، cancel بی‌اثر، publish نهایی plain در برابر `🔰 @handle`).

**آخرین رگرسیون کامل:**
- 2026-11-10 (T10، PHP 8.5 محلی): `852 passed / 2 skipped / 0 failed (5037 assertions)`.
- **2026-11-11 (release-gate):** PHP 8.2.12 → `864 passed / 2 skipped / 0 failed (5367 assertions, 138.79s)`؛
  PHP 8.5.5 → `862 passed + 2 deprecated / 2 skipped / 0 failed (5367 assertions, 90.35s)` —
  `2 deprecated` نویز vendor روی PHP 8.5 است، نه شکست.
