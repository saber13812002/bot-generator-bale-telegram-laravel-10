<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Helpers\EitaaProductLinkMessageHelper;
use App\Http\Controllers\SharabeBeheshtiMp3Controller;
use App\Models\RssChannel;
use App\Models\SharabeBeheshtiMp3;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestEitaaSharabeBeheshtiProductLink extends Command
{
    protected $signature = 'app:test-eitaa-sharabe-beheshti-product-link
                            {--id=63 : Sharabe Beheshti MP3 record id}
                            {--dry-run : Preview messages without sending to Eitaa}';

    protected $description = 'Test Eitaa product page link messages for Sharabe Beheshti (HTML with icon, HTML simple, plain URL)';

    public function handle(): int
    {
        $id = (int) $this->option('id');
        $dryRun = (bool) $this->option('dry-run');

        $item = SharabeBeheshtiMp3::find($id);
        if (!$item) {
            $this->error("SharabeBeheshtiMp3 record not found for id: {$id}");
            return self::FAILURE;
        }

        $shareUrl = SharabeBeheshtiMp3Controller::buildSharabeBeheshtiShareUrlById($id, 'eitaa');
        if (!$shareUrl) {
            $this->error("Could not build share URL for id: {$id}");
            return self::FAILURE;
        }

        $title = $item->title ?? 'شراب بهشتی';

        $variants = [
            [
                'label' => '1) HTML with xf-eitaa icon (parse_mode=html)',
                'message' => EitaaProductLinkMessageHelper::buildFullMessage($shareUrl, $title, withIcon: true),
                'parse_mode' => 'html',
            ],
            [
                'label' => '2) HTML simple link (parse_mode=html)',
                'message' => EitaaProductLinkMessageHelper::buildFullMessage($shareUrl, $title, withIcon: false),
                'parse_mode' => 'html',
            ],
            [
                'label' => '3) Plain URL (no HTML)',
                'message' => EitaaProductLinkMessageHelper::buildPlainUrlMessage($shareUrl, $title),
                'parse_mode' => null,
            ],
        ];

        $this->info("Sharabe Beheshti id: {$id}");
        $this->info("Share URL: {$shareUrl}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN — messages will not be sent.');
            $this->newLine();
        }

        $rssChannel = RssChannel::with('RssChannelOrigin')->find(2);
        if (!$rssChannel && !$dryRun) {
            $this->error('Eitaa test channel (RssChannel id=2) not found.');
            return self::FAILURE;
        }

        if ($rssChannel && $rssChannel->RssChannelOrigin?->slug !== 'eitaa') {
            $this->warn('RssChannel id=2 origin slug is not eitaa — sending anyway.');
        }

        foreach ($variants as $variant) {
            $this->line('--- ' . $variant['label'] . ' ---');
            $this->line($variant['message']);
            $this->newLine();

            if ($dryRun) {
                continue;
            }

            $response = BotHelper::sendMessageEitaaSupport(
                $variant['message'],
                $rssChannel->token,
                $rssChannel->target_id,
                'eitaa',
                $variant['parse_mode']
            );

            Log::info('TestEitaaSharabeBeheshtiProductLink sent', [
                'variant' => $variant['label'],
                'share_url' => $shareUrl,
                'parse_mode' => $variant['parse_mode'],
                'response' => $response,
            ]);

            $this->info('API response: ' . ($response ?: '(empty)'));
            $this->newLine();
        }

        if ($dryRun) {
            $this->info('Dry run complete. Run without --dry-run to send to Eitaa channel.');
        } else {
            $this->info('All 3 test messages sent to Eitaa channel. Check eitaalogpardisania visually.');
        }

        return self::SUCCESS;
    }
}
