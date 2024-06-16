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

    public function handle()
    {
//        $rssChannel = RssChannel::query()->first();
        $rssChannel = RssChannel::find($this->rssPostItemTranslationQueue->rss_channel_id);

        if (!$rssChannel) {
            Log::error("RSS Channel not found for ID: " . $this->rssPostItemTranslationQueue->rss_channel_id);
            return;
        }

//        try {
//            dd($this->rssPostItemTranslationQueue->postTranslation);
        $message = RssHelper::createMessage($this->rssPostItemTranslationQueue->postTranslation, true);
//        dd($rssChannel->RssChannelOrigin);
        if (!$rssChannel->RssChannelOrigin) {
            Log::error("RSS Channel Origin not found for ID: " . $rssChannel->id);
            return;
        }

        if (!$rssChannel->RssChannelOrigin->slug) {
            Log::error("RSS Channel Origin slug not found for ID: " . $rssChannel->id);
            return;
        }

        if ($rssChannel->RssChannelOrigin->slug == 'telegram') {
            $bot = new \Telegram($rssChannel->token, 'telegram');
        } else if ($rssChannel->RssChannelOrigin->slug == 'bale') {
            $bot = new \Telegram($rssChannel->token, 'bale');
        }

        $response = BotHelper::sendMessageByChatId($bot, $rssChannel->target_id, $message);

        if ($response['ok'] !== true) {
            Log::error("Failed to send message to RSS Channel: " . $rssChannel->id, $response);
        }

//        } catch (\Exception $e) {
//            Log::error("Error sending message: " . $e->getMessage());
//        }
    }

}
