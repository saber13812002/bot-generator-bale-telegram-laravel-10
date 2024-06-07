<?php

namespace App\Jobs;

use App\Helpers\BotHelper;
use App\Helpers\RssHelper;
use App\Models\RssChannel;
use App\Models\RssPostItemTranslation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RssPostItemTranslationToMessengerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected RssPostItemTranslation $rssPostItemTranslation;

    public function __construct(RssPostItemTranslation $rssPostItemTranslation)
    {
        $this->rssPostItemTranslation = $rssPostItemTranslation;
    }

    public function handle()
    {
        // Perform some processing on the new post
        // For example, you can send a notification, update search indexes, etc.
        // ...
        // todo: implement job with rss channel
        $rssChannel = RssChannel::query()->first();
        if ($rssChannel) {
            $bot = new \Telegram($rssChannel->token, 'bale');
//            $bot->ChatID()
            $message = RssHelper::createMessage($this->rssPostItemTranslation, withCommand: true);

            $response = BotHelper::sendMessageByChatId($bot, $rssChannel->target_id, $message);

//            dd($response);
        }
    }


}
