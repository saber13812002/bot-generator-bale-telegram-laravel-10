<?php

namespace App\Console\Commands;

use App\Interfaces\Services\BookPublishingService;
use App\Models\Bot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduleBookPublishing extends Command
{
    protected $signature = 'app:schedule-book-publishing';

    protected $description = 'Publish random book pages to channels (runs between 7 PM and 12 AM)';

    public function __construct(
        private BookPublishingService $publishingService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $currentHour = (int) now()->format('H');
        
        // Only run between 7 PM (19) and 12 AM (23)
        if ($currentHour < 19 || $currentHour > 23) {
            $this->info('Outside publishing hours (7 PM - 12 AM). Skipping...');
            return;
        }

        $this->info('Starting book page publishing...');

        // Get all active bots
        $bots = Bot::where('telegram_bot_status', 'Active')
            ->orWhere('bale_bot_status', 'Active')
            ->get();

        $publishedCount = 0;

        foreach ($bots as $bot) {
            try {
                // Determine bot type (telegram or bale)
                $type = $bot->telegram_bot_status === 'Active' ? 'telegram' : 'bale';
                
                // Get random pending item
                $queueItem = $this->publishingService->getRandomPendingItem($bot->id);
                
                if ($queueItem) {
                    if ($this->publishingService->publishToChannels($queueItem, $bot->id, $type)) {
                        $publishedCount++;
                        $this->info("Published item {$queueItem->id} for bot {$bot->id}");
                    }
                }
            } catch (\Exception $e) {
                Log::error('ScheduleBookPublishing - Error processing bot', [
                    'bot_id' => $bot->id,
                    'error' => $e->getMessage()
                ]);
                $this->error("Error processing bot {$bot->id}: " . $e->getMessage());
            }
        }

        $this->info("Published {$publishedCount} items.");
    }
}
