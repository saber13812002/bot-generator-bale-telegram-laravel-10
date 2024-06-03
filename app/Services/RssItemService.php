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
            $unique_field_name = $item->unique_xml_tag ?? 'link';

            $response = RssService::readRssAndSave($item->url, $item->id, $unique_field_name);
//            dd($response);
        }
    }
}
