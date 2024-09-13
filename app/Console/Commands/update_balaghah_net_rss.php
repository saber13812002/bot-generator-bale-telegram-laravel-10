<?php

namespace App\Console\Commands;

use App\Models\Nahj;
use App\Models\RssFeedWebOrigin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class update_balaghah_net_rss extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update_balaghah_net_rss';

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
        $randId = rand(1, 241);
        $nahjItem = Nahj::find($randId);

        if (!$nahjItem) {
            Log::info('No unpublished songs found.');
            return;
        }

        $mediaId = "11111111111" . $randId;
        // Create or update the RSS feed item
        $rssItem = RssFeedWebOrigin::create([
                "media_id" => $mediaId,
                "origin" => "balaghah.net",  // Set the origin
                "title" => $nahjItem->title ?? 'Untitled',
                "description" => $nahjItem->persian ?? 'Untitled',
                "image" => "http://farsi.balaghah.net/sites/all/themes/nahjFarsi/image/text/markaz.png",
                "link" => "http://farsi.balaghah.net/%D9%85%D8%AD%D9%85%D8%AF-%D8%AF%D8%B4%D8%AA%DB%8C/%D8%AA%D8%B1%D8%AC%D9%85%D9%87/?id=" . $randId . "&media_id=" . $mediaId,
            ]
        );

    }
}
