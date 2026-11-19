# Runbook: تست اتصال تولید به LiteLLM (ai.ismc.ir) — بدون نشت توکن

**مأموریت:** AI-PRODUCTION-VERIFICATION-07 / Task A
**مخاطب:** Owner (دسترسی cPanel)
**تاریخ:** 2026-11-15
**قانون طلایی:** توکن/سیcret در هیچ‌کدام از ج‌ای‌ها نمی‌آید: خروجی ترمینال، گزارش، shell history، git. TLS هرگز خاموش نمی‌شود.

---

## 1. معماری واقعی ذخیره‌ی کلید (نتیجه‌ی audit — Task A1)

| سؤال | پاسخ دقیق |
|---|---|
| رکورد اصلی provider کدام است؟ | اولین رکورد فعال جدول `ai_providers` (`AiProvider::active()->first()`). |
| کلید کجا ذخیره می‌شود؟ | ستون `ai_providers.api_key` با cast `encrypted` (AES با `APP_KEY`) — در DB به‌صورت مبهم. |
| آیا Nova می‌تواند کلید را ویرایش کند؟ | **بله.** `/nova/ai-providers` → رکورد provider → Edit → فیلد **API Key** (فقط در فرم، `required`). در لیست/دیتیل نمایش نمی‌شود. |
| نقش `AI_DEFAULT_API_KEY` چیست؟ | **فقط برای ساخت رکورد جدید** (`firstOrCreate` در `AiProviderService::ensureDefaultProvider()` و `AiProviderDefaultSeeder`). **تغییر env، رکورد موجود را به‌روز نمی‌کند.** |
| `ai:discover-models` چه تغییری در DB می‌دهد؟ | فقط `GET /v1/models`؛ به‌روزرسانی `available_models` + `settings.model_capabilities` + `models_discovered_at` + `last_tested_at` + `last_test_status=success`. **بدون chat، بدون notify، بدون هزینه توکن.** |
| `ai:test` چه side effect دارد؟ | `GET /v1/models` + **یک chat واقعی** (هزینه توکن) + به‌روزرسانی رکورد provider + **notifyAdmin در هر دو حالت موفق/ناموفق** (🟢/🔴/🟡). خروجی 1 در ناموفق. |
| `ai:health-check` چه side effect دارد؟ | ابتدا `ensureDefaultProvider()` (ممکن است رکورد بسازد)؛ برای هر provider فعالی که `needsTest` (>1h) باشد: connection + chat؛ ثبت `ai_provider_logs`؛ در ناموفق/recovery **notifyAllAdmins** + `Log::warning`. خروجی 1 در ناموفق. |

**نتیجه عملی:** جای رسمی ثبت توکن = **Nova → AI Providers → API Key**. متد env فقط وقتی کار می‌کند که هنوز رکوردی در `ai_providers` نباشد.

---

## 2. پیش‌نیاز: شبکه (نتیجه‌ی آزمون محلی — Task A3)

آزمایش از شبکه‌ی داخلی (محل Owner)، 2026-11-15:

| مرحله | نتیجه |
|---|---|
| DNS `ai.ismc.ir` | `172.30.0.133` (IP خصوصی — endpoint شبکه‌ی داخلی) |
| TCP connect :443 | موفق، ~31ms |
| TLS handshake | موفق، ~84ms |
| `GET /v1/models` بدون کلید | **HTTP 401** در ~120ms |
| `GET /v1/models` با کلید placeholder (کد app) | **HTTP 401** در 179ms |
| خطای production | `cURL error 28: Connection timed out after 30001 milliseconds` |

**جداسازی شبکه vs احراز هویت:**
- خطای ۳۰۰۰۱ms دقیقاً معادل `connect_timeout: 30` در `config/ai.php` است → **timeout در مرحله‌ی TCP connect**، یعنی production اصلاً به پورت 443 متصل نمی‌شود. این **قبل از** ارسال Authorization رخ می‌دهد → **مسئله‌ی authentication نیست**.
- چون endpoint از شبکه‌ی داخلی پاسخ 401 می‌دهد، سرویس سالم است؛ مشکل در **مسیر شبکه‌ی production** است (فایروال/روت/DNS روی هاست cPanel).
- چون `ai.ismc.ir` به IP خصوصی `172.30.0.133` resolve می‌شود، production باید روی همان شبکه/VLAN باشد یا route مناسب داشته باشد.

**اولین دستور تشخیص شبکه در production:**
```bash
curl -o /dev/null -s -w "http_code=%{http_code} connect=%{time_connect}s tls=%{time_appconnect}s total=%{time_total}s\n" --max-time 35 https://ai.ismc.ir/api/v1/models
```
- خروجی انتظار با شبکه‌ی درست: `http_code=401 connect=0.0x..s tls=0.0x..s` (401 یعنی TCP+TLS سالم؛ بعدش با کلید درست 200 می‌شود).
- اگر `connect` خالی/0.000 و `http_code=000` → TCP برنمی‌گردد → فایروال/روت production.
- **TLS را هرگز با `AI_VERIFY_SSL=false` خاموش نکنید.** اگر خطای TLS (certificate) آمد، CA bundle را درست کنید: `storage/certs/ismc-ai-ca-bundle.pem` (در `config('ai.http_options.verify')` استفاده می‌شود).

---

## 3. ورود به ترمینال production (cPanel)

1. cPanel → **Terminal** (یا SSH: `ssh -p 2156 user@host`).
2. وارد دایرکتوری اپلیکیشن شوید:
   ```bash
   cd ~/bot-generator-bale-telegram-laravel-10   # مسیر واقعی پروژه در cPanel
   ```

---

## 4. ثبت توکن (بدون اینکه در ترمینال تایپ شود)

**روش اصلی — Nova (توصیه‌شده):**
1. مرورگر → `/nova/ai-providers`
2. روی رکورد provider (مثلاً «ISMC AI Server») → **Edit**
3. فیلد **API Key** را پاک کرده، توکن واقعی را Paste کنید → **Save**.
4. فیلد فقط در فرم است و در لیست/دیتیل نمایش نمی‌شود؛ در DB با cast `encrypted` ذخیره می‌شود.

**روش جایگزین — فقط اگر رکوردی در `ai_providers` نیست (Nova نمی‌تواند بسازد یا دسترسی ندارید):**
1. cPanel → **File Manager** → `.env` را **Edit** کنید (نه در ترمینال → وارد history نمی‌شود).
2. مقداردهی کنید: `AI_DEFAULT_API_KEY=sk-...`
3. در ترمینال:
   ```bash
   php artisan config:clear
   ```
4. **هشدار:** این مقدار فقط موقع **ساخت** رکورد تازه استفاده می‌شود (اولین اجرای `ai:health-check` یا `ai:test`). اگر رکورد قبلی وجود دارد، env روی آن اثر نمی‌گذارد → در آن حالت فقط Nova (یا update مستقیم DB) کلید را عوض می‌کند.

---

## 5. تأیید ذخیره‌شدن کلید بدون نمایش آن

در ترمینال (فقط طول + پیش‌فايل را چاپ می‌کند، خود کلید نه):
```bash
php artisan tinker --execute="$p=\App\Models\AiProvider::active()->first(); echo $p ? ('provider_id='.$p->id.' key_len='.strlen($p->api_key).' prefix_ok='.var_export(str_starts_with($p->api_key,'sk-'),true).' status='.$p->status) : 'no active provider';"
```
- خروجی نمونه: `provider_id=1 key_len=52 prefix_ok=true status=active`
- اگر `key_len` همان 26 (مقدار placeholder `sk-***...`) است → توکن واقعی هنوز ثبت نشده.
- **هرگز** `php artisan tinker` + `dump($provider)` یا `cat .env` در ترمینال عمومی بزنید.

---

## 6. ترتیب دقیق دستورات تست (با timestamp)

هر دستور را با این پیش‌وند اجرا کنید تا زمان بماند:
```bash
date -u +"%Y-%m-%d %H:%M:%S UTC"; php artisan <command>
```

### 6.1 کشف مدل‌ها (ارزان‌ترین و امن‌ترین — بدون chat)
```bash
date -u; php artisan ai:discover-models
```
- **موفق:** جدول مدل‌ها + طبقه‌بندی (chat/vision/embedding/transcription/tts)؛ `last_test_status=success`؛ خروجی 0.
- **ناموفق:** خطای اتصال (با redaction توکن توسط `AiErrorRedactor`)؛ خروجی 1.

### 6.2 health check (connection + chat)
```bash
date -u; php artisan ai:health-check
```
- **موفق:** وضعیت هر provider `ok` + یک `ai_provider_log` با `check_type=chat`؛ خروجی 0.
- **ناموفق:** `check_type=connection` log + notification به همه adminها + خروجی 1.

### 6.3 تست کامل (models + chat واقعی — مصرف توکن)
```bash
date -u; php artisan ai:test
```
- **موفق:** 🟢 notification + خروجی 0.
- **چت شکست بخورد:** 🟡 notification + خروجی 1.
- **اتصال شکست بخورد:** 🔴 notification + خروجی 1.

### 6.4 (A4) ثبت شواهد بعد از اتصال موفق
- خروجی هر سه دستور + timestamp + تعداد مدل‌های کشف‌شده را در گزارش نهایی بنویسید.
- **قانون:** هیچ Vision/Embedding model بدون تست واقعی آن capability «verified» محسوب نمی‌شود (طبقه‌بندی `ai:discover-models` حدسی‌است بر اساس نام مدل).

---

## 7. جدول تصمیم‌گیری برای خطاها

| خطا | تفسیر | اقدام |
|---|---|---|
| `HTTP 401` | شبکه و TLS سالم، **کلید نادرست/غیاب** | توکن را دوباره در Nova ثبت کنید → §5 → `ai:test` |
| `HTTP 403` | کلید درست ولی **permission** ندارد | از تیم LiteLLM بخواهید scope کلید را چک کنند |
| `HTTP 404` | base_url اشتباه | `AI_DEFAULT_BASE_URL` / `ai_providers.base_url` باید `https://ai.ismc.ir/api` باشد (نه `/api/v1`) |
| `cURL error 28` بعد از 30001ms | **TCP connect timeout = شبکه production** (نه auth) | §2: دستور curl تشخیصی؛ فایروال/روت/DNS روی production؛ بررسی مسیر تا `172.30.0.133:443` |
| `SSL certificate problem` | CA bundle ناقص | `storage/certs/ismc-ai-ca-bundle.pem` را به‌روز کنید؛ **`AI_VERIFY_SSL=false` نگذارید** |
| `HTTP 200` ولی chat خالی | مدل نامعتبر | `ai:discover-models` → مدل chat صحیح در `model` provider (مثلاً `qwen38`) |

---

## 8. چک‌لیست نهایی Task A

- [ ] Owner توکن را در **Nova → AI Providers → API Key** ثبت کرد (نه در ترمینال).
- [ ] §5 اجرا شد و `key_len`/`prefix_ok` درست است (بدون نمایش کلید).
- [ ] curl تشخیصی (§2) در production `http_code=401` یا `200` با connect < 1s.
- [ ] `ai:discover-models` موفق + تعداد مدل‌ها ثبت شد.
- [ ] `ai:health-check` موفق (log `check_type=chat`).
- [ ] `ai:test` موفق (🟢) — chat واقعی با «سلام» (prompt پیش‌فرض) پاسخ گرفت.
- [ ] هیچ Vision/Embedding بدون تست واقعی verified اعلام نشد.
- [ ] هیچ‌جا (خروجی/گزارش/history/git) توکن چاپ نشد.
