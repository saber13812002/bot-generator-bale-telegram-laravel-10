<?php

namespace App\Console\Commands;

use App\Jobs\RssPostItemTranslationToMessengerJob;
use App\Models\RssPostItem;
use App\Models\RssPostItemTranslationQueue;
use Illuminate\Console\Command;

class TestSendPost extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-send-post';

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


        $queue = RssPostItemTranslationQueue::query()->whereRssChannelId(2)->first();
        RssPostItemTranslationToMessengerJob::dispatch($queue);
    }
}
