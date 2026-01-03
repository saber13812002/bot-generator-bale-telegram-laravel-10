<?php

namespace App\Console\Commands;

use App\Models\RssFeedWebOrigin;
use App\Services\AudioBookService;
use App\Services\SongSaraService;
use Illuminate\Console\Command;

class GenerateSongSaraNetId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-songsara-net-id';

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
        $this->info("Generating songsara ID...");
        \Log::info("Starting ID generation...");

        $maxAttempts = 3;
        $attempts = 0;
        $randomId = null;

        while ($attempts < $maxAttempts) {
            $randomId = rand(133000, 163999);
            \Log::info("Generated ID: {$randomId}");
            $this->info("Generated ID: {$randomId}");

            if (!RssFeedWebOrigin::where('media_id', $randomId)
                ->whereOrigin('songsara.net')->exists()) {
                \Log::info("Valid ID found: {$randomId}");
                $this->info("Valid ID found: {$randomId}");
                break; // Found a valid ID
            }
            $attempts++;
        }

        if ($attempts === $maxAttempts) {
            \Log::error("Failed to generate a valid random ID after {$maxAttempts} attempts.");
            $this->info("Failed to generate a valid random ID after {$maxAttempts} attempts.");
            return;
        }

        // Call the API with the valid randomId
        $data = SongSaraService::callCrawlerPage($randomId);

        // Check if the response is successful
        if ($data) {
            $responseData = SongSaraService::createAndGetResponseData($data, $randomId);
            $this->info("Response successful");
        } else {
            $this->info("Response not successful");
        }
    }

}
