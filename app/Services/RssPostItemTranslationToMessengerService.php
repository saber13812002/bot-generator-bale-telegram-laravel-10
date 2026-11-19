<?php

namespace App\Services;

use App\Models\RssPostItem;
use Illuminate\Support\Facades\Log;

class RssPostItemTranslationToMessengerService
{
    public function __construct()
    {
        //
    }

    /**
     * ترجمه و ارسال محتوای تازه به کانال‌ها (ورودی خط لوله انتشار).
     *
     * قبلا در هر اجرا فقط 1 آیتر ترجمه می‌شد (ریزش بسیار کند بک‌لاگ).
     * حالا به‌صورت batch (پیش‌فرض 5، حداکثر 20) و با ترتیب قدیمی‌ترین
     * اول پردازش می‌شود تا بک‌لاگ با سرعت معقول خالی شود.
     *
     * @param int|null $limit حداکثر تعداد آیتم در این اجرا (پیش‌فرض 5)
     * @param int|null $feedId اگر تنظیم باشد فقط آیتم‌های همان فید
     * @return array{processed: int, skipped: int}
     */
    public static function run(?int $limit = 5, ?int $feedId = null): array
    {
        $limit = max(1, min(20, (int) $limit));

        $query = RssPostItem::query()
            ->whereDoesntHave('translations')
            ->with('rssItem')
            ->whereHas('rssItem', function ($q) {
                $q->where('is_active', true);
            });

        if ($feedId !== null) {
            $query->where('rss_item_id', $feedId);
        }

        $items = $query->orderBy('id')->limit($limit)->get();

        $processed = 0;
        $skipped = 0;

        foreach ($items as $rssPostItem) {
            try {
                if (!$rssPostItem->rssItem || !$rssPostItem->rssItem->is_active) {
                    $skipped++;
                    continue;
                }

                RssPostItemTranslationService::call($rssPostItem);
                $processed++;
            } catch (\Throwable $e) {
                // خطای یک آیتم نباید بقیه batch را متوقف کند
                $skipped++;
                Log::error('RssPostItemTranslationToMessengerService: item translation failed', [
                    'rss_post_item_id' => $rssPostItem->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['processed' => $processed, 'skipped' => $skipped];
    }
}
