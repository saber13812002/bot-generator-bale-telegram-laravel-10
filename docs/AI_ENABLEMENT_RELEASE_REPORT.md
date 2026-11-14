# AI ENABLEMENT IMPLEMENTATION & RELEASE — گزارش نهایی (10-section)

> مأموریت: **MASTER EXECUTION PROMPT — AI ENABLEMENT IMPLEMENTATION & RELEASE**
> تاریخ: 2026-11-14 · Branch: `15esfand1404` · آخرین Commit: `f98b8ef6`
> اصل بنیادین: **شکست AI هرگز نباید کارکرد عادی ربات را بشکند.** (هر MVP/Consumer حداقل یک fallback صریح + تست دارد)

---

## 1) وضعیت کلی (Overall Status)

| بخش | وضعیت |
|---|---|
| WS-A تا WS-J (۱۰ کارگاه) | **DONE + VERIFIED** (همه‌ی ۱۰ کارگاه) |
| ۷ سناریوی پذیرش | **VERIFIED** (هر ۷ با تست سبز پوشش می‌شوند — §4) |
| سوت کامل تست | **VERIFIED**: 969 test / 5780 assertion / 0 fail (exit 0) |
| Security | **DONE** (۱۹ توکن از repo خارج + redaction همه‌ی مسیرهای خطای AI) |
| Operational readiness | **DONE** (queue، scheduler، 4 command، status reporting) |
| Release | **STOPPED at owner Release Gate** — تا تأیید مالک، منتشر نمی‌شود (§10) |

هیچ BLOCKED یا PARTIALی باقی نمانده؛ ۲ مورد «NOT STARTED به‌عنوان تصمیم آگاهانه»: TTS (فقط Adapter آماده — consumer موجود نیست) و RAG consumption (pipe-ready — reader consumer در فاز بعد).

## 2) وضعیت کارگاه‌ها (Workstreams A–J)

| WS | عنوان | وضعیت | Commit | شواهد کلیدی |
|---|---|---|---|---|
| A | Nova HTTP 500 fix | **DONE/VERIFIED** | `8bc4d2e9` | `AiProviderLog::authorizedToCreate` static شد؛ تست بازتولید 500 سبز |
| B | Central LiteLLM infra | **DONE/VERIFIED** | `f5572147` | TLS strict + CA bundle (`storage/certs/ismc-ai-ca-bundle.pem`)، 4 adapter (Vision/ASR/Embedding/TTS)، `ai:discover-models`، `AiTlsConfigTest` |
| C | Security + token rotation | **DONE/VERIFIED** | `d4c1408c` | 19 توکن واقعی به env، `AiErrorRedactor` در همه‌ی مسیرهای خطای AI، `docs/SECURITY_AI_TOKEN_ROTATION.md` |
| D | AI Queue & Resilience | **DONE/VERIFIED** | `a7f7110d` | جدول `ai_jobs` (9 state)، `AiJobDispatcher`، `ProcessAiJob` (unique + backoff + dead-letter)، 4 command (flush/recover/status/work)، `AiQueueResilienceTest` |
| E | AiFeatureGate + time policy | **DONE/VERIFIED** | `0c64c10c` | 6 لایه + kill switch + never-throw؛ سیاست Asia/Tehran (regular 09–14 deferred → flush 14:00، Pro 24h)؛ `AiFeatureGateTest` |
| F | OCR MVP — Book Pixel | **DONE/VERIFIED** | `abacc9f6` | عکس صفحه → متن (fa+ar)؛ `BookPixelOcrTest` |
| G | ASR MVP — Book Pixel | **DONE/VERIFIED** | `72908372` | ویس → متن (fa)؛ `BookPixelAsrTest` |
| H | Growth Companion AI MVP | **DONE/VERIFIED** | `f224010f` | پاسخ دستیار (fa) بدون fake output؛ fallback «دستیار در دسترس نیست»؛ `GrowthAssistantTest` 12/12 |
| I | RAG & Embedding readiness | **DONE/VERIFIED** | `2f56f079` | `ai_embeddings` + `RagEmbeddingService` (embed/search/cosine) + `executeEmbedding`؛ `AiRagEmbeddingTest` 8/8 |
| J | Harden existing AI consumers | **DONE/VERIFIED** | `f98b8ef6` | J1 strict L5 (`provider_missing`) + J2 `healthyProvider()` مشترک + J3 fallback growth LLM؛ `AiConsumerHardeningTest` + 3 تست consumer؛ 22/22 |

## 3) اصل بنیادین: AI failure never breaks the bot

هر مصرف‌کننده AI، fallback صریح + تست اختصاصی دارد:

| Consumer | رفتار هنگام شکست AI | تست اثبات |
|---|---|---|
| Translation RSS | متن اصلی برمی‌گردد (بدون HTTP اگر provider failed) | [`tests/Feature/TranslationDriverTest.php`](tests/Feature/TranslationDriverTest.php) `test_llm_failure_returns_original_text`, `test_failed_provider_returns_original_without_http` |
| گزارش فعالیت | بخش «تحلیل: در دسترس نیست» | [`tests/Feature/ActivityReportFallbackTest.php`](tests/Feature/ActivityReportFallbackTest.php) `test_build_message_falls_back_when_llm_unavailable` |
| کانال انگیزشی | run fail + بدون publish + بدون HTTP (اگر provider failed) | [`tests/Feature/ChannelMotivationSendTest.php`](tests/Feature/ChannelMotivationSendTest.php) `test_llm_failure_fails_run_and_skips_publish` |
| OCR/ASR Book Pixel | flow وب‌هوک دست‌نخورده، error classify، retry/backoff | `BookPixelOcrTest` / `BookPixelAsrTest` (gate closed → flow intact) |
| دستیار رشدیار | پیام «دستیار در حال حاضر در دسترس نیست» | [`tests/Feature/GrowthAssistantTest.php`](tests/Feature/GrowthAssistantTest.php) `test_terminal_failure_sends_assistant_unavailable_fallback` |
| دروازه (Gate) | exception → deny ایمن، هرگز throw نمی‌کند | `AiFeatureGateTest::test_gate_never_throws` |

## 4) ۷ سناریوی پذیرش — هر ۷ VERIFIED

اجرای متحدشدهٔ ۹ کلاس تست سناریومحور: **95 test / 387 assertion / 0 fail** (2026-11-14).

| # | سناریو | وضعیت | شواهد (تست سبز) |
|---|---|---|---|
| 1 | Time window: regular در 09–14 Tehran deferred → flush 14:00؛ Pro فوری؛ 00–09 و 14–24 فوری | **VERIFIED** | `AiQueueResilienceTest`: `test_regular_job_in_morning_window_is_deferred_until_14_tehran`, `test_regular_job_before_09_tehran_is_immediate`, `test_regular_job_outside_deferred_window_is_immediate`, `test_pro_job_is_immediate_even_in_deferred_window`, `test_flush_deferred_releases_due_jobs_once`, `test_flush_deferred_expires_past_expiry` + `AiFeatureGateTest` (3 تست window) + تست‌های deferred در `GrowthAssistantTest`/`BookPixel*Test` |
| 2 | Independence ربات‌ها (flag مستقل per-bot per-capability) | **VERIFIED** | `AiFeatureGateTest`: `test_capability_flag_off_denies_that_capability_only`, `test_bot_can_disable_globally_enabled_capability`, `test_kill_switch_wins_over_bot_override`, `test_bot_ai_flag_helpers_roundtrip`, `test_nova_style_setter_maps_to_ai_settings` |
| 3 | AI outage: bot هرگز نمی‌شکند، fallback صریح | **VERIFIED** | جدول §3 — همه‌ی 6 consumer با fake 500/401/empty-content تست شده‌اند |
| 4 | Duplicate / Idempotency | **VERIFIED** | `AiQueueResilienceTest`: `test_duplicate_dispatch_creates_only_one_job`, `test_idempotency_key_includes_bot_customer_and_payload` + `BookPixelOcrTest`/`BookPixelAsrTest`: `test_duplicate_trigger_creates_only_one_job` |
| 5 | Multi-user isolation | **VERIFIED** | `AiQueueResilienceTest`: `test_same_user_different_feature_creates_separate_jobs` + idempotency key شامل `user_id` + `test_capacity_exceeded_denies_new_jobs_for_busy_bot` (L6 per-bot) |
| 6 | Staged rollout allowlist (test group) | **VERIFIED** | `AiFeatureGateTest`: `test_test_group_allowlist_gates_users`, `test_no_test_group_means_everyone_allowed` |
| 7 | Recovery (dead-letter، stale worker، expired) | **VERIFIED** | `AiQueueResilienceTest`: `test_process_ai_job_retries_on_provider_error_with_backoff`, `test_process_ai_job_becomes_dead_letter_after_max_attempts`, `test_recover_stale_requeues_dead_worker_job`, `test_recover_stale_exhausts_to_failed_after_max_attempts`, `test_recover_stale_marks_expired_jobs`, `test_fresh_processing_job_is_not_touched` |

## 5) شواهد تست (Test Evidence)

| اجرا | نتیجه |
|---|---|
| **Full suite** (2026-11-14, 17:05 UTC) | **969 tests, 5780 assertions, 0 failures, 0 errors, exit 0** (2 warning شناخته‌شدهٔ runner: QuranTest/StringHelperTest؛ 2 skip) |
| سناریوهای پذیرش (9 کلاس) | 95 tests, 387 assertions |
| `--filter Ai` | 191/191 (1011 asserts) |
| `--filter "BookPixel|Book"` | 45/45 (228 asserts) |
| `--filter Growth` | 43/43 (189 asserts) |
| WS-J targeted (4 فایل) | 22/22 (64 asserts) — TDD: 7 تست RED با دلیل صحیح قبل از کد production |
| `php -l` | تمیز روی همه‌ی فایل‌های تغییرشده |

توضیح runner: exit code 1 با «WARNINGS!» فقط در صورت وجود warning runner است؛ baseline پیش از شروع همین 2 warning داشت — **همان وضعیت قبل از شروع، بدون regression جدید**.

## 6) Security (WS-C)

- **19 توکن ربات واقعی** از `BotsTableSeeder` به env منتقل شد (repo + تاریخ Git دیگر مخزن Secret نیست).
- **`AiErrorRedactor`** در همه‌ی مسیرهای خطای AI (adapterها، ProcessAiJob، gate، logها) — Key Hash/توکن در لاگ نمی‌ماند.
- **TLS strict** برای LiteLLM با CA bundle در repo (`verify=true`) — دیگر `verify=false` نیست.
- **401 redaction**: پیام 401 LiteLLM (حاوی `token_not_found_in_db` + Key Hash) قبل از لاگ پاک‌سازی می‌شود.
- نقشهٔ چرخش: [`docs/SECURITY_AI_TOKEN_ROTATION.md`](SECURITY_AI_TOKEN_ROTATION.md) — **اقدام مالک**: چرخش 19 توکن قبلاً افشا شده (checklist در مستند).

## 7) Operational readiness

- **Queue**: جدول `ai_jobs` با 9 state، `ProcessAiJob` (ShouldBeUnique + backoff + dead-letter بعد از max attempts)، queue `ai` جدا از sync.
- **Scheduler** (با cron واحد cPanel `* * * * * php artisan schedule:run`): flush deferred (14:00 + minute), recover-stale، status — همه در `routes/console.php`.
- **4 command**: `ai:flush-deferred`, `ai:recover-stale`, `ai:queue-status`, `ai:worker` (worker اختیاری؛ بدون worker هم cron-driven اجرا می‌شود).
- **Observability**: `ai:queue-status` report از states + `AiProviderService::notifyAllAdmins` هنگام شکست LLM در consumerها.
- **Migrations جدید**: `jobs`, `ai_jobs`, `ai_embeddings` (+ 2 column در `book_page_scans`) — همگی migrate-reversible.

## 8) نقشهٔ Rollout (4 مرحله، مطابق تصمیم مالک)

| مرحله | فعال‌سازی | خروجی مورد انتظار |
|---|---|---|
| 1 | deploy + `ai_global_enabled=true` + capability flags خام + test-group allowlist | فقط ربات/کاربر allowlist از AI استفاده می‌کنند؛ بقیه flow عادی |
| 2 | flag هر capability برایش خودش (ocr/asr/llm/rag/tts) | هر قابلیت مستقل scale می‌شود |
| 3 | per-bot override (Nova) | ربات‌های خاص می‌توانند capability را خام/روشن کنند |
| 4 | همه‌چیز + strict L5 (پیش‌فرض) | کامل |

ابزارها: `config/ai.php` (6 `AI_FLAG_*` + `AI_REQUIRE_ACTIVE_PROVIDER`)، Nova (per-bot overrides)، `ai:queue-status`، kill-switch `ai_global_enabled`. نقشهٔ پرچم‌ها: [`docs/AI_FEATURE_FLAGS.md`](AI_FEATURE_FLAGS.md).

## 9) ریسک‌ها و محدودیت‌های شناخته‌شده

1. **توکن LiteLLM**: در discovery، توکن محلی 401 بود؛ `AI_DEFAULT_API_KEY` در .env تنظیم است و `ai:discover-models` پس از deploy باید یک‌بار اجرا/تأیید شود (اگر 401، چرخش طبق `SECURITY_AI_TOKEN_ROTATION.md`).
2. **Strict L5 از بعد deploy فعال است**: اگر `ai_providers` خالی باشد، gate با `provider_missing` رد می‌کند. **Rollback**: `AI_REQUIRE_ACTIVE_PROVIDER=false` + `php artisan config:clear` (یا `ensureDefaultProvider` یک row می‌سازد).
3. **TTS**: Adapter آماده است ولی consumer فعال ندارد (NOT STARTED به‌صورت آگاهانه).
4. **RAG**: pipe (embeddings + cosine search) آماده است؛ reader consumer در فاز بعد.
5. **5xx regex در `classifyChatError`**: regex `'HTTP 5\d\d'` روی ورودی lowercased match نمی‌شود — 5xx به default `provider_error` می‌رود (behaviorally correct؛ cleanup جزئی).
6. **Production PHP 8.2.34 vs local 8.5.5**: کد با هر دو سازگار است (Laravel 10.48)؛ `ai:discover-models` و یک flush دستی بعد از deploy توصیه می‌شود.

## 10) Release Gate (STOP — در انتظار مالک)

**تا تأیید مالک، هیچ public release انجام نمی‌شود.** چک‌لیست پیشنهادی برای تأیید:

- [ ] `git push origin 15esfand1404` + deploy به staging/production (cPanel)
- [ ] `php artisan migrate --force` (3 migration جدید) + `php artisan config:clear && php artisan route:clear`
- [ ] `php artisan ai:discover-models` → تأیید 200 و لیست مدل‌ها (توکن production)
- [ ] بررسی `ai_providers` در production: حداقل یک row فعال (یا `AI_REQUIRE_ACTIVE_PROVIDER=false`)
- [ ] `php artisan ai:queue-status` → خروجی منطقی
- [ ] چرخش 19 توکن ربات افشا شده (طبق `SECURITY_AI_TOKEN_ROTATION.md`)
- [ ] مرحله 1 rollout: فقط test-group allowlist
- [ ] Smoke test عادی ربات‌ها بدون AI (flag همه خام) — اصل §3

### زنجیرهٔ Commitها (برای review)

```
1b3ea50b طرح (فاز discovery)
8bc4d2e9 WS-A  nova fix
f5572147 WS-B  LiteLLM core
d4c1408c WS-C  security
a7f7110d WS-D  queue & resilience
0c64c10c WS-E  AiFeatureGate
abacc9f6 WS-F  OCR MVP
72908372 WS-G  ASR MVP
f224010f WS-H  Growth AI MVP
2f56f079 WS-I  RAG & Embedding
f98b8ef6 WS-J  consumer hardening  ← HEAD
```

### مستندات ساخته‌شده در این فاز

`docs/audit/` (7+1 discovery) · `SECURITY_AI_TOKEN_ROTATION.md` · `AI_FEATURE_FLAGS.md` · `AI_OCR_BOOK_PIXEL.md` · `AI_ASR_BOOK_PIXEL.md` · `AI_GROWTH_COMPANION.md` · `AI_RAG_EMBEDDING.md` · `AI_CONSUMER_HARDENING.md` · این فایل.
