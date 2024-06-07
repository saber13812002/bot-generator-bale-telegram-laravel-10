<?php

namespace App\Console\Commands;

use App\Services\RocketChatService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class RssToBot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rss-to-bot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     * @throws GuzzleException
     */
    public function handle()
    {
        $rocket = new RocketChatService("test", "test");
        $rocket->sendMessage();
    }
}
