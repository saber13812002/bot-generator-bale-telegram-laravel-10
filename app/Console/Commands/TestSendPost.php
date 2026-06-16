<?php

namespace App\Console\Commands;

use App\Jobs\RssPostItemTranslationToMessengerJob;
use App\Models\RssPostItemTranslationQueue;
use Illuminate\Console\Command;

class TestSendPost extends Command
{
    protected $signature = 'app:test-send-post
                            {--channel=2 : rss_channels.id}
                            {--pending : فقط صف‌های ارسال‌نشده}
                            {--dry-run : فقط نمایش صف، بدون dispatch جاب}';

    protected $description = 'تست dispatch جاب RssPostItemTranslationToMessengerJob برای یک صف RSS';

    public function handle(): int
    {
        $channelId = (int) $this->option('channel');

        $query = RssPostItemTranslationQueue::query()
            ->where('rss_channel_id', $channelId)
            ->latest('id');

        if ($this->option('pending')) {
            $query->where('status', '!=', 'sent');
        }

        $queue = $query->first();

        if (!$queue) {
            $total = RssPostItemTranslationQueue::count();
            $this->error("هیچ صفی برای rss_channel_id={$channelId} یافت نشد.");
            $this->line("کل صف‌ها در سیستم: {$total}");
            $this->newLine();
            $this->comment('راه‌اندازی مسیر RSS:');
            $this->line('  1. php artisan app:ensure-eitaa-rss-channel');
            $this->line('  2. php artisan app:add_mp3_to_rss_for_sharabebeheshti');
            $this->line('  3. php artisan app:rss_read_translate');
            $this->newLine();
            $this->comment('یا پیش‌نمایش مستقیم جاب (بدون صف RSS):');
            $this->line('  php artisan app:preview-sharabe-beheshti-rss-job --id=1 --send --chat-id=YOUR_CHANNEL_ID');

            return self::FAILURE;
        }

        $this->info("Queue id={$queue->id} | channel={$queue->rss_channel_id} | status={$queue->status} | translation_id={$queue->rss_post_item_translation_id}");

        if ($this->option('dry-run')) {
            $this->comment('Dry run — برای dispatch: بدون --dry-run');

            return self::SUCCESS;
        }

        RssPostItemTranslationToMessengerJob::dispatchSync($queue);
        $this->info('Job dispatched (sync).');

        return self::SUCCESS;
    }
}
