<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Models\RssChannel;
use Illuminate\Console\Command;

class testSendchannels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-sendchannels';

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
        $rssChannel = RssChannel::find(2);
        BotHelper::sendMessageEitaaSupport("test", $rssChannel->token, $rssChannel->target_id, $rssChannel->RssChannelOrigin->slug);
    }
}
