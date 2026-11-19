# MISSION 09 — Bot AI Pilot Integration & Human Acceptance — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the two existing AI capabilities (Book Pixel OCR/ASR, Growth Companion LLM) pilot-ready with honest verification: close the webhook media-size validation gap, add user-visible OCR/ASR result tracking (`/result`), verify all bot commands, prove multi-user isolation, then deliver an owner acceptance guide and the 13-section final report — without changing any rollout stage/flag, without pushing, without fake AI output.

**Architecture:** The AI pipeline already exists and is tested end-to-end (`AiFeatureGate` → `AiJobDispatcher` → `ProcessAiJob` → per-feature service with strict no-fake-output semantics, states in `ai_jobs`, idempotency via `idempotency_key`). This plan adds only what is missing: (1) the 50 MB media-size validation at the Book Pixel webhook (photo + voice), (2) a `/result` command backed by a pure, unit-testable status service, (3) new TDD tests for command verification and growth multi-user isolation, (4) verification runs of the existing gate/queue suites with recorded numbers, (5) two owner-facing docs. **No new migrations, no schema changes, no flag/stage changes, no .env changes.**

**Tech Stack:** Laravel 10, PHP 8.5.5 local (8.2.34 prod), PHPUnit via `php artisan test --compact`, MySQL dev DB with `RefreshDatabase`, existing fakes (`Http::fake`, `Queue::fake`, `FakeGrowthMessenger` + `FakeGrowthMessengerFactory`), vendor `Telegram` client with `$bot->setData($update)` test injection.

**Spec:** Owner mission brief (chat, "MISSION 09 — BOT AI PILOT INTEGRATION & HUMAN ACCEPTANCE") + `docs/AGENT_HANDOFF.md` (golden rules) + `docs/AI_OCR_BOOK_PIXEL.md` / `docs/AI_ASR_BOOK_PIXEL.md` / `docs/AI_GROWTH_COMPANION.md` (WS-F/G/H contracts) + `docs/AI_PILOT_ROLLOUT_FINAL_REPORT.md` (stage states) + `docs/AI_PRODUCTION_VERIFICATION_FINAL_REPORT.md` (honest production state).

## Global Constraints

- **Stage 0 preserved.** No change to `AI_ROLLOUT_STAGE`, any `AI_FLAG_*` env, `config/ai.php` values, `ai_flags` rows, or `bots.ai_settings`. No `.env` edits. No production access (unreachable from this environment — production verification stays owner-side).
- **No `git push`, no deploy.** Local commits only. Owner approval required before any push/deploy.
- **No secrets** in code, tests, docs, or logs. Never print `ai_providers.api_key` or bot tokens in reports (reference by column/variable name only).
- **TLS always on.** No live provider calls in tests — `Http::fake` / `Queue::fake` / `FakeGrowthMessenger` only. Mock tests are never reported as live-provider tests.
- **No fake AI output.** Empty/failed provider results stay in error/retry states (`ocr_text`/`asr_text` remain NULL until a real success) — never invent placeholder text.
- **Media cap: 50 MB** per file (golden rule, `docs/AGENT_HANDOFF.md`).
- **PHP 8.5.5 local emits deprecation noise** and the full `php artisan test` run may exit code 1 even when every test passes. Judge GREEN/RED by the `Tests: … passed` summary line, never by exit code.
- Do not mix Mission 08-R1 (log retention / cron) changes into this work.
- Test quality contract: record REAL numbers from `php artisan test --compact`; never claim a test that was not run.
- User-facing copy is Persian-first in `lang/fa/bot.php` with `lang/en/bot.php` fallback. New keys are added to **both** files.
- Outbound vendor Telegram messages use raw curl and are NOT interceptable by `Http::fake` (see `tests/Feature/BookPixelHelpTest.php` docblock). Tests assert deterministic DB side effects + webhook 200, and text correctness is pinned by unit tests against the same translation keys (established repo pattern).

## Review Focus

1. **Oversized media at webhook** (photo/voice > 50 MB) → expected: polite rejection, no draft/scan/voice record, no AI job, conversation state preserved so the user can retry. → Task 1, Steps 1–4.
2. **Missing `file_size` metadata** (absent or 0) → expected: accepted (cannot validate), `Log::warning`, no exception, webhook 200. → Task 1, Step 1 (test 3).
3. **`/result` with no AI requests yet** → expected: friendly empty message, no crash, no state change. → Task 2, Step 5 (test 1).
4. **Two users on the same bot** → expected: full isolation — each LLM request contains only that user's checkin/text, and assistant replies are never sent to the other user's chat. → Task 4.
5. **Provider down at terminal attempt** → expected: short "unavailable" message, normal bot flow intact. (Already covered — re-verified in Task 6.)

## Baseline (Phase A — completed, recorded for the final report)

- Branch `15esfand1404` @ `913f470a`, clean working tree, in sync with `origin/15esfand1404` (0 ahead / 0 behind).
- Production (`/home/pardisa2/bots`) unreachable from this dev environment (no SSH) → production state is reported owner-side via a read-only command sheet (Task 8). Last known production state (from `docs/AI_PRODUCTION_VERIFICATION_FINAL_REPORT.md`): LiteLLM TCP connection blocked from production network; real token not yet entered in Nova.
- Local MySQL is a FRESH dev DB (0 bots, 0 ai_jobs). Local `ai_providers` row has a stale token (last test: HTTP 401 `token_not_found_in_db`, 2026-11-05).
- `php artisan ai:rollout-status`: **Stage 0 ACTIVE** (Internal), all capability flags false. Stage 1 READY (needs owner allowlist + approval); Stages 2/3 PAUSED.
- Baseline suite (2026-11-17, local): **2 deprecated, 2 skipped, 1027 passed (6054 assertions), 170.75 s** — exit code 1 due to PHP 8.5 deprecations (known, documented in `docs/AGENT_HANDOFF.md`).
- Confirmed gaps this plan closes: no media-size validation at Book Pixel webhook; no user-facing OCR/ASR result tracking; no growth multi-user isolation tests; per-command webhook verification incomplete.

---

### Task 1: Work branch + Book Pixel media-size validation (photo + voice)

**Files:**
- Create: `tests/Feature/BookPixelMediaValidationTest.php`
- Modify: `app/Http/Controllers/BookPixelController.php` — `handlePhoto()` (~line 456, insert before the draft save at line 465) and `handleVoice()` (~line 615, insert before `createVoice` at line 619)
- Modify: `lang/fa/bot.php` (~line 50) and `lang/en/bot.php` (~line 319) — one new key each

**Interfaces:**
- Produces (used by Task 3's stats-of-changed-files only):
  - `BookPixelController::private const MEDIA_MAX_BYTES = 50 * 1024 * 1024;` (comment: golden rule, AGENT_HANDOFF)
  - `BookPixelController::private function isMediaTooLarge(?int $fileSize): bool` — returns `true` only when `$fileSize !== null && $fileSize > self::MEDIA_MAX_BYTES` (missing/0 ⇒ not too large; caller logs a warning).
  - Lang key `bot.book_pixel_media_too_large` in fa + en.
- Consumes: existing fixture patterns from `tests/Feature/BookPixelOcrTest.php` (`makeScanFixture`, `setWaitingScanState`, `photoUpdate`) and `tests/Feature/BookPixelAsrTest.php` (`makeVoiceFixture`, `setVoiceState`, `voiceUpdate`).

- [ ] **Step 1: Create the work branch and write the failing test file**

```bash
git checkout -b m9-ai-pilot-acceptance
```

`tests/Feature/BookPixelMediaValidationTest.php` — `extends TestCase`, `use RefreshDatabase;`, `Queue::fake()` + `AiFeatureGate::clearHealthCache()` in setUp, `Carbon::setTestNow()` reset in tearDown (copy the exact setUp/tearDown/flags/makeScanFixture/setWaitingScanState/makeVoiceFixture/setVoiceState patterns from `BookPixelOcrTest.php` / `BookPixelAsrTest.php`). Add `photoUpdateWithSize(string $fileId, int $chatId, ?int $fileSize)` and `voiceUpdateWithSize(string $fileId, int $chatId, ?int $fileSize)` helpers — same shape as the existing `photoUpdate`/`voiceUpdate` but with the `file_size` field parameterized (omit the key entirely when `null`). `MEDIA_MAX = 50 * 1024 * 1024`.

Five tests (all flags OFF — this validates the webhook layer independently of the AI gate):
1. `test_photo_over_50mb_is_rejected_without_scan_draft_or_job` — state `waiting_scan` + book fixture; photo `file_size = MEDIA_MAX + 1`. Assert: webhook 200; `BookPageScan::count() === 0`; `book_drafts` empty for that file; `AiJob::count() === 0`; state row still `waiting_scan` (retry possible).
2. `test_photo_at_exactly_50mb_is_accepted` — same fixture, `file_size = MEDIA_MAX`. Assert: webhook 200; scan created with `pending_approval`; state cleared (normal flow intact).
3. `test_photo_with_missing_file_size_is_accepted` — photo entry without `file_size` key. Assert: webhook 200; scan created; `Log::warning` is called (assert with `$this->partialMock`/`Log::shouldReceive('warning')->once()` if practical; otherwise document in the test that the warning path exists and assert only the accept behavior).
4. `test_voice_over_50mb_is_rejected_without_voice_record_or_job` — scan fixture + voice state (`setVoiceState`); voice `file_size = MEDIA_MAX + 1`. Assert: webhook 200; `BookPageVoice::count() === 0`; `AiJob::count() === 0`; voice state preserved.
5. `test_voice_at_exactly_50mb_is_accepted` — `file_size = MEDIA_MAX`. Assert: webhook 200; `BookPageVoice` row exists; state cleared.

- [ ] **Step 2: Run the new tests to verify they fail**

Run: `php artisan test --filter=BookPixelMediaValidationTest --compact`
Expected: tests 1 and 4 FAIL (scan/voice get created because no validation exists yet); tests 2, 3, 5 pass.

- [ ] **Step 3: Implement the validation**

In `app/Http/Controllers/BookPixelController.php`:
1. Add `private const MEDIA_MAX_BYTES = 50 * 1024 * 1024;` (golden-rule comment) and:
```php
private function isMediaTooLarge(?int $fileSize): bool
{
    return $fileSize !== null && $fileSize > self::MEDIA_MAX_BYTES;
}
```
2. In `handlePhoto()` immediately after `$fileUniqueId = $photo['file_unique_id'] ?? null;` (line 460) and BEFORE `$this->draftService->saveDraft(...)`:
```php
$fileSize = isset($photo['file_size']) ? (int) $photo['file_size'] : null;
if ($this->isMediaTooLarge($fileSize)) {
    Log::warning('[BookPixel] Photo rejected: over media cap', ['chat_id' => $chatId, 'file_size' => $fileSize]);
    BotHelper::sendMessage($bot, trans('bot.book_pixel_media_too_large'));
    return;
}
if ($fileSize === null) {
    Log::warning('[BookPixel] Photo accepted without file_size metadata', ['chat_id' => $chatId]);
}
```
3. In `handleVoice()` immediately after `$fileUniqueId = $voice['file_unique_id'] ?? null;` (line 617) and BEFORE `$this->bookPixelService->createVoice(...)`: same three-step pattern using `$voice['file_size']` (log tag `[BookPixel] Voice rejected: over media cap`).

Add lang keys (fa, Persian-first; en mirrors):
- fa `lang/fa/bot.php`: `'book_pixel_media_too_large' => '📎 فایل بزرگ‌تر از سقف مجاز (۵۰ مگابایت) است. لطفاً فایل کوچک‌تری ارسال کنید.'`
- en `lang/en/bot.php`: `'book_pixel_media_too_large' => 'The file is larger than the 50 MB limit. Please send a smaller file.'`

- [ ] **Step 4: Run tests to verify they pass (plus no regression in the OCR/ASR suites)**

Run: `php artisan test --filter='BookPixelMediaValidationTest|BookPixelOcrTest|BookPixelAsrTest' --compact`
Expected: all pass (judge by the `Tests: … passed` line).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/BookPixelMediaValidationTest.php app/Http/Controllers/BookPixelController.php lang/fa/bot.php lang/en/bot.php
git commit -m "feat(book-pixel): enforce 50MB media cap at webhook (photo+voice)"
```

---

### Task 2: `/result` command — user-visible OCR/ASR tracking

**Files:**
- Create: `app/Services/BookPixel/BookPixelResultStatus.php`
- Create: `tests/Feature/BookPixelResultStatusTest.php`
- Create: `tests/Feature/BookPixelResultCommandTest.php`
- Modify: `app/Http/Controllers/BookPixelController.php` — command dispatch in `handleTextMessage()` (insert after the `/drafts` block, ~line 212) + new `handleResult()` method (after `handleHelp()`, ~line 809)
- Modify: `lang/fa/bot.php`, `lang/en/bot.php` — 8 new keys each
- Modify: `tests/Unit/BookPixelHelpTextTest.php` — add the 10th command line to `FA_COMMANDS` and `EN_COMMANDS`

**Interfaces:**
- Produces:
  ```php
  // app/Services/BookPixel/BookPixelResultStatus.php
  class BookPixelResultStatus
  {
      /**
       * @return array<int, array{type: 'scan'|'voice', id: int, page_number: int|null,
       *     status: 'completed'|'failed'|'pending'|'not_requested',
       *     code: string|null, preview: string|null}>
       */
      public function forUser(int $botUserId, int $botId, int $limit = 5): array
  }
  ```
  - Scans: `BookPageScan::where('user_id', $botUserId)->where('bot_id', $botId)->latest()->take($limit)`; `page_number` from `$scan->bookPage?->page_number`; voice via `$scan->voice` (hasOne, nullable).
  - OCR status priority: `ocr_text` non-null → `completed`; else an active `AiJob` (feature `ocr`, same `bot_id`, state in `[PENDING, QUEUED, PROCESSING, RETRYING, DEFERRED]`, `where('payload->scan_id', $scan->id)`) → `pending`; else `ocr_error` non-null → `failed` (code = `ocr_error`); else → `not_requested`.
  - ASR: same with `asr_text` / `asr_error` / feature `asr` / `payload->voice_id`.
  - `preview`: whitespace-collapsed, `Str::limit(trim((string) $text), 200)`; null unless `completed`.
  - Voice entries appear directly after their scan's entry (iterate scans; append each scan's voice when present).
- Controller: `handleResult(Telegram $bot, BotUsers $botUser, int $botId): void` — invoked from `handleTextMessage()` when `$text == '/result' || $text == 'result' || $text == 'نتیجه'` (before the state-based switch). Builds one message via `app(BookPixelResultStatus::class)->forUser($botUser->id, $botId)` (same `app(...)` pattern the controller already uses for `BookPixelOcrService`).
- Lang keys (fa shown; en mirrors in English):
  - `book_pixel_result_title` = '📋 وضعیت درخواست‌های هوش مصنوعی:'
  - `book_pixel_result_empty` = 'هنوز اسکن یا وویسی ثبت نشده است.'
  - `book_pixel_result_scan` = '📄 صفحه {page}:'
  - `book_pixel_result_voice` = '🎤 وویس صفحه {page}:'
  - `book_pixel_result_completed` = '✅ انجام شد'
  - `book_pixel_result_failed` = '❌ خطا: {code}'
  - `book_pixel_result_pending` = '⏳ در صف پردازش…'
  - `book_pixel_result_not_requested` = '—'
  - Help line: fa `/result - پیگیری نتیجه هوش مصنوعی`, en `/result - Track AI results` (appended to the `book_pixel_help` double-quoted string in both locale files).

- [ ] **Step 1: Write the failing service tests**

`tests/Feature/BookPixelResultStatusTest.php` (`extends TestCase`, `RefreshDatabase`): seed a `Bot`, `BotUsers`, `Book`, `BookPage`, `BookPageScan` (+ optional `BookPageVoice`, `AiJob` rows via `AiJob::create` like `BookPixelOcrTest::makeOcrJob`).
1. `test_no_scans_returns_empty_list` — `forUser` on a fresh user → `[]`.
2. `test_completed_ocr_shows_completed_with_truncated_preview` — scan with `ocr_text` = a 300-char Persian string → entry `status=completed`, `preview` length ≤ 200, `code=null`.
3. `test_failed_ocr_shows_failed_with_code` — `ocr_error='auth_failed'`, no active job → `status=failed`, `code='auth_failed'`, `preview=null`.
4. `test_active_job_shows_pending` — `AiJob` feature `ocr`, state `QUEUED`, `payload=['scan_id' => $scan->id, ...]` → `status=pending`.
5. `test_terminal_job_does_not_show_pending` — same job but state `FAILED` + `ocr_error='provider_error'` → `status=failed`.
6. `test_voice_entry_reflects_asr_state` — scan (no ocr) + voice with `asr_text` set → two entries: scan `not_requested`, voice `completed`.
7. `test_limit_is_respected_and_latest_first` — 6 scans → 5 entries, first entry is the latest scan.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=BookPixelResultStatusTest --compact`
Expected: FAIL — `Class App\Services\BookPixel\BookPixelResultStatus does not exist`.

- [ ] **Step 3: Implement `BookPixelResultStatus::forUser`** per the Interfaces contract (no side effects; read-only queries only).

- [ ] **Step 4: Run to verify pass**

Run: `php artisan test --filter=BookPixelResultStatusTest --compact`
Expected: all 7 pass.

- [ ] **Step 5: Write the failing command + help tests**

`tests/Feature/BookPixelResultCommandTest.php` (reuse the `BookPixelHelpTest` fixture pattern: `makeBot`, `webhookUrl`, `textUpdate`):
1. `test_result_command_with_no_scans_returns_ok` — webhook `POST …text=/result` → 200 `{"status":"ok"}`; `bot_users` row created; `bot_user_states` count 0 (command must not push the user into the ISBN flow).
2. `test_result_command_with_completed_ocr_returns_ok` — seed a scan with `ocr_text` for the chat's bot user; webhook `/result` → 200.
3. `test_persian_alias_natijeh_works` — webhook text `نتیجه` → 200.
4. `test_result_command_while_in_waiting_isbn_state_does_not_change_state` — pre-seed state `waiting_isbn`; webhook `/result` → 200; state row still `waiting_isbn`.

`tests/Unit/BookPixelHelpTextTest.php` — add `'/result - پیگیری نتیجه هوش مصنوعی'` to `FA_COMMANDS` and `'/result - Track AI results'` to `EN_COMMANDS` (contains-based assertions, so no other change needed).

- [ ] **Step 6: Run to verify failure**

Run: `php artisan test --filter='BookPixelResultCommandTest|BookPixelHelpTextTest' --compact`
Expected: command tests FAIL (200 still returned but bot_user_states assertion / handler not reached — the command is not dispatched, so `/result` falls into the state switch and `waiting_isbn`/default branch runs, breaking test 4's and test 1's state assertions); help unit test FAILS (line missing in translations).

- [ ] **Step 7: Implement controller dispatch + `handleResult` + lang keys + help line**

- Dispatch in `handleTextMessage()` after the `/drafts` block:
```php
if ($text == '/result' || $text == 'result' || $text == 'نتیجه') {
    $this->handleResult($bot, $botUser, $botId);
    return;
}
```
- `handleResult(Telegram $bot, BotUsers $botUser, int $botId): void`:
  - `$entries = app(BookPixelResultStatus::class)->forUser($botUser->id, $botId);`
  - Empty → `BotHelper::sendMessage($bot, trans('bot.book_pixel_result_empty')); return;`
  - Otherwise join lines: title, then per entry `"{icon} صفحه {page}: {statusLine}"` where statusLine is one of `trans('bot.book_pixel_result_completed')` (+ `"\n   «{$entry['preview']}»"` when preview non-null), `trans('bot.book_pixel_result_failed', ['code' => $entry['code']])`, `trans('bot.book_pixel_result_pending')`, or `trans('bot.book_pixel_result_not_requested')`. `page_number` null → use `'?'.`
- Add the 8 lang keys to both locale files and append the `/result` line to `book_pixel_help` (double-quoted string, real newlines — match the existing style so `BookPixelHelpTextTest`'s no-raw-`\n` assertion keeps passing).

- [ ] **Step 8: Run to verify pass (new + help + full Book Pixel suites)**

Run: `php artisan test --filter='BookPixelResultStatusTest|BookPixelResultCommandTest|BookPixelHelpTextTest|BookPixelHelpTest' --compact`
Expected: all pass.

- [ ] **Step 9: Commit**

```bash
git add app/Services/BookPixel/BookPixelResultStatus.php app/Http/Controllers/BookPixelController.php lang/fa/bot.php lang/en/bot.php tests/Feature/BookPixelResultStatusTest.php tests/Feature/BookPixelResultCommandTest.php tests/Unit/BookPixelHelpTextTest.php
git commit -m "feat(book-pixel): add /result command for OCR/ASR tracking"
```

---

### Task 3: Book Pixel command verification suite (9 commands, Telegram + Bale, real-newline /help)

**Files:**
- Create: `tests/Feature/BookPixelCommandVerificationTest.php`

**Interfaces:**
- Consumes: `BookPixelHelpTest` fixture pattern (`makeBot(string $origin)`, `webhookUrl(Bot, string)`, `textUpdate(string, int)`); `BookPixelOcrTest::makeScanFixture` for the skip test's pre-seeded state.

This is a VERIFICATION suite (expected GREEN on first run). Each test asserts webhook 200 + the deterministic DB side effect of the branch that must have run. If any test is RED, it is a discovered production bug: fix it, add the regression assertion, and record it in the final report (Task 9) using the 5-line bug format.

- [ ] **Step 1: Write the suite** — one test per command:
1. `test_start_sets_waiting_isbn_state` — `/start` → 200 + `bot_user_states` has `state=waiting_isbn`.
2. `test_help_telegram_returns_ok_and_provisions_user` — `/help` → 200 + `bot_users` row + 0 state rows (help must not start the ISBN flow).
3. `test_help_bale_returns_ok` — bale bot, `/help` → 200 + `bot_users` with `origin=bale`.
4. `test_help_text_has_real_newlines_and_all_commands_in_both_locales` — `trans('bot.book_pixel_help', [], 'fa')` and `…, 'en')`: each contains a real `"\n"`, does NOT contain the literal `'\\n'`, and contains all 10 command lines (reuse `FA_COMMANDS`/`EN_COMMANDS` from `BookPixelHelpTextTest`).
5. `test_cover_sets_waiting_cover_state` — `/cover` → 200 + state `waiting_cover`.
6. `test_search_with_query_returns_ok` — `/search کتاب` → 200, no state rows.
7. `test_drafts_with_no_drafts_returns_ok` — `/drafts` → 200.
8. `test_cancel_returns_ok_and_clears_state` — pre-seed state `waiting_book_name`; `/cancel` → 200 + state rows 0.
9. `test_skip_from_waiting_isbn_moves_to_waiting_book_name` — pre-seed state `waiting_isbn`; `/skip` → 200 + state `waiting_book_name`.
10. `test_score_returns_ok` — `/score` → 200 + `bot_users` row.
11. `test_stats_in_private_chat_returns_ok` — `/stats` from a private chat → 200 (vendor `getChat` with the fake token fails gracefully in the test env → non-group branch; assert no exception, no state rows).
12. `test_plain_text_still_takes_default_flow` — regression guard: free text `سلام` → state `waiting_isbn` (not swallowed by a command branch).

- [ ] **Step 2: Run the suite**

Run: `php artisan test --filter=BookPixelCommandVerificationTest --compact`
Expected: all 12 pass. Any RED → fix as a real bug (Task 9 five-line record).

- [ ] **Step 3: Re-run the /help real-newline evidence (Telegram + Bale) for the report**

Run: `php artisan test --filter='BookPixelHelpTest|BookPixelHelpTextTest|BookPixelCommandVerificationTest' --compact`
Expected: all pass; capture the summary line for the final report.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/BookPixelCommandVerificationTest.php
git commit -m "test(book-pixel): command verification suite (9 commands, telegram+bale, real-newline help)"
```

---

### Task 4: Growth Companion — multi-user isolation + ambiguous message (TDD)

**Files:**
- Create: `tests/Feature/GrowthAssistantIsolationTest.php`
- Modify (only if needed): `tests/Fakes/FakeGrowthMessenger.php` — add `textsSentTo(string|int $chatId): array` (all recorded text sends to that chat) if the fake doesn't already expose per-chat history.

**Interfaces:**
- Consumes (copied + ADAPTED from `GrowthAssistantTest` — those helpers are hard-bound to a single `$this->chatId = '555'`, so the new test defines its own chat-parameterized copies): `UsesGrowthCompanionSqlite` (`setUpGrowthCompanionTables`), `FakeGrowthMessengerFactory` binding, `flags(array $on)`, `seedHealthTemplate()`, `fakeChat(int $status, string $content)` (identical), and chat-parameterized variants:
  - `createBot(): Bot` — single shared bot (`endpoint_id='webhook-growth-companion'`, `bale_bot_token=$token`, `bot_mother_id=1`), created ONCE per test.
  - `onboard(int $chatId): void` — `/start` + `gc:f:health` + `gc:i:bal` + `gc:t:skip` callbacks, all with the chat-parameterized `textUpdate(string, int)` / `callbackUpdate(string, int)` (from/chat id = `$chatId`).
  - `consentFor(int $chatId, bool $value): void` — resolve `BotUsers` by chat_id → `GrowthProfile` → `forceFill(['ai_consent' => $value])->save()`.
  - `triggerFor(int $chatId, string $text): AiJob` — post `textUpdate($text, $chatId)` to the webhook, then `AiJob::where('feature','growth_companion')->where('payload->profile_id', profileId($chatId))->first()` (profile resolved via the chat's `GrowthProfile`).
  - `profileFor(int $chatId): GrowthProfile` — via `BotUsers::where('chat_id',$chatId)->first()` → its growth profile (the profile belongs to the bot user; resolve exactly the way `GrowthAssistantService::triggerForMessage` does — profile per bot user).
- Two users = same bot, two chat ids (`555` and `666`); both onboard + consent through the SAME webhook URL; `Carbon` pinned to `T_OUTSIDE` (`'2026-11-14 11:30:00'`).

Tests (the code is already isolated by design — `GrowthAssistantService::buildPrompt` scopes on `growth_profile_id`; these tests PIN that contract):
1. `test_two_users_same_bot_are_fully_isolated` — both onboarded + consent; flags `['growth_companion']`; `T_OUTSIDE`. A sends `'امروز خیلی خسته بودم'`; B sends `'مشکل خواب دارم'`. Assert: 2 `AiJob` rows with distinct `customer_id` (profile ids) and matching `payload['profile_id']`. Execute job A with `fakeChat(200, 'replyA-پاسخ')` → assistant row exists ONLY on A's profile; messenger sent the reply ONLY to chat `555`. Execute job B with `fakeChat(200, 'replyB-پاسخ')` → assistant row ONLY on B's profile; reply ONLY to chat `666`.
2. `test_llm_request_never_contains_other_users_text` — same setup; capture the LLM request bodies (Http::fake with a closure response that records `$request->body()`). Execute job A and job B; assert the request body for A contains `'خسته بودم'` and NOT `'مشکل خواب'`; the reverse for B.
3. `test_ambiguous_short_message_still_dispatches_and_replies` — consented user sends bare `'بله'` → job created, thinking message sent; execute with `fakeChat(200, 'بله؟ درباره چی؟')` → reply delivered to that user's chat.
4. `test_consent_is_per_user` — A consent=true, B consent=false; B's free text → NO job, NO assistant rows for B (home-card path), while A's text still dispatches.

- [ ] **Step 1: Inspect `FakeGrowthMessenger`; add `textsSentTo()` if per-chat history is missing**

Read `tests/Fakes/FakeGrowthMessenger.php`. If it doesn't record the chat id per send, add a `private array $sent = [];` append in `send()` and:
```php
public function textsSentTo(string|int $chatId): array
{
    return array_values(array_filter($this->sent, fn (array $s) => (string) $s['chat_id'] === (string) $chatId));
}
```
- [ ] **Step 2: Write the four failing tests** (they should compile but fail if isolation is broken; if the fake lacks `textsSentTo`, they fail on the missing method first).

- [ ] **Step 3: Run to verify**

Run: `php artisan test --filter=GrowthAssistantIsolationTest --compact`
Expected: GREEN (isolation already implemented). Any RED → a real leak: fix the scoping in `app/Services/GrowthCompanion/GrowthAssistantService.php` (prompt/reply scope on `growth_profile_id` / `user_id`), record the bug in the five-line format for Task 9.

- [ ] **Step 4: Run the full growth suites to confirm no regression**

Run: `php artisan test --filter='GrowthAssistantIsolationTest|GrowthAssistantTest' --compact`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/GrowthAssistantIsolationTest.php tests/Fakes/FakeGrowthMessenger.php
git commit -m "test(growth): multi-user isolation + ambiguous-message coverage"
```

---

### Task 5: Phase D — Feature gate & rollout rules verification (NO code changes, NO flag/stage changes)

**Files:** none (verification only; no commit).

- [ ] **Step 1: Gate + rollout suites**

Run: `php artisan test --filter='AiFeatureGateTest|AiPilotRolloutTest' --compact`
Expected: all pass. Record the real summary line (independent flag per capability, per-bot override, Stage 0/1 fail-closed allowlist, Stage 2 pro-only, Stage 3 public, Pro 24/7 vs regular 09–14 Tehran deferred — all already covered by these suites).

- [ ] **Step 2: Rollout status snapshot (read-only)**

Run: `php artisan ai:rollout-status`
Expected: Stage 0 ACTIVE, all flags false — identical to the Phase A baseline. Record the output for the final report.

- [ ] **Step 3: Prove nothing drifted**

Run: `git diff --stat 913f470a -- config/ai.php .env env.example`
Expected: empty (no config/env changes in this mission).

---

### Task 6: Phase E — Queue & resilience verification (NO code changes)

**Files:** none (verification only; no commit).

- [ ] **Step 1: Queue resilience suite (idempotency, retry/backoff, dead-letter, stale recovery, expiry, deferred flush)**

Run: `php artisan test --filter=AiQueueResilienceTest --compact`
Expected: all pass. Record the summary line.

- [ ] **Step 2: Per-feature queue integration + failure injection (OCR/ASR/growth)**

Run: `php artisan test --filter='BookPixelOcrTest|BookPixelAsrTest|GrowthAssistantTest' --compact`
Expected: all pass (duplicate trigger → one job; provider 500 → backoff retry; 401 → auth_failed; empty result → `empty_result`, never fake text; terminal failure → user-facing unavailable message; regular 09–14 Tehran → DEFERRED until 14:00 flush; Pro → immediate). Record the summary line.

- [ ] **Step 3: Webhook non-blocking under rejection paths**

Run: `php artisan test --filter='BookPixelMediaValidationTest|BookPixelResultCommandTest' --compact`
Expected: all pass — rejection paths return 200 without touching the queue. Record the summary line.

Any RED in Tasks 5–6 is a discovered bug: fix, add the regression test, record in Task 9's five-line format.

---

### Task 7: Phase F — Full test suite run + honest reporting

**Files:** none (no commit).

- [ ] **Step 1: Full suite**

Run: `php artisan test --compact`
Expected: all pass — summary = baseline 1027 + new tests from Tasks 1–4 (record the EXACT real numbers: deprecated/skipped/passed/assertions/duration). Judge by the `Tests:` line (exit code may be 1 due to PHP 8.5 deprecations — known).

- [ ] **Step 2: Record the honest test-status statement** (verbatim into Tasks 8 & 9):
  - All AI tests are **mock/fake-based** (`Http::fake`, `Queue::fake`, `FakeGrowthMessenger`) — none of them prove a live provider.
  - **Live OCR/ASR/LLM testing remains BLOCKED** until the production LiteLLM connection exists and the real token is configured (owner-side; last known state: TCP connect timeout from production to `ai.ismc.ir:443`; local token stale 401). No live test may be declared successful.

---

### Task 8: Phase G — Owner acceptance guide

**Files:**
- Create: `docs/M9_OWNER_ACCEPTANCE_GUIDE.md`

**Interfaces:**
- Consumes: real command list + user-facing strings from `lang/fa/bot.php` (Book Pixel: `/start /help(راهنما) /cover(جلد) /search /drafts(پیش‌نویس) /cancel(لغو) /skip(رد کردن) /score /stats(آمار, گروه) /result(نتیجه)`; Growth Companion: `/start /privacy /settings` + nav + free-text assistant after consent), webhook endpoints `POST /api/webhook-book-pixel`, `POST /api/webhook-growth-companion`, Phase D/E/F recorded numbers, Task 7's honest test-status statement.

Guide structure (per mission, per bot — Book Pixel, then Growth Companion/رشدیار):
1. Exact bot identity for production: owner runs the read-only lookup (production unreachable from dev — provide the exact read-only SQL: `SELECT id, telegram_bot_name, bale_bot_name, telegram_bot_username, bale_bot_username, endpoint_id, type FROM bots WHERE …;` — token columns must NOT be selected/printed) to get the exact bot name/link; webhook URL from `endpoint_id`.
2. Commands that work RIGHT NOW (Stage 0): full list with expected first response (copy the real Persian strings).
3. Features actually active vs still disabled: normal bot flow ACTIVE; OCR/ASR/LLM-chat AI flows **DISABLED in production** (Stage 0 + all flags off) — they are code-complete and mock-tested, awaiting owner Stage-1 approval + allowlist.
4. Test scenarios with expected message + PASS/FAIL conditions:
   - Book Pixel: (a) `/start` flow → cover → page → scan photo → approval message; (b) oversized photo (>50MB) → rejection message, retry works; (c) `/result` → empty message, then pending/completed/failed states; (d) `/help` → real newlines, all 10 commands; (e) voice → sent confirmation; (f) `/score /drafts /search /cancel /skip` spot checks; (g) `/stats` in group only.
   - Growth Companion: (a) onboarding → home card; (b) consent on → free text → "in progress" thinking message (until Stage 1 enabled → actual reply); (c) consent off → home card, no AI; (d) AI down → short unavailable message, normal flow intact (observable only after Stage 1); (e) two devices/users → no cross-leak.
   - PASS/FAIL per scenario: exact expected text/state, and the log line or `ai:queue-status` state that proves it.
5. Owner-side read-only checks: `php artisan ai:rollout-status`, `ai:queue-status`, `ai:alerts-check`, `ai:stage-report` (all read-only; no flag/stage mutation).
6. Error reporting method: what to send (timestamp Tehran, chat id, scenario, exact message, relevant `storage/logs/laravel.log` tail with secrets redacted).
7. **5-line bug summary format**: title / cause / location (file:line) / fix / test method.
8. Explicit honesty box: no live-provider test has been run from this environment; AI pilot scenarios are BLOCKED(OWNER) until Stage 1 + allowlist + working production LiteLLM connection.

- [ ] **Step 1: Write the guide** using only real strings, real command names, and recorded numbers. No secrets (no tokens, no API keys, no customer ids).

- [ ] **Step 2: Self-check** — grep the doc for token-like strings; confirm every "PASS" scenario is reachable under Stage 0 (normal flows) or explicitly marked BLOCKED(OWNER) (AI flows).

- [ ] **Step 3: Commit**

```bash
git add docs/M9_OWNER_ACCEPTANCE_GUIDE.md
git commit -m "docs(m9): owner acceptance guide for Book Pixel & Growth Companion"
```

---

### Task 9: Phase H — Final 13-section report + STOP

**Files:**
- Create: `docs/M9_FINAL_REPORT.md`

**Interfaces:**
- Consumes: `git log --oneline 913f470a..HEAD`, `git diff --name-only 913f470a..HEAD`, all recorded summary lines from Tasks 3–7, Task 8's guide, Phase A baseline section of this plan.

Report sections (exactly the 13 mission items):
1. **Local state** — branch `m9-ai-pilot-acceptance`, base `913f470a` on `15esfand1404`, HEAD hash, clean tree.
2. **GitHub state** — `15esfand1404` unchanged; work branch NOT pushed (owner approval pending).
3. **Production state** — unreachable from dev (no SSH); last known state per `docs/AI_PRODUCTION_VERIFICATION_FINAL_REPORT.md` (LiteLLM TCP blocked, token 401); owner-side read-only commands in the guide.
4. **New commits** — full list with hashes + subjects (Tasks 1, 2, 3, 4, 8, 9 commits).
5. **Changed files** — `git diff --name-only 913f470a..HEAD`.
6. **Migrations** — none (explicit statement).
7. **Tests run + real results** — per-suite summary lines (Tasks 3–7) + final full-suite numbers; honest mock-vs-live statement (Task 7 Step 2, verbatim).
8. **Ready features** — 50MB media cap; `/result` tracking; all 9+1 commands verified (Telegram + Bale, real-newline `/help`); growth isolation/ambiguity pinned; gate/queue suites green.
9. **Blocked features** — live OCR/ASR/LLM pilot (Stage 0 + flags off + production LiteLLM connection + real token); exact owner actions required for Stage 1 (allowlist + approval).
10. **Remaining risks** — production network path to `ai.ismc.ir`, stale local token, shared-cPanel queue via cron (`ai:queue-work` every minute), vendor-client raw-curl test limitation, PHP 8.5 deprecation noise on local runs.
11. **Testable bot links** — webhook endpoints + owner's read-only bot-lookup SQL (no token columns).
12. **Owner test scenarios** — pointer to `docs/M9_OWNER_ACCEPTANCE_GUIDE.md` (scenarios + PASS/FAIL).
13. **Next-step proposal** — owner approves Stage 1 + allowlist for a few test customers → pilot run → stage-gate criteria (jobs_24h ≥ 20, error_rate ≤ 5%, dead_letters = 0, p95 ≤ 10000ms, kill switch tested) → then Stage 2 (Pro) / Stage 3 (public) with separate approvals.

- [ ] **Step 1: Collect evidence**

```bash
git log --oneline 913f470a..HEAD
git diff --name-only 913f470a..HEAD
git status --short
```
- [ ] **Step 2: Write `docs/M9_FINAL_REPORT.md`** with the 13 sections; no secrets anywhere; every test number is a real recorded one.

- [ ] **Step 3: Final full-suite confirmation run**

Run: `php artisan test --compact`
Expected: all pass; record the final summary line into section 7 (overwrite if it differs from Task 7's).

- [ ] **Step 4: Commit**

```bash
git add docs/M9_FINAL_REPORT.md
git commit -m "docs(m9): final 13-section report — awaiting owner pilot approval"
```

- [ ] **Step 5: STOP.** No push, no deploy, no stage/flag change. Wait for the owner's explicit pilot approval.
