# Release Notes

## [Unreleased](https://github.com/laravel/laravel/compare/v10.0.4...10.x)

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
