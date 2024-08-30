<?php

namespace App\Console\Commands;

use App\Models\RssFeedWebOrigin;
use App\Models\SongsaraPost;
use App\Services\SongSaraService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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

        if (!$song) {
            Log::info('No unpublished songs found.');
            return;
        }

        // Create the RSS feed item
        $rssItem = RssFeedWebOrigin::create([
            "origin" => "songsara.net",
            "title" => $song->title ?? 'Untitled',
            "image" => $song->image_link ?? null,
            "link" => $song->url ?? null,
            "media_id" => $song->media_id ?? null,
        ]);

        // Fetch additional data
        $data = SongSaraService::getDescriptionMediaUrlByMediaId($song->media_id);

        if (empty($data['description']) || empty($data['media_url'])) {
            Log::warning('No description or media URL found for media ID: ' . $song->media_id);
            return;
        }

        // Update the RSS item with additional data
        $rssItem->description = $data['description'];
        $rssItem->media_url = $data['media_url'];
        $rssItem->save();

        // Update the song status
        $song->published = true;
        $song->description = $data['description'];
        $song->audio_link = $data['media_url'];
        $song->save();
    }
}
