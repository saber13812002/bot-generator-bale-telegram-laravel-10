<?php

namespace App\Console\Commands;

use App\Jobs\RssPostItemTranslationToMessengerJob;
use App\Models\RssPostItem;
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
        $post = RssPostItem::first();

        RssPostItemTranslationToMessengerJob::dispatch($post->translations->first());
    }
}
