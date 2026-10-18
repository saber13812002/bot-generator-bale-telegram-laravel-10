<?php

namespace App\Services;

use App\Helpers\AdminHelper;
use App\Helpers\TokenHelper;
use App\Jobs\RssPostItemTranslationToMessengerJob;
use App\Models\ChannelPosterQueue;
use App\Models\RssChannel;
use App\Models\RssPostItemTranslationQueue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * خدمات ریکاوری (احیا) رکوردهای صف انتشار.
 *
 * اصول ایمنی:
 * - حالت پیش‌فرض «dry-run / گزارش» است؛ اجرای واقعی فقط با --execute + ادمین معتبر.
 * - هرگز رکورد ثبت‌شده‌ی «موفق» (sent / published) دوباره ارسال نمی‌شود.
 * - نتایج مبهم (API result unclear) دسته‌بندی needs_review می‌شوند؛ هرگز resend کورکورانه.
 * - رکوردهای queue بدون هیچ سرنجه‌ی تحویل = insufficient_evidence؛ فرض «ارسال‌نشده» برایشان نمی‌رود.
 * - لاک کلی برای جلوگیری از اجرای موازی + lockForUpdate + بررسی مجدد status برای هر رکورد (idempotency).
 *
 * دسته‌بندی رکوردهای RSS (rss_post_item_translation_queues):
 * - sent                                  → delivered (هرگز resend)
 * - error با last_error و بدون سرنجه تحویل → retryable (جاب صریحاً «ارسال‌نشده» ثبت کرده)
 * - error با سرنجه تحویل / خطای مبهم       → needs_review
 * - error بدون last_error (رکوردهای قدیمی) → insufficient_evidence
 * - queue بدون سرنجه                        → insufficient_evidence (هرگز فرض ارسال‌نشده)
 * - queue با سرنجه                          → needs_review
 * - queue بدون سرنجه با --include-queue      → retryable (فقط با تأیید صریح ادمین)
 */
class RssRecoveryService
{
    public const SYSTEM_RSS = 'rss';
    public const SYSTEM_CHANNEL_POSTER = 'channel-poster';
    public const SYSTEM_BOTH = 'both';

    public const CLASS_DELIVERED = 'delivered';
    public const CLASS_RETRYABLE = 'retryable';
    public const CLASS_NEEDS_REVIEW = 'needs_review';
    public const CLASS_INSUFFICIENT = 'insufficient_evidence';
    public const CLASS_CANCELLED = 'cancelled';
    public const CLASS_PENDING = 'pending';

    protected const EXEC_LOCK_KEY = 'rss_recover_exec_lock';
    protected const EXEC_LOCK_TTL_SECONDS = 1800;
    protected const HARD_LIMIT = 100;

    /**
     * سقف تازگی (freshness) برای resend خودکار: رکوردهای قدیمی‌تر از این مقدار
     * (backlog کهنه) بدون بررسی دستی resend نمی‌شوند — مطابق
     * docs/RECOVERY_ROLLOUT_PLAN.md (محتوای بیش از ۷ روز منتشر نمی‌شود؛
     * حداکثر تا ۴۸ ساعت). برای ردیوزیهای خاص، --since/--until یا بررسی دستی.
     */
    protected const MAX_RETRY_AGE_HOURS = 48;

    /**
     * نشانه‌هایی در last_error که نشانه‌ی «نتیجه مبهم API» هستند
     * (ممکن است پیام رسیده باشد ولی پاسخ API دریافت نشده باشد).
     */
    protected const AMBIGUOUS_ERROR_MARKERS = [
        'timeout',
        'curl error 28',
        'connection',
        'reset',
        'socket',
        'broken pipe',
        'stream',
    ];

    /**
     * @param array{
     *   system?: string,               rss|channel-poster|both
     *   channelId?: int|string|null,   rss channel id یا slug
     *   recordId?: int|null,           id رکورد صف (هر دو سیستم)
     *   status?: string|null,
     *   since?: string|null,           ISO date/datetime برای created_at
     *   until?: string|null,
     *   limit?: int|null,
     *   execute?: bool,
     *   adminChatId?: string|int|null,
     *   includeQueue?: bool,
     * } $options
     *
     * @return array{
     *   dry_run: bool,
     *   admin_ok: bool,
     *   lock_ok: bool,
     *   error: ?string,
     *   totals: array<string, int>,
     *   rss: array<int, array<string, mixed>>,
     *   channel_poster: array<int, array<string, mixed>>,
     *   executed: array<int, array<string, mixed>>,
     * }
     */
    public function run(array $options): array
    {
        $system = $options['system'] ?? self::SYSTEM_BOTH;
        $execute = (bool) ($options['execute'] ?? false);
        $adminChatId = $options['adminChatId'] ?? null;
        $includeQueue = (bool) ($options['includeQueue'] ?? false);
        $limit = min(self::HARD_LIMIT, max(1, (int) ($options['limit'] ?? 100)));

        $result = [
            'dry_run' => !$execute,
            'admin_ok' => true,
            'lock_ok' => true,
            'error' => null,
            'totals' => [],
            'rss' => [],
            'channel_poster' => [],
            'executed' => [],
        ];

        if (!in_array($system, [self::SYSTEM_RSS, self::SYSTEM_CHANNEL_POSTER, self::SYSTEM_BOTH], true)) {
            $result['error'] = 'invalid system (expected rss, channel-poster or both)';

            return $this->finalize($result);
        }

        // --- دروازه‌ی ادمین برای اجرای واقعی ---
        if ($execute) {
            if ($adminChatId === null || $adminChatId === '' || !AdminHelper::isAdmin($adminChatId)) {
                $result['admin_ok'] = false;
                $result['error'] = 'real execution requires a valid --admin-chat-id (admin check failed)';
                Log::warning('rss:recover: real execution blocked — admin check failed', [
                    'admin_chat_id' => $adminChatId !== null && $adminChatId !== '' ? (string) $adminChatId : null,
                ]);

                return $this->finalize($result);
            }
        }

        // --- لاک کلی (فقط هنگام اجرای واقعی؛ اسکن خالی read-only است) ---
        $lock = null;
        if ($execute) {
            $lock = Cache::lock(self::EXEC_LOCK_KEY, self::EXEC_LOCK_TTL_SECONDS);
            if (!$lock->get()) {
                $result['lock_ok'] = false;
                $result['error'] = 'another rss:recover execution is in progress';

                return $this->finalize($result);
            }
        }

        try {
            if (in_array($system, [self::SYSTEM_RSS, self::SYSTEM_BOTH], true)) {
                $this->runRss($result, $options, $execute, $includeQueue, $limit);
            }

            if (in_array($system, [self::SYSTEM_CHANNEL_POSTER, self::SYSTEM_BOTH], true)) {
                $this->runChannelPoster($result, $options, $execute, $limit);
            }
        } catch (\Throwable $e) {
            $result['error'] = 'recovery run failed: ' . TokenHelper::redactTokens($e->getMessage());
            Log::error('rss:recover: run failed', [
                'error' => TokenHelper::redactTokens($e->getMessage()),
                'trace' => Str::limit($e->getTraceAsString(), 1000),
            ]);
        } finally {
            if ($lock !== null) {
                try {
                    $lock->release();
                } catch (\Throwable $ignored) {
                }
            }
        }

        return $this->finalize($result);
    }

    /**
     * محاسبه‌ی نهایی آمار (totals) روی رکوردهای دسته‌بندی‌شده.
     */
    protected function finalize(array $result): array
    {
        $totals = [
            'delivered' => 0,
            'retryable' => 0,
            'needs_review' => 0,
            'insufficient_evidence' => 0,
            'cancelled' => 0,
            'pending' => 0,
            'total' => 0,
            'executed_retried' => 0,
            'executed_succeeded' => 0,
            'executed_failed' => 0,
            'executed_skipped' => 0,
        ];

        foreach ([$result['rss'], $result['channel_poster']] as $rows) {
            foreach ($rows as $row) {
                $totals['total']++;
                $cls = $row['class'];
                if (array_key_exists($cls, $totals)) {
                    $totals[$cls]++;
                }
            }
        }

        foreach ($result['executed'] as $row) {
            if ($row['skipped']) {
                $totals['executed_skipped']++;
            } elseif ($row['ok']) {
                $totals['executed_succeeded']++;
            } else {
                $totals['executed_failed']++;
            }
        }
        $totals['executed_retried'] = $totals['executed_succeeded'] + $totals['executed_failed'];

        $result['totals'] = $totals;

        return $result;
    }

    /**
     * اسکن + دسته‌بندی + (در صورت --execute) resend ایمن رکوردهای RSS.
     */
    protected function runRss(array &$result, array $options, bool $execute, bool $includeQueue, int $limit): void
    {
        $query = RssPostItemTranslationQueue::query()->with('postTranslation.post.rssItem');

        if ($options['channelId'] !== null && $options['channelId'] !== '') {
            $channel = $this->resolveRssChannel($options['channelId']);
            if ($channel === null) {
                $result['error'] = $result['error'] ?? 'rss channel not found for --channel=' . $options['channelId'];

                return;
            }
            $query->where('rss_channel_id', $channel->id);
        }

        if ($options['recordId'] !== null && $options['recordId'] !== '') {
            $query->where('id', (int) $options['recordId']);
        }

        if (!empty($options['status'])) {
            $query->where('status', (string) $options['status']);
        }

        $query = $this->applyDateFilters($query, $options);

        $records = $query->orderBy('id')->limit($limit)->get();

        $channelNames = RssChannel::pluck('title', 'id');

        foreach ($records as $q) {
            $row = $this->classifyRssRecord($q, $includeQueue);
            $row['channel'] = (string) ($channelNames[$q->rss_channel_id] ?? ('#' . $q->rss_channel_id));
            $row['title'] = Str::limit((string) ($q->postTranslation->post->title ?? $q->postTranslation->post->rss_post_item_id ?? ''), 60);

            $result['rss'][] = $row;

            if ($execute && $row['class'] === self::CLASS_RETRYABLE) {
                $result['executed'][] = $this->executeRssRetry($q->id, $row, $includeQueue);
            }
        }
    }

    /**
     * اسکن + دسته‌بندی + (در صورت --execute) resend ایمن رکوردهای channel-poster.
     */
    protected function runChannelPoster(array &$result, array $options, bool $execute, int $limit): void
    {
        $query = ChannelPosterQueue::query()->with(['publishLogs.destination', 'bot']);

        if ($options['recordId'] !== null && $options['recordId'] !== '') {
            $query->where('id', (int) $options['recordId']);
        }

        if (!empty($options['status'])) {
            $query->where('status', (string) $options['status']);
        }

        // --channel= برای channel-poster = bot_id
        if ($options['channelId'] !== null && $options['channelId'] !== '') {
            if (is_numeric($options['channelId'])) {
                $query->where('bot_id', (int) $options['channelId']);
            } else {
                $result['error'] = $result['error'] ?? 'channel-poster --channel expects a bot_id (numeric)';

                return;
            }
        }

        $query = $this->applyDateFilters($query, $options);

        $records = $query->orderBy('id')->limit($limit)->get();

        foreach ($records as $item) {
            $row = $this->classifyChannelPosterRecord($item);
            $row['bot'] = $item->bot_id;
            $row['tag'] = (string) ($item->tag ?? '');

            $result['channel_poster'][] = $row;

            if ($execute && $row['class'] === self::CLASS_RETRYABLE) {
                $result['executed'][] = $this->executeChannelPosterRetry($item->id, $row);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function classifyRssRecord(RssPostItemTranslationQueue $q, bool $includeQueue): array
    {
        $status = (string) $q->status;
        $hasEvidence = !empty($q->message_id) || !empty($q->sent_at);
        $lastError = (string) ($q->last_error ?? '');

        if ($status === 'sent') {
            return $this->row($q->id, self::CLASS_DELIVERED, 'recorded as sent' . ($hasEvidence ? ' (with delivery evidence)' : ''));
        }

        if ($status === 'error') {
            if ($hasEvidence) {
                return $this->row($q->id, self::CLASS_NEEDS_REVIEW, 'error status but delivery evidence exists (message_id/sent_at)');
            }
            if ($lastError === '') {
                return $this->row($q->id, self::CLASS_INSUFFICIENT, 'error without last_error (pre-migration record) — no evidence either way');
            }
            if ($this->looksAmbiguous($lastError)) {
                return $this->row($q->id, self::CLASS_NEEDS_REVIEW, 'error looks ambiguous (may have been delivered): ' . $this->safe($lastError));
            }

            return $this->row($q->id, self::CLASS_RETRYABLE, 'failed before base delivery: ' . $this->safe($lastError));
        }

        // status === 'queue' (هرگز «ارسال‌نشده» فرض نمی‌شود)
        if ($hasEvidence) {
            return $this->row($q->id, self::CLASS_NEEDS_REVIEW, 'queue status but delivery evidence exists');
        }
        if ($includeQueue) {
            return $this->row($q->id, self::CLASS_RETRYABLE, 'queue status, no evidence — retried via explicit --include-queue');
        }

        return $this->row($q->id, self::CLASS_INSUFFICIENT, 'queue status, no delivery evidence — awaiting/unclear, not assumed unpublished');
    }

    /**
     * @return array<string, mixed>
     */
    protected function classifyChannelPosterRecord(ChannelPosterQueue $item): array
    {
        $status = (string) $item->status;
        $logs = $item->publishLogs;
        $successes = $logs->filter(fn ($l) => (bool) $l->success);
        $failures = $logs->filter(fn ($l) => !(bool) $l->success);

        $summary = sprintf('logs: %d ok / %d failed', $successes->count(), $failures->count());

        if ($status === ChannelPosterQueue::STATUS_PUBLISHED) {
            return $this->row($item->id, self::CLASS_DELIVERED, 'recorded as published. ' . $summary);
        }

        if ($status === ChannelPosterQueue::STATUS_CANCELLED) {
            // cancelled هرگز بدون تأیید دستی بازنویسی نمی‌شود
            return $this->row($item->id, self::CLASS_CANCELLED, 'cancelled — never rewritten automatically. ' . $summary);
        }

        if ($status === ChannelPosterQueue::STATUS_FAILED) {
            if ($successes->isNotEmpty()) {
                return $this->row($item->id, self::CLASS_NEEDS_REVIEW, 'failed but some destinations already succeeded — no blind resend. ' . $summary);
            }

            return $this->row($item->id, self::CLASS_RETRYABLE, 'failed without any successful destination. ' . $summary);
        }

        // pending
        if ($logs->isNotEmpty()) {
            return $this->row($item->id, self::CLASS_NEEDS_REVIEW, 'pending but publish logs exist (ambiguous). ' . $summary);
        }

        return $this->row($item->id, self::CLASS_PENDING, 'pending, no logs — normal state, scheduler will pick it up. ' . $summary);
    }

    /**
     * اجرای resend یک رکورد RSS با لاک ردیفی + بررسی مجدد status (idempotency).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function executeRssRetry(int $recordId, array $row, bool $includeQueue = false): array
    {
        $out = [
            'system' => 'rss',
            'record_id' => $recordId,
            'skipped' => true,
            'ok' => false,
            'final_status' => null,
            'note' => null,
        ];

        try {
            DB::transaction(function () use ($recordId, $includeQueue, &$out) {
                /** @var RssPostItemTranslationQueue|null $fresh */
                $fresh = RssPostItemTranslationQueue::whereKey($recordId)->lockForUpdate()->first();

                if (!$fresh) {
                    $out['note'] = 'record vanished';

                    return;
                }

                // بررسی مجدد status پس از lock — اگر بین اسکن و اجرا تغییر کرده، رد می‌شود.
                // رکوردهای 'queue' فقط با --include-queue (که همین وضعیت را retryable طبقه‌بندی می‌کند) ریسند می‌شوند.
                $allowedStatuses = $includeQueue ? ['error', 'queue'] : ['error'];
                if (!in_array($fresh->status, $allowedStatuses, true)) {
                    $out['note'] = 'status changed to ' . $fresh->status . ' since scan — skipped';

                    return;
                }

                // سقف تازگی: backlog کهنه (بیش از ۴۸ ساعت) بدون بررسی دستی resend نمی‌شود.
                if ($fresh->created_at !== null) {
                    $ageHours = abs(now()->diffInHours(Carbon::parse($fresh->created_at->toDateTimeString())));
                    if ($ageHours > self::MAX_RETRY_AGE_HOURS) {
                        $out['note'] = sprintf(
                            'stale record (age %dh > %dh) — manual review required, not auto-resent',
                            (int) $ageHours,
                            self::MAX_RETRY_AGE_HOURS
                        );

                        return;
                    }
                }

                // جاب خودش وضعیت را sent/error می‌کند و سرنجه تحویل را ثبت می‌کند.
                // در QUEUE_CONNECTION=sync این inline اجرا می‌شود.
                RssPostItemTranslationToMessengerJob::dispatch($fresh);

                $fresh->refresh();
                $out['final_status'] = (string) $fresh->status;
                $out['skipped'] = false;
                $out['ok'] = $fresh->status === 'sent';
                $out['note'] = $fresh->last_error ? $this->safe((string) $fresh->last_error) : null;
            });
        } catch (\Throwable $e) {
            $out['skipped'] = false;
            $out['ok'] = false;
            $out['note'] = 'exception: ' . $this->safe($e->getMessage());
            Log::error('rss:recover: retry exception', ['record_id' => $recordId, 'error' => TokenHelper::redactTokens($e->getMessage())]);
        }

        return $out;
    }

    /**
     * اجرای resend یک رکورد channel-poster با لاک ردیفی + بررسی مجدد status.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function executeChannelPosterRetry(int $recordId, array $row): array
    {
        $out = [
            'system' => 'channel-poster',
            'record_id' => $recordId,
            'skipped' => true,
            'ok' => false,
            'final_status' => null,
            'note' => null,
        ];

        try {
            DB::transaction(function () use ($recordId, &$out) {
                /** @var ChannelPosterQueue|null $fresh */
                $fresh = ChannelPosterQueue::whereKey($recordId)->lockForUpdate()->first();

                if (!$fresh) {
                    $out['note'] = 'record vanished';

                    return;
                }

                if ($fresh->status !== ChannelPosterQueue::STATUS_FAILED) {
                    $out['note'] = 'status changed to ' . $fresh->status . ' since scan — skipped';

                    return;
                }

                // reset به pending تا publishItem (که فقط pending می‌پذیرد) اجرا شود.
                // مقصدهای موفق قبلی نمی‌توانستند وجود داشته باشند (وگرنه needs_review بود).
                $fresh->update(['status' => ChannelPosterQueue::STATUS_PENDING, 'published_at' => null]);

                /** @var ChannelPosterQueuePublishService $publishService */
                $publishService = app(ChannelPosterQueuePublishService::class);
                $publishResult = $publishService->publishItem($fresh, false);

                $fresh->refresh();
                $out['final_status'] = (string) $fresh->status;
                $out['skipped'] = false;
                $out['ok'] = ($publishResult['status'] ?? '') === 'published';
                $out['note'] = $publishResult['error'] ?? null;
            });
        } catch (\Throwable $e) {
            $out['skipped'] = false;
            $out['ok'] = false;
            $out['note'] = 'exception: ' . $this->safe($e->getMessage());
            Log::error('rss:recover: channel-poster retry exception', ['record_id' => $recordId, 'error' => TokenHelper::redactTokens($e->getMessage())]);
        }

        return $out;
    }

    /**
     * آیا کانال RSS با id یا slug پیدا می‌شود؟
     */
    protected function resolveRssChannel($idOrSlug): ?RssChannel
    {
        if (is_numeric($idOrSlug)) {
            return RssChannel::find((int) $idOrSlug);
        }

        return RssChannel::where('slug', (string) $idOrSlug)->first();
    }

    /**
     * فیلترهای تاریخ روی created_at (portable برای MySQL و SQLite).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    protected function applyDateFilters($query, array $options)
    {
        if (!empty($options['since'])) {
            try {
                $query->where('created_at', '>=', Carbon::parse((string) $options['since'])->toDateTimeString());
            } catch (\Throwable $e) {
                // ignore invalid since
            }
        }

        if (!empty($options['until'])) {
            try {
                $query->where('created_at', '<=', Carbon::parse((string) $options['until'])->toDateTimeString());
            } catch (\Throwable $e) {
                // ignore invalid until
            }
        }

        return $query;
    }

    protected function looksAmbiguous(string $lastError): bool
    {
        $lower = strtolower($lastError);

        foreach (self::AMBIGUOUS_ERROR_MARKERS as $marker) {
            if (Str::contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(int $recordId, string $class, string $reason): array
    {
        return [
            'id' => $recordId,
            'class' => $class,
            'reason' => $this->safe($reason),
        ];
    }

    protected function safe(string $message): string
    {
        return Str::limit(TokenHelper::redactTokens($message), 250);
    }
}
