<?php

namespace App\Console\Commands;


use App\Models\RssPostItem;
use App\Services\RssItemService;
use App\Services\RssPostItemTranslationToMessengerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RssReadTranslate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rss_read_translate';

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
//        $countBefore = RssPostItem::query()->count();
        RssItemService::run();
//        $countAfter = RssPostItem::query()->count();
//        $message = "done:" . $countAfter - $countBefore . " items added. final count is:" . $countAfter;
//        $this->info($message);
        $this->info("done");

//Log::info($message);
        RssPostItemTranslationToMessengerService::run();
        $this->info("done");

    }
}
