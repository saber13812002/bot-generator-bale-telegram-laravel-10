<?php

namespace App\Console\Commands;

use App\Models\RssPostItem;
use App\Services\RssPostItemToMessengerService;
use Illuminate\Console\Command;

class RssPostToTranslate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rss-post-to-translate';

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
//        foreach ($rssPostItems as $rssPostItem) {
        $rssPostItem = RssPostItem::whereDoesntHave('translations')->first();
//        dd($rssPostItem);
        if ($rssPostItem)
            RssPostItemToMessengerService::call($rssPostItem);
    }
}
