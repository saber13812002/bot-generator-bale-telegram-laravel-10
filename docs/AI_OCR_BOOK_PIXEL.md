# WS-F — OCR MVP in Book Pixel

**Status:** DONE (11/11 feature tests green, no regressions)
**Branch:** `15esfand1404`

## Overview

When a user sends a **page scan** in the Book Pixel bot, the scan photo is
(optionally) sent through the shared AI vision pipeline (LiteLLM) to extract
the page text (Persian/Arabic). The extracted text is persisted on
`book_page_scans.ocr_text` and can later power RAG/search/translation
workstreams (WS-I, WS-J).

**Core principle:** OCR failure must **never** break normal bot functionality.
The normal scan flow (draft → scan record → moderation) is identical whether
OCR is enabled, deferred, or failing.

## Flow

```
Webhook (photo, state=waiting_scan)
  └─ handleScanPhoto()
       ├─ createBookPage / saveDraft / createScan          ← unchanged
       ├─ sendToModerationGroup                             ← unchanged
       ├─ sendMessage 'book_pixel_scan_sent_for_approval'   ← unchanged
       └─ BookPixelOcrService::triggerForScan()             ← NEW (try/Throwable)
            ├─ AiFeatureGate::check('ocr', bot, user, isPro)
            │    L0 unknown → L0.5 global → L1 flag → L2 bot override
            │    → L3 test group → L4 Tehran 09–14 window → L5 provider health
            │    → L6 per-bot capacity
            └─ AiJobDispatcher::dispatch('ocr', payload)
                 ├─ idempotent (idempotency_key UNIQUE)
                 ├─ regular in 09–14 Tehran → DEFERRED, due_at = 14:00 Tehran flush
                 ├─ Pro / outside window  → QUEUED + push ProcessAiJob (queue 'ai')
                 └─ bot receives 'book_pixel_ocr_started' only when dispatched

ProcessAiJob (worker, queue 'ai')
  └─ executeOcr() → BookPixelOcrService::execute($job)
       1. GET {platform}/bot{token}/getFile?file_id=...        → file_path
       2. GET {platform}/file/bot{token}{file_path}            → image bytes
          (platform: api.telegram.org or tapi.bale.ai)
       3. temp file storage/app/ai-tmp → VisionAdapter::extractText()
          (data-URI POST https://ai.ismc.ir/api/v1/chat/completions)
       4. persist:
            success  → ocr_text = text, ocr_error = null, ocr_at = now
            failure  → ocr_text = NULL (kept), ocr_error = code, ocr_at = now
       5. job state via ProcessAiJob handleFailure
          (backoff [30,120,300]s, max_attempts 5, dead letter FAILED + admin notify)
```

Temporary image files are deleted in `finally` after each attempt.

## "No fake output" guarantee

- Empty/whitespace vision content → error code `empty_result`, job is
  **RETRYING** (not COMPLETED), `ocr_text` stays `NULL`.
- No fallback text, no partial text on error: on any failure `ocr_text`
  retains its previous value (NULL until a successful run).
- Consumers must therefore read `ocr_error` / `ocr_at` to know the last
  attempt status, and treat `ocr_text IS NULL` as "not yet extracted".

## Error codes (ai_jobs.error_code + book_page_scans.ocr_error)

| code                 | source                          | retryable |
|----------------------|---------------------------------|-----------|
| `file_get_failed`    | platform `getFile` HTTP ≥400    | yes       |
| `file_download_failed` | platform image download ≥400 / empty | yes |
| `file_not_found`     | temp file unreadable (VisionAdapter) | yes   |
| `auth_failed`        | vision 401/403                  | yes       |
| `model_not_found`    | vision 404                      | yes       |
| `provider_error`     | vision ≥500                     | yes       |
| `rate_limited`       | vision 429                      | yes       |
| `http_error`         | other vision HTTP error         | yes       |
| `empty_result`       | vision 200 but blank text       | yes       |
| `exception`          | vision request exception        | yes       |
| `scan_not_found`     | payload scan_id missing         | yes (dead letter if persistent) |
| `no_bot_token`       | bot row/token missing           | yes (dead letter if persistent) |

## Schema

`database/migrations/2026_11_14_140000_add_ocr_columns_to_book_page_scans_table.php`

| column      | type                     | notes                    |
|-------------|--------------------------|--------------------------|
| `ocr_text`  | LONGTEXT nullable        | extracted text (success only) |
| `ocr_error` | VARCHAR(120) nullable    | last failed attempt code |
| `ocr_at`    | TIMESTAMP nullable       | last attempt time (UTC)  |

## Configuration

- `AI_FLAG_OCR=true` (config `ai.flags.ai_ocr_enabled`) — capability flag
- `AI_VISION_MODEL` — vision model for OCR (falls back to provider setting /
  `AI_DEFAULT_MODEL`)
- `AI_VISION_OCR_PROMPT` — OCR prompt (default: Persian/Arabic extraction,
  preserve line/paragraph order, text-only output)
- Time policy: regular users 09:00–14:00 **Asia/Tehran** → job DEFERRED until
  the 14:00 flush; Pro users 24/7. (Owner decision #2)
- Per-bot override + test-group allowlist via Nova `Bot` resource →
  `ai_settings` JSON (WS-E).

## Rollback

1. Set `AI_FLAG_OCR=false` → gate denies at L1 → no new jobs, normal flow
   intact. In-flight jobs finish and are persisted or retried normally.
2. Schema rollback: `php artisan migrate:rollback --step=1`
   (only if the WS-F migration is the latest one applied).
3. Feature flag is per-capability and per-bot, so a single bad bot can be
   disabled via `ai_settings.ocr = false` without touching the global flag.

## Test matrix (tests/Feature/BookPixelOcrTest.php — 11 tests)

| # | test | covers |
|---|------|--------|
| 1 | `test_webhook_scan_photo_dispatches_ocr_job_when_gate_open` | webhook → scan → job QUEUED + queue push |
| 2 | `test_webhook_scan_photo_flow_intact_when_ocr_gate_closed` | gate closed → normal flow, zero jobs |
| 3 | `test_executor_persists_ocr_text_on_success` | success path → ocr_text, result_reference |
| 4 | `test_executor_retries_on_provider_500` | provider_error → RETRYING + ocr_error |
| 5 | `test_executor_classifies_404_as_model_not_found` | 404 → model_not_found |
| 6 | `test_executor_retries_when_file_get_fails` | getFile 400 → file_get_failed |
| 7 | `test_executor_persists_error_when_download_fails` | download 404 → file_download_failed |
| 8 | `test_empty_vision_content_is_empty_result_not_fake_text` | blank vision → empty_result, ocr_text NULL |
| 9 | `test_pro_user_in_deferred_window_is_immediate` | Pro bypasses Tehran 09–14 window |
| 10 | `test_regular_user_in_deferred_window_is_deferred_until_14_tehran` | DEFERRED + due_at = 14:00 Tehran (10:30 UTC), no queue push |
| 11 | `test_duplicate_trigger_creates_only_one_job` | idempotency → 1 job |

Verified: `vendor\bin\phpunit --filter BookPixelOcrTest` → 11 pass, 61
assertions. Regressions: `--filter Ai` 167 pass / `--filter Book` 34 pass.

## Files changed

- `app/Services/BookPixel/BookPixelOcrService.php` (new) — trigger + executor
- `app/Jobs/ProcessAiJob.php` — `'ocr'` executor branch
- `app/Http/Controllers/BookPixelController.php` — OCR hook in
  `handleScanPhoto`; `$bot->setData($update)` after parse (vendor Telegram
  reads `php://input` in its constructor, which is empty in tests; in
  production the payload is identical to the webhook body)
- `app/Models/BookPageScan.php` — fillable + `ocr_at` datetime cast
- `database/migrations/2026_11_14_140000_add_ocr_columns_to_book_page_scans_table.php`
- `lang/fa/bot.php`, `lang/en/bot.php` — `book_pixel_ocr_started`
- `tests/Feature/BookPixelOcrTest.php` (new, TDD red→green)
