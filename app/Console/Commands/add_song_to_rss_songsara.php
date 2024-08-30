<?php

namespace App\Console\Commands;

use App\Models\RssFeedWebOrigin;
use App\Models\SongsaraPost;
use App\Services\SongSaraService;
use Illuminate\Console\Command;

class add_song_to_rss_songsara extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add_song_to_rss_songsara';

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
        $song = SongsaraPost::wherePublished(false)
            ->orderByDesc("id")
            ->first();

        $rssItem = RssFeedWebOrigin::create([
            "origin" => "songsara.net",
            "title" => $song->title,
            "image" => $song->image_link,
            "link" => $song->url,
            "media_id" => $song->media_id,
        ]);

        $data = SongSaraService::getDescriptionMediaUrlByMediaId($song->media_id);
        $rssItem->description = $data['description'];
        $rssItem->media_url = $data['media_url'];
        $rssItem->save();

        $song->published = true;
        $song->description = $data['description'];
        $song->audio_link = $data['media_url'];
        $song->save();
    }
}
