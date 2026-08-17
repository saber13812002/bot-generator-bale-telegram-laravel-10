<?php

namespace App\Interfaces\Services;

use App\Models\BotUsers;
use App\Models\GrowthProfile;
use App\Models\GrowthProgram;
use App\Models\GrowthQuestion;
use App\Models\GrowthQuestionSchedule;
use App\Models\GrowthQuestionVariant;
use App\Models\GrowthResponse;

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

    public function computeNextDueAt(GrowthProfile $profile, string $frequency, ?\Carbon\Carbon $from = null): \Carbon\Carbon;

    /**
     * @return \Illuminate\Support\Collection<int, GrowthQuestionSchedule>
     */
    public function dueSchedules(int $limit = 50);

    public function budgetExhaustedToday(GrowthProfile $profile): bool;

    public function inQuietHours(GrowthProfile $profile): bool;

    public function markSent(GrowthQuestionSchedule $schedule, GrowthProfile $profile): void;

    public function activeQuestion(GrowthProfile $profile, bool $includePaused = false): ?GrowthQuestion;
}
