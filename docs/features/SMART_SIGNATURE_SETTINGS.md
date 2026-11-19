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
| **A) سیاست انتخاب لینک** (`link_selection_policy`) | `self_only`، `all_channels`، `exclude_self`، `self_only_plus_eitaa_on_telegram`، `custom`، `random` | `self_only` |
| **B) چیدمان** (`layout`) | `single_line` (با جداکنندهٔ ≤۱۶ نویسه) / `multi_line` | `single_line` (جداکننده ` | `) |
| **C) سیاست اعمال** (`application_policy`) | `always_apply` / `ask_every_post` / `disabled` | `always_apply` |

قواعد کلیدی:

- **تنظیم یک‌بار → بدون سؤال تکراری.** فقط `ask_every_post` سؤال می‌کند.
- **سؤال چیدمان فقط وقتی ممکن است بیش از یک لینک ساخته شود** (SELF_ONLY،
  CUSTOM با یک انتخاب، RANDOM با count=1 سؤال نمی‌گیرند).
- **پیش‌فرض ۴ — تلگرام + ایتا** (`self_only_plus_eitaa_on_telegram`): فقط
  مقاصد تلگرام ایتا/لینک خود تلگرام را اضافه می‌کنند (سایر پلتفرم‌ها =
  `self_only`). قطعی (بدون random/فریز) و deterministic — برای مقصد
  تلگرام بدون لینک، لینک ایتا «ساختگی» نمی‌شود (خروجی خالی).
- **RANDOM پیش‌فرض نیست**؛ فقط با انتخاب صریح کاربر فعال می‌شود.
- **انتخاب RANDOM در لحظهٔ آماده‌سازی پیام فریز می‌شود** (ستون
  `channel_poster_queue.signature_selection`) تا retry/resend دقیقاً همان
  امضا را تولید کند. هیچ publisher جداگانه‌ای منطق random ندارد.
- **تنظیمات مقصدها مستقل‌اند:** تغییر یک مقصد هرگز امضای مقصد دیگر را
  تغییر نمی‌دهد (مقصد با `signature_settings = NULL` کاملاً legacy می‌ماند).
- **هیچ HTML/Markdown در تنظیمات ذخیره نمی‌شود**؛ فقط دادهٔ معنایی
  (policy/layout/separator/ids). رندر نهایی در زمان انتشار انجام می‌شود.

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
   │   ├─ self_only_plus_eitaa_on_telegram (پیش‌فرض ۴):
   │   │      ├─ مقصد تلگرام  ─► [خود(اگر لینک دارد)] + همهٔ ایتای واجدشرایط
   │   │      │                    (ترتیب واجدشرایط؛ ایتا بدون لینک حذف)
   │   │      └─ مقصد غیرتلگرام ─► [خود(اگر لینک دارد)] = همان self_only
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
           ├─ self_only_plus_eitaa_on_telegram (پیش‌فرض ۴)
           │                             ► (چیدمان؟ فقط تلگرام و >۱ لینک)
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
- **لغو:** `cp:ssigcancel` → `ssig_cancelled` + پاک‌سازی state (تنظیمات قبلی
  دست‌نخورده می‌ماند).
- **حفظ state:** هر گام `state` + `data` (draft) را نگه می‌دارد؛ ورودی نامعتبر
  همان گام را با پیام خطا تکرار می‌کند (`ssig_invalid_number` /
  `ssig_invalid_count` / `ssig_confirm_invalid`).
- **منو سیاست:** ۴ پیش‌فرض (شماره‌های ۱–۵) + «تلگرام + ایتا» (شمارهٔ ۶،
  فقط origin‌های native) + `custom` + `random` — دکمه‌ها برای همهٔ origin‌ها.

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
| L11 | پیش‌فرض ۴ — سؤalachیدمان | فقط برای مقصد تلگرام با >۱ لینک نهایی (خود تلگرام + حداقل یک ایتای لینک‌دار)؛ سایر پلتفرم‌ها هرگز سؤال نمی‌گیرند |
| L12 | پیش‌فرض ۴ — deterministic | بدون random/فریز؛ retry همان ترکیب را می‌سازد. مقصد تلگرام بدون لینک: ایتا «ساختگی» نمی‌شود → امضای خالی |
| L13 | سیاست اعمال مؤثر | سؤال کوتاه هر-پست (و `cp:ssigp:*`) با سیاست **اولین** مقصدِ هوشمندِ تگ (ترتیب بازگشت scope) تعیین می‌شود؛ برای رفتار یکنواخت همهٔ مقصدهای تگ را روی یک سیاست اعمال تنظیم کنید |

---

## ۷. نتایج تست (۲۰۲۶-۱۰-۲۶)

| مجموعه | نتیجه |
|---|---|
| `tests/Feature/ChannelPosterSmartSignatureTest.php` (سناریوهای §10 + Addendum §A) | ✅ 33 tests / 412 assertions |
| `tests/Unit/SignatureSettingsServiceTest.php` (موتور سیاست + overflow + preset4) | ✅ 31 tests / 116 assertions |
| `tests/Unit/SignatureRendererTest.php` (+ 11 تست renderMulti) | ✅ شامل |
| رگرسیون کامل (۹ Feature + 4 Unit: BotWebhook، MediaPublish، NativeAddWizard، OriginWebhook، QueueSend، RubikaResponseWizard، RubikaTestSend، SignatureManagement، SmartSignature + Renderer، SettingsService، ChannelPosterBotService) | ✅ **233 tests / 1467 assertions / 0 failures** (۲۰۲۶-۱۰-۲۷) |

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

---

## ۹. Addendum (۲۰۲۶-۱۰-۲۷): پیش‌فرض ۴ — تلگرام + ایتا
(`self_only_plus_eitaa_on_telegram`)

**مأموریت:** CP-SMART-SIGNATURE-PRESET4-ADDENDUM
**شعبه:** `15esfand1404`

### ۹.۱ تعریف و قواعد

سیاست جدیدِ انتخاب لینک — چهارم پیش‌فرض — که فقط روی **مقاصد تلگرام** اثر
می‌گذارد:

- **مقصد تلگرام:** `[خود(اگر `channel_link` دارد)] + همهٔ ایتاهای
  واجدشرایطِ تگ (ترتیب بازگشت scope = لگ/insertion؛ ایتا بدون لینک حذف).
- **مقاصد غیرتلگرام** (bale/eitaa/soroush/rubika): دقیقاً `self_only`.
- **قطعی** (بدون random → بدون freeze/`signature_selection` → ثبات retry
  رایگان).
- **قانون کلیدی:** اگر خودِ تلگرام لینک ندارد، **هیچ لینکی ساخته
  نمی‌شود** (لینک ایتا «ساختگی» نمی‌شود) → امضای خالی.
- `layout`/`separator` ذخیره‌شده بر رندر چندلینکی حاکم‌اند (مثل سایر
  سیاست‌ها).

### ۹.۲ ویزارد

- منوی سیاست: دکمهٔ ۵م — `channel_poster_ssig_policy_self_plus_eitaa` —
  و در origin‌های native (سروش/روبیکا) شمارهٔ **۶** در متن راهنما
  (`...ssig_text_hint_numbers_6`). شماره‌های ۱–۵ تغییر نکرده‌اند.
- سؤال چیدمان فقط وقتی خروجی ممکن است چندلینک باشد (تلگرام + >۱ لینک).
- باقی ویزارد (جداکننده/اعمال/پیش‌نمایش/ذخیره) بدون تغییر.

### ۹.۳ مثال‌های رندر

| مقصد | تگ | خروجی (single_line ` | `) |
|---|---|---|
| telegram (لینک دارد) + aitaa (لینک دارد) | — | `<a href="https://t.me/ch">در تلگرام</a> | <a href="https://eitaa.ir/ch">در ایتا</a>` |
| telegram (لینک دارد) + aitaa (بدون لینک) | — | فقط لینک خود تلگرام |
| telegram (بدون لینک) + aitaa (لینک دارد) | — | **امضای خالی** (ساختگی نمی‌شود) |
| bale / soroush / rubika | — | دقیقاً `self_only` |

### ۹.۴ نتایج (۲۰۲۶-۱۰-۲۷)

- `SignatureSettingsServiceTest`: ✅ 31 tests / 116 assertions (شامل ۳ تست
  resolveSelected preset4 + 1 تست layoutQuestionNeeded + 1 تست normalize +
  1 تست preview).
- `ChannelPosterSmartSignatureTest`: ✅ 33 tests / 412 assertions (۱۱ سناریوی
  جدید §12: P1–P11).
- رگرسیون کامل: ✅ 233 tests / 1467 assertions / 0 failures.

### ۹.۵ فایل‌های تغییریافتهٔ Addendum

| فایل | نوع | نقش |
|---|---|---|
| `app/Services/ChannelPoster/SignatureSettingsService.php` | تغییر | ثابت `POLICY_SELF_ONLY_PLUS_EITAA_ON_TELEGRAM`، `policies()`، `resolveSelected()` (شاخهٔ جدید)، `layoutQuestionNeeded()`، `buildPreview()` |
| `app/Http/Controllers/ChannelPosterBotController.php` | تغییر | دکمهٔ ۵م در `showSmartSigPolicy`، شمارهٔ ۶ در `handleSmartSigText`، کلید راهنمای جدید |
| `lang/fa/bot.php`، `lang/en/bot.php` | تغییر | `channel_poster_ssig_policy_self_plus_eitaa` + `..._ssig_text_hint_numbers_6` |
| `tests/Unit/SignatureSettingsServiceTest.php` | تغییر | ۵ تست جدید preset4 |
| `tests/Feature/ChannelPosterSmartSignatureTest.php` | تغییر | ۱۱ سناریوی جدید (P1–P11) + `assertState(origin?)` + `createDestination(chatId?)` |
| `docs/features/SMART_SIGNATURE_SETTINGS.md` | تغییر | این بخش + به‌روزرسانی جدول‌ها |
