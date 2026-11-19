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

    /**
     * آیتم‌های pending باقدمت بیش از این تعداد روز «قدیمی» (stale) محسوب می‌شوند
     * و به‌جای انتشار، لغو (cancel) می‌شوند.
     */
    public const STALE_DAYS = 7;

    public function handle(ChannelPosterQueuePublishService $publishService): void
    {
        // 1) لغو آیتم‌های قدیمی تا محتوای کهنه (مثلاً 17 روزه) منتشر نشود
        $staleBefore = now()->subDays(self::STALE_DAYS);

        ChannelPosterQueue::pending()
            ->where('scheduled_at', '<', $staleBefore)
            ->get()
            ->each(function ($item) {
                $item->status = ChannelPosterQueue::STATUS_CANCELLED;
                $item->save();
                Log::info('[ChannelPoster] Cancelled stale queue item (older than ' . self::STALE_DAYS . ' days)', [
                    'queue_id' => $item->id,
                    'bot_id' => $item->bot_id,
                    'scheduled_at' => (string) $item->scheduled_at,
                ]);
            });

        // 2) پردازش آیتم‌هایی که نوبتشان رسیده است
        $items = ChannelPosterQueue::ready()
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();

        Log::info('[ChannelPoster] ProcessChannelPosterQueueJob started (diagnostic marker)', ['ready_items' => $items->count()]);

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
