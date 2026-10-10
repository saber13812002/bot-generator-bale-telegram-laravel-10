<?php

namespace App\Jobs;

use App\Models\ChannelPosterQueue;
use App\Services\ChannelPosterQueuePublishService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessChannelPosterQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ChannelPosterQueuePublishService $publishService): void
    {
        $items = ChannelPosterQueue::ready()
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();

        foreach ($items as $item) {
            $item->refresh();

            $result = $publishService->publishItem($item);

            Log::info('[ChannelPoster] Scheduled queue item processed', [
                'queue_id' => $item->id,
                'status'   => $result['status'],
            ]);
        }
    }
}
