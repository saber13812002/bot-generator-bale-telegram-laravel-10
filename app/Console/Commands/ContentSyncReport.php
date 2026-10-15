<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Models\ContentSyncContent;
use App\Models\ContentSyncVersion;
use Illuminate\Console\Command;
use Throwable;

/**
 * خروجی CSV بانک همگام‌سازی چندکاناله (Content Sync)
 *
 * هر سطر = یک نسخه از یک محتوا در یک کانال/پیام‌رسان (با post_url و وضعیت لینک).
 * برای مهاجرت ~۵۰۰۰ مطلب قدیمی، این خروجی را باز می‌کنید و لینک‌ها را چک می‌کنید.
 *
 * نمونه:
 *   php artisan content-sync:report                          # همه ربات‌ها
 *   php artisan content-sync:report --bot=5                  # فقط ربات ۵
 *   php artisan content-sync:report --id=148                 # فقط مطلب ۱۴۸
 *   php artisan content-sync:report --status=failed          # فقط موارد ناموفق
 *   php artisan content-sync:report --output=sync.csv        # مسیر خروجی
 */
class ContentSyncReport extends Command
{
    protected $signature = 'content-sync:report
        {--bot= : فقط محتوای ربات با این id}
        {--id= : فقط محتوای با این content_id}
        {--status= : فیلتر publish_status (pending|queued|published|failed|skipped)}
        {--output= : مسیر فایل خروجی (پیش‌فرض storage/app/content-sync-report-YYYYMMDD.csv)}';

    protected $description = 'Export the multi-channel content sync archive as CSV (versions with post_url + link_status)';

    public function handle(): int
    {
        $contentsQuery = ContentSyncContent::query()->orderBy('id');

        $botId = $this->option('bot');
        if ($botId) {
            $bot = Bot::find((int) $botId);
            if (!$bot) {
                $this->error("Bot #{$botId} not found.");

                return self::FAILURE;
            }
            $contentsQuery->where('bot_id', $bot->id);
        }

        $contentId = $this->option('id');
        if ($contentId) {
            $contentsQuery->where('id', (int) $contentId);
        }

        $contents = $contentsQuery->with('versions')->get();

        if ($contents->isEmpty()) {
            $this->info('No content found matching the filters.');

            return self::SUCCESS;
        }

        $status = $this->option('status');

        $output = $this->option('output')
            ?: storage_path('app/content-sync-report-' . now()->format('Ymd') . '.csv');

        $fh = fopen($output, 'w');
        if ($fh === false) {
            $this->error("Cannot open output file: {$output}");

            return self::FAILURE;
        }

        // BOM برای درستی نمایش کاراکترهای فارسی در Excel
        fwrite($fh, "\xEF\xBB\xBF");

        fputcsv($fh, [
            'content_id',
            'source_platform',
            'source_channel',
            'source_message',
            'source_url',
            'tag',
            'created_by',
            'created_at',
            'text_excerpt',
            'platform',
            'channel_id',
            'channel_username',
            'message_id',
            'post_url',
            'link_status',
            'publish_status',
            'published_at',
            'queue_id',
            'chunks_count',
            'last_error',
        ]);

        $rows = 0;
        foreach ($contents as $content) {
            $versions = $content->versions;
            if ($status) {
                $versions = $versions->where('publish_status', $status)->values();
            }

            $textExcerpt = mb_substr(trim((string) $content->text), 0, 100);

            foreach ($versions as $version) {
                fputcsv($fh, [
                    $content->id,
                    $content->source_platform,
                    $content->source_channel_id,
                    $content->source_message_id,
                    $content->source_url,
                    $content->tag ?? '',
                    $content->created_by_chat_id ?? '',
                    $content->created_at?->format('Y-m-d H:i:s') ?? '',
                    $textExcerpt,
                    $version->platform,
                    $version->channel_id,
                    $version->channel_username ?? '',
                    $version->message_id ?? '',
                    $version->post_url ?? '',
                    $version->link_status,
                    $version->publish_status,
                    $version->published_at?->format('Y-m-d H:i:s') ?? '',
                    $version->queue_id ?? '',
                    $version->chunks_count,
                    $version->last_error ?? '',
                ]);
                $rows++;
            }
        }

        fclose($fh);

        $this->info("Exported {$rows} version rows for {$contents->count()} contents to: {$output}");

        return self::SUCCESS;
    }
}
