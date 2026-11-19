# AI Pilot Rollout — Final Report (AI-PILOT-ROLLOUT-05)

گزارش نهایی ۱۲‌ماده‌ای طبق brief §13. هر مرحله وضعیت
**READY / ACTIVE / PAUSED / ROLLBACK / COMPLETED** دارد.
حرف‌العصل: **خاموش/خرابی AI هرگز نباید عملکرد عادی botها را مختل کند.**

سند عملیاتی کامل: [`AI_PILOT_ROLLOUT.md`](AI_PILOT_ROLLOUT.md)

زنجیره commits این مأموریت (branch `15esfand1404`):

```
8e40bfbc  مأموریت 2: AI ENABLEMENT IMPLEMENTATION & RELEASE (پایه)
47441301  R1: rollout gate + audit
4cc4aa28  R2: Nova admin panel
56f35bee  R3: production monitoring
R4 (این کامیت): runbook + گزارش نهایی
```

---

## ماده ۱ — Precondition audit (brief §1)

| Gate | اعلام در brief | مدرک در ریپازیتوری | وضعیت |
|------|----------------|--------------------|--------|
| PREFLIGHT-01 | GO | ندارد (خروجی production) | **اعلام‌شده، مستقل تأیید نشده** |
| ROTATION-02 | VERIFIED | فرآیند موجود؛ مدرک چرخش فعلی ندارد | **اعلام‌شده، مستقل تأیید نشده** |
| DEPLOY-03 | DEPLOYED | کد در branch موجود؛ production از این محیط در دسترس نیست | **اعلام‌شده، مستقل تأیید نشده** |
| LIVE-ACCEPTANCE-04 | PASS | ندارد (مدرک live) | **اعلام‌شده، مستقل تأیید نشده** |

**نتیجه صادقانه:** production از این محیط (بدون SSH) قابل راستی‌آزمایی نیست؛
هر ۴ دروازه از brief پذیرفته شده‌اند اما مستقل تأیید نشده‌اند. تأیید نهایی
با owner است. هیچ stageای صرفاً بر اساس این ۴ دروازه «ACTIVE اعلام‌شده
است»؛ وضعیت‌های §5 زیر بر اساس **مدرک در ریپازیتوری** (کد + تست‌های سبز)
ستاده شده‌اند.

---

## ماده ۲ — Staged rollout gate (brief §2) — ✅ COMPLETED (R1)

- لایه L2.5 در [`AiFeatureGate`](../../app/Services/Ai/AiFeatureGate.php)
  بین bot-override و test-group: stage 0/1 fail-closed (بدون allowlist
  هیچ‌کس)، stage 2 فقط Pro، stage 3 همه.
- stage سراسری: env `AI_ROLLOUT_STAGE` (پیش‌فرض **0**).
- override هر bot: `bots.ai_settings['ai_rollout_stage']`
  ([`Bot::setAiRolloutStage`](../../app/Models/Bot.php)).
- limit زمانی AI در همه stageها اعمال می‌شود؛ رفع limit فقط با
  **pro-override ثبت‌شده** (audit log).
- allowlist خالی/غیاب → رد (fail-closed).

مدرک: commit `47441301`، [`AiPilotRolloutTest`](../../tests/Feature/AiPilotRolloutTest.php)
(۲۵ تست).

---

## ماده ۳ — Feature matrix: bot × capability (brief §3) — ✅ COMPLETED (R3)

- دستور `php artisan ai:rollout-status [--json]`
  ([`AiRolloutStatus`](../../app/Console/Commands/AiRolloutStatus.php)):
  برای هر bot → stage مؤثر (override ?? global) + allowlist + وضعیت هر
  capability با precedence: **kill switch → bot override → global flag**.
- ۶ capability: llm_chat, ocr, asr, rag, tts, growth_companion.
- هر capability flag **مستقل** است (Stage 2) — خاموشی OCR، LLM را خاموش
  نمی‌کند.

مدرک: [`AiProductionMonitoringTest::test_rollout_status_reports_bot_capability_matrix`](../../tests/Feature/AiProductionMonitoringTest.php).

---

## ماده ۴ — Nova admin panel + audit (brief §4) — ✅ COMPLETED (R2+R3)

| Resource | دسترسی | audit |
|----------|--------|-------|
| [`AiFlag`](../../app/Nova/AiFlag.php) | ویرایش (kill switch + ۵ capability) | ✅ afterCreate/Update/Delete → `ai_settings_audit_logs` |
| [`Bot`](../../app/Nova/Bot.php) (بخش AI) | ویرایش: ۶ capability (follow/on/off)، test group، rollout stage، pro override | ✅ همه تغییرات ai_settings با actor + reason |
| [`AiJob`](../../app/Nova/AiJob.php) | فقط-خواندنی (jobs view) | — |
| [`AiSettingsAuditLog`](../../app/Nova/AiSettingsAuditLog.php) | فقط-خواندنی (errors/settings history) | خودش log است |
| [`AiFeedback`](../../app/Nova/AiFeedback.php) | فقط-خواندنی | — |
| `AiProvider` / `AiProviderLog` | موجود از قبل | — |

- **تغییر flag بدون re-deploy**: layer L0.5 جدول `ai_flags` در DB
  ([`AiFlagService`](../../app/Services/Ai/AiFlagService.php)) — env فقط
  fallback است.
- تغییر flag بدون تغییر مقدار → ردیف audit تکراری نمی‌نویسد.
- `AiFlagService` هرگز throw نمی‌کند (fallback به config).

مدرک: commit `4cc4aa28`، [`AiAdminPanelTest`](../../tests/Feature/AiAdminPanelTest.php)
(۱۴ تست).

---

## ماده ۵ — وضعیت هر stage (brief §2/§11/§13)

| Stage | وضعیت | دلیل (مدرک‌محور) |
|-------|--------|------------------|
| **0 — Internal** | **ACTIVE** | وضعیت فعلی production: پیش‌فرض `AI_ROLLOUT_STAGE=0` = fail-safe، همه fail-closed. کد + ۲۵ تست Sبز. |
| **1 — Pilot** | **READY** | gate پیاده و تست‌شده (fail-closed، allowlist صریح)؛ **صبر برای**: تصویب owner + allowlist واقعی پیلوت (داده‌ای در production نیست). |
| **2 — All Pro** | **PAUSED** | **صبر برای**: ۲۴h+ داده stage 1 از `ai:stage-report` + تصویب owner (brief §12). |
| **3 — Public** | **PAUSED** | **صبر برای**: داده stage 2 + تصویب owner (brief §12). |

هیچ stageای PAUSE/ROLLBACK اجباری ندارد؛ هیچ incidentی ثبت نشده چون
production فعلاً در stage 0 (خاموش برای مشتری) است.

**پیش‌نیاز عبور هر stage (brief §11) — از داده واقعی:**
1. `jobs_24h.total >= 20`
2. `error_rate_24h <= 5%`
3. `dead_letters_24h == 0`
4. `latency_24h.p95_ms <= 10000`
5. kill switch تست‌شده (خاموش کردن `ai_global_enabled` همه را off کند)

این ۵ شرط در `gate_criteria` خروجی `ai:stage-report` خودکار چک می‌شود؛
**تصویب عبور فقط با owner** است.

---

## ماده ۶ — Production monitoring (brief §6) — ✅ COMPLETED (R3)

`/api/metrics?token=...` (secret-based) — سری‌های جدید `ai_job_*`:

| سری | نوع |
|-----|-----|
| `ai_job_total{state}` | gauge — ۹ state |
| `ai_job_by_feature{feature}` | counter |
| `ai_job_dead_letter_total` | counter (24h) |
| `ai_job_latency_avg_ms` / `ai_job_latency_p95_ms` | gauge (24h) |
| `ai_job_tokens_total` | counter (24h، از `ai_jobs.usage`) |
| `ai_job_est_cost_total{currency="USD"}` | counter (24h، `config('ai.pricing')`) |
| `ai_job_queue_depth` | gauge |

- usage هر attempt موفق در `ai_jobs.usage` (JSON) ذخیره می‌شود
  (migration `2026_11_15_130000`)؛ attempt شکست‌خورده usage نمی‌نویسد.
- guard: نبودن جدول `ai_jobs` → endpoint 200 بدون این سری‌ها (test:
  `test_metrics_without_ai_jobs_table_is_safe`).
- `ai:queue-status` برای مشاهده لحظه‌ای queue.

مدرک: commit `56f35bee`، [`AiProductionMonitoringTest`](../../tests/Feature/AiProductionMonitoringTest.php)
(۱۷ تست).

---

## ماده ۷ — Alerts (brief §7) — ✅ COMPLETED (R3)

`php artisan ai:alerts-check` هر ۵ دقیقه در schedule
(`withoutOverlapping` + `onOneServer`؛ cron دقیقه‌ای production همین را اجرا
می‌کند):

| Alert | شرط | env / پیش‌فرض |
|-------|-----|----------------|
| `queue_backlog` | jobs در انتظار > آستانه | `AI_ALERTS_QUEUE_BACKLOG=100` |
| `dead_letter:<error_code>` | حداقل ۱ FAILED در پنجره | `AI_ALERTS_DEAD_LETTER_HOURS=24` |
| `error_rate` | failed/(failed+completed) ≥ آستانه با حداقل حجم | `AI_ALERTS_ERROR_RATE_PCT=25`, `MIN_VOLUME=20` |

- **dedup**: cooldown 3600s (cache) — `AI_ALERTS_COOLDOWN_SECONDS`.
- **کانال امن**: `AiProviderService::notifyAllAdmins` (ربات مادر + ادمین
  قرآن) — همان مسیر نوتیفیکیشن امن موجود، هرگز throw نمی‌کند.
- `--dry-run`: بدون ارسال/ثبت cooldown. خاموش‌سازی: `AI_ALERTS_ENABLED=false`.
- دستور **هرگز نمی‌شکند** (exit 0 تضمینی) — مانیتورینگ نباید bot را بکند.

مدرک: [`AiAlertService`](../../app/Services/Ai/AiAlertService.php)،
[`AiAlertsCheck`](../../app/Console/Commands/AiAlertsCheck.php)، تست‌های
`test_*alert*` در AiProductionMonitoringTest.

---

## ماده ۸ — Customer feedback (brief §8) — ✅ COMPLETED (R3)

- جدول `ai_feedback` (migration `2026_11_15_130100`) + مدل
  [`AiFeedback`](../../app/Models/AiFeedback.php): feature، category
  (general/bug/quality/missing/other)، comment، rating، status.
- Resource Nova فقط-خواندنی برای جمع‌بندی بازخورد پیلوت.
- در `ai:stage-report` → `feedback {total, open, by_category}`.

---

## ماده ۹ — Rollback & Kill Switch (brief §9) — ✅ COMPLETED (R1+R2)

| سطح | روش | نیاز به re-deploy؟ |
|-----|-----|---------------------|
| کل AI | `ai_global_enabled=false` (Nova/AiFlag یا env) | **خیر** |
| یک capability | `ai_<cap>_enabled=false` | **خیر** |
| یک bot | override off در Nova → Bot | **خیر** |
| stage | کاهش `AI_ROLLOUT_STAGE` / override bot | **خیر** |
| alertها | `AI_ALERTS_ENABLED=false` | **خیر** |
| metrics | حذف `observability.metrics_secret` | **خیر** |

خاموش بودن AI هرگز processing عادی پیام‌ها/callbackها را قطع نمی‌کند —
gate بسته = همان رفتار قبلی bot (تست‌شده در همه test files مأموریت 2:
webhook flow intact when gate closed).

---

## ماده ۱۰ — Test evidence (TDD)

- **کل suite: 1014 passed / 0 failed / 2 skipped / 5987 assertions / exit 0**
  (اجرای کامل پس از R3).
- تست‌های این مأموریت:
  - [`AiPilotRolloutTest`](../../tests/Feature/AiPilotRolloutTest.php) — 25 تست (R1)
  - [`AiAdminPanelTest`](../../tests/Feature/AiAdminPanelTest.php) — 14 تست (R2)
  - [`AiProductionMonitoringTest`](../../tests/Feature/AiProductionMonitoringTest.php) — 17 تست (R3)
  - ۱۹۹ تست جدید + ۲ deprecated-marked.
- TDD رعایت شد: در هر round اول تست RED، سپس production code، سپس GREEN.

---

## ماده ۱۱ — Deploy & rollback plan (cPanel، بدون SSH)

```
1. git pull
2. php artisan migrate        # 2 migration جدید R3: ai_jobs.usage + ai_feedback
                              # (migrations R1/R2: ai_settings_audit_logs + ai_flags
                              #  باید قبلاً اجرا شده باشند — commit 47441301/4cc4aa28)
3. php artisan config:clear
4. (اختیاری) .env: AI_ROLLOUT_STAGE, AI_FLAG_*, AI_ALERTS_*, AI_PRICING_*
5. cron دقیقه‌ای موجود (schedule:run) خودکارسcheduled را فعال می‌کند:
   ai:health-check (hourly), ai:queue-work (min), ai:queue-flush-deferred
   (daily 10:30 + 10min), ai:queue-recover-stale (10min),
   ai:alerts-check (5min), ai:daily-health-report (daily 09:00)
```

Rollback deploy: revert به commit قبلی + (در صورت نیاز) `migrate:rollback`
برای ۲ migration جدید — هیچ‌کدام column/drop تخریبی روی جداول عادی ندارند
(فقط column اضافه + جدول جدید).

---

## ماده ۱۲ — Request owner approval

**فقط stage با مدرک‌محور پیشنهاد می‌شود:**

> **تصویب Stage 0 (Internal) به‌عنوان baseline production** —
> ACTIVE فعلی، fail-safe، همه capabilityها fail-closed، بدون تغییر رفتار
> مشتری. این فقط تأیید رسمی وضعیت موجود است؛ اقدامی لازم ندارد جز ثبت.

**برای شروع Stage 1 (Pilot) از owner لازم است:**
1. allowlist صریح کاربران پیلوت (idهای واقعی) — بدون آن stage 1
   fail-closed می‌ماند و هیچ‌کس AI را نمی‌بیند.
2. تصویب رسمی عبور Stage 0 → 1 (brief §12).

Stage 2 و 3 بدون داده `ai:stage-report` از stage قبلی و تصویب owner
آغاز **نمی‌شوند** (brief §12).

---

## محدودیت‌ها (brief §12) — رعایت‌شده

- بدون allowlist، Pilot عمومی وجود ندارد (stage 1/2 fail-closed). ✅
- Stage 2/3 بدون تصویب owner. ✅ (PAUSED)
- عبور stage فقط با داده واقعی. ✅ (gate_criteria در ai:stage-report)
