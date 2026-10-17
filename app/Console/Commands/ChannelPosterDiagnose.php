<?php

namespace App\Console\Commands;

use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterQueue;
use App\Services\ChannelPosterMediaStore;
use Illuminate\Console\Command;

class ChannelPosterDiagnose extends Command
{
    protected $signature = 'channel-poster:diagnose';

    protected $description = 'عیب‌یابی (فقط‌خواندنی) channel poster: صف، مقصدها و پوشه‌ی رسانه';

    public function handle(ChannelPosterMediaStore $mediaStore): int
    {
        $this->line('<info>── Queue ──</info>');
        foreach ([
            ChannelPosterQueue::STATUS_PENDING,
            ChannelPosterQueue::STATUS_PUBLISHED,
            ChannelPosterQueue::STATUS_FAILED,
            ChannelPosterQueue::STATUS_CANCELLED,
        ] as $status) {
            $count = ChannelPosterQueue::where('status', $status)->count();
            $this->line(sprintf('  %-10s : %d', $status, $count));
        }

        $this->line('');
        $this->line('<info>── Destinations ──</info>');
        $destinations = ChannelPosterDestination::all();
        $this->line('  total  : ' . $destinations->count());
        foreach ($destinations->groupBy('platform') as $platform => $group) {
            $active = $group->where('is_active', true)->count();
            $this->line(sprintf('  %-10s : %d total, %d active', $platform, $group->count(), $active));
        }

        $this->line('');
        $this->line('<info>── Media folder (storage/app/channel-poster-media) ──</info>');
        $diagnostics = $mediaStore->diagnose();
        $this->line('  files    : ' . $diagnostics['total']);
        $this->line('  referenced: ' . $diagnostics['referenced']);
        $this->line('  orphan   : ' . $diagnostics['orphan']);
        $this->line('  stale    : ' . $diagnostics['stale']);
        $this->line('  bytes    : ' . number_format($diagnostics['bytes']));

        if ($diagnostics['files'] !== []) {
            $this->table(
                ['File', 'Bytes', 'Status'],
                array_map(
                    fn (array $file) => [$file['name'], (string) $file['bytes'], $file['status']],
                    $diagnostics['files']
                )
            );
        }

        $this->line('');
        $this->line('<info>── Recent queue items ──</info>');
        $recent = ChannelPosterQueue::query()
            ->latest('id')
            ->take(10)
            ->get();

        if ($recent->isEmpty()) {
            $this->line('  (empty)');
        } else {
            $this->table(
                ['ID', 'Bot', 'Tag', 'Type', 'Status', 'Scheduled at'],
                $recent->map(fn ($item) => [
                    $item->id,
                    $item->bot_id,
                    $item->tag,
                    $item->content_type,
                    $item->status,
                    (string) $item->scheduled_at,
                ])->all()
            );
        }

        $this->info('Done. (read-only — no changes were made)');
        return self::SUCCESS;
    }
}
