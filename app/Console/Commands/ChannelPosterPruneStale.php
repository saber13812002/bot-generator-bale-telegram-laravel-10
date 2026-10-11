<?php

namespace App\Console\Commands;

use App\Models\ChannelPosterQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ChannelPosterPruneStale extends Command
{
    protected $signature = 'channel-poster:prune-stale
        {--days=7 : آیتم‌های pending باقدمت بیش از این تعداد روز لغو می‌شوند}
        {--dry-run : فقط نشان بده چه چیزی لغو می‌شود، بدون تغییر}';

    protected $description = 'لغو (cancel) آیتم‌های pending قدیمی در صف channel_poster_queue تا محتوای کهنه منتشر نشود';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $stale = ChannelPosterQueue::pending()
            ->where('scheduled_at', '<', $cutoff)
            ->orderBy('scheduled_at')
            ->get();

        if ($stale->isEmpty()) {
            $this->info("No pending items older than {$days} days found.");
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Bot', 'Tag', 'Scheduled at', 'Age (days)'],
            $stale->map(fn ($i) => [
                $i->id,
                $i->bot_id,
                $i->tag,
                (string) $i->scheduled_at,
                (string) now()->diffInDays($i->scheduled_at, false),
            ])->all()
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing was changed. Run without --dry-run to cancel.');
            return self::SUCCESS;
        }

        foreach ($stale as $item) {
            $item->status = ChannelPosterQueue::STATUS_CANCELLED;
            $item->save();
        }

        Log::info('[ChannelPoster] Pruned stale pending queue items', [
            'count' => $stale->count(),
            'days' => $days,
        ]);

        $this->info("Cancelled {$stale->count()} stale item(s).");
        return self::SUCCESS;
    }
}
