<?php

namespace App\Jobs;

use App\Helpers\BotHelper;
use App\Helpers\RssHelper;
use App\Models\RssChannel;
use App\Models\RssPostItemTranslationQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RssPostItemTranslationToMessengerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected RssPostItemTranslationQueue $rssPostItemTranslationQueue;

    public function __construct(RssPostItemTranslationQueue $rssPostItemTranslationQueue)
    {
        $this->rssPostItemTranslationQueue = $rssPostItemTranslationQueue;
    }

    public function handle(): void
    {
        $rssChannel = RssChannel::find($this->rssPostItemTranslationQueue->rss_channel_id);

        if (!$rssChannel) {
            $this->logError("RSS Channel not found for ID: {$this->rssPostItemTranslationQueue->rss_channel_id}");
            return;
        }

        $rssChannelOrigin = $rssChannel->RssChannelOrigin;
        if (!$rssChannelOrigin || !$rssChannelOrigin->slug) {
            $this->logError("RSS Channel Origin or slug not found for ID: {$rssChannel->id}");
            return;
        }

        $bot = $this->getBotInstance($rssChannelOrigin->slug, $rssChannel->token);
        if (!$bot) {
            $this->logError("Unsupported RSS Channel Origin slug: {$rssChannelOrigin->slug}");
            return;
        }

        $message = RssHelper::createMessage($this->rssPostItemTranslationQueue->postTranslation, true);
        $response = BotHelper::sendMessageByChatId($bot, $rssChannel->target_id, $message);

        if ($response['ok'] !== true) {
            $this->logError("Failed to send message to RSS Channel: {$rssChannel->id}", $response);
        }
    }

    protected function logError(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    protected function getBotInstance(string $slug, string $token): ?object
    {
        switch ($slug) {
            case 'telegram':
                return new \Telegram($token, 'telegram');
            case 'bale':
                return new \Telegram($token, 'bale');
            default:
                return null;
        }
    }
}
