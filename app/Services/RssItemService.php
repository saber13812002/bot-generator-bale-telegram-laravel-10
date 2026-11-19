<?php

namespace App\Services;

use App\Models\RssItem;
use App\Helpers\TokenHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RssItemService
{
    /** تعداد خطاهای متوالی که بعد از آن فید به‌طور خودکار غیرفعال می‌شود */
    public const MAX_CONSECUTIVE_FAILURES = 10;

    /** فیدهای غیرفعال هر 7 روز یک‌بار بازتست می‌شوند تا اگر زنده شده باشند دوباره فعال شوند */
    public const RETEST_DISABLED_EVERY_DAYS = 7;

    public function __construct()
    {
        //
    }

    /**
     * دریافت فیدهای به‌روز شده.
     *
     * هر فید در try/catch جداگانه اجرا می‌شود تا خطای یک فید بقیه را
     * مختل نکند (عایق‌سازی خطا per-feed). خروجی آرایه خلاصه‌ای با
     * شمارنده‌های موفق/ناموفق و شناسه‌های فیدهای خراب است.
     *
     * @param bool $switch true = فقط فیدهای اخیر (--switch)، false = همه فیدهای سرِ وقت
     * @param int|null $limit حداکثر تعداد فید در هر اجرا (جلوگیری از طولانی شدن cron)
     * @param int|null $feedId اگر تنظیم باشد، فقط همان فید پردازش می‌شود
     * @param bool $dryRun در حالت dry-run هیچ ذخیره‌ای انجام نمی‌شود (فقط گزارش)
     * @return array{ok: int, failed: int, skipped: int, total: int, failed_feeds: array, new_saved: int, message: ?string}
     */
    public static function run($switch = false, ?int $limit = null, ?int $feedId = null, bool $dryRun = false): array
    {
        $summary = [
            'ok' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total' => 0,
            'failed_feeds' => [],
            'new_saved' => 0,
            'message' => null,
        ];

        $items = self::resolveFeedsToRun($switch, $feedId, $limit);

        $summary['total'] = $items->count();

        if ($items->isEmpty()) {
            $summary['message'] = $feedId
                ? 'target feed not found or not active'
                : 'no due feeds';

            return $summary;
        }

        foreach ($items as $item) {
            try {
                $unique_field_name = $item->unique_xml_tag ?? 'link';

                if ($dryRun) {
                    // در dry-run فقط اعتبار URL بررسی می‌شود؛ درخواست HTTP و ذخیره انجام نمی‌شود
                    if (!preg_match('#^https?://#i', trim((string) $item->url))) {
                        $summary['failed']++;
                        $summary['failed_feeds'][] = [
                            'id' => (int) $item->id,
                            'url' => $item->url,
                            'error' => 'invalid_url',
                        ];
                        continue;
                    }

                    $summary['ok']++;
                    continue;
                }

                $result = RssService::readRssAndSave($item->url, $item->id, $unique_field_name);

                self::recordFeedResult($item, $result);

                if (!empty($result['ok'])) {
                    $summary['ok']++;
                    $summary['new_saved'] += (int) ($result['new_saved'] ?? 0);
                } else {
                    $summary['failed']++;
                    $summary['failed_feeds'][] = [
                        'id' => (int) $item->id,
                        'url' => $item->url,
                        'error' => (string) ($result['error'] ?? 'unknown error'),
                    ];
                }
            } catch (\Throwable $e) {
                // عایق‌سازی: خطای یک فید نباید بقیه فیدها را متوقف کند
                Log::error('RssItemService: unexpected feed processing error', [
                    'rss_item_id' => $item->id,
                    'url' => TokenHelper::redactTokens((string) $item->url),
                    'error' => TokenHelper::redactTokens($e->getMessage()),
                ]);

                $summary['failed']++;
                $summary['failed_feeds'][] = [
                    'id' => (int) $item->id,
                    'url' => $item->url,
                    'error' => 'processing_exception: ' . $e->getMessage(),
                ];
            }
        }

        if (!$dryRun) {
            // بازتست هفتگی فیدهای غیرفعال (خودش isolated است)
            self::retestDisabledFeeds();
        }

        return $summary;
    }

    /**
     * انتخاب فیدهای قابل پردازش برای این اجرا.
     *
     * - فیلتر سر-وقت (due) سمت PHP انجام می‌شود (سازگار با SQLite/MySQL —
     *   قبلاً whereRaw DATE_ADD فقط روی MySQL کار می‌کرد).
     * - مرتب‌سازی بر اساس قدیمی‌ترین last_synced_at (NULLها اول) تا فیدهای
     *   انتهای لیست گرسنه نمانند (anti-starvation) وقتی limit فعال است.
     *
     * @return Collection
     */
    public static function resolveFeedsToRun(bool $switch = false, ?int $feedId = null, ?int $limit = null): Collection
    {
        if ($feedId !== null) {
            $item = RssItem::query()->find($feedId);

            return $item ? new Collection([$item]) : new Collection();
        }

        $query = RssItem::query()->where('is_active', 1);

        $items = $query->get()->values();

        if (!$switch) {
            $now = Carbon::now();
            $items = $items->filter(function (RssItem $item) use ($now) {
                if ($item->last_synced_at === null) {
                    return true;
                }

                $lastSynced = Carbon::parse($item->last_synced_at);
                $dueAt = $lastSynced->copy()->addMinutes((int) ($item->interval_minutes ?? 59));

                return $dueAt->lte($now);
            });
        }

        $items = $items->sortBy(function (RssItem $item) {
            // NULL (هرگز سینک نشده‌اند) اولویت بالاترین را دارند
            return $item->last_synced_at === null ? 0 : Carbon::parse($item->last_synced_at)->timestamp;
        })->values();

        if ($limit !== null && $limit > 0) {
            $items = $items->take($limit);
        }

        return $items;
    }

    /**
     * ثبت نتیجه دریافت فید و مدیریت سلامت آن:
     * - موفق: شمارنده خطا صفر، last_error پاک، last_synced_at به‌روز می‌شود
     * - ناموفق: شمارنده خطا افزایش می‌یابد؛ با رسیدن به حد، فید خودکار غیرفعال می‌شود (is_active = 0)
     *
     * @param RssItem $item
     * @param array{ok: bool, error: string|null} $result
     */
    public static function recordFeedResult(RssItem $item, array $result): void
    {
        if (!empty($result['ok'])) {
            $item->last_synced_at = now();
            $item->consecutive_failures = 0;
            $item->last_error = null;
            $item->save();
            return;
        }

        $item->consecutive_failures = (int) ($item->consecutive_failures ?? 0) + 1;
        $item->last_error = Str::limit(TokenHelper::redactTokens((string) ($result['error'] ?? 'unknown error')), 250);
        // در خطا هم last_synced_at به‌روز می‌شود تا فید قبل از رسیدن interval دوباره تست نشود
        $item->last_synced_at = now();

        if ($item->consecutive_failures >= self::MAX_CONSECUTIVE_FAILURES && (int) $item->is_active === 1) {
            $item->is_active = 0;
            Log::warning('RSS feed auto-disabled after consecutive failures', [
                'rss_item_id' => $item->id,
                'url' => TokenHelper::redactTokens((string) $item->url),
                'failures' => $item->consecutive_failures,
                'last_error' => $item->last_error,
            ]);
        }

        $item->save();
    }

    /**
     * بازتست هفتگی فیدهای غیرفعال: فقط فیدهایی که RETEST_DISABLED_EVERY_DAYS
     * از آخرین تستشان گذشته باشد. در هر اجرا حداکثر چند فید بازتست می‌شود
     * تا run طولانی نشود (هر run 3 فید).
     *
     * @return int تعداد فیدهایی که بازفعال شدند
     */
    public static function retestDisabledFeeds(): int
    {
        $candidates = RssItem::query()
            ->where('is_active', 0)
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subDays(self::RETEST_DISABLED_EVERY_DAYS));
            })
            ->orderBy('id')
            ->limit(3)
            ->get();

        $reEnabled = 0;

        foreach ($candidates as $item) {
            try {
                $result = RssService::readRssAndSave($item->url, $item->id, $item->unique_xml_tag ?? 'link');

                if (!empty($result['ok'])) {
                    $item->is_active = 1;
                    $item->consecutive_failures = 0;
                    $item->last_error = null;
                    $item->last_synced_at = now();
                    $item->save();
                    $reEnabled++;
                    Log::info('Disabled RSS feed re-enabled after successful retest', [
                        'rss_item_id' => $item->id,
                        'url' => TokenHelper::redactTokens((string) $item->url),
                    ]);
                } else {
                    $item->last_error = Str::limit(TokenHelper::redactTokens((string) ($result['error'] ?? 'unknown error')), 250);
                    $item->last_synced_at = now();
                    $item->save();
                }
            } catch (\Throwable $e) {
                Log::error('RssItemService: retest failed', [
                    'rss_item_id' => $item->id,
                    'error' => TokenHelper::redactTokens($e->getMessage()),
                ]);
            }
        }

        return $reEnabled;
    }

    /**
     * @return Collection
     */
    public static function getRssItemsThatShould(): Collection
    {
        return self::resolveFeedsToRun(false);
    }

    /**
     * @return Collection
     */
    public static function getRssItemsThatShouldTest(): Collection
    {
        return RssItem::query()
            ->where('last_synced_at', '<', Carbon::now()->subHours(2))
            ->get();
    }

    /**
     * @return Collection
     */
    public static function getRssItemsThatActivated(): Collection
    {
        return RssItem::query()
            ->whereIsActive(1)
            ->get();
    }

    /**
     * @return Collection
     */
    public static function getRssItemsAll(): Collection
    {
        return RssItem::query()
            ->get();
    }

    /**
     * @return Collection
     */
    public static function getRssItemsForToday(): Collection
    {
        return RssItem::query()
            ->orderByDesc('id')
            ->limit(2)
            ->get();

    }
}
