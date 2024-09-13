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
        $items = self::
//        getRssItemsThatShould()
        getRssItemsThatActivated();

        if ($switch) {
            $items = self::getRssItemsForToday();
        }

//        dd($items);
        foreach ($items as $item) {
            $unique_field_name = $item->unique_xml_tag ?? 'link';
            $response = RssService::readRssAndSave($item->url, $item->id, $unique_field_name);
//            dd($response);
        }
    }

    /**
     * @return Collection
     */
    public static function getRssItemsThatShould(): Collection
    {
        return RssItem::query()
            ->whereIsActive(1)
            ->where('last_synced_at', '<', Carbon::now()->subHours(2))
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
