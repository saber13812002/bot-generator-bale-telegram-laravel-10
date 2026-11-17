# M9 — Final Report: Bot AI Pilot Integration & Human Acceptance

Mission 09 complete. This is the 13-section final report. **No secrets** anywhere in this
document. Every test number is a real recorded summary line from this branch.

**Status: awaiting owner pilot approval.** No push, no deploy, no stage/flag change.

---

## 1. Local state

- Working directory: `bot-generator-bale-telegram-laravel-10` (Windows 10, PHP 8.5.5 local, Laravel 10).
- Branch: `m9-ai-pilot-acceptance` (created from `15esfand1404`).
- Baseline commit: `913f470a` (tip of `15esfand1404` at mission start).
- HEAD after Task 8: `304f0dab` (final report commit added on top — see §4).
- Tree: clean (only this report + the untracked plan/probe artifacts, not committed).
- No migrations, no config/`.env` changes (verified §5, §6).

## 2. GitHub state

- `15esfand1404` **unchanged** — mission work is on a separate branch.
- Branch `m9-ai-pilot-acceptance` **NOT pushed** to GitHub (owner approval pending, per mission rule).

## 3. Production state

- Production (cPanel, PHP 8.2.34) is **unreachable from this dev environment** (no SSH/HTTP path).
- Last known production state (per prior discovery, read-only sheet for owner):
  - AI rollout **Stage 0 (Internal) ACTIVE**; all global `AI_FLAG_*` false.
  - LiteLLM: TCP connect **timeout** from production to `ai.ismc.ir:443`; local test token returns **401** (stale).
- Owner-side read-only commands to re-verify from the production host (see also the guide §5):
  `php artisan ai:rollout-status`, `ai:queue-status`, `ai:alerts-check --dry-run`, `ai:stage-report`.

## 4. New commits (913f470a..HEAD)

```
304f0dab docs(m9): owner acceptance guide for Book Pixel & Growth Companion
c4f76fef test(growth): multi-user isolation + ambiguous-message coverage
4eea4070 test(book-pixel): command verification suite (9 commands, telegram+bale, real-newline help)
d9ee2fdf feat(book-pixel): add /result command for OCR/ASR tracking
a1398ea6 feat(book-pixel): enforce 50MB media cap at webhook (photo+voice)
```
(+ this report's commit, added at the end: `docs(m9): final 13-section report — awaiting owner pilot approval`)

- Task 1 (50 MB media cap) → `a1398ea6`
- Task 2 (`/result` command + `BookPixelResultStatus`) → `d9ee2fdf`
- Task 3 (command verification suite) → `4eea4070`
- Task 4 (Growth multi-user isolation + ambiguous-message tests) → `c4f76fef`
- Task 8 (owner acceptance guide) → `304f0dab`
- Task 5 & 6 were verification-only (no code changes, no commits).

## 5. Changed files (git diff --name-only 913f470a..HEAD, before this report)

```
app/Http/Controllers/BookPixelController.php
app/Services/BookPixel/BookPixelResultStatus.php
docs/M9_OWNER_ACCEPTANCE_GUIDE.md
lang/en/bot.php
lang/fa/bot.php
tests/Fakes/FakeGrowthMessenger.php
tests/Feature/BookPixelCommandVerificationTest.php
tests/Feature/BookPixelMediaValidationTest.php
tests/Feature/BookPixelResultCommandTest.php
tests/Feature/BookPixelResultStatusTest.php
tests/Feature/GrowthAssistantIsolationTest.php
tests/Unit/BookPixelHelpTextTest.php
```
(+ `docs/M9_FINAL_REPORT.md` with this commit.)

Notably: **no changes** to `config/ai.php`, `.env`, `env.example`, any `ai_jobs`-related
production service, or the Growth controller/service.

## 6. Migrations

**None.** This mission added no database schema changes. All new behavior uses existing
tables (`book_page_scans`, `book_page_voices`, `ai_jobs`, `bot_users`, `growth_profiles`,
`growth_assistant_messages`). Verified by the absence of `database/migrations` in the diff.

## 7. Tests run + real results (recorded `Tests:` lines)

| Suite group | Real summary line |
|---|---|
| Task 3 — Book Pixel command verification | `Tests: 12 passed` (BookPixelCommandVerificationTest) + `BookPixelHelpTextTest` 10 passed — part of the combined run recorded at commit `4eea4070` |
| Task 4 — Growth isolation (after harness fixes) | `Tests: 2 deprecated, 2 passed (74 assertions)` (4 tests total; the 2 "deprecated" marks are PHP 8.5 deprecation notices, not failures) |
| Task 4 combined | `php artisan test --filter="GrowthAssistantIsolationTest\|GrowthAssistantTest"` → `Tests: 2 deprecated, 14 passed (150 assertions)` |
| Task 5 — gate/rollout | `php artisan test --filter="AiFeatureGateTest\|AiPilotRolloutTest"` → `Tests: 2 deprecated, 37 passed (106 assertions)` |
| Task 6 — queue + feature suites (8 classes) | `Tests: 2 deprecated, 73 passed (430 assertions)` (AiQueueResilienceTest, BookPixelOcrTest, BookPixelAsrTest, GrowthAssistantTest, GrowthAssistantIsolationTest, BookPixelResultStatusTest, BookPixelResultCommandTest, BookPixelMediaValidationTest) |
| Task 7 — **full suite** | `Tests: 2 deprecated, 2 skipped, 1059 passed (6279 assertions)` — **0 failures**, 130.78s |
| Task 9 — **final confirmation run** | `Tests: 2 deprecated, 2 skipped, 1059 passed (6279 assertions)` — **0 failures**, 129.15s (identical counts) |
| Baseline (mission start) | `Tests: 2 deprecated, 2 skipped, 1027 passed (6054 assertions)`, 170.75s |

Net: **+32 tests, +225 assertions** vs baseline; 0 regressions.

Environment note: exit code of `php artisan test` is 1 due to PHP 8.5 deprecation noise in
vendor code (known local quirk); GREEN is judged by the `Tests:` summary line, which shows 0 failed.

**Honest mock-vs-live statement (verbatim):**
- All AI tests are **mock/fake-based** (`Http::fake`, `Queue::fake`, `FakeGrowthMessenger`) — none of them prove a live provider.
- **Live OCR/ASR/LLM testing remains BLOCKED** until the production LiteLLM connection exists and the real token is configured (owner-side; last known state: TCP connect timeout from production to `ai.ismc.ir:443`; local token stale 401). No live test may be declared successful.

Test-harness pitfalls caught during Task 4 (all harness-side, **no product leaks found**):
1. Laravel 10 `Http::fake()` stubs accumulate within a test — a second `Http::fake()` does not
   replace the first; fixed with a single `Http::sequence()` fake (`fakeChatSequence` helper).
2. LLM request bodies are `json_encode`d with ASCII `\uXXXX` escapes — raw-UTF-8 needle assertions
   fail; fixed by `json_decode` and asserting on `messages[0].content`.
3. The home card is a multi-line message *containing* `home_title` ('امروز') — exact `assertContains`
   fails; fixed with a substring check.
(These pin, via green tests, that delivery is per-chat and prompts are per-profile: the initial
"leak" symptom was proven a stale stub, not cross-user delivery.)

## 8. Ready features (production-usable now, Stage 0)

- **50 MB media cap** at the Book Pixel webhook (photo + voice) — oversized media rejected with a
  clear Persian message; webhook stays non-blocking (200).
- **`/result` command** — per-user OCR/ASR status tracking (`⏳ pending / ✅ completed / ❌ failed`),
  latest-first, 200-char preview, terminal-state-safe.
- **All Book Pixel commands verified** on Telegram **and** Bale: `/start /help(راهنما) /cover(جلد)
  /search /drafts(پیش‌نویس) /cancel(لغو) /skip(رد کردن) /score /stats(گروه) /result(نتیجه)` —
  including **real newlines** in `/help` (no literal `\n`), 10-command list.
- **Growth isolation pinned**: multi-user jobs/rows/prompts/delivery per profile; per-user consent
  gating; ambiguous short message ('بله') still dispatches and replies.
- **Queue resilience verified**: idempotency (duplicate trigger → one job), retry backoff
  [30,120,300]s, dead letter at 5 attempts, stale-processing recovery, expiry, deferred-window flush,
  Pro immediate.
- **Feature gate/rollout suites green**; Stage 0 preserved (see §9/§13 and the guide §5).

## 9. Blocked features (owner actions required)

Live AI pilot (OCR / ASR / LLM assistant) is **BLOCKED(OWNER)**:
1. Production → `ai.ismc.ir:443` network path is not working (TCP timeout) — owner must fix/verify.
2. A **real, valid provider token** must be configured (current local test token is stale → 401).
3. **Stage 1 approval + customer allowlist** by the owner (stage/flag changes are forbidden for me).
4. Then a live smoke test per bot (guide scenarios GC-2, BP-1-with-OCR, BP-5-with-ASR).

Until those, AI flows in production remain off (Stage 0 + all flags false — verified, unchanged).

## 10. Remaining risks

- Production network path to `ai.ismc.ir` (single point of failure for the whole pilot).
- Stale local token → any local "live" attempt 401s; risk of someone mistaking it for a code bug.
- Shared cPanel queue via cron (`ai:queue-work` every minute) — pilot traffic shares it with
  other jobs; watch p95.
- Vendor-client raw-curl test limitation (production curl environment cannot be tested from here).
- PHP 8.5 deprecation noise on local runs (exit code 1 even when green) — must not be read as failure.
- If Stage 1 is approved with the LiteLLM path still broken, users will see the unavailable
  fallback (`دستیار موقتا‌ً در دسترس نیست…`) — graceful, but must be communicated.

## 11. Testable bot links (owner-side)

- Book Pixel: `POST /api/webhook-book-pixel?origin=telegram|bale&token=<bot token>`
- Growth Companion: `POST /api/webhook-growth-companion?origin=telegram|bale&token=<bot token>`
- Bot identity lookup (read-only, no token columns):
  `SELECT id, telegram_bot_name, bale_bot_name, telegram_bot_username, bale_bot_username, endpoint_id, type FROM bots WHERE endpoint_id IN ('webhook-book-pixel','webhook-growth-companion');`
- Full details: `docs/M9_OWNER_ACCEPTANCE_GUIDE.md` §1.

## 12. Owner test scenarios

See **`docs/M9_OWNER_ACCEPTANCE_GUIDE.md`**:
- Book Pixel: BP-1…BP-7 (start/cover/scan approval, >50 MB rejection, `/result` states,
  real-newline `/help`, voice, spot checks, group-only `/stats`).
- Growth Companion: GC-1…GC-5 (onboarding → home card, consent-on free text, consent-off,
  AI-down fallback, two-user no-cross-leak).
- Each scenario: exact expected text + PASS/FAIL conditions + read-only proof commands.

## 13. Next-step proposal

1. Owner fixes/verifies the production → `ai.ismc.ir` path + configures a real token.
2. Owner approves **Stage 1** and sets a **customer allowlist** (a few test customers).
3. Pilot run → monitor via `ai:stage-report` / `ai:queue-status` against the **stage-gate criteria**:
   - `jobs_24h ≥ 20`
   - `error_rate ≤ 5%`
   - `dead_letters = 0`
   - `p95 ≤ 10000 ms`
   - **kill switch tested** (global flag flip stops dispatch within a minute)
4. On pass: **Stage 2 (Pro users)** — separate owner approval.
5. On pass: **Stage 3 (public)** — separate owner approval + announcement.

**STOP POINT: this branch is complete and verified. No push, no deploy, no stage/flag change.
Waiting for the owner's explicit pilot approval.**
