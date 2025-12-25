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
        $this->info("Generating Audio Book ID...");
        \Log::info("Starting ID generation...");

        $maxAttempts = 3;
        $attempts = 0;
        $audioBookId = null;

        while ($attempts < $maxAttempts) {
            $audioBookId = rand(100, 15000);
            \Log::info("Generated ID: {$audioBookId}");
            $this->info("Generated ID: {$audioBookId}");

            if (!RssFeedWebOrigin::where('media_id', $audioBookId)
                ->whereOrigin('navaar.ir')->exists()) {
                \Log::info("Valid ID found: {$audioBookId}");
                $this->info("Valid ID found: {$audioBookId}");
                break; // Found a valid ID
            }
            $attempts++;
        }

        if ($attempts === $maxAttempts) {
            \Log::error("Failed to generate a valid audio book ID after {$maxAttempts} attempts.");
            $this->info("Failed to generate a valid audio book ID after {$maxAttempts} attempts.");
            return;
        }

        // Call the API with the valid audioBookId
        $response = AudioBookService::callDetailApi($audioBookId);
        
        // Log response status and basic info
        \Log::info("API Response Status: {$response->status()}");
        
        // Check if the response is successful before parsing JSON
        if ($response->successful()) {
            $responseData = $response->json();
            
            if ($responseData === null) {
                \Log::error("API Response is null or invalid JSON for ID: {$audioBookId}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $this->error("API Response is null or invalid JSON for ID: {$audioBookId}");
                return;
            }
            
            \Log::info("API Response: " . json_encode($responseData));
            
            try {
                $responseData = AudioBookService::createAndGetResponseData($response, $audioBookId);
                $this->info("Response successful");
                \Log::info("Audio book data created successfully for ID: {$audioBookId}");
            } catch (\Exception $e) {
                \Log::error("Error creating audio book data: " . $e->getMessage(), [
                    'audio_book_id' => $audioBookId,
                    'exception' => $e,
                ]);
                $this->error("Error creating audio book data: " . $e->getMessage());
            }
        } else {
            \Log::error("API Response not successful for ID: {$audioBookId}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->error("Response not successful. Status: {$response->status()}");
        }
    }

}
