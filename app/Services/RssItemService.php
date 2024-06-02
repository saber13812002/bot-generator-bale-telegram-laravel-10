<?php

namespace App\Services;

use App\Models\RssItem;

class RssItemService
{
    public function __construct()
    {
        //
    }

    public static function run()
    {
        $items = RssItem::query()->get();
        foreach ($items as $item) {
            $response = RssService::readRssAndSave($item->url, $item->id);
//            dd($response);
        }
    }
}
