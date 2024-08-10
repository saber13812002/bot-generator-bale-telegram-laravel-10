<?php

namespace App\Console\Commands;

use App\Models\RssFeedWebOrigin;
use App\Services\AudioBookService;
use Illuminate\Console\Command;

class GenerateAudioBookId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-audio-book-id';

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

        $maxAttempts = 3;
        $attempts = 0;
        $audioBookId = null;

        while ($attempts < $maxAttempts) {
            $audioBookId = rand(100, 15000);
            if (!RssFeedWebOrigin::where('media_id', $audioBookId)->exists()) {
                break; // Found a valid ID
            }
            $attempts++;
        }

        if ($attempts === $maxAttempts) {
            // Handle the case where no valid ID was found after 3 attempts
            \Log::error("Failed to generate a valid audio book ID after {$maxAttempts} attempts.");
            return;
        }

        // Call the API with the valid audioBookId

        $response = AudioBookService::callDetailApi($audioBookId);

        // Check if the response is successful
        if ($response->successful()) {
            $responseData = AudioBookService::saveAndGetResponseData($response, $audioBookId);

        }
    }

}
