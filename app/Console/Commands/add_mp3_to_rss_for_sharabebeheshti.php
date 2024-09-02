<?php

namespace App\Console\Commands;

use App\Models\RssFeedWebOrigin;
use App\Models\SharabeBeheshtiMp3;
use App\Models\SongsaraPost;
use App\Services\SongSaraService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class add_mp3_to_rss_for_sharabebeheshti extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add_mp3_to_rss_for_sharabebeheshti';

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
        $rand = rand(1, 88);
        $randNumber = rand(1000, 9999);

        $mp3Item = SharabeBeheshtiMp3::find($rand);


        if (!$mp3Item) {
            Log::info('No unpublished songs found.');
            return;
        }

        // Create the RSS feed item
        $rssItem = RssFeedWebOrigin::create([
            "origin" => $mp3Item->origin ?? null,
            "title" => $mp3Item->title ?? 'Untitled',
            "image" => "https://bots.pardisania.ir/sharabebeheshti.jpg",
            "link" => "https://" . $mp3Item->origin . "/shb" . $mp3Item->part . "?random_id=" . $randNumber . "&id=" . $rand . "&utm_source=saber&utm_medium=messenger&utm_campaign=campaign_khoda&utm_term=term_zohoor&utm_content=emamzaman",
//            "media_link" => $mp3Item->link,
            "media_id" => "7777777777" . $rand,

        ]);

    }
}
