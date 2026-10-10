<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Models\ChannelPosterQueue;
use App\Services\ChannelPosterQueuePublishService;
use Illuminate\Console\Command;

/**
 * ارسال دستی آیتم‌های صف channel-poster
 *
 * نمونه:
 *   php artisan channel-poster:send-queued                  # همه آیتم‌های آماده (موعد رسیده)
 *   php artisan channel-poster:send-queued --all            # همه آیتم‌های pending (حتی آینده)
 *   php artisan channel-poster:send-queued --bot=3          # فقط ربات ۳ (آیتم‌های آماده)
 *   php artisan channel-poster:send-queued --id=42          # فقط آیتم ۴۲
 *   php artisan channel-poster:send-queued --id=42 --all    # حتی اگر موعدش نرسیده
 */
class ChannelPosterSendQueued extends Command
{
    protected $signature = 'channel-poster:send-queued
        {--bot= : فقط آیتم‌های ربات با این id}
        {--id= : فقط آیتم صف با این id}
        {--all : ارسال همه آیتم‌های pending (حتی موعد نرسیده) — پیش‌فرض فقط آیتم‌های آماده}';

    protected $description = 'Send queued channel-poster items manually (fix stuck queue items)';

    public function handle(ChannelPosterQueuePublishService $publishService): int
    {
        $query = ChannelPosterQueue::query()
            ->where('status', ChannelPosterQueue::STATUS_PENDING);

        $botId = $this->option('bot');
        if ($botId) {
            $bot = Bot::find((int) $botId);
            if (!$bot) {
                $this->error("Bot #{$botId} not found.");

                return self::FAILURE;
            }
            $query->where('bot_id', $bot->id);
        }

        $itemId = $this->option('id');
        if ($itemId) {
            $query->where('id', (int) $itemId);
        }

        // پیش‌فرض: فقط آیتم‌های آماده (موعدش رسیده) — مگر --all
        if (!$this->option('all')) {
            $query->where('scheduled_at', '<=', now());
        }

        $items = $query->orderBy('scheduled_at')->limit(50)->get();

        if ($items->isEmpty()) {
            $this->info('No pending queue items match the filters.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Processing %d item(s)...', $items->count()));

        $published = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $item->refresh();
            $result = $publishService->publishItem($item, $notifyOwner = true);

            $label = "#{$item->id} bot={$item->bot_id} tag=" . ($item->tag ?? '-') . ' scheduled=' . $item->scheduled_at;
            if ($result['status'] === 'published') {
                $this->info("✅ {$label} → published");
                $published++;
            } elseif ($result['status'] === 'failed') {
                $this->warn("❌ {$label} → failed: {$result['error']}");
                $failed++;
            } else {
                $this->line("⏭️  {$label} → skipped");
                $skipped++;
            }
        }

        $this->info(sprintf('Done. published=%d failed=%d skipped=%d', $published, $failed, $skipped));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
