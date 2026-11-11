# Channel Poster Signatures — Fix & `/deletepurelink` Plan (v2 با addendum A–G)

**Date:** 2026-11-10
**Status:** ✅ **اجراشده (T1–T10 — commitها `553e5a98`…`4851503c`) — رکورد تاریخی اجرا.** تصمیمات مصوب: Option A (یک امضای فعال بر مقصد — بدون Option B/migration) و عدم نیاز به UI ترتیب لینک (`link_order` فقط از تنظیمات JSON اعمال می‌شود). ادامهٔ پوشش: ۱۰ تست ترنزیشن release-gate در `tests/Unit/SignatureStateTransitionTest.php` + `tests/Feature/SignatureStateTransitionFeatureTest.php` (2026-11-11) و مرجع نهایی در `docs/CHANNEL_POSTER_SIGNATURES.md`.
**Branch:** `15esfand1404`
**Module:** Channel Poster (ربات post-to-channel) — امضاها (signature) + Content Sync

---

## Goal

رفع باگ‌ها و کاستی‌ها با رعایت دقیق addendum:

1. **فاصلهٔ ایموجی و @handle** — `🔰@handle` → `🔰 @handle`.
2. **`/deletepurelink`** — نوع‌آگاه (type-aware): فقط امضای pure_url را حذف کند و **به هیچ‌وجه** handle یا تنظیمات هوشمند را غیرفعال/حذف نکند.
3. **نشت `signature_label`** — label فقط بر اساس `signature_type` واقعی تفسیر شود؛ emoji و pure-URL text هرگز به‌عنوان label لینک‌های دیگر استفاده نشوند (در همه‌جا: publish، preview ویزارد، display لیست کانال‌ها).
4. **«بعضی جاها نه»** — (الف) کپشن رسانهٔ تلگرام `parse_mode` نمی‌گیرد؛ (ب) `multi_line` در non-HTML یک‌خطه بود درحالی‌که قرارداد دوخطهٔ `برچسب:/لینک` است.
5. **قرارداد multi-line مشخص** (D): هر مقصد یک‌بار، هر ورودی در خط مستقل، label و url در خطوط مشخص، ترتیب پیش‌فرض Eitaa→Bale→Soroush→Telegram→Rubika، خروجی معنایی یکسان در ۵ پلتفرم.
6. **Retry امن** (E): فقط روی خطای صریح parse؛ نه timeout/شبکه/ناشناخته.
7. **Eitaa مستقل و plain** (B): هیچ HTML خام در متن/کپشن ایتا؛ تست text/photo/video/caption.

## Context (یافته‌های دابل‌چک)

- زنجیرهٔ اولویت (تأییدشده در `ChannelPosterQueuePublishService::publishItem` و `publishPendingToTag`):
  `signature_enabled === false` → امضای صفر؛ `signature_settings !== null` → موتور هوشمند (`buildSignatureForDestination`، `signature_type` را نادیده می‌گیرد)؛ وگرنه مسیر legacy بر پایهٔ `signature_type`.
- `signature_label` چندمعنایی است (label inline / متن pure_url / ایموجی handle). **دو نقطهٔ نشت:**
  - [`buildSignatureForDestination()`](app/Services/ChannelPosterBotServiceImpl.php:700) — label بدون بررسی type.
  - [`SignatureSettingsService::buildPreview()`](app/Services/ChannelPoster/SignatureSettingsService.php:519) — پیش‌نمایش ویزارد هم بدون بررسی type label می‌گیرد (نشت در UI).
- [`SignatureRenderer::renderHandle()`](app/Services/ChannelPoster/SignatureRenderer.php:385): `$emoji . '@' . $handle` بدون فاصله.
- [`SignatureRenderer::renderMulti()`](app/Services/ChannelPoster/SignatureRenderer.php:280): multi_line در non-HTML = `label: url` (یک خط) درحالی‌که [`render()`](app/Services/ChannelPoster/SignatureRenderer.php:198) در همان پلتفرم‌ها `label:\nurl` (دو خط) است.
- ترتیب فعلی لینک‌های multi = ترتیب درج مقصدها (eligible) — ترتیب پیش‌فرض تعریف‌شده‌ای وجود ندارد.
- [`ChannelPosterMediaSender::send()`](app/Services/ChannelPosterMediaSender.php:36): پارامتر `parseMode` ندارد؛ کپشن رسانهٔ تلگرام HTML را خام نمایش می‌دهد.
- [`TelegramChannelPosterPublisher::publish()`](app/Services/TelegramChannelPosterPublisher.php:514): retry فعلی **برای هر** خطا انجام می‌شود (`!isOk` — شامل timeout/شبکه/نامشخص) — خلاف addendum E.
- [`publish()`](app/Services/TelegramChannelPosterPublisher.php:393): `$parseMode` را به `sendMessageEitaaSupport` می‌دهد، هرچند API رسمی ایتایار parse_mode ندارد (RCA). رندرر برای ایتا همیشه parse_mode=null می‌دهد، ولی لایهٔ publisher باید defensively null بکند.
- سناریوهای کاربری که بدون تغییر کد، با سیاست‌های موجود پوشش می‌شوند (فقط مستندسازی در T10): `self_only` (فقط لینک ایتا در ایتا/تلگرام در تلگرام)، `all_channels`/`custom` (لینک مستقیم بله در تلگرام)، Content Sync (پست با لینک ایتا → `post_url` برای هر مقصد + suffix منبع؛ حالت ویژهٔ eitaa→eitaa «لینک این پست در کانال اصلی:»).
- «لینک سروش اول در تلگرام»: کنترل ترتیب وجود ندارد → T9 اختیاری (`link_order`) که روی ترتیب پیش‌فرضِ T4 سوار می‌شود.

## Architecture Decision (addendum A — باید قبل از اجرا تأیید شود)

**سوال:** آیا معماری فعلی می‌تواند pure_url + handle (+ smart) را روی یک مقصد **هم‌زمان** نگه دارد؟

**تحلیل schema فعلی:** `ChannelPosterDestination` یک ستون `signature_type` دارد ⇒ هر مقصد حداکثر **یک** امضای فعال legacy. pure_url و handle روی یک مقصد هم‌زمان **غیرممکن** است (last-writer-wins — که در پیام‌های ذخیرهٔ ویزاردها صریح‌شده: «این امضا مستقل از تنظیمات هوشمند است؛ اگر تنظیمات هوشمندی بود پاک شد»).

**Option A (پیشنهادی — بدون migration، بدون تغییر دادهٔ مشتریان):**
- قرارداد: یک مقصد = حداکثر یک امضای فعال. «استقلال» به این معنا که عملیات هر امضا **نوع‌آگاه** است و دادهٔ نوع دیگری را نمی‌زند:
  - `/deletepurelink` روی مقصدی که نوع فعلی‌اش `pure_url` است → `signature_type=none` + `signature_label=null`؛ **`signature_enabled` و `signature_settings` دست نمی‌خورند** (smart اگر بود باقی می‌ماند و چون type=none، مسیر legacy هیچ‌چیز رندر نمی‌کند؛ مسیر smart هم settings خودش را دارد).
  - `/deletepurelink` روی مقصدی که نوع فعلی‌اش `handle` (یا هر غیر-pure_url) است → **no-op** با گزارش «این کانال purelink ندارد (نوع فعلی: …)» — یعنی غیرفعال‌سازی pure_url هرگز handle را خاموش نمی‌کند (چون اگر handle فعال است، pure_url دیگر در مقصد نیست).
  - `signature_enabled=false` فقط در عملیات صریح «غیرفعال‌سازی کامل همهٔ امضاها» استفاده می‌شود (گزینهٔ جدا در تأیید ویزارد: `signature_enabled=false` + `type=none` + `settings=null` + `label=null`).
  - پاک‌سازی type-aware: هیچ دستور/ویزاردی `signature_settings` را پاک نمی‌کند مگر اینکه خودِ آن ویزارد روی همان نوع نوشته باشد (رفتار فعلی pure/handle-wizard که settings را null می‌کند **ماندنی** است — برای اینکه type جدید حاکم شود؛ و در پیام ذخیره صریح‌شده).
- **مigration: نه. تغییر داده: نه.** هیچ رکورد موجود بدون اقدام صریح کاربر دست نمی‌خورد.

**Option B (coexistence واقعی — با migration):** جدول جداگانه `channel_poster_signatures` (destination_id, kind[pure_url|handle], enabled, label) + ترکیب چند امضا در رندر (بلوک pure_url + خط handle). هزینه: migration + بازنویسی مسیر publish + ویزاردها + cleanup دادهٔ legacy.
- **در این چرخه انجام نمی‌شود** مگر اینکه کاربر Option B را انتخاب کند.

**تأیید لازم:** پیش‌فرض plan = **Option A**. اگر کاربر بخواهد pure_url و handle واقعاً روی یک کانال هم‌زمان نمایش بخورند، باید Option B را تأیید کند (plan بازطراحی می‌شود).

## Global Constraints

- DB semantic، render at publish — فرمت ذخیره‌شده دست نمی‌خورد.
- **هیچ تنظیمات موجود مشتریان بدون بررسی/تأیید تغییر نمی‌کند** — بدون data migration؛ عملیات فقط از ویزاردهای صریح.
- **Eitaa:** هرگز HTML/parse_mode تولید یا ارسال نشود (متن و کپشن، هر دو مسیر متن/رسانه).
- **Retry:** فقط خطای صریح و قابل‌تشخیص parse؛ timeout/شبکه/نامشخص ⇒ بدون retry (جلوگیری از ارسال تکراری).
- کپشن ۱۰۲۴: امضا حذف می‌شود نه متن اصلی (`fitsCaption` دست نمی‌خورد).
- test-first: هر task ابتدا تست شکسته (red) → fix → سبز → commit (scope `cp-signature:`).
- زبان: fa منبع اصلی؛ سایر زبان‌ها اختیاری.
- در پایان: رگرسیون کامل + گزارش وضعیت Local / GitHub / Production جداگانه.

---

## Tasks

### Task 1 — فاصلهٔ ایموجی و @handle

- [ ] تست: در [`tests/Unit/SignatureRendererTest.php`](tests/Unit/SignatureRendererTest.php:612) همهٔ assertionهای handle (حدود 612–640) از `"\n\n{emoji}@{handle}"` به `"\n\n{emoji} @{handle}"` (به‌ویژه `test_handle_custom_emoji_and_at_prefix_stripping`: `"\n\n⭐@saberblog"` → `"\n\n⭐ @saberblog"`).
- [ ] `php artisan test --filter=SignatureRendererTest` → red ثبت شود.
- [ ] fix [`renderHandle()`](app/Services/ChannelPoster/SignatureRenderer.php:385): `$emoji . ' @' . $handle` + docblock (خط 357).
- [ ] fix پیش‌نمایش ویزارد [`saveHandleSignature()`](app/Http/Controllers/ChannelPosterBotController.php:2747): همان فاصله (پیش‌نمایش = دقیقاً خروجی واقعی).
- [ ] سبز + commit. (پوشش acceptance scenario 2)

### Task 2 — تفسیر نوع‌آگاهِ `signature_label` (addendum C)

- [ ] helper مشترک جدید در `SignatureSettingsService`:
  ```php
  public static function labelForSignature(ChannelPosterDestination $d, array $platformLabels): string
  ```
  قواعد: فقط وقتی `signature_type` ∈ {`inline`, `composite`} یا خالی/null (legacy) است، `signature_label` به‌عنوان label لینک استفاده می‌شود؛ `pure_url`/`handle`/`raw`/`plain`/`button`/`none` ⇒ برچسب پلتفرم (سازگاری داده‌های قدیمی: type خالی + label = inline legacy).
- [ ] تست‌ها (red):
  - pure_url در مقصد انتخاب‌شده → label = پلتفرم (نه متن دلخواه).
  - handle در مقصد انتخاب‌شده → label = پلتفرم (نه ایموجی).
  - inline/composite/null با label → label همان.
  - legacy (type null) + label → label همان (سازگاری دادهٔ قدیمی).
  - `buildPreview` هم همین رفتار (پیش‌نمایش ویزارد آلوده نمی‌شود).
- [ ] fix: [`buildSignatureForDestination()`](app/Services/ChannelPosterBotServiceImpl.php:700) و [`buildPreview()`](app/Services/ChannelPoster/SignatureSettingsService.php:519) هر دو از helper استفاده کنند.
- [ ] test مستقل برای **هر** type امضا (pure_url, handle, inline, raw, plain, button, composite, none) — اینکه label هر type فقط در مسیر خودش تفسیر می‌شود.
- [ ] سبز + commit. (پوشش acceptance scenarios 3 و 4)

### Task 3 — قرارداد multi-line (addendum D)

قرارداد: (1) هر مقصد/لینک **فقط یک‌بار** (dedup بر اساس url در `renderMulti`)؛ (2) multi_line: هر ورودی در خط مستقل؛ (3) حالت label‌دار: `برچسب:` خط اول + لینک خط دوم (همهٔ پلتفرم‌ها)؛ (4) single_line دست نمی‌خورد؛ (5) خروجی معنایی ۵ پلتفرم یکسان (هر label + هر url دقیقاً یک‌بار، هم‌ترتیب).

- [ ] تست‌ها (red) در `SignatureRendererTest`:
  - `renderMulti(LAYOUT_MULTI_LINE)` برای `eitaa|bale|soroush|rubika`: خروجی `"بله:\nhttps://ble.ir/ch"` / `"بله:\n[https://ble.ir/ch](https://ble.ir/ch)"` (بale-markdown فقط وقتی label امن) — فعلی `"بله: https://ble.ir/ch"`.
  - dedup: دو لینک با url یکسان → یک نمایش.
  - parity: برای یک مجموعهٔ لینک، ۵ پلتفرم → هر کدوم labelها و urlها را یک‌بار و هم‌ترتیب شامل باشند.
- [ ] fix [`renderMulti()`](app/Services/ChannelPoster/SignatureRenderer.php:280): شاخهٔ multi_line غیر-HTML → `label . ":\n" . linkLine(...)` (هم‌فرمت `render()`) + dedup بر اساس url در ابتدای تابع.
- [ ] سبز + commit. (پوشش acceptance scenarios 5 و 9-قسمت)

### Task 4 — ترتیب پیش‌فرض پلتفرم‌ها (addendum D)

- [ ] ثابت priority در `SignatureSettingsService`: `eitaa=0, bale=1, soroush=2, telegram=3, rubika=4`.
- [ ] fix [`buildSignatureForDestination()`](app/Services/ChannelPosterBotServiceImpl.php:695): بعد از `resolveSelected` و قبل از ساخت `$links`، برای سیاست‌های قطعی (`all_channels`, `exclude_self`) مرتب‌سازی بر اساس priority. استثنائات مصوب:
  - `custom` → ترتیب انتخاب کاربر حفظ می‌شود (کنترل صریح).
  - `random` → ترتیب تصادفیِ فریزشده حفظ می‌شود.
  - `self_only_plus_eitaa_on_telegram` → خود تلگرام اول (قرارداد فعلی و مستند سیاست) + ایتاها بعد.
- [ ] تست‌ها: `all_channels` با مقصدهای به‌جای‌گذاری‌شده به‌ترتیب rubika/telegram/bale/soroush/eitaa → خروجی order = eitaa, bale, soroush, telegram, rubika؛ custom و random دست‌نخورده.
- [ ] سبز + commit.

### Task 5 — `parse_mode` کپشن رسانهٔ تلگرام + امنیت Retry (addendum E)

- [ ] اکتشاف اولیه (قبل از تست): shape پاسخ کلاینت تلگرام در خطای parse (فیلد description/error و متن «can't parse entities») — برای شرط retry دقیق.
- [ ] تست `ChannelPosterMediaSender` (Http::fake, red):
  - telegram + `parseMode='html'` → فرم multipart شامل `parse_mode=html`.
  - bale/soroush/rubika/eitaa → فیلد `parse_mode` **نباشد** (ایتا هرگز).
- [ ] fix [`ChannelPosterMediaSender::send()`](app/Services/ChannelPosterMediaSender.php:36): `?string $parseMode = null`؛ فقط در `sendViaMultipart` (telegram) و فقط اگر غیرخالی.
- [ ] fix [`publish()`](app/Services/TelegramChannelPosterPublisher.php:322): پاس دادن `$parseMode` به `send()` در شاخهٔ رسانه.
- [ ] **Retry امن** (E):
  - شاخهٔ متنی (خط 514): retry فقط اگر پاسخ خطای API صریح با pattern parse باشد (`/can'?t parse|parse.*entit|entities/i` در description) — نه روی `!isOk` عمومی (timeout/ConnectionException در catch قبلی می‌افتند ⇒ بدون retry؛ پاسخ نامشخص ⇒ بدون retry).
  - شاخهٔ رسانهٔ telegram: همان guard برای retry بدون parse_mode.
  - تست‌ها: (a) parse error → retry یک‌بار بدون parse_mode و موفق؛ (b) network exception → **بدون** retry (تعداد درخواست‌ها = 1)؛ (c) HTTP 500/ناشناخته → بدون retry.
  - لاگ: `parse_mode retry (text|media, {platform})`.
- [ ] سبز + commit. (پوشش acceptance scenarios 8 و 10-قسمت)

### Task 6 — Hardening مستقل Eitaa (addendum B)

- [ ] fix defensive در [`publish()`](app/Services/TelegramChannelPosterPublisher.php:391) شاخهٔ ایتا: `$parseMode` را صریحاً `null` کند قبل از `sendMessageEitaaSupport` (API رسمی ایتایار parse_mode ندارد؛ حتی اگر مسیر بالادستی چیزی بفرستد).
- [ ] تست‌ها (red):
  - رندر: برای هر type (inline/multi/pure/handle) خروجی ایتا شامل `<` نمی‌شود (assert روی خروجی `render`/`renderMulti`).
  - مسیر متنی: publish ایتا با امضای multi → `Http::fake` ثبت می‌کند `text` بدون HTML و بدون پارامتر parse_mode.
  - مسیر رسانه: photo + video با کپشنِ امضای multi-line plain → `Http::fake` روی endpoint ایتا: `caption` دقیقاً همان plain (بدون `<`)، `text` فیلد ایتا در sendFile.
- [ ] اطمینان: URL ایتا = URL سادهٔ خودکار-لینک‌دارشونده (رفتار فعلی `linkLine` ایتا) — assert.
- [ ] سبز + commit. (پوشش acceptance scenario 9)

### Task 7 — `/deletepurelink` نوع‌آگاه (addendum A، Option A)

- [ ] سرویس: `deletePureUrlSignature(int $destinationId): array{changed: bool, previous_type: ?string}` در `ChannelPosterBotServiceImpl`:
  - اگر `signature_type === 'pure_url'` → `signature_type = TYPE_NONE`, `signature_label = null` (فقط این دو ستون + save). `signature_enabled`, `signature_settings`, `channel_link`, `channel_username` دست نمی‌خورند.
  - وگرنه → هیچ تغییری، برگشت `{changed: false, previous_type}`.
  - و `disableAllSignatures(int $destinationId)` جدا: `signature_enabled=false`, `type=none`, `settings=null`, `label=null` (فقط با تأیید صریح کاربر).
  - تست واحد: pure_url → پاک + enabled/settings دست‌نخورده (settings را در تست صریحاً ست کن و باقی ماندن را assert)؛ handle → no-op و `signature_type=handle` + emoji دست‌نخورده. (پوشش acceptance scenarios 1 و 10)
- [ ] کنترلر:
  - stateها: `cp_dpl_awaiting_tag` / `cp_dpl_awaiting_channel` / `cp_dpl_awaiting_confirm`.
  - dispatch: `preg_match('#^/deletepurelink\b#')` کنار `/sign` ([خط 375](app/Http/Controllers/ChannelPosterBotController.php:375)).
  - ویزارد (آینهٔ `startPureUrlWizard`): تگ → کانال (**با نمایش نوع فعلی امضای هر کانال** در لیست: pure_url / handle / هوشمند / legacy / غیرفعال) → مرحلهٔ تأیید:
    - نوع pure_url: پیش‌نمایش امضای فعلی + دکمه‌ها `حذف purelink` / `غیرفعال‌سازی کامل همهٔ امضاها` / `انصراف`.
    - نوع دیگر: پیام «این کانال purelink ندارد (نوع فعلی: X)» + دکمه‌های `غیرفعال‌سازی کامل همهٔ امضاها` / `انصراف`.
  - callbackها: `cp:dpltag:*`, `cp:dplch:*`, `cp:dplconf:pur|all|cancel`.
  - fallback متنی `handleDeletePureLinkText` در شاخهٔ stateful ([خط 507](app/Http/Controllers/ChannelPosterBotController.php:507)) + بازوی لغو در `/cancel` ([خط 356](app/Http/Controllers/ChannelPosterBotController.php:356)).
- [ ] زبان: بلوک `bot.channel_poster_dpl_*` در [`lang/fa/bot.php`](lang/fa/bot.php) (بعد از `channel_poster_hnd_*`): `dpl_menu_entry`, `dpl_ask_tag`, `dpl_pick_channel` (با توضیح نشانه‌های نوع), `dpl_confirm_pur`, `dpl_confirm_all`, `dpl_no_purelink`, `dpl_deleted`, `dpl_all_disabled`, `dpl_cancelled`, `dpl_hint_*`.
- [ ] ورودی منو کنار [`cp:pururl`/`cp:handle`](app/Http/Controllers/ChannelPosterBotController.php:4314) + یک خط در `channel_sync_help_owner_commands`.
- [ ] سبز + commit.

### Task 8 — بستهٔ پذیرش ۱۰ سناریویی (addendum F)

- [ ] `tests/Unit/SignatureScenariosTest.php` — هر سناریو یک تست مستقل با خروجی fix‌شده:
  1. حذف Pure URL باقی ماندن Handle (تست یکپارچهٔ T7: pure→handle روی مقصد؛ deletepurelink روی مقصدی با type=handle ⇒ no-op؛ حذف روی type=pure_url ⇒ handle دادهٔ مقصد دیگر دست‌نخورده).
  2. Handle با فاصله: `\n\n🔰 @saberblog` (همهٔ پلتفرم‌ها).
  3. عدم نشت Label از Pure URL به امضای هم‌تگ‌ها + به preview.
  4. عدم نشت Emoji از Handle به label لینک‌های دیگر + به preview.
  5. Multi-line در هر ۵ پیام‌رسان (label:/link دوخطه، dedup، order پیش‌فرض).
  6. Single-line در هر ۵ پیام‌رسان (`ایتا | بله | سروش | تلگرام | روبیکا`؛ telegram anchor HTML، بقیه plain/markdown).
  7. HTML در پیام متنی تلگرام (parse_mode=html، escape صحیح، retry guard).
  8. HTML در کپشن رسانهٔ تلگرام (multipart با parse_mode).
  9. عدم نمایش HTML خام در ایتا (متن + photo + video؛ بدون پارامتر parse_mode).
  10. عدم ارسال تکراری در retry (parse error → 1 retry؛ network/unknown → 0 retry) + حفظ تنظیمات سایر مقصدها (deletepurelink روی مقصد A ⇒ مقصد B دست‌نخورده).
- [ ] سبز + commit.

### Task 9 (اختیاری — حذف‌پذیر در review) — `link_order` کنترل ترتیب

- [ ] `link_order` (آرایهٔ id) در `normalize()`/`defaults()`؛ در `resolveSelected`/ساخت لینک‌ها: اگر تعیین شده، برتر از ترتیب پیش‌فرضِ T4 (idهای غایب به انتها به ترتیب priority).
- [ ] ویزارد: مرحلهٔ اختیاری ترتیب (بالا/پایین) + fallback متنی.
- [ ] تست: `all_channels` + `link_order` با سروش اول → در مقصد تلگرام، خط اول = سروش (سناریوی «اول لینک سروش در تلگرام» بدون ویرایش دستی).
- [ ] سبز + commit.

### Task 10 — مستندات، رگرسیون، گزارش وضعیت (addendum G)

- [ ] سند فارسی `docs/CHANNEL_POSTER_SIGNATURES.md`: نقشهٔ سناریو→سیاست/چیدمان، اولویت‌های `signature_enabled`، `/deletepurelink`، توضیح «بعضی جاها» (parse_mode رسانه + multi_line)، قرارداد ترتیب پیش‌فرض، راهنمای Eitaa plain.
- [ ] آپدیت `docs/PROJECT_MASTER_MAP.md` (لینک به سند جدید + وضعیت فیچرها).
- [ ] **رگرسیون کامل:** `php artisan test` — کل سوت‌تست سبز (شامل `SignatureRendererTest`, `SignatureSettingsServiceTest`, `ChannelPosterBotServiceTest`, `ChannelHandleExtractorTest`, ContentSync tests).
- [ ] **گزارش وضعیت سه‌بخشی (قبل از انتشار):**
  - Local: `git status` + آخرین commitها + خروجی رگرسیون.
  - GitHub: وضعیت push branch `15esfand1404` (ahead/behind remote).
  - Production: آخرین commit deployed (از `deploy_commands.sh`/CPANEL) + فهرست مواردی که هنوز deploy نشده‌اند.
- [ ] commit نهایی.

---

## Self-Review Checklist (v2)

- [ ] هر بند addendum به task با test خورده؟ (A→T7+AD، B→T6، C→T2+T8، D→T3+T4+T8، E→T5+T8، F→T8، G→T10)
- [ ] Option A تأیید کاربر شده؟ (مگر تأیید Option B، migration نمی‌شود)
- [ ] هیچ data migration / بازنویسی رکورد موجودی در plan نیست.
- [ ] `signature_enabled=false` فقط در `disableAllSignatures` (تأیید صریح) — نه در `deletePureUrlSignature`.
- [ ] Eitaa در T3/T5/T6 هیچ مسیر HTML/parse_mode جدیدی ندارد.
- [ ] retry فقط parse-error صریح (T5) — شامل شاخهٔ متنی موجود که الان broad است.
- [ ] `buildPreview` و publish path از یک helper مشترک استفاده می‌کنند (یک تفسیر، دو مصرف).
- [ ] ترتیب پیش‌فرض T4 با استثنائات مصوب (custom/random/self_plus_eitaa) مستند شده.
- [ ] ویزارد `/deletepurelink` fallback متنی سروش/روبیکا دارد.
- [ ] گزارش سه‌بخشی Local/GitHub/Production در T10.

## Execution Handoff

پیش‌نیاز: تأیید **Option A** (یا انتخاب Option B = بازطراحی).
سپس یکی:
- **Subagent-driven**: T1–T6 تقریباً مستقل (T2 helper باید قبل از T3/T4 بیاید) → سپس T7، T8.
- **Native**: توالی T1→T10 در همین سشن.
روش اجرا مطابق G: test-first مرحله‌ای، تست مرتبط بعد از هر مرحله، رگرسیون کامل در پایان.
