<?php

namespace App\Interfaces\Services;

use App\Models\BotUsers;
use App\Models\GrowthProfile;
use App\Models\GrowthProfileTopic;
use App\Models\GrowthProgram;
use App\Models\GrowthQuestion;
use App\Models\GrowthQuestionSchedule;
use App\Models\GrowthQuestionVariant;
use App\Models\GrowthResponse;
use App\Models\GrowthReview;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface GrowthCompanionService
{
    public function getOrCreateProfile(BotUsers $botUser, int $botId): GrowthProfile;

    public function completeOnboarding(
        GrowthProfile $profile,
        string $focusSlug,
        string $intensity,
        string $notifyTime,
        ?string $customFocus = null
    ): GrowthProgram;

    public function recordResponse(
        GrowthQuestion $question,
        BotUsers $botUser,
        int $botId,
        string $body,
        ?int $variantId = null
    ): GrowthResponse;

    public function pauseActiveQuestion(GrowthProfile $profile): bool;

    public function unpauseActiveQuestion(GrowthProfile $profile): bool;

    public function setFrequency(GrowthProfile $profile, string $frequency): bool;

    public function addCustomQuestion(GrowthProfile $profile, string $text): GrowthQuestion;

    public function deleteProgram(GrowthProfile $profile): bool;

    public function deleteAllGrowthData(BotUsers $botUser, int $botId): void;

    public function pickVariant(GrowthQuestion $question, int $botUserId, ?string $locale = null): ?GrowthQuestionVariant;

    public function computeNextDueAt(GrowthProfile $profile, string $frequency, ?Carbon $from = null): Carbon;

    /**
     * @return Collection<int, GrowthQuestionSchedule>
     */
    public function dueSchedules(int $limit = 50);

    public function budgetExhaustedToday(GrowthProfile $profile): bool;

    public function inQuietHours(GrowthProfile $profile): bool;

    public function markSent(GrowthQuestionSchedule $schedule, GrowthProfile $profile): void;

    public function activeQuestion(GrowthProfile $profile, bool $includePaused = false): ?GrowthQuestion;

    public function ensureDefaultBoard(GrowthProfile $profile, ?string $primarySlug = null, ?string $customLabel = null): void;

    /**
     * @return Collection<int, GrowthProfileTopic>
     */
    public function enabledTopics(GrowthProfile $profile): Collection;

    public function enableTopic(GrowthProfile $profile, string $slug, ?string $customLabel = null): GrowthProfileTopic;

    public function addCustomTopic(GrowthProfile $profile, string $label): GrowthProfileTopic;

    public function disableTopic(GrowthProfile $profile, string $slug): bool;

    public function setTopicCadence(GrowthProfile $profile, string $slug, string $cadence): bool;

    public function toggleTopicWeekday(GrowthProfile $profile, string $slug, int $weekday): bool;

    public function setIntensity(GrowthProfile $profile, string $intensity): void;

    public function dayWindowStart(GrowthProfile $profile, ?Carbon $now = null): Carbon;

    public function weekWindowStart(GrowthProfile $profile, ?Carbon $now = null): Carbon;

    public function isTopicDone(GrowthProfile $profile, GrowthProfileTopic $topic, ?Carbon $now = null): bool;

    public function canOpenTopic(GrowthProfile $profile, GrowthProfileTopic $topic, ?Carbon $now = null): string;

    public function questionForTopic(GrowthProfile $profile, string $slug, bool $includePaused = false): ?GrowthQuestion;

    public function usedBudgetToday(GrowthProfile $profile, ?Carbon $now = null): int;

    public function weeklyReviewText(GrowthProfile $profile): string;

    public function saveWeeklyReview(GrowthProfile $profile, string $body): GrowthReview;

    public function exportData(GrowthProfile $profile): array;

    public function generateAiVariants(GrowthQuestion $question, string $locale, int $count = 3): int;

    public function setAiConsent(GrowthProfile $profile, bool $consent): void;

    public function setMode(GrowthProfile $profile, string $mode): void;

    /**
     * @return list<string>
     */
    public function addableSlugs(GrowthProfile $profile): array;

    public function dispatchSkipReason(GrowthQuestionSchedule $schedule, GrowthProfile $profile): ?string;

    public static function budgetForIntensity(string $intensity): int;

    public function dayKey(GrowthProfile $profile, ?Carbon $now = null): string;

    public function todayCheckin(GrowthProfile $profile, ?Carbon $now = null): ?\App\Models\GrowthDailyCheckin;

    public function upsertCheckin(GrowthProfile $profile, array $attrs, ?Carbon $now = null): \App\Models\GrowthDailyCheckin;

    public function nextOpenTopic(GrowthProfile $profile, ?Carbon $now = null): ?GrowthProfileTopic;

    public function weekCheckinStats(GrowthProfile $profile, ?Carbon $now = null): array;

    /**
     * @return \Illuminate\Support\Collection<int, GrowthResponse>
     */
    public function recentResponses(GrowthProfile $profile, int $limit = 10);

    /**
     * @return list<string>
     */
    public function bulletSummary(string $body, int $limit = 6): array;
}
