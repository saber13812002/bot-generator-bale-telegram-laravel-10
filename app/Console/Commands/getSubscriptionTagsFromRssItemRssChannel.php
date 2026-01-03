<?php

namespace App\Console\Commands;

use App\Models\RssChannel;
use App\Models\RssItem;
use Illuminate\Console\Command;

class getSubscriptionTagsFromRssItemRssChannel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-subscription-tags-from-rss-item-rss-channel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rssItem5 = RssItem::find(5);

        $rssTags = $rssItem5->tags()->pluck('id')->toArray();

        $tags = RssChannel::whereHas('tags', function ($query) use ($rssTags) { $query->whereIn('tags.id', $rssTags); })->get();

        dd($tags);
    }
}
