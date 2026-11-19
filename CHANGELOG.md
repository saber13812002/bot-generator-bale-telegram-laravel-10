# Release Notes

## [Unreleased](https://github.com/laravel/laravel/compare/v10.0.4...10.x)

### 2026-10-19 — Documentation Suite (Agent Handoff) + Test Suite Re-Verification

- **New operational docs** (documentation-only pass, no behavior changes):
  - `docs/AGENT_HANDOFF.md` — primary agent handoff reference: verified project
    identity (repo, branch `15esfand1404`, production path `/home/pardisa2/bots`,
    PHP 8.2.34, UTC timezone, `QUEUE_CONNECTION=sync`), golden rules, subsystem
    maps (RSS pipeline / Channel Poster / Content Sync), troubleshooting flows,
    test quality contract, security findings, known issues list.
  - `docs/JOBS_AND_SCHEDULER.md` — official reference for all 30 scheduler
    entries (13 data points each), concurrency locks table, UTC↔Tehran
    conversion, cron failure diagnosis, safe-change checklist. Documents that
    `app:rss-post-to-translate` is NOT scheduled and that 3 commands
    (`app:task-reminder-command`, `app:users-ranking-command`,
    `app:test-schedule-daily-into-slack`) lack `withoutOverlapping()`.
  - `docs/PRODUCTION_RUNBOOK.md` — deploy/migrate/cron/diagnose/rollback
    procedures derived from `deploy_commands.sh` + `DEPLOY_INSTRUCTIONS.md`,
    with a migration rollback table for the 2026-10 migrations.
  - `docs/ARCHITECTURE.md` — layer map, webhook catalog from `routes/api.php`,
    data-flow diagrams for RSS / Channel Poster / Content Sync, design
    decisions, known weaknesses.
  - `README.md` reworked into a concise operational entry point (previously a
    marketing-focused 380-line README); feature detail now lives in the docs
    index.
- **Finding (flagged in docs):** `docs/SCHEDULER_CRON_PLAN.md` references a
  `storage:logs-cleanup` command that **does not exist** in the codebase — it
  must never be added to crontab.
- **Finding (flagged in docs):** `POST /api/webhook-rss` is defined twice in
  `routes/api.php` (legacy `BotMotherController::rss` +
  `RssPostItemTranslationController::index`); the later definition wins.
  Intent is unverified — needs a dedicated follow-up.
- **Test suite re-run (real, 2026-10-19, local PHP 8.5+):**
  **399 passed / 2 skipped / 2 deprecated — 2205 assertions, 0 failures**
  (108.41s). This supersedes the "402 tests / 2198 assertions" figure quoted in
  commit `60880a6c`: the total number of test methods is 403 (399 passed +
  2 skipped + 2 deprecated) and the actual assertion count is 2205. Local PHP
  8.5+ emits deprecation noise absent on production PHP 8.2.34; the run's
  non-zero exit code came from deprecations, not test failures.
- **Action item (production, unverified):** the previously logged Bale token
  leak — rotation status is unverified. See `docs/PRODUCTION_RUNBOOK.md` §7.

### 2026-10-19 — RSS Recovery: 48h Freshness Guard (commits 394317b3, 13f1eca6)

- `RssRecoveryService` (used by `php artisan rss:recover`) now enforces a
  **48h freshness guard** (`MAX_RETRY_AGE_HOURS = 48`) on automatic resend:
  queue records older than 48 hours are **never** auto-resent — they are
  reported and require manual review (prevents bulk-spamming stale content).
- The guard applies in the execute path of both systems (`rss` and
  `channel-poster`), alongside the existing `lockForUpdate` + status re-check
  safety rails.
- `13f1eca6` documents the guard in `docs/RECOVERY_ROLLOUT_PLAN.md` (backlog
  rollout stages use freshness checks + caps).

### 2026-10-18 — RSS Pipeline Tests All Green (commit 60880a6c)

- All RSS pipeline test failures resolved; the commit message records
  "402 tests / 2198 assertions, 0 failures". Re-verified on 2026-10-19 — see
  the 2026-10-19 entry above for the corrected real numbers (399 passed /
  2205 assertions; 403 total methods including 2 skipped + 2 deprecated).

### 2026-10-17 — Channel Poster: Real Media Upload + MP3/Document Support

- `ee25f734` — real multipart media upload for **photo+caption** to
  Bale/Telegram/Eitaa (previously media could be dropped silently depending on
  the platform path).
- `7cdd2d79` — **MP3/document support**: audio and document media types are
  now carried through `ChannelPosterMediaStore` (fetch ≤ 50MB,
  `storage/app/channel-poster-media/`) and `ChannelPosterMediaSender`
  (multipart with the correct `photo`/`video`/`document`/`audio` field per
  content type), fixing the silent audio-drop bug.
- Cross-platform media rule (unchanged, now documented): a messenger `file_id`
  is only valid on its **origin platform** — publishing to another platform
  always re-uploads the local file via multipart.

### 2026-10-16 — Content Sync Phase 4: Source Identification, Attribution, Reliable URLs

- **One-time source-channel identification**: when a synced link's channel id
  matches no known destination, the bot asks **once** with a short keyboard
  (one button per active destination of that platform + «کانال دیگر»).
  Selecting a destination persists the mapping and learns `channel_username`
  + `channel_link` from the real link, so the same channel is auto-associated
  on every later link (no repeated "⛔ needs review" halts). «کانال دیگر»
  keeps those destinations suspicious (no publishing to them).
- **Source attribution (one-time choice)**: unknown-source registration shows a
  compact one-time settings keyboard (🎯 dedup / ⏱ publish time / ⚡ fast mode /
  📌 source display). Modes: `signed` (default — «📌 منبع: …» label + source
  link), `link_only` (link only), `none`. The suffix is appended to the last
  chunk (separate message on overflow) and skipped when the source link is
  already in the text. Stored per admin in
  `content_sync_admin_prefs.source_attribution`, with the owner's 👑 default in
  `content_sync_bot_defaults.source_attribution` as fallback. **Frozen on
  queueing**: `channel_poster_queue.source_attribution` snapshots the mode at
  queue time, so queued posts keep the attribution chosen when they were queued.
- **No silent tag/destination fallbacks**: the last-used tag is applied only
  when the source is a known destination with exactly one tag; unknown sources
  always ask. The dedup submenu no longer offers an "all channels" option
  (channel / platform only).
- **Reliable `channel_username`**: new
  `channel_poster_destinations.channel_username` stores the trusted public
  username (captured from a real channel link — never fabricated from a chat
  id) and is used as the preferred source for constructed `post_url`s.
  `PostUrlParser::matchesDestination` compares usernames case-insensitively;
  Bale links are canonicalized to `ble.ir/{username}/{chat_id}/{message_id}`
  (lowercase, mirror variants collapsed) in both parser and `PostUrlBuilder`.
- **`/add` accepts a channel link**: the Telegram/Eitaa chat-id step now also
  accepts a full channel link (`https://t.me/saberblog/42`) — chat id/username
  is extracted, the test message goes to the resolved channel, and username +
  canonical link are stored. Bale stays forward-based (its username is learned
  later from the first matching link).
- **Migration**: `2026_10_16_200000_add_content_sync_phase4` (column-existence
  guards, reversible).
- **Tests**: 12 new acceptance tests — 10 in
  `tests/Feature/ContentSyncFlowTest.php` (`test_p4_*`: one-time keyboard
  persistence, unknown-source keyboard, pick-saves-association + re-resolve,
  "other" keeps destinations suspicious, last-tag-not-guessed, tag+attribution
  frozen on queue, signed suffix appended, link_only/none, queue keeps frozen
  attribution, known-source auto-association) + 2 in
  `tests/Feature/ChannelPosterBotWebhookTest.php` (Bale `/add` via forward
  stores no fabricated username; Telegram `/add` with a channel link captures
  username + canonical link). Phase-3 settings tests updated to the compact
  keyboard (6 rows, no buttons on the link message). Full suite: 335 passed /
  1840 assertions.

### 2026-10-16 — Content Sync UX: Compact One-Time Settings (Jepeto)

- Link-registration no longer carries the settings keyboard (clean
  three-line message: registered / send text / `⚙️ تغییر تنظیمات: /settings`).
  Unknown-source links trigger the one-time compact settings keyboard instead.
- `/settings` is a short main menu (status lines + one button per row) opening
  independent short submenus (🎯 / ⏱ / ⚡ / 📌 / 👑 defaults for the owner)
  with ✅ on the active choice and 🔙 back; changes are confirmed with an
  inline toast, no new message. Old callback values remain backward compatible.
- `/help` rewritten as a categorized Persian guide (owner-only commands shown
  to the owner only); every sync report ends with the `/help` footer.
- Docs: README Content Sync section +
  `tests/ManualContentSyncTestScenario.md` updated.

### 2026-10-15 — API Token: Security Hardening + Token Management Endpoints

- **Security fix (critical)**: `MissionApiController` was reading
  `is_super_admin` and `tenant_id` from the **request body** (client-supplied),
  allowing a tenant token to escalate to super-admin and access any tenant's
  missions. All 6 access checks now read from `$request->api_token->type`
  and `->tenant_id` (set by the `ApiTokenAuth` middleware, immutable by the
  client). New `effectiveTenantId()` and `assertTenantAccess()` helpers
  centralize the check.
- **Security fix**: `ApiTokenAuth` middleware was merging
  `tenant_id` from the token into the request, which **clobbered** the
  super-admin's body `tenant_id` (set to null since super-admin tokens have
  no tenant). Now only tenant tokens inject `tenant_id`; super-admin body
  params are preserved.
- **Security fix**: `POST /api-tokens/generate` is now **super-admin only**
  (previously any valid token could create new tokens, including super_admin).
- **New endpoints**:
  - `POST /api/v1/missions/{id}/prompt` — update a mission's prompt text.
  - `GET /api/v1/tokens` — list API tokens (super-admin: all; tenant: own only).
    Never exposes `plain_token` or the hash.
  - `DELETE /api/v1/tokens/{id}` — revoke a token (soft: `is_active=false`).
    Super-admin can revoke any; tenant only its own.
- **Refactor**: `MissionApiController::createMission` now creates missions
  through `MissionRepository::create()` (constructor-injected) instead of
  calling `Mission::create()` directly.
- **Tests**: `tests/Feature/ApiTokenAuthTest.php` — 15 tests covering
  privilege escalation (tenant→super_admin), cross-tenant access denial,
  super-admin cross-tenant access, token generation auth, token listing
  scoping, token revocation, and prompt update.

### 2026-10-15 — Channel Poster: Multi-Channel Content Sync & Archive

- **Content Sync (fast mode)**: admins forward a link to an already-published
  post plus its text to the bot; the content is registered once and
  immediately re-published to the other channels of the same tag
  (source channel excluded). No extra questions when the tag is unambiguous
  (source-channel tag → single tag → admin hint → last-used tag).
  `PostUrlParser` (Eitaa/Bale/Soroush/Telegram) resolves the source and
  `PostUrlBuilder` constructs the destination post URLs with a
  confirmed/constructed/unverified link status.
- **Archive & dedup**: new tables `content_sync_contents`,
  `content_sync_versions`, `content_sync_operations`, `content_sync_admins`,
  `content_sync_admin_prefs` (migration `2026_10_15_100000_create_content_sync_tables`).
  Unique constraints on `(bot, source_platform, source_channel, source_message)`
  and `(content, destination)` keep concurrent admins from double-publishing;
  every version stores its `post_url`, `link_status`, `message_id` and
  `published_at` so old posts can be found later.
- **Long text**: `TextChunker` splits posts over ~3500 chars into sequential
  messages; `chunks_count` is recorded per version.
- **Queue mode**: `/mode` (immediate|queue); queued content reuses
  `ChannelPosterQueue` slots and `ChannelPosterQueuePublishService` records
  results back into `content_sync_versions`. Soroush destinations are skipped
  (no publisher yet) instead of silently failing.
- **Bot commands**: `/sync [id|url]` (full report), `/last`, `/retry`
  (failed versions only), `/admin` (multi-admin), `/channels` (list + per-channel
  detach button). `php artisan content-sync:report [id|--all]` exports a
  text/CSV report for the 5000-post migration goal.
- **Bug fixes**: `/channels` delete button now detaches the destination
  (link-takeover fix) instead of erroring; `logs/TelegramErrorLogger-*.txt`
  added to `.gitignore`.
- **Tests**: `tests/Unit/ContentSync/*` (PostUrlBuilder/PostUrlParser/
  TextChunker) + `tests/Feature/ContentSyncFlowTest.php` (SQLite in-memory,
  fake publisher) — 49 new tests.

### 2026-10-09 — LLM Reports, Translation, Queue Control & Channel Campaigns

- **F1 — Quran Bot Smart Report**: `/report` now appends an anonymized 7-day cohort
  comparison (users/median/avg/max/percentile, no names) plus an optional LLM analysis
  (`ActivityReportSummaryService`). Falls back to plain stats when the LLM is unavailable.
- **F2 — LLM Translation Driver**: added `LlmTranslationService` (local OpenAI-compatible
  provider) and a `TRANSLATION_DRIVER` switch (`one_api` | `llm` | `auto`) in
  `config/translation.php`. `auto` tries one-api first and falls back to the LLM;
  on any failure the original text is returned.
- **F3 — Channel Poster Queue Control**: new `/queue` command and `Queue` start-menu button
  on the Channel Poster bot list pending/overdue items with owner-only
  `send now` / `cancel` callbacks. Publishing extracted into
  `ChannelPosterQueuePublishService` and exposed via
  `php artisan channel-poster:send-queued` (`--bot=`, `--id=`, `--all` to send overdue
  or future items).
- **F4 — Hourly Watchdog**: new `php artisan channel-poster:watchdog` (scheduled hourly)
  resends overdue pending items, marks stuck bots **red** in cache and alerts admins
  hourly while red, then sends a single **green** alert on recovery.
  `--dry-run` available.
- **F5 — Weekly Reading Invitation**: new `weekly_read_invitations` table/model,
  `php artisan weekly-read:invite` (scheduled) posts a "read post #N" invitation to a
  channel once per week on its configured day, and a `/weeklyinvite` Bot Mother wizard
  to create/manage invitations (`--dry-run`, `--force` supported).
- **F6 — Weekly LLM Motivational Post**: new `channel_motivational_schedules` table/model,
  `php artisan channel-motivation:send` (scheduled) asks the LLM for a short one-line
  Persian motivational text and posts it (`✨` prefix) on the schedule's day with
  daily/biweekly/weekly frequency and duplicate-avoidance via recent-text history.
  `/motivation` Bot Mother wizard to create/manage schedules. LLM failures alert admins.
- **Bug fixes**: `ActivityReportSummaryService::streakDays()` counted one day short
  (`flip()`/`filter()` removed the first day); `ChannelMotivationSend::generateText()`
  used `preg_split('/\R+/')` without the `u` flag, which corrupted UTF-8 Persian text
  (byte 0x85 matched as a line break) and made `preg_replace` return null;
  `LlmTranslationService::cleanResponse()` bullet-strip pattern also got the `u` flag.
- **Tests**: 6 new test files / 23 tests covering the translation driver, queued
  publishing, watchdog resend/red/green state, report stats + LLM fallback,
  weekly invite and motivation dry-runs (SQLite in-memory fixtures, fake
  publisher factory + LLM HTTP fakes).
- **Nova**: new resources for the new tables — `ChannelPosterQueue`
  (📤 Channel Poster Queue), `WeeklyReadInvitation` (📖 Weekly Read
  Invitations) and `ChannelMotivationalSchedule` (💬 Motivational Schedules)
  for inspecting/managing queue items, weekly invites and motivational
  schedules from the admin panel.

## [v10.0.4](https://github.com/laravel/laravel/compare/v10.0.3...v10.0.4) - 2023-02-27

- Fix typo by @izzudin96 in https://github.com/laravel/laravel/pull/6128
- Specify facility in the syslog driver config by @nicolus in https://github.com/laravel/laravel/pull/6130

## [v10.0.3](https://github.com/laravel/laravel/compare/v10.0.2...v10.0.3) - 2023-02-21

- Remove redundant `@return` docblock in UserFactory by @datlechin in https://github.com/laravel/laravel/pull/6119
- Reverts change in asset helper by @timacdonald in https://github.com/laravel/laravel/pull/6122

## [v10.0.2](https://github.com/laravel/laravel/compare/v10.0.1...v10.0.2) - 2023-02-16

- Remove unneeded call by @taylorotwell in https://github.com/laravel/laravel/commit/3986d4c54041fd27af36f96cf11bd79ce7b1ee4e

## [v10.0.1](https://github.com/laravel/laravel/compare/v10.0.0...v10.0.1) - 2023-02-15

- Add PHPUnit result cache to gitignore by @itxshakil in https://github.com/laravel/laravel/pull/6105
- Allow php-http/discovery as a composer plugin by @nicolas-grekas in https://github.com/laravel/laravel/pull/6106

## [v10.0.0 (2022-02-14)](https://github.com/laravel/laravel/compare/v9.5.2...v10.0.0)

Laravel 10 includes a variety of changes to the application skeleton. Please consult the diff to see what's new.
