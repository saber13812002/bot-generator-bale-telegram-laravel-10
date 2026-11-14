# WS-H — Growth Companion AI: دستیار متن آزاد (assistant reply MVP)

مأموریت: کاربر بتواند به‌جای دکمه‌ها یک پیام آزاد به رشدیار بنویسد و از طریق صف مشترک AI
پاسخ LLM بگیرد — بدون اینکه شکست AI هرگز جریان عادی bot را خراب کند.

## چه چیزی پیاده شد (MVP)

- **Trigger (همزمان با webhook)**: در `GrowthCompanionController::handlePrivateMessage`،
  بعد از onboarding، هر متن آزاد (غیر از `/command`) که `ai_consent` فعال باشد:
  1. `GrowthAssistantService::triggerForMessage()` → `AiFeatureGate::check('growth_companion', ...)`
  2. ثبت ردیف `growth_assistant_messages` با `role=user`
  3. `AiJobDispatcher::dispatch('growth_companion', ...)` → job در `ai_jobs`
  4. controller پیام «🤖 در حال فکرم؛ پاسخ همین‌جا می‌آید…» می‌فرستد
  - هر خطا (gate بسته، بی‌consent، exception) → fallback به کارت خانه — **webhook هرگز نمی‌افتد**.
- **Executor (در صف `ai`)**: `ProcessAiJob` → `GrowthAssistantService::execute()`:
  - prompt از `resources/growth_prompts/companion.v1.md` با context:
    mode/intensity/notify_time + برنامه‌های فعال (focus) + چک‌این‌های ۷ روز اخیر + ۳ جواب اخیر
  - یک call به `AiProviderService::chat()` (LiteLLM، model از `AI_GROWTH_COMPANION_MODEL`)
  - موفقیت → ردیف `role=assistant` + `usage` + `result_reference=assistant_message:{id}` → ارسال به کاربر
  - شکست → `error_code` روی ردیف user؛ retry با backoff توسط `ProcessAiJob`
  - attempt ترمینال (پس از ۵ تلاش) → پیام کوتاه «دستیار موقتاً در دسترس نیست»
  - **پاسخ «قبل» از ارسال روی DB ثبت می‌شود؛ شکست ارسال bot را نمی‌شکند.**
- **Time policy**: Pro 24h فوری؛ regular در پنجره 09–14 تهران → `DEFERRED` تا flush 14:00.
- **طبقه‌بندی خطا**: `auth_failed` (401/403)، `model_not_found` (404)، `rate_limited` (429)،
  `provider_error` (HTTP 5xx)، `provider_unavailable` (503/timeout) — منطبق بر `ProcessAiJob::classifyChatError`.

## فایلهای اضافه/تغییرشده

| فایل | نقش |
| --- | --- |
| `app/Services/GrowthCompanion/GrowthAssistantService.php` | trigger + executor |
| `app/Models/GrowthAssistantMessage.php` | model مکالمه |
| `database/migrations/2026_11_14_160000_create_growth_assistant_messages_table.php` | جدول مکالمه |
| `resources/growth_prompts/companion.v1.md` | prompt v1 (فارسی) |
| `app/Jobs/ProcessAiJob.php` | arm `'growth_companion'` + `executeGrowthCompanion()` |
| `app/Http/Controllers/GrowthCompanionController.php` | `tryAssistantMessage()` + fallback home card |
| `lang/{fa,en}/growth_companion.php` | `assistant_thinking` / `assistant_unavailable` |
| `config/ai.php` | `growth_companion_model` → `env('AI_GROWTH_COMPANION_MODEL')` |
| `tests/Feature/GrowthAssistantTest.php` | 12 تست (RED→GREEN) |
| `tests/UsesGrowthCompanionSqlite.php` | mirror جدول‌های `ai_jobs` + `growth_assistant_messages` |

## گیت فعال‌سازی (staged rollout)

1. `ai.flags.ai_global_enabled` (kill switch)
2. `ai.flags.ai_growth_companion_enabled` (capability)
3. per-bot override در `bots.ai_settings` (`aiFlag('growth_companion')`)
4. test-group allowlist (اختیاری)
5. `ai_consent` روی profile کاربر (در onboarding)

خروجی جعلی نداریم: اگر LLM خراب/خالی جواب دهد، هیچ متن fake به کاربر نمی‌رود — یا retry یا
پیام کوتاه «در دسترس نیست» در attempt ترمینال.

## Defer (WS-H2)

- follow-up هوشمند («۲ روز دیگر یادم بیاور») → نیاز به job زمان‌دار جدا
- پاراگراف LLM در گزارش هفتگی
- وویس (ASR ورودی / TTS خروجی) روی همین مسیر assistant

## شواهد

- `vendor\bin\phpunit --filter GrowthAssistantTest` → **12/12، 76 assertions**
- رگرسیون: `--filter Growth` 41/41 · `--filter Ai` 173/173 · `--filter "BookPixel|Book"` 45/45
- TDD: ابتدا 12 تست RED (4 error «class not found» + 8 failure «home card / no job»)
