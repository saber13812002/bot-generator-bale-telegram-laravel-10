<?php

namespace App\Console\Commands;

use App\Jobs\FetchLinkedInUpdates;
use Illuminate\Console\Command;

class FetchLinkedin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-linkedin';

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
        FetchLinkedInUpdates::dispatch();
    }
}
