<?php

namespace App\Services\GrowthCompanion;

use App\Interfaces\Services\GrowthCompanionService;
use App\Interfaces\Services\GrowthMessengerFactory;
use App\Models\AiJob;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\GrowthAssistantMessage;
use App\Models\GrowthDailyCheckin;
use App\Models\GrowthProfile;
use App\Services\Ai\AiFeatureGate;
use App\Services\Ai\AiJobDispatcher;
use App\Services\AiProviderService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WS-H — دستیار رشدیار: پاسخ LLM به متن آزاد کاربر.
 *
 * دو نیمه‌ی مستقل:
 *  - triggerForMessage(): همزمان با webhook — gate → ردیف پیام user → dispatch به ai_jobs.
 *    هرگز throw نمی‌کند؛ در هر خطا {'dispatched' => false} برمی‌گردد تا
 *    controller روی کارت خانه fallback کند (اصل: شکست AI bot را نمی‌شکند).
 *  - execute(): توسط ProcessAiJob در worker — prompt از template + context،
 *    یک call به chat LiteLLM، persistence ردیف assistant، ارسال پاسخ.
 *    پاسخ «قبل» از ارسال روی DB ثبت می‌شود؛ شکست ارسال، job را نمی‌شکند.
 */
class GrowthAssistantService
{
    private const PROMPT_TEMPLATE = 'growth_prompts/companion.v1.md';

    /**
     * Trigger همزمان (webhook).
     *
     * @return array{dispatched: bool, job: ?AiJob, reason: string}
     */
    public function triggerForMessage(GrowthProfile $profile, Bot $bot, string $text, BotUsers $botUser): array
    {
        try {
            $isPro = (bool) app(GrowthCompanionService::class)->isPro($profile);

            $gate = (new AiFeatureGate())->check(
                AiFeatureGate::FEATURE_GROWTH,
                (int) $profile->bot_id,
                (int) $profile->bot_user_id,
                (int) $botUser->id,
                $isPro
            );

            if (! $gate['allowed']) {
                return ['dispatched' => false, 'job' => null, 'reason' => $gate['reason']];
            }

            $message = GrowthAssistantMessage::create([
                'growth_profile_id' => $profile->id,
                'bot_user_id' => $botUser->id,
                'role' => 'user',
                'content' => $text,
            ]);

            $result = AiJobDispatcher::dispatch('growth_companion', [
                'profile_id' => (int) $profile->id,
                'message_id' => (int) $message->id,
                'user_text' => $text,
                'bot_id' => (int) $profile->bot_id,
                'bot_user_id' => (int) $profile->bot_user_id,
            ], [
                'bot_id' => (int) $profile->bot_id,
                'customer_id' => (int) $profile->id,
                'user_id' => (int) $botUser->id,
                'is_pro' => $isPro,
                'model' => config('ai.growth_companion_model'),
            ]);

            return [
                'dispatched' => true,
                'job' => $result['job'],
                'reason' => $result['deferred'] ? 'deferred' : 'queued',
            ];
        } catch (Throwable $e) {
            Log::warning('Growth assistant trigger error', [
                'error' => $e->getMessage(),
            ]);

            return ['dispatched' => false, 'job' => null, 'reason' => 'error'];
        }
    }

    /**
     * Executor (در صف ai / worker). فرمت بازگشتی مطابق قرارداد ProcessAiJob.
     *
     * @return array{success: bool, error_code: ?string, message: ?string, result_reference: ?string, usage?: array}
     */
    public function execute(AiJob $job): array
    {
        $payload = $job->payload ?? [];

        $message = GrowthAssistantMessage::find((int) ($payload['message_id'] ?? 0));
        if (! $message) {
            return $this->fail('message_not_found', 'User message not found.');
        }

        $profile = GrowthProfile::find((int) ($payload['profile_id'] ?? 0));
        if (! $profile) {
            return $this->fail('profile_not_found', 'Growth profile not found.');
        }

        $bot = Bot::find((int) ($payload['bot_id'] ?? 0));
        $botUser = BotUsers::find((int) ($payload['bot_user_id'] ?? 0));
        if (! $bot || ! $botUser) {
            return $this->fail('no_bot', 'Bot or bot user missing.');
        }

        $origin = $botUser->origin ?: 'telegram';
        $token = $origin === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
        if (! $token) {
            return $this->fail('no_bot_token', 'Bot token missing.');
        }

        if ($bot->language_code) {
            app()->setLocale($bot->language_code);
        }

        $prompt = $this->buildPrompt($profile, (string) $message->content);
        $chat = (new AiProviderService())->chat($prompt, 300, 0.4, 120);

        if (! $chat['success']) {
            $code = $this->classifyChatError((string) ($chat['response'] ?? ''));
            $this->persistError($message, $code);
            $this->maybeSendUnavailable($job, $botUser, $token, $origin);

            return $this->fail($code, (string) ($chat['response'] ?? ''));
        }

        $reply = trim((string) ($chat['response'] ?? ''));
        if ($reply === '') {
            $this->persistError($message, 'empty_result');
            $this->maybeSendUnavailable($job, $botUser, $token, $origin);

            return $this->fail('empty_result', 'LLM returned an empty response.');
        }

        $assistant = GrowthAssistantMessage::create([
            'growth_profile_id' => $profile->id,
            'bot_user_id' => $botUser->id,
            'role' => 'assistant',
            'content' => $reply,
            'usage' => $chat['usage'] ?? null,
            'ai_job_id' => (int) $job->id,
        ]);

        try {
            app(GrowthMessengerFactory::class)
                ->make($token, $origin)
                ->send((string) $botUser->chat_id, $reply);
        } catch (Throwable $e) {
            // پاسخ در DB ثبت شده؛ شکست ارسال، job را نمی‌شکند
            Log::warning('Growth assistant reply delivery failed (reply persisted)', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'error_code' => null,
            'message' => null,
            'result_reference' => 'assistant_message:'.$assistant->id,
            'usage' => $chat['usage'] ?? [],
        ];
    }

    /**
     * ساخت prompt از template + context کاربر (focus/checkin/جواب‌های اخیر).
     */
    private function buildPrompt(GrowthProfile $profile, string $userText): string
    {
        $template = (string) file_get_contents(resource_path(self::PROMPT_TEMPLATE));

        $focus = $profile->activePrograms()->pluck('name')->map(fn ($n) => (string) $n);
        $focusLines = $focus->isEmpty() ? '—' : $focus->implode("\n");

        $checkins = GrowthDailyCheckin::where('growth_profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->limit(7)
            ->get();
        $checkinLines = $checkins->isEmpty()
            ? '—'
            : $checkins->map(function (GrowthDailyCheckin $c) {
                return sprintf(
                    '%s | mood=%s | energy=%s | sleep=%s | moved=%s | note=%s',
                    (string) $c->day_key,
                    (string) ($c->mood ?? '؟'),
                    (string) ($c->energy ?? '؟'),
                    (string) ($c->sleep_hours ?? '؟'),
                    $c->moved ? 'بله' : 'خیر',
                    mb_substr((string) ($c->evening_note ?? ''), 0, 120)
                );
            })->implode("\n");

        $recent = app(GrowthCompanionService::class)->recentResponses($profile, 3);
        $recentLines = $recent->isEmpty()
            ? '—'
            : $recent->map(fn ($r) => sprintf(
                '%s: %s',
                $r->question?->program?->name ?? 'موضوع',
                mb_substr((string) $r->body, 0, 200)
            ))->implode("\n");

        return strtr($template, [
            '{{mode}}' => (string) $profile->mode,
            '{{intensity}}' => (string) $profile->intensity,
            '{{notify_time}}' => (string) $profile->notify_time,
            '{{focus_lines}}' => $focusLines,
            '{{checkin_lines}}' => $checkinLines,
            '{{recent_lines}}' => $recentLines,
            '{{user_text}}' => $userText,
        ]);
    }

    /**
     * طبقه‌بندی خطای chat — منطبق بر قواعد ProcessAiJob::classifyChatError
     * (پیام ورودی: 'HTTP {status}: {body300}' redact شده).
     */
    private function classifyChatError(string $message): string
    {
        $m = mb_strtolower($message);

        if (preg_match('/http\s+(401|403)/', $m) || str_contains($m, 'token_not_found') || str_contains($m, 'unauthorized')) {
            return 'auth_failed';
        }
        if (str_contains($m, '404') || str_contains($m, 'model_not_found') || str_contains($m, 'does not exist')) {
            return 'model_not_found';
        }
        if (str_contains($m, '429') || str_contains($m, 'rate limit') || str_contains($m, 'too many')) {
            return 'rate_limited';
        }
        if (preg_match('/HTTP\s+5\d\d/', $m)) {
            return 'provider_error';
        }
        if (str_contains($m, '503') || str_contains($m, 'unavailable') || str_contains($m, 'timeout')) {
            return 'provider_unavailable';
        }

        return 'provider_error';
    }

    /**
     * فقط در attempt ترمینال (بعد از +1 در ProcessAiJob: attempt_number >= max)
     * یک پیام کوتاه «دستیار در دسترس نیست» می‌فرستد. هرگز throw نمی‌کند.
     */
    private function maybeSendUnavailable(AiJob $job, BotUsers $botUser, string $token, string $origin): void
    {
        if ($job->attempt_number < (int) config('ai.queue.max_attempts', 5)) {
            return;
        }

        try {
            app(GrowthMessengerFactory::class)
                ->make($token, $origin)
                ->send((string) $botUser->chat_id, trans('growth_companion.assistant_unavailable'));
        } catch (Throwable $e) {
            Log::warning('Growth assistant unavailable notice failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function persistError(GrowthAssistantMessage $message, string $code): void
    {
        try {
            $message->forceFill(['error' => $code])->save();
        } catch (Throwable $e) {
            Log::warning('Growth assistant error persist failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function fail(string $errorCode, string $message): array
    {
        return [
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
            'result_reference' => null,
        ];
    }
}
