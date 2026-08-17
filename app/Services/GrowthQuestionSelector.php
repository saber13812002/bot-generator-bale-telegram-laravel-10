<?php

namespace App\Services;

use App\Models\GrowthQuestion;
use App\Models\GrowthQuestionVariant;
use App\Models\GrowthResponse;
use Carbon\Carbon;

class GrowthQuestionSelector
{
    public function pickVariant(GrowthQuestion $question, int $botUserId, ?string $locale = null): ?GrowthQuestionVariant
    {
        $variants = $question->variants()->get();
        if ($variants->isEmpty()) {
            return null;
        }

        $locale = $locale ?: app()->getLocale();
        $localized = $variants->where('locale', $locale);
        if ($localized->isEmpty()) {
            $localized = $variants->where('locale', 'fa');
        }
        if ($localized->isEmpty()) {
            $localized = $variants;
        }

        $usedIds = GrowthResponse::query()
            ->where('bot_user_id', $botUserId)
            ->where('growth_question_id', $question->id)
            ->where('answered_at', '>=', Carbon::now()->subDays(30))
            ->whereNotNull('growth_question_variant_id')
            ->pluck('growth_question_variant_id')
            ->all();

        $unused = $localized->whereNotIn('id', $usedIds);
        if ($unused->isNotEmpty()) {
            return $unused->first();
        }

        $leastRecentId = GrowthResponse::query()
            ->where('bot_user_id', $botUserId)
            ->where('growth_question_id', $question->id)
            ->whereIn('growth_question_variant_id', $localized->pluck('id'))
            ->orderBy('answered_at')
            ->value('growth_question_variant_id');

        if ($leastRecentId) {
            return $localized->firstWhere('id', $leastRecentId) ?? $localized->first();
        }

        return $localized->first();
    }
}
