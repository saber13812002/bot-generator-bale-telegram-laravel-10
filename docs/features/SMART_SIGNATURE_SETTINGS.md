# تنظیمات هوشمند امضا (Smart Signature Settings) — Channel Poster

**تاریخ:** 2026-10-26 (۱۴۰۵/۰۸/۰۴)
**شعبه:** `15esfand1404`
**مأموریت:** CP-SMART-SIGNATURE-SETTINGS
**پوشش:** تلگرام، بله، ایتا، سروش، روبیکا

---

## ۱. خلاصه

هر مقصد (کانال) به‌طور **مستقل** می‌تواند تنظیمات امضای هوشمند داشته باشد. مدل
تنظیمات سه‌بخشی است:

| بخش | گزینه‌ها | پیش‌فرض |
|---|---|---|
| **A) سیاست انتخاب لینک** (`link_selection_policy`) | `self_only`، `all_channels`، `exclude_self`، `custom`، `random` | `self_only` |
| **B) چیدمان** (`layout`) | `single_line` (با جداکنندهٔ ≤۱۶ نویسه) / `multi_line` | `single_line` (جداکننده ` | `) |
| **C) سیاست اعمال** (`application_policy`) | `always_apply` / `ask_every_post` / `disabled` | `always_apply` |

قواعد کلیدی:

- **تنظیم یک‌بار → بدون سؤال تکراری.** فقط `ask_every_post` سؤال می‌کند.
- **سؤال چیدمان فقط وقتی ممکن است بیش از یک لینک ساخته شود** (SELF_ONLY،
  CUSTOM با یک انتخاب، RANDOM با count=1 سؤال نمی‌گیرند).
- **RANDOM پیش‌فرض نیست**؛ فقط با انتخاب صریح کاربر فعال می‌شود.
- **انتخاب RANDOM در لحظهٔ آماده‌سازی پیام فریز می‌شود** (ستون
  `channel_poster_queue.signature_selection`) تا retry/resend دقیقاً همان
  امضا را تولید کند. هیچ publisher جداگانه‌ای منطق random ندارد.
- **تنظیمات مقصدها مستقل‌اند:** تغییر یک مقصد هرگز امضای مقصد دیگر را
  تغییر نمی‌دهد (مقصد با `signature_settings = NULL` کاملاً legacy می‌ماند).
- **هیچ HTML/Markdown در تنظیمات ذخیره نمی‌شود**؛ فقط دادهٔ معنایی
  (policy/layout/separator/ids). رندر نهایی در زمان انتشار انجام می‌شود.
- **فقط لینک عمومیِ ثبت‌شده** (`channel_link`) استفاده می‌شود؛ هرگز
  token/chat-id/لینک ساختگی.
- **اگر هیچ لینک معتبری باقی نماند، امضای ناقص ساخته نمی‌شود** (خروجی خالی).

---

## ۲. درخت تصمیم (Publish-time)

```
buildSignatureForDestination(dest, frozenSelection?)
│
├─ signature_enabled === false  ───────────────►  امضای خالی (override سخت)
│
├─ signature_settings === NULL ───────────────►  مسیر LEGACY (بدون تغییر):
│                                                buildSignature قدیمی —
│                                                label پلتفرم + linkLine
│                                                (فرمت byte-identical)
│
└─ settings ≠ NULL (SMART)
   ├─ !isSignatureActive (enabled=false یا policy=disabled)
   │                                              ──► امضای خالی
   ├─ scope = resolveByTag(tag)                  (اگر تگ خالی بود → 'all')
   ├─ selected = resolveSelected(dest, scope, frozen)
   │   ├─ random + frozen ≠ null ─► همان idهای فریزشده (ثبات retry)
   │   ├─ self_only    ─► [خود] اگر لینک دارد
   │   ├─ all_channels ─► همهٔ واجدشرایط (لینک‌دار)
   │   ├─ exclude_self ─► واجدشرایط به‌جز خود (خالی = امضای خالی)
   │   ├─ custom       ─► واجدشرایط ∩ selected_destinations (idها)
   │   │                  (اگر خالی شد و خود لینک دارد → [خود])
   │   └─ random       ─► pickRandom(eligible, random_count)   [LCG، seed قطعی]
   ├─ links خالی ─► امضای خالی
   └─ SignatureRenderer::renderMulti(platform, links, layout, separator)
```

### ۲.۱ مصداق‌شدن (eligible)

`eligibleDestinations(scope)` = مقصدهای scope که `channel_link` خالی نیست.
ترتیب بازگشتی scope (اگر `resolveByTag` بدون `orderBy` باشد، در sqlite با
ایندکس `[bot_id, platform, channel_chat_id]` ≈ ترتیب الفبای پلتفرم) حفظ
می‌شود — نه ترتیب انتخاب کاربر.

### ۲.۲ انتخاب RANDOM و ثبات صف

- `computeFrozenSelection(tagDestinations, seed?)` یک‌بار در لحظهٔ
  صف‌بانی (و مسیر فوری) اجرا می‌شود و `dest_id => [dest_id, …]` برمی‌گرداند.
- Seed پیش‌فرض: `random_int(1, 0x7FFFFFFF)` — **محدود به ۳۱ بیت** تا
  ضرب LCG overflow نکند (overflow → تبدیل به float → خطای cast در PHP 8؛
  این باگ production بود و با تست رگرسیون پوشش داده شد).
- seed هر مقصد: `$seed + $dest->id` (تعیینی و تکرارپذیر با seed ثابت).
- در `ChannelPosterQueuePublishService::publishItem` اگر
  `signature_selection` روی آیتم صف ثبت شده باشد **همان** استفاده می‌شود؛
  فقط اگر خالی بود (مثلاً مسیر content-sync) یک‌بار محاسبه و ذخیره می‌شود.

---

## ۳. ویزارد «تنظیمات امضا»

ورود: منوی تنظیمات → «تنظیمات امضا» (`cp:ssig`). کاملاً callback-driven و
هم‌چنین **متن‌محور** (برای سروش/روبیکا که کیبورد inline ندارند، اعداد/کلمات
کافی‌اند — `handleSmartSigText`).

```
cp:ssig
  └─ (اگر >۱ تگ) سؤال تگ ──────────────► cp:ssigt:{tag}
     └─ فهرست کانال‌ها ───────────────►  cp:ssigc:{dest_id}
        └─ سؤال سیاست انتخاب لینک ────► cp:ssigpol:{policy}
           ├─ self_only ────────────────► (چیدمان؟ نه) ─► APPLY
           ├─ all_channels ─────────────► (چیدمان؟ اگر eligible>1)
           ├─ exclude_self ─────────────► (چیدمان؟ اگر eligible>1)
           ├─ custom ───────────────────► cp:ssigck:{dest_id} (چندتایی)
           │                              + «انجام شد» (حداقل ۱ انتخاب،
           │                              وگرنه ssig_custom_empty + باقی‌ماندن)
           ├─ random ───────────────────► سؤال count (۱ تا ۵۰)
           │                              cp:ssigrc:{n} (count=1 → بدون چیدمان)
           └─ [چیدمان] ─────────────────► cp:ssiglay:{single_line|multi_line}
              └─ (single_line) سؤال جداکننده (≤۱۶ نویسه، «پیش‌فرض» مجاز)
                 └─ سؤال سیاست اعمال ─► cp:ssigap:{policy}
                    └─ پیش‌نمایش ──────► cp:ssigpv:yes → ذخیره + ssig_saved
                                        cp:ssigpv:no  → باقی‌ماندن
```

- **بازگشت:** `cp:ssigbk` از هر گام به گام قبلی می‌رود (در CUSTOM_PICK و
  RANDOM_COUNT به POLICY؛ در سایر گام‌ها بر اساس match روی state فعلی).
- **لغو:** `cp:ssigx` → `ssig_cancelled` + پاک‌سازی state (تنظیمات قبلی
  دست‌نخورده می‌ماند).
- **حفظ state:** هر گام `state` + `data` (draft) را نگه می‌دارد؛ ورودی نامعتبر
  همان گام را با پیام خطا تکرار می‌کند (`ssig_invalid_number` /
  `ssig_invalid_count` / `ssig_confirm_invalid`).

### ۳.۱ سؤالات کوتاهِ هر-پست (ASK_EVERY_POST)

وقتی حداقل یک مقصدِ تگ `application_policy = ask_every_post` و لینک دارد،
بعد از ثبت محتوا فقط این سؤال کوتاه می‌آید (نه ویزارد کامل):

```
امضای این مطلب اضافه شود؟
  [بله] [خیر] [تغییر تنظیمات] [دیگر نپرس]
```

| پاسخ | رفتار |
|---|---|
| بله / `cp:ssq:yes` | امضا با تنظیمات دائمی اعمال می‌شود |
| خیر / `cp:ssq:no` | **موقت** — امضای این پست حذف؛ تنظیمات دائمی دست‌نخورده |
| تغییر تنظیمات / `cp:ssq:settings` | ویزارد کامل باز می‌شود؛ بعد از ذخیره، flow پست ادامه می‌یابد |
| دیگر نپرس / `cp:ssq:stop` | همهٔ مقصدهای تگ → `always_apply` (دائمی) |

مسیرهای متن (`بله`/`خیر`/`تنظیمات`/`دیگر نپرس`/`الان`) برای origin‌های
native پشتیبانی می‌شوند؛ ورودی نامعتبر سؤال را با راهنما تکرار می‌کند.

### ۳.۲ DISABLED

`application_policy = disabled` → بدون سؤال، بدون امضا؛ خروجی همیشه خالی.
تنظیمات هوشمند هنوز «smart» می‌مانند (ویرایش‌پذیر از ویزارد) ولی
`signature_enabled` هم `false` نگه‌داری می‌شود تا دو لایه هماهنگ باشند.

---

## ۴. ذخیره‌سازی

### ۴.۱ Migration

`database/migrations/2026_10_26_000001_add_smart_signature_settings.php`

| جدول | ستون | نوع | توضیح |
|---|---|---|---|
| `channel_poster_destinations` | `signature_settings` | JSON nullable | تنظیمات هوشمند (NULL = legacy) |
| `channel_poster_queue` | `signature_selection` | JSON nullable | انتخاب فریزشدهٔ RANDOM (retry-stable) |

آینه‌ها در `tests/UsesChannelPosterSqlite.php` (DDL تست) هم‌راستا شد.

### ۴.۲ شکل JSON `signature_settings`

```json
{
  "enabled": true,
  "link_selection_policy": "custom",
  "layout": "single_line",
  "separator": "–",
  "selected_destinations": [3, 5],
  "random_count": 2,
  "application_policy": "always_apply"
}
```

قواعد `normalize()`:

- `link_selection_policy`/`layout`/`application_policy` خارج از فهرست مجاز →
  پیش‌فرض (هرگز exception نمی‌دهد).
- `separator`: خالی → `' | '`؛ طول > ۱۶ → برش به ۱۶.
- `selected_destinations`: فقط int > 0 (id مقصد، نه نام پلتفرم).
- `random_count`: clamp به `[1, 50]`.
- `enabled`: bool.
- ذخیره: `SignatureSettingsService::saveForDestination` (همچنین
  `signature_enabled` را همگام می‌کند)؛ حذف: `clearForDestination`
  (settings → NULL + `signature_enabled` → NULL → legacy «لینک = فعال»).

### ۴.۳ لایه‌بندی سémantic (اولویت)

```
signature_enabled === false        → همیشه امضای خالی (سخت‌ترین override)
signature_settings ≠ NULL          → موتور SMART (سیاست اعمال حاکم)
signature_settings === NULL        → LEGACY: لینک ذخیره‌شده = امضای قدیمی
```

`signature_enabled = NULL` در legacy یعنی «لینک = فعال» (رفتار قدیمی بدون
تغییر). مقصدها مستقل‌اند — هیچ تگ/تغییری امضای مقصد دیگر را عوض نمی‌کند.

---

## ۵. رندر چندلینکی (`SignatureRenderer::renderMulti`)

| پلتفرم | single_line | multi_line | parse_mode |
|---|---|---|---|
| telegram / eitaa | `<a href="…">label</a>` با جداکننده | `label:\n<a …>…</a>` در هر خط | `html` |
| bale | `[label](url)` با جداکننده (label ناامن → `label: url` + fallback) | `[label](url)` در هر خط | `null` |
| soroush / rubika | `label: url` با جداکننده | `label: url` در هر خط (برچسب تکرار نمی‌شود) | `null` |

- برچسب خالی/نبود → خود URL رندر می‌شود (anchor با متن URL در HTML).
- برچسب‌ها به ۶۴ نویسه برش می‌خورند؛ URL/label در HTML با
  `htmlspecialchars(ENT_QUOTES)` escape می‌شوند.
- لینک‌های با URL خالی حذف می‌شوند؛ اگر همه حذف شوند → خروجی خالی
  (امضای ناقص ساخته نمی‌شود).
- `fallback` فقط وقتی label واقعاً به فرمت ساده «افتاده» باشد (بale ناامن /
  soroush / rubika با label)؛ لینک بدون label فرمت بومی است، نه fallback.
- `button` در حالت multi هرگز ساخته نمی‌شود (چندلینک = فقط متن).

### ۵.۱ Legacy (بدون تغییر)

`signature_settings = NULL` → `SignatureRenderer::render` با همان فرمت‌های
قدیمی (PLAIN/RAW/BUTTON/inline) — تست‌های رگرسیون
`ChannelPosterSignatureManagementTest` + `SignatureRendererTest`
`render_legacy` تضمین‌کننده‌اند.

---

## ۶. محدودیت‌ها و رفتارها (Known limits)

| # | موضوع | رفتار فعلی |
|---|---|---|
| L1 | Caption ۱۰۲۴ | guard موجود (`fitsCaption`) — امضا حذف می‌شود، نه متن اصلی؛ امضای multi در caption هم همان guard را می‌گیرد |
| L2 | Retry | `publishItem` مقصدهای موفقی که `channel_poster_publish_logs.success` دارند را skip می‌کند → بدون ارسال دوتایی و بدون امضای دوتایی |
| L3 | Random + صف | انتخاب فریزشده **فقط** در `signature_selection`؛ publisherها هیچ random جدا ندارند |
| L4 | Token leak | پیش‌نمایش و متن‌های تنظیمات فقط label/لینک عمومی دارند؛ تست `assertNoTokenLeak` هر بار چک می‌کند |
| L5 | Webhook exception | کل handler در try/catch است (HTTP 200 + `status:error` + لاگ)؛ خطای overflow در LCG با seed ۳۱-بیت و تست رگرسیون برطرف شد |
| L6 | ترتیب scope | `resolveByTag` بدون orderBy؛ در sqlite ترتیب الفبای پلتفرم. اگر ریل‌آپ order خاصی لازم شد، `orderBy` اضافه شود |
| L7 | جداکننده | حداکثر ۱۶ نویسه؛ خالی → ` | ` |
| L8 | random_count | ۱ تا ۵۰ (بیش از تعداد واجدشرایط → همه، بدون برش) |
| L9 | سoroush/روبیکا | کل ویزارد با **متن** قابل طی است (اعداد برای کانال/سیاست/چیدمان/تعداد؛ «بله/خیر» برای پیش‌نمایش) |
| L10 | exclude_self خالی | اگر همهٔ لینک‌دارها «خود» باشند → امضای خالی (خودرسان: خودِ مقصد لینکش را در امزای خودش نمی‌بیند مگر self_only) |

---

## ۷. نتایج تست (۲۰۲۶-۱۰-۲۶)

| مجموعه | نتیجه |
|---|---|
| `tests/Feature/ChannelPosterSmartSignatureTest.php` (سناریوهای §10) | ✅ 22 tests / 277 assertions |
| `tests/Unit/SignatureSettingsServiceTest.php` (موتور سیاست + overflow) | ✅ شامل (در مجموع زیر) |
| `tests/Unit/SignatureRendererTest.php` (+ 11 تست renderMulti) | ✅ شامل |
| رگرسیون کامل (۸ Feature + 4 Unit: BotWebhook، MediaPublish، NativeAddWizard، OriginWebhook، QueueSend، RubikaResponseWizard، RubikaTestSend، SignatureManagement، SmartSignature + Renderer، SettingsService، ChannelPosterBotService، RubikaBotClient) | ✅ **271 tests / 1471 assertions / 0 failures** |

سناریوهای پوشش‌شده (مأموریت §10): SELF_ONLY بدون سؤال چیدمان؛
ALL_CHANNELS تک‌خط/چندخط؛ EXCLUDE_SELF (شامل خود → خالی)؛ CUSTOM یک/چند
انتخاب + خالی + back-navigation؛ RANDOM count=1 (بدون چیدمان) و count=3
(با چیدمان)؛ random فریزشده در صف + ثبات retry؛ استقلال مقصدها؛
ASK_EVERY_POST (سؤال کوتاه، بله/خیر موقت/دیگر نپرس دائمی/تنظیمات با
بازگشت به پست/الان/ورودی نامعتبر)؛ DISABLED؛ چندکانالِ یک‌پلتفرم در
custom؛ مقصد بدون URL؛ سازگاری `/add`؛ رگرسیون بدون شکست؛ حفظ رفتار
روبیکا؛ بدون ارسال دوتایی؛ بدون نشت token؛ legacy پس از migration دست‌نخورده.

---

## ۸. فایل‌های تغییریافته

| فایل | نوع | نقش |
|---|---|---|
| `app/Services/ChannelPoster/SignatureSettingsService.php` | **جدید** | موتور سیاست (normalize/resolveSelected/pickRandom/seededShuffle/computeFrozenSelection/layoutQuestionNeeded/buildPreview/save/clear) |
| `app/Services/ChannelPoster/SignatureRenderer.php` | تغییر | `renderMulti` (چندلینک) + ثابت‌های layout + برش label ۶۴ + escape |
| `app/Services/ChannelPosterBotServiceImpl.php` | تغییر | `buildSignatureForDestination` (SMART/LEGACY)، `freezeSignatureSelections`، `saveSmartSignatureSettings`، `enqueue` (+ `signature_selection`) |
| `app/Services/ChannelPosterQueuePublishService.php` | تغییر | publishItem: فریزشده از صف (یا محاسبه+ذخیره)، smart/legacy per-dest |
| `app/Http/Controllers/ChannelPosterBotController.php` | تغییر | 9 state SSIG، ویزارد (callback + text)، سؤال کوتاه هر-پست (callback + text)، ورودی منوی تنظیمات |
| `app/Interfaces/Services/ChannelPosterBotService.php` | تغییر | امضای روش‌های جدید |
| `app/Models/ChannelPosterDestination.php` | تغییر | `signature_settings` در fillable + cast `array` |
| `app/Models/ChannelPosterQueue.php` | تغییر | `signature_selection` در fillable + cast `array` |
| `database/migrations/2026_10_26_000001_add_smart_signature_settings.php` | **جدید** | دو ستون JSON nullable |
| `lang/fa/bot.php`، `lang/en/bot.php` | تغییر | کلیدهای `ssig_*`، `sig_*`، `channel_poster_queued` |
| `tests/Feature/ChannelPosterSmartSignatureTest.php` | **جدید** | 22 سناریوی §10 |
| `tests/Unit/SignatureSettingsServiceTest.php` | **جدید** | موتور سیاست + رگرسیون overflow |
| `tests/Unit/SignatureRendererTest.php` | تغییر | 11 تست renderMulti |
| `tests/UsesChannelPosterSqlite.php` | تغییر | ستون‌های جدید در DDL تست |
