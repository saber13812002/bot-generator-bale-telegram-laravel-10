<?php

namespace App\Console\Commands;

use App\Models\RssPostItem;
use Illuminate\Console\Command;

class TestCreateRssPostItem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-create-rss-post-item';

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
        RssPostItem::query()->insert([
            'rss_item_id' => 29,
            'title' => "sdhaflhkasdkfhads ",
            'link' => "httpsdf:/sadfadsfasdf",
//                        'description' => $description,
//            'pub_date' => ,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
