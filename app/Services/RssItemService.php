<?php

namespace App\Services;

use App\Models\RssItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class RssItemService
{
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
            $response = RssService::readRssAndSave($item->url, $item->id, $unique_field_name);

            // به‌روزرسانی ستون last_synced_at پس از اجرای موفقیت‌آمیز
            $item->last_synced_at = now();
            $item->save();
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
