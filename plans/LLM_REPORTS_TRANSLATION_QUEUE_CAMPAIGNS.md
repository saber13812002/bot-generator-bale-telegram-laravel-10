# Plan: LLM Reports, LLM Translation, Queue Control, Hourly Watchdog, Weekly Campaigns

Approved 2026-10-09 (architect mode). Keep the existing 5-min queue job + hourly watchdog safety net with red/green alerts.

## Context / Current State

- Translation: `app/Services/TranslationService.php` hard-calls `OneApiTranslationService::call()` (one-api.ir — dead). RSS bot translation flow: `RssItemService::run()` + `RssPostItemTranslationService` (only translates when `rss_item.locale != 'fa' && target_locale == 'fa'`), triggered by `RssReadTranslate` every 15 min.
- AI infra: `AiProviderService` (OpenAI-compatible, `chat()` max_tokens=50), hourly `ai:health-check` (`AiHealthCheck`) with red fail / single-green recovery notifications, `ai:daily-health-report`. All scheduled in `app/Console/Kernel.php`.
- Channel-poster queue: `channel_poster_queue` (status pending/published/failed/cancelled, scheduled_at). Processed by `ProcessChannelPosterQueueJob` via `$schedule->job(...)` every 5 min (stuck problem). `ChannelPosterBotController` has no way to list/manually send queued items. Callback convention: `cp:*`.
- Reports: Quran bot `/report` in `QuranWordController` (line ~1837) → `QuranBotUserRankingService::specificUserReport()` + web link. Cohort stats exist in `EmailReportEnhancementService` (e.g. `getTop10Users`, no names).
- Channel posting: `PostDailyVerseToChannels` + `AdminDailyChannelConfig` (slots), destinations via `ChannelPosterBotService::resolveByTag/resolveDestinations`, publishing via `ChannelPosterPublisherFactory`.

## Feature 2 — LLM Translation with switchable driver

1. `AiProviderService::chat()` — add optional `$maxTokens` / `$temperature` params (default current values).
2. New `app/Services/LlmTranslationService.php`:
   - `translate(string $text, string $target = 'fa'): ?string`
   - Prompt: translate to Persian, output only the translation, no quotes/preamble.
   - Return null on failure (so driver can fall back).
3. `config/translation.php` — add `'driver' => env('TRANSLATION_DRIVER', 'auto')` (values: `one_api`, `llm`, `auto`).
4. `TranslationService::translate()`:
   - driver `one_api` → OneApi only.
   - driver `llm` → LlmTranslationService only (fall back to original text on failure).
   - driver `auto` → try OneApi; if all providers fail / non-200, try LLM; if that fails return original text. (Resolves existing `// todo:if status not 200 call another translation api`.)
5. Verify `RssPostItemTranslationService::call()` continues to work (it calls `TranslationService::call`, so the switch applies automatically).

## Feature 3 — Manual control of the channel-poster queue

1. Refactor: extract the per-item publishing logic of `ProcessChannelPosterQueueJob::processItem()` into a shared service method (e.g. on `ChannelPosterBotServiceImpl` or a new `ChannelPosterQueuePublishService`) usable by both the job and manual sends. Keep idempotency: only publish items with `status = pending`.
2. `ChannelPosterBotController`:
   - New `/queue` command (also add a button to the start menu when pending count > 0).
   - Lists pending items for this bot: `#id — tag — scheduled_at (Tehran) — text/file preview`, with inline buttons per item: `send now` / `cancel`.
   - Callbacks `cp:q:item:<id>:send|cancel` (owner-only guard already in `handleCallbackQuery`).
   - `send now` → run the shared publish method immediately; `cancel` → status=cancelled.
3. New command `channel-poster:send-queued {--bot=} {--id=}` for server-side manual sending (prints result summary).
4. Keep the 5-min scheduled job unchanged.

## Feature 4 — Hourly PostOverdueWatchdog (safety net)

1. New command `PostOverdueWatchdog` (`channel-poster:watchdog`), scheduled hourly in Kernel (`withoutOverlapping()->onOneServer()`).
2. Checks items in `channel_poster_queue` with `status=pending` and `scheduled_at <= now()` (overdue):
   - Attempt resend via the shared publish method (mark published/failed).
   - Also check today's `PostDailyVerseToChannels` slot posts: for active `AdminDailyChannelConfig` whose slot already ran and `shouldRunInSlot` was true, detect missing publish (no `channel_poster_publish_log`-style record / or a lightweight `last_daily_post_at` column added to config) — flag as missed.
3. Notification state machine (per bot / global, cached):
   - While red: send red alert to bot mother each hour (same recipients as `AiProviderService::notifyAllAdmins`).
   - On recovery (no overdue items): send exactly ONE green "recovered" message, then silence again while green.
   - Cache keys like `watchdog_state:<bot_id>`; state = ok|red.
4. Alert content: count of overdue items, oldest scheduled_at, item ids, action taken (resend success/fail).

## Feature 1 — LLM activity report (Quran bot, on-demand)

1. New `app/Services/ActivityReportSummaryService.php`:
   - `buildStats(int $chatId, string $origin): array` — 7-day BotLog counts (quran commands per day), current/last week totals, streak days.
   - `buildCohort(array $stats): array` — anonymized comparison: median/avg of all users last week, user's percentile, top-10 count (no names). Reuse patterns from `EmailReportEnhancementService`.
   - `analyze(array $stats, array $cohort): ?string` — short Persian prompt to LLM (`AiProviderService::chat` with higher max_tokens, e.g. 300): 3–6 line analysis comparing the user's Quran reading activity vs. others (anonymized). Returns null on LLM failure.
2. Wire into `QuranWordController` `/report` flow: after `specificUserReport()`, append the LLM analysis message (or a plain-text stats summary if LLM returned null). Keep existing web-link message.
3. No new migrations needed (BotLog already stores activity).

## Feature 5 — Weekly reading invitation (شراب بهشتی style)

1. Migration + model `WeeklyReadInvitation`: `destination_id` (channel_poster_destination or bot_id+tag), `day_of_week` (0=Sat..6=Fri, default 5=Friday), `max_post_id` (int, admin-provided), `enabled`, `last_invited_at`.
2. Command `weekly-read:invite`, scheduled daily (e.g. 10:00):
   - For each enabled config whose `day_of_week` matches today and `last_invited_at` is not this week:
     - Pick random post number 1..`max_post_id`, build invitation text: "دعوت به مطالعه مطلب شماره X از کانال" + link to the channel (destination `channel_link` if present) + short motivational line.
     - Publish via the channel's platform (bale/telegram/eitaa token from destination).
     - Update `last_invited_at`.
3. Bot mother wizard `/weeklyinvite` (in `BotMotherController` state machine, owner/admin only): select bot → tag → platform (existing destination) → day of week (default Friday) → max post id → enable/disable. Also allow "auto-detect last post id" where platform API allows, else manual entry.

## Feature 6 — Weekly LLM motivational post per channel

1. Migration + model `ChannelMotivationalSchedule`: `destination_id`, `day_of_week`, `frequency` (weekly|biweekly|daily, default weekly), `prompt` (long Persian prompt, default provided in `config/content_bots.php` or lang), `last_sent_at`, `enabled`.
2. Command `channel-motivation:send`, scheduled daily:
   - For each enabled schedule due today (weekly: `last_sent_at < 7d ago` or null; biweekly: 14d; daily: 1d):
     - Fetch last ~10 sent texts (cache or a `sent_texts` json column on the model) to instruct the LLM to avoid repetition.
     - Ask LLM (long prompt) for ONE short Persian motivational line (max ~200 chars).
     - Publish to that destination's platform; store the text; update `last_sent_at`.
   - If LLM down → skip (log + count), do NOT post a fallback boilerplate repeatedly; optionally one static fallback text per week.
3. Bot mother wizard `/motivation`: select bot → tag → platform → day of week → frequency (default weekly) → optional custom prompt → enable/disable. Each channel gets its own day so posts are spread across the week.

## Tests & Docs

- Feature tests:
  - Translation driver: `one_api`/`llm`/`auto` routing + fallback to original text (mock both providers).
  - `/queue` list + `cp:q:item:<id>:send` marks published (publisher mocked).
  - Watchdog: overdue item triggers red alert + resend; recovery sends single green.
  - `/report` LLM fallback path (LLM down → plain stats).
  - `weekly-read:invite` and `channel-motivation:send` dry-run (fake time, mocked publisher/LLM).
- Docs: `CHANGELOG.md` entry, `.env.example` (`TRANSLATION_DRIVER`), README note on new commands/wizards.

## Suggested implementation order

1. F2 translation (independent, small).
2. F3 queue control (refactor shared publish first).
3. F4 watchdog (reuses F3 publish + health-alert patterns).
4. F1 LLM report (needs `chat()` max_tokens param from F2 step 1).
5. F5 weekly invitation.
6. F6 motivational post.
7. Tests + docs.
