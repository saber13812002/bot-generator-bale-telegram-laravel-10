# پیدا کردن chat_id کانال روبیکا + تشخیص (Diagnose) پاسخ API

مستند عملیاتی برای پیدا کردن شناسهٔ کانال/گروه روبیکا و عیب‌یابی پاسخ‌های
`botapi.rubika.ir` بدون نیاز به `jq` (فقط `curl` + `php` در ترمینال).

این مستند از مأموریت **CP-RUBIKA-RESPONSE-FIX** (اکتبر ۲۰۲۶) به‌وجود آمده:
در محیط واقعی (پروتکشن) مشاهده شد که روبیکا پاسخ `sendMessage` را در یک پاکت
دوسویه `{ "status": ..., "data": ... }` برمی‌گرداند که `message_id` آن در
سطح دوم (`data.message_id`) قرار دارد — در حالی که مستندات رسمی شکل دقیق
پاکت پاسخ را تعریف نکرده‌اند. دستور زیر در یک کانال واقعی **تأیید شده** است.

> ⚠️ این مستند هیچ توکن واقعی ندارد. هرگز توکن واقعی را در مستندات، لاگ یا
> commit ننویسید. اگر در ترمینال استفاده می‌کنید، خروجی را به‌گمان نبرید که
> می‌تواند در تاریخچهٔ shell باقی بماند.

---

## ۱. آدرس رسمی API

| مورد | مقدار |
|---|---|
| Base URL | `https://botapi.rubika.ir/v3/{TOKEN}/{METHOD}` |
| متد HTTP | **فقط POST JSON** (`Content-Type: application/json`) |
| مستندات رسمی | `https://rubika.ir/botapi` ، `https://rubika.ir/botapi/methods` ، `https://rubika.ir/botapi/models` |
| فرمت توکن | opaque (فرمت رسمی اعلام نشده)؛ در کد پروژه با `[a-zA-Z0-9_-]{8,200}` اعتبارسنجی می‌شود |
| شکل پاکت پاسخ | **مستند نیست**؛ مشاهدهٔ زنده: `{"status": <string>, "data": <array>}` — تحلیل متمرکز در [`app/Services/RubikaBotClient.php`](../../app/Services/RubikaBotClient.php) (`analyzeResponse()` — پنج حالت) |

متدهای مرتبط با این مستند:

| متد | پارامترها | کاربرد |
|---|---|---|
| `getUpdates` | `offset_id` (اختیاری)، `limit` (اختیاری) | خواندن آپدیت‌های اخیر (برای پیدا کردن chat_id) |
| `getChat` | `chat_id` | تأیید صحت chat_id و خواندن مشخصات چت |
| `sendMessage` | `chat_id`، `text`، `disable_notification` (اختیاری) | ارسال پیام (برای تست دسترسی) |
| `updateBotEndpoints` | `url`، `type` (مثلاً `ReceiveUpdate`) | ثبت آدرس webhook |

---

## ۲. پیدا کردن chat_id با `getUpdates`

ساده‌ترین و مطمئن‌ترین راه: ربات را به کانال/گروه اضافه کنید، یک پیام در آن
چت بنویسید (یا ربات را @mention کنید)، سپس آپدیت‌ها را بخوانید:

```bash
read -rp "Rubika bot token: " TOKEN

curl -s -X POST "https://botapi.rubika.ir/v3/${TOKEN}/getUpdates" \
  -H "Content-Type: application/json" \
  -d '{"limit": 50}' | php -r '
$data = json_decode(stream_get_contents(STDIN), true);
if (!is_array($data)) { echo "BODY: not a JSON object\n"; exit(0); }
$updates = $data["updates"] ?? ($data["data"]["updates"] ?? []);
if (!is_array($updates)) { echo "no updates found\n"; var_export($data); exit(0); }
foreach ($updates as $u) {
    if (!is_array($u)) continue;
    $inner = $u["update"] ?? $u;
    $type = $inner["type"] ?? "?";
    $cid  = $inner["chat_id"] ?? ($inner["new_message"]["chat_id"] ?? "?");
    $msg  = $inner["new_message"] ?? [];
    $text = is_array($msg) ? ($msg["text"] ?? "") : "";
    $name = is_array($msg) ? ($msg["chat"]["name"] ?? "") : "";
    echo "type=$type  chat_id=$cid  chat=$name  text=" . mb_substr((string)$text, 0, 60) . "\n";
}
'
```

سطر خروجی مربوط به همان چتی که پیام در آن نوشته شده، `chat_id` را نشان
می‌دهد (ممکن است عددی باشد یا با `@` شروع شود — هر دو را مستقیم در
`chat_id` متدها استفاده کنید).

### تفاوت‌های آپدیت روبیکا با تلگرام

| موضوع | تلگرام | روبیکا |
|---|---|---|
| ساختار بالای آپدیت | `{update_id, message: {...}}` | معمولاً در یک لایهٔ `update` بسته می‌شود: `{"update": {"type": "...", "chat_id": "...", "new_message": {...}}}` |
| نام فیلد پیام | `message` | `new_message` |
| نوع آپدیت | با وجود/نبود فیلد تشخیص | فیلد `type` صریح: `NewMessage`، `User`، `Bot` و ... |
| شناسهٔ فرستنده | `message.from.id` | `new_message.sender_id` |
| نوع فرستنده | `from.is_bot` | `new_message.sender_type` (`User`/`Bot`) |
| chat_id | `message.chat.id` | در سطح آپدیت: `update.chat_id` (فیل‌بک: `new_message.chat_id`) |

> در کد پروژه، نرمال‌سازی این ساختار در
> [`ChannelPosterBotController::normalizeRubikaUpdate()`](../../app/Http/Controllers/ChannelPosterBotController.php) انجام
> می‌شود و فقط `NewMessage` + `sender_type=User` پردازش می‌شود — ساختار
> غیرمستند فرض نمی‌شود.

---

## ۳. تأیید chat_id با `getChat`

```bash
read -rp "Rubika bot token: " TOKEN
read -rp "Chat id: " CID

curl -s -X POST "https://botapi.rubika.ir/v3/${TOKEN}/getChat" \
  -H "Content-Type: application/json" \
  -d "{\"chat_id\": \"${CID}\"}" | php -r '
$data = json_decode(stream_get_contents(STDIN), true);
var_export($data); echo "\n";
'
```

اگر `chat_id` اشتباه باشد یا ربات عضو آن چت نباشد، پاکت پاسخ معمولاً
`status` خطا می‌دهد (مثل `404`/خطای دسترسی). اگر `chat` با نام/عنوان چت
بازگشت، شناسه درست است.

---

## ۴. دستور تشخیص (Diagnose) پاسخ `sendMessage` — تأییدشده در کانال واقعی

این دستور یک پیام کوتاه می‌فرستد و شکل پاسخ را بدون `jq` تحلیل می‌کند.
**کاربرد**: ۱) بررسی اینکه ربات دسترسی ارسال دارد، ۲) دیدن شکل واقعی
پاکت پاسخ، ۳) پیدا کردن مسیر `message_id`.

```bash
read -rp "Rubika bot token: " TOKEN
read -rp "Channel/chat id: " CID

curl -s -X POST "https://botapi.rubika.ir/v3/${TOKEN}/sendMessage" \
  -H "Content-Type: application/json" \
  -d "{\"chat_id\":\"${CID}\",\"text\":\"CP API diagnostic $(date +%s)\"}" \
  -w '\n__HTTP_STATUS__:%{http_code}\n' | php -r '
$raw = stream_get_contents(STDIN);
$pos = strrpos($raw, "\n__HTTP_STATUS__:");
$status = $pos === false ? "?" : substr($raw, $pos + strlen("\n__HTTP_STATUS__:"));
$body   = $pos === false ? $raw : substr($raw, 0, $pos);
echo "HTTP STATUS: " . trim($status) . "\n";
$data = json_decode(trim($body), true);
if (!is_array($data)) { echo "BODY: not a JSON object\n"; exit(0); }
foreach ($data as $k => $v) {
    $t = is_array($v) ? (array_is_list($v) ? "array(list)" : "array") : gettype($v);
    echo "  {$k}: {$t}\n";
}
if (isset($data["status"]) && is_string($data["status"])
    && preg_match("/^[a-zA-Z0-9_-]{1,40}$/", $data["status"])) {
    echo "API STATUS: " . $data["status"] . "\n";
}
$found = false;
$walk = function ($v, $path, int $depth) use (&$walk, &$found) {
    if ($found || $depth > 6 || !is_array($v)) return;
    foreach ($v as $k => $sub) {
        $p = $path === "" ? (string)$k : $path . "." . $k;
        if ($k === "message_id" && (is_int($sub) || (is_string($sub) && $sub !== ""))) {
            echo "MESSAGE ID PATH: {$p}\n";
            $found = true;
            return;
        }
        $walk($sub, $p, $depth + 1);
    }
};
$walk($data, "", 0);
if (!$found) echo "MESSAGE ID PATH: (not found)\n";
$d = $data["data"] ?? null;
if (is_array($d)) {
    $keys = array_map(fn($k) => (string)$k, array_keys($d));
    echo "DATA KEYS: " . (implode(", ", $keys) ?: "(empty)") . "\n";
}
'
```

### نمونهٔ خروجی موفق (مشاهده‌شده در پروتکشن، اکتبر ۲۰۲۶)

```text
HTTP STATUS: 200
  status: string
  data: array
API STATUS: OK
MESSAGE ID PATH: data.message_id
DATA KEYS: message_id
```

### تفسیر خروجی

| خطوط خروجی | تفسیر |
|---|---|
| `HTTP STATUS: 200` + `MESSAGE ID PATH: data.message_id` | ✅ ارسال موفق — پیام ثبت شده. پیام تست در کانال باید دیده شود. |
| `HTTP STATUS: 200` + `API STATUS: OK` + `MESSAGE ID PATH: (not found)` | ✅ پذیرش صریح API، ولی `message_id` در پاسخ نبود (موفقیت بدون شناسه). در کد پروژه = `success_without_message_id` — مقصد ذخیره می‌شود و یک یادداشت می‌دهد که پیام را در کانال چک کنید. |
| `HTTP STATUS: 200` + `API STATUS:` یک کلمهٔ خطا (مثل `denied`/`forbidden`) | ❌ خطای خود API — معمولاً ربات عضو کانال نیست یا ادمین نیست. |
| `HTTP STATUS: 403` / `404` | ❌ خطای HTTP — معمولاً permission (403) یا chat_id اشتباه (404). |
| `HTTP STATUS: 401` | ❌ توکن نامعتبر. |
| `HTTP STATUS: 000` یا timeout | ❌ خطای شبکه — اتصال/DNS را چک کنید. |
| `BODY: not a JSON object` | ❌ پاسخ JSON نیست — یا خطای شبکه/پراکسی است یا توکن اشتباه (صفحهٔ خطای HTML). |
| `HTTP STATUS: 200` ولی هیچ‌کدام از بالا | ⚠️ `unknown_result` — پیام **ممکن است** ارسال شده باشد. قبل از تلاش مجدد کانال را چک کنید تا پیام تکراری نشود (دقیقاً همان پیامی که ربات در ویزارد می‌دهد). |

### نکات مهم در مورد «موفقیت بدون message_id»

- در روبیکا، HTTP 200 به‌تنهایی مدرک موفقیت **نیست** و نبود message_id به‌تنهایی
  مدرک شکست **نیست**. تصمیم‌گیری فقط با شواهد واقعی بدنه انجام می‌شود
  (پنج‌حالتی در [`RubikaBotClient::analyzeResponse()`](../../app/Services/RubikaBotClient.php)).
- ربات در ویزارد `/add`، «ربات ادمین کانال نیست» را **فقط** وقتی می‌گوید که
  شواهد واقعی permission (HTTP 403 یا `forbidden`/`denied` در status) وجود
  داشته باشد.

---

## ۵. Long Polling (`getUpdates`) در مقابل Webhook

| | Long Polling | Webhook |
|---|---|---|
| نحوه | ربات شما در یک حلقهٔ `getUpdates` با `offset_id` هر نوبت، آپدیت‌های جدید را می‌خواند | روبیکا آپدیت‌ها را به آدرس شما POST می‌کند (`updateBotEndpoints` با `type=ReceiveUpdate`) |
| URL عمومی | لازم نیست | **ضروری** (HTTPS قابل‌دسترس از بیرون) |
| مصرف | درخواست‌های مکرر + ممکن است آپدیت‌های قدیمی‌تر «مصرف» شوند و دیگر از `getUpdates` برنگردند | یک اتصال ورودی |
| کاربرد در این پروژه | فقط برای عیب‌یابی/پیدا کردن chat_id | مسیر اصلی: `BotRegistrationService::registerNativeClientBot('rubika')` آدرس webhook را با `updateEndpoint()` ثبت می‌کند |

نکته: `getUpdates` برای **کشف** مناسب است ولی جایگزین ثبت webhook برای
رسیدگی مداوم به پیام‌ها نیست.

---

## ۶. امنیت توکن

- توکن در مسیر URL است (`/v3/{token}/method`) → هر لاگ/خطایی که URL کامل
  بیاورد، توکن را فاش می‌کند. در کد پروژه همهٔ پیام‌های خطا/لاگ از
  `TokenHelper::redactTokens()` عبور می‌کنند.
- توکن را در git، مستندات، اسکرین‌شات یا چت ندهید. اگر فاش شد، از
  پنل روبیکا توکن جدید بگیرید.
- در ترمینال، `read -p` (نه `echo`) استفاده شده تا توکن در خروجی تکرار نشود؛
  ولی توکن در تاریخچهٔ shell / process list ممکن است دیده شود — در سیستم
  اشتراکی مراقب باشید.

---

## ۷. خطاهای رایج PHP / curl / JSON و رفع آن‌ها

| علامت | علل احتمالی | راه‌حل |
|---|---|---|
| `HTTP STATUS: 000` | DNS، فایروال، قطع اینترنت، timeout | اتصال را چک کنید؛ `-v` به curl اضافه کنید تا مرحلهٔ شکست مشخص شود |
| `curl: (6) Could not resolve host` | DNS خراب | `nslookup botapi.rubika.ir` را امتحان کنید |
| `BODY: not a JSON object` + HTML در بدنه | پراکسی/CDN خطا داده یا توکن بی‌اعتبار | مستقیم (بدون پراکسی) امتحان کنید؛ توکن را در پنل روبیکا بازنویسی کنید |
| `php -r` خطای syntax داد (در PowerShell) | PowerShell به‌جای bash — `read -rp` و `$(date)` کار نمی‌کنند | از Git Bash / WSL / cmd با `set /p` استفاده کنید یا متغیرها را دستی در اسکریپت بگذارید |
| `MESSAGE ID PATH: (not found)` ولی `API STATUS: OK` | روبیکا بعضی بارها message_id برنمی‌گرداند | این شکست نیست — پیام را در کانال چک کنید (حالت `success_without_message_id`) |
| `API STATUS: denied/forbidden` + HTTP 200 | ربات عضو نیست یا ادمین نیست | ربات را به کانال اضافه/ادمین کنید و دوباره امتحان کنید |
| `HTTP STATUS: 429` | نرخ‌بندی API | چند ثانیه صبر کنید و دوباره امتحان کنید (در کد پروژه = `api_rejected`) |
| خروجی فارسی/Unicode در ترمینال بهم‌ریخته | کدک ترمینال | در Windows: `chcp 65001` |

---

## ۸. محدودیت‌های این مستند

1. **شکل دقیق پاکت پاسخ در مستندات رسمی تعریف نشده**؛ `status: OK` و
   `data.message_id` از مشاهدهٔ زنده (اکتبر ۲۰۲۶) است و ممکن است در
   متدها/نسخه‌های دیگر متفاوت باشد. کد پروژه همین‌دلیل را می‌داند و هر
   چیزی بدون شواهد را `unknown_result` می‌کند — ساختار غیرمستند فرض نمی‌شود.
2. `getUpdates` ممکن است آپدیت‌های قدیمی را «مصرف» کند؛ اگر webhook
   ثبت‌شده دارید، آپدیت‌ها به webhook می‌روند و `getUpdates` ممکن است خالی
   باشد.
3. کلمات `SUCCESS/FAILURE STATUS WORDS` در
   [`RubikaBotClient.php`](../../app/Services/RubikaBotClient.php) فرض
   محافظه‌کارانه و مستندسازانه هستند، نه قرارداد رسمی روبیکا.
4. این دستور یک پیام واقعی در کانال می‌فرستد — برای کانال‌های پرتراف فقط
   در زمان مناسب اجرا کنید.
