<?php

namespace App\Console\Commands;

use App\Helpers\WebPageMediaFindSave;
use Illuminate\Console\Command;

class fetchAndSaveMp3UrlTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-and-save-mp3-url-test';

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
        try {
            WebPageMediaFindSave::fetchAndSaveMp3UrlTest();
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
