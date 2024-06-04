<?php

namespace App\Console\Commands;

use App\Services\RssItemService;
use App\Services\RssPostItemTranslationToMessengerService;
use Illuminate\Console\Command;

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
        RssItemService::run();
        RssPostItemTranslationToMessengerService::run();

    }
}
