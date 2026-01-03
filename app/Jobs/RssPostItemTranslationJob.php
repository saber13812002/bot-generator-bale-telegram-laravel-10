<?php

namespace App\Jobs;

use App\Models\RssChannel;
use App\Models\RssPostItemTranslation;
use App\Models\RssPostItemTranslationQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RssPostItemTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected RssPostItemTranslation $rssPostItemTranslation;

    public function __construct(RssPostItemTranslation $rssPostItemTranslation)
    {
        $this->rssPostItemTranslation = $rssPostItemTranslation;
    }

    public function handle()
    {

        $rssItem = $this->rssPostItemTranslation->post->rssItem;

//        dd($rssItem);
        if (!$rssItem) {
            Log::info('$rssItem is empty rss post item translation job');
            return;
        }

        $rssTags = $rssItem->tags()->pluck('id')->toArray();

        if (empty($rssTags)) {
            Log::info('$rssTags is empty rss post item translation job');
            return;
        }

        $rssChannels = RssChannel::whereHas('tags', function ($query) use ($rssTags) {
            $query->whereIn('tags.id', $rssTags);
        })->get();

        $newItemCreated = false;

        foreach ($rssChannels as $rssChannel) {
            $newItem = RssPostItemTranslationQueue::firstOrCreate([
                'rss_post_item_translation_id' => $this->rssPostItemTranslation->id,
                'rss_channel_id' => $rssChannel->id,
            ]);
            $newItemCreated = true;
        }

        if (!$newItemCreated) {
            Log::warning('no new item created in rss post item translation because cant find any channels with tags');
        } else {
            Log::info('new item created as well. as rss post item translation');
        }
    }


}
