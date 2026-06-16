<?php

namespace App\Console\Commands;

use App\Builders\BotBuilder;
use App\Models\RssChannel;
use App\Services\SharabeBeheshtiRssMessageBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram;

class PreviewSharabeBeheshtiRssJob extends Command
{
    protected $signature = 'app:preview-sharabe-beheshti-rss-job
                            {--id=1 : Sharabe Beheshti MP3 id}
                            {--medium=eitaa : Platform slug (eitaa, bale, telegram, messenger)}
                            {--rss-channel=2 : rss_channels.id for bot token and default chat_id}
                            {--chat-id= : Override Eitaa chat_id (channel/group)}
                            {--send : ارسال واقعی صوت + caption مثل جاب}
                            {--dry-run : فقط نمایش (پیش‌فرض وقتی --send نیست)}';

    protected $description = 'پیش‌نمایش/تست خروجی RssPostItemTranslationToMessengerJob برای شراب بهشتی';

    public function __construct(
        private readonly SharabeBeheshtiRssMessageBuilder $builder
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $mp3Id = (int) $this->option('id');
        $medium = (string) $this->option('medium');
        $send = (bool) $this->option('send');

        $payload = $this->builder->buildFromMp3Id($mp3Id, $medium, $medium);
        if (!$payload) {
            $this->error("MP3 id={$mp3Id} یافت نشد یا لینک ساخته نشد.");
            $this->line('ابتدا: php artisan db:seed --class=SharabeBeheshtiMp3sTableSeeder');

            return self::FAILURE;
        }

        $this->info("=== Sharabe Beheshti RSS Job Preview (mp3 id={$payload['mp3_id']}) ===");
        $this->newLine();
        $this->line('<comment>post_link:</comment> ' . $payload['post_link']);
        $this->line('<comment>share_url:</comment> ' . ($payload['share_url'] ?? '(none)'));
        $this->line('<comment>mp3_url:</comment> ' . $payload['mp3_url']);
        $this->line('<comment>audio_title:</comment> ' . str_replace("\n", ' / ', $payload['audio_title']));
        $this->newLine();
        $this->line('--- caption (audio) ---');
        $this->line($payload['caption']);
        $this->newLine();

        Log::info('SharabeBeheshti message built (preview command)', $payload);

        if (!$send) {
            $this->comment('Dry run — برای ارسال واقعی: --send');
            $this->line('قبل از --send: php artisan app:ensure-eitaa-rss-channel');

            return self::SUCCESS;
        }

        $rssChannelId = (int) $this->option('rss-channel');
        $rssChannel = RssChannel::with('RssChannelOrigin')->find($rssChannelId);
        if (!$rssChannel || empty($rssChannel->token)) {
            $this->error("rss_channels id={$rssChannelId} یافت نشد یا token خالی است.");
            $this->line('اجرا کنید: php artisan app:ensure-eitaa-rss-channel');

            return self::FAILURE;
        }

        $chatId = $this->option('chat-id') ?: $rssChannel->target_id;
        $originSlug = $rssChannel->RssChannelOrigin?->slug ?? $medium;

        $this->info("Sending to chat_id={$chatId} via rss_channel id={$rssChannelId} ({$originSlug})");

        $botBuilder = new BotBuilder(new Telegram($rssChannel->token, $originSlug));
        if ($payload['parse_mode']) {
            $botBuilder->setParseMode($payload['parse_mode']);
        }

        $data = $botBuilder
            ->setChatId((string) $chatId)
            ->setCaption($payload['caption'])
            ->setTitle($payload['audio_title'])
            ->setAudioUrl($payload['mp3_url'])
            ->sendAudio();

        $this->info('API response: ' . (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE)));
        Log::info('SharabeBeheshti audio send response (preview command)', ['data' => $data]);

        return self::SUCCESS;
    }
}
