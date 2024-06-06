<?php

namespace App\Jobs;

use App\Helpers\BotHelper;
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
            $message = $this->createMessage();

            $response = BotHelper::sendMessageByChatId($bot, $rssChannel->target_id, $message);

//            dd($response);
        }
    }

    /**
     * @return string
     */
    private function createMessage(): string
    {

        $message = "
: #" . $this->rssPostItemTranslation->post->rssItem->title . "

: " . $this->rssPostItemTranslation->title . "

: " . $this->rssPostItemTranslation->content;

//        dd($this->rssPostItemTranslation->post);
        if ($this->rssPostItemTranslation->post) {
            $message = ": " . $this->rssPostItemTranslation->post->title . "

: " . $this->rssPostItemTranslation->title . "

: " . $this->rssPostItemTranslation->post->description . "

: " . $this->rssPostItemTranslation->content . "". //"

//: " . $this->rssPostItemTranslation->post->rssItem->url . "
//
": " . $this->stringifyTags($this->rssPostItemTranslation->post->rssItem->tags) . "
👇👇👇
: " . $this->rssPostItemTranslation->post->link;
        }


        return $message;
    }


    public function stringifyTags($tags): string
    {
        $stringTags = "";
        foreach ($tags as $tag) {
            // Access the "fa" attribute of the tag
            $faValue = $tag->name;

            // Do something with the $faValue
            // For example, you can echo it or store it in an array
            $stringTags .= "#" . $faValue . " ";
        }
        return $stringTags;
    }

}
