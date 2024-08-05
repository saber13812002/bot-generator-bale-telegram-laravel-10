<?php

namespace App\Console\Commands;

use App\Builders\BotBuilder;
use App\Models\RssChannel;
use App\Models\RssPostItem;
use Illuminate\Console\Command;

class TestSendPhotoMessageToEitaa extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-send-photo-message-to-eitaa';

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

        // get eitaa channel token
        $rssChannel = RssChannel::find(2);
        // create a bot with token

        $rssChannelOrigin = $rssChannel->RssChannelOrigin;

        // create or get post with image

        $rssPostItem = RssPostItem::query()->where('image_url', '!=', '')->first();
//        dd($rssPostItem);
        $postImageUrl = $rssPostItem->image_url;
        //send photo test message to eitaa

        $botBuilder = new BotBuilder(new \Telegram($rssChannel->token, $rssChannelOrigin->slug));

        $data = $botBuilder
            ->setChatId($rssChannel->target_id)
            ->setCaption('image')
            ->setTitle('image')
            ->setImageUrl($postImageUrl)
            ->sendPhoto();
    }
}
