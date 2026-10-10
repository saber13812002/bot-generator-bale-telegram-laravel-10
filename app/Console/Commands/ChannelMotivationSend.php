<?php

namespace App\Console\Commands;

use App\Interfaces\Services\ChannelPosterBotService;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\AiProvider;
use App\Models\ChannelMotivationalSchedule;
use App\Services\AiProviderService;
use App\Services\BotHealthRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F6 — متن انگیزشی هفتگی (LLM) برای هر کانال با روز اختصاصی
 *
 * هر روز (ساعت ۱۱:۰۰ UTC) برای هر اسکدیول فعالی که امروز نوبتش باشد:
 *   - LLM یک جمله‌ی کوتاه انگیزشی فارسی (≤ ۲۰۰ نویسه) می‌نویسد
 *     (با آگاهی از آخرین متن‌های ارسال‌شده تا تکرار نشود)
 *   - ارسال از طریق پلتفرم مقصد (bale/telegram/eitaa)
 *   - ذخیره متن در sent_texts + به‌روزرسانی last_sent_at
 *
 * اگر LLM خراب باشد: ارسال نمی‌شود (فقط لاگ + شمارش skip) تا از
 * پست‌های کلیشه‌ای تکراری پرهیز شود.
 */
class ChannelMotivationSend extends Command
{
    protected $signature = 'channel-motivation:send
        {--dry-run : فقط گزارش بده؛ ارسال و LLM انجام نده}
        {--force : محدودیت روز/فراوانی را نادیده بگیر}
        {--schedule= : فقط اسکدیول با این شناسه بررسی شود}';

    protected $description = 'ارسال متن انگیزشی LLM به کانال‌های اسکدیول‌شده (روز اختصاصی + فراوانی)';

    public function __construct(
        private ChannelPosterPublisherFactory $publisherFactory,
        private ChannelPosterBotService $channelService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $onlyId = $this->option('schedule');

        $query = ChannelMotivationalSchedule::query()->where('enabled', true);
        if ($onlyId !== null && $onlyId !== '') {
            $query->where('id', (int) $onlyId);
        }

        $schedules = $query->with('destination.bot')->get();

        $sent = 0;
        $skipped = 0;
        $llmFailed = 0;
        $failed = 0;

        foreach ($schedules as $schedule) {
            $due = $force ? true : $schedule->isDueAt($now);
            if (! $due) {
                $skipped++;
                $this->line("• Schedule #{$schedule->id}: skipped (not due)");
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    "• [dry-run] Schedule #%d (%s, %s): would ask LLM and post",
                    $schedule->id,
                    $schedule->frequency,
                    ChannelMotivationalSchedule::dayNameFa($schedule->day_of_week)
                ));
                $sent++;
                continue;
            }

            $text = $this->generateText($schedule);
            if ($text === null) {
                $llmFailed++;
                continue;
            }

            $result = $this->publishText($schedule, $text, $now);
            match ($result) {
                'sent' => $sent++,
                'failed' => $failed++,
                default => $skipped++,
            };
        }

        $this->info(sprintf(
            'channel-motivation:send finished: sent=%d skipped=%d llm_failed=%d failed=%d%s',
            $sent,
            $skipped,
            $llmFailed,
            $failed,
            $dryRun ? ' (dry-run)' : ''
        ));

        if ($llmFailed > 0) {
            // هشدار برای مدیران تا از خرابی LLM اطلاع داشته باشند (بدون پست boilerplate)
            try {
                AiProviderService::notifyAllAdmins(
                    "⚠️ Channel Motivation LLM\n{$llmFailed} اسکدیول به دلیل در دسترس نبودن LLM ارسال نشد.\nTime: " . now()->toDateTimeString()
                );
            } catch (Throwable $e) {
                Log::warning('[ChannelMotivation] Failed to notify admins about LLM failure', ['error' => $e->getMessage()]);
            }
        }

        return $failed > 0 || $llmFailed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * تولید یک جمله‌ی انگیزشی از LLM. در صورت هر خطا: null.
     */
    private function generateText(ChannelMotivationalSchedule $schedule): ?string
    {
        try {
            $provider = AiProvider::active()->orderBy('id')->first()
                ?? AiProviderService::ensureDefaultProvider();

            $prompt = $schedule->getEffectivePrompt();

            $recent = $schedule->recentSentTexts(10);
            if ($recent !== []) {
                $prompt .= "\n\nمتن‌های قبلی (تکراری یا هم‌شکل با این‌ها ننویس):\n"
                    . implode("\n", array_map(
                        static fn (string $t): string => '- ' . $t,
                        $recent
                    ));
            }

            $result = (new AiProviderService($provider))->chat($prompt, 150, 0.8);

            if (! ($result['success'] ?? false)) {
                Log::warning('[ChannelMotivation] LLM chat failed', [
                    'schedule_id' => $schedule->id,
                    'error' => $result['error'] ?? 'unknown',
                ]);

                return null;
            }

            $text = trim((string) ($result['response'] ?? ''));
            // پاک‌سازی: خط اول را بگیر، پیشوندها/نقل‌قول‌ها را حذف کن
            $text = trim(preg_split('/\R+/u', $text)[0] ?? '');
            $text = trim($text, "\"'“”‘’ \t\n");
            $text = preg_replace('/^(جمله:|پاسخ:|متن:)\s*/u', '', $text);

            if ($text === '' || mb_strlen($text) > ChannelMotivationalSchedule::LLM_TEXT_LIMIT * 2) {
                Log::warning('[ChannelMotivation] LLM text invalid/empty', [
                    'schedule_id' => $schedule->id,
                    'length' => mb_strlen($text),
                ]);

                return null;
            }

            return mb_substr($text, 0, ChannelMotivationalSchedule::LLM_TEXT_LIMIT);
        } catch (Throwable $e) {
            Log::error('[ChannelMotivation] LLM error', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return string sent|skipped|failed
     */
    private function publishText(ChannelMotivationalSchedule $schedule, string $text, Carbon $now): string
    {
        $destination = $schedule->destination;
        if (! $destination || ! $destination->is_active) {
            $this->warn("• Schedule #{$schedule->id}: destination missing/inactive");

            return 'skipped';
        }

        try {
            $token = $destination->bot_token
                ?? ($destination->bot !== null
                    ? ($destination->platform === 'telegram' ? $destination->bot->telegram_bot_token : $destination->bot->bale_bot_token)
                    : null);

            if (! $token) {
                Log::warning('[ChannelMotivation] No token available', [
                    'schedule_id' => $schedule->id,
                    'destination_id' => $destination->id,
                ]);
                $this->warn("• Schedule #{$schedule->id}: no bot token");

                return 'failed';
            }

            $origin = $destination->platform === 'telegram' ? 'telegram' : 'bale';
            $publisher = $this->publisherFactory->make($token, $origin);

            $result = $publisher->publish(
                $destination->channel_chat_id,
                'text',
                '✨ ' . $text,
                null,
                $destination->platform,
                $destination->bot_token
            );

            $ok = $result['success'] ?? false;
            $messageId = $result['message_id'] ?? null;

            $this->channelService->logPublish(
                (int) $destination->bot_id,
                (int) $destination->id,
                (string) $destination->platform,
                $ok,
                $messageId,
                $ok ? null : 'channel-motivation failed',
                null
            );

            BotHealthRecorder::record([
                'feature_key' => 'channel_motivation',
                'platform'    => (string) $destination->platform,
                'event_type'  => 'channel_post',
                'status'      => $ok ? 'ok' : 'fail',
                'meta'        => [
                    'schedule_id' => $schedule->id,
                    'destination_id' => $destination->id,
                    'message_id'  => $messageId,
                ],
            ]);

            if ($ok) {
                $schedule->rememberSentText($text);
                $schedule->forceFill(['last_sent_at' => $now])->save();
                $this->info("• Schedule #{$schedule->id}: posted to {$destination->platform} (msg {$messageId})");

                return 'sent';
            }

            Log::warning('[ChannelMotivation] Publish failed', [
                'schedule_id' => $schedule->id,
                'destination_id' => $destination->id,
            ]);
            $this->warn("• Schedule #{$schedule->id}: publish failed to {$destination->platform}");

            return 'failed';
        } catch (Throwable $e) {
            Log::error('[ChannelMotivation] Error', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);
            $this->error("• Schedule #{$schedule->id}: " . $e->getMessage());

            return 'failed';
        }
    }
}
