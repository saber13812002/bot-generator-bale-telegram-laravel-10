<?php

namespace App\Console\Commands;

use App\Models\ChannelPosterQueue;
use App\Models\RssItem;
use App\Models\RssPostItem;
use App\Models\RssPostItemTranslation;
use App\Models\RssPostItemTranslationQueue;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * pipeline:status — گزارش وضعیت زنده‌ی خط لوله‌ی محتوا (P5: monitoring).
 *
 * نمایش:
 * - قلب تپنده‌ی scheduler (heartbeat) + مدت قطعی
 * - آخرین fetch هر فیڈ + فیڈهای مرده/خاموش + خطای آخر
 * - آخرین content/translation/publish کل سیستم و هر کانال RSS
 * - شمارنده‌های صف (queue/sent/error + pending/failed/published) و رکوردهای قدیمی
 * - آخرین دلیل خطا
 *
 * خروجی همیشه exit 0 است، مگر اینکه قلب تپنده > 10 دقیقه قدیمی باشد (exit 1)
 * تا cron/مانیتور بتواند scheduler مرده را تشخیص دهد.
 */
class PipelineStatus extends Command
{
    protected $signature = 'pipeline:status {--json : خروجی JSON برای اسکریپت‌بندی}';

    protected $description = 'Live diagnostics for the content pipeline: scheduler heartbeat, RSS feed health, last fetch/content/translation/publish per channel, queue counters, last failures';

    protected const HEARTBEAT_STALE_SECONDS = 600;
    protected const STALE_RECORD_HOURS = 24;

    public function handle(): int
    {
        $now = now();
        $report = [
            'generated_at' => $now->toIso8601String(),
            'app_timezone' => config('app.timezone'),
            'scheduler' => $this->schedulerStatus($now),
            'rss' => $this->rssStatus($now),
            'queues' => $this->queuesStatus($now),
            'channel_poster' => $this->channelPosterStatus($now),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->render($report);
        }

        return $report['scheduler']['ok'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * وضعیت heartbeat scheduler (pipeline:heartbeat هر دقیقه).
     *
     * @return array<string, mixed>
     */
    protected function schedulerStatus(Carbon $now): array
    {
        $ts = (int) (Cache::get('pipeline:heartbeat:ts') ?? 0);
        $at = (string) (Cache::get('pipeline:heartbeat:at') ?? '');

        $ageSeconds = $ts > 0 ? max(0, $now->getTimestamp() - $ts) : null;
        $ok = $ageSeconds !== null && $ageSeconds <= self::HEARTBEAT_STALE_SECONDS;

        return [
            'ok' => $ok,
            'last_heartbeat' => $at !== '' ? $at : null,
            'age_seconds' => $ageSeconds,
            'stale_after_seconds' => self::HEARTBEAT_STALE_SECONDS,
            'outage_since' => $ok ? null : ($ts > 0 ? date('c', $ts) : 'never (heartbeat cache empty — scheduler not running?)'),
        ];
    }

    /**
     * وضعیت RSS: فیڈها + آخرین fetch/content/translation/publish.
     *
     * @return array<string, mixed>
     */
    protected function rssStatus(Carbon $now): array
    {
        $feeds = RssItem::query()->select(
            'id', 'title', 'url', 'is_active', 'consecutive_failures', 'last_synced_at', 'last_error', 'interval_minutes'
        )->orderBy('id')->get();

        $feedRows = [];
        $deadActive = 0;
        $disabled = 0;

        foreach ($feeds as $f) {
            $ageH = $f->last_synced_at ? max(0, (int) $f->last_synced_at->diffInHours($now)) : null;
            $stale = $ageH === null || $ageH >= 48;
            $active = (bool) $f->is_active;

            if ($stale) {
                $active ? $deadActive++ : $disabled++;
            }

            $feedRows[] = [
                'id' => (int) $f->id,
                'name' => (string) ($f->title ?? $f->url),
                'active' => $active,
                'last_synced_at' => $f->last_synced_at ? $f->last_synced_at->toIso8601String() : null,
                'stale' => $stale,
                'failures' => (int) ($f->consecutive_failures ?? 0),
                'last_error' => $f->last_error ? \Illuminate\Support\Str::limit((string) $f->last_error, 120) : null,
            ];
        }

        $lastPost = RssPostItem::query()->max('created_at');
        $lastTranslation = RssPostItemTranslation::query()->max('created_at');
        $lastPublish = RssPostItemTranslationQueue::query()
            ->where('status', 'sent')
            ->whereNotNull('sent_at')
            ->max('sent_at');
        $lastSentAtRaw = RssPostItemTranslationQueue::query()
            ->whereNotNull('sent_at')
            ->orderByDesc('sent_at')
            ->first(['sent_at']);

        // آخرین fetch/content/translation/publish به تفکیک کانال RSS
        $perChannel = RssPostItemTranslationQueue::query()
            ->select(
                'rss_channel_id',
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'),
                \Illuminate\Support\Facades\DB::raw("SUM(status = 'queue') as q_queue"),
                \Illuminate\Support\Facades\DB::raw("SUM(status = 'sent') as q_sent"),
                \Illuminate\Support\Facades\DB::raw("SUM(status = 'error') as q_error"),
                \Illuminate\Support\Facades\DB::raw('MAX(sent_at) as last_publish')
            )
            ->groupBy('rss_channel_id')
            ->get()
            ->keyBy('rss_channel_id');

        $channelRows = [];
        $channels = \App\Models\RssChannel::with('RssChannelOrigin')->get(['id', 'title', 'origin_id']);
        foreach ($channels as $c) {
            $q = $perChannel[$c->id] ?? null;
            $channelRows[] = [
                'channel_id' => (int) $c->id,
                'title' => (string) $c->title,
                'origin' => (string) ($c->RssChannelOrigin->slug ?? '?'),
                'total_records' => $q ? (int) $q->total : 0,
                'queue' => $q ? (int) $q->q_queue : 0,
                'sent' => $q ? (int) $q->q_sent : 0,
                'error' => $q ? (int) $q->q_error : 0,
                'last_publish' => $q && $q->last_publish ? Carbon::parse($q->last_publish)->toIso8601String() : null,
            ];
        }

        return [
            'feeds_total' => $feeds->count(),
            'feeds_active' => $feeds->where('is_active', true)->count(),
            'feeds_disabled' => $feeds->where('is_active', false)->count(),
            'feeds_stale_active' => $deadActive,
            'feeds_stale_disabled' => $disabled,
            'feeds' => $feedRows,
            'last_content' => $lastPost ? Carbon::parse($lastPost)->toIso8601String() : null,
            'last_translation' => $lastTranslation ? Carbon::parse($lastTranslation)->toIso8601String() : null,
            'last_publish' => $lastPublish ? Carbon::parse($lastPublish)->toIso8601String() : null,
            'publish_outage_since' => $lastSentAtRaw && $lastSentAtRaw->sent_at
                ? Carbon::parse($lastSentAtRaw->sent_at)->toIso8601String()
                : 'never (no sent_at evidence yet — pre-migration sends have no sent_at)',
            'channels' => $channelRows,
        ];
    }

    /**
     * شمارنده‌های صف RSS.
     *
     * @return array<string, mixed>
     */
    protected function queuesStatus(Carbon $now): array
    {
        $cutoff = $now->copy()->subHours(self::STALE_RECORD_HOURS);
        $byStatus = RssPostItemTranslationQueue::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $oldQueue = RssPostItemTranslationQueue::where('status', 'queue')->where('created_at', '<', $cutoff)->count();
        $oldError = RssPostItemTranslationQueue::where('status', 'error')->where('created_at', '<', $cutoff)->count();

        $lastErrorRow = RssPostItemTranslationQueue::query()
            ->where('status', 'error')
            ->whereNotNull('last_error')
            ->orderByDesc('id')
            ->first(['id', 'created_at', 'last_error']);

        return [
            'total' => RssPostItemTranslationQueue::count(),
            'by_status' => ['queue' => (int) ($byStatus['queue'] ?? 0), 'sent' => (int) ($byStatus['sent'] ?? 0), 'error' => (int) ($byStatus['error'] ?? 0)],
            'queue_older_than_' . self::STALE_RECORD_HOURS . 'h' => $oldQueue,
            'error_older_than_' . self::STALE_RECORD_HOURS . 'h' => $oldError,
            'last_error_record' => $lastErrorRow ? [
                'id' => (int) $lastErrorRow->id,
                'created_at' => $lastErrorRow->created_at->toIso8601String(),
                'last_error' => \Illuminate\Support\Str::limit((string) $lastErrorRow->last_error, 200),
            ] : null,
        ];
    }

    /**
     * شمارنده‌های صف channel-poster.
     *
     * @return array<string, mixed>
     */
    protected function channelPosterStatus(Carbon $now): array
    {
        $cutoff = $now->copy()->subHours(self::STALE_RECORD_HOURS);
        $byStatus = ChannelPosterQueue::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $oldPending = ChannelPosterQueue::where('status', ChannelPosterQueue::STATUS_PENDING)
            ->where('created_at', '<', $cutoff)->count();

        $lastPublished = ChannelPosterQueue::whereNotNull('published_at')->orderByDesc('published_at')->first(['published_at']);

        return [
            'total' => ChannelPosterQueue::count(),
            'by_status' => [
                'pending' => (int) ($byStatus[ChannelPosterQueue::STATUS_PENDING] ?? 0),
                'published' => (int) ($byStatus[ChannelPosterQueue::STATUS_PUBLISHED] ?? 0),
                'failed' => (int) ($byStatus[ChannelPosterQueue::STATUS_FAILED] ?? 0),
                'cancelled' => (int) ($byStatus[ChannelPosterQueue::STATUS_CANCELLED] ?? 0),
            ],
            'pending_older_than_' . self::STALE_RECORD_HOURS . 'h' => $oldPending,
            'last_published_at' => $lastPublished && $lastPublished->published_at
                ? Carbon::parse($lastPublished->published_at)->toIso8601String()
                : null,
        ];
    }

    /**
     * رندر متنی گزارش.
     *
     * @param array<string, mixed> $r
     */
    protected function render(array $r): void
    {
        $s = $r['scheduler'];
        $this->info('=== Pipeline Status — ' . $r['generated_at'] . ' (app tz: ' . $r['app_timezone'] . ') ===');
        $this->line('');

        $hb = $s['ok']
            ? "OK (last: {$s['last_heartbeat']}, age {$s['age_seconds']}s)"
            : ('STALE — outage since ' . $s['outage_since']);
        $this->line("Scheduler heartbeat: {$hb}");
        $this->line('');

        $rs = $r['rss'];
        $this->info(sprintf(
            'RSS feeds: %d total | %d active | %d disabled | %d active-but-stale(>48h)',
            $rs['feeds_total'], $rs['feeds_active'], $rs['feeds_disabled'], $rs['feeds_stale_active']
        ));
        $this->line(sprintf(
            'Last content: %s | Last translation: %s | Last publish: %s',
            $rs['last_content'] ?? 'never',
            $rs['last_translation'] ?? 'never',
            $rs['last_publish'] ?? 'never'
        ));
        $this->line('Publish outage since (last sent_at): ' . $rs['publish_outage_since']);
        $this->line('');

        $q = $r['queues'];
        $this->info(sprintf(
            'RSS queue: total=%d queue=%d sent=%d error=%d | old(>24h) queue=%d error=%d',
            $q['total'],
            $q['by_status']['queue'],
            $q['by_status']['sent'],
            $q['by_status']['error'],
            $q['queue_older_than_24h'],
            $q['error_older_than_24h']
        ));
        if ($q['last_error_record']) {
            $le = $q['last_error_record'];
            $this->line('Last failure: #' . $le['id'] . ' (' . $le['created_at'] . '): ' . $le['last_error']);
        }
        $this->line('');

        $cp = $r['channel_poster'];
        $this->info(sprintf(
            'Channel-poster: total=%d pending=%d published=%d failed=%d cancelled=%d | old pending(>24h)=%d | last published: %s',
            $cp['total'],
            $cp['by_status']['pending'],
            $cp['by_status']['published'],
            $cp['by_status']['failed'],
            $cp['by_status']['cancelled'],
            $cp['pending_older_than_24h'],
            $cp['last_published_at'] ?? 'never'
        ));
        $this->line('');

        $this->info('Per-channel publish stats (RSS queues):');
        $this->table(
            ['Channel', 'Origin', 'Total', 'Queue', 'Sent', 'Error', 'Last publish'],
            array_map(
                fn ($c) => [$c['title'], $c['origin'], $c['total_records'], $c['queue'], $c['sent'], $c['error'], $c['last_publish'] ?? 'never'],
                $r['rss']['channels']
            )
        );
        $this->line('');

        $stale = array_filter($r['rss']['feeds'], fn ($f) => $f['stale']);
        if ($stale) {
            $this->warn('Stale feeds (>48h since last sync):');
            $this->table(
                ['ID', 'Name', 'Active', 'Last sync', 'Fails', 'Last error'],
                array_map(
                    fn ($f) => [$f['id'], $f['name'], $f['active'] ? 'yes' : 'no', $f['last_synced_at'] ?? 'never', $f['failures'], $f['last_error'] ?? ''],
                    $stale
                )
            );
        } else {
            $this->line('All feeds synced within 48h.');
        }
    }
}
