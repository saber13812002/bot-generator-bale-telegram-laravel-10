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
    protected $signature = 'app:rss_read_translate {--switch : Description of the switch}';

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
        // Set default value for the switch
        $switch = $this->option('switch') ? true : false;

        // Use the switch in your logic
        if ($switch) {
            $this->info('Switch is on.');
            // Perform actions when the switch is on
        } else {
            $this->info('Switch is off.');
            // Perform actions when the switch is off
        }

        // Your existing logic
        RssItemService::run($switch);
        $this->info("done");

        RssPostItemTranslationToMessengerService::run();
        $this->info("done");
    }
}
