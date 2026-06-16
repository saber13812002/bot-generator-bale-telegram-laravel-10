<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Helpers\EitaaProductLinkMessageHelper;
use App\Http\Controllers\SharabeBeheshtiMp3Controller;
use App\Models\AdminDailyChannelConfig;
use App\Models\Messenger;
use App\Models\RssChannel;
use App\Models\RssChannelOrigin;
use App\Models\SharabeBeheshtiMp3;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestEitaaSharabeBeheshtiProductLink extends Command
{
    protected $signature = 'app:test-eitaa-sharabe-beheshti-product-link
                            {--id=63 : Sharabe Beheshti MP3 record id}
                            {--rss-channel=2 : rss_channels.id for token and default target_id}
                            {--chat-id= : Override Eitaa chat_id (channel/group) — use when target_id is wrong}
                            {--variant= : Send only one variant: 1=html+icon, 2=html simple, 3=plain url (default: 2)}
                            {--all-variants : Send all 3 format variants for comparison}
                            {--list : List available Eitaa channels/targets and Sharabe Beheshti ids}
                            {--dry-run : Preview messages without sending to Eitaa}';

    protected $description = 'Test Eitaa product page link messages for Sharabe Beheshti (HTML with icon, HTML simple, plain URL)';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listTargets();
        }

        $id = (int) $this->option('id');
        $dryRun = (bool) $this->option('dry-run');

        $item = SharabeBeheshtiMp3::find($id);
        if (!$item) {
            $this->error("SharabeBeheshtiMp3 record not found for id: {$id}");
            $this->line('Run with --list to see available ids.');
            return self::FAILURE;
        }

        $shareUrl = SharabeBeheshtiMp3Controller::buildSharabeBeheshtiShareUrlById($id, 'eitaa');
        if (!$shareUrl) {
            $this->error("Could not build share URL for id: {$id}");
            return self::FAILURE;
        }

        $title = $item->title ?? 'شراب بهشتی';
        $variants = $this->buildVariants($shareUrl, $title);

        $variantFilter = $this->option('variant');
        if (!$this->option('all-variants') && ($variantFilter === null || $variantFilter === '')) {
            $variantFilter = '2';
        }
        if ($variantFilter !== null && $variantFilter !== '') {
            $key = (int) $variantFilter - 1;
            if (!isset($variants[$key])) {
                $this->error('Invalid --variant. Use 1, 2, or 3.');
                return self::FAILURE;
            }
            $variants = [$variants[$key]];
        }

        $this->info("Sharabe Beheshti id: {$id}");
        $this->info("Share URL: {$shareUrl}");
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN — messages will not be sent.');
            $this->newLine();
        }

        $rssChannelId = (int) $this->option('rss-channel');
        $rssChannel = RssChannel::with('RssChannelOrigin')->find($rssChannelId);

        if (!$rssChannel && !$dryRun) {
            $this->error("RssChannel id={$rssChannelId} not found. Use --list or --rss-channel=.");
            return self::FAILURE;
        }

        $chatId = $this->option('chat-id') ?: ($rssChannel?->target_id);
        if (!$chatId && !$dryRun) {
            $this->error('No chat_id. Set --chat-id=YOUR_CHANNEL_ID or fix rss_channels.target_id.');
            return self::FAILURE;
        }

        if ($rssChannel) {
            $this->info("RssChannel: id={$rssChannel->id}, title={$rssChannel->title}, origin={$rssChannel->RssChannelOrigin?->slug}");
            $this->info("Sending to chat_id: {$chatId}" . ($this->option('chat-id') ? ' (override)' : ''));
            $this->newLine();
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
                $chatId,
                'eitaa',
                $variant['parse_mode']
            );

            Log::info('TestEitaaSharabeBeheshtiProductLink sent', [
                'variant' => $variant['label'],
                'share_url' => $shareUrl,
                'chat_id' => $chatId,
                'parse_mode' => $variant['parse_mode'],
                'response' => $response,
            ]);

            $this->info('API response: ' . ($response ?: '(empty)'));
            $this->warnIfPrivateChat($response, $chatId);
            $this->newLine();
        }

        if ($dryRun) {
            $this->info('Dry run complete. Run without --dry-run to send to Eitaa.');
        } else {
            $this->info('Done. If you do not see messages in your channel, check the warning above — chat_id may point to a private chat.');
        }

        return self::SUCCESS;
    }

    private function listTargets(): int
    {
        $this->info('=== Sharabe Beheshti MP3 ids (sample) ===');
        SharabeBeheshtiMp3::query()
            ->orderBy('id')
            ->limit(15)
            ->get(['id', 'title', 'part', 'part_name'])
            ->each(function ($row) {
                $this->line("  id={$row->id} | part={$row->part} ({$row->part_name}) | {$row->title}");
            });
        $total = SharabeBeheshtiMp3::count();
        if ($total > 15) {
            $this->line("  ... and " . ($total - 15) . " more (ids 1–{$total})");
        }
        $this->newLine();

        $this->info('=== RSS channels (eitaa origin) ===');
        $eitaaOriginId = RssChannelOrigin::query()->where('slug', 'eitaa')->value('id');
        $rssChannels = RssChannel::with('RssChannelOrigin')
            ->when($eitaaOriginId, fn ($q) => $q->where('origin_id', $eitaaOriginId))
            ->get();

        if ($rssChannels->isEmpty()) {
            $this->warn('  No rss_channels with eitaa origin.');
        } else {
            foreach ($rssChannels as $ch) {
                $tokenPreview = $ch->token ? substr($ch->token, 0, 8) . '...' : '(empty)';
                $this->line("  rss_channels.id={$ch->id} | {$ch->title} | target_id={$ch->target_id} | type={$ch->type} | token={$tokenPreview}");
            }
        }
        $this->newLine();

        $this->info('=== admin_daily_channel_configs (eitaa) ===');
        $daily = AdminDailyChannelConfig::query()
            ->whereNotNull('eitaa_channel_chat_id')
            ->where('eitaa_channel_chat_id', '!=', '')
            ->get(['id', 'admin_chat_id', 'eitaa_channel_chat_id', 'content_type', 'is_active']);
        if ($daily->isEmpty()) {
            $this->line('  (none)');
        } else {
            foreach ($daily as $row) {
                $this->line("  id={$row->id} | eitaa_channel_chat_id={$row->eitaa_channel_chat_id} | content={$row->content_type} | active=" . ($row->is_active ? 'yes' : 'no'));
            }
        }
        $this->newLine();

        $this->info('=== messengers (eitaa) ===');
        if (DB::getSchemaBuilder()->hasTable('messengers')) {
            $messengers = Messenger::query()
                ->whereNotNull('eitaa_channel_chat_id')
                ->get(['id', 'user_id', 'eitaa_channel_chat_id', 'eitaa_channel_invite_link']);
            if ($messengers->isEmpty()) {
                $this->line('  (none)');
            } else {
                foreach ($messengers as $m) {
                    $this->line("  id={$m->id} | user_id={$m->user_id} | eitaa_channel_chat_id={$m->eitaa_channel_chat_id} | {$m->eitaa_channel_invite_link}");
                }
            }
        }
        $this->newLine();

        $this->comment('Usage examples:');
        $this->line('  php artisan app:test-eitaa-sharabe-beheshti-product-link --list');
        $this->line('  php artisan app:test-eitaa-sharabe-beheshti-product-link --id=63 --variant=3 --chat-id=YOUR_CHANNEL_ID');
        $this->line('  php artisan app:test-eitaa-sharabe-beheshti-product-link --rss-channel=2 --dry-run');

        return self::SUCCESS;
    }

    private function buildVariants(string $shareUrl, string $title): array
    {
        return [
            [
                'label' => '1) HTML with xf-eitaa icon (parse_mode=html)',
                'message' => EitaaProductLinkMessageHelper::buildFullMessage($shareUrl, $title, withIcon: true, platformSlug: 'eitaa'),
                'parse_mode' => 'html',
            ],
            [
                'label' => '2) HTML simple link (parse_mode=html)',
                'message' => EitaaProductLinkMessageHelper::buildFullMessage($shareUrl, $title, withIcon: false, platformSlug: 'eitaa'),
                'parse_mode' => 'html',
            ],
            [
                'label' => '3) Plain URL (no HTML)',
                'message' => EitaaProductLinkMessageHelper::buildPlainUrlMessage($shareUrl, $title),
                'parse_mode' => null,
            ],
        ];
    }

    private function warnIfPrivateChat(?string $response, string $chatId): void
    {
        if (!$response) {
            return;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !($decoded['ok'] ?? false)) {
            $this->error('API returned ok=false — message may not have been delivered.');
            return;
        }

        $chatType = $decoded['result']['chat']['type'] ?? null;
        $actualChatId = $decoded['result']['chat']['id'] ?? null;

        if ($chatType === 'private') {
            $this->warn("⚠ پیام به چت خصوصی (private) با id={$actualChatId} رفت — نه کانال!");
            $this->warn("  شما احتمالاً کانال eitaalogpardisania را نگاه می‌کنید ولی target_id={$chatId} چت خصوصی است.");
            $this->warn('  شناسه صحیح کانال را با --chat-id= بفرستید. برای لیست: --list');
        } elseif ($chatType === 'channel') {
            $this->info("✓ ارسال به کانال (channel) id={$actualChatId}");
        } elseif ($chatType === 'group' || $chatType === 'supergroup') {
            $this->info("✓ ارسال به گروه ({$chatType}) id={$actualChatId}");
        }
    }
}
