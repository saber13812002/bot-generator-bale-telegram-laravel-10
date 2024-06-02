<?php

namespace App\Console\Commands;

use App\Services\RssItemService;
use Illuminate\Console\Command;

class rss_test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rss_test';

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
    }
}
