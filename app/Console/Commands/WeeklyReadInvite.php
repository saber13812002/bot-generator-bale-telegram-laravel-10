<?php

namespace App\Console\Commands;

use App\Interfaces\Services\ChannelPosterBotService;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\WeeklyReadInvitation;
use App\Services\BotHealthRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F5 — دعوت هفتگی به مطالعه یک مطلب آرشیوی (سبک شراب بهشتی)
 *
 * هر روز (ساعت ۱۰:۰۰ UTC) برای هر کانفیگ فعالی که روز آن امروز باشد
 * و هفته‌ی جاری هنوز ارسال نشده باشد:
 *   - شماره‌ی تصادفی ۱..max_post_id
 *   - متن دعوت + لینک کانال (در صورت موجود بودن) + یک جمله‌ی کوتاه انگیزشی
 *   - ارسال از طریق پلتفرم مقصد (bale/telegram/eitaa)
 *   - به‌روزرسانی last_invited_at
 */
class WeeklyReadInvite extends Command
{
    protected $signature = 'weekly-read:invite
        {--dry-run : فقط گزارش بده؛ ارسال انجام نده}
        {--force : محدودیت «یک‌بار در هفته» را نادیده بگیر}
        {--invitation= : فقط کانفیگ با این شناسه بررسی شود}';

    protected $description = 'ارسال دعوت هفتگی به مطالعه یک مطلب آرشیوی تصادفی از کانال';

    private const MOTIVATION_LINES = [
        'هر مطلب خوانده‌شده، یک قدم به جلو است. 🌱',
        'پنج دقیقه مطالعه، زندگی‌ات را کمی بهتر می‌کند.',
        'از آرشیو گمشده‌هایت، دانه‌های طلایی پیدا می‌شود.',
        'امروز وقت بازگشت به یک مطلب قدیمی و زیباست.',
        'مطالعه‌ی روزانه، سرمایه‌ی ذهنی توست. ✨',
        'یک مطلب، یک لبخند، یک روز بهتر.',
    ];

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
        $onlyId = $this->option('invitation');

        $query = WeeklyReadInvitation::query()->where('enabled', true);
        if ($onlyId !== null && $onlyId !== '') {
            $query->where('id', (int) $onlyId);
        }

        $invitations = $query->with('destination.bot')->get();

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($invitations as $invitation) {
            $due = $force ? true : $invitation->isDueAt($now);
            if (! $due) {
                $skipped++;
                $this->line("• Invitation #{$invitation->id}: skipped (not due today or already sent this week)");
                continue;
            }

            $result = $this->sendInvitation($invitation, $now, $dryRun);
            match ($result) {
                'sent' => $sent++,
                'failed' => $failed++,
                default => $skipped++,
            };
        }

        $this->info(sprintf(
            'weekly-read:invite finished: sent=%d skipped=%d failed=%d%s',
            $sent,
            $skipped,
            $failed,
            $dryRun ? ' (dry-run)' : ''
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return string sent|skipped|failed
     */
    private function sendInvitation(WeeklyReadInvitation $invitation, Carbon $now, bool $dryRun): string
    {
        $destination = $invitation->destination;
        if (! $destination) {
            Log::warning('[WeeklyReadInvite] Destination missing', ['invitation_id' => $invitation->id]);
            $this->warn("• Invitation #{$invitation->id}: destination missing");

            return 'skipped';
        }

        if (! $destination->is_active) {
            $this->line("• Invitation #{$invitation->id}: destination inactive");

            return 'skipped';
        }

        $maxId = max(1, (int) $invitation->max_post_id);
        $postId = random_int(1, $maxId);

        $title = $destination->channel_title ?: 'کانال';
        $text = sprintf('📖 دعوت به مطالعه‌ی مطلب شماره %d از کانال «%s»', $postId, $title);

        if (! empty($destination->channel_link)) {
            $text .= "\n" . $destination->channel_link;
        }

        $text .= "\n" . $this->pickMotivationLine();

        if ($dryRun) {
            $this->line(sprintf(
                "• [dry-run] Invitation #%d -> %s (%s) post #%d",
                $invitation->id,
                $destination->channel_chat_id,
                $destination->platform,
                $postId
            ));
            $this->line('  ' . str_replace("\n", "\n  ", $text));

            return 'sent';
        }

        try {
            $token = $this->resolveToken($invitation, $destination);
            if (! $token) {
                Log::warning('[WeeklyReadInvite] No token available', [
                    'invitation_id' => $invitation->id,
                    'destination_id' => $destination->id,
                ]);
                $this->warn("• Invitation #{$invitation->id}: no bot token");

                return 'failed';
            }

            $origin = $destination->platform === 'telegram' ? 'telegram' : 'bale';
            $publisher = $this->publisherFactory->make($token, $origin);

            $result = $publisher->publish(
                $destination->channel_chat_id,
                'text',
                $text,
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
                $ok ? null : 'weekly-read-invite failed',
                null
            );

            BotHealthRecorder::record([
                'feature_key' => 'weekly_read_invite',
                'platform'    => (string) $destination->platform,
                'event_type'  => 'channel_post',
                'status'      => $ok ? 'ok' : 'fail',
                'meta'        => [
                    'invitation_id' => $invitation->id,
                    'destination_id' => $destination->id,
                    'post_id'       => $postId,
                    'message_id'    => $messageId,
                ],
            ]);

            if ($ok) {
                $invitation->update(['last_invited_at' => $now]);
                $this->info("• Invitation #{$invitation->id}: sent post #{$postId} to {$destination->platform} (msg {$messageId})");

                return 'sent';
            }

            Log::warning('[WeeklyReadInvite] Publish failed', [
                'invitation_id' => $invitation->id,
                'destination_id' => $destination->id,
            ]);
            $this->warn("• Invitation #{$invitation->id}: publish failed to {$destination->platform}");

            return 'failed';
        } catch (Throwable $e) {
            Log::error('[WeeklyReadInvite] Error', [
                'invitation_id' => $invitation->id,
                'error'         => $e->getMessage(),
            ]);
            $this->error("• Invitation #{$invitation->id}: " . $e->getMessage());

            return 'failed';
        }
    }

    /**
     * توکن پایه برای ساخت publisher: توکن خودِ مقصد، وگرنه توکن ربات بر اساس پلتفرم.
     */
    private function resolveToken(WeeklyReadInvitation $invitation, \App\Models\ChannelPosterDestination $destination): ?string
    {
        if ($destination->bot_token) {
            return $destination->bot_token;
        }

        $bot = $destination->bot;
        if (! $bot) {
            return null;
        }

        return $destination->platform === 'telegram' ? $bot->telegram_bot_token : $bot->bale_bot_token;
    }

    private function pickMotivationLine(): string
    {
        $lines = self::MOTIVATION_LINES;

        return $lines[array_rand($lines)];
    }
}
