<?php

namespace App\Services;

use App\Interfaces\Services\GrowthCompanionService;
use App\Models\BotUsers;
use App\Models\GrowthProfile;
use App\Models\GrowthProgram;
use App\Models\GrowthQuestion;
use App\Models\GrowthQuestionSchedule;
use App\Models\GrowthQuestionVariant;
use App\Models\GrowthResponse;
use App\Models\GrowthTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GrowthCompanionServiceImpl implements GrowthCompanionService
{
    public function __construct(private GrowthQuestionSelector $selector)
    {
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
        $frequency = $intensity === 'minimal' ? 'weekly' : 'daily';
        $budget = $intensity === 'active' ? 2 : 1;

        $profile->intensity = $intensity;
        $profile->notify_time = $notifyTime;
        $profile->interaction_budget_per_day = $budget;
        $profile->onboarding_completed_at = now();
        $profile->save();

        $profile->programs()->where('status', 'active')->update(['status' => 'paused']);

        $template = GrowthTemplate::where('slug', $focusSlug)->with('questions')->first();
        $programName = $customFocus
            ?: ($template?->name ?? trans('growth_companion.focus.'.$focusSlug, [], app()->getLocale()));

        $program = GrowthProgram::create([
            'growth_profile_id' => $profile->id,
            'bot_id' => $profile->bot_id,
            'bot_user_id' => $profile->bot_user_id,
            'name' => $programName,
            'template_slug' => $focusSlug,
            'status' => 'active',
            'settings' => ['frequency' => $frequency],
        ]);

        if ($template && $template->questions->isNotEmpty()) {
            foreach ($template->questions as $templateQuestion) {
                $this->instantiateTemplateQuestion($program, $templateQuestion, $frequency, $profile);
            }
        } else {
            $this->createGenericQuestion($program, $profile, $frequency, $customFocus ?: $focusSlug);
        }

        Log::info('[GrowthCompanion] Onboarding completed', [
            'bot_id' => $profile->bot_id,
            'profile_id' => $profile->id,
            'focus' => $focusSlug,
        ]);

        return $program->fresh(['questions.variants', 'questions.schedule']);
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
        $question = $this->activeQuestion($profile);
        if (!$question) {
            return false;
        }

        $question->paused_at = now();
        $question->save();

        return true;
    }

    public function unpauseActiveQuestion(GrowthProfile $profile): bool
    {
        $question = $this->activeQuestion($profile, includePaused: true);
        if (!$question) {
            return false;
        }

        $question->paused_at = null;
        $question->active = true;
        $question->save();

        $schedule = $question->schedule;
        if ($schedule) {
            $schedule->next_due_at = $this->computeNextDueAt($profile, $question->frequency);
            $schedule->save();
        }

        return true;
    }

    public function setFrequency(GrowthProfile $profile, string $frequency): bool
    {
        if (!in_array($frequency, ['daily', 'weekly'], true)) {
            return false;
        }

        $question = $this->activeQuestion($profile, includePaused: true);
        if (!$question) {
            return false;
        }

        $question->frequency = $frequency;
        $question->save();

        $schedule = $question->schedule;
        if ($schedule) {
            $schedule->cadence_type = $frequency;
            $schedule->next_due_at = $this->computeNextDueAt($profile, $frequency);
            $schedule->save();
        }

        $program = $question->program;
        $settings = $program->settings ?? [];
        $settings['frequency'] = $frequency;
        $program->settings = $settings;
        $program->save();

        return true;
    }

    public function addCustomQuestion(GrowthProfile $profile, string $text): GrowthQuestion
    {
        $program = $profile->activeProgram();
        if (!$program) {
            $program = GrowthProgram::create([
                'growth_profile_id' => $profile->id,
                'bot_id' => $profile->bot_id,
                'bot_user_id' => $profile->bot_user_id,
                'name' => trans('growth_companion.custom_program'),
                'template_slug' => 'custom',
                'status' => 'active',
            ]);
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
        $program = $profile->activeProgram();
        if (!$program) {
            return false;
        }

        $program->status = 'deleted';
        $program->save();
        $program->questions()->update(['active' => false, 'paused_at' => now()]);

        return true;
    }

    public function deleteAllGrowthData(BotUsers $botUser, int $botId): void
    {
        $profiles = GrowthProfile::where('bot_user_id', $botUser->id)->where('bot_id', $botId)->get();
        foreach ($profiles as $profile) {
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
        $tz = $profile->timezone ?: 'Asia/Tehran';
        $start = Carbon::now($tz)->startOfDay()->utc();
        $end = Carbon::now($tz)->endOfDay()->utc();
        $budget = max(1, (int) $profile->interaction_budget_per_day);

        $sent = GrowthQuestionSchedule::query()
            ->whereHas('question.program', function ($query) use ($profile) {
                $query->where('growth_profile_id', $profile->id);
            })
            ->whereBetween('last_sent_at', [$start, $end])
            ->count();

        return $sent >= $budget;
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
