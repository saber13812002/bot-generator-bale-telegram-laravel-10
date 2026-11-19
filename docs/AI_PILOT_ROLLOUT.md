# AI Pilot Rollout — Runbook (AI-PILOT-ROLLOUT-05)

مستند عملیاتی rollout تدریجی پلتفرم AI به مشتریان.
حرف‌العصل: **خاموش/خرابی AI هرگز نباید عملکرد عادی botها را مختل کند.**

---

## ۱. Precondition audit (brief §1) — وضعیت صادقانه

| Gate | اعلام در brief | مدرک در ریپازیتوری | وضعیت |
|------|----------------|--------------------|--------|
| PREFLIGHT-01 | GO | ندارد (خروجی اجرای production) | **اعلام‌شده، مستقل تأیید نشده** |
| ROTATION-02 | VERIFIED | `docs/SECURITY_AI_TOKEN_ROTATION.md` (فرآیند)؛ مدرک چرخش فعلی ندارد | **اعلام‌شده، مستقل تأیید نشده** |
| DEPLOY-03 | DEPLOYED | کد در branch `15esfand1404` موجود است؛ دسترسی SSH/production از این محیط نیست | **اعلام‌شده، مستقل تأیید نشده** |
| LIVE-ACCEPTANCE-04 | PASS | ندارد (مدرک live) | **اعلام‌شده، مستقل تأیید نشده** |

نتیجه: هیچ‌کدام از ۴ دروازه از داخل این محیط قابل راستی‌آزمایی نیست (production
unreachable). بر اساس brief آن‌ها را GO در نظر می‌گیریم اما **به‌عنوان پیش‌فرض
مستقل تأییدنشده** ثبت می‌شوند. مسئول تأیید نهایی: owner.

---

## ۲. Stageها (brief §2)

| Stage | نام | دسترسی | رفتار بدون allowlist |
|-------|-----|---------|----------------------|
| 0 | Internal | فقط حساب‌های داخلی (fail-closed) | **هیچ‌کس** |
| 1 | Pilot | فقط allowlist صریح (fail-closed) | **هیچ‌کس** (نه حتی با user id) |
| 2 | All Pro | فقط Pro (24h؛ یا pro-override ثبت‌شده) | کاربران عادی رد می‌شوند |
| 3 | Public | همه | — |

- stage سراسری: env `AI_ROLLOUT_STAGE` (پیش‌فرض **0** = fail-safe production).
- override هر bot: `bots.ai_settings['ai_rollout_stage']` (Nova → ربات).
- هر capability flag مستقل است (برای Stage 2) — خاموشی OCR، LLM را خاموش نمی‌کند.
- محدودیت زمانی AI (پنجره Tehran §5) در همه stageها اعمال می‌شود؛ رفع محدودیت
  plan فقط از مسیر **pro-override ثبت‌شده** (audit log دارد).

### پیش‌نیازهای عبور از هر stage (brief §11) — از داده واقعی

1. `ai:stage-report --stage=N --json` → `jobs_24h.total >= 20`
2. `error_rate_24h <= 5%`
3. `dead_letters_24h == 0`
4. `latency_24h.p95_ms <= 10000`
5. kill switch تست‌شده باشد (خاموش کردن `ai_global_enabled` همه چیز را off کند)

بررسی خودکار این ۵ شرط در خروجی `gate_criteria` دستور `ai:stage-report` وجود
دارد؛ اما **تصویب عبور فقط توسط owner** (brief §12).

---

## ۳. Feature matrix — bot × capability (brief §3)

دستور: `php artisan ai:rollout-status` (یا `--json`)

Precedence (همان لایه‌های `AiFeatureGate`):

```
L0   kill switch سراسری (ai_global_enabled) خاموش  → همه off
L2   override bot (bots.ai_settings)              → مقدار آن
L0.5 flag سراسری (ai_flags table / env AI_FLAG_*) → مقدار آن
```

خروجی: stage مؤثر هر bot + allowlist + وضعیت 🟢/🔴 هر capability.

---

## ۴. پنل مدیریت Nova (brief §4)

| Resource | دسترسی | توضیح |
|----------|--------|-------|
| `AiFlag` | ویرایش | override flag سراسری؛ **هر تغییر → audit log** |
| `Bot` | ویرایش (بخش AI) | ۶ capability (follow/on/off)، test group (allowlist)، rollout stage، pro override |
| `AiJob` | فقط-خواندنی | queue/jobs + state |
| `AiSettingsAuditLog` | فقط-خواندنی | تاریخچه همه تغییرات AI (actor، زمان، reason) |
| `AiFeedback` | فقط-خواندنی | بازخورد pilot (brief §8) |
| `AiProvider` / `AiProviderLog` | ویرایش/فقط-خواندنی | موجود از قبل |

تغییر flag **بدون re-deploy** اعمال می‌شود (L0.5 جدول `ai_flags` در DB).

---

## ۵. قانون زمان Tehran (brief §5)

| بازه (Asia/Tehran) | رفتار jobهای عادی |
|---------------------|--------------------|
| 00:00–09:00 | فوری |
| 09:00–14:00 | **DEFERRED** — flush روزانه 14:00 (UTC 10:30) |
| 14:00–24:00 | فوری |
| کاربران Pro | ۲۴ ساعته (بای‌پس پنجره) |

Jobهای DEFERRED قدیمی‌تر از 48h → EXPIRED (بدون شکست فرآیند عادی).

---

## ۶. مانیتورینگ (brief §6) — `/api/metrics?token=...`

سری‌های جدید `ai_job_*` (در کنار سری‌های موجود `ai_provider_*` و bot):

| سری | نوع | توضیح |
|-----|-----|-------|
| `ai_job_total{state}` | gauge | شمارش هر state (PENDING/QUEUED/PROCESSING/COMPLETED/DEFERRED/RETRYING/FAILED/CANCELLED/EXPIRED) |
| `ai_job_by_feature{feature}` | counter | مجموع jobها به تفکیک capability |
| `ai_job_dead_letter_total{window_hours="24"}` | counter | FAILED در ۲۴ ساعت |
| `ai_job_latency_avg_ms{window_hours="24"}` | gauge | میانگین started→completed |
| `ai_job_latency_p95_ms{window_hours="24"}` | gauge | P95 (nearest-rank) |
| `ai_job_tokens_total{window_hours="24"}` | counter | مجموع `usage.total_tokens` |
| `ai_job_est_cost_total{window_hours="24",currency="USD"}` | counter | از `config('ai.pricing')` (پیش‌فرض 0) |
| `ai_job_queue_depth` | gauge | PENDING+QUEUED+RETRYING+DEFERRED |

usage هر attempt موفق در `ai_jobs.usage` (JSON) ذخیره می‌شود.
اگر جدول `ai_jobs` نباشد، endpoint ۲۰۰ می‌دهد بدون این سری‌ها (guard).

---

## ۷. هشدارها (brief §7) — `ai:alerts-check` هر ۵ دقیقه

| Alert | شرط (env) | مقدار پیش‌فرض |
|-------|-----------|----------------|
| `queue_backlog` | jobs در انتظار > آستانه | `AI_ALERTS_QUEUE_BACKLOG=100` |
| `dead_letter:<error_code>` | هر error_code با حداقل ۱ FAILED در پنجره | پنجره `AI_ALERTS_DEAD_LETTER_HOURS=24` |
| `error_rate` | failed/(failed+completed) ≥ آستانه با حداقل حجم | `AI_ALERTS_ERROR_RATE_PCT=25`, `MIN_VOLUME=20`, پنجره 24h |

- **dedup**: هر alert تا `AI_ALERTS_COOLDOWN_SECONDS=3600` تکرار نمی‌شود (cache).
- **کانال امن**: `AiProviderService::notifyAllAdmins` (ربات مادر + ربات ادمین قرآن)
  — همان مسیر نوتیفیکیشن امن موجود؛ هرگز throw نمی‌کند.
- `--dry-run`: فقط بررسی، ارسال و ثبت cooldown ندارد.
- خاموش‌سازی: `AI_ALERTS_ENABLED=false`.

پیشنهاد آستانه‌های اولیه (بر اساس داده baseline): با شروع Pilot، یک هفته داده
جمع کنید و `ai:stage-report` را بررسی کنید؛ آستانه‌ها بر اساس baseline واقعی
تنظیم شوند (پیش‌فرض‌های بالا محافظه‌کارانه‌اند).

---

## ۸. بازخورد مشتری (brief §8)

جدول `ai_feedback` + resource Nova (فقط-خواندنی): feature، category
(general/bug/quality/missing/other)، comment، rating، status
(new/in_progress/resolved/ignored). جمع‌آوری از pilot از طریق flow موجود
بات‌ها؛ گزارش در `ai:stage-report` → `feedback` (total/open/by_category).

---

## ۹. Rollback & Kill Switch (brief §9)

| سطح | روش | تأثیر |
|-----|-----|-------|
| کل AI | flag `ai_global_enabled=false` (Nova/AiFlag یا env) | همه capabilityها off؛ **فرآیند عادی bot دست‌نخورده** |
| یک capability | flag `ai_<cap>_enabled=false` | فقط همان قابلیت |
| یک bot | override `off` در Nova → Bot | فقط آن bot |
| stage | کاهش `AI_ROLLOUT_STAGE` / override bot | جمع شدن دسترسی |
| هشدارها | `AI_ALERTS_ENABLED=false` | فقط alertها |
| metrics | حذف `observability.metrics_secret` | endpoint metrics بسته |

هر rollback **بدون re-deploy** است (flags در DB؛ env فقط برای تغییرات
سراسری‌تر که با edit `.env` اعمال می‌شود).

آزمود: `tests/Feature/AiPilotRolloutTest.php` (precedence kill switch بر
bot override) + `AiProductionMonitoringTest` (kill switch → همه off در ماتریس).

---

## ۱۰. فیلدهای گزارش مرحله‌ای (brief §10) — `ai:stage-report --stage=N --json`

```
stage, stage_name, current_global_stage, allowlisted_users,
bots_total, bots_by_stage,
jobs_24h {total, completed, failed, deferred},
latency_24h {avg_ms, p95_ms},
tokens_24h, est_cost_24h, error_rate_24h, dead_letters_24h,
feedback {total, open, by_category},
alerts_active, gate_criteria[]
```

بدون `--stage`، stage سراسری (config) را گزارش می‌دهد.

---

## ۱۱. Deploy checklist (cPanel، بدون SSH)

```
1. git pull
2. php artisan migrate            # 2 migration جدید: ai_jobs.usage + ai_feedback
3. php artisan config:clear
4. (اختیاری) env: AI_ROLLOUT_STAGE, AI_FLAG_*, AI_ALERTS_*, AI_PRICING_*
5. cron دقیقه‌ای موجود (schedule:run) خودش ai:alerts-check را فعال می‌کند
```

---

## ۱۲. محدودیت‌ها (brief §12)

- **بدون allowlist، Pilot عمومی وجود ندارد** (stage 1/2 fail-closed).
- **Stage 2 و 3 بدون تصویب owner نمی‌آیند.**
- عبور هر stage فقط با داده واقعی `ai:stage-report` و ۵ معیار §2.
