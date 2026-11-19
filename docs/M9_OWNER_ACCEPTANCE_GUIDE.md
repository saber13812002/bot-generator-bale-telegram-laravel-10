# M9 — Owner Acceptance Guide: Book Pixel & Growth Companion (رشدیار)

Mission 09 — AI pilot integration & human acceptance. This guide is for the **owner (stakeholder)**
to run human-acceptance scenarios on the **production** bots after Stage 1 is approved.
Everything here uses **real** strings from `lang/fa/*`, **real** commands, and **recorded**
test numbers from this branch. **No secrets** (tokens, API keys, customer ids) are included.

---

## 0. State at handover (recorded on branch `m9-ai-pilot-acceptance`)

| Item | Recorded value |
|---|---|
| AI rollout stage | **Stage 0 (Internal) — ACTIVE** |
| Global AI flags | all **false**: `ai_global_enabled`, `ai_llm_enabled`, `ai_ocr_enabled`, `ai_asr_enabled`, `ai_rag_enabled`, `ai_growth_companion_enabled`, `ai_tts_enabled` |
| Config diff vs baseline `913f470a` | `config/ai.php`, `.env`, `env.example` — **no changes** (verified `git diff --stat` empty) |
| Full test suite | `Tests: 2 deprecated, 2 skipped, 1059 passed (6279 assertions)` — 0 failures, 130.78s |
| Gate/rollout suites | `Tests: 2 deprecated, 37 passed (106 assertions)` |
| Queue + feature suites | `Tests: 2 deprecated, 73 passed (430 assertions)` |

> ⚠️ **Honesty box (read first)**: No **live-provider** test (real OCR / ASR / LLM) has been run
> from this development environment — the production LiteLLM connection is not reachable
> (last known: TCP connect timeout from production to `ai.ismc.ir:443`; local test token returns 401).
> All AI tests are **mock/fake-based** (`Http::fake`, `Queue::fake`, `FakeGrowthMessenger`).
> **AI pilot scenarios below are BLOCKED(OWNER)** until Stage 1 + customer allowlist + a working
> production LiteLLM connection + a real provider token. Normal (non-AI) bot flows are ACTIVE
> and can be tested immediately.

---

## 1. Production bot identity (owner runs — read-only)

The production database is not reachable from the dev machine. Run this **read-only** lookup on the
production host (cPanel → MySQL / phpMyAdmin) to get the exact bot name/username and webhook URL.
**Do not select or print the token columns.**

```sql
SELECT id, telegram_bot_name, bale_bot_name,
       telegram_bot_username, bale_bot_username,
       endpoint_id, type
FROM bots
WHERE endpoint_id IN ('webhook-book-pixel', 'webhook-growth-companion');
```

Webhook URL pattern (from `routes/api.php`):

| Bot | Webhook endpoint |
|---|---|
| Book Pixel | `POST https://<your-domain>/api/webhook-book-pixel?origin=telegram|bale&token=<bot token>` |
| Growth Companion (رشدیار) | `POST https://<your-domain>/api/webhook-growth-companion?origin=telegram|bale&token=<bot token>` |

Set the webhook on each bot (Telegram: `setWebhook`; Bale: bot panel) to that URL.

---

## 2. Book Pixel — commands that work RIGHT NOW (Stage 0, no AI needed)

All commands work in **both** Telegram and Bale. Expected first responses (real Persian strings
from `lang/fa/bot.php`):

| Command | Expected response |
|---|---|
| `/start` | `📚 خوش آمدید به ربات یک پیکسل کتاب!` + `این ربات به شما امکان به اشتراک‌گذاری صفحات کتاب را می‌دهد.` then `لطفاً نام کتاب یا ISBN/شابک را ارسال کنید:` |
| `/help` (alias `راهنما`) | help card, **real newlines** (verified in tests — no literal `\n`), listing all 10 commands: `/start /help /cover /search <query> /drafts /result /cancel /skip /score /stats` |
| `/cover` (alias `جلد`) | `لطفاً عکس جلد کتاب را ارسال کنید.` |
| `/search <query>` | `نتایج جستجو:` or `کتابی یافت نشد.` |
| `/drafts` (alias `پیش‌نویس`) | `لیست پیش‌نویس‌ها:` or `پیش‌نویسی وجود ندارد.` |
| `/result` (alias `نتیجه`) | `📋 وضعیت درخواست‌های هوش مصنوعی:` or `هنوز اسکن یا وویسی ثبت نشده است.` |
| `/cancel` (alias `لغو`) | `✅ فرآیند لغو شد.` |
| `/skip` (alias `رد کردن`) | `چیزی برای رد کردن وجود ندارد.` (when nothing to skip) |
| `/score` | `📊 امتیاز شما:` with points/scans/voices, or `شما هنوز امتیازی ندارید…` |
| `/stats` | **group only** — in a private chat: `دستور /stats فقط در گروه کار می‌کند.` |

---

## 3. What is ACTIVE vs still DISABLED (production, Stage 0)

| Flow | Status |
|---|---|
| Book Pixel normal bot flow (book/cover/page/scan/voice/approval, commands) | **ACTIVE** |
| Book Pixel OCR (vision) AI flow | **DISABLED** in production (Stage 0 + flag off). Code-complete + mock-tested; awaiting Stage 1 approval + allowlist |
| Book Pixel ASR (voice) AI flow | **DISABLED** — same as above |
| Growth Companion normal flow (onboarding, home card, daily question, check-in, topics, settings) | **ACTIVE** |
| Growth Companion LLM assistant (free text → Persian reply) | **DISABLED** — same as above |

---

## 4. Test scenarios (expected message + PASS/FAIL)

### Book Pixel

**BP-1: `/start` → cover → page → scan photo → approval message**
- Steps: `/start` → book name → cover photo → page number → scan photo.
- Expected: `✅ عکس دریافت شد.` then `✅ اسکن صفحه برای تایید ارسال شد. پس از تایید، در صف انتشار قرار می‌گیرد.`
- PASS: approval message received and scan visible in drafts (`/drafts`).
- FAIL: any other text, error, or silent drop.

**BP-2: oversized photo (> 50 MB) → rejection, retry works** *(new in M9)*
- Steps: send a photo larger than 50 MB at the scan step.
- Expected: `📎 فایل بزرگ‌تر از سقف مجاز (۵۰ مگابایت) است. لطفاً فایل کوچک‌تری ارسال کنید.`
- PASS: rejection message received, state still waits for the scan, and a subsequent valid photo
  is accepted. Webhook still responds 200 (non-blocking).
- FAIL: crash, no response, or state lost.

**BP-3: `/result` — empty, then states** *(new in M9)*
- Steps: `/result` on a fresh user → `هنوز اسکن یا وویسی ثبت نشده است.`
  After scans/voices (once AI enabled): each entry shows `📄 صفحه N:` / `🎤 وویس صفحه N:` with one of
  `⏳ در صف پردازش…` / `✅ انجام شد` / `❌ خطا: <code>`.
- PASS: empty message for new users; per-page status lines for existing users; states never show
  `pending` for a job that already reached a terminal state.
- FAIL: empty user sees entries; terminal job still shows `⏳`.

**BP-4: `/help` real newlines + full command list** *(new in M9, both platforms)*
- Steps: `/help` in Telegram and in Bale.
- Expected: help card with **real line breaks** (not literal `\n`), all 10 commands including `/result`.
- PASS: readable multi-line card on both platforms.
- FAIL: raw `\n` visible, or a command missing.

**BP-5: voice → sent confirmation**
- Steps: after a scan, send a voice message.
- Expected: `✅ وویس با موفقیت ارسال شد.` (ASR AI line `🎧 …` only after Stage 1).
- PASS: confirmation received, voice attached to the scan row.

**BP-6: spot checks** — `/score` shows points or the "no score yet" line; `/drafts` lists or "no
drafts"; `/search` returns results or "no books"; `/cancel` → `✅ فرآیند لغو شد.` and state clears;
`/skip` on a skippable optional field advances the flow.
- PASS: each returns the exact string above.

**BP-7: `/stats` in group only**
- Steps: `/stats` in a private chat → `دستور /stats فقط در گروه کار می‌کند.`
  Then in a group → `آمار ربات` card.
- PASS: both behaviors as stated.

### Growth Companion (رشدیار)

**GC-1: onboarding → home card**
- Steps: `/start` → pick focus (`gc:f:…`) → intensity (`gc:i:…`) → notify time (`gc:t:…`).
- Expected: welcome `سلام. من رشدیار هستم؛ همراه تأمل و رشد شخصی تو. حوزه را تو انتخاب می‌کنی.`
  → after completion, a home card starting with **`<b>امروز — <weekday></b>`** (home title `امروز`)
  + daily question `سؤال امروز`.
- PASS: onboarding completes, home card + question delivered, nav keyboard present.

**GC-2: consent ON → free text → thinking message** *(AI itself BLOCKED(OWNER) until Stage 1)*
- Steps: on a consented user, send free text (e.g. `امروز خیلی خسته بودم`).
- Stage 0 (now): the gate is closed, so the user gets the home card + `از دکمه‌های پایین صفحه انتخاب کن.`
  and **no AI job** — this is the correct Stage 0 behavior.
- Stage 1 (after approval): the user immediately gets `🤖 در حال فکرم؛ پاسخ همین‌جا می‌آید…` and,
  within a minute, the assistant reply in the same chat.
- PASS (Stage 1): thinking message + reply only in the sender's chat.
- FAIL: reply missing, or reply appears in another user's chat (isolation violation — see GC-5).

**GC-3: consent OFF → home card, no AI**
- Steps: on a non-consented user, send free text.
- Expected: **no** thinking message; home card instead; **no** `ai_jobs` row created.
- PASS: home card delivered, `ai_jobs` count for that user stays 0.
- Proof: `php artisan ai:queue-status` shows no new job for the user.

**GC-4: AI down → graceful fallback** *(observable only after Stage 1)*
- Expected after max retries: `دستیار موقتا‌ً در دسترس نیست. می‌توانی دوباره تلاش کنی یا از دکمه‌های پایین استفاده کنی.`
  Normal bot flow stays intact (buttons still work).
- PASS: short unavailable message, no crash, no silent loss.

**GC-5: two devices/users → no cross-leak** *(pinned by `GrowthAssistantIsolationTest`, 4 tests green)*
- Steps: two different users on the same bot each send free text; each checks their own chat.
- PASS: each user sees only their own reply; assistant rows are scoped per growth profile;
  the LLM prompt of user A never contains user B's text.
- FAIL: any text or reply visible across users.

### Verifying AI jobs (read-only, owner-side)

- `php artisan ai:queue-status` — job states (queued/processing/completed/failed), retries, backoff.
- A Stage-1 assistant reply corresponds to an `ai_jobs` row `state=completed`
  with `result_reference = assistant_message:<id>`.

---

## 5. Owner-side read-only checks (no flag/stage mutation)

Run on the production host (all read-only; none of these change any flag or stage):

```bash
php artisan ai:rollout-status    # expect: Stage 0 + all global flags false (until Stage 1)
php artisan ai:queue-status      # job pipeline state
php artisan ai:alerts-check      # alert detection (add --dry-run to never notify)
php artisan ai:stage-report      # stage metrics for the gate criteria
```

---

## 6. Error reporting method (what to send)

When a scenario fails, send a short report with:
1. Timestamp in **Tehran time** (e.g. `1404-11-26 23:45`).
2. Bot + platform (Telegram/Bale) and **chat id** (user id).
3. Scenario id (BP-1…GC-5) and the exact steps.
4. The **exact** message received (screenshot or copy).
5. Tail of `storage/logs/laravel.log` around that timestamp **with secrets redacted**
   (tokens, API keys, customer PII must not be pasted).

---

## 7. 5-line bug summary format

Every reported bug should be summarized in 5 lines:

1. **Title** — one line, `bot/scenario: symptom`.
2. **Cause** — root cause, one line.
3. **Location** — `file:line` (and stack frame if helpful).
4. **Fix** — what was changed (or "not yet fixed").
5. **Test method** — the command/scenario that reproduces it and now guards it.

---

## 8. Recorded test evidence (this branch)

- Book Pixel command verification (9 commands × Telegram + Bale, real-newline `/help`):
  `BookPixelCommandVerificationTest` — 12 passed.
- Media cap (50 MB, photo + voice, webhook stays 200): `BookPixelMediaValidationTest` — passed.
- `/result` status service + command: `BookPixelResultStatusTest` (7), `BookPixelResultCommandTest` (4) — passed.
- OCR/ASR/assistant executors + queue behaviors (500 retry, 401, empty-result, deferred window,
  Pro immediate, duplicate trigger → one job): `BookPixelOcrTest`, `BookPixelAsrTest`,
  `GrowthAssistantTest` — passed.
- Queue resilience (idempotency, backoff [30,120,300]s, dead letter, stale recovery, expiry):
  `AiQueueResilienceTest` — passed.
- Multi-user isolation + ambiguous message + per-user consent: `GrowthAssistantIsolationTest` — 4 passed.
- Full suite: `Tests: 2 deprecated, 2 skipped, 1059 passed (6279 assertions)`, 130.78s, 0 failures.
  (2 "deprecated" marks are PHP 8.5 deprecation notices during the run, not test failures.)

> ⚠️ Again: **none of these are live-provider tests.** Declaring a live OCR/ASR/LLM pilot
> "successful" before a working production LiteLLM connection exists would be dishonest.
