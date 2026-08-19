<?php

namespace App\Services;

use App\Interfaces\Services\GrowthCompanionService;
use App\Interfaces\Services\GrowthLlmProvider;
use App\Models\BotUsers;
use App\Models\GrowthDailyCheckin;
use App\Models\GrowthProfile;
use App\Models\GrowthProfileTopic;
use App\Models\GrowthProgram;
use App\Models\GrowthQuestion;
use App\Models\GrowthQuestionSchedule;
use App\Models\GrowthQuestionVariant;
use App\Models\GrowthResponse;
use App\Models\GrowthReview;
use App\Models\GrowthTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GrowthCompanionServiceImpl implements GrowthCompanionService
{
    public function __construct(
        private GrowthQuestionSelector $selector,
        private GrowthLlmProvider $llm
    ) {
    }

    public static function budgetForIntensity(string $intensity): int
    {
        return $intensity === 'active' ? 2 : 1;
    }

    public function getOrCreateProfile(BotUsers $botUser, int $botId): GrowthProfile
    {
        return GrowthProfile::firstOrCreate(
            [
                'bot_user_id' => $botUser->id,
                'bot_id' => $botId,
            ],
            [
                'mode' => 'simple',
                'timezone' => 'Asia/Tehran',
                'notify_time' => '21:00:00',
                'depth' => 'quick',
                'intensity' => 'balanced',
                'interaction_budget_per_day' => 1,
                'day_reset_hour' => 3,
                'ai_consent' => false,
            ]
        );
    }

    public function completeOnboarding(
        GrowthProfile $profile,
        string $focusSlug,
        string $intensity,
        string $notifyTime,
        ?string $customFocus = null
    ): GrowthProgram {
        $profile->intensity = $intensity;
        $profile->notify_time = $notifyTime;
        $profile->interaction_budget_per_day = self::budgetForIntensity($intensity);
        $profile->day_reset_hour = $profile->day_reset_hour ?: 3;
        $profile->onboarding_completed_at = now();
        $profile->save();

        $this->ensureDefaultBoard($profile, $focusSlug, $customFocus);

        $primarySlug = $focusSlug === 'custom'
            ? $this->customSlugForLabel($customFocus ?: trans('growth_companion.focus.custom'))
            : $focusSlug;
        $primary = $profile->programs()
            ->where('status', 'active')
            ->where('template_slug', $primarySlug)
            ->latest('id')
            ->first();

        if (!$primary) {
            $primary = $profile->activeProgram();
        }
        if (!$primary) {
            $fallback = $this->enableTopic($profile, 'self');
            $primary = $this->programForSlug($profile, $fallback->template_slug, true);
        }

        Log::info('[GrowthCompanion] Onboarding completed', [
            'bot_id' => $profile->bot_id,
            'profile_id' => $profile->id,
            'focus' => $focusSlug,
        ]);

        return $primary->fresh(['questions.variants', 'questions.schedule']);
    }

    public function ensureDefaultBoard(GrowthProfile $profile, ?string $primarySlug = null, ?string $customLabel = null): void
    {
        $slugs = GrowthProfile::DEFAULT_BOARD_SLUGS;
        if ($primarySlug && $primarySlug !== 'custom' && !in_array($primarySlug, $slugs, true)) {
            $slugs[] = $primarySlug;
        }

        foreach ($slugs as $index => $slug) {
            $topic = $this->enableTopic($profile, $slug);
            if ($topic->sort_order !== $index) {
                $topic->sort_order = $index;
                $topic->save();
            }
        }

        if ($primarySlug === 'custom') {
            $this->addCustomTopic(
                $profile,
                $customLabel ?: trans('growth_companion.focus.custom')
            );
        }
    }

    public function enableTopic(GrowthProfile $profile, string $slug, ?string $customLabel = null): GrowthProfileTopic
    {
        $max = (int) $profile->topics()->max('sort_order');
        $topic = GrowthProfileTopic::firstOrNew([
            'growth_profile_id' => $profile->id,
            'template_slug' => $slug,
        ]);
        $creating = !$topic->exists;
        $topic->enabled = true;
        $topic->cadence = $topic->cadence ?: 'daily';
        if ($customLabel) {
            $topic->custom_label = $customLabel;
        }
        if ($creating) {
            $topic->sort_order = $max + 1;
        }
        $topic->save();

        $this->ensureProgramForTopic($profile, $topic);

        return $topic;
    }

    public function addCustomTopic(GrowthProfile $profile, string $label): GrowthProfileTopic
    {
        return $this->enableTopic($profile, $this->customSlugForLabel($label), $label);
    }

    public function disableTopic(GrowthProfile $profile, string $slug): bool
    {
        $topic = GrowthProfileTopic::where('growth_profile_id', $profile->id)
            ->where('template_slug', $slug)
            ->first();
        if (!$topic) {
            return false;
        }

        $topic->enabled = false;
        $topic->save();

        $program = $this->programForSlug($profile, $slug);
        if ($program) {
            $program->status = 'paused';
            $program->save();
            $program->questions()->update(['paused_at' => now()]);
        }

        return true;
    }

    public function enabledTopics(GrowthProfile $profile): Collection
    {
        return $profile->topics()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function setTopicCadence(GrowthProfile $profile, string $slug, string $cadence): bool
    {
        if (!in_array($cadence, ['daily', 'weekly'], true)) {
            return false;
        }

        $topic = GrowthProfileTopic::where('growth_profile_id', $profile->id)
            ->where('template_slug', $slug)
            ->first();
        if (!$topic) {
            return false;
        }

        $topic->cadence = $cadence;
        $topic->save();
        $this->syncQuestionCadence($profile, $slug, $cadence);

        return true;
    }

    public function toggleTopicWeekday(GrowthProfile $profile, string $slug, int $weekday): bool
    {
        if ($weekday < 0 || $weekday > 6) {
            return false;
        }

        $topic = GrowthProfileTopic::where('growth_profile_id', $profile->id)
            ->where('template_slug', $slug)
            ->first();
        if (!$topic) {
            return false;
        }

        $days = array_values(array_map('intval', $topic->weekdays ?? []));
        if (in_array($weekday, $days, true)) {
            $days = array_values(array_filter($days, fn ($day) => $day !== $weekday));
        } else {
            $days[] = $weekday;
            sort($days);
        }
        $topic->weekdays = $days === [] ? null : $days;
        $topic->save();

        $question = $this->questionForTopic($profile, $slug, true);
        $schedule = $question?->schedule;
        if ($schedule) {
            $schedule->days_of_week = $topic->weekdays;
            $schedule->save();
        }

        return true;
    }

    public function setIntensity(GrowthProfile $profile, string $intensity): void
    {
        if (!in_array($intensity, ['minimal', 'balanced', 'active'], true)) {
            return;
        }

        $profile->intensity = $intensity;
        $profile->interaction_budget_per_day = self::budgetForIntensity($intensity);
        $profile->save();
    }

    public function dayWindowStart(GrowthProfile $profile, ?Carbon $now = null): Carbon
    {
        $tz = $profile->timezone ?: 'Asia/Tehran';
        $hour = (int) ($profile->day_reset_hour ?: 3);
        $now = ($now ?: Carbon::now($tz))->copy()->timezone($tz);
        $boundary = $now->copy()->startOfDay()->addHours($hour);
        if ($now->lt($boundary)) {
            $boundary->subDay();
        }

        return $boundary->utc();
    }

    public function weekWindowStart(GrowthProfile $profile, ?Carbon $now = null): Carbon
    {
        $tz = $profile->timezone ?: 'Asia/Tehran';
        $hour = (int) ($profile->day_reset_hour ?: 3);
        $now = ($now ?: Carbon::now($tz))->copy()->timezone($tz);
        $monday = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay()->addHours($hour);
        if ($now->lt($monday)) {
            $monday->subWeek();
        }

        return $monday->utc();
    }

    public function isTopicDone(GrowthProfile $profile, GrowthProfileTopic $topic, ?Carbon $now = null): bool
    {
        $question = $this->questionForTopic($profile, $topic->template_slug, true);
        if (!$question) {
            return false;
        }

        $start = $topic->isWeekly()
            ? $this->weekWindowStart($profile, $now)
            : $this->dayWindowStart($profile, $now);

        return $question->responses()
            ->where('answered_at', '>=', $start)
            ->exists();
    }

    public function canOpenTopic(GrowthProfile $profile, GrowthProfileTopic $topic, ?Carbon $now = null): string
    {
        if ($this->isTopicDone($profile, $topic, $now)) {
            return $topic->isWeekly() ? 'done_week' : 'done_today';
        }

        if ($this->topicSentInDayWindow($profile, $topic, $now)) {
            return 'ask';
        }

        if ($this->usedBudgetToday($profile, $now) >= $profile->dailyBudget()) {
            return 'budget';
        }

        return 'ask';
    }

    public function questionForTopic(GrowthProfile $profile, string $slug, bool $includePaused = false): ?GrowthQuestion
    {
        $program = $this->programForSlug($profile, $slug, $includePaused);
        if (!$program) {
            return null;
        }

        $query = $program->questions()->with(['variants', 'schedule']);
        if (!$includePaused) {
            $query->where('active', true)->whereNull('paused_at');
        }

        return $query->latest('id')->first();
    }

    public function usedBudgetToday(GrowthProfile $profile, ?Carbon $now = null): int
    {
        $start = $this->dayWindowStart($profile, $now);
        $programIds = $profile->programs()->where('status', 'active')->pluck('id');
        $used = [];

        $answeredQuestionIds = GrowthResponse::query()
            ->whereIn(
                'growth_question_id',
                GrowthQuestion::whereIn('growth_program_id', $programIds)->pluck('id')
            )
            ->where('answered_at', '>=', $start)
            ->pluck('growth_question_id');

        foreach (GrowthQuestion::whereIn('id', $answeredQuestionIds)->pluck('growth_program_id') as $programId) {
            $used[(int) $programId] = true;
        }

        $sentQuestionIds = GrowthQuestionSchedule::query()
            ->whereIn(
                'growth_question_id',
                GrowthQuestion::whereIn('growth_program_id', $programIds)->pluck('id')
            )
            ->where('last_sent_at', '>=', $start)
            ->pluck('growth_question_id');

        foreach (GrowthQuestion::whereIn('id', $sentQuestionIds)->pluck('growth_program_id') as $programId) {
            $used[(int) $programId] = true;
        }

        return count($used);
    }

    private function topicSentInDayWindow(GrowthProfile $profile, GrowthProfileTopic $topic, ?Carbon $now = null): bool
    {
        $question = $this->questionForTopic($profile, $topic->template_slug, true);
        $schedule = $question?->schedule;
        if (!$schedule?->last_sent_at) {
            return false;
        }

        return $schedule->last_sent_at->gte($this->dayWindowStart($profile, $now));
    }

    public function weeklyReviewText(GrowthProfile $profile): string
    {
        $data = $this->weeklyReviewData($profile);
        $lines = ['<b>'.e(trans('growth_companion.weekly_section_title')).'</b>', ''];

        $lines[] = '<b>'.e(trans('growth_companion.weekly_wins')).'</b>';
        $lines[] = trans('growth_companion.weekly_count', ['count' => count($data['wins'])]);
        foreach ($data['wins'] as $win) {
            $lines[] = '✓ '.$this->e($win);
        }
        if ($data['wins'] === []) {
            $lines[] = e(trans('growth_companion.weekly_review_empty'));
        }

        $lines[] = '';
        $lines[] = '<b>'.e(trans('growth_companion.weekly_challenges')).'</b>';
        $lines[] = trans('growth_companion.weekly_count', ['count' => count($data['challenges'])]);
        foreach ($data['challenges'] as $item) {
            $lines[] = '· '.$this->e($item);
        }

        $lines[] = '';
        $lines[] = '<b>'.e(trans('growth_companion.weekly_learned')).'</b>';
        $lines[] = trans('growth_companion.weekly_count', ['count' => count($data['notes'])]);
        foreach ($data['notes'] as $note) {
            $lines[] = '· '.$this->e($note);
        }

        $lines[] = '';
        $lines[] = '<b>'.e(trans('growth_companion.weekly_next')).'</b>';
        foreach ($data['next'] as $i => $action) {
            $lines[] = ($i + 1).'. '.$this->e($action);
        }

        $stats = $data['stats'];
        $lines[] = '';
        $lines[] = e(trans('growth_companion.week_line', [
            'done' => $stats['days'],
            'total' => 7,
        ])).' '.$this->progressBar((int) $stats['days']);
        if ($stats['moved'] > 0) {
            $lines[] = e(trans('growth_companion.week_moved', ['count' => $stats['moved']]));
        }
        if ($stats['sleep_ok'] > 0) {
            $lines[] = e(trans('growth_companion.week_sleep', ['count' => $stats['sleep_ok']]));
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{wins: list<string>, challenges: list<string>, notes: list<string>, next: list<string>, stats: array}
     */
    public function weeklyReviewData(GrowthProfile $profile, ?Carbon $now = null): array
    {
        $start = $this->weekWindowStart($profile, $now);
        $wins = [];
        $challenges = [];
        $notes = [];

        foreach ($this->enabledTopics($profile) as $topic) {
            $question = $this->questionForTopic($profile, $topic->template_slug, true);
            $count = 0;
            if ($question) {
                $responses = $question->responses()->where('answered_at', '>=', $start)->orderBy('answered_at')->get();
                $count = $responses->count();
                foreach ($responses as $response) {
                    foreach ($this->bulletSummary((string) $response->body, 2) as $bullet) {
                        $notes[] = $bullet;
                    }
                }
            }
            if ($count > 0) {
                $wins[] = $topic->displayLabel();
            } else {
                $challenges[] = $topic->displayLabel();
            }
        }

        $notes = array_slice(array_values(array_unique($notes)), 0, 4);
        $stats = $this->weekCheckinStats($profile, $now);
        $next = [];
        foreach (array_slice($challenges, 0, 2) as $challenge) {
            $next[] = trans('growth_companion.weekly_next_topic', ['topic' => $challenge]);
        }
        if ($stats['days'] < 5) {
            $next[] = trans('growth_companion.weekly_next_checkin');
        }
        if ($next === []) {
            $next[] = trans('growth_companion.weekly_next_keep');
        }
        $next = array_slice($next, 0, 3);

        return [
            'wins' => $wins,
            'challenges' => array_slice($challenges, 0, 6),
            'notes' => $notes,
            'next' => $next,
            'stats' => $stats,
        ];
    }

    public function dayKey(GrowthProfile $profile, ?Carbon $now = null): string
    {
        $tz = $profile->timezone ?: 'Asia/Tehran';

        return $this->dayWindowStart($profile, $now)->timezone($tz)->toDateString();
    }

    public function todayCheckin(GrowthProfile $profile, ?Carbon $now = null): ?GrowthDailyCheckin
    {
        return GrowthDailyCheckin::where('growth_profile_id', $profile->id)
            ->where('day_key', $this->dayKey($profile, $now))
            ->first();
    }

    public function upsertCheckin(GrowthProfile $profile, array $attrs, ?Carbon $now = null): GrowthDailyCheckin
    {
        $row = GrowthDailyCheckin::firstOrNew([
            'growth_profile_id' => $profile->id,
            'day_key' => $this->dayKey($profile, $now),
        ]);
        $row->fill($attrs);
        $row->save();

        return $row;
    }

    public function nextOpenTopic(GrowthProfile $profile, ?Carbon $now = null): ?GrowthProfileTopic
    {
        foreach ($this->enabledTopics($profile) as $topic) {
            if ($this->canOpenTopic($profile, $topic, $now) === 'ask') {
                return $topic;
            }
        }

        return null;
    }

    public function weekCheckinStats(GrowthProfile $profile, ?Carbon $now = null): array
    {
        $start = $this->weekWindowStart($profile, $now);
        $tz = $profile->timezone ?: 'Asia/Tehran';
        $fromKey = $start->copy()->timezone($tz)->toDateString();
        $checkins = GrowthDailyCheckin::where('growth_profile_id', $profile->id)
            ->where('day_key', '>=', $fromKey)
            ->get();

        $days = $checkins->pluck('day_key')->unique()->count();
        $programIds = $profile->programs()->where('status', 'active')->pluck('id');
        $questionIds = GrowthQuestion::whereIn('growth_program_id', $programIds)->pluck('id');
        $responseDays = GrowthResponse::whereIn('growth_question_id', $questionIds)
            ->where('answered_at', '>=', $start)
            ->get()
            ->map(fn (GrowthResponse $response) => $this->dayKey($profile, $response->answered_at?->timezone($tz)))
            ->unique()
            ->count();

        return [
            'days' => max($days, $responseDays),
            'moved' => $checkins->where('moved', true)->count(),
            'sleep_ok' => $checkins->filter(fn ($row) => (int) $row->sleep_hours >= 7)->count(),
        ];
    }

    public function recentResponses(GrowthProfile $profile, int $limit = 10)
    {
        $programIds = $profile->programs()->pluck('id');
        $questionIds = GrowthQuestion::whereIn('growth_program_id', $programIds)->pluck('id');

        return GrowthResponse::whereIn('growth_question_id', $questionIds)
            ->with('question.program')
            ->orderByDesc('answered_at')
            ->limit($limit)
            ->get();
    }

    public function bulletSummary(string $body, int $limit = 6): array
    {
        $parts = preg_split('/[\r\n]+|(?<=[.!?؟])\s+/u', $body) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $out[] = mb_strlen($part) > 80 ? mb_substr($part, 0, 77).'…' : $part;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    public function progressBar(int $filled, int $total = 7): string
    {
        $filled = max(0, min($total, $filled));

        return str_repeat('▰', $filled).str_repeat('▱', $total - $filled);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function saveWeeklyReview(GrowthProfile $profile, string $body): GrowthReview
    {
        return GrowthReview::create([
            'growth_profile_id' => $profile->id,
            'period_start' => $this->weekWindowStart($profile),
            'period_end' => now(),
            'body' => $body,
            'stats' => ['answered' => $this->usedBudgetToday($profile)],
        ]);
    }

    public function exportData(GrowthProfile $profile): array
    {
        $topics = [];
        foreach ($this->enabledTopics($profile) as $topic) {
            $topics[] = [
                'slug' => $topic->template_slug,
                'label' => $topic->displayLabel(),
                'cadence' => $topic->cadence,
            ];
        }

        $responses = [];
        $programIds = $profile->programs()->pluck('id');
        $questions = GrowthQuestion::whereIn('growth_program_id', $programIds)->get();
        foreach ($questions as $question) {
            foreach ($question->responses()->orderBy('answered_at')->get() as $response) {
                $responses[] = [
                    'question_key' => $question->question_key,
                    'answered_at' => optional($response->answered_at)?->toIso8601String(),
                    'body' => $response->body,
                ];
            }
        }

        return [
            'exported_at' => now()->toIso8601String(),
            'intensity' => $profile->intensity,
            'timezone' => $profile->timezone,
            'topics' => $topics,
            'responses' => $responses,
        ];
    }

    public function generateAiVariants(GrowthQuestion $question, string $locale, int $count = 3): int
    {
        $bodies = $this->llm->generateVariants(
            (string) ($question->intent ?: $question->question_key),
            (string) ($question->domain ?: 'self'),
            (int) ($question->difficulty ?: 1),
            $locale,
            $count
        );

        $added = 0;
        foreach ($bodies as $body) {
            $exists = $question->variants()
                ->where('locale', $locale)
                ->where('body', $body)
                ->exists();
            if ($exists) {
                continue;
            }
            GrowthQuestionVariant::create([
                'growth_question_id' => $question->id,
                'body' => $body,
                'locale' => $locale,
                'difficulty' => $question->difficulty ?: 1,
            ]);
            $added++;
        }

        return $added;
    }

    public function setAiConsent(GrowthProfile $profile, bool $consent): void
    {
        $profile->ai_consent = $consent;
        $profile->save();
    }

    public function setMode(GrowthProfile $profile, string $mode): void
    {
        $profile->mode = $mode === 'advanced' ? 'advanced' : 'simple';
        $profile->save();
    }

    public function addableSlugs(GrowthProfile $profile): array
    {
        $enabled = $profile->topics()->where('enabled', true)->pluck('template_slug')->all();
        $catalog = array_values(array_unique(array_merge(
            GrowthProfile::CATALOG_SLUGS,
            GrowthTemplate::query()->pluck('slug')->all()
        )));

        return array_values(array_filter(
            $catalog,
            fn (string $slug) => !in_array($slug, $enabled, true)
        ));
    }

    public function dispatchSkipReason(GrowthQuestionSchedule $schedule, GrowthProfile $profile): ?string
    {
        if ($this->inQuietHours($profile)) {
            return 'quiet';
        }

        $question = $schedule->question;
        $program = $question?->program;
        if (!$question || !$program) {
            return 'missing';
        }

        $topic = GrowthProfileTopic::where('growth_profile_id', $profile->id)
            ->where('template_slug', $program->template_slug)
            ->first();

        if ($topic && !$topic->enabled) {
            return 'disabled';
        }

        if ($topic && $this->isTopicDone($profile, $topic)) {
            return 'done';
        }

        $start = $this->dayWindowStart($profile);
        if ($schedule->last_sent_at && $schedule->last_sent_at->gte($start)) {
            return 'already_sent';
        }

        if ($this->budgetExhaustedToday($profile)) {
            return 'budget';
        }

        $days = $schedule->days_of_week ?: ($topic->weekdays ?? null);
        if (is_array($days) && $days !== []) {
            $tz = $profile->timezone ?: 'Asia/Tehran';
            $dow = Carbon::now($tz)->dayOfWeek;
            $days = array_map('intval', $days);
            if (!in_array($dow, $days, true)) {
                return 'weekday';
            }
        }

        return null;
    }

    public function recordResponse(
        GrowthQuestion $question,
        BotUsers $botUser,
        int $botId,
        string $body,
        ?int $variantId = null
    ): GrowthResponse {
        $response = GrowthResponse::create([
            'growth_question_id' => $question->id,
            'growth_question_variant_id' => $variantId,
            'bot_user_id' => $botUser->id,
            'bot_id' => $botId,
            'body' => $body,
            'answered_at' => now(),
        ]);

        Log::info('[GrowthCompanion] first_response', [
            'bot_id' => $botId,
            'question_id' => $question->id,
        ]);

        return $response;
    }

    public function pauseActiveQuestion(GrowthProfile $profile): bool
    {
        $updated = 0;
        foreach ($profile->activePrograms()->get() as $program) {
            $updated += $program->questions()->update(['paused_at' => now()]);
        }

        return $updated > 0;
    }

    public function unpauseActiveQuestion(GrowthProfile $profile): bool
    {
        $updated = 0;
        foreach ($profile->activePrograms()->get() as $program) {
            $questions = $program->questions()->get();
            foreach ($questions as $question) {
                $question->paused_at = null;
                $question->active = true;
                $question->save();
                $schedule = $question->schedule;
                if ($schedule) {
                    $schedule->next_due_at = $this->computeNextDueAt($profile, $question->frequency);
                    $schedule->save();
                }
                $updated++;
            }
        }

        return $updated > 0;
    }

    public function setFrequency(GrowthProfile $profile, string $frequency): bool
    {
        if (!in_array($frequency, ['daily', 'weekly'], true)) {
            return false;
        }

        $ok = false;
        foreach ($this->enabledTopics($profile) as $topic) {
            if ($this->setTopicCadence($profile, $topic->template_slug, $frequency)) {
                $ok = true;
            }
        }

        return $ok;
    }

    public function addCustomQuestion(GrowthProfile $profile, string $text): GrowthQuestion
    {
        $program = $profile->activeProgram();
        if (!$program) {
            $topic = $this->addCustomTopic($profile, trans('growth_companion.custom_program'));
            $program = $this->programForSlug($profile, $topic->template_slug, true);
        }

        $question = GrowthQuestion::create([
            'growth_program_id' => $program->id,
            'question_key' => 'user_custom_'.time(),
            'intent' => 'custom_checkin',
            'domain' => 'custom',
            'difficulty' => 1,
            'frequency' => 'daily',
            'source' => 'user',
            'active' => true,
        ]);

        GrowthQuestionVariant::create([
            'growth_question_id' => $question->id,
            'body' => $text,
            'locale' => app()->getLocale() ?: 'fa',
            'difficulty' => 1,
        ]);

        GrowthQuestionSchedule::create([
            'growth_question_id' => $question->id,
            'cadence_type' => 'daily',
            'time_local' => $profile->notify_time,
            'next_due_at' => $this->computeNextDueAt($profile, 'daily'),
        ]);

        return $question->fresh(['variants', 'schedule']);
    }

    public function deleteProgram(GrowthProfile $profile): bool
    {
        $had = false;
        foreach ($profile->programs as $program) {
            $had = true;
            $program->status = 'deleted';
            $program->save();
            $program->questions()->update(['active' => false, 'paused_at' => now()]);
        }
        $profile->topics()->update(['enabled' => false]);

        return $had;
    }

    public function deleteAllGrowthData(BotUsers $botUser, int $botId): void
    {
        $profiles = GrowthProfile::where('bot_user_id', $botUser->id)->where('bot_id', $botId)->get();
        foreach ($profiles as $profile) {
            $profile->reviews()->delete();
            $profile->checkins()->delete();
            $profile->topics()->delete();
            foreach ($profile->programs as $program) {
                foreach ($program->questions as $question) {
                    $question->responses()->delete();
                    $question->schedule()->delete();
                    $question->variants()->delete();
                    $question->delete();
                }
                $program->delete();
            }
            $profile->delete();
        }

        Log::info('[GrowthCompanion] delete_all_data', [
            'bot_id' => $botId,
            'bot_user_id' => $botUser->id,
        ]);
    }

    public function pickVariant(GrowthQuestion $question, int $botUserId, ?string $locale = null): ?GrowthQuestionVariant
    {
        return $this->selector->pickVariant($question, $botUserId, $locale);
    }

    public function computeNextDueAt(GrowthProfile $profile, string $frequency, ?Carbon $from = null): Carbon
    {
        $tz = $profile->timezone ?: 'Asia/Tehran';
        $from = ($from ?: Carbon::now($tz))->copy()->timezone($tz);
        $time = $profile->notify_time ?: '21:00:00';
        $next = Carbon::parse($from->toDateString().' '.$time, $tz);

        if ($next->lte($from)) {
            $next->addDay();
        }

        if ($frequency === 'weekly') {
            $next->addWeek();
        }

        return $next->utc();
    }

    public function dueSchedules(int $limit = 50): Collection
    {
        return GrowthQuestionSchedule::query()
            ->where('next_due_at', '<=', now())
            ->whereHas('question', function ($query) {
                $query->where('active', true)
                    ->whereNull('paused_at')
                    ->whereHas('program', function ($program) {
                        $program->where('status', 'active');
                    });
            })
            ->with(['question.variants', 'question.program.profile'])
            ->orderBy('next_due_at')
            ->limit($limit)
            ->get();
    }

    public function budgetExhaustedToday(GrowthProfile $profile): bool
    {
        return $this->usedBudgetToday($profile) >= $profile->dailyBudget();
    }

    public function inQuietHours(GrowthProfile $profile): bool
    {
        if (!$profile->quiet_hours_start || !$profile->quiet_hours_end) {
            return false;
        }

        $tz = $profile->timezone ?: 'Asia/Tehran';
        $now = Carbon::now($tz)->format('H:i:s');
        $start = (string) $profile->quiet_hours_start;
        $end = (string) $profile->quiet_hours_end;

        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }

        return $now >= $start || $now <= $end;
    }

    public function markSent(GrowthQuestionSchedule $schedule, GrowthProfile $profile): void
    {
        $question = $schedule->question;
        $schedule->last_sent_at = now();
        $schedule->next_due_at = $this->computeNextDueAt($profile, $question->frequency ?? $schedule->cadence_type);
        $schedule->save();
    }

    public function activeQuestion(GrowthProfile $profile, bool $includePaused = false): ?GrowthQuestion
    {
        $program = $profile->activeProgram();
        if (!$program) {
            return null;
        }

        $query = $program->questions()->with(['variants', 'schedule']);
        if (!$includePaused) {
            $query->where('active', true)->whereNull('paused_at');
        }

        return $query->latest('id')->first();
    }

    private function ensureProgramForTopic(GrowthProfile $profile, GrowthProfileTopic $topic): GrowthProgram
    {
        $program = $this->programForSlug($profile, $topic->template_slug, true);
        if ($program) {
            $program->status = 'active';
            if ($topic->custom_label) {
                $program->name = $topic->custom_label;
            }
            $program->save();
            $program->questions()->update(['active' => true, 'paused_at' => null]);

            return $program;
        }

        $template = GrowthTemplate::where('slug', $topic->template_slug)->with('questions')->first();
        $name = $topic->custom_label
            ?: ($template?->name ?? $topic->displayLabel());

        $program = GrowthProgram::create([
            'growth_profile_id' => $profile->id,
            'bot_id' => $profile->bot_id,
            'bot_user_id' => $profile->bot_user_id,
            'name' => $name,
            'template_slug' => $topic->template_slug,
            'status' => 'active',
            'settings' => ['frequency' => $topic->cadence ?: 'daily'],
        ]);

        $frequency = $topic->cadence ?: 'daily';
        if ($template && $template->questions->isNotEmpty()) {
            foreach ($template->questions as $templateQuestion) {
                $this->instantiateTemplateQuestion($program, $templateQuestion, $frequency, $profile);
            }
        } else {
            $this->createGenericQuestion($program, $profile, $frequency, $topic->template_slug);
        }

        return $program;
    }

    private function programForSlug(GrowthProfile $profile, string $slug, bool $includeInactive = false): ?GrowthProgram
    {
        $query = $profile->programs()->where('template_slug', $slug);
        if (!$includeInactive) {
            $query->where('status', 'active');
        }

        return $query->latest('id')->first();
    }

    private function syncQuestionCadence(GrowthProfile $profile, string $slug, string $cadence): void
    {
        $program = $this->programForSlug($profile, $slug, true);
        if (!$program) {
            return;
        }

        $settings = $program->settings ?? [];
        $settings['frequency'] = $cadence;
        $program->settings = $settings;
        $program->save();

        foreach ($program->questions as $question) {
            $question->frequency = $cadence;
            $question->save();
            $schedule = $question->schedule;
            if ($schedule) {
                $schedule->cadence_type = $cadence;
                $schedule->next_due_at = $this->computeNextDueAt($profile, $cadence);
                $schedule->save();
            }
        }
    }

    private function customSlugForLabel(?string $label): string
    {
        $seed = $label ?: (string) microtime(true);

        return 'u'.substr(sha1($seed), 0, 8);
    }

    private function instantiateTemplateQuestion(
        GrowthProgram $program,
        $templateQuestion,
        string $frequency,
        GrowthProfile $profile
    ): GrowthQuestion {
        $question = GrowthQuestion::create([
            'growth_program_id' => $program->id,
            'question_key' => $templateQuestion->question_key,
            'intent' => $templateQuestion->intent,
            'domain' => $templateQuestion->domain,
            'difficulty' => $templateQuestion->difficulty ?: 1,
            'frequency' => $frequency,
            'source' => 'template',
            'active' => true,
        ]);

        foreach ($templateQuestion->variants ?? [] as $variant) {
            if (!is_array($variant) || empty($variant['body'])) {
                continue;
            }
            GrowthQuestionVariant::create([
                'growth_question_id' => $question->id,
                'body' => $variant['body'],
                'locale' => $variant['locale'] ?? 'fa',
                'tone' => $variant['tone'] ?? null,
                'difficulty' => $variant['difficulty'] ?? 1,
            ]);
        }

        GrowthQuestionSchedule::create([
            'growth_question_id' => $question->id,
            'cadence_type' => $frequency,
            'time_local' => $profile->notify_time,
            'next_due_at' => $this->computeNextDueAt($profile, $frequency),
        ]);

        return $question;
    }

    private function createGenericQuestion(
        GrowthProgram $program,
        GrowthProfile $profile,
        string $frequency,
        string $focus
    ): GrowthQuestion {
        $question = GrowthQuestion::create([
            'growth_program_id' => $program->id,
            'question_key' => 'daily_self_reflection',
            'intent' => 'daily_reflection',
            'domain' => $focus,
            'difficulty' => 1,
            'frequency' => $frequency,
            'source' => 'template',
            'active' => true,
        ]);

        GrowthQuestionVariant::create([
            'growth_question_id' => $question->id,
            'body' => trans('growth_companion.generic_question'),
            'locale' => app()->getLocale() ?: 'fa',
            'difficulty' => 1,
        ]);

        GrowthQuestionSchedule::create([
            'growth_question_id' => $question->id,
            'cadence_type' => $frequency,
            'time_local' => $profile->notify_time,
            'next_due_at' => $this->computeNextDueAt($profile, $frequency),
        ]);

        return $question;
    }
}
