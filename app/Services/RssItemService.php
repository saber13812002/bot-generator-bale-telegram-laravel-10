<?php

namespace App\Services;

use App\Models\RssItem;
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

    public static function run($switch = false)
    {
        $items = $switch
            ? self::getRssItemsForToday()
            : self::getRssItemsThatShould();

        foreach ($items as $item) {
            $unique_field_name = $item->unique_xml_tag ?? 'link';
            $result = RssService::readRssAndSave($item->url, $item->id, $unique_field_name);

            self::recordFeedResult($item, $result);
        }

        // بازتست هفتگی یک فید غیرفعال شده تا اگر زنده شده باشد دوباره فعال شود
        self::retestOneDisabledFeed();
    }

    /**
     * ثبت نتیجه دریافت فید و مدیریت سلامت آن:
     * - موفق: شمارنده خطا صفر، last_error پاک، last_synced_at به‌روز می‌شود
     * - ناموفق: شمارنده خطا افزایش می‌یابد؛ با رسیدن به حد، فید خودکار غیرفعال می‌شود (is_active = 0)
     *
     * @param RssItem $item
     * @param array{ok: bool, error: string|null, new: int} $result
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
        $item->last_error = Str::limit((string) ($result['error'] ?? 'unknown error'), 250);
        // در خطا هم last_synced_at به‌روز می‌شود تا فید قبل از رسیدن interval دوباره تست نشود
        $item->last_synced_at = now();

        if ($item->consecutive_failures >= self::MAX_CONSECUTIVE_FAILURES && (int) $item->is_active === 1) {
            $item->is_active = 0;
            Log::warning('RSS feed auto-disabled after consecutive failures', [
                'rss_item_id' => $item->id,
                'url' => $item->url,
                'failures' => $item->consecutive_failures,
                'last_error' => $item->last_error,
            ]);
        }

        $item->save();
    }

    /**
     * بازتست هفتگی یک فید غیرفعال: اگر نتیجه OK بود، فید دوباره فعال می‌شود.
     */
    public static function retestOneDisabledFeed(): void
    {
        $item = RssItem::query()
            ->where('is_active', 0)
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subDays(self::RETEST_DISABLED_EVERY_DAYS));
            })
            ->orderBy('id')
            ->first();

        if (!$item) {
            return;
        }

        $result = RssService::readRssAndSave($item->url, $item->id, $item->unique_xml_tag ?? 'link');

        if (!empty($result['ok'])) {
            $item->is_active = 1;
            $item->consecutive_failures = 0;
            $item->last_error = null;
            $item->last_synced_at = now();
            $item->save();
            Log::info('Disabled RSS feed re-enabled after successful retest', [
                'rss_item_id' => $item->id,
                'url' => $item->url,
            ]);
        }
    }

    /**
     * @return Collection
     */
    public static function getRssItemsThatShould(): Collection
    {
        // حالا شرط بررسی اینکه last_synced_at + interval_minutes کمتر از الان باشد
        return RssItem::query()
            ->whereIsActive(1)
            ->whereRaw('DATE_ADD(last_synced_at, INTERVAL interval_minutes MINUTE) <= NOW()')
            ->get();
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
